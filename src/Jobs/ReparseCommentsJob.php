<?php

namespace MediaWiki\Extension\Yappin\Jobs;

use MediaWiki\Extension\Yappin\CommentHelperService;
use MediaWiki\Extension\Yappin\Models\Comment;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class ReparseCommentsJob extends Job {
	public function __construct(
		array $params,
		private readonly TitleFactory $titleFactory,
		private readonly IConnectionProvider $connectionProvider,
		private readonly CommentHelperService $commentHelperService
	) {
		parent::__construct( 'ReparseCommentsJob', $params );
	}

	/** @inheritDoc */
	public function run() {
		$start = $this->params[ 'start' ];
		$end = $this->params[ 'end' ];
		$db = $this->connectionProvider->getPrimaryDatabase();

		$rows = $db->newSelectQueryBuilder()
			->select( [ 'c_id', 'c_page', 'c_wikitext' ] )
			->from( Comment::TABLE_NAME )
			->where( $db->expr( 'c_id', '>=', $start ) )
			->andWhere( $db->expr( 'c_id', '<=', $end ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $rows as $row ) {
			$title = $this->titleFactory->newFromID( $row->c_page );
			if ( !$title ) {
				$this->setLastError( "Page with ID {$row->c_page} does not exist" );
				continue;
			}

			$html = $this->commentHelperService->getCommentAsHtml( $row->c_wikitext, $title );
			$db->newUpdateQueryBuilder()
				->table( Comment::TABLE_NAME )
				->set( [ 'c_html' => $html ] )
				->where( [ 'c_id' => $row->c_id ] )
				->caller( __METHOD__ )
				->execute();
		}

		return true;
	}
}
