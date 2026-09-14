<?php

namespace MediaWiki\Extension\Yappin;

use MediaWiki\Config\Config;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\Parsoid\ParsoidParserFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\WANObjectCache;

class CommentHelperService {
	public function __construct(
		private readonly ParsoidParserFactory $parserFactory,
		private readonly WANObjectCache $wanCache,
		private readonly UserFactory $userFactory,
		private readonly Config $config
	) {
	}

	/**
	 * Gets the HTML representation of a comment by parsing some wikitext.
	 * @param string $wikitext
	 * @param PageReference $page
	 * @return string
	 */
	public function getCommentAsHtml( string $wikitext, PageReference $page ): string {
		$parser = $this->parserFactory->create();
		$opts = ParserOptions::newFromAnon();
		$output = $parser->parse( $wikitext, $page, $opts );
		return $output->runOutputPipeline( $opts, [ 'unwrap' => true ] )->getContentHolderText();
	}

	/**
	 * Runs wikitext for a comment through the AbuseFilter extension's filters
	 * @param UserIdentity $user
	 * @param Title $title
	 * @param string $wikitext
	 * @return Status|null
	 */
	public function checkAbuseFilter( UserIdentity $user, Title $title, string $wikitext ): Status|null {
		if ( !$this->config->get( 'CommentsUseAbuseFilter' ) ||
			!ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) ) {
			return Status::newGood();
		}

		$user = $this->userFactory->newFromUserIdentity( $user );
		$vars = AbuseFilterServices::getVariableGeneratorFactory()
			->newGenerator()
			->addUserVars( $user )
			->addTitleVars( $title, 'page' )
			->addGenericVars()
			->getVariableHolder();
		$vars->setVar( 'action', 'comment' );
		$vars->setVar( 'new_wikitext', $wikitext );
		$vars->setLazyLoadVar( 'new_size', 'length', [ 'length-var' => 'new_wikitext' ] );

		$rf = AbuseFilterServices::getFilterRunnerFactory();
		$runner = $rf->newRunner( $user, $title, $vars, 'default' );
		return $runner->run();
	}
}
