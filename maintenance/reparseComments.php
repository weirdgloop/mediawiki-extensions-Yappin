<?php

namespace MediaWiki\Extension\Yappin;

use MediaWiki\Extension\Yappin\Jobs\ReparseCommentsJob;
use MediaWiki\Extension\Yappin\Models\Comment;
use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ReparseComments extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Reparses all comments that are saved in the database using the job queue. ' .
			'This can be used after an update that changes the way that comments are parsed or a bug fix.'
		);
		$this->addOption( 'from', 'Reparse comments from a given ID onwards', false, true );
		$this->addOption( 'to', 'Reparse comments up to this ID', false, true );
		$this->setBatchSize( 500 );
	}

	public function execute() {
		$from = (int)$this->getOption( 'from', 1 );
		$to = (int)$this->getOption( 'to' );

		if ( !$to ) {
			$to = (int)$this->getReplicaDB()->newSelectQueryBuilder()
				->select( 'MAX(c_id)' )
				->from( Comment::TABLE_NAME )
				->caller( __METHOD__ )
				->fetchField();
		}

		if ( $from > $to ) {
			$this->fatalError( 'The \'from\' parameter must not be greater than the \'to\' parameter' );
		}

		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroupFactory()->makeJobQueueGroup();
		$this->output( "Scheduling jobs between $from and $to...\n" );

		$jobs = 0;
		foreach ( array_chunk( range( $from, $to ), $this->getBatchSize() ) as $batch ) {
			$job = new ReparseCommentsJob( [
				'start' => $batch[ 0 ],
				'end' => $batch[ count( $batch ) - 1 ]
			],
				$this->getServiceContainer()->getTitleFactory(),
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getService( 'Yappin.CommentHelperService' )
			);
			$jobQueueGroup->push( $job );
			$jobs++;
		}

		$this->output( "Scheduled $jobs jobs.\n" );
	}
}

$maintClass = ReparseComments::class;
require_once RUN_MAINTENANCE_IF_MAIN;
