/**
 * Stubli: little surprises on special days.
 *
 * One small script for the page and the app. It draws into a box (the
 * profile card on the page, the greeting in the app), plays once a day per
 * visitor, and steps aside with a tap. With "reduce motion" on, the drawings
 * appear still and fade away.
 *
 * window.StubliSeason.play( box, { key, years, preview }, text )
 */
( function () {
	'use strict';

	var STYLE_ID = 'stubli-season-style';
	var CSS = [
		'.ss-layer{position:absolute;inset:0;pointer-events:none;overflow:hidden;border-radius:inherit;z-index:3;color:var(--st-accent,var(--accent,#3f6b4b))}',
		'.ss-layer button{pointer-events:auto;background:none;border:0;padding:0;margin:0;cursor:pointer;color:inherit;font:inherit}',
		'.ss-layer button:focus-visible{outline:2px solid currentColor;outline-offset:3px;border-radius:8px}',
		'.ss-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
		'.ss-fade{transition:opacity .6s ease;opacity:0!important}',
		/* Samichlaus */
		'.ss-chlaus{position:absolute;right:14px;bottom:-2px;width:62px;height:82px;transform:translateY(105%)}',
		'.ss-chlaus svg{width:100%;height:100%;display:block;overflow:visible}',
		'.ss-go .ss-chlaus{animation:ss-climb 5.6s cubic-bezier(.3,.7,.3,1) forwards}',
		'.ss-go .ss-arm{transform-origin:43px 47px;animation:ss-wave .5s ease-in-out 1.3s 4 alternate}',
		'@keyframes ss-climb{0%{transform:translateY(105%) rotate(0)}10%{transform:translateY(70%) rotate(-4deg)}18%{transform:translateY(40%) rotate(4deg)}26%{transform:translateY(0) rotate(0)}78%{transform:translateY(0)}100%{transform:translateY(105%)}}',
		'@keyframes ss-wave{from{transform:rotate(0)}to{transform:rotate(-28deg)}}',
		'.ss-gift{position:absolute;right:84px;bottom:8px;display:flex;gap:3px;align-items:flex-end;opacity:0;transform:scale(.4);transform-origin:bottom}',
		'.ss-gift svg{display:block}',
		'.ss-go .ss-gift{animation:ss-pop .45s cubic-bezier(.3,1.6,.5,1) 2.9s forwards}',
		'@keyframes ss-pop{to{opacity:1;transform:scale(1)}}',
		/* New Year */
		'.ss-burst{position:absolute;width:70px;height:70px;margin:-35px 0 0 -35px;opacity:0}',
		'.ss-go .ss-burst{animation:ss-burst 1.3s ease-out forwards}',
		'@keyframes ss-burst{0%{opacity:1;transform:scale(.15)}60%{opacity:1}100%{opacity:0;transform:scale(1.15)}}',
		/* Easter */
		'.ss-egg{position:absolute;width:30px;height:38px}',
		'.ss-egg svg{width:100%;height:100%;display:block;overflow:visible}',
		'.ss-go .ss-egg:not(.is-open){animation:ss-wobble 4s ease-in-out 1.5s infinite}',
		'@keyframes ss-wobble{0%,86%,100%{transform:rotate(0)}89%{transform:rotate(-9deg)}93%{transform:rotate(8deg)}96%{transform:rotate(-4deg)}}',
		'.ss-egg .ss-top{transition:transform .5s cubic-bezier(.3,1.4,.5,1);transform-origin:6px 17px}',
		'.ss-egg.is-open .ss-top{transform:translate(-4px,-11px) rotate(-38deg)}',
		'.ss-egg .ss-chick{transform:translateY(12px);opacity:0;transition:transform .5s ease .15s,opacity .3s ease .15s}',
		'.ss-egg.is-open .ss-chick{transform:translateY(0);opacity:1}',
		/* Birthday */
		'.ss-balloon{position:absolute;bottom:-90px;width:30px;height:76px}',
		'.ss-go .ss-balloon{animation:ss-rise 4.6s ease-in forwards}',
		'@keyframes ss-rise{0%{transform:translate(0,0)}50%{transform:translate(6px,-55%)}100%{transform:translate(-4px,-420px)}}',
		/* Still versions */
		'.ss-still .ss-chlaus{transform:none}',
		'.ss-still .ss-gift{opacity:1;transform:none}',
		'.ss-still .ss-burst{opacity:1;transform:none}',
		'.ss-still .ss-balloon{bottom:10px}'
	].join( '' );

	var SKIN = '#f2d7c2', WHITE = '#fffdf8', INK = '#2b2a28';

	function svg( vb, w, h, body ) {
		return '<svg viewBox="' + vb + '" width="' + w + '" height="' + h + '" aria-hidden="true" focusable="false">' + body + '</svg>';
	}

	// A Samichlaus in the owner's colour: hooded robe, big white beard, staff.
	var CHLAUS = svg( '0 0 64 84', 62, 82,
		'<path d="M9 84V27c0-7 4-10 8-10s7 3 7 7" fill="none" stroke="#8a6a45" stroke-width="3" stroke-linecap="round"/>' +
		'<path d="M15 84l5-34c4-5 8-7 12-7s8 2 12 7l5 34z" fill="currentColor"/>' +
		'<path d="M30 52h4v32h-4z" fill="' + WHITE + '" opacity=".9"/>' +
		'<g class="ss-arm"><path d="M43 47l12-12" stroke="currentColor" stroke-width="6" stroke-linecap="round"/><circle cx="56.5" cy="33.5" r="4" fill="' + SKIN + '" stroke="' + INK + '" stroke-width="1.2"/></g>' +
		'<path d="M20 30c0-15 6-24 12-26 6 2 12 11 12 26z" fill="currentColor"/>' +
		'<path d="M20 29.5h24" stroke="' + WHITE + '" stroke-width="4" stroke-linecap="round"/>' +
		'<path d="M32 9v8M28 13h8" stroke="' + WHITE + '" stroke-width="2" stroke-linecap="round"/>' +
		'<circle cx="32" cy="36" r="7.5" fill="' + SKIN + '"/>' +
		'<path d="M23.5 36c0 12 4 19 8.5 21 4.5-2 8.5-9 8.5-21-2.5 3.5-5.5 5-8.5 5s-6-1.5-8.5-5z" fill="' + WHITE + '" stroke="' + INK + '" stroke-width="1"/>' +
		'<circle cx="29.3" cy="34.5" r="1.1" fill="' + INK + '"/><circle cx="34.7" cy="34.5" r="1.1" fill="' + INK + '"/>' +
		'<path d="M29.5 40.5q2.5 1.6 5 0" fill="none" stroke="' + INK + '" stroke-width="1" stroke-linecap="round"/>'
	);
	var MANDARIN = svg( '0 0 24 24', 22, 22, '<circle cx="12" cy="14" r="9" fill="#f08a24"/><circle cx="9" cy="11" r="2.2" fill="#fff" opacity=".35"/><path d="M12 5c1-3 4-4 6-3-1 2-3 3-6 3z" fill="#4f7a3a"/>' );
	var PEANUT = svg( '0 0 12 20', 9, 15, '<path d="M6 1c3 0 4.5 2.4 4 5-.3 1.4-1.3 2.2-1.3 3.5s1 2.1 1.3 3.5c.5 2.6-1 5-4 5s-4.5-2.4-4-5c.3-1.4 1.3-2.2 1.3-3.5S2.3 7.4 2 6c-.5-2.6 1-5 4-5z" fill="#d9b483" stroke="#a8814f" stroke-width=".8"/>' );

	function burst( colour ) {
		var rays = '';
		for ( var i = 0; i < 12; i++ ) {
			var a = i * Math.PI / 6, x1 = 35 + Math.cos( a ) * 9, y1 = 35 + Math.sin( a ) * 9, x2 = 35 + Math.cos( a ) * 31, y2 = 35 + Math.sin( a ) * 31;
			rays += '<path d="M' + x1.toFixed( 1 ) + ' ' + y1.toFixed( 1 ) + 'L' + x2.toFixed( 1 ) + ' ' + y2.toFixed( 1 ) + '" stroke="' + colour + '" stroke-width="2.4" stroke-linecap="round"/><circle cx="' + x2.toFixed( 1 ) + '" cy="' + y2.toFixed( 1 ) + '" r="1.8" fill="' + colour + '"/>';
		}
		return svg( '0 0 70 70', 70, 70, rays );
	}

	var EGG = svg( '0 0 30 38', 30, 38,
		'<g class="ss-chick"><circle cx="15" cy="19" r="8" fill="#f6cf45"/><circle cx="12.3" cy="17.5" r="1.1" fill="' + INK + '"/><circle cx="17.7" cy="17.5" r="1.1" fill="' + INK + '"/><path d="M13.5 20.5l1.5 1.6 1.5-1.6z" fill="#e58a2b"/></g>' +
		'<path d="M3 21c0 9 5 15 12 15s12-6 12-15l-4 2-4-3-4 3-4-3-4 3z" fill="' + WHITE + '" stroke="currentColor" stroke-width="1.6"/>' +
		'<path d="M6.5 29h17" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".55"/>' +
		'<g class="ss-top"><path d="M3 21C3 10 8.5 2 15 2s12 8 12 19l-4 2-4-3-4 3-4-3-4 3z" fill="' + WHITE + '" stroke="currentColor" stroke-width="1.6"/>' +
		'<path d="M6 13h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="10" cy="8" r="1.4" fill="currentColor"/><circle cx="20" cy="8" r="1.4" fill="currentColor"/><circle cx="15" cy="17" r="1.4" fill="currentColor"/></g>'
	);

	function balloon( colour ) {
		return svg( '0 0 30 76', 30, 76,
			'<path d="M15 40c-2 8 3 14-1 22s2 10 0 14" fill="none" stroke="#9b9a95" stroke-width="1"/>' +
			'<path d="M15 2C7 2 2 9 2 17c0 10 7 19 13 21 6-2 13-11 13-21 0-8-5-15-13-15z" fill="' + colour + '"/>' +
			'<path d="M13 38l2 3 2-3z" fill="' + colour + '"/><path d="M8 11c1-3 3-5 6-5.5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".5"/>'
		);
	}

	function reduced() {
		try { return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches; } catch ( e ) { return false; }
	}
	function today() {
		var d = new Date();
		return d.getFullYear() + '-' + ( d.getMonth() + 1 ) + '-' + d.getDate();
	}
	function seen( key, mark ) {
		var k = 'stubli-season-' + key + '-' + today();
		try {
			if ( mark ) { localStorage.setItem( k, '1' ); return true; }
			return localStorage.getItem( k ) === '1';
		} catch ( e ) { return false; }
	}
	function addStyle() {
		if ( document.getElementById( STYLE_ID ) ) { return; }
		var s = document.createElement( 'style' );
		s.id = STYLE_ID;
		s.textContent = CSS;
		document.head.appendChild( s );
	}
	function fmt( s, v ) { return String( s || '' ).replace( '%s', v ); }

	function play( box, season, text ) {
		if ( ! box || ! season || ! season.key ) { return null; }
		text = text || {};
		var key = season.key, still = reduced();
		// Once a day per visitor; the Easter egg keeps hiding until it's opened.
		if ( ! season.preview && seen( key ) ) { return null; }
		addStyle();
		var old = box.querySelector( ':scope > .ss-layer' );
		if ( old ) { old.remove(); }
		if ( getComputedStyle( box ).position === 'static' ) { box.style.position = 'relative'; }
		var layer = document.createElement( 'div' );
		layer.className = 'ss-layer ' + ( still ? 'ss-still' : 'ss-go' );
		var sr = '<span class="ss-sr" role="status"></span>';
		var html = '', life = 6200;

		if ( key === 'samichlaus' ) {
			html = '<button type="button" class="ss-chlaus" aria-label="' + esc( text.samichlaus ) + '">' + CHLAUS + '</button>' +
				'<span class="ss-gift" aria-hidden="true">' + MANDARIN + PEANUT + PEANUT + '</span>';
			life = 5600;
		} else if ( key === 'newyear' ) {
			var av = box.querySelector( '.st-avatar' ), bx = box.getBoundingClientRect(), cx = 60, cy = 44;
			if ( av ) { var r = av.getBoundingClientRect(); cx = r.left - bx.left + r.width / 2; cy = r.top - bx.top + r.height / 2; }
			var spots = [ [ cx + 34, cy - 6, 0, 'currentColor' ], [ cx + 82, cy + 18, 450, '#d9a520' ], [ cx + 128, cy - 10, 900, 'currentColor' ] ];
			html = spots.map( function ( p ) {
				return '<span class="ss-burst" aria-hidden="true" style="left:' + p[ 0 ] + 'px;top:' + p[ 1 ] + 'px;animation-delay:' + p[ 2 ] + 'ms">' + burst( p[ 3 ] ) + '</span>';
			} ).join( '' );
			life = still ? 2600 : 2400;
		} else if ( key === 'easter' ) {
			var places = [ 'right:16px;bottom:12px', 'left:14px;bottom:12px', 'right:22%;bottom:10px', 'right:12px;top:52%' ];
			html = '<button type="button" class="ss-egg" style="' + places[ Math.floor( Math.random() * places.length ) ] + '" aria-label="' + esc( text.egg ) + '">' + EGG + '</button>';
			life = 0; // Stays until it's opened.
		} else if ( key === 'birthday' ) {
			var cols = [ 'currentColor', '#d9a520', '#7fb2d6', '#e0785a', 'currentColor' ];
			html = cols.map( function ( c, i ) {
				return '<span class="ss-balloon" aria-hidden="true" style="left:' + ( 12 + i * 17 ) + '%;animation-delay:' + ( i * 260 ) + 'ms;color:' + c + '">' + balloon( 'currentColor' ) + '</span>';
			} ).join( '' );
			life = 5400;
		} else {
			return null;
		}
		layer.innerHTML = html + sr;
		if ( key === 'newyear' ) {
			// Fireworks go behind the name and photo, in front of the card.
			box.style.isolation = 'isolate';
			layer.style.zIndex = '-1';
		}
		box.appendChild( layer );
		var status = layer.querySelector( '[role=status]' );
		var say = function ( s ) { if ( status ) { status.textContent = s || ''; } };
		var gone = false;
		var end = function () {
			if ( gone ) { return; }
			gone = true;
			layer.classList.add( 'ss-fade' );
			setTimeout( function () { layer.remove(); }, 650 );
		};

		if ( key === 'easter' ) {
			var egg = layer.querySelector( '.ss-egg' );
			egg.addEventListener( 'click', function () {
				if ( egg.classList.contains( 'is-open' ) ) { end(); return; }
				egg.classList.add( 'is-open' );
				egg.setAttribute( 'aria-label', text.chick || '' );
				say( text.chick );
				seen( key, true );
				setTimeout( end, 4200 );
			} );
			return layer;
		}
		if ( key === 'samichlaus' ) {
			layer.querySelector( '.ss-chlaus' ).addEventListener( 'click', end );
			if ( ! still ) {
				// The gifts stay a little after he's gone.
				life += 2600;
			}
		}
		if ( key === 'birthday' ) { say( fmt( text.birthday, season.years || 1 ) ); }
		if ( key === 'newyear' || key === 'samichlaus' ) { say( key === 'newyear' ? text.newyear : text.samichlaus ); }
		if ( ! season.preview ) { seen( key, true ); }
		setTimeout( end, still ? 4000 : life );
		return layer;
	}

	function esc( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } );
	}

	window.StubliSeason = { play: play };

	// On the page: the profile card, when today is a special day.
	function auto() {
		var s = window.stubliSeason;
		if ( ! s || ! s.season ) { return; }
		var box = document.querySelector( '.st-profile' );
		if ( box ) { setTimeout( function () { play( box, s.season, s.text ); }, 700 ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', auto ); } else { auto(); }
}() );
