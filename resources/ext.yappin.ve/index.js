/**
 * Comments extension's implementation of VisualEditor. We require that VisualEditor is installed for this extension to
 * work. Some of the code in this module is similar to DiscussionTools implementation of VE, and some is also inspired
 * by the VEForAll extension.
 *
 * @author Jayden Bailey <jayden@weirdgloop.org>
 */

mw.commentsExt = mw.commentsExt || {};
mw.commentsExt.ve = mw.commentsExt.ve || {};

require( './MWWikitextWarningCommand.js' );
require( './CommentTarget.js' );
require( './CommentEditor.js' );
