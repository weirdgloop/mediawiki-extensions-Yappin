( function ( $, mw, OO, ve ) {
	'use strict';

	/**
	 * @class
	 * @constructor
	 * @param {jQuery} $node Node to replace with a VisualEditor
	 * @param {string} [content='']
	 */
	mw.commentsExt.ve.Editor = function ( $node, content ) {
		let modules;

		OO.EventEmitter.call( this );
		this.$node = $( $node );

		// @hack: make textarea look pending in case we didn't come from an editor switch.
		// Once this is an OO.ui.TextInputWidget we'll be able to use real PendingElement
		// functionality for this.
		this.$node
			.prop( 'disabled', true )
			.addClass( 'oo-ui-texture-pending' );

		// The main module should already be loaded.
		modules = mw.config.get( 'wgVisualEditorConfig' ).pluginModules.filter( mw.loader.getState );

		// load dependencies & init editor
		mw.loader.using( modules, this.init.bind( this, content || '' ) );
	};

	OO.mixinClass( mw.commentsExt.ve.Editor, OO.EventEmitter );

	mw.commentsExt.ve.Editor.prototype.initCallbacks = [];

	mw.commentsExt.ve.Editor.prototype.createTarget = function () {
		let self = this, $wrapperNode, maxHeight;

		this.target = new mw.commentsExt.ve.Target( this.$node, $( this.$node ).val() );

		// Various tasks to do once VE has finished being applied.
		self.target.on( 'editor-ready', () => {
			// Catch keyup events on surface to comply with
			// saveAndContinue button state and changes warning.
			self.target.getSurface().getView().on( 'keyup', () => {
				self.$node.trigger( 'change' );
			} );

			// Catch keyup events on raw textarea to use changes
			// warning on page reload.
			self.target.$node.on( 'keyup', () => {
				self.$node.trigger( 'change' );
			} );

			// Set max height of the textarea, if it was specified.
			$wrapperNode = self.$node.parent( '.ve-area-wrapper' );
			maxHeight = $wrapperNode.attr( 'data-max-height' );
			if ( maxHeight !== undefined ) {
				$wrapperNode.find( '.ve-ce-documentNode' ).css( 'max-height', maxHeight )
					.css( 'overflow-y', 'auto' );
			}
		} );

		return this.target;
	};

	/**
	 * Callback function, executed after all VE dependencies have been loaded.
	 *
	 * @param {string} [content='']
	 */
	mw.commentsExt.ve.Editor.prototype.init = function ( content ) {
		this.target = this.createTarget();

		$.each( this.initCallbacks, ( k, callback ) => {
			callback.apply( this );
		} );
	};

	mw.commentsExt.ve.Editor.prototype.destroy = function () {
		if ( this.target ) {
			this.target.destroy();
		}

		// re-display original node
		this.$node.show();
	};

	/**
	 * Gets HTML of Flow field
	 *
	 * @return {string}
	 */
	mw.commentsExt.ve.Editor.prototype.getRawContent = function () {
		let doc, html;

		// If we haven't fully loaded yet, just return nothing.
		if ( !this.target ) {
			return '';
		}

		// get document from ve
		doc = ve.dm.converter.getDomFromModel( this.dmDoc );

		// document content will include html, head & body nodes; get only content inside body node
		html = ve.properInnerHtml( $( doc.documentElement ).find( 'body' )[ 0 ] );
		return html;
	};

	/**
	 * Checks if the document is empty
	 *
	 * @return {boolean} True if and only if it's empty
	 */
	mw.commentsExt.ve.Editor.prototype.isEmpty = function () {
		if ( !this.dmDoc ) {
			return true;
		}

		// Per Roan
		return this.dmDoc.data.countNonInternalElements() <= 2;
	};

	mw.commentsExt.ve.Editor.prototype.focus = function () {
		if ( !this.target ) {
			this.initCallbacks.push( function () {
				this.focus();
			} );
			return;
		}

		this.target.surface.getView().focus();
	};

	mw.commentsExt.ve.Editor.prototype.moveCursorToEnd = function () {
		let data, cursorPos;

		if ( !this.target ) {
			this.initCallbacks.push( function () {
				this.moveCursorToEnd();
			} );
			return;
		}

		data = this.target.surface.getModel().getDocument().data;
		cursorPos = data.getNearestContentOffset( data.getLength(), -1 );

		this.target.surface.getModel().setSelection( new ve.Range( cursorPos ) );
	};

	// Static fields

	/**
	 * Type of content to use
	 *
	 * @member {string}
	 */
	mw.commentsExt.ve.Editor.static.format = 'html';

	/**
	 * Name of this editor
	 *
	 * @member string
	 */
	mw.commentsExt.ve.Editor.static.name = 'visualeditor';

	// Static methods

	mw.commentsExt.ve.Editor.static.isSupported = function () {
		const isMobileTarget = ( mw.config.get( 'skin' ) === 'minerva' );

		/* global VisualEditorSupportCheck */
		return !!(
			!isMobileTarget &&
			mw.loader.getState( 'ext.visualEditor.core' ) &&
			window.VisualEditorSupportCheck && VisualEditorSupportCheck
		);
	};

}( jQuery, mediaWiki, OO, ve ) );
