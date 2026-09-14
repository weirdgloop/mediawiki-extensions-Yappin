const SORT_OPTIONS = [
	{ label: mw.message( 'yappin-sort-highest-rated' ).text(), value: 'sort_rating_desc' },
	{ label: mw.message( 'yappin-sort-newest' ).text(), value: 'sort_date_desc' },
	{ label: mw.message( 'yappin-sort-oldest' ).text(), value: 'sort_date_asc' }
];

/**
 * @param {HTMLElement|jQuery} el
 * @return {boolean}
 */
const isElementInView = ( el ) => {
	if ( el instanceof jQuery ) {
		el = el[ 0 ];
	}

	const rect = el.getBoundingClientRect();

	return (
		rect.top >= 0 &&
		rect.left >= 0 &&
		rect.bottom <= ( window.innerHeight || document.documentElement.clientHeight ) &&
		rect.right <= ( window.innerWidth || document.documentElement.clientWidth )
	);
};

module.exports = {
	SORT_OPTIONS,
	isElementInView
};
