<?php

namespace MediaWiki\Extension\Yappin\Models;

use MediaWiki\Extension\Yappin\CommentFactory;
use MediaWiki\Extension\Yappin\CommentHelperService;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

class Comment {
	public const TABLE_NAME = 'com_comment';

	/** @var int|null */
	public $mId = null;

	/** @var Title|null */
	private $mTitle = null;

	/** @var int */
	public $mPageId;

	/** @var UserIdentity|null */
	private $mActor = null;

	/** @var int */
	public $mActorId;

	/** @var string|null */
	public $mCreatedTimestamp = null;

	/** @var string|null */
	public $mEditedTimestamp = null;

	/** @var Comment|null */
	private $mParent = null;

	/** @var int|null */
	public $mParentId;

	/** @var UserIdentity|null */
	public $mDeletedActor = null;

	/** @var int|null */
	public $mDeletedActorId = null;

	/** @var int */
	public $mRating = 0;

	/** @var string|null */
	public $mHtml = null;

	/** @var string */
	public $mWikitext;

	/** @var IDatabase */
	public IDatabase $dbw;

	/**
	 * @internal
	 */
	public function __construct(
		private readonly LBFactory $lbFactory,
		private readonly ActorStore $actorStore,
		private readonly CommentFactory $commentFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly CommentHelperService $commentHelperService
	) {
		$this->dbw = $this->lbFactory->getPrimaryDatabase();
	}

	/**
	 * The ID of this comment
	 * @return int
	 */
	public function getId() {
		return $this->mId;
	}

	/**
	 * The wiki page the comment was posted on
	 * @return Title
	 */
	public function getTitle() {
		if ( $this->mTitle === null ) {
			$this->mTitle = $this->titleFactory->newFromID( $this->mPageId );
		}
		return $this->mTitle;
	}

	/**
	 * Sets the Title (wiki page) that this comment has been posted on
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param Title $title
	 * @return self
	 */
	public function setTitle( $title ) {
		$this->mTitle = $title;
		$this->mPageId = $this->mTitle->getId();
		return $this;
	}

	/**
	 * The actor who posted the comment
	 * @return UserIdentity
	 */
	public function getActor() {
		if ( $this->mActor === null ) {
			$this->mActor = $this->actorStore->getActorById( $this->mActorId, $this->dbw );
		}
		return $this->mActor;
	}

	/**
	 * Sets the actor who posted this comment
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param UserIdentity $user
	 * @param int|null $actorId
	 * @return self
	 */
	public function setActor( $user, $actorId = null ) {
		$this->mActor = $user;
		$this->mActorId = $actorId ?? $this->actorStore->acquireActorId( $user, $this->dbw );
		return $this;
	}

	/**
	 * The comment that is being replied to
	 * @return Comment|null
	 */
	public function getParent() {
		if ( !$this->mParent && $this->mParentId ) {
			$this->mParent = $this->commentFactory->newFromId( $this->mParentId );
		}

		return $this->mParent;
	}

	/**
	 * Sets the parent comment of this comment.
	 *
	 * A comment can only have one parent, and comments can only be nested
	 * one level deep. Once set, a comment's parent should *not* be mutated.
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param self|null $commentOrNull
	 * @return self
	 */
	public function setParent( $commentOrNull ) {
		$this->mParent = $commentOrNull;
		$this->mParentId = $commentOrNull?->getId();
		return $this;
	}

	/**
	 * Was this comment deleted?
	 * @return bool
	 */
	public function isDeleted() {
		return $this->mDeletedActorId !== null;
	}

	/**
	 * The actor who deleted the comment
	 * @return UserIdentity|null
	 */
	public function getDeletedActor() {
		if ( $this->mDeletedActorId === null || $this->mDeletedActor !== null ) {
			return $this->mDeletedActor;
		}

		$this->mDeletedActor = $this->actorStore->getActorById( $this->mDeletedActorId, $this->dbw );
		return $this->mDeletedActor;
	}

	/**
	 * Sets the user who deleted this comment.
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param UserIdentity|null $user
	 * @return Comment
	 */
	public function setDeletedActor( $user ) {
		$this->mDeletedActor = $user;
		$this->mDeletedActorId = $user ? $this->actorStore->acquireActorId( $user, $this->dbw ) : null;
		return $this;
	}

	/**
	 * The parsed HTML for the comment
	 * @return string
	 * @throws RuntimeException if called before the comment exists in the database
	 */
	public function getHtml() {
		if ( !$this->mHtml && $this->mWikitext ) {
			// An earlier version of the extension may have not stored HTML but did store wikitext
			// so we should do a one-time parse of it again and save it
			$this->mHtml = $this->commentHelperService->getCommentAsHtml(
				$this->mWikitext,
				$this->getTitle()
			);
			$this->save( false );
		}

		return $this->mHtml;
	}

	/**
	 * Sets the HTML for this comment.
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param string $html
	 * @return Comment
	 */
	public function setHtml( $html ) {
		$this->mHtml = $html;
		return $this;
	}

	/**
	 * The wikitext for the comment, used to populate the textarea when editing the comment.
	 * This field is not used to render the comment, use `Comment::getHtml` instead.
	 * @return string
	 */
	public function getWikitext() {
		return $this->mWikitext;
	}

	/**
	 * Sets the wikitext for this comment.
	 *
	 * This method returns the current Comment object for easier chaining.
	 * @param string $text
	 * @return self
	 */
	public function setWikitext( $text ) {
		$this->mWikitext = $text;
		return $this;
	}

	/**
	 * The timestamp for the comment
	 * @return string
	 */
	public function getTimestamp() {
		return $this->mCreatedTimestamp;
	}

	/**
	 * The edited timestamp for the comment
	 * @return string
	 */
	public function getEditedTimestamp() {
		return $this->mEditedTimestamp;
	}

	/**
	 * The overall rating for the comment.
	 *
	 * This is not necessarily equivalent to a SUM() of all CommentRating objects
	 * associated with this comment, and is instead used as a quick lookup,
	 * similarly to `user_editcount` in MediaWiki core.
	 *
	 * @return int
	 */
	public function getRating() {
		return $this->mRating;
	}

	/**
	 * Sets a rating for a particular user.
	 *
	 * @param UserIdentity $user
	 * @param int $rating an integer matching `-1`, `0`, or `1`
	 * @return CommentRating
	 */
	public function setRatingForUser( $user, $rating ) {
		$obj = new CommentRating( $this->actorStore, $this->lbFactory, $this->commentFactory );
		$obj->setComment( $this )
			->setActor( $user )
			->setRating( $rating )
			->save();

		return $obj;
	}

	/**
	 * Increments the current rating count for the comment. This method will update the increment the current live
	 * value in the database, reloading this Comment object with the updated value.
	 *
	 * This method should ONLY be called on comments that already exist in the database.
	 * @param int $amount
	 * @return void
	 */
	public function incrementRatingCount( $amount = 1 ) {
		$this->dbw->newUpdateQueryBuilder()
			->table( $this::TABLE_NAME )
			->set( [ 'c_rating=c_rating+' . $amount ] )
			->where( [ 'c_id' => $this->mId ] )
			->caller( __METHOD__ )->execute();

		$this->mRating = (int)$this->dbw->newSelectQueryBuilder()
			->select( 'c_rating' )
			->table( $this::TABLE_NAME )
			->where( [ 'c_id' => $this->mId ] )
			->caller( __METHOD__ )->fetchField();
	}

	/**
	 * Decrement the current rating count for the comment. This method will update the decrement the current live
	 * value in the database, reloading this Comment object with the updated value.
	 *
	 * This method should ONLY be called on comments that already exist in the database.
	 * @param int $amount
	 * @return void
	 */
	public function decrementRatingCount( $amount = 1 ) {
		$this->dbw->newUpdateQueryBuilder()
			->table( $this::TABLE_NAME )
			->set( [ 'c_rating=c_rating-' . $amount ] )
			->where( [ 'c_id' => $this->mId ] )
			->caller( __METHOD__ )->execute();

		$this->mRating = (int)$this->dbw->newSelectQueryBuilder()
			->select( 'c_rating' )
			->table( $this::TABLE_NAME )
			->where( [ 'c_id' => $this->mId ] )
			->caller( __METHOD__ )->fetchField();
	}

	/**
	 * Sets the rating for this comment. This should not typically be called manually.
	 *
	 * This method returns the current Comment object for easier chaining.
	 *
	 * @param int $rating
	 * @return self
	 */
	public function setRating( $rating ) {
		$this->mRating = $rating;
		return $this;
	}

	/**
	 * Saves this object to the database and returns the insert ID
	 * @param bool $setEditedTs
	 * @return int|null
	 */
	public function save( bool $setEditedTs = true ) {
		$isUpdate = $this->mId !== null;

		if ( !$this->mCreatedTimestamp ) {
			$this->mCreatedTimestamp = wfTimestamp( TS_ISO_8601 );
		}

		if ( $isUpdate && $setEditedTs ) {
			$this->mEditedTimestamp = wfTimestampOrNull( TS_ISO_8601, 0 );
		}

		$row = [
			'c_page' => $this->mPageId,
			'c_actor' => $this->mActorId,
			'c_parent' => $this->mParentId,
			'c_timestamp' => $this->dbw->timestamp( $this->mCreatedTimestamp ),
			'c_deleted_actor' => $this->mDeletedActorId,
			'c_rating' => $this->mRating,
			'c_html' => $this->mHtml,
			'c_wikitext' => $this->mWikitext,
			'c_edited_timestamp' => $this->dbw->timestampOrNull( $this->mEditedTimestamp )
		];

		if ( !$isUpdate ) {
			// If there is no ID for this object, then we'll presume it doesn't exist.
			$this->dbw->newInsertQueryBuilder()
				->insertInto( self::TABLE_NAME )
				->row( $row )
				->caller( __METHOD__ )
				->execute();

			// Set the ID of this object to the newly inserted object ID
			$this->mId = $this->dbw->insertId();
		} else {
			// Perform an update instead
			$this->dbw->newUpdateQueryBuilder()
				->table( self::TABLE_NAME )
				->set( $row )
				->where( [ 'c_id' => $this->mId ] )
				->caller( __METHOD__ )
				->execute();
		}
		return $this->dbw->affectedRows() ? $this->mId : null;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			'id' => $this->mId,
			'created' => wfTimestamp( TS_ISO_8601, $this->mCreatedTimestamp ),
			'edited' => wfTimestampOrNull( TS_ISO_8601, $this->mEditedTimestamp ),
			'user' => [
				'name' => $this->getActor()->getName(),
				'anon' => !$this->getActor()->isRegistered(),
				'temp' => $this->userIdentityUtils->isTemp( $this->getActor() )
			],
			'parent' => $this->mParentId,
			'deleted' => $this->getDeletedActor() ? [
				'name' => $this->getDeletedActor()->getName(),
				'id' => $this->getDeletedActor()->getId()
			] : null,
			'rating' => $this->mRating,
			'html' => $this->getHtml(),
			'wikitext' => $this->mWikitext
		];
	}
}
