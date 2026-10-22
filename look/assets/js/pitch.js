/**
 * stubli.app · "Stubli in a minute": makes the app pieces respond.
 * Everything on the page reads fine without this script.
 */
( function () {
	'use strict';
	var page = document.querySelector( '.st-pitch' );
	if ( ! page ) {
		return;
	}
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var $ = function ( sel, root ) { return ( root || page ).querySelector( sel ); };
	var $$ = function ( sel, root ) { return Array.prototype.slice.call( ( root || page ).querySelectorAll( sel ) ); };

	/* Greeting by the visitor's own time of day. */
	var hr = new Date().getHours();
	var greet = hr < 5 ? 'Good night' : hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : hr < 22 ? 'Good evening' : 'Good night';
	$$( '[data-sp-greet]' ).forEach( function ( el ) { el.textContent = greet + ', Anna'; } );

	/* The hero app: screens change slowly, like light in a room. */
	var cycle = $( '[data-sp-cycle]' );
	if ( cycle && ! still ) {
		var screens = $$( '[data-sp-screen]', cycle ), at = 0, timer = null;
		var tabs = $$( '.sp-tabbar i', cycle );
		var tabFor = [ 0, 4, 4, 0 ];
		var step = function () {
			screens[ at ].classList.remove( 'is-on' );
			at = ( at + 1 ) % screens.length;
			screens[ at ].classList.add( 'is-on' );
			tabs.forEach( function ( t, i ) { t.classList.toggle( 'is-on', i === tabFor[ at ] ); } );
		};
		var run = function () { if ( ! timer ) { timer = setInterval( step, 5600 ); } };
		var stop = function () { clearInterval( timer ); timer = null; };
		document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } else { run(); } } );
		run();
	}

	/* Your name, your Stubli. */
	var nameIn = $( '[data-sp-name]' );
	var startName = $( '[data-sp-start-name]' );
	var slug = function ( s ) {
		return ( s.normalize ? s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ) : s ).toLowerCase().replace( /[^a-z0-9]+/g, '' ).slice( 0, 20 ) || 'yourname';
	};
	var applyName = function ( raw ) {
		var name = ( raw || '' ).trim().replace( /\s+/g, ' ' ) || 'Anna';
		var first = name.split( ' ' )[ 0 ];
		var set = function ( sel, text ) { var el = $( sel ); if ( el ) { el.textContent = text; } };
		set( '[data-sp-full]', name );
		set( '[data-sp-ini]', first.charAt( 0 ).toUpperCase() );
		set( '[data-sp-addr]', slug( name ) + '.wordpress.com' );
		set( '[data-sp-bio]', 'Hi, I’m ' + first + '. Come in, have a look around, and sign the guest book on your way out.' );
		$$( '[data-sp-greet]' ).forEach( function ( el ) { el.textContent = greet + ', ' + first; } );
		$$( '.sp-step-card .sp-field' ).forEach( function ( el ) {
			el.textContent = el.textContent.replace( /^[a-z0-9]+\.wordpress\.com/, slug( name ) + '.wordpress.com' );
		} );
		if ( startName && document.activeElement !== startName ) { startName.value = name; }
	};
	if ( nameIn ) {
		nameIn.addEventListener( 'input', function () { applyName( nameIn.value ); } );
	}
	if ( startName ) {
		startName.addEventListener( 'input', function () { if ( nameIn ) { nameIn.value = startName.value; } applyName( startName.value ); } );
	}

	/* House rules: each control changes the post on the left. */
	var room = $( '[data-sp-room]' );
	var friends = $( '[data-sp-friends]' );
	var aud = $( '[data-sp-aud]' );
	if ( friends && aud ) {
		friends.addEventListener( 'change', function () {
			aud.textContent = friends.checked ? 'Friends only · shared with 4 friends, not on your page' : 'Everyone can see this';
			aud.classList.toggle( 'is-friends', friends.checked );
		} );
	}
	var comment = $( '[data-sp-comment]' );
	var cstate = $( '[data-sp-cstate]' );
	var words = { wait: 'Waiting for your OK. Only you can see it.', ok: 'Let in. Everyone can see it now.', block: 'Blocked quietly. They won’t be told.' };
	$$( '[data-sp-cset]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			if ( comment ) { comment.setAttribute( 'data-sp-comment', r.value ); }
			if ( cstate ) { cstate.textContent = words[ r.value ]; }
		} );
	} );
	var more = $( '[data-sp-scroll]' );
	var end = $( '[data-sp-feedend]' );
	var presses = 0;
	if ( more && end ) {
		var lines = [ 'Keep scrolling', 'Still everything', 'Really, that’s all' ];
		more.addEventListener( 'click', function () {
			presses++;
			end.hidden = false;
			var box = end.querySelector( '.sp-end' );
			if ( box ) { box.style.animation = 'none'; void box.offsetWidth; box.style.animation = ''; }
			if ( room ) { room.scrollTo ? room.scrollTo( { top: room.scrollHeight, behavior: still ? 'auto' : 'smooth' } ) : ( room.scrollTop = room.scrollHeight ); }
			more.textContent = lines[ Math.min( presses, lines.length - 1 ) ];
		} );
	}
	$$( '[data-sp-colour]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			if ( ! room ) { return; }
			room.style.setProperty( '--room', r.getAttribute( 'data-c' ) );
			room.style.setProperty( '--room-on', r.getAttribute( 'data-on' ) );
			room.style.setProperty( '--room-d', r.getAttribute( 'data-d' ) );
		} );
	} );

	/* A stage: the numbers never stop. */
	var likes = $( '[data-sp-likes]' ), follows = $( '[data-sp-follow]' );
	if ( likes && ! still && 'IntersectionObserver' in window ) {
		var n = 1204, f = 8931, tick = null;
		var bump = function ( el, v ) { el.textContent = v.toLocaleString( 'en-US' ); var c = el.parentNode; c.classList.remove( 'is-bump' ); void c.offsetWidth; c.classList.add( 'is-bump' ); };
		new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) {
				if ( e.isIntersecting && ! tick ) {
					tick = setInterval( function () { n += 1 + Math.floor( Math.random() * 9 ); bump( likes, n ); if ( Math.random() < .35 ) { f += 1; bump( follows, f ); } }, 900 );
				} else if ( ! e.isIntersecting && tick ) { clearInterval( tick ); tick = null; }
			} );
		} ).observe( likes );
	}

	/* Four steps: the slider, the arrow keys or a tap on a step. */
	var range = $( '[data-sp-range]' );
	var items = $$( '.st-pitch-steps li' );
	var stepScreens = $$( '[data-sp-step]' );
	var setStep = function ( i ) {
		items.forEach( function ( li, k ) { li.classList.toggle( 'is-on', k === i ); li.setAttribute( 'aria-current', k === i ? 'step' : 'false' ); } );
		stepScreens.forEach( function ( s, k ) { s.classList.toggle( 'is-on', k === i ); } );
		if ( range ) { range.value = String( i + 1 ); range.setAttribute( 'aria-valuetext', items[ i ] ? items[ i ].querySelector( 'strong' ).textContent : '' ); }
	};
	if ( items.length ) {
		setStep( 0 );
		if ( range ) { range.addEventListener( 'input', function () { setStep( parseInt( range.value, 10 ) - 1 ); } ); }
		items.forEach( function ( li, k ) {
			li.tabIndex = 0;
			li.addEventListener( 'click', function () { setStep( k ); } );
			li.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key || ' ' === e.key ) { e.preventDefault(); setStep( k ); } } );
		} );
	}

	/* Entrances: heading, words, then the picture, once per section. */
	if ( 'IntersectionObserver' in window && ! still ) {
		var groups = $$( ':scope > section, .st-pitch-room, .st-pitch-onstage, .st-pitch-athome' );
		groups.forEach( function ( g ) {
			var parts = $$( ':scope > h1, :scope > h2, :scope > h3, :scope > p, :scope > .wp-block-buttons, :scope > ul, :scope > ol, :scope > .sp-ctl, :scope > .sp-noise, :scope > .sp-calm, :scope > .st-pitch-facts, :scope > .sp-dialog, :scope > .st-pitch-portrait, .st-pitch-sheet > *, .st-pitch-dark > *, .st-pitch-closewords > *', g );
			parts.forEach( function ( el, i ) { el.setAttribute( 'data-reveal', '' ); el.style.setProperty( '--d', Math.min( i, 6 ) * 80 + 'ms' ); } );
		} );
		page.classList.add( 'is-live' );
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { if ( e.isIntersecting ) { e.target.classList.add( 'is-in' ); io.unobserve( e.target ); } } );
		}, { rootMargin: '0px 0px -8% 0px' } );
		$$( '[data-reveal]' ).forEach( function ( el ) { io.observe( el ); } );
	} else {
		page.classList.add( 'is-live' );
		$$( '[data-reveal]' ).forEach( function ( el ) { el.classList.add( 'is-in' ); } );
	}
}() );
