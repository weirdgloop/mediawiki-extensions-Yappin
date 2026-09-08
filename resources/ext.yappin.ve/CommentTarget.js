const { TOOLBAR_CONFIG } = require( './consts.js' );
const registries = require( './registries.js' );

( function ( mw, OO, ve ) {
	'use strict';

	/**
	 * Inherits from the standard VE target.
	 *
	 * @param node
	 * @param html
	 * @class
	 * @extends ve.init.mw.Target
	 */
	mw.commentsExt.ve.Target = function ( node, html ) {
		const config = {};
		config.toolbarConfig = {};
		config.toolbarConfig.actions = true;

		this.$node = node;

		this.toolbarAutoHide = false;
		this.toolbarPosition = 'top';
		config.toolbarConfig.floatable = false;

		mw.commentsExt.ve.Target.parent.call( this, config );
		this.dummyToolbar = true;

		this.init( html );
	};

	OO.inheritClass( mw.commentsExt.ve.Target, ve.init.mw.Target );

	mw.commentsExt.ve.Target.prototype.init = function ( html ) {
		this.createWithHtmlContent( html );

		$( this.$node )
			.prop( 'disabled', false )
			.removeClass( 'oo-ui-texture-pending' );
	};

	// Static

	mw.commentsExt.ve.Target.static.name = 'commentsExt';

	mw.commentsExt.ve.Target.static.toolbarGroups = ( function () {
		return TOOLBAR_CONFIG;
	}() );

	mw.commentsExt.ve.Target.static.actionGroups = [];

	// Allow pasting links
	mw.commentsExt.ve.Target.static.importRules = ve.copy( mw.commentsExt.ve.Target.static.importRules );
	mw.commentsExt.ve.Target.static.importRules.external.blacklist = OO.simpleArrayDifference(
		mw.commentsExt.ve.Target.static.importRules.external.blacklist,
		[ 'link/mwExternal' ]
	);

	/**
	 * Create a new surface with VisualEditor, and add it to the target.
	 *
	 * @param {string} content text to initiate content, in html format
	 */
	mw.commentsExt.ve.Target.prototype.createWithHtmlContent = function ( content ) {
		const target = this;

		this.addSurface(
			ve.dm.converter.getModelFromDom(
				ve.createDocumentFromHtml( content )
			)
		);
		// Append the target to the document
		$( this.$node ).before( this.$element );

		$( this.$node ).hide()
			.removeClass( 'oo-ui-texture-pending' ).prop( 'disabled', false );

		this.setDir();

		target.once( 'surfaceReady', () => {
			// Focus the VE surface when it is ready
			target.getSurface().getView().focus();
		} );

		target.getToolbar().onWindowResize();
		target.onToolbarResize();
		target.onContainerScroll();

		target.emit( 'editor-ready' );
	};

	mw.commentsExt.ve.Target.prototype.getPageName = function () {
		return mw.config.get( 'wgPageName' );
	};

	mw.commentsExt.ve.Target.prototype.escapePipesInTables = function ( text ) {
		let lines = text.split( '\n' ), i, curLine, withinTable = false;

		// This algorithm will hopefully work for all cases except
		// when there are template calls within the table, and those
		// template calls include a pipe at the beginning of a line.
		// That is hopefully a rare case (a template call within a
		// table within a template call) that hopefully does not
		// justify creating more complex handling.
		for ( i = 0; i < lines.length; i++ ) {
			curLine = lines[ i ];
			// start of table is {|, but could be also escaped, like this: {{{!}}
			if ( curLine.indexOf( '{|' ) === 0 || curLine.indexOf( '{{{!}}' ) === 0 ) {
				withinTable = true;
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			} else if ( withinTable && curLine.indexOf( '|' ) === 0 ) {
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			}
			// Table caption case (`|+`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && curLine.includes( '|+' ) ) {
				lines[ i ] = curLine.replace( /\|\+/g, '{{!}}+' );
			}
			// colspan/rowspan case (`|rowspan=`/`|colspan=`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && ( curLine.includes( 'colspan' ) || curLine.includes( 'rowspan' ) ) ) {
				lines[ i ] = curLine.replace( /(colspan|rowspan)="(\d+?)"\s{0,}\|/, '$1="$2" {{!}}' ).replace( /^\s{0,}\|/, '{{!}} ' );
			}
			if ( curLine.indexOf( '|}' ) === 0 ) {
				withinTable = false;
			}
		}
		return lines.join( '\n' );
	};

	mw.commentsExt.ve.Target.prototype.setDir = function () {
		const view = this.surface.getView(),
			dir = $( 'body' ).is( '.rtl' ) ? 'rtl' : 'ltr';
		if ( view ) {
			view.getDocument().setDir( dir );
		}
	};

	mw.commentsExt.ve.Target.prototype.getSurfaceConfig = function ( config ) {
		return mw.commentsExt.ve.Target.super.prototype.getSurfaceConfig.call( this, ve.extendObject( {
			sequenceRegistry: registries.sequenceRegistry,
			commandRegistry: registries.commandRegistry
		}, config ) );
	};

	ve.init.mw.targetFactory.register( mw.commentsExt.ve.Target );

}( mediaWiki, OO, ve ) );
