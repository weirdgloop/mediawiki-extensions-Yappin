/**
 * Main module for the Comments extension.
 *
 * This module creates a new Vue app, which handles displaying comments and allowing users to post new comments. It is
 * used on all wiki pages where comments should be displayed.
 *
 * @author Jayden Bailey <jayden@weirdgloop.org>
 */

const
	Vue = require( 'vue' ),
	App = require( './App.vue' );

/**
 * @return {void}
 */
function initApp() {
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#bodyContent' ).append(
		$( '<div>' ).attr( 'id', 'ext-comments-container' )
	);

	Vue.createMwApp( App )
		.mount( '#ext-comments-container' );
}

mw.commentsExt = mw.commentsExt || {};

$( () => {
	// Create the Vue app
	initApp();
} );
