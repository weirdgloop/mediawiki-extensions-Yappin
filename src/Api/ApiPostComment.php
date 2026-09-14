<?php

namespace MediaWiki\Extension\Yappin\Api;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Yappin\CommentFactory;
use MediaWiki\Extension\Yappin\CommentHelperService;
use MediaWiki\Extension\Yappin\Utils;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Message\Message;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\TempUser\TempUserCreator;
use MediaWiki\User\UserFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPostComment extends SimpleHandler {
	private StatusFormatter $statusFormatter;

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly CommentFactory $commentFactory,
		private readonly Config $config,
		private readonly TempUserCreator $tempUserCreator,
		private readonly PageRestHelperFactory $pageRestHelperFactory,
		private readonly CommentHelperService $commentHelperService,
		readonly FormatterFactory $formatterFactory,
		private readonly UserFactory $userFactory
	) {
		$this->statusFormatter = $formatterFactory->getStatusFormatter( RequestContext::getMain() );
	}

	/**
	 * @throws HttpException
	 */
	public function run(): Response {
		$auth = $this->getAuthority();
		$user = $this->userFactory->newFromAuthority( $auth );

		$canComment = Utils::canUserComment( $user );
		if ( $canComment !== true ) {
			throw new LocalizedHttpException( $canComment, 403 );
		}

		$body = $this->getValidatedBody();
		'@phan-var array $body';
		$pageId = (int)$body[ 'pageid' ];
		$parentId = (int)$body[ 'parentid' ];

		// Must either provide a page ID or a parent ID
		if ( !$pageId && !$parentId ) {
			throw new HttpException( 'Must provide either page ID or parent ID' );
		}

		$html = trim( (string)$body[ 'html' ] );
		$wikitext = trim( (string)$body[ 'wikitext' ] );
		if ( !$html && !$wikitext ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-submit-error-empty' ), 400 );
		}

		$parent = null;
		if ( $parentId ) {
			$parent = $this->commentFactory->newFromId( $parentId );

			if ( $parent->isDeleted() ) {
				throw new LocalizedHttpException(
					new MessageValue( 'yappin-submit-error-parent-missing', [ $parentId ] ), 400 );
			}
			if ( $parent->getParent() ) {
				throw new LocalizedHttpException(
					new MessageValue( 'yappin-submit-error-parent-hasparent' ), 400 );
			}

			$pageId = $parent->getTitle()->getId();
		}

		$page = $this->titleFactory->newFromID( $pageId );
		if ( !$page || !$page->exists() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-submit-error-page-missing', [ $pageId ] ), 400 );
		}

		if ( !Utils::isCommentsEnabled( $this->config, $page ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-submit-error-comments-disabled' ), 400 );
		}

		// Handle temporary users. Use "edit" action as it's the only one supported right now.
		if ( $this->tempUserCreator->shouldAutoCreate( $this->getAuthority(), 'edit' ) ) {
			$status = $this->tempUserCreator->create(
				null,
				$this->getSession()->getRequest()
			);
			if ( $status->isOK() ) {
				$user = $status->getUser();
			} else {
				$msg = $this->statusFormatter->getMessage( $status );
				if ( $msg->getKey() === 'acct_creation_throttle_hit' ) {
					$msg = new Message( 'yappin-generic-error-tempuser-throttle', $msg->getParams() );
				}
				throw new LocalizedHttpException( MessageValue::newFromSpecifier( $msg ), 400 );
			}
		} else {
			$user = $this->getAuthority()->getUser();
		}

		// Create a new comment
		$comment = $this->commentFactory->newEmptyComment()
			->setTitle( $page )
			->setActor( $user )
			->setParent( $parent );

		if ( $html ) {
			// This is a little silly but to sanitise the HTML we're going to parse it to wikitext and back again
			$wikitext = $this->pageRestHelperFactory->newHtmlInputTransformHelper( [], $page, $html )
				->getContent()->serialize();
		}
		$html = $this->commentHelperService->getCommentAsHtml(
			$wikitext,
			$page
		);

		$comment->setWikitext( $wikitext );
		$comment->setHtml( $html );
		$af = $this->commentHelperService->checkAbuseFilter( $user, $page, $wikitext );

		if ( !$af->isOK() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-submit-error-spam' ), 400
			);
		}

		$comment->save();

		return $this->getResponseFactory()->createJson( [
			'comment' => $comment->toArray()
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'pageid' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false
			],
			'parentid' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false
			],
			'html' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			],
			'wikitext' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}
}
