<?php

namespace MediaWiki\Extension\Yappin\Api;

use InvalidArgumentException;
use LogicException;
use MediaWiki\Extension\Yappin\CommentFactory;
use MediaWiki\Extension\Yappin\CommentsPager;
use MediaWiki\Extension\Yappin\Models\Comment;
use MediaWiki\Extension\Yappin\Utils;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\ActorStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;

class ApiGetCommentById extends SimpleHandler {
	private IReadableDatabase $dbr;

	public function __construct(
		private readonly CommentFactory $commentFactory,
		private readonly ActorStore $actorStore,
		LBFactory $factory
	) {
		$this->dbr = $factory->getReplicaDatabase();
	}

	/**
	 * @param array{c: Comment, ur: int, ours: bool, p: ?array{title: string, ns: int, id: int}, num_children: int} $r
	 * @return array
	 */
	private function getCommentDataFromResult( $r ) {
		return $r['c']->toArray() + [
			'children' => [],
			'userRating' => $r[ 'ur' ],
			'ours' => $r[ 'ours' ],
			'page' => $r[ 'p' ]
		];
	}

	/**
	 * @throws HttpException
	 */
	public function run(): Response {
		$params = $this->getValidatedParams();
		$commentId = $params[ 'commentid' ];
		$showDeleted = Utils::canUserModerate( $this->getAuthority() );

		// Do not use ActorStore::acquireActorId, otherwise a new actor ID will be made for every anonymous page view
		$actor = $this->actorStore->findActorId( $this->getAuthority()->getUser(), $this->dbr );

		try {
			$comment = $this->commentFactory->newFromId( $commentId );
		} catch ( InvalidArgumentException ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-comment-missing', [ $commentId ] ), 400
			);
		}

		if ( !Utils::canUserModerate( $this->getAuthority() )
			&& $comment->isDeleted() && $comment->getActor() !== $actor ) {
			throw new LocalizedHttpException(
				new MessageValue( 'yappin-generic-error-comment-missing', [ $commentId ] ), 400
			);
		}

		$parentId = $comment->mParentId;
		$targetId = $parentId === null ? $comment->getId() : $parentId;

		$pager = new CommentsPager(
			[
				'includeDeleted' => $showDeleted
			],
			$actor,
			$params[ 'sort' ],
		);

		$pager->setLimit( 1 );
		$res = $pager->fetchResultsForParent( $targetId );

		/** @var Comment[] $comments */
		$childComments = [];

		$parent = null;
		foreach ( $res as $r ) {
			$data = $this->getCommentDataFromResult( $r );
			if ( $r['c']->mId !== $targetId ) {
				// If this is a child comment, add it to the child comments array for processing later
				$childComments[] = $data;
			} else {
				$parent = $data;
			}
		}
		if ( !is_array( $parent ) ) {
			throw new LogicException( 'Expected $parent to be an array!' );
		}

		return $this->getResponseFactory()->createJson( [
			'comment' => array_merge( $parent, [
				'children' => $childComments
			] ),
			// Piggyback off this API call to return some extra info about the logged in user which the UI will use
			'isMod' => $showDeleted
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'commentid' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'sort' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					CommentsPager::SORT_RATING_ASC,
					CommentsPager::SORT_RATING_DESC,
					CommentsPager::SORT_DATE_ASC,
					CommentsPager::SORT_DATE_DESC
				],
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => CommentsPager::SORT_DATE_DESC
			]
		];
	}
}
