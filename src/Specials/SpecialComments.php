<?php

namespace MediaWiki\Extension\Yappin\Specials;

use MediaWiki\Extension\Yappin\Utils;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Implement Special:Comments. It is essentially a dummy special page that simply loads our module, which then fetches
 * all of the comments on the wiki. Users without JS enabled will see a message telling them to enable it.
 */
class SpecialComments extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Comments' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		if ( $subPage ) {
			$this->getOutput()->redirect(
				$this->getPageTitle()->createFragmentTarget( 'user=' . $subPage )->getFullURL()
			);
		}

		$out = $this->getOutput();
		$this->setHeaders();

		Utils::loadCommentsModule( $out );
		$out->addHTML(
			Html::element( 'noscript', [], $this->msg( 'yappin-no-script' )->text() )
		);
	}

	/**
	 * @return string
	 */
	public function getGroupName() {
		return 'pages';
	}
}
