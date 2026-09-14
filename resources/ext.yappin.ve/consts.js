const TOOLBAR_CONFIG = [
	{
		header: OO.ui.deferMsg( 'visualeditor-toolbar-text-style' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		include: [ 'bold', 'italic', 'moreTextStyle' ]
	}, {
		include: [ 'link' ]
	}, {
		header: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		type: 'list',
		icon: 'listBullet',
		include: { group: 'structure' },
		demote: [ 'outdent', 'indent' ]
	}, {
		header: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		type: 'list',
		icon: 'add',
		include: [ 'insertTable', 'specialCharacter' ]
	}
];

module.exports = {
	TOOLBAR_CONFIG
};
