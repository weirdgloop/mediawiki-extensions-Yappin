<?php

namespace MediaWiki\Extension\Yappin;

use InvalidArgumentException;
use MediaWiki\Extension\Yappin\Models\Comment;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use stdClass;
use Wikimedia\Rdbms\LBFactory;

class CommentFactory {
	public function __construct(
		private readonly LBFactory $lbFactory,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly CommentHelperService $commentHelperService
	) {
	}

	/**
	 * Create a new empty Comment object
	 * @return Comment
	 */
	public function newEmptyComment() {
		return new Comment(
			$this->lbFactory,
			$this->actorStoreFactory->getActorStore(),
			$this,
			$this->titleFactory,
			$this->userIdentityUtils,
			$this->commentHelperService
		);
	}

	/**
	 * Create a new Comment object from a database row
	 * @param stdClass $row
	 * @param UserIdentity|null $user optional, instantiates the comment object with a UserIdentity object
	 * @return Comment
	 */
	public function newFromRow( $row, $user = null ) {
		$comment = $this->newEmptyComment();
		$comment->mId = (int)$row->c_id;
		$comment->mPageId = (int)$row->c_page;

		if ( $user !== null ) {
			$comment->setActor( $user, (int)$row->c_actor );
		} else {
			$comment->mActorId = (int)$row->c_actor;
		}

		$parentId = (int)$row->c_parent;
		if ( $parentId ) {
			$comment->mParentId = $parentId;
		}

		$comment->mCreatedTimestamp = wfTimestamp( TS_MW, $row->c_timestamp );
		$comment->mEditedTimestamp = wfTimestampOrNull( TS_MW, $row->c_edited_timestamp );

		$comment->mDeletedActorId = $row->c_deleted_actor;
		$comment->mWikitext = (string)$row->c_wikitext;
		$comment->mHtml = (string)$row->c_html;
		$comment->mRating = (int)$row->c_rating;

		return $comment;
	}

	/**
	 * Create a new Comment object from a given ID. The ID should already exist in the database.
	 * @param int $id
	 * @return Comment
	 * @throws InvalidArgumentException
	 */
	public function newFromId( $id ) {
		$db = $this->lbFactory->getPrimaryDatabase();
		$row = $db->newSelectQueryBuilder()
			->fields( '*' )
			->from( 'com_comment' )
			->where( [ 'c_id' => $id ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			throw new InvalidArgumentException( "No comment found with ID: $id" );
		}

		return $this->newFromRow( $row );
	}
}
