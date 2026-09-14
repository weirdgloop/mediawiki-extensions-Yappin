<?php

namespace MediaWiki\Extension\Yappin;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Message\MessageValue;

class Utils {
	/**
	 * If the user cannot comment, this method returns a MessageValue object indicating why.
	 * @param User $user
	 * @return MessageValue|true
	 */
	public static function canUserComment( $user ) {
		if ( !$user->isAllowed( 'comments' ) ) {
			return new MessageValue( 'yappin-submit-error-noperm' );
		}

		$block = $user->getBlock();
		if ( $block && ( $block->isSitewide() || $block->appliesToRight( 'comments' ) ) ) {
			return new MessageValue( 'yappin-submit-error-blocked' );
		}

		if ( $user->pingLimiter( 'comments' ) ) {
			return new MessageValue( 'actionthrottledtext' );
		}

		return true;
	}

	/**
	 * Returns whether the given user can moderate comments or not.
	 * @param User|Authority $userOrAuthority
	 * @return bool
	 */
	public static function canUserModerate( $userOrAuthority ) {
		return $userOrAuthority->isAllowed( 'comments-manage' );
	}

	/**
	 * @param OutputPage $out
	 * @return void
	 */
	public static function loadCommentsModule( OutputPage $out ) {
		// On desktop, load VE dependencies. On mobile, we will just use a normal <input> for writing a comment.
		$services = MediaWikiServices::getInstance();
		if ( !(
			ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView()
		) ) {
			$out->addModules( [ 'ext.yappin.ve.desktop' ] );
		}

		$out->addModules( [ 'ext.yappin.main' ] );
	}

	/**
	 * Given a Title object, should comments be enabled for it?
	 * @param Config $config
	 * @param Title $title
	 * @return bool
	 */
	public static function isCommentsEnabled( Config $config, Title $title ) {
		$enabledNs = $config->get( 'CommentsEnabledNamespaces' );

		if ( empty( $enabledNs[ $title->getNamespace() ] ) ) {
			return false;
		}
		if ( $title->isTalkPage() ) {
			return false;
		}
		if ( $title->isSpecialPage() ) {
			return false;
		}
		if ( !$title->exists() ) {
			return false;
		}
		if ( !$config->get( 'CommentsShowOnMainPage' ) && $title->isMainPage() ) {
			return false;
		}

		return true;
	}
}
