/* stubli.app · How Stubli works: each chapter plays out as you scroll. No dependencies. */
( function () {
	'use strict';
	var page = document.querySelector( '.st-guide' );
	if ( ! page ) { return; }
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var $$ = function ( s, el ) { return Array.prototype.slice.call( ( el || page ).querySelectorAll( s ) ); };

	$$( '[data-g-chapter]' ).forEach( function ( ch ) {
		var steps = $$( '.st-guide-step', ch );
		var screens = $$( '[data-g-screen]', ch );
		var says = $$( '[data-g-say]', ch );
		var dotsBox = ch.querySelector( '.sg-dots' );
		var diagram = ch.querySelector( '.sg-diagram' );
		if ( ! steps.length ) { return; }
		steps.forEach( function ( s, k ) {
			var no = document.createElement( 'small' );
			no.className = 'sg-stepno';
			no.textContent = ( k + 1 ) + ' / ' + steps.length;
			s.insertBefore( no, s.firstChild );
			if ( dotsBox ) { dotsBox.appendChild( document.createElement( 'i' ) ); }
		} );
		var dots = dotsBox ? $$( 'i', dotsBox ) : [];
		ch.style.setProperty( '--n', steps.length );
		ch.classList.add( 'is-scrolly' );
		var types = $$( '[data-g-type]', ch ).map( function ( el ) { return { el: el.firstChild, text: el.getAttribute( 'data-g-type' ) }; } );
		var cur = -1;
		var paint = function () {
			var r = ch.getBoundingClientRect();
			var span = ch.offsetHeight - window.innerHeight;
			var p = Math.min( 1, Math.max( 0, -r.top / ( span || 1 ) ) );
			var x = p * steps.length;
			var i = Math.min( steps.length - 1, Math.floor( x ) );
			var sub = Math.min( 1, x - i );
			if ( i !== cur ) {
				cur = i;
				steps.forEach( function ( s, k ) { s.classList.toggle( 'is-on', k === i ); } );
				screens.forEach( function ( s, k ) { s.classList.toggle( 'is-on', k === i ); } );
				says.forEach( function ( s, k ) { s.classList.toggle( 'is-on', k === i ); } );
				dots.forEach( function ( d, k ) { d.classList.toggle( 'is-on', k === i ); d.classList.toggle( 'is-done', k < i ); } );
				if ( diagram ) { diagram.setAttribute( 'data-beat', i ); }
			}
			// The details inside a screen follow the scroll: typing, choosing.
			types.forEach( function ( t ) {
				var on = t.el && t.el.parentNode.closest( '.is-on' );
				if ( on && t.el ) { t.el.textContent = still ? t.text : t.text.slice( 0, Math.ceil( Math.min( 1, sub * 1.6 ) * t.text.length ) ); }
			} );
			var who = $$( '[data-g-who]', ch );
			if ( who.length ) {
				var friends = sub > .5;
				who.forEach( function ( w ) { w.classList.toggle( 'is-on', ( 'friends' === w.getAttribute( 'data-g-who' ) ) === friends ); } );
			}
		};
		var queued = false;
		window.addEventListener( 'scroll', function () { if ( ! queued ) { queued = true; requestAnimationFrame( function () { queued = false; paint(); } ); } }, { passive: true } );
		window.addEventListener( 'resize', paint );
		paint();

		// Underneath: letters travel along the path between the houses.
		if ( diagram ) {
			var path = diagram.querySelector( '.sg-path' );
			var len = path ? path.getTotalLength() : 0;
			var letters = { 1: diagram.querySelector( '.sg-letter-in' ), 2: diagram.querySelector( '.sg-letter-out' ), 3: diagram.querySelector( '.sg-letter-friend' ) };
			var place = function ( el, t ) {
				if ( ! el || ! len ) { return; }
				var pt = path.getPointAtLength( len * t );
				el.setAttribute( 'transform', 'translate(' + pt.x.toFixed( 1 ) + ' ' + ( pt.y - 4 ).toFixed( 1 ) + ')' );
			};
			Object.keys( letters ).forEach( function ( k ) { place( letters[ k ], .5 ); } );
			var visible = false, t0 = 0;
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) { es.forEach( function ( e ) { visible = e.isIntersecting; } ); } ).observe( diagram );
			}
			var loop = function ( now ) {
				requestAnimationFrame( loop );
				if ( ! visible || still ) { return; }
				if ( ! t0 ) { t0 = now; }
				var beat = +diagram.getAttribute( 'data-beat' );
				var t = ( ( now - t0 ) % 2600 ) / 2600;
				var e = t < .8 ? t / .8 : 1;
				e = e < .5 ? 2 * e * e : 1 - Math.pow( -2 * e + 2, 2 ) / 2;
				if ( 1 === beat ) { place( letters[ 1 ], 1 - e ); }
				if ( 2 === beat ) { place( letters[ 2 ], e ); }
				if ( 3 === beat ) { place( letters[ 3 ], e ); }
				diagram.classList.toggle( 'is-lit-a', 1 === beat && t > .8 );
				diagram.classList.toggle( 'is-lit-b', ( 2 === beat || 3 === beat ) && t > .8 );
			};
			requestAnimationFrame( loop );
		}
	} );
}() );
