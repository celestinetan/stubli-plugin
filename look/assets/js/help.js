/* stubli.app · Help: filter the index as you type. No dependencies. */
( function () {
	'use strict';
	var q = document.querySelector( '[data-sh-q]' );
	if ( ! q ) { return; }
	var items = Array.prototype.slice.call( document.querySelectorAll( '[data-sh-item]' ) );
	var groups = Array.prototype.slice.call( document.querySelectorAll( '[data-sh-group]' ) );
	var none = document.querySelector( '[data-sh-none]' );
	var run = function () {
		var words = q.value.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
		var shown = 0;
		items.forEach( function ( li ) {
			var hay = li.getAttribute( 'data-sh-words' ) || '';
			var ok = words.every( function ( w ) { return hay.indexOf( w ) > -1; } );
			li.hidden = ! ok;
			if ( ok ) { shown++; }
		} );
		groups.forEach( function ( g ) { g.hidden = ! g.querySelector( '[data-sh-item]:not([hidden])' ); } );
		if ( none ) { none.hidden = shown > 0; }
	};
	q.addEventListener( 'input', run );
	try { var start = new URLSearchParams( location.search ).get( 'q' ); if ( start ) { q.value = start; run(); } } catch ( e ) {}
}() );
