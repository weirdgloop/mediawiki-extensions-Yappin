<?php

namespace MediaWiki\Extension\Yappin\Api;

use InvalidArgumentException;
use MediaWiki\Extension\Yappin\CommentFactory;
use MediaWiki\Extension\Yappin\CommentHelperService;
use MediaWiki\Extension\Yappin\Utils;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEditComment extends SimpleHandler {
	public function __construct(
		private readonly CommentFactory $commentFactory,
		private readonly PageRestHelperFactory $pageRestHelperFactory,
		private readonly CommentHelperService $commentHelperService,
		private readonly UserFactory $userFactory
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run(): Response {
		if ( $this->getRequest()->getMethod() === 'PUT' ) {
			return $this->runEditComment();
		} else {
			return $this->runDeleteComment();
		}
	}

	/**
	 * @throws HttpException
	 */
	private function runEditComment(): Response {
		$auth = $this->getAuthority();
		$user = $this->userFactory->newFromAuthority( $auth );

		$canComment = Utils::canUserComment( $user );
		if ( $canComment !== true ) {
			throw new LocalizedHttpException( $canComment, 403 );
		}

		$body = $this->getValidatedBody();
		'@phan-var array $body';
		$params = $this->getValidatedParams();
		$commentId = (int)$params[ 'commentid' ];

		$html = trim( (string)$body[ 'html' ] );
		$wikitext = trim( (string)$body[ 'wikitext' ] );
		if ( !$html && !$wikitext ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-submit-error-empty' ), 400 );
		}

		try {
			$comment = $this->commentFactory->newFromId( $commentId );
		} catch ( InvalidArgumentException ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-comment-missing', [ $commentId ] ), 400
			);
		}

		if ( $comment->isDeleted() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-comment-missing', [ $commentId ] ), 400
			);
		}

		$user = $this->getAuthority()->getUser();
		if ( $comment->getActor()->getId() !== $user->getId() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-notself' ), 400
			);
		}

		if ( $html ) {
			// This is a little silly but to sanitise the HTML we're going to parse it to wikitext and back again
			$wikitext = $this->pageRestHelperFactory->newHtmlInputTransformHelper( [], $comment->getTitle(), $html )
				->getContent()->serialize();
		}
		$html = $this->commentHelperService->getCommentAsHtml(
			$wikitext,
			$comment->getTitle()
		);

		$comment->setWikitext( $wikitext );
		$comment->setHtml( $html );
		$af = $this->commentHelperService->checkAbuseFilter( $user, $comment->getTitle(), $wikitext );

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
	 * @throws HttpException
	 */
	private function runDeleteComment(): Response {
		$body = $this->getValidatedBody();
		'@phan-var array $body';
		$params = $this->getValidatedParams();
		$commentId = (int)$params[ 'commentid' ];
		$delete = (bool)$body[ 'delete' ];

		try {
			$comment = $this->commentFactory->newFromId( $commentId );
		} catch ( InvalidArgumentException ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-comment-missing', [ $commentId ] ), 400
			);
		}

		$ownComment = $comment->getActor()->equals( $this->getAuthority()->getUser() );
		$isMod = Utils::canUserModerate( $this->getAuthority() );

		if ( $ownComment && $delete ) {
			$comment->setDeletedActor( $comment->getActor() );
		} elseif ( $isMod ) {
			$comment->setDeletedActor( $delete ? $this->getAuthority()->getUser() : null );
		} else {
			// No permission
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-notself' ), 400
			);
		}

		$comment->save( false );

		return $this->getResponseFactory()->createJson( [
			'deleted' => $comment->getDeletedActor() ? [
				'name' => $comment->getDeletedActor()->getName(),
				'id' => $comment->getDeletedActor()->getId()
			] : null
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		if ( $this->getRequest()->getMethod() === 'PUT' ) {
			return [
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
		} else {
			return [
				'delete' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_TYPE => 'boolean',
					ParamValidator::PARAM_REQUIRED => true
				]
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings(): array {
		return [
			'commentid' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}
}
