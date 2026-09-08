/**
 * The code in this file creates new registries for VE so that we can override specific behaviour without impacting
 * the normal VisualEditor.
 *
 * Adapted from the DiscussionTools extension
 */

// Adapted from ve.ui.MWWikitextDataTransferHandlerFactory
function importRegistry( parent, child ) {
	let name;
	// Copy existing items
	for ( name in parent.registry ) {
		child.register( parent.registry[ name ] );
	}
	// Copy any new items when they're added
	parent.on( 'register', ( n, data ) => {
		child.register( data );
	} );
}

const sequenceRegistry = new ve.ui.SequenceRegistry();
importRegistry( ve.ui.sequenceRegistry, sequenceRegistry );

const commandRegistry = new ve.ui.CommandRegistry();
importRegistry( ve.ui.commandRegistry, commandRegistry );

// Disable sequences that should not appear in the context of a comment
sequenceRegistry.unregister( 'wikitextHeading' );
sequenceRegistry.unregister( 'wikitextComment' );
sequenceRegistry.unregister( 'wikitextTemplate' );

// Override the wikitext warning message to our own message
commandRegistry.unregister( 'mwWikitextWarning' );
commandRegistry.register( new mw.commentsExt.ve.MWWikitextWarningCommand() );

module.exports = {
	sequenceRegistry,
	commandRegistry
};
