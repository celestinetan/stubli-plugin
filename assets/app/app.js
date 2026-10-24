/* Stubli app: feed, composer, journal, Discover, What's new, setup wizard. No dependencies. */
( function () {
	'use strict';
	var B = window.STUBLI || {};
	// Translations: the app's words come in English; B.i18n (sent by the
	// plugin for the site's language) swaps them. _f fills in %s in order.
	// Translations come through WordPress (wp.i18n), in the site's language.
	// _f fills in each %s in order, after translating.
	function __( s, d ) { return window.wp && wp.i18n ? wp.i18n.__( s, d || 'stubli' ) : s; }
	function _x( s, c, d ) { return window.wp && wp.i18n ? wp.i18n._x( s, c, d || 'stubli' ) : s; }
	function _f( s ) { var a = arguments, i = 1; return s.replace( /%s/g, function () { return String( a[ i++ ] ); } ); }
	var root = document.getElementById( 'app' );
	var store = {
		get: function ( k ) { try { return localStorage.getItem( k ); } catch ( e ) { return null; } },
		set: function ( k, v ) { try { localStorage.setItem( k, v ); } catch ( e ) {} },
		del: function ( k ) { try { localStorage.removeItem( k ); } catch ( e ) {} }
	};

	/* ── Helpers ── */
	// Titles and names can arrive with HTML entities ("Here&#8217;s").
	function unent( s ) { var t = document.createElement( 'textarea' ); t.innerHTML = String( s == null ? '' : s ); return t.value; }
	function initials( n ) { return unent( n ).trim().split( /\s+/ ).slice( 0, 2 ).map( function ( w ) { return w.charAt( 0 ); } ).join( '' ).toUpperCase(); }
	// A photo of a person; their initials if it's missing or fails to load.
	function face( url, name, size ) {
		var ini = '<span class="a-av a-ini" style="width:' + size + 'px;height:' + size + 'px" aria-hidden="true">' + esc( initials( name ) ) + '</span>';
		return url ? '<img src="' + esc( url ) + '" alt="" width="' + size + '" height="' + size + '" data-face="' + esc( initials( name ) ) + '">' : ini;
	}
	document.addEventListener( 'error', function ( e ) {
		var im = e.target;
		if ( im && im.tagName === 'IMG' && im.hasAttribute( 'data-face' ) ) {
			var sp = document.createElement( 'span' );
			sp.className = 'a-av a-ini'; sp.setAttribute( 'aria-hidden', 'true' );
			sp.style.width = im.width + 'px'; sp.style.height = im.height + 'px';
			sp.textContent = im.getAttribute( 'data-face' );
			im.replaceWith( sp );
		}
	}, true );
	// A small ? that opens the matching answer on stubli.app/help.
	function helpLink( path, what ) { return '<a class="a-helplink" href="https://stubli.app/help/' + path + '/" target="_blank" rel="noopener" aria-label="' + esc( _f( __( 'Help: %s', 'stubli' ), what ) ) + '" title="' + esc( __( 'How this works', 'stubli' ) ) + '">?</a>'; }
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function $( sel, el ) { return ( el || root ).querySelector( sel ); }
	function $$( sel, el ) { return Array.prototype.slice.call( ( el || root ).querySelectorAll( sel ) ); }
	var I = {
		home: '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z"/>',
		discover: '<circle cx="12" cy="12" r="8.5"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
		plus: '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><path d="M12 8v8M8 12h8"/>',
		news: '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
		you: '<circle cx="12" cy="9" r="3.6"/><path d="M5 20c1-3.6 3.9-5.5 7-5.5s6 1.9 7 5.5"/>',
		back: '<path d="M15 5l-7 7 7 7"/>',
		x: '<path d="M6 6l12 12M18 6 6 18"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		video: '<rect x="3.5" y="5.5" width="17" height="13" rx="3"/><path d="M10.5 9.5v5l4-2.5z"/>',
		rotate: '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4v4h-4"/>',
		zoom: '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4M8 11h6M11 8v6"/>',
		photo: '<rect x="3.5" y="5" width="17" height="14" rx="3"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 2.5-2 4.5 3.5"/>',
		lock: '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
		check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		portrait: '<rect x="7" y="4" width="10" height="16" rx="2"/>',
		square: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
		landscape: '<rect x="3" y="7.5" width="18" height="9" rx="2"/>',
		play: '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
		chev: '<path d="M9 6l6 6-6 6"/>',
		up: '<path d="M6 15l6-6 6 6"/>',
		down: '<path d="M6 9l6 6 6-6"/>',
		pin: '<path d="M12 17v5"/><path d="M8 3h8l-1 6 3 4H6l3-4z"/>',
		menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
		book: '<path d="M12 6.5C10.3 5.2 7.9 4.5 4 4.5v14c3.9 0 6.3.7 8 2 1.7-1.3 4.1-2 8-2v-14c-3.9 0-6.3.7-8 2z"/><path d="M12 6.5v14"/>',
		pen: '<path d="M15.5 4.5l4 4L9 19l-5 1 1-5z"/><path d="M13 7l4 4"/>',
		mark: '<path d="M6.5 3.5h11v17L12 16.5l-5.5 4z"/>',
		people: '<circle cx="9" cy="8.5" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5"/><circle cx="16.5" cy="9.5" r="2.6"/><path d="M15.5 14.2c2.6-.3 4.6 1.3 5.2 4.3"/>',
		sort: '<path d="M8 4v16M4.5 7.5 8 4l3.5 3.5M16 20V4M12.5 16.5 16 20l3.5-3.5"/>',
		grip: '<path d="M5 9h14M5 15h14"/>',
		ul: '<path d="M9.5 7h10M9.5 12h10M9.5 17h10"/><circle cx="5" cy="7" r="1.1" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1.1" fill="currentColor" stroke="none"/><circle cx="5" cy="17" r="1.1" fill="currentColor" stroke="none"/>',
		ol: '<path d="M10 7h10M10 12h10M10 17h10"/><path d="M4 5.5 5.3 5v4M4 9h2.6" stroke-width="1.4"/><path d="M4 14.3a1.3 1.3 0 0 1 2.5.4c0 .9-2.5 1.7-2.5 3.3h2.6" stroke-width="1.4"/>',
		checklist: '<circle cx="5.5" cy="7" r="2.2"/><path d="m3.9 16.9 1.2 1.2 2.2-2.4"/><circle cx="5.5" cy="17" r="2.2"/><path d="M10.5 7h9.5M10.5 17h9.5"/>',
		quote: '<path d="M5 17.5c2.5-1 3.5-3 3.5-5.5V7.5H4.5v4.5h4M14 17.5c2.5-1 3.5-3 3.5-5.5V7.5h-4v4.5h4"/>'
	};
	function icon( n, w ) {
		return '<svg viewBox="0 0 24 24" width="' + ( w || 22 ) + '" height="' + ( w || 22 ) + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + I[ n ] + '</svg>';
	}
	function wordmark() {
		var mark = B.logo && B.logo.html ?
			'<span class="a-wordmark stw stw-' + esc( B.logo.key ) + '" role="img" aria-label="' + esc( __( 'Stubli', 'stubli' ) ) + '" title="' + esc( B.logo.title ) + '">' + B.logo.html + '</span>' :
			'<span class="a-wordmark" role="img" aria-label="' + esc( __( 'Stubli', 'stubli' ) ) + '"><span aria-hidden="true">st</span><svg viewBox="0 0 60 80" aria-hidden="true"><path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/></svg><span aria-hidden="true">bli</span></span>';
		return '<span class="a-brand">' + mark + '<span class="a-beta">' + __( 'Beta', 'stubli' ) + '</span></span>';
	}
	/* Time of day on the reader's own clock: morning, afternoon, evening, night. */
	function daypart() {
		var h = new Date().getHours();
		return h >= 5 && h < 12 ? 'morning' : h >= 12 && h < 18 ? 'afternoon' : h >= 18 && h < 22 ? 'evening' : 'night';
	}
	/*
	 * A small line drawing beside the greeting: the Stubli smile on a sun or a
	 * sleepy moon, over a ridge of Alps (a nod to Zurich). The sun rises behind
	 * the mountains in the morning, turns slowly in the afternoon, glows as it
	 * sets in the evening; at night the stars twinkle. Motion only when the
	 * reader hasn't asked for less (CSS).
	 */
	var skyN = 0;
	function sky( size ) {
		// The time of day in Stubli's line style, the sun and moon with the Stubli
		// smile. Motion only when allowed (CSS).
		var d = daypart(), s = size || 44;
		var f = function ( n ) { return ( +n ).toFixed( 1 ); };
		var ray = function ( cx, cy, a, r1, r2 ) { var c = Math.cos( a * Math.PI / 180 ), n = Math.sin( a * Math.PI / 180 ); return '<line x1="' + f( cx + r1 * c ) + '" y1="' + f( cy + r1 * n ) + '" x2="' + f( cx + r2 * c ) + '" y2="' + f( cy + r2 * n ) + '"/>'; };
		var star = function ( x, y, r, i ) { return '<path class="a-sky-star a-sky-star' + i + '" d="M' + x + ' ' + ( y - r ) + 'Q' + x + ' ' + y + ' ' + ( x + r ) + ' ' + y + 'Q' + x + ' ' + y + ' ' + x + ' ' + ( y + r ) + 'Q' + x + ' ' + y + ' ' + ( x - r ) + ' ' + y + 'Q' + x + ' ' + y + ' ' + x + ' ' + ( y - r ) + 'z" fill="currentColor" stroke="none"/>'; };
		var face = function ( cx, cy, r, asleep ) {
			var ex = r * 0.38, ey = cy - r * 0.14, er = Math.max( 0.7, r * 0.14 ), m = r * 0.4;
			var eyes = asleep ?
				'<path d="M' + f( cx - ex - er ) + ' ' + f( ey ) + 'q' + f( er ) + ' ' + f( er * 1.2 ) + ' ' + f( er * 2 ) + ' 0M' + f( cx + ex - er ) + ' ' + f( ey ) + 'q' + f( er ) + ' ' + f( er * 1.2 ) + ' ' + f( er * 2 ) + ' 0" stroke-width="1.1"/>' :
				'<circle cx="' + f( cx - ex ) + '" cy="' + f( ey ) + '" r="' + f( er ) + '" fill="currentColor" stroke="none"/><circle cx="' + f( cx + ex ) + '" cy="' + f( ey ) + '" r="' + f( er ) + '" fill="currentColor" stroke="none"/>';
			return eyes + '<path d="M' + f( cx - m ) + ' ' + f( cy + r * 0.2 ) + 'a' + f( m ) + ' ' + f( m ) + ' 0 0 0 ' + f( 2 * m ) + ' 0" stroke-width="1.2"/>';
		};
		// Morning: the sun and a coffee. Afternoon: high sun and a cloud. Evening:
		// the sun hands the sky over to the moon. Night: the moon, smiling.
		// A crescent: outer circle (cx, cy, 12k) minus an inner one up and right.
		var moon = function ( cx, cy, k, sleepy ) {
			var r0 = 12 * k, r1 = 9 * k, ix = cx + 7 * k, iy = cy - 6 * k;
			var dx = ix - cx, dy = iy - cy, dd = Math.sqrt( dx * dx + dy * dy );
			var aa = ( r0 * r0 - r1 * r1 + dd * dd ) / ( 2 * dd ), hh = Math.sqrt( r0 * r0 - aa * aa );
			var mx = cx + aa * dx / dd, my = cy + aa * dy / dd;
			var p1 = [ mx + hh * dy / dd, my - hh * dx / dd ], p2 = [ mx - hh * dy / dd, my + hh * dx / dd ];
			// The face sits in the thick of the crescent, level with the inner curve's edge.
			var fy = iy + 1.5 * k, xin = ix - Math.sqrt( r1 * r1 - ( fy - iy ) * ( fy - iy ) ), xout = cx - Math.sqrt( r0 * r0 - ( fy - cy ) * ( fy - cy ) );
			var fx = ( xin + xout ) / 2, t = xin - xout;
			// p1 → p2 the long way round the outer circle, back along the inner one.
			var cross = ( p1[ 0 ] - cx ) * ( p2[ 1 ] - cy ) - ( p1[ 1 ] - cy ) * ( p2[ 0 ] - cx );
			var sw = cross > 0 ? 0 : 1;
			return '<g class="a-sky-moon"><path d="M' + f( p1[ 0 ] ) + ' ' + f( p1[ 1 ] ) + 'A' + f( r0 ) + ' ' + f( r0 ) + ' 0 1 ' + sw + ' ' + f( p2[ 0 ] ) + ' ' + f( p2[ 1 ] ) + 'A' + f( r1 ) + ' ' + f( r1 ) + ' 0 0 ' + ( 1 - sw ) + ' ' + f( p1[ 0 ] ) + ' ' + f( p1[ 1 ] ) + 'Z"/>' + face( fx, fy, t * 0.5, sleepy ) + '</g>';
		};
		var steam = function ( x, k ) { return '<path class="a-sky-steam a-sky-steam' + k + '" d="M' + x + ' 24c-1.6 -1.6 1.6 -3 0 -4.6s1.6 -3 0 -4.6" stroke-width="1.3"/>'; };
		var body;
		if ( d === 'morning' ) {
			body = '<g class="a-sky-rise"><g class="a-sky-rays">' + [ 0, 45, 90, 135, 180, 225, 270, 315 ].map( function ( a ) { return ray( 14, 13, a, 7.6, 10 ); } ).join( '' ) + '</g><circle cx="14" cy="13" r="5.6"/>' + face( 14, 13, 5.6 ) + '</g>' +
				steam( 27.5, 0 ) + steam( 32, 1 ) +
				'<path d="M23 27H36.5V32.5a5.5 5.5 0 0 1 -5.5 5.5H28.5a5.5 5.5 0 0 1 -5.5 -5.5Z" fill="var(--bg, #fbfaf8)"/><path d="M36.5 29.2h1.4a2.6 2.6 0 0 1 0 5.2H36"/><path d="M21 41H38.5"/>';
		} else if ( d === 'afternoon' ) {
			body = '<g class="a-sky-rays">' + [ 0, 45, 90, 135, 180, 225, 270, 315 ].map( function ( a ) { return ray( 19, 15, a, 8.4, 11 ); } ).join( '' ) + '</g><circle cx="19" cy="15" r="6.2"/>' + face( 19, 15, 6.2 ) +
				'<path class="a-sky-cloud" d="M25.4 33.6h12.2a3.6 3.6 0 0 0 .2 -7.2a5.2 5.2 0 0 0 -9.8 -1.4a4.3 4.3 0 0 0 -2.6 8.6z" fill="var(--bg, #fbfaf8)"/>';
		} else if ( d === 'evening' ) {
			body = '<g class="a-sky-set">' + [ 180, 225, 270, 315, 0, 90, 135, 45 ].map( function ( a ) { return ray( 13, 32, a, 7.4, 9.4 ); } ).join( '' ) + '<circle cx="13" cy="32" r="5.4"/>' + face( 13, 32, 5.4 ) + '</g>' +
				moon( 29, 14, 0.78 ) + star( 39, 31, 2, 1 );
		} else {
			body = moon( 20, 23, 1.05 ) +
				star( 35, 7, 3, 1 ) + star( 9, 7, 2.4, 2 ) + star( 39, 29, 2, 3 );
		}
		var house = '';
		return '<svg class="a-sky a-sky-' + d + '" width="' + s + '" height="' + s + '" viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + body + house + '</svg>';
	}
	function endLine() {
		return { morning: __( 'Have a lovely morning.', 'stubli' ), afternoon: __( 'Enjoy the rest of your afternoon.', 'stubli' ), evening: __( 'Have a calm evening.', 'stubli' ), night: __( 'Sleep well.', 'stubli' ) }[ daypart() ];
	}
	document.addEventListener( 'visibilitychange', function () { document.documentElement.classList.toggle( 'a-hidden', document.hidden ); } );
	// A phone can keep the app open for days. When you come back to it and the
	// site has a newer Stubli, offer a reload (never forced: you may be mid-post).
	var verChecked = Date.now();
	function checkVersion() {
		if ( document.hidden || ! B.version || Date.now() - verChecked < 10 * 60 * 1000 || document.querySelector( '.a-update' ) ) { return; }
		verChecked = Date.now();
		send( 'GET', 'app-version', null ).then( function ( r ) {
			if ( ! r || ! r.version || r.version === B.version || document.querySelector( '.a-update' ) ) { return; }
			var bar = document.createElement( 'div' );
			bar.className = 'a-update'; bar.setAttribute( 'role', 'status' );
			bar.innerHTML = '<span>' + esc( __( 'Stubli has been updated.', 'stubli' ) ) + '</span><button type="button" class="a-text-btn">' + esc( __( 'Reload', 'stubli' ) ) + '</button>';
			bar.querySelector( 'button' ).addEventListener( 'click', function () { location.reload(); } );
			document.body.appendChild( bar );
		}, function () {} );
	}
	document.addEventListener( 'visibilitychange', checkVersion );
	window.addEventListener( 'focus', checkVersion );
	window.addEventListener( 'pageshow', function ( e ) { if ( e.persisted ) { verChecked = 0; checkVersion(); } } );
	function greeting() {
		var part = { morning: __( 'Good morning', 'stubli' ), afternoon: __( 'Good afternoon', 'stubli' ), evening: __( 'Good evening', 'stubli' ), night: __( 'Good evening', 'stubli' ) }[ daypart() ];
		// After midnight, a softer hello (the smiling moon stays).
		if ( new Date().getHours() < 5 ) { return B.name ? _f( __( 'Still up, %s?', 'stubli' ), B.name ) : __( 'Still up?', 'stubli' ); }
		return part + ( B.name ? ', ' + B.name : '' );
	}

	/* ── REST with nonce refresh ── */
	function refreshNonce() {
		return fetch( B.ajax + '?action=rest-nonce', { credentials: 'same-origin' } ).then( function ( r ) {
			return r.ok ? r.text() : Promise.reject();
		} ).then( function ( n ) { if ( /^[a-f0-9]{10}$/.test( n.trim() ) ) { B.nonce = n.trim(); } } );
	}
	function send( method, path, body, onProgress, retried ) {
		return new Promise( function ( resolve, reject ) {
			var x = new XMLHttpRequest();
			var parts = String( path ).split( '?' );
			x.open( method, B.rest + parts[ 0 ] + ( parts[ 1 ] ? ( B.rest.indexOf( '?' ) > -1 ? '&' : '?' ) + parts[ 1 ] : '' ) );
			x.setRequestHeader( 'X-WP-Nonce', B.nonce );
			x.withCredentials = true;
			if ( body == null ) { body = null; } else if ( ! ( body instanceof FormData ) ) { x.setRequestHeader( 'Content-Type', 'application/json' ); body = JSON.stringify( body ); }
			if ( onProgress && x.upload ) { x.upload.onprogress = function ( e ) { if ( e.lengthComputable ) { onProgress( e.loaded / e.total ); } }; }
			x.onload = function () {
				var d = null;
				try { d = JSON.parse( x.responseText ); } catch ( e ) {}
				if ( x.status >= 200 && x.status < 300 ) { return resolve( d ); }
				if ( x.status === 403 && d && d.code === 'rest_cookie_invalid_nonce' && ! retried ) {
					return refreshNonce().then( function () { resolve( send( method, path, body instanceof FormData ? body : JSON.parse( body ), onProgress, true ) ); }, function () { reject( { message: __( 'Your session ended. Please sign in again and try once more.', 'stubli' ) } ); } );
				}
				reject( { message: ( d && d.message ) || __( 'Something went wrong on the way. Try again in a moment.', 'stubli' ), code: d && d.code } );
			};
			x.onerror = function () { reject( { message: __( 'No connection right now. Try again when you\'re back online.', 'stubli' ), code: 'offline' } ); };
			x.send( body );
		} );
	}

	/* ── Photos: load, thumbnail, crop (re-encoding drops location data) ── */
	var RATIOS = { portrait: 4 / 5, square: 1, landscape: 3 / 2 };
	function loadImage( file ) {
		if ( window.createImageBitmap ) {
			return createImageBitmap( file, { imageOrientation: 'from-image' } ).catch( function () { return loadViaImg( file ); } );
		}
		return loadViaImg( file );
	}
	function loadViaImg( file ) {
		return new Promise( function ( resolve, reject ) {
			var u = URL.createObjectURL( file ), img = new Image();
			img.onload = function () { resolve( img ); };
			img.onerror = function () { URL.revokeObjectURL( u ); reject( new Error( 'unreadable' ) ); };
			img.src = u;
		} );
	}
	function dims( img ) { return { w: img.width || img.naturalWidth, h: img.height || img.naturalHeight }; }
	function toBlob( canvas, q ) {
		return new Promise( function ( resolve ) { canvas.toBlob( resolve, 'image/jpeg', q ); } );
	}
	function draw( img, sx, sy, sw, sh, W, H ) {
		var c = document.createElement( 'canvas' );
		c.width = Math.round( W ); c.height = Math.round( H );
		var g = c.getContext( '2d' );
		g.imageSmoothingQuality = 'high';
		g.drawImage( img, sx, sy, sw, sh, 0, 0, c.width, c.height );
		return c;
	}
	function makeThumb( file ) {
		return loadImage( file ).then( function ( img ) {
			var d = dims( img ), s = Math.min( 1, 720 / Math.max( d.w, d.h ) );
			var c = draw( img, 0, 0, d.w, d.h, d.w * s, d.h * s );
			if ( img.close ) { img.close(); }
			return toBlob( c, 0.8 );
		} ).then( function ( b ) { return URL.createObjectURL( b ); } );
	}
	function cropped( file, ratio ) {
		return loadImage( file ).then( function ( img ) {
			var d = dims( img ), r = RATIOS[ ratio ], cw, ch;
			if ( d.w / d.h > r ) { ch = d.h; cw = d.h * r; } else { cw = d.w; ch = d.w / r; }
			var s = Math.min( 1, 2048 / Math.max( cw, ch ) );
			var c = draw( img, ( d.w - cw ) / 2, ( d.h - ch ) / 2, cw, ch, cw * s, ch * s );
			if ( img.close ) { img.close(); }
			return toBlob( c, 0.88 );
		} );
	}

	/* ── Videos: up to a minute, location removed before upload, cover picture made here ── */
	var MAX_VIDEO = 60;
	var LOC_ATOMS = [ '©xyz', 'loci', 'gps ' ];
	function isVideo( f ) { return /^video\//.test( f.type || '' ) || /\.(mp4|mov|m4v)$/i.test( f.name || '' ); }
	// Whether this site's host takes this kind of video (WordPress.com Personal takes none).
	function canVideo( type ) { return ( B.videoTypes || [] ).indexOf( type ) >= 0; }
	function videoType( f ) { return /^video\//.test( f.type || '' ) ? f.type : ( /\.mov$/i.test( f.name || '' ) ? 'video/quicktime' : 'video/mp4' ); }
	function pickAccept() { return 'image/*' + ( ( B.videoTypes || [] ).length ? ',' + B.videoTypes.join( ',' ) : '' ); }
	function fmtDur( s ) { s = Math.round( s ); return Math.floor( s / 60 ) + ':' + ( '0' + ( s % 60 ) ).slice( -2 ); }
	function fmtMB( b ) { return ( b / 1048576 ).toFixed( b < 10485760 ? 1 : 0 ) + ' MB'; }
	function readAt( file, off, len ) { return file.slice( off, off + len ).arrayBuffer().then( function ( b ) { return new Uint8Array( b ); } ); }
	function u32( u, p ) { return ( ( u[ p ] << 24 ) >>> 0 ) + ( u[ p + 1 ] << 16 ) + ( u[ p + 2 ] << 8 ) + u[ p + 3 ]; }
	function typeAt( u, p ) { return String.fromCharCode( u[ p ], u[ p + 1 ], u[ p + 2 ], u[ p + 3 ] ); }
	function boxAt( u, p, end ) {
		if ( p + 8 > end ) { return null; }
		var len = u32( u, p ), hl = 8;
		if ( len === 1 ) { if ( p + 16 > end ) { return null; } len = u32( u, p + 8 ) * 4294967296 + u32( u, p + 12 ); hl = 16; } else if ( len === 0 ) { len = end - p; }
		if ( len < hl || p + len > end ) { return null; }
		return { len: len, type: typeAt( u, p + 4 ), hl: hl };
	}
	function setFree( u, p ) { u[ p + 4 ] = 102; u[ p + 5 ] = 114; u[ p + 6 ] = 101; u[ p + 7 ] = 101; }
	function scrubBoxes( u, start, end ) {
		var n = 0, p = start, b;
		while ( ( b = boxAt( u, p, end ) ) ) {
			if ( LOC_ATOMS.indexOf( b.type ) > -1 ) { setFree( u, p ); n++; }
			else if ( [ 'moov', 'trak', 'udta', 'mdia', 'minf', 'edts' ].indexOf( b.type ) > -1 ) { n += scrubBoxes( u, p + b.hl, p + b.len ); }
			else if ( b.type === 'meta' ) { n += scrubMeta( u, p + b.hl, p + b.len ); }
			p += b.len;
		}
		return n;
	}
	function scrubMeta( u, start, end ) {
		if ( typeAt( u, start + 4 ) !== 'hdlr' ) { start += 4; }
		var n = 0, loc = {}, p = start, b, q, i, count, ksz, name, k;
		while ( ( b = boxAt( u, p, end ) ) ) {
			if ( b.type === 'keys' ) {
				q = p + b.hl + 4; count = u32( u, q ); q += 4;
				for ( i = 1; i <= count && q + 8 <= p + b.len; i++ ) {
					ksz = u32( u, q ); if ( ksz < 8 ) { break; }
					name = ''; for ( k = q + 8; k < q + ksz; k++ ) { name += String.fromCharCode( u[ k ] ); }
					if ( /location/i.test( name ) ) { loc[ i ] = true; }
					q += ksz;
				}
			}
			p += b.len;
		}
		p = start;
		while ( ( b = boxAt( u, p, end ) ) ) {
			if ( b.type === 'ilst' ) {
				var it, qe = p + b.len; q = p + b.hl;
				while ( ( it = boxAt( u, q, qe ) ) ) {
					if ( loc[ u32( u, q + 4 ) ] || LOC_ATOMS.indexOf( it.type ) > -1 ) { setFree( u, q ); n++; }
					q += it.len;
				}
			} else if ( LOC_ATOMS.indexOf( b.type ) > -1 ) { setFree( u, p ); n++; }
			p += b.len;
		}
		return n;
	}
	function blankCoords( u ) {
		// Last safety net: any ISO 6709 coordinates left in the metadata become zeros.
		var s = '', i; for ( i = 0; i < u.length; i++ ) { s += String.fromCharCode( u[ i ] ); }
		var re = /[+-]\d{2}\.\d{2,}[+-]\d{3}\.\d{2,}(?:[+-]\d+(?:\.\d+)?)?\//g, m;
		while ( ( m = re.exec( s ) ) ) { for ( i = m.index; i < m.index + m[ 0 ].length; i++ ) { if ( u[ i ] >= 48 && u[ i ] <= 57 ) { u[ i ] = 48; } } }
	}
	function stripLocation( file ) {
		var parts = [], off = 0, size = file.size;
		function next() {
			if ( off + 8 > size ) { return Promise.resolve( new Blob( parts, { type: file.type || 'video/mp4' } ) ); }
			return readAt( file, off, 16 ).then( function ( h ) {
				var b = boxAt( h, 0, Math.min( 16, size - off ) ) || ( function () { var l = u32( h, 0 ); return { len: l === 1 ? u32( h, 8 ) * 4294967296 + u32( h, 12 ) : l === 0 ? size - off : l, type: typeAt( h, 4 ), hl: l === 1 ? 16 : 8 }; }() );
				if ( b.len < 8 ) { return Promise.reject( new Error( 'bad box' ) ); }
				var start = off; off += b.len;
				if ( [ 'moov', 'udta', 'meta' ].indexOf( b.type ) > -1 && b.len <= 64 * 1048576 ) {
					return readAt( file, start, b.len ).then( function ( u ) {
						if ( b.type === 'meta' ) { scrubMeta( u, b.hl, u.length ); } else { scrubBoxes( u, b.hl, u.length ); }
						blankCoords( u ); parts.push( u ); return next();
					} );
				}
				parts.push( file.slice( start, Math.min( off, size ) ) );
				return next();
			} );
		}
		return next();
	}
	function findBox( u, start, end, path ) {
		var p = start, b;
		while ( ( b = boxAt( u, p, end ) ) ) {
			if ( b.type === path[ 0 ] ) { return path.length === 1 ? { p: p, b: b } : findBox( u, p + b.hl, p + b.len, path.slice( 1 ) ); }
			p += b.len;
		}
		return null;
	}
	function moovOf( file ) {
		var off = 0;
		function next() {
			if ( off + 8 > file.size ) { return Promise.reject( new Error( 'no moov' ) ); }
			return readAt( file, off, 16 ).then( function ( h ) {
				var l = u32( h, 0 ), t = typeAt( h, 4 ), len = l === 1 ? u32( h, 8 ) * 4294967296 + u32( h, 12 ) : l === 0 ? file.size - off : l;
				if ( len < 8 ) { return Promise.reject( new Error( 'bad box' ) ); }
				if ( t === 'moov' ) { return readAt( file, off, len ); }
				off += len; return next();
			} );
		}
		return next();
	}
	// When this browser can't decode the video (e.g. HEVC in Firefox), read its
	// length and shape from the file and use a plain cover picture.
	function videoInfoFromFile( file ) {
		return moovOf( file ).then( function ( u ) {
			var mv = findBox( u, 8, u.length, [ 'mvhd' ] );
			if ( ! mv ) { return Promise.reject( new Error( 'no mvhd' ) ); }
			var q = mv.p + mv.b.hl, v1 = u[ q ] === 1, ts = u32( u, q + ( v1 ? 20 : 12 ) ), du = v1 ? u32( u, q + 24 ) * 4294967296 + u32( u, q + 28 ) : u32( u, q + 16 );
			var w = 1080, h = 1350, p = 8, b;
			while ( ( b = boxAt( u, p, u.length ) ) ) {
				if ( b.type === 'trak' ) {
					var tk = findBox( u, p + b.hl, p + b.len, [ 'tkhd' ] );
					if ( tk ) { var e = tk.p + tk.b.len, tw = u32( u, e - 8 ) / 65536, th = u32( u, e - 4 ) / 65536; if ( tw > 0 && th > 0 ) { w = tw; h = th; break; } }
				}
				p += b.len;
			}
			var s = Math.min( 1, 1080 / Math.max( w, h ) ), c = document.createElement( 'canvas' ), g;
			c.width = Math.round( w * s ); c.height = Math.round( h * s ); g = c.getContext( '2d' );
			g.fillStyle = '#3a2f26'; g.fillRect( 0, 0, c.width, c.height );
			var r = Math.min( c.width, c.height ) * 0.09; g.fillStyle = 'rgba(255,255,255,.85)';
			g.beginPath(); g.moveTo( c.width / 2 - r * 0.6, c.height / 2 - r ); g.lineTo( c.width / 2 + r, c.height / 2 ); g.lineTo( c.width / 2 - r * 0.6, c.height / 2 + r ); g.closePath(); g.fill();
			return toBlob( c, 0.86 ).then( function ( poster ) {
				return { duration: ts ? du / ts : 0, w: w, h: h, poster: poster, thumb: URL.createObjectURL( poster ), url: URL.createObjectURL( file ), plain: true };
			} );
		} );
	}
	function videoInfo( file ) {
		return new Promise( function ( resolve, reject ) {
			var url = URL.createObjectURL( file ), v = document.createElement( 'video' ), done = false;
			v.muted = true; v.playsInline = true; v.setAttribute( 'playsinline', '' ); v.preload = 'auto';
			var fail = function () { if ( ! done ) { done = true; URL.revokeObjectURL( url ); reject( new Error( 'unreadable' ) ); } };
			var timer = setTimeout( fail, 8000 );
			v.addEventListener( 'error', fail );
			v.addEventListener( 'loadedmetadata', function () { v.currentTime = Math.min( 0.5, ( v.duration || 1 ) / 2 ); } );
			var tries = 0;
			v.addEventListener( 'seeked', function () {
				if ( done ) { return; }
				var w = v.videoWidth, h = v.videoHeight, s = Math.min( 1, 1440 / Math.max( w, h ) );
				if ( ! w || ! h ) { return fail(); }
				var c = document.createElement( 'canvas' ); c.width = Math.round( w * s ); c.height = Math.round( h * s );
				var g = c.getContext( '2d' ); g.drawImage( v, 0, 0, c.width, c.height );
				// A browser that can read the file but not decode it draws an empty frame.
				var t = document.createElement( 'canvas' ); t.width = t.height = 16; var tg = t.getContext( '2d' ); tg.drawImage( c, 0, 0, 16, 16 );
				var px = tg.getImageData( 0, 0, 16, 16 ).data, lit = 0;
				for ( var i = 0; i < px.length; i += 4 ) { if ( px[ i + 3 ] > 0 && px[ i ] + px[ i + 1 ] + px[ i + 2 ] > 24 ) { lit++; } }
				if ( lit < 3 ) { if ( ++tries < 2 ) { v.currentTime = Math.min( 2, ( v.duration || 2 ) * 0.6 ); return; } return fail(); }
				toBlob( c, 0.86 ).then( function ( poster ) {
					done = true; clearTimeout( timer );
					resolve( { duration: v.duration, w: w, h: h, poster: poster, thumb: URL.createObjectURL( poster ), url: url } );
				} );
			} );
			v.src = url;
		} );
	}

	/* ── State ── */
	var post = null;
	function newPost() {
		post = { photos: [], current: 0, ratio: 'portrait', caption: '', collection: B.collections && B.collections[ 0 ] || '', newCollection: '', addingCollection: ! ( B.collections && B.collections.length ), note: false, noteText: '', audience: 'everyone', linkOpen: false, altsChecked: false, busy: false, error: '' };
	}
	var journal = { title: store.get( 'stubli-journal-title' ) || '', html: store.get( 'stubli-journal-html' ) || jTextToHtml( store.get( 'stubli-journal-draft' ) ), visibility: store.get( 'stubli-journal-vis' ) || 'everyone', tool: '', busy: false, error: '', uploading: 0, photos: [], pending: {} };
	try { journal.photos = JSON.parse( store.get( 'stubli-journal-photos' ) || '[]' ) || []; } catch ( e ) { journal.photos = []; }
	journal.did = +( store.get( 'stubli-journal-did' ) || 0 ) || 0;
	function saveJournalDraft() { store.set( 'stubli-journal-title', journal.title || '' ); store.set( 'stubli-journal-html', journal.html ); store.del( 'stubli-journal-draft' ); store.set( 'stubli-journal-photos', JSON.stringify( journal.photos ) ); }

	/* ── Router ── */
	function route() {
		var h = ( location.hash || '#home' ).slice( 1 );
		window.scrollTo( 0, 0 );
		if ( h !== 'you' ) { me.sheet = null; me.edit = false; me.renaming = 0; }
		if ( h === 'new' ) { if ( ! post ) { newPost(); } return viewPick(); }
		if ( h === 'new/details' ) { if ( ! post || ! post.photos.length ) { location.replace( '#new' ); return; } return viewDetails(); }
		if ( h === 'journal' ) { return viewJournal( 0 ); }
		if ( /^journal\/\d+$/.test( h ) ) { return viewJournal( +h.split( '/' )[ 1 ] ); }
		if ( h === 'journals' ) { return viewJournals(); }
		if ( h === 'you' ) { return viewYou(); }
		if ( h === 'more' ) { return viewMore(); }
		if ( h === 'about' ) { return viewAbout(); }
		if ( h === 'look' ) { return viewLook(); }
		if ( h === 'guestbook' ) { return viewGuestbook(); }
		if ( h === 'links' ) { return viewLinks(); }
		if ( h === 'arrange' ) { return viewArrange(); }
		if ( /^arrange\/\d+$/.test( h ) ) { return viewPins( +h.split( '/' )[ 1 ] ); }
		if ( /^note\/\d+$/.test( h ) ) { return viewNote( +h.split( '/' )[ 1 ] ); }
		if ( h === 'feedback' ) { return viewFeedback(); }
		if ( h === 'import' ) { if ( imp && ( imp.state === 'done' || imp.state === 'empty' ) ) { imp = null; } return viewImport(); }
		if ( h === 'discover' ) { return viewDiscover(); }
		if ( h === 'whatsnew' ) { return viewWhatsNew(); }
		if ( h === 'setup' ) { return viewSetup(); }
		if ( /^edit\/\d+$/.test( h ) ) { return viewEdit( +h.split( '/' )[ 1 ] ); }
		if ( ! location.hash && ! B.setupDone && ! store.get( 'stubli-setup-later' ) ) { location.replace( '#setup' ); return; }
		return viewHome();
	}
	// Back buttons return to the tab you came from (audit #22).
	var cameFrom = '#you';
	window.addEventListener( 'hashchange', function ( e ) {
		var was = ( e.oldURL || '' ).split( '#' )[ 1 ];
		was = was === undefined ? 'home' : was;
		if ( [ 'home', 'discover', 'whatsnew', 'you', 'more' ].indexOf( was ) > -1 ) { cameFrom = '#' + was; }
	} );
	window.addEventListener( 'hashchange', route );

	function bar( active ) {
		var items = [
			[ 'home', '#home', __( 'Home', 'stubli' ), 'home', __( 'Home', 'stubli' ) ],
			[ 'discover', '#discover', __( 'People', 'stubli' ), 'people', __( 'People', 'stubli' ) ],
			[ 'new', '#new', __( 'New post', 'stubli' ), 'plus', _x( 'New', 'tab', 'stubli' ) ],
			[ 'whatsnew', '#whatsnew', __( 'What\'s new', 'stubli' ), 'news', __( 'What\'s new', 'stubli' ) ],
			[ 'you', '#you', __( 'You', 'stubli' ), 'you', __( 'You', 'stubli' ) ]
		];
		return '<div class="a-bar"><nav aria-label="' + esc( __( 'Stubli', 'stubli' ) ) + '">' + items.map( function ( i ) {
			var waiting = ( B.waiting || 0 ) + ( B.requests || 0 ), dot = i[ 0 ] === 'whatsnew' && waiting > 0;
			return '<a href="' + esc( i[ 1 ] ) + '"' + ( i[ 0 ] === active ? ' aria-current="page"' : '' ) + ' aria-label="' + esc( i[ 2 ] + ( dot ? ', ' + _f( __( '%s waiting for your OK', 'stubli' ), waiting ) : '' ) ) + '"><span class="a-bar-i">' + icon( i[ 3 ], 24 ) + ( dot ? '<span class="a-dot" aria-hidden="true"></span>' : '' ) + '</span><span class="a-bar-l" aria-hidden="true">' + esc( i[ 4 ] ) + '</span></a>';
		} ).join( '' ) + '</nav></div>';
	}

	/* ── Home ── */
	function isStandalone() { return ( window.matchMedia && matchMedia( '(display-mode: standalone)' ).matches ) || navigator.standalone; }
	function viewMore() {
		var ios = /iphone|ipad|ipod/i.test( navigator.userAgent );
		var host = String( B.home || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + ( '</a><h1>' + __( 'Settings and more', 'stubli' ) + '</h1><span></span></header><main class="a-main" id="main">' ) +
			( '<section class="a-card"><h2>' + __( 'Your page', 'stubli' ) + '</h2><p class="muted">' ) + esc( host ) + '</p><div class="a-actions"><a class="a-btn" href="' + esc( B.home ) + ( '">' + __( 'See your page', 'stubli' ) + '</a><a class="a-btn quiet" href="' ) + esc( B.settings ) + ( '">' + __( 'Advanced settings', 'stubli' ) + '</a></div><p class="a-help">' + __( 'Your name, photo and bio are under Edit page. Advanced settings open your WordPress dashboard.', 'stubli' ) + '</p></section>' ) +
			summaryCard() +
			( '<section class="a-card"><h2>' + __( 'Colour and reading mode', 'stubli' ) + '</h2><p class="muted">' + __( 'The colour of your page and how it opens for visitors: Automatic, Light, Paper or Dark.', 'stubli' ) + '</p><a class="a-btn quiet" href="#look">' + __( 'Change', 'stubli' ) + '</a></section>' ) +
			( '<section class="a-card"><h2>' + __( 'Bring your photos from Instagram', 'stubli' ) ) + helpLink( 'posting/instagram', __( 'bringing photos from Instagram', 'stubli' ) ) + ( '</h2><p class="muted">' + __( 'Your posts arrive with their original dates and captions.', 'stubli' ) + '</p><a class="a-btn quiet" href="#import">' + __( 'Start', 'stubli' ) + '</a></section>' ) +
			keepCard() +
			( '<section class="a-card"><h2>' + __( 'Tell us what you think', 'stubli' ) + '</h2><p class="muted">' + __( 'Ideas, things that don\'t work, kind words: every note goes to the people who make Stubli.', 'stubli' ) + '</p><a class="a-btn quiet" href="#feedback">' + __( 'Send feedback', 'stubli' ) + '</a></section>' ) +
			( isStandalone() ? '' : ( '<section class="a-card"><h2>' + __( 'Put Stubli on your home screen', 'stubli' ) + '</h2><p class="muted">' ) + ( ios ? _f( __( 'In Safari, tap the Share button, then %s.', 'stubli' ), '<strong>' + __( 'Add to Home Screen', 'stubli' ) + '</strong>' ) : _f( __( 'Open your browser\'s menu, then choose %s or %s.', 'stubli' ), '<strong>' + __( 'Add to Home screen', 'stubli' ) + '</strong>', '<strong>' + __( 'Install app', 'stubli' ) + '</strong>' ) ) + ( ' ' + __( 'It opens like an app, without the browser around it.', 'stubli' ) + '</p></section>' ) ) +
			( '<p class="a-help a-thanks">' + __( 'Stubli is free and always will be. Celestine makes it and pays for it; if you enjoy it, you can', 'stubli' ) + ' <a href="https://buymeacoffee.com/celestinetan" target="_blank" rel="noopener">' + __( 'leave him a tip', 'stubli' ) + '</a>. ' + __( 'It unlocks nothing; it just keeps Stubli going.', 'stubli' ) + '</p>' ) +
			'</main>' + bar( 'you' );
		bindSummary( redrawSummary ); bindKeep();
		bindMove();
		if ( ! mv.loaded ) { mv.loaded = true; Promise.all( [ send( 'GET', 'move/link', null ), send( 'GET', 'move/in', null ) ] ).then( function ( r ) { mv.out = r[ 0 ]; mv.inState = r[ 1 ]; if ( r[ 1 ].state === 'moving' ) { runMove( function () { if ( location.hash === '#more' ) { redrawMove(); } } ); } if ( location.hash === '#more' ) { redrawMove(); } }, function () {} ); }
		if ( ! kp.loaded && ! kp.loading ) { kp.loading = true; send( 'GET', 'archive', null ).then( function ( r ) { kp.ar = r; kp.loaded = true; kp.loading = false; if ( r.state === 'packing' ) { packArchive(); } else if ( location.hash === '#more' ) { redrawKeep(); } }, function () { kp.loaded = true; kp.loading = false; } ); }
		if ( ! ds.loaded && ! ds.loading ) { ds.loading = true; loadSummary().then( function () { ds.loading = false; if ( location.hash === '#more' ) { redrawSummary(); } } ); }
	}


	/* ── You: your own profile, like your page (agreed 22 Oct) ── */
	var me = { p: null, loading: false, i: 0, coll: '', inviteOpen: false, edit: false, renaming: 0, sheet: null };
	function loadMe() {
		if ( me.loading ) { return Promise.resolve(); }
		me.loading = true;
		return send( 'GET', 'profile', null ).then( function ( p ) { me.p = p || {}; me.loading = false; }, function () { me.loading = false; me.p = me.p || {}; } );
	}
	function deckOf( p ) {
		return [ { bio: true } ].concat( ( p && p.prompts || [] ).map( function ( c ) { return { q: c.q, a: c.a }; } ) );
	}
	function stackHtml( deck, i, bioHtml, opts ) {
		var n = deck.length;
		i = ( ( i % n ) + n ) % n;
		var cards = deck.map( function ( c, k ) {
			var pos = ( k - i + n ) % n, front = pos === 0;
			var inner = c.bio ? bioHtml : '<p class="a-pcard-q">' + esc( c.q ) + '</p><p class="a-pcard-a' + ( String( c.a ).length <= 28 ? ' is-short' : String( c.a ).length <= 80 ? ' is-mid' : '' ) + '">' + esc( c.a ) + '</p>' + ( opts && opts.reply ? opts.reply( k - 1 ) : '' );
			return '<div class="a-pcard' + ( c.bio ? ' a-pcard-bio' : ' t-' + ( ( k - 1 ) % 4 ) ) + '" data-pos="' + ( pos > 2 ? 'hide' : pos ) + '"' + ( front ? '' : ' aria-hidden="true" inert' ) + '>' + inner + '</div>';
		} ).join( '' );
		var nav = n > 1 ? '<div class="a-stack-nav"><button type="button" class="a-stack-btn" data-stack-prev aria-label="' + esc( __( 'Previous card', 'stubli' ) ) + '">' + icon( 'back', 18 ) + '</button><span class="a-stack-dots" aria-hidden="true">' + deck.map( function ( _, k ) { return '<span' + ( k === i ? ' class="is-on"' : '' ) + '></span>'; } ).join( '' ) + '</span><button type="button" class="a-stack-btn" data-stack-next aria-label="' + esc( __( 'Next card', 'stubli' ) ) + '">' + icon( 'chev', 18 ) + '</button><span class="sr" aria-live="polite" data-stack-status></span></div>' : '';
		return '<div class="a-stack' + ( n > 1 ? ' is-deck' : '' ) + '" data-stack>' + cards + '</div>' + nav;
	}
	function bindStack( get, set, redraw ) {
		var st = $( '[data-stack]' ); if ( ! st ) { return; }
		var go = function ( d ) { set( get() + d ); redraw(); var s2 = $( '[data-stack-status]' ), f = $( '.a-pcard[data-pos="0"]' ); if ( s2 && f ) { s2.textContent = f.textContent.slice( 0, 120 ); } };
		var pv = $( '[data-stack-prev]' ), nx = $( '[data-stack-next]' );
		if ( pv ) { pv.addEventListener( 'click', function () { go( -1 ); var b = $( '[data-stack-prev]' ); if ( b ) { b.focus(); } } ); }
		if ( nx ) { nx.addEventListener( 'click', function () { go( 1 ); var b = $( '[data-stack-next]' ); if ( b ) { b.focus(); } } ); }
		var x0 = null, y0 = null;
		st.addEventListener( 'touchstart', function ( e ) { x0 = e.touches[ 0 ].clientX; y0 = e.touches[ 0 ].clientY; }, { passive: true } );
		st.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) { return; }
			var dx = e.changedTouches[ 0 ].clientX - x0, dy = e.changedTouches[ 0 ].clientY - y0; x0 = null;
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) { go( dx < 0 ? 1 : -1 ); }
		} );
	}
	function youGrid() {
		if ( ! mine.loaded ) { return '<div class="a-mine-grid" aria-hidden="true"><span></span><span></span><span></span></div>'; }
		var list = mine.items.filter( function ( i ) { return ! me.coll || i.coll === me.coll; } );
		if ( ! list.length ) { return '<div class="a-empty-you">' + smile() + '<p>' + ( me.coll ? _f( __( 'Nothing in %s yet.', 'stubli' ), esc( me.coll ) ) : __( 'Your page is waiting for its first photo.', 'stubli' ) ) + ( '</p><a class="a-btn quiet" href="#new">' + __( 'Post a photo', 'stubli' ) + '</a></div>' ); }
		var firstMissing = mine.items.filter( function ( i ) { return i.missing; } )[ 0 ];
		var shape = me.coll && mine.shapes ? mine.shapes[ me.coll ] : '';
		return ( '' ) + // Descriptions are optional (23 Oct): no reminders.
			'<div class="a-mine-grid' + ( shape && shape !== 'portrait' ? ' is-' + shape : '' ) + '">' + list.map( function ( i ) {
				var label = ( i.title || ( i.video ? __( 'Video post', 'stubli' ) : __( 'Photo post', 'stubli' ) ) ) + ( i.count > 1 ? _f( __( ', %s photos', 'stubli' ), i.count ) : '' ) + ( i.friends ? __( ', friends only', 'stubli' ) : '' );
				return '<a href="#edit/' + i.id + '" aria-label="' + esc( _f( __( 'Edit: %s', 'stubli' ), label ) ) + '">' + ( i.thumb ? '<img src="' + esc( i.thumb ) + '" alt="" loading="lazy">' : '' ) +
					( i.count > 1 ? '<span class="a-mine-n" aria-hidden="true">' + i.count + '</span>' : i.video ? '<span class="a-mine-n" aria-hidden="true">' + icon( 'play', 12 ) + '</span>' : '' ) +
					( i.friends ? ( '<span class="a-mine-friends" aria-hidden="true">' + __( 'Friends only', 'stubli' ) + '</span>' ) : '' ) + '</a>';
			} ).join( '' ) + '</div>' +
			( mine.more && ! me.coll ? ( '<button type="button" class="a-btn quiet wide" data-mine-more style="margin-top:12px">' + __( 'Show more', 'stubli' ) + '</button>' ) : '' );
	}
	function smile() {
		return '<svg class="a-smile" viewBox="0 0 60 80" width="26" height="35" aria-hidden="true" focusable="false"><path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/></svg>';
	}
	function viewYou() {
		if ( ! me.p || ! mine.loaded ) {
			root.innerHTML = '<main class="a-main" id="main"><div class="a-top a-top-row">' + wordmark() + '<a class="a-text-btn" href="#more" aria-label="' + esc( __( 'Settings and more', 'stubli' ) ) + '">' + icon( 'menu', 22 ) + ( '</a></div><h1 class="sr">' + __( 'You', 'stubli' ) + '</h1><div class="a-feed-skel" aria-hidden="true"><span style="height:150px"></span><span style="height:220px"></span></div><p class="sr" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>' ) + bar( 'you' );
			var todo = [];
			if ( ! me.p && ! me.loading ) { todo.push( loadMe() ); }
			if ( ! mine.loaded && ! mine.loading ) { mine.loading = true; todo.push( loadMine( true ).then( function () { mine.loading = false; }, function () { mine.loading = false; mine.loaded = true; } ) ); }
			if ( todo.length ) { Promise.all( todo ).then( function () { if ( location.hash === '#you' ) { viewYou(); } } ); }
			return;
		}
		var p = me.p, host = hostOf( B.home );
		var name = p ? ( p.name || host ) : '';
		var bio = p ? ( '<div class="a-you-head">' + ( p.avatar ? '<img src="' + esc( p.avatar ) + '" alt="" width="52" height="52">' : '<span class="a-av a-ini" style="width:52px;height:52px">' + esc( initials( name ) ) + '</span>' ) +
			'<span><strong>' + esc( unent( name ) ) + '</strong>' + ( p.pronouns ? ' <span class="muted">' + esc( p.pronouns ) + '</span>' : '' ) + '<small class="muted">' + esc( host ) + '</small></span></div>' +
			( p.bio ? '<p class="a-you-bio">' + esc( unent( p.bio ) ) + '</p>' : ( '<p class="a-you-bio muted"><a href="#about">' + __( 'Add a line about you', 'stubli' ) + '</a></p>' ) ) +
			( p.prompts && p.prompts.length ? '' : ( '<a class="a-text-btn a-you-add" href="#about">' + __( 'Add cards about you', 'stubli' ) + '</a>' ) ) ) : '';
		var latest = mine.loaded && mine.items[ 0 ];
		var colls = [];
		mine.items.forEach( function ( i ) { if ( i.coll && colls.indexOf( i.coll ) < 0 ) { colls.push( i.coll ); } } );
		// Same order as your page (Arrange); new ones at the end.
		var ord = B.collOrder || []; colls.sort( function ( a, b ) { var x = ord.indexOf( a ), y = ord.indexOf( b ); return ( x < 0 ? 999 : x ) - ( y < 0 ? 999 : y ); } );
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top a-top-row">' + wordmark() + '<a class="a-text-btn" href="#more" aria-label="' + esc( __( 'Settings and more', 'stubli' ) ) + '">' + icon( 'menu', 22 ) + '</a></div>' +
			( '<h1 class="sr">' + __( 'You', 'stubli' ) + '</h1>' ) +
			'<div data-you-stack>' + ( p ? stackHtml( deckOf( p ), me.i, bio, null ) : '<div class="a-feed-skel" aria-hidden="true"><span style="height:150px"></span></div>' ) + '</div>' +
			( '<div class="a-you-btns"><a class="a-btn quiet" href="#about">' + __( 'Edit page', 'stubli' ) + '</a><button type="button" class="a-btn quiet" data-share>' + __( 'Share', 'stubli' ) + '</button><button type="button" class="a-btn" data-invite-toggle aria-expanded="' ) + me.inviteOpen + ( '">' + __( 'Invite', 'stubli' ) + '</button></div>' ) +
			'<section class="a-card" data-invite-card' + ( me.inviteOpen ? '' : ' hidden' ) + ( '><p class="muted" style="margin-top:0">' + __( 'Send a friend a link. Once they\'ve set up their own Stubli, you follow each other automatically. Each link works once, for 30 days.', 'stubli' ) + '</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>' + __( 'Create an invite link', 'stubli' ) + '</button></section>' ) +
			( latest ? '<a class="a-latest" href="' + esc( latest.url || ( '#edit/' + latest.id ) ) + '">' + ( latest.thumb ? '<img src="' + esc( latest.thumb ) + '" alt="" width="44" height="44">' : '' ) + '<span><span class="muted">' + _f( __( 'Latest · %s', 'stubli' ), esc( gentle( latest.date ) ) ) + '</span><span class="a-latest-t">' + esc( latest.title || __( 'Photo post', 'stubli' ) ) + ( latest.coll ? ' <span class="muted">' + _f( __( 'in %s', 'stubli' ), esc( latest.coll ) ) + '</span>' : '' ) + '</span></span>' + icon( 'chev', 18 ) + '</a>' : '' ) +
			'<div class="a-ctabs-row"><div class="a-ctabs" role="group" aria-label="' + esc( __( 'Collections', 'stubli' ) ) + '">' + [ '' ].concat( colls ).map( function ( c ) { return '<button type="button" class="a-ctab" aria-pressed="' + ( me.coll === c ) + '" data-coll="' + esc( c ) + '">' + esc( c || __( 'Photos', 'stubli' ) ) + '</button>'; } ).join( '' ) + '</div>' +
				'<a class="a-ctab a-ctab-icon" href="#journals" aria-label="' + esc( __( 'Your journal', 'stubli' ) ) + '" title="' + esc( __( 'Your journal', 'stubli' ) ) + '">' + icon( 'book', 18 ) + '</a>' +
				( colls.length ? '<button type="button" class="a-ctab a-ctab-icon" data-tabedit aria-label="' + esc( __( 'Arrange and rename collections', 'stubli' ) ) + '" title="' + esc( __( 'Arrange and rename collections', 'stubli' ) ) + '">' + icon( 'sort', 18 ) + '</button>' : '' ) +
				'<span class="a-ctabs-gap"></span>' +
				( p.guestbook ? '<a class="a-ctab a-ctab-icon a-ctab-green" href="#guestbook" aria-label="' + esc( __( 'Guest book', 'stubli' ) ) + '" title="' + esc( __( 'Guest book', 'stubli' ) ) + '">' + icon( 'pen', 18 ) + '</a>' : '' ) +
				'<a class="a-ctab a-ctab-icon a-ctab-green" href="#links" aria-label="' + esc( __( 'Links', 'stubli' ) ) + '" title="' + esc( __( 'Links', 'stubli' ) ) + '">' + icon( 'mark', 18 ) + '</a></div>' +
			( me.edit ? tabEditHtml() : '' ) +
			'<div class="a-you-grid" data-you-grid><div data-you-line>' + youLine() + '</div>' + youGrid() + '</div>' +
			( me.sheet ? sheetHtml() : '' ) +
			'</main>' + bar( 'you' );
		var redrawStack = function () { var w = $( '[data-you-stack]' ); if ( w ) { w.innerHTML = stackHtml( deckOf( me.p ), me.i, bio, null ); bindStack( function () { return me.i; }, function ( v ) { me.i = v; }, redrawStack ); } };
		bindStack( function () { return me.i; }, function ( v ) { me.i = v; }, redrawStack );
		bindInvite();
		var it = $( '[data-invite-toggle]' ); if ( it ) { it.addEventListener( 'click', function () { me.inviteOpen = ! me.inviteOpen; var c = $( '[data-invite-card]' ); c.hidden = ! me.inviteOpen; it.setAttribute( 'aria-expanded', me.inviteOpen ); if ( me.inviteOpen ) { var bb = $( '[data-invite]' ); if ( bb ) { bb.focus(); } } } ); }
		var sh = $( '[data-share]' ); if ( sh ) { sh.addEventListener( 'click', function () {
			if ( navigator.share ) { navigator.share( { title: name, url: B.home } ).catch( function () {} ); return; }
			var ok = function () { toast( __( 'Link to your page copied.', 'stubli' ) ); };
			if ( navigator.clipboard ) { navigator.clipboard.writeText( B.home ).then( ok, function () { toast( B.home ); } ); } else { toast( B.home ); }
		} ); }
		bindTabEdit();
		$$( '[data-coll]' ).forEach( function ( b ) { longPress( b, function () { if ( b.dataset.coll ) { openTabEdit(); } } ); } );
		$$( '[data-coll]' ).forEach( function ( b ) { b.addEventListener( 'click', function ( e ) { if ( b.dataset.lp ) { delete b.dataset.lp; e.preventDefault(); return; } me.coll = b.dataset.coll; swap( '[data-you-grid]', '<div class="a-you-grid" data-you-grid><div data-you-line>' + youLine() + '</div>' + youGrid() + '</div>', bindYouGrid ); $$( '[data-coll]' ).forEach( function ( x ) { x.setAttribute( 'aria-pressed', x.dataset.coll === me.coll ); } ); } ); } );
		bindYouGrid();
	}
	function bindYouGrid() {
		$$( '[data-you-grid] a[href^="#edit/"]' ).forEach( function ( a ) {
			longPress( a, function () { openSheet( +a.getAttribute( 'href' ).split( '/' )[ 1 ] ); } );
			a.addEventListener( 'click', function ( e ) { if ( a.dataset.lp ) { delete a.dataset.lp; e.preventDefault(); } } );
		} );
		var m = $( '[data-mine-more]' );
		if ( m ) { m.addEventListener( 'click', function () { m.disabled = true; m.textContent = __( 'Loading…', 'stubli' ); mine.page++; loadMine( false ).then( function () { if ( location.hash === '#you' ) { viewYou(); } } ); } ); }
	}

	// The short line of the collection you're looking at, as on your page.
	function youLine() {
		if ( ! ar.list ) { if ( ! ar.loading ) { ar.loading = true; send( 'GET', 'arrange', null ).then( function ( r ) { ar.list = r.collections; ar.loading = false; var yl = $( '[data-you-line]' ); if ( yl && location.hash === '#you' ) { yl.innerHTML = youLine(); } }, function () { ar.loading = false; } ); } return ''; }
		var name = me.coll || __( 'Photos', 'stubli' );
		var c = ar.list.filter( function ( x ) { return x.name === name; } )[ 0 ];
		return c && c.description ? '<p class="a-you-line">' + esc( c.description ) + '</p>' : '';
	}

	/* ── Hold to edit, like on a phone's home screen ── */
	function longPress( el, fn ) {
		var t = null, x0 = 0, y0 = 0;
		var stop = function () { clearTimeout( t ); t = null; };
		el.addEventListener( 'pointerdown', function ( e ) {
			if ( e.button > 0 ) { return; }
			x0 = e.clientX; y0 = e.clientY; stop();
			t = setTimeout( function () { t = null; el.dataset.lp = '1'; if ( navigator.vibrate ) { try { navigator.vibrate( 10 ); } catch ( er ) {} } fn(); }, 480 );
		} );
		el.addEventListener( 'pointermove', function ( e ) { if ( t && ( Math.abs( e.clientX - x0 ) > 8 || Math.abs( e.clientY - y0 ) > 8 ) ) { stop(); } } );
		[ 'pointerup', 'pointercancel', 'pointerleave' ].forEach( function ( ev ) { el.addEventListener( ev, stop ); } );
		el.addEventListener( 'contextmenu', function ( e ) { e.preventDefault(); } );
	}
	// Your collections, in edit mode: drag to reorder, pencil to rename.
	function openTabEdit() {
		me.edit = true; me.renaming = 0; me.editMsg = '';
		if ( ! ar.list ) { send( 'GET', 'arrange', null ).then( function ( r ) { ar.list = r.collections; if ( location.hash === '#you' && me.edit ) { viewYou(); } }, function ( e ) { toast( e.message ); } ); }
		viewYou();
		var h = $( '[data-tabedit-title]' ); if ( h ) { h.focus(); }
	}
	function tabEditHtml() {
		var list = ar.list;
		return '<section class="a-card a-tabedit" aria-labelledby="a-te-h"><div class="a-tabedit-head"><h2 id="a-te-h" tabindex="-1" data-tabedit-title>' + __( 'Your collections', 'stubli' ) + '</h2><button type="button" class="a-btn" data-tabedit-done>' + __( 'Done', 'stubli' ) + '</button></div>' +
			'<p class="muted">' + __( 'Drag to change the order of your tabs; the first one is what visitors see first. Tap the pencil to rename one or give it a short line.', 'stubli' ) + '</p>' +
			( ! list ? '<p class="muted" role="status">' + __( 'Loading…', 'stubli' ) + '</p>' : '<ul class="a-tabedit-list" data-te-list>' + list.map( function ( c, i ) {
				if ( me.renaming === c.id ) {
					return '<li data-id="' + c.id + '" class="is-renaming"><div class="a-te-form"><label class="a-label" for="a-te-name">' + __( 'Name', 'stubli' ) + '</label><input id="a-te-name" class="a-field" maxlength="40" value="' + esc( c.name ) + '">' +
						'<label class="a-label" for="a-te-desc">' + __( 'A short line about it', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></label><input id="a-te-desc" class="a-field" maxlength="200" placeholder="' + esc( __( 'e.g. Mostly at dawn, by the lake.', 'stubli' ) ) + '" value="' + esc( c.description || '' ) + '">' +
						'<div class="a-actions"><button type="button" class="a-btn" data-te-save="' + c.id + '">' + __( 'Save', 'stubli' ) + '</button><button type="button" class="a-text-btn" data-te-cancel>' + __( 'Cancel', 'stubli' ) + '</button></div></div></li>';
				}
				return '<li data-id="' + c.id + '"><span class="a-te-grip" data-te-grip aria-hidden="true">' + icon( 'grip', 18 ) + '</span><span class="a-te-name"><strong>' + esc( c.name ) + '</strong><small class="muted">' + esc( c.description || ( c.count === 1 ? __( '1 post', 'stubli' ) : _f( __( '%s posts', 'stubli' ), c.count ) ) ) + '</small></span>' +
					'<button type="button" class="a-icon-btn" data-te-ren="' + c.id + '" aria-label="' + esc( _f( __( 'Edit the name and line of %s', 'stubli' ), c.name ) ) + '">' + icon( 'pen', 17 ) + '</button>' +
					'<button type="button" class="a-icon-btn" data-te-move="' + i + '" data-dir="-1"' + ( i === 0 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move %s up', 'stubli' ), c.name ) ) + '">' + icon( 'up', 18 ) + '</button>' +
					'<button type="button" class="a-icon-btn" data-te-move="' + i + '" data-dir="1"' + ( i === list.length - 1 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move %s down', 'stubli' ), c.name ) ) + '">' + icon( 'down', 18 ) + '</button></li>';
			} ).join( '' ) + '</ul>' ) +
			'<p class="a-help" aria-live="polite" data-te-msg>' + esc( me.editMsg || '' ) + '</p>' +
			'<p class="a-help">' + __( 'Tip: hold a photo to pin it to the top of its collection.', 'stubli' ) + '</p></section>';
	}
	function saveTabOrder( ids ) {
		var before = ar.list.map( function ( c ) { return c.id; } ).join( ',' );
		if ( ids.join( ',' ) === before ) { return; }
		ar.list = ids.map( function ( id ) { return ar.list.filter( function ( c ) { return c.id === id; } )[ 0 ]; } );
		B.collOrder = ar.list.map( function ( c ) { return c.name; } );
		me.editMsg = __( 'Saving…', 'stubli' ); viewYou();
		send( 'POST', 'arrange', { order: ids } ).then( function ( r ) { ar.list = r.collections; B.collOrder = r.collections.map( function ( c ) { return c.name; } ); me.editMsg = __( 'Saved. Your page uses this order now.', 'stubli' ); if ( location.hash === '#you' ) { viewYou(); } }, function ( e ) { me.editMsg = e.message; viewYou(); } );
	}
	function bindTabEdit() {
		var te = $( '[data-tabedit]' ); if ( te ) { te.addEventListener( 'click', function () { if ( me.edit ) { me.edit = false; viewYou(); } else { openTabEdit(); } } ); te.setAttribute( 'aria-pressed', me.edit ? 'true' : 'false' ); }
		var done = $( '[data-tabedit-done]' ); if ( done ) { done.addEventListener( 'click', function () { me.edit = false; me.renaming = 0; viewYou(); var b = $( '[data-tabedit]' ); if ( b ) { b.focus(); } } ); }
		$$( '[data-te-move]' ).forEach( function ( b ) { b.addEventListener( 'click', function () {
			var i = +b.dataset.teMove, j = i + ( +b.dataset.dir ), ids = ar.list.map( function ( c ) { return c.id; } ), t = ids[ i ]; ids[ i ] = ids[ j ]; ids[ j ] = t;
			saveTabOrder( ids ); var again = $( '[data-te-move="' + j + '"][data-dir="' + b.dataset.dir + '"]' ) || $( '[data-te-move="' + j + '"]' ); if ( again ) { again.focus(); }
		} ); } );
		$$( '[data-te-ren]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { me.renaming = +b.dataset.teRen; viewYou(); var f = $( '#a-te-name' ); if ( f ) { f.focus(); f.select(); } } ); } );
		var cancel = $( '[data-te-cancel]' ); if ( cancel ) { cancel.addEventListener( 'click', function () { me.renaming = 0; viewYou(); } ); }
		var sv = $( '[data-te-save]' );
		if ( sv ) {
			var go = function () {
				var id = +sv.dataset.teSave, name = $( '#a-te-name' ).value.trim(), desc = $( '#a-te-desc' ).value.trim();
				if ( ! name ) { me.editMsg = __( 'Give the collection a name.', 'stubli' ); viewYou(); return; }
				sv.disabled = true;
				send( 'POST', 'arrange/' + id, { name: name, description: desc } ).then( function () {
					return send( 'GET', 'arrange', null );
				} ).then( function ( r ) {
					ar.list = r.collections; B.collOrder = r.collections.map( function ( c ) { return c.name; } ); B.collections = r.collections.map( function ( c ) { return c.name; } );
					me.renaming = 0; me.editMsg = __( 'Saved. Your page shows it now.', 'stubli' ); me.coll = ''; mine.loaded = false; me.p = null; viewYou();
				}, function ( e ) { sv.disabled = false; me.editMsg = e.message; viewYou(); } );
			};
			sv.addEventListener( 'click', go );
			$( '#a-te-name' ).addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); go(); } if ( e.key === 'Escape' ) { me.renaming = 0; viewYou(); } } );
		}
		// Drag by the grip, or anywhere on the row on a touch screen.
		var ul = $( '[data-te-list]' );
		if ( ul ) {
			Array.prototype.forEach.call( ul.children, function ( li ) {
				if ( li.classList.contains( 'is-renaming' ) ) { return; }
				var grip = li.querySelector( '[data-te-grip]' );
				grip.addEventListener( 'pointerdown', function ( e ) {
					e.preventDefault(); li.classList.add( 'is-drag' );
					var move = function ( ev ) {
						var sibs = Array.prototype.filter.call( ul.children, function ( x ) { return x !== li; } ), before = null;
						for ( var k = 0; k < sibs.length; k++ ) { var r = sibs[ k ].getBoundingClientRect(); if ( ev.clientY < r.top + r.height / 2 ) { before = sibs[ k ]; break; } }
						ul.insertBefore( li, before );
					};
					// Listen on the whole page: moving the row would drop a pointer capture.
					var up = function () {
						document.removeEventListener( 'pointermove', move ); document.removeEventListener( 'pointerup', up ); document.removeEventListener( 'pointercancel', up );
						li.classList.remove( 'is-drag' );
						saveTabOrder( Array.prototype.map.call( ul.children, function ( x ) { return +x.dataset.id; } ) );
					};
					document.addEventListener( 'pointermove', move );
					document.addEventListener( 'pointerup', up );
					document.addEventListener( 'pointercancel', up );
				} );
			} );
		}
		// The photo sheet.
		$$( '[data-sheet-close]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { me.sheet = null; viewYou(); } ); } );
		var pt = $( '[data-sheet-pin]' );
		if ( pt ) {
			pt.addEventListener( 'click', function () {
				var sh = me.sheet, on = sh.pins.indexOf( sh.id ) > -1, next = on ? sh.pins.filter( function ( x ) { return x !== sh.id; } ) : sh.pins.concat( [ sh.id ] );
				pt.disabled = true;
				send( 'POST', 'arrange/' + sh.cat, { pins: next } ).then( function () {
					me.sheet = null; ar.list = null; viewYou();
					toast( on ? _f( __( 'Unpinned from %s.', 'stubli' ), sh.coll ) : _f( __( 'Pinned to the top of %s on your page.', 'stubli' ), sh.coll ) );
				}, function ( e ) { pt.disabled = false; me.sheet.msg = e.message; viewYou(); } );
			} );
		}
		if ( me.sheet ) { var f = $( '[data-sheet] button, [data-sheet] a' ); if ( f ) { f.focus(); } }
	}
	// Hold a photo: pin it to the top of its collection, or edit it.
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && me.sheet && location.hash === '#you' ) { me.sheet = null; viewYou(); } } );
	function openSheet( id ) {
		var item = mine.items.filter( function ( i ) { return i.id === id; } )[ 0 ];
		if ( ! item ) { return; }
		// Posts in the untouched default collection show it as "Photos".
		var coll = item.coll || __( 'Photos', 'stubli' );
		me.sheet = { id: id, coll: coll, cat: 0, pins: [], loading: true, msg: '' };
		viewYou();
		( ar.list ? Promise.resolve( { collections: ar.list } ) : send( 'GET', 'arrange', null ) ).then( function ( r ) {
			ar.list = r.collections;
			var c = ar.list.filter( function ( x ) { return x.name === coll; } )[ 0 ];
			if ( ! c ) { me.sheet.loading = false; me.sheet.coll = ''; viewYou(); return null; }
			me.sheet.cat = c.id;
			return send( 'GET', 'arrange/' + c.id, null );
		} ).then( function ( r ) { if ( r && me.sheet ) { me.sheet.pins = r.pins || []; me.sheet.max = r.max; me.sheet.loading = false; viewYou(); } }, function ( e ) { if ( me.sheet ) { me.sheet.loading = false; me.sheet.msg = e.message; viewYou(); } } );
	}
	function sheetHtml() {
		var sh = me.sheet, on = sh.pins.indexOf( sh.id ) > -1;
		return '<div class="a-sheet-back" data-sheet-close aria-hidden="true"></div><div class="a-sheet" role="dialog" aria-modal="true" aria-label="' + esc( __( 'Photo options', 'stubli' ) ) + '" data-sheet>' +
			( sh.coll ? ( sh.loading ? '<p class="muted" role="status">' + __( 'Loading…', 'stubli' ) + '</p>' : '<button type="button" class="a-sheet-b" data-sheet-pin>' + icon( 'pin', 18 ) + ( on ? _f( __( 'Unpin from %s', 'stubli' ), esc( sh.coll ) ) : _f( __( 'Pin to the top of %s', 'stubli' ), esc( sh.coll ) ) ) + '</button>' ) : '<p class="muted">' + __( 'Put this post in a collection to pin it.', 'stubli' ) + '</p>' ) +
			( sh.msg ? '<p class="a-help" role="alert">' + esc( sh.msg ) + '</p>' : '' ) +
			'<a class="a-sheet-b" href="#edit/' + sh.id + '">' + icon( 'pen', 18 ) + __( 'Edit post', 'stubli' ) + '</a>' +
			'<button type="button" class="a-sheet-b a-sheet-cancel" data-sheet-close>' + __( 'Cancel', 'stubli' ) + '</button></div>';
	}

	/* ── Arrange your page: tab order, and pins inside each collection ── */
	var ar = { list: null, msg: '' };
	function arrangeHead( back, title ) {
		return '<header class="a-head"><a class="a-text-btn" href="' + back + '" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + '</a><h1>' + esc( title ) + '</h1><span></span></header>';
	}
	function viewArrange() {
		if ( ! ar.list ) {
			root.innerHTML = arrangeHead( '#you', __( 'Arrange your page', 'stubli' ) ) + '<main class="a-main" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:220px"></span></div></main>';
			send( 'GET', 'arrange', null ).then( function ( r ) { ar.list = r.collections; if ( location.hash === '#arrange' ) { viewArrange(); } }, function ( e ) { toast( e.message ); } );
			return;
		}
		var n = ar.list.length;
		root.innerHTML = arrangeHead( '#you', __( 'Arrange your page', 'stubli' ) ) + '<main class="a-main" id="main">' +
			'<section class="a-card"><h2>' + __( 'Tab order', 'stubli' ) + '</h2><p class="muted">' + __( 'The first tab is the one people see when they open your page.', 'stubli' ) + '</p>' +
			( n ? '<ol class="a-arr">' + ar.list.map( function ( c, i ) {
				return '<li><span class="a-arr-n" aria-hidden="true">' + ( i + 1 ) + '</span><span class="a-arr-name"><strong>' + esc( c.name ) + '</strong><small class="muted">' + ( c.count === 1 ? __( '1 post', 'stubli' ) : _f( __( '%s posts', 'stubli' ), c.count ) ) + ( c.pins ? ' · ' + ( c.pins === 1 ? __( '1 pinned', 'stubli' ) : _f( __( '%s pinned', 'stubli' ), c.pins ) ) : '' ) + '</small></span>' +
					'<a class="a-btn quiet a-arr-pin" href="#arrange/' + c.id + '" aria-label="' + esc( _f( __( 'Edit %s', 'stubli' ), c.name ) ) + '">' + icon( 'pen', 16 ) + __( 'Edit', 'stubli' ) + '</a>' +
					'<button type="button" class="a-icon-btn" data-arr-move="' + i + '" data-dir="-1"' + ( i === 0 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move %s up', 'stubli' ), c.name ) ) + '">' + icon( 'up', 20 ) + '</button>' +
					'<button type="button" class="a-icon-btn" data-arr-move="' + i + '" data-dir="1"' + ( i === n - 1 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move %s down', 'stubli' ), c.name ) ) + '">' + icon( 'down', 20 ) + '</button></li>';
			} ).join( '' ) + '</ol>' : '<p class="muted">' + __( 'Your collections appear here once you\'ve posted.', 'stubli' ) + '</p>' ) +
			'<p class="a-help" aria-live="polite" data-arr-msg>' + esc( ar.msg ) + '</p></section>' +
			'<p class="a-help">' + __( 'Only your own page changes. Your followers still see your posts newest first in their Home.', 'stubli' ) + '</p>' +
			'</main>' + bar( 'you' );
		$$( '[data-arr-move]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var i = +b.getAttribute( 'data-arr-move' ), j = i + ( +b.getAttribute( 'data-dir' ) );
				if ( j < 0 || j >= ar.list.length ) { return; }
				var t = ar.list[ i ]; ar.list[ i ] = ar.list[ j ]; ar.list[ j ] = t;
				ar.msg = __( 'Saving…', 'stubli' ); viewArrange();
				var again = $( '[data-arr-move="' + j + '"][data-dir="' + b.getAttribute( 'data-dir' ) + '"]' ) || $( '[data-arr-move="' + j + '"]' ); if ( again ) { again.focus(); }
				send( 'POST', 'arrange', { order: ar.list.map( function ( c ) { return c.id; } ) } ).then( function ( r ) { ar.list = r.collections; B.collOrder = r.collections.map( function ( c ) { return c.name; } ); ar.msg = __( 'Saved. Your page shows the new order.', 'stubli' ); var m = $( '[data-arr-msg]' ); if ( m ) { m.textContent = ar.msg; } me.p = null; }, function ( e ) { ar.msg = e.message; var m = $( '[data-arr-msg]' ); if ( m ) { m.textContent = e.message; } } );
			} );
		} );
	}
	var pn = null;
	function viewPins( cat ) {
		var c = ( ar.list || [] ).filter( function ( x ) { return x.id === cat; } )[ 0 ], title = c ? c.name : __( 'Pin posts', 'stubli' );
		if ( ! pn || pn.cat !== cat ) {
			pn = { cat: cat, posts: null, pins: [], max: 3, msg: '' };
			root.innerHTML = arrangeHead( '#arrange', title ) + '<main class="a-main" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:260px"></span></div></main>';
			send( 'GET', 'arrange/' + cat, null ).then( function ( r ) { pn.posts = r.posts; pn.pins = r.pins; pn.max = r.max; pn.name = r.name || ''; pn.desc = r.description || ''; if ( location.hash === '#arrange/' + cat ) { viewPins( cat ); } }, function ( e ) { toast( e.message ); } );
			return;
		}
		if ( ! pn.posts ) { return; }
		root.innerHTML = arrangeHead( '#arrange', pn.name || title ) + '<main class="a-main" id="main">' +
			'<section class="a-card"><h2>' + __( 'Name and words', 'stubli' ) + '</h2>' +
			'<label class="a-label" for="a-cn">' + __( 'Name', 'stubli' ) + '</label><input id="a-cn" class="a-field" maxlength="40" value="' + esc( pn.name ) + '">' +
			'<label class="a-label" for="a-cd">' + __( 'A line under the tab', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></label><input id="a-cd" class="a-field" maxlength="200" placeholder="' + esc( __( 'e.g. Mostly at dawn, by the lake.', 'stubli' ) ) + '" value="' + esc( pn.desc ) + '">' +
			'<div class="a-actions" style="margin-top:14px"><button type="button" class="a-btn" data-cn-save>' + __( 'Save', 'stubli' ) + '</button></div><p class="a-help" aria-live="polite" data-cn-msg>' + esc( pn.nameMsg || '' ) + '</p></section>' +
			'<h2 class="a-label">' + __( 'Pin to the top', 'stubli' ) + '</h2>' +
			'<p class="muted" style="margin:4px 0 12px">' + _f( __( 'Tap up to %s posts to pin them to the top of this collection, in the order you tap them. Tap again to unpin. Everything else stays newest first.', 'stubli' ), pn.max ) + '</p>' +
			'<ul class="a-pins" role="list">' + pn.posts.map( function ( p ) {
				var at = pn.pins.indexOf( p.id );
				return '<li><button type="button" class="a-pin-tile' + ( at > -1 ? ' is-pinned' : '' ) + '" data-pin="' + p.id + '" aria-pressed="' + ( at > -1 ) + '" aria-label="' + esc( ( p.title || __( 'Photo post', 'stubli' ) ) + ( at > -1 ? ', ' + _f( __( 'pinned %s', 'stubli' ), at + 1 ) : '' ) ) + '">' +
					( p.thumb ? '<img src="' + esc( p.thumb ) + '" alt="" loading="lazy">' : '' ) + ( at > -1 ? '<span class="a-pin-n" aria-hidden="true">' + icon( 'pin', 12 ) + ( at + 1 ) + '</span>' : '' ) + '</button></li>';
			} ).join( '' ) + '</ul>' +
			'<p class="a-help" aria-live="polite" data-pin-msg>' + esc( pn.msg ) + '</p></main>' + bar( 'you' );
		$( '#a-cn' ).addEventListener( 'input', function ( e ) { pn.name = e.target.value; } );
		$( '#a-cd' ).addEventListener( 'input', function ( e ) { pn.desc = e.target.value; } );
		$( '[data-cn-save]' ).addEventListener( 'click', function ( e ) {
			var btn = e.currentTarget, m = $( '[data-cn-msg]' );
			if ( ! pn.name.trim() ) { m.textContent = __( 'Give the collection a name.', 'stubli' ); return; }
			btn.disabled = true; m.textContent = __( 'Saving…', 'stubli' );
			send( 'POST', 'arrange/' + cat, { name: pn.name, description: pn.desc } ).then( function ( r ) { pn.name = r.name; pn.desc = r.description; pn.nameMsg = __( 'Saved. Your page shows the new name.', 'stubli' ); ar.list = null; B.collOrder = null; mine.loaded = false; me.p = null; viewPins( cat ); },
				function ( err ) { btn.disabled = false; m.textContent = err.message; } );
		} );
		$$( '[data-pin]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = +b.getAttribute( 'data-pin' ), at = pn.pins.indexOf( id ), next = pn.pins.slice();
				if ( at > -1 ) { next.splice( at, 1 ); } else if ( next.length >= pn.max ) { pn.msg = _f( __( 'That\'s %s pinned already. Tap one to unpin it first.', 'stubli' ), pn.max ); $( '[data-pin-msg]' ).textContent = pn.msg; return; } else { next.push( id ); }
				pn.pins = next; pn.msg = __( 'Saving…', 'stubli' ); viewPins( cat );
				var f = $( '[data-pin="' + id + '"]' ); if ( f ) { f.focus(); }
				send( 'POST', 'arrange/' + cat, { pins: next } ).then( function ( r ) { pn.pins = r.pins; pn.msg = next.length ? __( 'Saved. Pinned posts lead this collection on your page.', 'stubli' ) : __( 'Saved. Nothing pinned here now.', 'stubli' ); var m = $( '[data-pin-msg]' ); if ( m ) { m.textContent = pn.msg; } ar.list = null; me.p = null; }, function ( e ) { var m = $( '[data-pin-msg]' ); if ( m ) { m.textContent = e.message; } } );
			} );
		} );
	}

	/* ── Edit page: your line and your prompt cards ── */
	var ab = null;
	function viewAbout() {
		if ( ! ab ) {
			ab = { loaded: false, cards: [], choices: [], max: 3, maxA: 150, bio: '', picking: null, own: '', busy: false, error: '' };
			root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit page', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:200px"></span></div></main>' );
			Promise.all( [ send( 'GET', 'prompts', null ), me.p ? Promise.resolve( me.p ) : send( 'GET', 'profile', null ), send( 'GET', 'setup', null ) ] ).then( function ( r ) {
				ab.cards = ( r[ 0 ].prompts || [] ).slice(); ab.choices = r[ 0 ].choices || []; ab.max = r[ 0 ].max || 3; ab.maxA = r[ 0 ].max_answer || 150;
				me.p = me.p || r[ 1 ]; ab.bio = String( r[ 1 ].bio || '' ); ab.d = r[ 2 ]; ab.name = String( r[ 2 ].name || '' ); ab.pronouns = String( r[ 2 ].pronouns || '' ); ab.loaded = true;
				if ( location.hash === '#about' ) { renderAbout(); }
			}, function ( e ) { ab = null; root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit page', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-notice" role="alert"><p>' ) + esc( e.message ) + ( '</p><a class="a-btn quiet" href="#you">' + __( 'Back', 'stubli' ) + '</a></div></main>' ); } );
			return;
		}
		if ( ab.loaded ) { renderAbout(); }
	}
	function renderAbout() {
		var used = ab.cards.map( function ( c ) { return c.q; } );
		var picker = function ( slot ) {
			return '<div class="a-picker" role="group" aria-label="' + esc( __( 'Choose a prompt', 'stubli' ) ) + '">' + ab.choices.filter( function ( q ) { return used.indexOf( q ) < 0; } ).map( function ( q ) { return '<button type="button" class="a-chip" data-pick-q="' + esc( q ) + '" data-slot="' + slot + '">' + esc( q ) + '</button>'; } ).join( '' ) +
				( '</div><div class="a-inline"><label class="sr" for="a-own-q">' + __( 'Your own prompt', 'stubli' ) + '</label><input id="a-own-q" class="a-field" maxlength="80" placeholder="' + esc( __( 'Or write your own…', 'stubli' ) ) + '" value="' ) + esc( ab.own ) + '"><button type="button" class="a-btn quiet" data-own-q data-slot="' + slot + ( '">' + __( 'Use', 'stubli' ) + '</button></div>' ) +
				( '<button type="button" class="a-text-btn" data-pick-cancel>' + __( 'Cancel', 'stubli' ) + '</button>' );
		};
		root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit page', 'stubli' ) + '</h1><button type="button" class="a-btn" data-ab-save' ) + ( ab.busy ? ' disabled' : '' ) + '>' + ( ab.busy ? __( 'Saving…', 'stubli' ) : __( 'Save', 'stubli' ) ) + '</button></header>' +
			'<main class="a-compose" id="main">' + ( ab.error ? '<div class="a-notice" role="alert"><p>' + esc( ab.error ) + '</p></div>' : '' ) +
			( ab.d ? '<h2 class="a-label">' + __( 'Cover photo', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></h2>' +
				( ab.cc ? coverEditor() : '<div class="a-cover' + ( ab.d.cover ? '' : ' is-empty' ) + '">' + ( ab.d.cover ? '<img src="' + esc( ab.d.cover ) + '" alt="' + esc( __( 'Your cover photo', 'stubli' ) ) + '">' : '<span>' + icon( 'photo', 22 ) + '</span>' ) + '</div>' ) +
				'<div class="a-actions"' + ( ab.cc ? ' hidden' : '' ) + '><label class="a-btn quiet" for="a-ab-cover">' + icon( 'photo', 18 ) + ( ab.d.cover ? __( 'Change cover', 'stubli' ) : __( 'Choose a cover photo', 'stubli' ) ) + '</label><input id="a-ab-cover" class="a-file" type="file" accept="image/jpeg,image/png,image/webp,image/heic">' +
				( ab.d.cover ? '<button type="button" class="a-text-btn" data-ab-cover-off>' + __( 'Remove', 'stubli' ) + '</button>' : '' ) + '</div>' +
				( ab.d.coverSeen ? '<p class="a-help"><a href="' + esc( B.home + ( B.home.indexOf( '?' ) > -1 ? '&' : '?' ) + 'fresh=' + ab.d.coverSeen ) + '">' + __( 'See it on your page', 'stubli' ) + '</a></p>' : '' ) + '<p class="a-help" aria-live="polite" data-ab-cover-msg' + ( ab.cc ? ' hidden' : '' ) + '>' + esc( ab.d.coverMsg || __( 'A wide landscape photo works best, at least 1500 × 500 pixels. JPEG, PNG or WebP. You choose which part shows, and location details are removed.', 'stubli' ) ) + '</p>' : '' ) +
			( ab.d ? '<h2 class="a-label">' + __( 'Profile photo', 'stubli' ) + '</h2><div class="a-su-photo" style="margin-top:4px"><label class="a-su-av" for="a-ab-file">' + ( ab.d.avatar ? '<img src="' + esc( ab.d.avatar ) + '" alt="' + ( ab.d.photo ? __( 'Your photo', 'stubli' ) : __( 'Your smiley', 'stubli' ) ) + '">' : '' ) + '</label>' +
				'<div><label class="a-btn quiet" for="a-ab-file">' + icon( 'photo', 18 ) + ( ab.d.photo ? __( 'Change photo', 'stubli' ) : __( 'Add a photo', 'stubli' ) ) + '</label><input id="a-ab-file" class="a-file" type="file" accept="image/*"><p class="a-help" aria-live="polite" data-ab-photo-msg>' + esc( ab.d.photoMsg || ( ab.d.photo ? __( 'A face, a place, anything that feels like you.', 'stubli' ) : __( 'Or keep your smiley, and make it yours below.', 'stubli' ) ) ) + '</p></div></div>' + smileyPicker( ab.d ) : '' ) +
			( '<label class="a-label" for="a-ab-name">' + __( 'Your name', 'stubli' ) + '</label><input id="a-ab-name" class="a-field" type="text" maxlength="60" autocomplete="name" value="' ) + esc( ab.name ) + '">' +
			( '<label class="a-label" for="a-ab-bio">' + __( 'One line about you', 'stubli' ) + '</label><textarea id="a-ab-bio" class="a-field a-grow" rows="2" maxlength="160">' ) + esc( ab.bio ) + '</textarea><p class="a-count" data-ab-bio-n>' + ab.bio.length + ' / 160</p>' +
			( '<label class="a-label" for="a-ab-pro">' + __( 'Pronouns', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></label><input id="a-ab-pro" class="a-field" type="text" maxlength="30" value="' ) + esc( ab.pronouns ) + '" placeholder="' + esc( __( 'she/her, they/them…', 'stubli' ) ) + '">' +
			( '<h2 class="a-label">' + __( 'Cards about you', 'stubli' ) + '</h2><p class="muted" style="margin:-4px 0 12px">' + _f( __( 'Up to %s cards sit behind your bio on your page. Short answers work best, and people can reply to each one.', 'stubli' ), ab.max ) ) + ( '' + '</p>' ) +
			ab.cards.map( function ( c, k ) {
				return '<div class="a-card a-pedit">' + ( ab.picking === k ? picker( k ) :
					'<div class="a-row"><label class="a-label" style="margin:0" for="a-pa-' + k + '">' + esc( c.q ) + '</label><span><button type="button" class="a-text-btn" data-change="' + k + ( '">' + __( 'Change', 'stubli' ) + '</button><button type="button" class="a-text-btn" data-remove="' ) + k + '" aria-label="' + esc( _f( __( 'Remove %s', 'stubli' ), c.q ) ) + ( '">' + __( 'Remove', 'stubli' ) + '</button></span></div>' ) +
					'<textarea id="a-pa-' + k + '" class="a-field a-grow a-pedit-a" rows="2" maxlength="' + ab.maxA + '" data-answer="' + k + '">' + esc( c.a ) + '</textarea><p class="a-count" data-answer-n="' + k + '">' + c.a.length + ' / ' + ab.maxA + '</p>' ) + '</div>';
			} ).join( '' ) +
			( ab.cards.length < ab.max ? ( ab.picking === 'new' ? '<div class="a-card a-pedit">' + picker( 'new' ) + '</div>' : '<button type="button" class="a-btn quiet wide" data-add-card>' + icon( 'plus', 18 ) + ( __( 'Add a card', 'stubli' ) + '</button>' ) ) : '' ) +
			'</main>';
		var grow = function ( t ) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
		$( '#a-ab-name' ).addEventListener( 'input', function ( e ) { ab.name = e.target.value; } );
		$( '#a-ab-pro' ).addEventListener( 'input', function ( e ) { ab.pronouns = e.target.value; } );
		if ( ab.d ) {
			bindSmiley( ab.d, renderAbout );
			var cf = $( '#a-ab-cover' );
			cf.addEventListener( 'change', function () {
				var f = cf.files && cf.files[ 0 ], m = $( '[data-ab-cover-msg]' ); if ( ! f ) { return; }
				m.textContent = __( 'Opening your photo…', 'stubli' );
				loadImage( f ).then( function ( img ) { ab.cc = { img: img, rot: 0, zoom: 1, x: 0, y: 0 }; renderAbout(); },
					function () { m.textContent = __( 'That photo couldn\'t be read. Try another one.', 'stubli' ); } );
			} );
			bindCoverEditor();
			var co = $( '[data-ab-cover-off]' );
			if ( co ) { co.addEventListener( 'click', function () { send( 'DELETE', 'setup/cover', null ).then( function () { ab.d.cover = ''; ab.d.coverMsg = __( 'Cover removed.', 'stubli' ); me.p = null; renderAbout(); }, function ( e ) { toast( e.message ); } ); } ); }
			var af = $( '#a-ab-file' );
			af.addEventListener( 'change', function () {
				var f = af.files && af.files[ 0 ], m = $( '[data-ab-photo-msg]' ); if ( ! f ) { return; }
				m.textContent = __( 'Uploading…', 'stubli' );
				squareAvatar( f ).then( function ( blob ) { var fd = new FormData(); fd.append( 'photo', blob, 'profile.jpg' ); return send( 'POST', 'setup/photo', fd ); } )
					.then( function ( r ) { ab.d.avatar = r.avatar; ab.d.photo = true; ab.d.smileyOpen = false; ab.d.photoMsg = __( 'Lovely. That\'s your photo now.', 'stubli' ); me.p = null; renderAbout(); },
						function ( e ) { m.textContent = ( e && e.message ) || __( 'That photo couldn\'t be read. Try another one.', 'stubli' ); } );
			} );
		}
		var bio = $( '#a-ab-bio' ); grow( bio );
		bio.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); } } );
		bio.addEventListener( 'input', function () { ab.bio = bio.value.replace( /\s*\n+\s*/g, ' ' ); $( '[data-ab-bio-n]' ).textContent = ab.bio.length + ' / 160'; grow( bio ); } );
		$$( '[data-answer]' ).forEach( function ( t ) { grow( t ); t.addEventListener( 'input', function () { var k = +t.dataset.answer; ab.cards[ k ].a = t.value.replace( /\s*\n+\s*/g, ' ' ); $( '[data-answer-n="' + k + '"]' ).textContent = ab.cards[ k ].a.length + ' / ' + ab.maxA; grow( t ); } ); } );
		var setQ = function ( slot, q ) {
			q = String( q || '' ).trim(); if ( ! q ) { return; }
			if ( slot === 'new' ) { ab.cards.push( { q: q, a: '' } ); slot = ab.cards.length - 1; } else { ab.cards[ +slot ].q = q; }
			ab.picking = null; ab.own = ''; renderAbout(); var t = $( '#a-pa-' + slot ); if ( t ) { t.focus(); }
		};
		$$( '[data-pick-q]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { setQ( b.dataset.slot, b.dataset.pickQ ); } ); } );
		var own = $( '[data-own-q]' ); if ( own ) { var oi = $( '#a-own-q' ); oi.addEventListener( 'input', function () { ab.own = oi.value; } ); own.addEventListener( 'click', function () { var v = oi.value.trim(); if ( ! v ) { oi.focus(); return; } setQ( own.dataset.slot, /[…?:]$/.test( v ) ? v : v + '…' ); } ); }
		var pc = $( '[data-pick-cancel]' ); if ( pc ) { pc.addEventListener( 'click', function () { ab.picking = null; renderAbout(); } ); }
		var add = $( '[data-add-card]' ); if ( add ) { add.addEventListener( 'click', function () { ab.picking = 'new'; renderAbout(); var f = $( '[data-pick-q]' ); if ( f ) { f.focus(); } } ); }
		$$( '[data-change]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { ab.picking = +b.dataset.change; renderAbout(); } ); } );
		$$( '[data-remove]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { ab.cards.splice( +b.dataset.remove, 1 ); renderAbout(); } ); } );
		$( '[data-ab-save]' ).addEventListener( 'click', function () {
			var empty = ab.cards.filter( function ( c ) { return ! c.a.trim(); } )[ 0 ];
			if ( empty ) { ab.error = _f( __( 'Write an answer for "%s", or remove that card.', 'stubli' ), empty.q ); renderAbout(); return; }
			if ( ! ab.name.trim() ) { ab.error = __( 'Add the name people should see.', 'stubli' ); renderAbout(); $( '#a-ab-name' ).focus(); return; }
			ab.busy = true; ab.error = ''; renderAbout();
			Promise.all( [ send( 'POST', 'prompts', { prompts: ab.cards } ), send( 'POST', 'setup', { bio: ab.bio, name: ab.name.trim(), pronouns: ab.pronouns.trim() } ) ] ).then( function ( r ) {
				me.p = null; me.i = 0; ab = null; location.hash = '#you'; toast( __( 'Your page is updated.', 'stubli' ) );
			}, function ( e ) { ab.busy = false; ab.error = e.message; renderAbout(); } );
		} );
	}

	/* ── Notes: private replies (Q48) ── */
	var nt = { loaded: false, inbox: [], requests: [], done: {} };
	function loadNotes() {
		return send( 'GET', 'notes', null ).then( function ( r ) { nt.inbox = r.inbox || []; nt.requests = r.requests || []; nt.loaded = true; B.requests = nt.requests.length; }, function () { nt.loaded = true; } );
	}
	function contactLink( c ) {
		c = String( c || '' ).trim();
		if ( /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( c ) ) { return { href: 'mailto:' + c, label: __( 'Write back by email', 'stubli' ) }; }
		if ( /^\+?[\d\s()\-]{7,}$/.test( c ) ) { return { href: 'tel:' + c.replace( /[^\d+]/g, '' ), label: __( 'Call or text', 'stubli' ) }; }
		return null;
	}
	function noteCard( n, isRequest ) {
		var d = nt.done[ n.id ];
		if ( d ) { return '<div class="a-card a-note is-done" data-note-card="' + n.id + '"><p role="status" style="margin:0">' + esc( d ) + '</p></div>'; }
		var who = n.kind === 'guest' ? __( 'no Stubli', 'stubli' ) : hostOf( n.site ), c = n.kind === 'guest' ? contactLink( n.contact ) : null;
		return '<div class="a-card a-note" data-note-card="' + n.id + '"><div class="a-wait-head">' + face( n.avatar, n.name, 36 ) +
			'<span><strong>' + esc( unent( n.name ) ) + '</strong> <span class="muted">' + esc( who ) + ( isRequest ? ( ' ' + __( '· Request', 'stubli' ) ) : '' ) + ' · ' + esc( gentle( n.date ) ) + '</span></span></div>' +
			( n.prompt ? '<p class="muted small a-note-re">' + _f( __( 'Replying to “%s”', 'stubli' ), esc( n.prompt.q ) ) + '</p>' : '' ) +
			'<p class="a-note-text">' + esc( n.text ) + '</p>' +
			( n.kind === 'guest' ? '<p class="a-note-contact">' + _f( __( 'Reach %s at %s', 'stubli' ), esc( unent( n.name ) ), '<strong>' + esc( n.contact ) + '</strong>' ) + '</p>' : '' ) +
			'<div class="a-wait-acts">' +
			( isRequest ? '<button type="button" class="a-btn" data-note="accept" data-id="' + n.id + '">' + ( n.kind === 'guest' ? __( 'Keep', 'stubli' ) : __( 'Accept', 'stubli' ) ) + '</button>' : '' ) +
			( ! isRequest && n.kind !== 'guest' ? '<a class="a-btn" href="#note/' + n.id + '">' + ( n.replied ? __( 'Reply again', 'stubli' ) : __( 'Reply', 'stubli' ) ) + '</a>' : '' ) +
			( ! isRequest && c ? '<a class="a-btn" href="' + esc( c.href ) + '">' + esc( c.label ) + '</a>' : '' ) +
			'<button type="button" class="a-btn quiet" data-note="delete" data-id="' + n.id + ( '">' + __( 'Delete', 'stubli' ) + '</button>' ) +
			'<button type="button" class="a-text-btn a-block" data-note="block" data-id="' + n.id + ( '">' + __( 'Block', 'stubli' ) + '</button></div></div>' );
	}
	function notesSection() {
		if ( ! nt.loaded || ( ! nt.inbox.length && ! nt.requests.length ) ) { return ''; }
		var open = nt.requests.filter( function ( n ) { return ! nt.done[ n.id ]; } ).length + nt.inbox.filter( function ( n ) { return ! nt.done[ n.id ]; } ).length;
		return ( '<h2 class="a-label">' + __( 'Notes', 'stubli' ) ) + ( open ? ' · ' + open : '' ) + '</h2>' + nt.requests.map( function ( n ) { return noteCard( n, true ); } ).join( '' ) + nt.inbox.slice( 0, 20 ).map( function ( n ) { return noteCard( n, false ); } ).join( '' );
	}
	function bindNotes( redraw ) {
		$$( '[data-note]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = +b.dataset.id, act = b.dataset.note;
				$$( '[data-note][data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = true; } );
				send( 'POST', 'notes/' + id, { action: act } ).then( function () {
					var n = nt.requests.concat( nt.inbox ).filter( function ( x ) { return x.id === id; } )[ 0 ];
					if ( act === 'accept' && n ) { nt.requests = nt.requests.filter( function ( x ) { return x.id !== id; } ); nt.inbox.unshift( n ); }
					else { nt.done[ id ] = act === 'block' ? __( 'Blocked quietly. Nothing more from them will arrive, and they aren\'t told.', 'stubli' ) : __( 'Deleted.', 'stubli' ); }
					B.requests = nt.requests.filter( function ( x ) { return ! nt.done[ x.id ]; } ).length;
					redraw();
				}, function ( e ) { $$( '[data-note][data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = false; } ); toast( e.message ); } );
			} );
		} );
	}
	var nc = null;
	function viewNote( id ) {
		var n = nt.inbox.filter( function ( x ) { return x.id === id; } )[ 0 ];
		if ( ! n ) {
			root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#whatsnew">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Reply', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:160px"></span></div></main>' );
			loadNotes().then( function () { if ( nt.inbox.some( function ( x ) { return x.id === id; } ) ) { viewNote( id ); } else { location.replace( '#whatsnew' ); } } );
			return;
		}
		if ( ! nc || nc.id !== id ) { nc = { id: id, text: '', busy: false, error: '' }; }
		root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#whatsnew">' + __( 'Close', 'stubli' ) + '</a><h1>' + _f( __( 'Reply to %s', 'stubli' ), esc( unent( n.name ) ) ) ) + '</h1><button type="button" class="a-btn" data-nc-send' + ( nc.busy || ! nc.text.trim() ? ' disabled' : '' ) + '>' + ( nc.busy ? __( 'Sending…', 'stubli' ) : __( 'Send', 'stubli' ) ) + '</button></header>' +
			'<main class="a-compose" id="main"><div class="a-card a-note"><p class="muted small" style="margin:0 0 4px">' + esc( unent( n.name ) ) + ' wrote</p><p class="a-note-text" style="margin:0">' + esc( n.text ) + '</p></div>' +
			( nc.error ? '<div class="a-notice" role="alert"><p>' + esc( nc.error ) + '</p></div>' : '' ) +
			( '<label class="a-label" for="a-nc">' + __( 'Your note', 'stubli' ) + '</label><textarea id="a-nc" class="a-textarea" maxlength="1000">' ) + esc( nc.text ) + '</textarea>' +
			( '<p class="a-help">' + _f( __( 'Private: only %s sees it, in their own Stubli.', 'stubli' ), esc( unent( n.name ) ) ) ) + ( '' + '</p></main>' );
		var t = $( '#a-nc' ), b = $( '[data-nc-send]' );
		t.addEventListener( 'input', function () { nc.text = t.value; b.disabled = ! nc.text.trim() || nc.busy; } );
		b.addEventListener( 'click', function () {
			nc.busy = true; nc.error = ''; b.disabled = true; b.textContent = __( 'Sending…', 'stubli' );
			send( 'POST', 'notes/send', { to: n.site, text: nc.text, reply: n.id } ).then( function () { n.replied = true; nc = null; location.hash = '#whatsnew'; toast( _f( __( 'Sent. Only %s sees it.', 'stubli' ), unent( n.name ) ) ); }, function ( e ) { nc.busy = false; nc.error = e.message; viewNote( id ); } );
		} );
		t.focus();
	}

	/* ── Your people: who you follow, and who is a friend (Q44) ── */
	var pp = { loaded: false, loading: false, list: [], open: false, msg: '' };
	function peopleCard() {
		var list = pp.list || [], shown = pp.open ? list : list.slice( 0, 6 );
		return ( '<section class="a-card" data-people><h2>' + __( 'Your people', 'stubli' ) ) + helpLink( 'friends-and-privacy/friends-only', __( 'friends only posts', 'stubli' ) ) + '</h2>' +
			( list.length ? ( '<p class="muted">' + __( 'Tick your friends. They see your posts marked "Friends only", in their own Stubli; nobody else does.', 'stubli' ) + '</p><ul class="a-people">' ) + shown.map( function ( f ) {
				var host = hostOf( f.site_url ), name = f.name || host;
				return '<li><span class="a-people-who"><strong>' + esc( name ) + '</strong><small class="muted">' + esc( host ) + ( f.list_name ? ' · ' + esc( f.list_name ) : '' ) + '</small></span>' +
					'<label class="a-friend"><input type="checkbox" data-friend="' + f.id + '"' + ( f.friend ? ' checked' : '' ) + ( '><span>' + __( 'Friend', 'stubli' ) + '</span><span class="sr"> – ' ) + esc( name ) + '</span></label></li>';
			} ).join( '' ) + '</ul>' + ( list.length > 6 && ! pp.open ? '<button type="button" class="a-btn quiet wide" data-people-all>' + _f( __( 'Show all %s', 'stubli' ), list.length ) + '</button>' : '' ) : ( pp.loaded ? ( '<p class="muted">' + __( 'You don\'t follow anyone yet.', 'stubli' ) + '</p>' ) : '' ) ) +
			( pp.msg ? '<p class="a-help" role="status">' + esc( pp.msg ) + '</p>' : '' ) +
			( '<p class="a-label">' + __( 'Follow someone', 'stubli' ) + '</p><p class="muted" style="margin:0 0 8px">' + __( 'Know someone with a Stubli? Type their address.', 'stubli' ) + '</p>' ) + followForm( 'you' ) + '</section>';
	}
	function redrawPeople() { swap( '[data-people]', peopleCard(), function () { bindPeople(); bindFollow(); } ); }
	function bindPeople() {
		var all = $( '[data-people-all]' ); if ( all ) { all.addEventListener( 'click', function () { pp.open = true; redrawPeople(); } ); }
		$$( '[data-friend]' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var id = +cb.dataset.friend, on = cb.checked, f = pp.list.filter( function ( x ) { return +x.id === id; } )[ 0 ];
				cb.disabled = true;
				send( 'PATCH', 'follows/' + id, { friend: on } ).then( function ( r ) {
					if ( f ) { f.friend = r.friend; }
					B.friends = pp.list.filter( function ( x ) { return x.friend; } ).length;
					var name = f && ( f.name || hostOf( f.site_url ) );
					pp.msg = on ? ( r.sent ? _f( __( '%s is a friend now.', 'stubli' ), name ) : _f( __( '%s is a friend now. Their Stubli couldn\'t be reached just now, so they\'ll see your friends only posts once it can (we keep trying for a week).', 'stubli' ), name ) ) : _f( __( '%s is no longer a friend. Your friends posts leave their feed at its next check.', 'stubli' ), name );
					redrawPeople();
				}, function ( e ) { cb.checked = ! on; cb.disabled = false; pp.msg = e.message; redrawPeople(); } );
			} );
		} );
	}

	/* ── Yours to keep: download everything, move to another site ── */
	var kp = { loaded: false, loading: false, ar: null, busy: false, msg: '' };
	function keepCard() {
		var a = kp.ar || { state: 'none' }, body;
		if ( a.state === 'packing' ) {
			body = '<p class="a-ds-status" role="status">' + __( 'Packing your archive…', 'stubli' ) + ' ' + ( a.total ? _f( __( '%s of %s photos and videos', 'stubli' ), a.done, a.total ) : '' ) + '</p><div class="a-progress" aria-hidden="true"><span style="width:' + ( a.total ? Math.round( 100 * a.done / a.total ) : 5 ) + ( '%"></span></div><p class="a-help">' + __( 'Keep this screen open until it\'s ready. It can take a few minutes.', 'stubli' ) + '</p>' );
		} else if ( a.state === 'ready' ) {
			body = '<p class="a-ds-status" role="status">' + icon( 'check', 16 ) + _f( __( 'Your archive is ready (%s)', 'stubli' ), esc( a.size ) ) + '</p><div class="a-actions"><a class="a-btn" href="' + esc( a.download ) + ( '">' + __( 'Download', 'stubli' ) + '</a><button type="button" class="a-btn quiet" data-keep-new>' + __( 'Make a new one', 'stubli' ) + '</button></div><p class="a-help">' + _f( __( 'Kept on your site until %s, then removed.', 'stubli' ), esc( a.expires ) ) ) + '</p>';
		} else {
			body = '<button type="button" class="a-btn quiet" data-keep-start' + ( kp.busy || a.supported === false ? ' disabled' : '' ) + '>' + ( kp.busy ? __( 'Starting…', 'stubli' ) : __( 'Download everything', 'stubli' ) ) + '</button>' + ( a.supported === false ? ( '<p class="a-help">' + __( 'This host can\'t make zip files. Ask your host to turn on PHP\'s Zip extension.', 'stubli' ) + '</p>' ) : '' );
		}
		return ( '<section class="a-card" data-keep><h2>' + __( 'Yours to keep', 'stubli' ) ) + helpLink( 'moving-and-leaving/download', __( 'downloading everything', 'stubli' ) ) + ( '</h2><p class="muted">' + __( 'One zip with your photos and videos as they are on your site, your captions, journal, comments, guest book and the people you follow. It opens offline in any browser.', 'stubli' ) + '</p>' ) + body +
			( kp.msg ? '<div class="a-notice" role="alert"><p>' + esc( kp.msg ) + '</p></div>' : '' ) + '</section>' + moveCard();
	}
	/* Move: out (this site is the old one) and in (this site is the new one). */
	var mv = { out: null, link: '', expires: '', inState: null, busy: false, msg: '', copied: false };
	function moveProgress( st ) {
		if ( ! st || st.state === 'none' ) { return ''; }
		if ( st.state === 'moving' ) {
			return '<p class="a-ds-status" role="status">' + _f( __( 'Copying from %s… %s of %s', 'stubli' ), esc( hostOf( st.from ) ), st.done, st.total ) + '</p><div class="a-progress" aria-hidden="true"><span style="width:' + ( st.total ? Math.round( 100 * st.done / st.total ) : 5 ) + ( '%"></span></div><p class="a-help">' + __( 'Photos and videos arrive at full size, so this can take a while. Keep this screen open.', 'stubli' ) + '</p>' );
		}
		return '<p class="a-ds-status" role="status">' + icon( 'check', 16 ) + ( st.skipped ? ( st.skipped > 1 ? _f( __( 'Everything from %s is here (%s items couldn\'t be copied).', 'stubli' ), esc( hostOf( st.from ) ), st.skipped ) : _f( __( 'Everything from %s is here (one item couldn\'t be copied).', 'stubli' ), esc( hostOf( st.from ) ) ) ) : _f( __( 'Everything from %s is here.', 'stubli' ), esc( hostOf( st.from ) ) ) ) + '</p><p class="a-help">' + ( st.told ? __( 'Your old site now points here, and the people who follow you switch to this address within a few hours.', 'stubli' ) : __( 'Your old site couldn\'t be told about the move. Your followers can follow you here by your new address.', 'stubli' ) ) + '</p>';
	}
	function moveCard() {
		var o = mv.out || {}, out;
		if ( o.moved_to ) {
			out = '<p class="a-ds-status">' + icon( 'check', 16 ) + _f( __( 'This Stubli has moved to %s.', 'stubli' ), esc( hostOf( o.moved_to ) ) ) + ( '</p><p class="a-help">' + __( 'Your followers switch to the new address by themselves. You can keep this site as it is, or close it once they have.', 'stubli' ) + '</p>' );
		} else if ( mv.link ) {
			out = ( '<label class="a-label" for="a-mv-link">' + __( 'Your move link', 'stubli' ) + '</label><div class="a-inline" style="margin-top:0"><input id="a-mv-link" class="a-field" type="text" readonly value="' ) + esc( mv.link ) + '"><button type="button" class="a-btn quiet" data-mv-copy>' + ( mv.copied ? __( 'Copied', 'stubli' ) : __( 'Copy', 'stubli' ) ) + '</button></div>' +
				'<p class="a-help">' + _f( __( 'Paste it into your new Stubli, in its setup or under You → Settings and more → Move to another site. It works until %s. Keep it private: it lets the new site copy your photos, journal, guest book and people, including entries only you can see.', 'stubli' ), esc( mv.expires ) ) + ( '</p><button type="button" class="a-btn quiet" data-mv-stop>' + __( 'Stop this link', 'stubli' ) + '</button>' );
		} else {
			out = ( '<p class="muted">' + __( 'Moving to a new WordPress, for example your own server? Make a move link here and paste it into your new Stubli. It copies everything, and your followers come along.', 'stubli' ) + '</p><button type="button" class="a-btn quiet" data-mv-make' ) + ( mv.busy ? ' disabled' : '' ) + ( '>' + __( 'Make a move link', 'stubli' ) + '</button>' ) + ( o.has_link ? ( '<p class="a-help">' + __( 'A move link is already active. Making a new one stops the old one.', 'stubli' ) + '</p>' ) : '' );
		}
		var st = mv.inState, inn = moveProgress( st );
		if ( ! inn ) {
			inn = ( '<form class="a-inline" data-mv-in style="margin-top:0"><label class="sr" for="a-mv-in">' + __( 'Move link from your old Stubli', 'stubli' ) + '</label><input id="a-mv-in" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="' + esc( __( 'oldsite.com/?stubli_move=…', 'stubli' ) ) + '"><button type="submit" class="a-btn quiet"' ) + ( mv.busy ? ' disabled' : '' ) + ( '>' + __( 'Start', 'stubli' ) + '</button></form>' );
		}
		return ( '<section class="a-card" data-move><h2>' + __( 'Move to another site', 'stubli' ) ) + helpLink( 'moving-and-leaving/move', __( 'moving to another site', 'stubli' ) ) + '</h2>' + out +
			( '<p class="a-label" style="margin-top:18px">' + __( 'Moving here from another Stubli?', 'stubli' ) + '</p>' ) + inn +
			( mv.msg ? '<div class="a-notice" role="alert"><p>' + esc( mv.msg ) + '</p></div>' : '' ) + '</section>';
	}
	function redrawMove() { swap( '[data-move]', moveCard(), bindMove ); }
	function runMove( onUpdate ) {
		if ( mv.running ) { return; }
		mv.running = true;
		var go = function () {
			send( 'POST', 'move/in/step', {} ).then( function ( r ) {
				mv.inState = r; onUpdate();
				if ( r.state === 'moving' ) { return go(); }
				mv.running = false; feed.loaded = false; mine.loaded = false;
			}, function ( e ) { mv.running = false; mv.msg = e.message + ( ' ' + __( 'Tap Start again to carry on where it stopped.', 'stubli' ) ); mv.inState = null; onUpdate(); } );
		};
		go();
	}
	function startMove( link, onUpdate ) {
		mv.busy = true; mv.msg = ''; onUpdate();
		return send( 'POST', 'move/in', { link: link } ).then( function ( r ) { mv.busy = false; mv.inState = r; onUpdate(); runMove( onUpdate ); return r; }, function ( e ) { mv.busy = false; mv.msg = e.message; onUpdate(); throw e; } );
	}
	function bindMove() {
		var mk = $( '[data-mv-make]' );
		if ( mk ) { mk.addEventListener( 'click', function () { mv.busy = true; redrawMove(); send( 'POST', 'move/link', {} ).then( function ( r ) { mv.busy = false; mv.link = r.link; mv.expires = r.expires; mv.out = r; redrawMove(); var f = $( '#a-mv-link' ); if ( f ) { f.focus(); f.select(); } }, function ( e ) { mv.busy = false; mv.msg = e.message; redrawMove(); } ); } ); }
		var cp = $( '[data-mv-copy]' );
		if ( cp ) { cp.addEventListener( 'click', function () { var f = $( '#a-mv-link' ); f.select(); ( navigator.clipboard ? navigator.clipboard.writeText( mv.link ) : Promise.reject() ).catch( function () { try { document.execCommand( 'copy' ); } catch ( x ) {} } ); mv.copied = true; redrawMove(); } ); }
		var sp = $( '[data-mv-stop]' );
		if ( sp ) { sp.addEventListener( 'click', function () { send( 'DELETE', 'move/link', null ).then( function ( r ) { mv.link = ''; mv.copied = false; mv.out = r; redrawMove(); } ); } ); }
		var fi = $( '[data-mv-in]' );
		if ( fi ) { fi.addEventListener( 'submit', function ( e ) { e.preventDefault(); var v = $( '#a-mv-in' ).value.trim(); if ( ! v ) { return; } startMove( v, redrawMove ).catch( function () {} ); } ); }
	}
	function redrawKeep() { swap( '[data-keep]', keepCard(), bindKeep ); }
	function packArchive() {
		if ( kp.packing ) { return; }
		kp.packing = true;
		var go = function () {
			send( 'POST', 'archive/step', {} ).then( function ( r ) {
				kp.ar = r; if ( location.hash === '#more' ) { redrawKeep(); }
				if ( r.state === 'packing' ) { return go(); }
				kp.packing = false;
			}, function ( e ) { kp.packing = false; kp.msg = e.message; kp.ar = { state: 'none' }; if ( location.hash === '#more' ) { redrawKeep(); } } );
		};
		go();
	}
	function bindKeep() {
		var start = function () {
			kp.busy = true; kp.msg = ''; redrawKeep();
			send( 'POST', 'archive', {} ).then( function ( r ) { kp.busy = false; kp.ar = r; redrawKeep(); packArchive(); }, function ( e ) { kp.busy = false; kp.msg = e.message; redrawKeep(); } );
		};
		var s = $( '[data-keep-start]' ); if ( s ) { s.addEventListener( 'click', start ); }
		var n = $( '[data-keep-new]' ); if ( n ) { n.addEventListener( 'click', start ); }
	}

	/* ── Your posts: grid on You, edit and delete ── */
	var mine = { loaded: false, items: [], page: 1, more: false, total: 0, missing: 0, toast: null };
	function loadMine( reset ) {
		if ( reset ) { mine.page = 1; }
		return send( 'GET', 'mine?page=' + mine.page, null ).then( function ( r ) {
			mine.items = reset ? r.items : mine.items.concat( r.items );
			mine.more = r.more; mine.total = r.total; mine.missing = r.missing; mine.loaded = true; if ( r.shapes ) { mine.shapes = r.shapes; }
		}, function () { mine.loaded = true; } );
	}
	function mineSection() {
		if ( ! mine.loaded ) { return ( '<section class="a-card" data-mine><h2>' + __( 'Your posts', 'stubli' ) + '</h2><div class="a-mine-grid" aria-hidden="true"><span></span><span></span><span></span></div></section>' ); }
		if ( ! mine.items.length ) { return '<div data-mine hidden></div>'; }
		var firstMissing = mine.items.filter( function ( i ) { return i.missing; } )[ 0 ];
		return ( '<section class="a-card" data-mine><h2>' + __( 'Your posts', 'stubli' ) + '</h2><p class="muted">' + __( 'Tap a photo to change its caption, collection or descriptions, or to delete it.', 'stubli' ) + '</p>' ) +
			( '' ) + // Descriptions are optional (23 Oct): no reminders.
			'<div class="a-mine-grid">' + mine.items.map( function ( i ) {
				var label = ( i.title || ( i.video ? __( 'Video post', 'stubli' ) : __( 'Photo post', 'stubli' ) ) ) + ( i.video && i.count === 1 ? __( ', video', 'stubli' ) : '' ) + ( i.count > 1 ? _f( __( ', %s photos', 'stubli' ), i.count ) : '' ) + ( i.friends ? __( ', friends only', 'stubli' ) : '' );
				return '<a href="#edit/' + i.id + '" aria-label="' + esc( _f( __( 'Edit: %s', 'stubli' ), label ) ) + '">' + ( i.thumb ? '<img src="' + esc( i.thumb ) + '" alt="" loading="lazy"' + ( i.note ? ' class="is-noted"' : '' ) + '>' : '' ) +
					( i.count > 1 ? '<span class="a-mine-n" aria-hidden="true">' + i.count + '</span>' : i.video ? '<span class="a-mine-n" aria-hidden="true">' + icon( 'play', 12 ) + '</span>' : '' ) + ( i.friends ? ( '<span class="a-mine-friends" aria-hidden="true">' + __( 'Friends only', 'stubli' ) + '</span>' ) : '' ) + '</a>';
			} ).join( '' ) + '</div>' +
			( mine.more ? ( '<button type="button" class="a-btn quiet wide" data-mine-more style="margin-top:12px">' + __( 'Show more', 'stubli' ) + '</button>' ) : '' ) + '</section>';
	}
	function bindMine() {
		var m = $( '[data-mine-more]' );
		if ( m ) { m.addEventListener( 'click', function () { m.disabled = true; m.textContent = __( 'Loading…', 'stubli' ); mine.page++; loadMine( false ).then( function () { swap( '[data-mine]', mineSection(), bindMine ); } ); } ); }
	}
	function toast( text, undo ) {
		var old = document.querySelector( '.a-toast' ); if ( old ) { old.remove(); }
		var t = document.createElement( 'div' ); t.className = 'a-toast'; t.setAttribute( 'role', 'status' );
		t.innerHTML = '<span>' + esc( text ) + '</span>' + ( undo ? ( '<button type="button" class="a-text-btn">' + __( 'Undo', 'stubli' ) + '</button>' ) : '' );
		document.body.appendChild( t );
		if ( undo ) { t.querySelector( 'button' ).addEventListener( 'click', function () { t.remove(); undo(); } ); }
		setTimeout( function () { if ( t.isConnected ) { t.remove(); } }, undo ? 10000 : 4000 );
	}

	var ed = null;
	function viewEdit( id ) {
		if ( ed && ed.id === id && ed.d ) { return renderEdit(); }
		ed = { id: id, d: null, busy: false, error: '', sure: false, newCol: '', adding: false };
		root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit post', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:200px"></span></div><p class="sr" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>' );
		send( 'GET', 'mine/' + id, null ).then( function ( d ) {
			if ( ! ed || ed.id !== id ) { return; }
			ed.d = d; ed.noteOn = !! d.note; ed.orig = d.photos.map( function ( ph ) { return ph.id; } ); ed.ratio = d.ratio || 'portrait'; renderEdit();
		}, function ( e ) {
			root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit post', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-notice" role="alert"><p>' ) + esc( e.message ) + ( '</p><a class="a-btn quiet" href="#you">' + __( 'Back to your posts', 'stubli' ) + '</a></div></main>' );
		} );
	}
	/* Who can see a post: Everyone or Friends (design Q44). */
	function audienceChips( on, attr ) {
		var friends = on === 'friends';
		return '<h2 class="a-label" id="a-aud-' + attr + ( '">' + __( 'Who can see it', 'stubli' ) ) + helpLink( 'friends-and-privacy/friends-only', __( 'who can see a post', 'stubli' ) ) + '</h2><div class="a-chips" role="radiogroup" aria-labelledby="a-aud-' + attr + '">' +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ! friends + '" data-' + attr + '="everyone">' + ( ! friends ? icon( 'check', 16 ) : '' ) + ( __( 'Everyone', 'stubli' ) + '</button>' ) +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + friends + '" data-' + attr + '="friends">' + ( friends ? icon( 'check', 16 ) : '' ) + ( __( 'Friends only', 'stubli' ) + '</button></div>' ) +
			'<p class="a-help">' + ( friends ? ( B.friends ? ( B.friends === 1 ? __( 'Only the friend you chose sees it, in their own Stubli. It isn\'t on your public page.', 'stubli' ) : _f( __( 'Only the %s friends you chose see it, in their own Stubli. It isn\'t on your public page.', 'stubli' ), B.friends ) ) : __( 'You haven\'t chosen any friends yet. Tick them under You → Settings and more → Your people; until then, nobody else sees this post.', 'stubli' ) ) : __( 'On your page and in your followers\' feeds.', 'stubli' ) ) + '</p>';
	}
	function renderEdit() {
		var d = ed.d, cols = ( B.collections || [] ).slice();
		if ( d.collection && cols.indexOf( d.collection ) < 0 ) { cols.push( d.collection ); }
		var missing = d.photos.filter( function ( p ) { return ! p.alt.trim(); } ).length;
		root.innerHTML = ( '<header class="a-head"><a class="a-text-btn" href="#you">' + __( 'Close', 'stubli' ) + '</a><h1>' + __( 'Edit post', 'stubli' ) + '</h1><button type="button" class="a-btn" data-ed-save' ) + ( ed.busy ? ' disabled' : '' ) + '>' + ( ed.busy ? __( 'Saving…', 'stubli' ) : __( 'Save', 'stubli' ) ) + '</button></header>' +
			'<main class="a-compose" id="main">' +
			( '<h2 class="a-label" id="a-ed-desc">' + ( d.photos.length > 1 ? __( 'Photos', 'stubli' ) : __( 'Photo', 'stubli' ) ) + '</h2>' ) +
			( d.photos.length > 1 ? '<p class="a-help" style="margin:-4px 0 10px">' + __( 'The first photo is the cover: it shows on your page and in your followers\' feeds. Change the order with the arrows. Descriptions are optional.', 'stubli' ) + '</p>' : '' ) +
			d.photos.map( function ( ph, i ) {
				var n = d.photos.length, kind = ph.video ? 'video' : 'photo';
				return '<div class="a-alt a-ed-ph"><span class="a-ed-thumb"><img src="' + esc( ph.nu ? ph.thumb : ph.src ) + '" alt="">' + ( n > 1 && i === 0 ? '<span class="a-ed-cover">' + __( 'Cover', 'stubli' ) + '</span>' : ( ph.nu ? '<span class="a-ed-cover a-ed-new">' + _x( 'New', 'photo', 'stubli' ) + '</span>' : '' ) ) + '</span>' +
					'<span class="a-ed-ph-main"><label class="sr" for="a-ed-alt-' + i + '">' + _f( __( 'Description of photo %s', 'stubli' ), i + 1 ) + '</label><input id="a-ed-alt-' + i + '" class="a-field" data-ed-alt="' + i + '" maxlength="300" placeholder="' + esc( ph.video ? _f( __( 'Video %s: what happens in it?', 'stubli' ), i + 1 ) : _f( __( 'Photo %s: what\'s in it?', 'stubli' ), i + 1 ) ) + '" value="' + esc( ph.alt ) + '"></span>' +
					'<span class="a-ed-ph-tools"><span class="a-ed-ph-acts">' +
						( n > 1 && i > 0 ? '<button type="button" class="a-text-btn" data-ed-cover="' + i + '">' + __( 'Make cover', 'stubli' ) + '</button>' : '' ) +
						'<label class="a-text-btn">' + __( 'Replace', 'stubli' ) + '<input class="a-file" type="file" accept="' + pickAccept() + '" data-ed-rep="' + i + '" aria-label="' + esc( kind === 'video' ? _f( __( 'Replace video %s', 'stubli' ), i + 1 ) : _f( __( 'Replace photo %s', 'stubli' ), i + 1 ) ) + '"></label>' +
						( n > 1 ? '<button type="button" class="a-text-btn" data-ed-rm="' + i + '" aria-label="' + esc( kind === 'video' ? _f( __( 'Remove video %s', 'stubli' ), i + 1 ) : _f( __( 'Remove photo %s', 'stubli' ), i + 1 ) ) + '">' + __( 'Remove', 'stubli' ) + '</button>' : '' ) +
					'</span>' +
					( n > 1 ? '<button type="button" class="a-icon-btn" data-ed-mv="' + i + '" data-dir="-1"' + ( i === 0 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move photo %s earlier', 'stubli' ), i + 1 ) ) + '">' + icon( 'up', 18 ) + '</button>' +
						'<button type="button" class="a-icon-btn" data-ed-mv="' + i + '" data-dir="1"' + ( i === n - 1 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move photo %s later', 'stubli' ), i + 1 ) ) + '">' + icon( 'down', 18 ) + '</button>' : '' ) +
					'</span></div>';
			} ).join( '' ) +
			( d.photos.length < B.maxPhotos ? '<label class="a-btn quiet a-ed-add">' + icon( 'plus', 18 ) + ( ( B.videoTypes || [] ).length ? __( 'Add photos or videos', 'stubli' ) : __( 'Add photos', 'stubli' ) ) + '<input class="a-file" type="file" accept="' + pickAccept() + '" multiple data-ed-add></label>' : '' ) +
			( ed.loading ? '<p class="muted" role="status">' + __( 'Opening your video…', 'stubli' ) + '</p>' : '' ) +
			( d.photos.some( function ( ph ) { return ph.nu && ! ph.video; } ) ? '<h2 class="a-label" id="a-ed-shape">' + __( 'Shape for new photos', 'stubli' ) + '</h2><div class="a-ratios" role="radiogroup" aria-labelledby="a-ed-shape">' + Object.keys( RATIOS ).map( function ( k ) {
				var lab = { portrait: __( 'Portrait', 'stubli' ), square: __( 'Square', 'stubli' ), landscape: __( 'Landscape', 'stubli' ) };
				return '<button type="button" class="a-chip" role="radio" data-ed-ratio="' + k + '" aria-checked="' + ( ed.ratio === k ) + '">' + icon( k, 16 ) + lab[ k ] + '</button>';
			} ).join( '' ) + '</div><p class="a-help">' + __( 'New photos are cropped to this shape, from the middle. The photos already in the post stay as they are.', 'stubli' ) + '</p>' : '' ) +
			( ed.orig.some( function ( id ) { return ! d.photos.some( function ( ph ) { return ph.id === id; } ); } ) ? '<p class="a-help">' + __( 'Photos you take out are deleted from your site when you tap Save. You can undo straight after.', 'stubli' ) + '</p>' : '' ) +
			( ed.photoMsg ? '<div class="a-notice calm" role="alert"><p>' + esc( ed.photoMsg ) + '</p></div>' : '' ) +
			( '<label class="a-label" for="a-ed-cap">' + __( 'Caption', 'stubli' ) + '</label><textarea id="a-ed-cap" class="a-textarea" maxlength="' ) + B.maxCaption + '">' + esc( d.caption ) + '</textarea>' +
			'<p class="a-count" data-ed-count' + ( d.caption.length < B.maxCaption - 200 ? ' hidden' : '' ) + '>' + d.caption.length + ' / ' + B.maxCaption + '</p>' +
			( '<h2 class="a-label" id="a-ed-col">' + __( 'Collection', 'stubli' ) + '</h2><div class="a-chips" role="radiogroup" aria-labelledby="a-ed-col">' ) +
			cols.map( function ( c ) { var on = ! ed.adding && d.collection === c; return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-ed-col="' + esc( c ) + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( c ) + '</button>'; } ).join( '' ) +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ( ! ed.adding && ! d.collection ) + '" data-ed-col="">' + ( ! ed.adding && ! d.collection ? icon( 'check', 16 ) : '' ) + ( __( 'None', 'stubli' ) + '</button>' ) +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ed.adding + '" data-ed-col-new>' + ( ed.adding ? icon( 'check', 16 ) : '' ) + ( __( '+ New', 'stubli' ) + '</button></div>' ) +
			( ed.adding ? ( '<div class="a-inline"><label class="sr" for="a-ed-newcol">' + __( 'New collection name', 'stubli' ) + '</label><input id="a-ed-newcol" class="a-field" maxlength="40" placeholder="' + esc( __( 'e.g. Mountains', 'stubli' ) ) + '" value="' ) + esc( ed.newCol ) + '"></div>' : '' ) +
			audienceChips( d.audience, 'ed-aud' ) +
			( '<label class="a-check"><span><strong>' + __( 'Content note', 'stubli' ) + '</strong><span class="muted small">' + __( 'The photo stays softly blurred until someone chooses to see it.', 'stubli' ) + '</span></span><input type="checkbox" data-ed-note' ) + ( ed.noteOn ? ' checked' : '' ) + '></label>' +
			( ed.noteOn ? ( '<div class="a-inline"><label class="sr" for="a-ed-notetext">' + __( 'Content note', 'stubli' ) + '</label><input id="a-ed-notetext" class="a-field" maxlength="' ) + B.maxNote + '" placeholder="e.g. Flashing lights" value="' + esc( d.note ) + '"></div>' : '' ) +
			( ed.error ? '<div class="a-notice" role="alert"><p>' + esc( ed.error ) + '</p></div>' : '' ) +
			( '<p class="a-help" style="margin-top:16px">' + __( 'Changes reach your followers\' feeds the next time their Stubli checks for news, usually within 15 minutes.', 'stubli' ) + '</p>' ) +
			'<p style="margin:16px 0"><a href="' + esc( d.url ) + ( '">' + __( 'See the post', 'stubli' ) + '</a></p>' ) +
			'<section class="a-ed-delete">' + ( ed.sure ?
				( '<p><strong>' + __( 'Delete this post?', 'stubli' ) + '</strong> ' + __( 'It leaves your page, and your followers\' feeds the next time they check. You can undo right after.', 'stubli' ) + '</p><div class="a-actions"><button type="button" class="a-btn a-danger" data-ed-del-yes>' + __( 'Delete post', 'stubli' ) + '</button><button type="button" class="a-btn quiet" data-ed-del-no>' + __( 'Keep it', 'stubli' ) + '</button></div>' ) :
				( '<button type="button" class="a-text-btn a-danger-text" data-ed-del>' + __( 'Delete post…', 'stubli' ) + '</button>' ) ) + '</section>' +
			'</main>';
		bindEdit();
	}
	function bindEdit() {
		var d = ed.d;
		$$( '[data-ed-alt]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { d.photos[ +inp.dataset.edAlt ].alt = inp.value; } ); } );
		$$( '[data-ed-mv]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var i = +b.dataset.edMv, j = i + ( +b.dataset.dir ); var t = d.photos[ i ]; d.photos[ i ] = d.photos[ j ]; d.photos[ j ] = t; ed.moved = true; renderEdit(); var f = $( '[data-ed-mv="' + j + '"][data-dir="' + b.dataset.dir + '"]' ) || $( '[data-ed-mv="' + j + '"]' ); if ( f && ! f.disabled ) { f.focus(); } } ); } );
		var edFiles = function ( list, at ) {
			var files = Array.prototype.slice.call( list || [] ), room = B.maxPhotos - d.photos.length + ( at == null ? 0 : 1 );
			ed.photoMsg = '';
			if ( files.length > room ) { ed.photoMsg = _f( __( 'A post holds up to %s photos, so only the first %s were added.', 'stubli' ), B.maxPhotos, room ); files = files.slice( 0, room ); }
			prepFiles( files, function () { ed.loading = true; renderEdit(); } ).then( function ( r ) {
				ed.loading = false;
				r.got.forEach( function ( g ) { g.nu = true; } );
				if ( at == null ) { Array.prototype.push.apply( d.photos, r.got ); } else if ( r.got.length ) { d.photos.splice( at, 1, r.got[ 0 ] ); }
				if ( r.notes.length ) { ed.photoMsg = ( ed.photoMsg ? ed.photoMsg + ' ' : '' ) + r.notes.join( ' ' ); }
				renderEdit();
				if ( r.got.length ) { toast( at == null ? __( 'Added. Tap Save to keep it.', 'stubli' ) : __( 'Replaced. Tap Save to keep it.', 'stubli' ) ); }
			} );
		};
		$$( '[data-ed-rep]' ).forEach( function ( inp ) { inp.addEventListener( 'change', function () { edFiles( inp.files, +inp.dataset.edRep ); } ); } );
		var add = $( '[data-ed-add]' ); if ( add ) { add.addEventListener( 'change', function () { edFiles( add.files, null ); } ); }
		$$( '[data-ed-rm]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var i = +b.dataset.edRm; d.photos.splice( i, 1 ); renderEdit(); var f = $( '[data-ed-alt="' + Math.min( i, d.photos.length - 1 ) + '"]' ); if ( f ) { f.focus(); } toast( __( 'Taken out. Tap Save to keep the change.', 'stubli' ) ); } ); } );
		$$( '[data-ed-ratio]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { ed.ratio = b.dataset.edRatio; renderEdit(); $( '[data-ed-ratio="' + ed.ratio + '"]' ).focus(); } ); } );
		$$( '[data-ed-cover]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var i = +b.dataset.edCover; d.photos.unshift( d.photos.splice( i, 1 )[ 0 ] ); ed.moved = true; renderEdit(); toast( __( 'That\'s the cover now. Tap Save to keep it.', 'stubli' ) ); } ); } );
		var cap = $( '#a-ed-cap' ), cnt = $( '[data-ed-count]' );
		cap.addEventListener( 'input', function () { d.caption = cap.value; cnt.textContent = cap.value.length + ' / ' + B.maxCaption; cnt.hidden = cap.value.length < B.maxCaption - 200; } );
		$$( '[data-ed-col]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.collection = b.dataset.edCol; ed.adding = false; renderEdit(); } ); } );
		$( '[data-ed-col-new]' ).addEventListener( 'click', function () { ed.adding = true; renderEdit(); $( '#a-ed-newcol' ).focus(); } );
		var nc = $( '#a-ed-newcol' ); if ( nc ) { nc.addEventListener( 'input', function () { ed.newCol = nc.value; } ); }
		$$( '[data-ed-aud]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.audience = b.dataset.edAud; renderEdit(); $( '[data-ed-aud="' + d.audience + '"]' ).focus(); } ); } );
		$( '[data-ed-note]' ).addEventListener( 'change', function ( e ) { ed.noteOn = e.target.checked; renderEdit(); if ( ed.noteOn ) { $( '#a-ed-notetext' ).focus(); } } );
		var nt = $( '#a-ed-notetext' ); if ( nt ) { nt.addEventListener( 'input', function () { d.note = nt.value; } ); }
		$( '[data-ed-save]' ).addEventListener( 'click', function () {
			var alts = {}; d.photos.forEach( function ( p ) { if ( ! p.nu ) { alts[ p.id ] = p.alt; } } );
			var col = ed.adding ? ed.newCol.trim() : d.collection;
			if ( ed.adding && ! col ) { ed.error = __( 'Give the new collection a name, or choose one above.', 'stubli' ); renderEdit(); return; }
			if ( ed.noteOn && ! d.note.trim() ) { ed.error = __( 'Write a short content note, like "Flashing lights", or turn it off.', 'stubli' ); renderEdit(); return; }
			ed.busy = true; ed.error = ''; renderEdit();
			var news = d.photos.filter( function ( ph ) { return ph.nu; } ), orig = ed.orig.slice(), id = ed.id;
			var body = { caption: d.caption, collection: col, note: ed.noteOn ? d.note : '', alts: alts, audience: d.audience || 'everyone', order: d.photos.map( function ( ph ) { return ph.nu ? 'new' + news.indexOf( ph ) : ph.id; } ) };
			var total = 0;
			var req = ! news.length ? send( 'POST', 'mine/' + id, body ) : Promise.all( news.map( function ( ph ) {
				return ( ph.video ? stripLocation( ph.file ) : cropped( ph.file, ed.ratio ) ).then( function ( b ) { total += b.size + ( ph.video ? ph.poster.size : 0 ); return b; } );
			} ) ).then( function ( blobs ) {
				if ( B.maxPost && total > B.maxPost ) { return Promise.reject( { message: _f( __( 'Together these are %s, and your host accepts up to %s at once. Add the videos one at a time, or choose a shorter clip.', 'stubli' ), fmtMB( total ), fmtMB( B.maxPost ) ) } ); }
				var fd = new FormData();
				blobs.forEach( function ( b, i ) {
					var ph = news[ i ];
					if ( ph.video ) {
						fd.append( 'photos[]', b, 'video-' + ( i + 1 ) + ( /quicktime/.test( b.type ) || /\.mov$/i.test( ph.file.name ) ? '.mov' : '.mp4' ) );
						fd.append( 'posters[' + i + ']', ph.poster, 'video-' + ( i + 1 ) + '-cover.jpg' );
					} else {
						fd.append( 'photos[]', b, 'photo-' + ( i + 1 ) + '.jpg' );
					}
					fd.append( 'new_alts[]', ( ph.alt || '' ).trim() );
				} );
				fd.append( 'data', JSON.stringify( body ) );
				return send( 'POST', 'mine/' + id, fd );
			} );
			req.then( function ( r ) {
				if ( col && ( B.collections || [] ).indexOf( col ) < 0 ) { ( B.collections = B.collections || [] ).push( col ); }
				var now = ( r && r.photos || [] ).map( function ( ph ) { return ph.id; } );
				var gone = orig.some( function ( x ) { return now.indexOf( x ) < 0; } );
				ed = null; mine.loaded = false; location.hash = '#you';
				// Photos taken out stay on the site for a few minutes, so Undo can put them back.
				toast( __( 'Saved.', 'stubli' ), gone ? function () {
					send( 'POST', 'mine/' + id, { order: orig } ).then( function () { mine.loaded = false; if ( location.hash === '#you' ) { route(); } toast( __( 'The photos are back as they were.', 'stubli' ) ); }, function ( e ) { toast( e.message ); } );
				} : null );
			}, function ( e ) { ed.busy = false; ed.error = e.message; renderEdit(); } );
		} );
		var del = $( '[data-ed-del]' ); if ( del ) { del.addEventListener( 'click', function () { ed.sure = true; renderEdit(); $( '[data-ed-del-no]' ).focus(); } ); }
		var no = $( '[data-ed-del-no]' ); if ( no ) { no.addEventListener( 'click', function () { ed.sure = false; renderEdit(); $( '[data-ed-del]' ).focus(); } ); }
		var yes = $( '[data-ed-del-yes]' );
		if ( yes ) {
			yes.addEventListener( 'click', function () {
				var id = ed.id; yes.disabled = true; yes.textContent = __( 'Deleting…', 'stubli' );
				send( 'DELETE', 'mine/' + id, null ).then( function () {
					ed = null; mine.loaded = false; feed.loaded = false; location.hash = '#you';
					toast( __( 'Post deleted.', 'stubli' ), function () {
						send( 'POST', 'mine/' + id + '/restore', {} ).then( function () { mine.loaded = false; if ( location.hash === '#you' ) { viewYou(); } toast( __( 'It\'s back on your page.', 'stubli' ) ); }, function ( e ) { toast( e.message ); } );
					} );
				}, function ( e ) { ed.error = e.message; ed.sure = false; renderEdit(); } );
			} );
		}
	}

	/* ── Daily summary: phone notifications (installed app) with email as the fallback ── */
	var ds = { loaded: false, info: null, on: false, busy: false, msg: '' };
	function pushSupported() { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; }
	function iosNeedsInstall() { return /iphone|ipad|ipod/i.test( navigator.userAgent ) && ! isStandalone(); }
	function keyBytes( b64 ) { var s = atob( b64.replace( /-/g, '+' ).replace( /_/g, '/' ) + '==='.slice( ( b64.length + 3 ) % 4 ) ), u = new Uint8Array( s.length ); for ( var i = 0; i < s.length; i++ ) { u[ i ] = s.charCodeAt( i ); } return u; }
	function loadSummary() {
		var subP = pushSupported() ? navigator.serviceWorker.getRegistration( B.swScope ).then( function ( r ) { return r ? r.pushManager.getSubscription() : null; } ).catch( function () { return null; } ) : Promise.resolve( null );
		return Promise.all( [ send( 'GET', 'push', null ), subP ] ).then( function ( r ) { ds.info = r[ 0 ]; ds.on = !! r[ 1 ]; ds.loaded = true; }, function () { ds.loaded = true; } );
	}
	function enablePush() {
		if ( ! pushSupported() ) { return Promise.reject( { message: __( 'This browser can\'t show notifications.', 'stubli' ) } ); }
		return Notification.requestPermission().then( function ( p ) {
			if ( p !== 'granted' ) { return Promise.reject( { message: __( 'Notifications are turned off for Stubli. You can allow them in your phone\'s settings, or use email below.', 'stubli' ) } ); }
			return navigator.serviceWorker.register( B.sw, { scope: B.swScope } );
		} ).then( function () { return navigator.serviceWorker.ready; } ).then( function ( reg ) {
			return ( ds.info && ds.info.key ? Promise.resolve( ds.info ) : send( 'GET', 'push', null ) ).then( function ( info ) {
				ds.info = info;
				return reg.pushManager.subscribe( { userVisibleOnly: true, applicationServerKey: keyBytes( info.key ) } );
			} );
		} ).then( function ( sub ) {
			var j = sub.toJSON();
			store.set( 'stubli-push-endpoint', j.endpoint );
			return send( 'POST', 'push', { endpoint: j.endpoint, keys: j.keys, device: device() } );
		} ).then( function () { ds.on = true; } );
	}
	function disablePush() {
		var known = store.get( 'stubli-push-endpoint' );
		return navigator.serviceWorker.getRegistration( B.swScope ).then( function ( r ) { return r ? r.pushManager.getSubscription() : null; } ).catch( function () { return null; } ).then( function ( sub ) {
			var ep = sub ? sub.endpoint : known;
			return ( sub ? sub.unsubscribe() : Promise.resolve() ).then( function () { return ep ? send( 'DELETE', 'push?endpoint=' + encodeURIComponent( ep ), null ) : null; } );
		} ).then( function () { store.del( 'stubli-push-endpoint' ); ds.on = false; } );
	}
	function timeOptions( cur ) {
		var t = [], h; for ( h = 6; h <= 22; h++ ) { t.push( ( h < 10 ? '0' : '' ) + h + ':00' ); }
		if ( cur && t.indexOf( cur ) < 0 ) { t.push( cur ); t.sort(); }
		return t.map( function ( x ) { return '<option' + ( x === cur ? ' selected' : '' ) + '>' + x + '</option>'; } ).join( '' );
	}
	function summaryCard() {
		if ( ! ds.loaded || ! ds.info ) { return ( '<section class="a-card" data-summary><h2>' + __( 'Daily summary', 'stubli' ) + '</h2><p class="muted">' + __( 'Loading…', 'stubli' ) + '</p></section>' ); }
		var i = ds.info, phone;
		if ( iosNeedsInstall() ) {
			phone = ( '<p class="a-help">' + __( 'On iPhone, add Stubli to your Home Screen first (Share, then Add to Home Screen). Open it from there to turn this on.', 'stubli' ) + '</p>' );
		} else if ( ! pushSupported() ) {
			phone = ( '<p class="a-help">' + __( 'This browser can\'t show notifications. Email below works everywhere.', 'stubli' ) + '</p>' );
		} else {
			phone = '<p class="a-ds-status">' + icon( ds.on ? 'check' : 'news', 16 ) + ( ds.on ? __( 'On for this phone', 'stubli' ) : __( 'Off for this phone', 'stubli' ) ) + '</p><div class="a-actions">' +
				( ds.on ? '<button type="button" class="a-btn quiet" data-ds-test' + ( ds.busy ? ' disabled' : '' ) + ( '>' + __( 'Send a test', 'stubli' ) + '</button><button type="button" class="a-text-btn" data-ds-off' ) + ( ds.busy ? ' disabled' : '' ) + ( '>' + __( 'Turn off', 'stubli' ) + '</button>' ) :
					'<button type="button" class="a-btn" data-ds-on' + ( ds.busy ? ' disabled' : '' ) + '>' + ( ds.busy ? __( 'Asking your phone…', 'stubli' ) : __( 'Send it to this phone', 'stubli' ) ) + '</button>' ) + '</div>';
		}
		return ( '<section class="a-card" data-summary><h2>' + __( 'Daily summary', 'stubli' ) ) + helpLink( 'reading-and-following/daily-summary', __( 'the daily summary', 'stubli' ) ) + ( '</h2><p class="muted">' + __( 'At most one a day, and only when something is new: comments, guest book entries and new posts from your people. Never one per post.', 'stubli' ) + '</p>' ) +
			( '<label class="a-label" for="a-ds-time" style="margin-top:4px">' + __( 'Time', 'stubli' ) + '</label><select id="a-ds-time" class="a-field a-su-select" data-ds-time>' ) + timeOptions( i.time ) + '</select>' +
			( '<p class="a-label">' + __( 'On your phone', 'stubli' ) + '</p>' ) + phone +
			( '<label class="a-check"><span><strong>' + __( 'Also by email', 'stubli' ) + '</strong><span class="muted small">' + _f( __( 'To %s. Handy if notifications don\'t reach you.', 'stubli' ), esc( i.address ) ) ) + '</span></span><input type="checkbox" data-ds-email' + ( i.email ? ' checked' : '' ) + '></label>' +
			'<p class="a-help" aria-live="polite" data-ds-msg>' + esc( ds.msg ) + '</p></section>';
	}
	function bindSummary( rerender ) {
		var m = function ( t ) { ds.msg = t; var e = $( '[data-ds-msg]' ); if ( e ) { e.textContent = t; } };
		var tm = $( '[data-ds-time]' ); if ( tm ) { tm.addEventListener( 'change', function () { send( 'POST', 'settings', { digest_time: tm.value } ).then( function () { ds.info.time = tm.value; m( _f( __( 'Saved. It arrives around %s when something is new.', 'stubli' ), tm.value ) ); }, function ( e ) { m( e.message ); } ); } ); }
		var em = $( '[data-ds-email]' ); if ( em ) { em.addEventListener( 'change', function () { send( 'POST', 'settings', { digest_email: em.checked } ).then( function () { ds.info.email = em.checked; m( em.checked ? __( 'Email is on.', 'stubli' ) : __( 'Email is off.', 'stubli' ) ); }, function ( e ) { em.checked = ! em.checked; m( e.message ); } ); } ); }
		var on = $( '[data-ds-on]' ); if ( on ) { on.addEventListener( 'click', function () { ds.busy = true; ds.msg = ''; rerender(); enablePush().then( function () { ds.busy = false; ds.msg = __( 'On. Your summary arrives on this phone once a day, when something is new.', 'stubli' ); rerender(); }, function ( e ) { ds.busy = false; ds.msg = ( e && e.message ) || __( 'That didn\'t work. Try again.', 'stubli' ); rerender(); } ); } ); }
		var off = $( '[data-ds-off]' ); if ( off ) { off.addEventListener( 'click', function () { ds.busy = true; rerender(); disablePush().then( function () { ds.busy = false; ds.msg = __( 'Off for this phone.', 'stubli' ); rerender(); }, function () { ds.busy = false; rerender(); } ); } ); }
		var te = $( '[data-ds-test]' ); if ( te ) { te.addEventListener( 'click', function () { te.disabled = true; send( 'POST', 'push/test', {} ).then( function ( r ) { te.disabled = false; m( r.sent ? __( 'Sent. It should appear in a moment.', 'stubli' ) : __( 'Your phone\'s notification service didn\'t accept it. Turn it off and on again.', 'stubli' ) ); }, function ( e ) { te.disabled = false; m( e.message ); } ); } ); }
	}

	// Redraw one section in place, so whatever else is on screen (an invite link just made) stays.
	function swap( sel, html, bind ) {
		var el = $( sel ); if ( ! el ) { return; }
		var t = document.createElement( 'div' ); t.innerHTML = html;
		var fresh = t.firstElementChild || document.createElement( 'div' );
		el.parentNode.replaceChild( fresh, el ); if ( bind ) { bind(); }
	}
	function redrawSummary() { swap( '[data-summary]', summaryCard(), function () { bindSummary( redrawSummary ); } ); }
	function followForm( where ) {
		return '<form class="a-inline" data-follow="' + where + '"><label class="sr" for="a-follow-' + where + ( '">' + __( 'Their Stubli address', 'stubli' ) + '</label><input id="a-follow-' ) + where + ( '" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="theirname.com"><button type="submit" class="a-btn quiet">' + __( 'Follow', 'stubli' ) + '</button></form><div class="a-help" data-follow-msg aria-live="polite"></div>' );
	}
	function bindFollow() {
		$$( '[data-follow]' ).forEach( function ( f ) {
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var inp = f.querySelector( 'input' ), m = f.nextElementSibling, btn = f.querySelector( 'button' ), v = inp.value.trim();
				if ( ! v ) { inp.focus(); return; }
				if ( ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				btn.disabled = true; btn.textContent = __( 'Following…', 'stubli' ); m.textContent = '';
				send( 'POST', 'follows', { url: v } ).then( function ( r ) { pp.loaded = false;
					m.textContent = _f( __( 'You\'re following %s. Their photos appear in Home.', 'stubli' ), r && r.name ? r.name : v );
					inp.value = ''; btn.disabled = false; btn.textContent = __( 'Follow', 'stubli' ); feed.loaded = false;
					if ( location.hash === '#setup' && su.loaded ) { send( 'GET', 'setup', null ).then( function ( d ) { su.d.follows = d.follows; renderSetup(); var mm = $( '[data-follow-msg]' ); if ( mm ) { mm.textContent = ( __( 'You\'re following', 'stubli' ) + ' ' ) + ( r && r.name ? r.name : v ) + '.'; } }, function () {} ); }
				}, function ( err ) {
					m.textContent = ( err && err.message ) || __( 'That didn\'t work. Check the address and try again.', 'stubli' );
					btn.disabled = false; btn.textContent = __( 'Follow', 'stubli' );
				} );
			} );
		} );
	}

	/* ── Home: the feed ── */
	var feed = { items: [], page: 1, more: true, list: '', lists: [], loaded: false, loading: false, seen: +( store.get( 'stubli-seen' ) || 0 ), newSince: 0, open: {} };
	function gentle( iso ) {
		var t = Date.parse( iso ), d = Math.floor( ( Date.now() - t ) / 86400000 );
		if ( ! t ) { return ''; }
		if ( d < 1 ) { var h = Math.floor( ( Date.now() - t ) / 3600000 ); return h < 1 ? __( 'just now', 'stubli' ) : _f( __( '%s h ago', 'stubli' ), h ); }
		if ( d === 1 ) { return __( 'yesterday', 'stubli' ); }
		if ( d < 14 ) { return _f( __( '%s days ago', 'stubli' ), d ); }
		return new Date( t ).toLocaleDateString( undefined, { day: 'numeric', month: 'short', year: d > 300 ? 'numeric' : undefined } );
	}
	function loadFeed( reset ) {
		if ( feed.loading ) { return Promise.resolve(); }
		feed.loading = true;
		if ( reset ) { feed.page = 1; feed.items = []; feed.more = true; }
		var first = feed.page === 1 && ! feed.list;
		return send( 'GET', 'feed?page=' + feed.page + ( feed.list ? '&list=' + encodeURIComponent( feed.list ) : '' ), null ).then( function ( rows ) {
			rows = Array.isArray( rows ) ? rows : [];
			feed.items = feed.items.concat( rows ); feed.more = rows.length >= 20; feed.page++; feed.loaded = true; feed.loading = false; feed.error = ''; feed.stale = false;
			if ( first ) { store.set( 'stubli-feed-cache', JSON.stringify( rows.slice( 0, 20 ) ) ); }
		}, function ( e ) {
			feed.loading = false; feed.loaded = true;
			var cached = null;
			if ( first ) { try { cached = JSON.parse( store.get( 'stubli-feed-cache' ) || 'null' ); } catch ( x ) { cached = null; } }
			if ( Array.isArray( cached ) && cached.length && ! feed.items.length ) {
				// Show what we had last time, with a quiet note.
				feed.items = cached; feed.more = false; feed.stale = true; feed.error = '';
			} else {
				feed.error = ( e && e.code === 'offline' ) ? __( 'No connection right now. Your feed comes back when you\'re online.', 'stubli' ) : __( 'Your feed didn\'t load this time.', 'stubli' );
			}
		} );
	}
	function viewHome() {
		if ( feed.error || feed.stale ) { feed.loaded = false; feed.error = ''; }
		var first = ! feed.loaded;
		renderHome();
		if ( first ) {
			Promise.all( [
				send( 'GET', 'follows', null ).then( function ( fl ) {
					feed.follows = Array.isArray( fl ) ? fl : [];
					var names = {};
					feed.follows.forEach( function ( f ) { if ( f.list_name && ! +f.muted ) { names[ f.list_name ] = 1; } } );
					feed.lists = Object.keys( names ).sort();
				}, function () { feed.follows = []; } ),
				loadFeed( true )
			] ).then( function () {
				feed.newSince = feed.items.filter( function ( i ) { return Date.parse( i.published ) > feed.seen; } ).length;
				feed.seenBefore = feed.seen;
				feed.seen = Date.now(); store.set( 'stubli-seen', String( feed.seen ) );
				if ( location.hash === '' || location.hash === '#home' ) { renderHome(); }
				// Ask the site to fetch anything brand new, then refresh quietly.
				send( 'POST', 'sync', {} ).then( function ( r ) {
					if ( r && r.new_items > 0 ) { loadFeed( true ).then( function () { if ( location.hash === '' || location.hash === '#home' ) { renderHome(); } } ); }
				}, function () {} );
			} );
		}
	}
	function retryFeed() {
		feed.error = ''; feed.loaded = false; feed.stale = false; renderHome();
		loadFeed( true ).then( function () { if ( location.hash === '' || location.hash === '#home' ) { renderHome(); } } );
	}
	window.addEventListener( 'online', function () { if ( ( feed.error || feed.stale ) && ( location.hash === '' || location.hash === '#home' ) ) { retryFeed(); } } );
	/* ── Your first day: three small steps, then it goes away ── */
	var fd = { how: false };
	if ( isStandalone() ) { store.set( 'stubli-installed', '1' ); }
	function fdSteps() {
		var f = B.firstDay || {};
		return [
			{ k: 'photo', done: !! f.photo, t: __( 'Post your first photo', 'stubli' ), s: __( 'It goes on your page and to your followers.', 'stubli' ), go: '<a class="a-btn a-fd-go" href="#new">' + __( 'Post', 'stubli' ) + '</a>' },
			{ k: 'invite', done: !! f.invited, t: __( 'Invite one friend', 'stubli' ), s: __( 'You follow each other once they join.', 'stubli' ), go: '<a class="a-btn a-fd-go" href="#discover">' + __( 'Invite', 'stubli' ) + '</a>' },
			{ k: 'home', done: isStandalone() || !! store.get( 'stubli-installed' ), t: __( 'Put Stubli on your home screen', 'stubli' ), s: __( 'Opens like an app, one tap away.', 'stubli' ), go: '<button type="button" class="a-btn a-fd-go" data-fd-how aria-expanded="' + fd.how + '">' + __( 'Show me', 'stubli' ) + '</button>' }
		];
	}
	function firstDayCard() {
		if ( ! B.firstDay ) { return ''; }
		var steps = fdSteps(), n = steps.filter( function ( x ) { return x.done; } ).length, ios = /iphone|ipad|ipod/i.test( navigator.userAgent );
		if ( n === steps.length ) {
			return '<section class="a-card a-fd a-fd-done" aria-live="polite"><p><strong>' + __( 'You\'re all set.', 'stubli' ) + '</strong> ' + __( 'Enjoy your Stubli.', 'stubli' ) + '</p><button type="button" class="a-text-btn" data-fd-hide>' + __( 'Close', 'stubli' ) + '</button></section>';
		}
		return '<section class="a-card a-fd" aria-labelledby="a-fd-h"><div class="a-fd-head"><h2 id="a-fd-h">' + __( 'Your first day', 'stubli' ) + '</h2><span class="muted">' + _f( __( '%s of %s', 'stubli' ), n, steps.length ) + '</span></div>' +
			'<div class="a-fd-bar" aria-hidden="true"><span style="width:' + Math.round( 100 * n / steps.length ) + '%"></span></div><ul class="a-fd-list">' + steps.map( function ( x ) {
				return '<li class="' + ( x.done ? 'is-done' : '' ) + '"><span class="a-fd-c" aria-hidden="true">' + ( x.done ? icon( 'check', 14 ) : '' ) + '</span><span class="a-fd-t"><span>' + esc( x.t ) + ( x.done ? '<span class="sr"> ' + __( '(done)', 'stubli' ) + '</span>' : '' ) + '</span>' + ( x.done ? '' : '<small>' + esc( x.s ) + '</small>' ) + '</span>' + ( x.done ? '' : x.go ) + '</li>' +
					( x.k === 'home' && fd.how && ! x.done ? '<li class="a-fd-how"><p>' + ( ios ? _f( __( 'In Safari, tap the Share button, then %s.', 'stubli' ), '<strong>' + __( 'Add to Home Screen', 'stubli' ) + '</strong>' ) : _f( __( 'Open your browser\'s menu, then choose %s.', 'stubli' ), '<strong>' + __( 'Add to Home screen', 'stubli' ) + '</strong>' ) ) + '</p><button type="button" class="a-text-btn" data-fd-did>' + __( 'I\'ve done it', 'stubli' ) + '</button></li>' : '' );
			} ).join( '' ) + '</ul><button type="button" class="a-text-btn a-fd-hide" data-fd-hide>' + __( 'Hide this', 'stubli' ) + '</button></section>';
	}
	function bindFirstDay() {
		var how = $( '[data-fd-how]' ); if ( how ) { how.addEventListener( 'click', function () { fd.how = ! fd.how; renderHome(); var d = $( '[data-fd-did]' ) || $( '[data-fd-how]' ); if ( d ) { d.focus(); } } ); }
		var did = $( '[data-fd-did]' ); if ( did ) { did.addEventListener( 'click', function () { store.set( 'stubli-installed', '1' ); fd.how = false; renderHome(); } ); }
		var hide = $( '[data-fd-hide]' ); if ( hide ) { hide.addEventListener( 'click', function () { B.firstDay = null; renderHome(); send( 'POST', 'firstday', {} ).catch( function () {} ); } ); }
	}
	function renderHome() {
		var body = '';
		if ( ! feed.loaded ) {
			body = ( '<div class="a-feed-skel" aria-hidden="true"><span></span><span></span></div><p class="sr" role="status">' + __( 'Loading your feed…', 'stubli' ) + '</p>' );
		} else if ( feed.error ) {
			body = '<div class="a-notice" role="alert"><p>' + esc( feed.error ) + ( '</p><button type="button" class="a-btn quiet" data-feed-retry>' + __( 'Try again', 'stubli' ) + '</button></div>' );
		} else if ( ! ( feed.follows || [] ).length ) {
			body = ( '<section class="a-card"><h2>' + __( 'Your feed is waiting', 'stubli' ) + '</h2><p class="muted">' + __( 'Follow someone and their new photos will appear here, newest first. No ads, no suggestions, just the people you chose.', 'stubli' ) + '</p>' ) + followForm( 'home' ) + '</section>' +
				( '<section class="a-card"><h2>' + __( 'Or invite a friend', 'stubli' ) + '</h2><p class="muted">' + __( 'When they\'ve set up their Stubli, you follow each other automatically.', 'stubli' ) + '</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>' + __( 'Create an invite link', 'stubli' ) + '</button></section>' );
		} else {
			var pills = '';
			if ( feed.lists.length ) {
				pills = '<div class="a-chips a-filter" role="group" aria-label="' + esc( __( 'Show', 'stubli' ) ) + '">' + [ '' ].concat( feed.lists ).map( function ( l ) {
					return '<button type="button" class="a-chip" aria-pressed="' + ( feed.list === l ) + '" data-list="' + esc( l ) + '">' + esc( l || __( 'All', 'stubli' ) ) + '</button>';
				} ).join( '' ) + '</div>';
			}
			body = pills + ( feed.items.length ? feed.items.map( item ).join( '' ) : ( '<div class="a-empty-feed"><p class="muted">' + __( 'Nothing here yet. Follow a few people and their new posts arrive here, every 15 minutes or so.', 'stubli' ) + '</p><div class="a-actions"><a class="a-btn" href="#discover">' + __( 'Find people in Discover', 'stubli' ) + '</a><a class="a-btn quiet" href="#more">' + __( 'Invite a friend', 'stubli' ) + '</a></div></div>' ) ) +
				( feed.more && feed.items.length ? ( '<button type="button" class="a-btn quiet wide" data-older>' + __( 'Older posts', 'stubli' ) + '</button>' ) : '' ) +
				( feed.items.length && ! feed.more ? '<div class="a-end">' + sky( 28 ) + ( '<strong>' + __( 'That\'s everything for now', 'stubli' ) + '</strong><span class="muted">' + __( 'You\'re all caught up.', 'stubli' ) + ' ' ) + esc( endLine() ) + '</span></div>' : '' );
		}
		var sub = feed.stale ? __( 'Offline. Showing your feed from last time.', 'stubli' ) : feed.loaded && feed.newSince ? _f( __( '%s new since your last visit', 'stubli' ), feed.newSince ) : '';
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div>' +
			'<h1 class="a-greet a-greet-sky">' + sky( 36 ) + '<span>' + esc( greeting() ) + '</span></h1>' + ( sub ? '<p class="muted" style="margin:-2px 0 14px">' + esc( sub ) + '</p>' : '' ) +
			setupCard() + firstDayCard() + body + '</main>' + bar( 'home' );
		bindFollow(); bindInvite(); bindFirstDay();
		var fr = $( '[data-feed-retry]' ); if ( fr ) { fr.addEventListener( 'click', retryFeed ); }
		$$( '[data-list]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { feed.list = b.dataset.list; loadFeed( true ).then( renderHome ); } ); } );
		var older = $( '[data-older]' );
		if ( older ) { older.addEventListener( 'click', function () { older.disabled = true; older.textContent = __( 'Loading…', 'stubli' ); var y = window.scrollY; loadFeed( false ).then( function () { renderHome(); window.scrollTo( 0, y ); } ); } ); }
		bindItems();
	}
	function item( it ) {
		var imgs = ( it.images && it.images.length ? it.images : ( it.image ? [ { url: it.image, alt: '' } ] : [] ) );
		var isNew = Date.parse( it.published ) > ( feed.seenBefore || 0 ) && feed.seenBefore;
		var note = it.content_note && ! feed.open[ it.id ];
		var c = feed.open[ 'c' + it.id ];
		return '<article class="a-item" data-item="' + it.id + '">' +
			'<header class="a-item-head">' + face( it.author.avatar, it.author.name, 32 ) +
			'<span><a href="' + esc( it.author.url ) + '"><strong>' + esc( unent( it.author.name ) ) + '</strong></a> <span class="muted">' + esc( gentle( it.published ) ) + '</span>' + ( it.audience === 'friends' ? ( ' <span class="a-friends-tag">' + __( 'Friends only', 'stubli' ) + '</span>' ) : '' ) + ( isNew ? ( ' <span class="a-new">' + __( 'New', 'stubli' ) + '</span>' ) : '' ) + '</span></header>' +
			( imgs.length ? '<div class="a-photos' + ( note ? ' has-note' : '' ) + '"><div class="a-track" tabindex="0" aria-label="' + esc( __( 'Photos', 'stubli' ) ) + '">' + imgs.map( function ( im ) {
					if ( im.video && /^https?:\/\//.test( im.video ) ) {
						return '<button type="button" class="a-play" data-play="' + esc( im.video ) + '" data-type="' + esc( im.type || 'video/mp4' ) + '" aria-label="' + esc( note ? __( 'Play video', 'stubli' ) : ( im.alt ? _f( __( 'Play video: %s', 'stubli' ), im.alt ) : __( 'Play video', 'stubli' ) ) ) + '"><img src="' + esc( im.url ) + '" alt="" loading="lazy"><span class="a-play-icon" aria-hidden="true">' + icon( 'play', 28 ) + '</span></button>';
					}
					return '<img src="' + esc( im.url ) + '" alt="' + esc( note ? '' : ( im.alt || '' ) ) + '" loading="lazy">';
				} ).join( '' ) + '</div>' +
				( imgs.length > 1 ? '<span class="a-count-pill" aria-hidden="true">' + _f( __( '%s of %s', 'stubli' ), 1, imgs.length ) + '</span>' : '' ) +
				( note ? '<button type="button" class="a-reveal" data-reveal="' + it.id + '"><strong>' + esc( it.content_note ) + ( '</strong><span>' + __( 'Show photo', 'stubli' ) + '</span></button>' ) : '' ) + '</div>' : '' ) +
			( it.caption_html ? '<div class="a-caption">' + it.caption_html + '</div>' : '' ) +
			'<div class="a-item-acts"><button type="button" class="a-text-btn" data-comment="' + it.id + '" aria-expanded="' + ( c ? 'true' : 'false' ) + ( '">' + __( 'Comment', 'stubli' ) + '</button>' ) + ( it.my_comments ? ( '<span class="muted small">' + __( 'You commented', 'stubli' ) + '</span>' ) : '' ) + '<a class="a-text-btn muted" href="' + esc( it.permalink ) + ( '">' + __( 'Open', 'stubli' ) + '</a></div>' ) +
			( c ? '<div class="a-comment-box"><label class="sr" for="a-cm-' + it.id + ( '">' + __( 'Your comment', 'stubli' ) + '</label><textarea id="a-cm-' ) + it.id + '" class="a-textarea" maxlength="1000" placeholder="' + esc( __( 'Say something kind…', 'stubli' ) ) + '">' + esc( c.text || '' ) + '</textarea><div class="a-actions" style="margin-top:8px"><button type="button" class="a-btn" data-send-comment="' + it.id + ( '">' + __( 'Send', 'stubli' ) + '</button><button type="button" class="a-text-btn" data-comment="' ) + it.id + ( '">' + __( 'Cancel', 'stubli' ) + '</button></div><p class="a-help" data-cm-msg="' ) + it.id + '" aria-live="polite">' + esc( c.msg || __( 'It\'s saved on your own site too. It shows on their post right away if they follow you; otherwise after their OK.', 'stubli' ) ) + '</p></div>' : '' ) +
			'</article>';
	}
	function bindItems() {
		$$( '[data-reveal]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { feed.open[ b.dataset.reveal ] = true; renderHome(); } ); } );
		$$( '[data-comment]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var k = 'c' + b.dataset.comment; feed.open[ k ] = feed.open[ k ] ? null : { text: '' }; var y = window.scrollY; renderHome(); window.scrollTo( 0, y ); var t = $( '#a-cm-' + b.dataset.comment ); if ( t ) { t.focus( { preventScroll: true } ); } } ); } );
		$$( '.a-comment-box textarea' ).forEach( function ( t ) { t.addEventListener( 'input', function () { var k = 'c' + t.id.replace( 'a-cm-', '' ); if ( feed.open[ k ] ) { feed.open[ k ].text = t.value; } } ); } );
		$$( '[data-send-comment]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = b.dataset.sendComment, k = 'c' + id, t = $( '#a-cm-' + id ), m = $( '[data-cm-msg="' + id + '"]' );
				if ( ! t.value.trim() ) { t.focus(); return; }
				b.disabled = true; b.textContent = __( 'Sending…', 'stubli' );
				send( 'POST', 'items/' + id + '/comments', { text: t.value.trim() } ).then( function ( r ) {
					var it = feed.items.filter( function ( x ) { return String( x.id ) === String( id ); } )[ 0 ];
					if ( it ) { it.my_comments = ( it.my_comments || 0 ) + 1; }
					var msg = r && r.status === 'paused' ? __( 'Saved. They\'re taking a break, so Stubli will keep sending it and it shows once they\'re back.', 'stubli' ) : r && r.status === 'not_sent' ? __( 'Saved on your site. Their site didn\'t answer, so Stubli will keep trying for a week.', 'stubli' ) : __( 'Sent. Thank you.', 'stubli' );
					feed.open[ k ] = null; var y = window.scrollY; renderHome(); window.scrollTo( 0, y );
					var art = $( '[data-item="' + id + '"] .a-item-acts' ); if ( art ) { art.insertAdjacentHTML( 'afterend', '<p class="a-help" role="status">' + esc( msg ) + '</p>' ); }
				}, function ( e ) { b.disabled = false; b.textContent = __( 'Send', 'stubli' ); m.textContent = ( e && e.message ) || __( 'Couldn\'t send that. Try again.', 'stubli' ); } );
			} );
		} );
		$$( '[data-play]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				// Tap to play, with sound. Nothing downloads before the tap.
				var v = document.createElement( 'video' ), img = b.querySelector( 'img' ), src = document.createElement( 'source' );
				v.controls = true; v.playsInline = true; v.setAttribute( 'playsinline', '' ); v.className = 'a-video';
				if ( img ) { v.poster = img.src; if ( img.naturalWidth ) { v.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight; } }
				src.src = b.dataset.play; src.type = b.dataset.type; v.appendChild( src );
				b.parentNode.replaceChild( v, b ); v.focus();
				var pr = v.play(); if ( pr && pr.catch ) { pr.catch( function () {} ); }
			} );
		} );
		$$( '.a-photos .a-track' ).forEach( function ( tr ) {
			var pill = tr.parentNode.querySelector( '.a-count-pill' ), n = tr.children.length;
			if ( ! pill ) { return; }
			tr.addEventListener( 'scroll', function () { var i = Math.round( tr.scrollLeft / Math.max( 1, tr.clientWidth ) ); pill.textContent = _f( __( '%s of %s', 'stubli' ), i + 1, n ); }, { passive: true } );
		} );
	}

	function bindInvite() {
		var b = $( '[data-invite]' ), out = $( '[data-invite-out]' );
		if ( ! b ) { return; }
		b.addEventListener( 'click', function () {
			b.disabled = true; b.textContent = __( 'Creating…', 'stubli' );
			send( 'POST', 'invites', {} ).then( function ( r ) {
				if ( B.firstDay ) { B.firstDay.invited = true; }
				out.innerHTML = ( '<label class="sr" for="a-inv">' + __( 'Invite link', 'stubli' ) + '</label><input id="a-inv" class="a-field" readonly value="' ) + esc( r.url ) + '">' +
					'<div class="a-actions" style="margin-top:8px">' + ( navigator.share ? ( '<button type="button" class="a-btn" data-inv-share>' + __( 'Share…', 'stubli' ) + '</button>' ) : '' ) + ( '<button type="button" class="a-btn quiet" data-inv-copy>' + __( 'Copy link', 'stubli' ) + '</button></div><p class="a-help" aria-live="polite" data-inv-msg></p>' );
				b.hidden = true;
				var f = $( '#a-inv' ), m = $( '[data-inv-msg]' );
				f.addEventListener( 'focus', function () { f.select(); } );
				$( '[data-inv-copy]' ).addEventListener( 'click', function () {
					var ok = function () { m.textContent = __( 'Copied. Send it to one friend.', 'stubli' ); };
					if ( navigator.clipboard ) { navigator.clipboard.writeText( r.url ).then( ok, function () { f.select(); m.textContent = __( 'Select the link and copy it.', 'stubli' ); } ); } else { f.select(); document.execCommand( 'copy' ); ok(); }
				} );
				var sh = $( '[data-inv-share]' );
				if ( sh ) { sh.addEventListener( 'click', function () { navigator.share( { title: __( 'Join me on Stubli', 'stubli' ), text: __( 'I made you an invite to Stubli, a calm place for photos.', 'stubli' ), url: r.url } ).catch( function () {} ); } ); }
			}, function ( e ) {
				b.disabled = false; b.textContent = __( 'Create an invite link', 'stubli' );
				out.innerHTML = '<div class="a-notice" role="alert"><p>' + esc( e.message ) + '</p></div>';
			} );
		} );
	}



	/* ── Discover: friends of friends ── */
	var disc = { loaded: false, list: [], followed: {}, i: 0 };
	function viewDiscover() {
		renderDiscover();
		disc.error = '';
		send( 'GET', 'discover', null ).then( function ( l ) { disc.list = Array.isArray( l ) ? l : []; disc.loaded = true; disc.error = ''; }, function ( e ) { disc.loaded = true; disc.error = ( e && e.message ) || __( 'Couldn\'t load suggestions.', 'stubli' ); } ).then( function () { if ( location.hash === '#discover' ) { renderDiscover(); } } );
	}
	function viaText( v ) { return v.length > 2 ? _f( __( '%s and %s more', 'stubli' ), v.slice( 0, 2 ).join( ', ' ), v.length - 2 ) : v.length === 2 ? _f( __( '%s and %s', 'stubli' ), v[ 0 ], v[ 1 ] ) : v.join( '' ); }
	function renderDiscover() {
		var body;
		if ( ! disc.loaded ) {
			body = ( '<div class="a-feed-skel" aria-hidden="true"><span style="height:220px"></span><span style="height:220px"></span></div><p class="sr" role="status">' + __( 'Loading…', 'stubli' ) + '</p>' );
		} else if ( disc.error ) {
			body = '<div class="a-notice" role="alert"><p>' + esc( disc.error ) + ( '</p><button type="button" class="a-btn quiet" data-disc-retry>' + __( 'Try again', 'stubli' ) + '</button></div>' );
		} else if ( ! disc.list.length ) {
			body = ( '<p class="muted">' + __( 'No suggestions yet. They come from the public lists of the people you follow, so they appear once you follow a few people who share theirs.', 'stubli' ) + '</p>' );
		} else {
			var at = Math.min( disc.i, disc.list.length );
			body = at >= disc.list.length ? '<div class="a-end">' + sky( 28 ) + ( '<strong>' + __( 'That\'s everyone for now', 'stubli' ) + '</strong><span class="muted">' + __( 'New suggestions come as the people you follow follow others.', 'stubli' ) + '</span><button type="button" class="a-text-btn" data-disc-again>' + __( 'See them again', 'stubli' ) + '</button></div>' ) : [ disc.list[ at ] ].map( function ( s ) {
				var host = hostOf( s.url ), done = disc.followed[ s.url ], pr = s.prompts && s.prompts[ 0 ];
				return '<article class="a-card a-disc">' +
					'<div class="a-wait-head">' + ( s.avatar ? '<img src="' + esc( s.avatar ) + '" alt="" width="40" height="40">' : '<span class="a-av"></span>' ) +
					'<span><a href="' + esc( s.url ) + '"><strong>' + esc( s.name || host ) + '</strong></a> <span class="muted">' + esc( host ) + '</span><span class="muted small a-ctx">' + _f( __( 'Followed by %s', 'stubli' ), esc( viaText( s.via ) ) ) + '</span></span>' +
					'<button type="button" class="a-btn' + ( done ? ' quiet' : '' ) + ' a-disc-follow" data-disc="' + esc( s.url ) + '"' + ( done ? ' disabled' : '' ) + '>' + ( done ? __( 'Following', 'stubli' ) : __( 'Follow', 'stubli' ) ) + '</button></div>' +
					( s.bio ? '<p class="a-disc-bio">' + esc( s.bio ) + '</p>' : '' ) +
					( pr ? '<div class="a-pcard a-disc-card"><p class="a-pcard-q">' + esc( pr.q ) + '</p><p class="a-pcard-a">' + esc( pr.a ) + '</p><a class="a-text-btn a-pcard-reply" href="' + esc( B.home + 'app/?act=1&do=note&p=0&url=' + encodeURIComponent( s.url ) ) + ( '">' + __( 'Reply to this', 'stubli' ) + '</a></div>' ) : '' ) +
					( s.recent && s.recent.length ? '<div class="a-wn-thumbs">' + s.recent.map( function ( r ) { return '<a href="' + esc( r.link ) + '"><img src="' + esc( r.url ) + '" alt="' + esc( r.note ? _f( __( 'Photo with a content note: %s', 'stubli' ), r.note ) : r.alt ) + '" loading="lazy"' + ( r.note ? ' class="is-noted"' : '' ) + '></a>'; } ).join( '' ) + '</div>' : '' ) +
					'<p class="a-help" data-disc-msg="' + esc( s.url ) + '" aria-live="polite"></p>' +
					'<div class="a-actions"><button type="button" class="a-btn quiet" data-disc-next>' + ( done ? __( 'Next', 'stubli' ) : __( 'Not now', 'stubli' ) ) + '</button></div></article>' +
					'<p class="muted small" style="text-align:center">' + _f( __( '%s of %s', 'stubli' ), at + 1, disc.list.length ) + '</p>';
			} ).join( '' );
		}
		// People: bring friends in, the people you follow, then people they follow.
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + ( '</div><h1 class="a-greet">' + __( 'People', 'stubli' ) + '</h1>' ) +
			( '<section class="a-card" data-invite-card><h2>' + __( 'Invite a friend', 'stubli' ) ) + helpLink( 'reading-and-following/follow', __( 'following and invites', 'stubli' ) ) + ( '</h2><p class="muted">' + __( 'Send them a link. Once they\'ve set up their own Stubli, you follow each other. Each link works once, for 30 days.', 'stubli' ) + '</p>' ) +
			( '<div data-invite-out></div><button type="button" class="a-btn" data-invite>' + __( 'Create an invite link', 'stubli' ) + '</button></section>' ) +
			peopleCard() +
			( '<h2 class="a-caps" style="margin-top:22px">' + __( 'Discover', 'stubli' ) + '</h2><p class="muted" style="margin:-2px 0 14px">' + __( 'Sites followed by people you follow. No rankings, no algorithm: the newest photos come first.', 'stubli' ) + '</p>' ) +
			body + '</main>' + bar( 'discover' );
		bindInvite(); bindPeople();
		if ( ! pp.loaded && ! pp.loading ) { pp.loading = true; send( 'GET', 'follows', null ).then( function ( r ) { pp.list = Array.isArray( r ) ? r : []; pp.loaded = true; pp.loading = false; if ( $( '[data-people]' ) ) { redrawPeople(); } }, function () { pp.loading = false; } ); }
		var dr = $( '[data-disc-retry]' ); if ( dr ) { dr.addEventListener( 'click', function () { disc.loaded = false; viewDiscover(); } ); }
		var dn = $( '[data-disc-next]' ); if ( dn ) { dn.addEventListener( 'click', function () { disc.i++; renderDiscover(); var h = $( '.a-disc strong, .a-end strong' ); if ( h ) { h.setAttribute( 'tabindex', '-1' ); h.focus(); } } ); }
		var da = $( '[data-disc-again]' ); if ( da ) { da.addEventListener( 'click', function () { disc.i = 0; renderDiscover(); } ); }
		bindFollow();
		$$( '[data-disc]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var url = b.dataset.disc, m = $( '[data-disc-msg="' + url + '"]' );
				b.disabled = true; b.textContent = __( 'Following…', 'stubli' );
				send( 'POST', 'follows', { url: url } ).then( function () {
					disc.followed[ url ] = true; feed.loaded = false; b.textContent = __( 'Following', 'stubli' ); b.classList.add( 'quiet' );
					m.textContent = __( 'Their new photos will appear in Home.', 'stubli' );
				}, function ( e ) { b.disabled = false; b.textContent = __( 'Follow', 'stubli' ); m.textContent = ( e && e.message ) || __( 'That didn\'t work. Try again.', 'stubli' ); } );
			} );
		} );
	}

	/* ── What's new ── */
	var wn = { loaded: false, queue: [], activity: [], items: [], done: {}, seen: +( store.get( 'stubli-wn-seen' ) || 0 ), showLater: false };
	function viewWhatsNew() {
		renderWhatsNew();
		Promise.all( [
			send( 'GET', 'queue', null ).then( function ( q ) { wn.queue = Array.isArray( q ) ? q : []; }, function () { wn.queue = []; } ),
			send( 'GET', 'activity', null ).then( function ( a ) { wn.activity = Array.isArray( a ) ? a : []; }, function () { wn.activity = []; } ),
			send( 'GET', 'feed?page=1', null ).then( function ( f ) { wn.items = Array.isArray( f ) ? f : []; }, function () { wn.items = []; } ),
			loadNotes()
		] ).then( function () {
			wn.loaded = true; wn.since = wn.seen || ( Date.now() - 86400000 );
			B.waiting = wn.queue.filter( function ( c ) { return ! c.later; } ).length;
			if ( location.hash === '#whatsnew' ) { renderWhatsNew(); }
			wn.seen = Date.now(); store.set( 'stubli-wn-seen', String( wn.seen ) );
		} );
	}
	function hostOf( u ) { return String( u || '' ).replace( /^https?:\/\//, '' ).replace( /\/.*$/, '' ); }
	function renderWhatsNew() {
		var body = '';
		if ( ! wn.loaded ) {
			body = ( '<div class="a-feed-skel" aria-hidden="true"><span style="height:160px"></span><span style="height:160px"></span></div><p class="sr" role="status">' + __( 'Loading…', 'stubli' ) + '</p>' );
		} else {
			body += notesSection();
			var waiting = wn.queue.filter( function ( c ) { return ! c.later || wn.done[ c.id ]; } );
			var later = wn.queue.filter( function ( c ) { return c.later && ! wn.done[ c.id ]; } );
			var open = waiting.filter( function ( c ) { return ! wn.done[ c.id ]; } ).length;
			body += ( '<h2 class="a-label">' + __( 'Waiting for your OK', 'stubli' ) ) + ( open ? ' · ' + open : '' ) + '</h2>';
			// Many comments from one site: show two, fold the rest.
			var perHost = {}, folded = {}, names = {};
			body += waiting.length ? waiting.map( function ( c ) {
				var h = hostOf( c.author.url );
				names[ h ] = names[ h ] || unent( c.author.name || '' ) || h;
				perHost[ h ] = ( perHost[ h ] || 0 ) + 1;
				if ( perHost[ h ] > 2 && ! ( wn.expand || {} )[ h ] && ! wn.done[ c.id ] ) { folded[ h ] = ( folded[ h ] || 0 ) + 1; return ''; }
				return waitCard( c );
			} ).join( '' ) + Object.keys( folded ).map( function ( h ) {
				return '<button type="button" class="a-btn quiet wide" data-wn-more="' + esc( h ) + '">' + _f( __( '%s more from %s', 'stubli' ), folded[ h ], esc( names[ h ] ) ) + '</button>';
			} ).join( '' ) : ( '<p class="muted">' + __( 'Nothing is waiting. Comments from people you follow appear on your page straight away.', 'stubli' ) + '</p>' );
			if ( later.length ) {
				body += '<button type="button" class="a-text-btn" data-later-toggle aria-expanded="' + wn.showLater + '">' + _f( __( 'Set aside · %s', 'stubli' ), later.length ) + '</button>' + ( wn.showLater ? later.map( waitCard ).join( '' ) : '' );
			}
			var fresh = wn.items.filter( function ( i ) { return Date.parse( i.published ) > wn.since; } );
			var groups = {};
			fresh.forEach( function ( i ) { var k = i.list || ''; ( groups[ k ] = groups[ k ] || [] ).push( i ); } );
			var keys = Object.keys( groups ).sort( function ( a, b ) { return ( a === '' ) - ( b === '' ) || a.localeCompare( b ); } );
			body += ( '<h2 class="a-label">' + __( 'From people you follow', 'stubli' ) + '</h2>' );
			body += keys.length ? keys.map( function ( k ) {
				var g = groups[ k ], names = [];
				g.forEach( function ( i ) { var n = String( i.author.name || '' ).split( ' ' )[ 0 ]; if ( names.indexOf( n ) < 0 ) { names.push( n ); } } );
				var who = names.length > 2 ? _f( __( '%s and others', 'stubli' ), names.slice( 0, 2 ).join( ', ' ) ) : names.length === 2 ? _f( __( '%s and %s', 'stubli' ), names[ 0 ], names[ 1 ] ) : names.join( '' );
				return '<section class="a-wn-group"><div class="a-row"><h3 class="a-wn-h"><strong>' + esc( k || __( 'Everyone', 'stubli' ) ) + '</strong> <span class="muted">' + _f( __( '%s new from %s', 'stubli' ), g.length, esc( who ) ) + '</span></h3><a class="a-text-btn" href="#home" data-open-list="' + esc( k ) + ( '">' + __( 'Open', 'stubli' ) + '</a></div>' ) +
					'<div class="a-wn-thumbs">' + g.slice( 0, 3 ).map( function ( i ) { var im = ( i.images && i.images[ 0 ] ) || { url: i.image, alt: '' }; return '<a href="' + esc( i.permalink ) + '"><img src="' + esc( im.url ) + '" alt="' + esc( i.content_note ? i.author.name + ': ' + i.content_note : i.author.name + ( im.alt ? ': ' + im.alt : '' ) ) + '" loading="lazy"' + ( i.content_note ? ' class="is-noted"' : '' ) + '></a>'; } ).join( '' ) + '</div></section>';
			} ).join( '' ) : ( '<p class="muted">' + __( 'Nothing new since your last look.', 'stubli' ) + '</p>' );
			var recent = wn.activity.filter( function ( a ) { return a.status === 'approved' && Date.parse( a.date ) > Date.now() - 14 * 86400000; } ).slice( 0, 12 );
			body += ( '<h2 class="a-label">' + __( 'On your page', 'stubli' ) + '</h2>' );
			body += recent.length ? '<ul class="a-wn-list">' + recent.map( function ( a ) {
				return '<li><strong>' + esc( unent( a.author.name ) ) + '</strong> ' + ( a.where === 'guestbook' ? __( 'signed your guest book', 'stubli' ) : _f( __( 'commented on %s', 'stubli' ), '<a href="' + esc( a.post_url ) + '">' + esc( unent( a.post_title ) || __( 'your post', 'stubli' ) ) + '</a>' ) ) + ' <span class="muted">· ' + esc( gentle( a.date ) ) + '</span><span class="a-wn-quote">' + esc( String( a.text || '' ).slice( 0, 140 ) ) + '</span></li>';
			} ).join( '' ) + '</ul>' : ( '<p class="muted">' + __( 'No new comments or guest book entries in the last two weeks.', 'stubli' ) + '</p>' );
		}
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + ( '</div><h1 class="a-greet">' + __( 'What\'s new', 'stubli' ) + '</h1><p class="muted" style="margin:-2px 0 6px">' + __( 'Since your last look', 'stubli' ) + '</p>' ) + body + '</main>' + bar( 'whatsnew' );
		$$( '[data-wn-more]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { wn.expand = wn.expand || {}; wn.expand[ b.dataset.wnMore ] = true; renderWhatsNew(); } ); } );
		bindNotes( renderWhatsNew );
		var lt = $( '[data-later-toggle]' );
		if ( lt ) { lt.addEventListener( 'click', function () { wn.showLater = ! wn.showLater; renderWhatsNew(); } ); }
		$$( '[data-open-list]' ).forEach( function ( a ) { a.addEventListener( 'click', function () { feed.list = a.dataset.openList; feed.loaded = false; } ); } );
		$$( '[data-wn]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = b.dataset.id, act = b.dataset.wn, c = wn.queue.filter( function ( x ) { return String( x.id ) === id; } )[ 0 ];
				if ( act === 'undo' ) {
					b.disabled = true;
					send( 'DELETE', 'blocks?url=' + encodeURIComponent( wn.done[ id ].blocked ), null ).then( function () { delete wn.done[ id ]; renderWhatsNew(); }, function () { b.disabled = false; } );
					return;
				}
				$$( '[data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = true; } );
				send( 'POST', 'queue/' + id, { action: act } ).then( function ( r ) {
					wn.done[ id ] = { status: act, blocked: r && r.blocked };
					if ( act === 'later' && c ) { c.later = true; }
					B.waiting = wn.queue.filter( function ( x ) { return ! x.later && ! wn.done[ x.id ]; } ).length;
					renderWhatsNew();
				}, function ( e ) { $$( '[data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = false; } ); alertIn( id, ( e && e.message ) || __( 'That didn\'t work. Try again.', 'stubli' ) ); } );
			} );
		} );
	}
	function alertIn( id, text ) { var card = $( '[data-card="' + id + '"]' ); if ( card ) { card.insertAdjacentHTML( 'beforeend', '<p class="a-help" role="alert">' + esc( text ) + '</p>' ); } }
	function waitCard( c ) {
		var d = wn.done[ c.id ], host = hostOf( c.author.url );
		var ctx = ( c.where === 'guestbook' ? __( 'Guest book', 'stubli' ) : ( c.post && c.post.title ? _f( __( 'Comment on %s', 'stubli' ), unent( c.post.title ) ) : __( 'Comment on your post', 'stubli' ) ) ) + ( c.via ? ' ' + _f( __( '· followed by %s', 'stubli' ), c.via ) : '' );
		if ( d ) {
			var msg = d.status === 'show' ? __( 'Shown on your page.', 'stubli' ) : d.status === 'later' ? __( 'Set aside. It stays hidden; you\'ll find it under Set aside.', 'stubli' ) : _f( __( 'Blocked quietly. Nothing from %s will show here, and they aren\'t told.', 'stubli' ), host );
			return '<div class="a-card a-wait is-done" data-card="' + c.id + '"><p role="status" style="margin:0">' + esc( msg ) + '</p>' + ( d.status === 'block' ? '<button type="button" class="a-text-btn" data-wn="undo" data-id="' + c.id + ( '">' + __( 'Undo', 'stubli' ) + '</button>' ) : '' ) + '</div>';
		}
		return '<div class="a-card a-wait" data-card="' + c.id + '"><div class="a-wait-head">' + face( c.author.avatar, c.author.name, 36 ) +
			'<span><strong>' + esc( unent( c.author.name ) ) + '</strong> <span class="muted">' + esc( host ) + '</span><span class="muted small a-ctx">' + esc( ctx ) + '</span></span></div>' +
			'<p class="a-wait-text">' + esc( c.text ) + '</p>' +
			'<div class="a-wait-acts"><button type="button" class="a-btn" data-wn="show" data-id="' + c.id + ( '">' + __( 'Show it', 'stubli' ) + '</button>' ) + ( c.later ? '' : '<button type="button" class="a-btn quiet" data-wn="later" data-id="' + c.id + ( '">' + __( 'Not now', 'stubli' ) + '</button>' ) ) +
			'<button type="button" class="a-text-btn a-block" data-wn="block" data-id="' + c.id + ( '">' + __( 'Block this site', 'stubli' ) + '</button></div></div>' );
	}

	/* ── Setup wizard (DESIGN §13): Hello · Interests · Look · Your people · Ready ── */
	var STEPS = [ __( 'Hello', 'stubli' ), __( 'Interests', 'stubli' ), __( 'Look', 'stubli' ), __( 'Your people', 'stubli' ), __( 'Ready', 'stubli' ) ];
	var MODE_HINTS = {
		auto: __( 'Follows the phone: light by day, dark at night.', 'stubli' ),
		light: __( 'Bright linen and ink, like a fresh page.', 'stubli' ),
		paper: __( 'Warm paper with a book font for reading.', 'stubli' ),
		dark: __( 'A dark, quiet ground that\'s easy on the eyes at night.', 'stubli' )
	};
	var su = { loaded: false, d: null, step: 0, busy: false, error: '', picked: [], own: '', sugg: null, ticks: {}, photoBusy: false, photoMsg: '' };
	function viewSetup() {
		if ( su.loaded ) { return renderSetup(); }
		if ( su.loading ) { return; }
		su.loading = true;
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + ( '</div><div class="a-feed-skel" aria-hidden="true"><span style="height:280px"></span></div><p class="sr" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>' );
		send( 'GET', 'setup', null ).then( function ( d ) {
			su.d = d; su.loaded = true; su.loading = false; su.step = d.done ? 0 : Math.min( 4, d.step || 0 );
			su.picked = d.interests.map( function ( i ) { return i.name; } );
			renderSetup();
		}, function ( e ) {
			su.loading = false;
			root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div><div class="a-notice" role="alert"><p>' + esc( e.message ) + ( '</p><a class="a-btn quiet" href="#home">' + __( 'Go to Home', 'stubli' ) + '</a></div></main>' );
		} );
	}
	function accentCss( a ) {
		if ( ! a || a.key === 'moss' ) { return ''; }
		return ':root,[data-mode="light"],[data-mode="paper"]{--accent:' + a.hex + ';--accent-ink:' + a.ink + ';--accent-text:' + ( a.key === 'gold' ? '#7d5f16' : a.hex ) + '}[data-mode="dark"]{--accent:' + a.dark + ';--accent-ink:#1c1916;--accent-text:' + a.dark + '}';
	}
	function accentOf( key ) { return su.d.accents.filter( function ( a ) { return a.key === key; } )[ 0 ] || su.d.accents[ 0 ]; }
	function applyLook() {
		var s = document.getElementById( 'stubli-accent' );
		if ( ! s ) { s = document.createElement( 'style' ); s.id = 'stubli-accent'; document.head.appendChild( s ); }
		s.textContent = accentCss( accentOf( su.d.accent ) );
		if ( ! store.get( 'stubli-mode' ) ) {
			var m = su.d.mode === 'auto' ? ( matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' ) : su.d.mode;
			document.documentElement.setAttribute( 'data-mode', m );
		}
	}
	/* ── Your guest book: the welcome line, and each entry to pin or remove ── */
	var gbk = { loaded: false, entries: [], intro: '', msg: '' };
	function viewGuestbook() {
		if ( ! gbk.loaded ) {
			root.innerHTML = arrangeHead( '#you', __( 'Guest book', 'stubli' ) ) + '<main class="a-main" id="main"><p class="muted" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>';
			Promise.all( [ send( 'GET', 'guestbook', null ), send( 'GET', 'guestbook/intro', null ) ] ).then( function ( r ) { gbk.entries = r[ 0 ] || []; gbk.intro = r[ 1 ].text || ''; gbk.loaded = true; if ( location.hash === '#guestbook' ) { viewGuestbook(); } }, function ( e ) { toast( e.message ); } );
			return;
		}
		root.innerHTML = arrangeHead( '#you', __( 'Guest book', 'stubli' ) ) + '<main class="a-main" id="main">' +
			'<section class="a-card"><h2>' + __( 'Welcome line', 'stubli' ) + '</h2><p class="muted">' + __( 'A few words at the top of your guest book.', 'stubli' ) + '</p>' +
			'<label class="sr" for="a-gb-intro">' + __( 'Welcome line', 'stubli' ) + '</label><textarea id="a-gb-intro" class="a-field a-grow" rows="2" maxlength="300">' + esc( gbk.intro ) + '</textarea>' +
			'<div class="a-actions" style="margin-top:10px"><button type="button" class="a-btn" data-gb-save>' + __( 'Save', 'stubli' ) + '</button><a class="a-text-btn" href="' + esc( me.p && me.p.guestbook || B.home ) + '">' + __( 'See it on your page', 'stubli' ) + '</a></div><p class="a-help" aria-live="polite" data-gb-msg>' + esc( gbk.msg ) + '</p></section>' +
			'<h2 class="a-caps">' + ( gbk.entries.length === 1 ? __( '1 signature', 'stubli' ) : _f( __( '%s signatures', 'stubli' ), gbk.entries.length ) ) + '</h2>' +
			( gbk.entries.length ? '<ul class="a-gbl" role="list">' + gbk.entries.map( function ( e ) {
				return '<li class="a-gbl-item' + ( e.pinned ? ' is-pinned' : '' ) + '">' + ( e.pinned ? '<span class="a-gbl-pin">' + icon( 'pin', 13 ) + __( 'Pinned', 'stubli' ) + '</span>' : '' ) + '<p class="a-gbl-text">' + esc( unent( e.text ) ) + '</p>' +
					'<div class="a-gbl-sig"><span><strong>' + esc( unent( e.author.name ) ) + '</strong><small class="muted">' + esc( hostOf( e.author.url ) ) + ' · ' + esc( gentle( e.date ) ) + '</small></span>' +
					'<button type="button" class="a-btn quiet" data-gb-pin="' + e.id + '" data-on="' + ( e.pinned ? 1 : 0 ) + '">' + icon( 'pin', 15 ) + ( e.pinned ? __( 'Unpin', 'stubli' ) : __( 'Pin', 'stubli' ) ) + '</button>' +
					'<button type="button" class="a-btn quiet" data-gb-rm="' + e.id + '" aria-label="' + esc( _f( __( 'Remove the entry from %s', 'stubli' ), unent( e.author.name ) ) ) + '">' + __( 'Remove', 'stubli' ) + '</button></div></li>';
			} ).join( '' ) + '</ul>' : '<p class="muted">' + __( 'No signatures yet. When friends sign from their own Stubli, their words appear here.', 'stubli' ) + '</p>' ) +
			'</main>';
		gbk.msg = '';
		var ta = $( '#a-gb-intro' ), fit = function () { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }; fit(); ta.addEventListener( 'input', fit );
		$( '[data-gb-save]' ).addEventListener( 'click', function ( e ) {
			var b = e.currentTarget; b.disabled = true;
			send( 'POST', 'guestbook/intro', { text: ta.value } ).then( function ( r ) { gbk.intro = r.text; gbk.msg = __( 'Saved. Your guest book shows it now.', 'stubli' ); viewGuestbook(); }, function ( er ) { b.disabled = false; $( '[data-gb-msg]' ).textContent = er.message; } );
		} );
		$$( '[data-gb-pin]' ).forEach( function ( b ) { b.addEventListener( 'click', function () {
			b.disabled = true;
			send( b.dataset.on === '1' ? 'DELETE' : 'POST', 'guestbook/' + b.dataset.gbPin + '/pin', null ).then( function () { gbk.loaded = false; viewGuestbook(); }, function ( er ) { b.disabled = false; toast( er.message ); } );
		} ); } );
		$$( '[data-gb-rm]' ).forEach( function ( b ) { b.addEventListener( 'click', function () {
			var id = b.dataset.gbRm; b.disabled = true;
			send( 'DELETE', 'guestbook/' + id, null ).then( function () {
				gbk.entries = gbk.entries.filter( function ( x ) { return String( x.id ) !== id; } ); viewGuestbook();
				toast( __( 'Entry removed from your guest book.', 'stubli' ), function () { send( 'POST', 'guestbook/' + id + '/restore', {} ).then( function () { gbk.loaded = false; if ( location.hash === '#guestbook' ) { viewGuestbook(); } toast( __( 'It\'s back in your guest book.', 'stubli' ) ); }, function ( er ) { toast( er.message ); } ); } );
			}, function ( er ) { b.disabled = false; toast( er.message ); } );
		} ); } );
	}

	/* ── Your links: add, change, reorder, remove ── */
	var lks = { loaded: false, list: [], msg: '', dirty: false };
	function viewLinks() {
		if ( ! lks.loaded ) {
			root.innerHTML = arrangeHead( '#you', __( 'Links', 'stubli' ) ) + '<main class="a-main" id="main"><p class="muted" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>';
			Promise.all( [ send( 'GET', 'links', null ), pp.loaded ? Promise.resolve( pp.list ) : send( 'GET', 'follows', null ) ] ).then( function ( r ) { lks.list = r[ 0 ] || []; pp.list = Array.isArray( r[ 1 ] ) ? r[ 1 ] : []; pp.loaded = true; lks.loaded = true; if ( ! lks.list.length ) { lks.list = [ { label: '', url: '' } ]; } if ( location.hash === '#links' ) { viewLinks(); } }, function ( e ) { toast( e.message ); } );
			return;
		}
		var n = lks.list.length;
		root.innerHTML = arrangeHead( '#you', __( 'Links', 'stubli' ) ) + '<main class="a-main" id="main">' +
			'<section class="a-card"><h2>' + __( 'Your links', 'stubli' ) + '</h2><p class="muted">' + __( 'A shop, a newsletter, another website: they show in the Links tab on your page.', 'stubli' ) + '</p>' +
			'<ol class="a-lk">' + lks.list.map( function ( l, i ) {
				return '<li><div class="a-lk-fields"><label class="sr" for="a-lk-l' + i + '">' + _f( __( 'Name of link %s', 'stubli' ), i + 1 ) + '</label><input id="a-lk-l' + i + '" class="a-field" maxlength="60" placeholder="' + esc( __( 'Name, like Shop', 'stubli' ) ) + '" value="' + esc( l.label ) + '" data-lk-label="' + i + '">' +
					'<label class="sr" for="a-lk-u' + i + '">' + _f( __( 'Address of link %s', 'stubli' ), i + 1 ) + '</label><input id="a-lk-u' + i + '" class="a-field" type="url" inputmode="url" placeholder="shop.example.com" value="' + esc( l.url ) + '" data-lk-url="' + i + '"></div>' +
					'<div class="a-lk-btns"><button type="button" class="a-icon-btn" data-lk-move="' + i + '" data-dir="-1"' + ( i === 0 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move link %s up', 'stubli' ), i + 1 ) ) + '">' + icon( 'up', 18 ) + '</button><button type="button" class="a-icon-btn" data-lk-move="' + i + '" data-dir="1"' + ( i === n - 1 ? ' disabled' : '' ) + ' aria-label="' + esc( _f( __( 'Move link %s down', 'stubli' ), i + 1 ) ) + '">' + icon( 'down', 18 ) + '</button><button type="button" class="a-icon-btn" data-lk-rm="' + i + '" aria-label="' + esc( _f( __( 'Remove link %s', 'stubli' ), i + 1 ) ) + '">' + icon( 'x', 18 ) + '</button></div></li>';
			} ).join( '' ) + '</ol>' +
			'<div class="a-actions"><button type="button" class="a-btn quiet" data-lk-add' + ( n >= 20 ? ' disabled' : '' ) + '>' + icon( 'plus', 18 ) + __( 'Add a link', 'stubli' ) + '</button><button type="button" class="a-btn" data-lk-save>' + __( 'Save', 'stubli' ) + '</button></div><p class="a-help" aria-live="polite" data-lk-msg>' + esc( lks.msg ) + '</p></section>' +
			'<section class="a-card"><h2>' + __( 'Sites you follow', 'stubli' ) + '</h2><p class="muted">' + __( 'The ones you follow publicly show under your links. Change who you follow in People.', 'stubli' ) + '</p>' +
			( pp.list.length ? '<ul class="a-lk-sites">' + pp.list.map( function ( f ) { return '<li><strong>' + esc( f.name || hostOf( f.site_url ) ) + '</strong> <span class="muted">' + esc( hostOf( f.site_url ) ) + '</span></li>'; } ).join( '' ) + '</ul>' : '<p class="muted">' + __( 'You don\'t follow anyone yet.', 'stubli' ) + '</p>' ) +
			'<a class="a-btn quiet" href="#discover">' + __( 'Open People', 'stubli' ) + '</a></section></main>';
		lks.msg = '';
		var read = function () { lks.list = lks.list.map( function ( l, i ) { return { label: $( '[data-lk-label="' + i + '"]' ).value, url: $( '[data-lk-url="' + i + '"]' ).value }; } ); };
		$$( '[data-lk-move]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { read(); var i = +b.dataset.lkMove, j = i + ( +b.dataset.dir ), t = lks.list[ i ]; lks.list[ i ] = lks.list[ j ]; lks.list[ j ] = t; viewLinks(); var f = $( '[data-lk-move="' + j + '"][data-dir="' + b.dataset.dir + '"]' ) || $( '[data-lk-move="' + j + '"]' ); if ( f ) { f.focus(); } } ); } );
		$$( '[data-lk-rm]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { read(); lks.list.splice( +b.dataset.lkRm, 1 ); if ( ! lks.list.length ) { lks.list = [ { label: '', url: '' } ]; } viewLinks(); } ); } );
		$( '[data-lk-add]' ).addEventListener( 'click', function () { read(); lks.list.push( { label: '', url: '' } ); viewLinks(); var f = $( '[data-lk-label="' + ( lks.list.length - 1 ) + '"]' ); if ( f ) { f.focus(); } } );
		$( '[data-lk-save]' ).addEventListener( 'click', function ( e ) {
			read(); var b = e.currentTarget; b.disabled = true;
			send( 'POST', 'links', { links: lks.list } ).then( function ( r ) { lks.list = r.length ? r : [ { label: '', url: '' } ]; lks.msg = __( 'Saved. Your Links tab shows them now.', 'stubli' ); viewLinks(); }, function ( er ) { b.disabled = false; $( '[data-lk-msg]' ).textContent = er.message; } );
		} );
	}

	/* ── Your colour and reading mode, any time after setup ── */
	var lk = { msg: '' };
	function viewLook() {
		if ( ! su.d ) {
			root.innerHTML = arrangeHead( '#more', __( 'Colour and reading mode', 'stubli' ) ) + '<main class="a-main" id="main"><p class="muted" role="status">' + __( 'Loading…', 'stubli' ) + '</p></main>';
			send( 'GET', 'setup', null ).then( function ( d ) { su.d = d; if ( location.hash === '#look' ) { viewLook(); } }, function ( e ) { toast( e.message ); } );
			return;
		}
		var d = su.d, a = accentOf( d.accent ), pm = document.documentElement.getAttribute( 'data-mode' ) === 'dark' ? 'dark' : 'light';
		root.innerHTML = arrangeHead( '#more', __( 'Colour and reading mode', 'stubli' ) ) + '<main class="a-main" id="main">' +
			'<p class="a-label" id="a-lk-col">' + _f( __( 'Colour: %s', 'stubli' ), esc( a.name ) ) + '</p><div class="a-swatches" role="radiogroup" aria-labelledby="a-lk-col">' + d.accents.map( function ( c ) {
				var on = c.key === d.accent;
				return '<button type="button" role="radio" aria-checked="' + on + '" aria-label="' + esc( c.name + ( c.key === 'moss' ? __( ', the Stubli green', 'stubli' ) : '' ) ) + '" data-accent="' + c.key + '" tabindex="' + ( on ? 0 : -1 ) + '"><span style="background:' + ( pm === 'dark' ? c.dark : c.hex ) + '"></span><small>' + esc( c.name ) + '</small></button>';
			} ).join( '' ) + '</div>' +
			( d.accent === 'gold' && pm !== 'dark' ? ( '<p class="a-help" role="status">' + __( 'Gold is made for dark pages. On a light page your tabs blend in a little; Dark mode suits it best.', 'stubli' ) + '</p>' ) : '' ) +
			'<p class="a-label" id="a-lk-mode" style="margin-top:20px">' + __( 'Reading mode', 'stubli' ) + '</p><div class="a-chips" role="radiogroup" aria-labelledby="a-lk-mode">' + [ [ 'auto', __( 'Automatic', 'stubli' ) ], [ 'light', __( 'Light', 'stubli' ) ], [ 'paper', __( 'Paper', 'stubli' ) ], [ 'dark', __( 'Dark', 'stubli' ) ] ].map( function ( m ) {
				return '<button type="button" class="a-chip" role="radio" aria-checked="' + ( d.mode === m[ 0 ] ) + '" data-mode-pick="' + m[ 0 ] + '">' + ( d.mode === m[ 0 ] ? icon( 'check', 16 ) : '' ) + m[ 1 ] + '</button>';
			} ).join( '' ) + '</div><p class="a-help">' + esc( MODE_HINTS[ d.mode ] ) + ' ' + __( 'This is how your page opens for visitors. They can still switch with the Aa button.', 'stubli' ) + '</p>' +
			'<label class="a-check" style="margin-top:26px"><span><strong>' + __( 'Little surprises on special days', 'stubli' ) + '</strong><span class="muted small">' + __( 'On a few days a year, the Stubli logo joins in: confetti at New Year, heart eyes on Valentine\'s Day, a pumpkin at Halloween, fairy lights at Christmas, a Swiss flag on 1 August (on Swiss Stublis) and a cupcake on your Stubli\'s birthday.', 'stubli' ) + '</span></span><input type="checkbox" data-lk-season' + ( d.seasonal ? ' checked' : '' ) + '></label>' +
			'<p class="a-help" aria-live="polite" data-lk-msg>' + esc( lk.msg ) + '</p></main>';
		lk.msg = '';
		var save = function ( change, focusSel ) {
			send( 'POST', 'setup', change ).then( function ( nd ) { su.d = nd; applyLook(); lk.msg = __( 'Saved. Your page and the app use it now.', 'stubli' ); viewLook(); var f = $( focusSel ); if ( f ) { f.focus(); } }, function ( e ) { toast( e.message ); } );
		};
		var sw = $$( '[data-accent]' );
		sw.forEach( function ( b, i ) {
			b.addEventListener( 'click', function () { save( { accent: b.dataset.accent }, '[data-accent="' + b.dataset.accent + '"]' ); } );
			b.addEventListener( 'keydown', function ( e ) {
				var k = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ e.key ];
				if ( k ) { e.preventDefault(); var nb = sw[ ( i + k + sw.length ) % sw.length ]; save( { accent: nb.dataset.accent }, '[data-accent="' + nb.dataset.accent + '"]' ); }
			} );
		} );
		$$( '[data-mode-pick]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { save( { mode: b.dataset.modePick }, '[data-mode-pick="' + b.dataset.modePick + '"]' ); } ); } );
		var ss = $( '[data-lk-season]' );
		if ( ss ) {
			ss.addEventListener( 'change', function () {
				var on = ss.checked;
				send( 'POST', 'setup', { seasonal: on } ).then( function ( nd ) {
					su.d = nd;
					if ( ! on ) { B.logo = null; }
					lk.msg = on ? __( 'Little surprises are on.', 'stubli' ) : __( 'Little surprises are off.', 'stubli' );
					viewLook(); var f = $( '[data-lk-season]' ); if ( f ) { f.focus(); }
				}, function ( e ) { ss.checked = ! on; toast( e.message ); } );
			} );
		}
	}
	function ikey( s ) { return String( s ).toLowerCase().replace( /[^\p{L}\p{N}]+/gu, '' ); }
	function initials( n ) { return String( n || '?' ).trim().split( /\s+/ ).slice( 0, 2 ).map( function ( w ) { return w.charAt( 0 ); } ).join( '' ).toUpperCase(); }
	function setupHead() {
		var n = su.step;
		return '<header class="a-head">' + ( n > 0 ? '<button type="button" class="a-text-btn" data-su-back>' + icon( 'back', 20 ) + ( __( 'Back', 'stubli' ) + '</button>' ) : '<span></span>' ) +
			'<h1>' + _f( __( 'Step %s of 5', 'stubli' ), n + 1 ) + ( '</h1><button type="button" class="a-text-btn" data-su-later>' + __( 'Finish later', 'stubli' ) + '</button></header>' ) +
			'<div class="a-su-progress" role="progressbar" aria-label="' + esc( __( 'Setup', 'stubli' ) ) + '" aria-valuemin="1" aria-valuemax="5" aria-valuenow="' + ( n + 1 ) + '" aria-valuetext="' + esc( _f( __( 'Step %s of 5: %s', 'stubli' ), n + 1, STEPS[ n ] ) ) + '">' +
			STEPS.map( function ( s, i ) { return '<span' + ( i <= n ? ' class="is-on"' : '' ) + '></span>'; } ).join( '' ) + '</div>';
	}
	function setupFoot( label ) {
		return ( su.error ? '<div class="a-notice" role="alert"><p>' + esc( su.error ) + '</p></div>' : '' ) +
			'<div class="a-su-foot"><button type="button" class="a-btn wide" data-su-next' + ( su.busy ? ' disabled' : '' ) + '>' + esc( su.busy ? __( 'Saving…', 'stubli' ) : label ) + '</button></div>';
	}
	function suMove() {
		var st = mv.inState;
		if ( st && st.state === 'done' ) {
			return '<section class="a-su-move">' + moveProgress( st ) + ( '<button type="button" class="a-btn wide" data-su-move-go>' + __( 'Continue', 'stubli' ) + '</button></section>' );
		}
		if ( st && st.state === 'moving' ) { return '<section class="a-su-move">' + moveProgress( st ) + '</section>'; }
		return '<section class="a-su-move"><button type="button" class="a-link" data-su-move-open aria-expanded="' + !! su.moveOpen + ( '">' + __( 'Moving here from another Stubli?', 'stubli' ) + '</button>' ) +
			( su.moveOpen ? ( '<p class="muted">' + __( 'On your old Stubli, open You → Settings and more → Move to another site → Make a move link. Paste it here and everything comes along: photos, journal, guest book, people and settings.', 'stubli' ) + '</p><form class="a-inline" data-su-move style="margin-top:0"><label class="sr" for="a-su-mv">' + __( 'Move link', 'stubli' ) + '</label><input id="a-su-mv" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="' + esc( __( 'oldsite.com/?stubli_move=…', 'stubli' ) ) + '"><button type="submit" class="a-btn quiet"' ) + ( mv.busy ? ' disabled' : '' ) + ( '>' + __( 'Start', 'stubli' ) + '</button></form>' ) + ( mv.msg ? '<div class="a-notice" role="alert"><p>' + esc( mv.msg ) + '</p></div>' : '' ) : '' ) + '</section>';
	}
	function bindSuMove() {
		var redraw = function () { if ( location.hash === '#setup' ) { renderSetup(); } };
		var op = $( '[data-su-move-open]' ); if ( op ) { op.addEventListener( 'click', function () { su.moveOpen = ! su.moveOpen; renderSetup(); var f = $( '#a-su-mv' ); if ( f ) { f.focus(); } } ); }
		var fm = $( '[data-su-move]' ); if ( fm ) { fm.addEventListener( 'submit', function ( e ) { e.preventDefault(); var v = $( '#a-su-mv' ).value.trim(); if ( v ) { startMove( v, redraw ).catch( function () {} ); } } ); }
		var go = $( '[data-su-move-go]' ); if ( go ) { go.addEventListener( 'click', function () { send( 'GET', 'setup', null ).then( function ( d ) { su.d = d; su.picked = d.interests.map( function ( i ) { return i.name; } ); B.name = /^(your|my)\b/i.test( d.name ) ? '' : d.name.split( /\s+/ )[ 0 ]; applyLook(); setupGo( 4 ); } ); } ); }
	}
	function smileyUrl( sm, c, x ) { return sm.base + c + ( x ? '-' + x : '' ); }
	function smileyData( sm, c, x ) { var svg = sm.own[ c + '-' + x ] || sm.tpl[ x ].split( '__BG__' ).join( sm.colours[ c ] ); return 'data:image/svg+xml,' + encodeURIComponent( svg ); }
	function smileyPicker( d ) {
		var sm = d.smiley; if ( ! sm ) { return ''; }
		var on = ! d.photo;
		return '<details class="a-su-smiley"' + ( d.smileyOpen ? ' open' : '' ) + '><summary>' + ( on ? __( 'Change your smiley', 'stubli' ) : __( 'Use a smiley instead', 'stubli' ) ) + '</summary>' +
			( '<p class="a-label" id="a-su-sc">' + __( 'Colour', 'stubli' ) + '</p><div class="a-su-sc" role="radiogroup" aria-labelledby="a-su-sc">' ) + sm.colours.map( function ( hex, i ) {
				var sel = on && i === sm.c;
				return '<button type="button" role="radio" aria-checked="' + sel + '" aria-label="' + esc( _f( __( 'Colour %s', 'stubli' ), i + 1 ) ) + '" data-sm-c="' + i + '" tabindex="' + ( ( on ? sel : i === 0 ) ? 0 : -1 ) + '"><span style="background:' + hex + '"></span></button>';
			} ).join( '' ) + '</div>' +
			( '<p class="a-label" id="a-su-sx">' + __( 'Something extra', 'stubli' ) + '</p><div class="a-su-sx" role="radiogroup" aria-labelledby="a-su-sx">' ) + sm.extras.map( function ( e, i ) {
				var sel = on && e.key === sm.x;
				return '<button type="button" role="radio" aria-checked="' + sel + '" data-sm-x="' + esc( e.key ) + '" tabindex="' + ( ( on ? sel : i === 0 ) ? 0 : -1 ) + '"><img src="' + esc( smileyData( sm, sm.c, e.key ) ) + '" alt="" width="44" height="44"><small>' + esc( e.label ) + '</small></button>';
			} ).join( '' ) + '</div></details>';
	}
	function pickSmiley( d, c, x, focusSel, redraw ) {
		var sm = d.smiley; sm.c = c; sm.x = x; d.avatar = smileyData( sm, c, x ); d.photo = false; d.smileyOpen = true; d.photoMsg = '';
		redraw(); var f = $( focusSel ); if ( f ) { f.focus(); }
		send( 'POST', 'setup/smiley', { c: c, x: x } ).then( function ( r ) { d.avatar = r.avatar; }, function () { var m = $( '[data-su-photo-msg]' ); if ( m ) { m.textContent = __( 'That didn\'t save. Try again in a moment.', 'stubli' ); } } );
	}
	function bindSmiley( d, redraw ) {
		var det = $( '.a-su-smiley' ); if ( ! det ) { return; }
		det.addEventListener( 'toggle', function () { d.smileyOpen = det.open; } );
		[ [ '[data-sm-c]', 'smC' ], [ '[data-sm-x]', 'smX' ] ].forEach( function ( g ) {
			var bs = $$( g[ 0 ] );
			bs.forEach( function ( b, i ) {
				var pick = function ( el ) {
					if ( el.dataset.smC !== undefined ) { pickSmiley( d, +el.dataset.smC, d.smiley.x, '[data-sm-c="' + el.dataset.smC + '"]', redraw ); } else { pickSmiley( d, d.smiley.c, el.dataset.smX, '[data-sm-x="' + el.dataset.smX + '"]', redraw ); }
				};
				b.addEventListener( 'click', function () { pick( b ); } );
				b.addEventListener( 'keydown', function ( e ) {
					var k = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ e.key ]; if ( ! k ) { return; }
					e.preventDefault(); pick( bs[ ( i + k + bs.length ) % bs.length ] );
				} );
			} );
		} );
	}
	function renderSetup() {
		var d = su.d, n = su.step, body = '';
		if ( n === 0 ) {
			body = ( '<h2 class="a-su-title">' + __( 'Hello! Whose Stubli is this?', 'stubli' ) + '</h2><p class="muted">' + __( 'This is what people see at the top of your page. You can change it any time.', 'stubli' ) + '</p>' ) +
				'<div class="a-su-photo"><label class="a-su-av" for="a-su-file">' + ( d.avatar ? '<img src="' + esc( d.avatar ) + '" alt="' + ( d.photo ? __( 'Your photo', 'stubli' ) : __( 'Your smiley', 'stubli' ) ) + '">' : '<span aria-hidden="true">' + esc( initials( d.name ) ) + '</span>' ) + '</label>' +
				'<div><label class="a-btn quiet" for="a-su-file">' + icon( 'photo', 18 ) + ( d.photo ? __( 'Change photo', 'stubli' ) : __( 'Add a photo', 'stubli' ) ) + '</label><input id="a-su-file" class="a-file" type="file" accept="image/*"><p class="a-help" aria-live="polite" data-su-photo-msg>' + esc( su.photoMsg || ( d.photo ? __( 'A face, a place, anything that feels like you.', 'stubli' ) : __( 'Optional. Until then, this smiley is you. Make it yours below.', 'stubli' ) ) ) + '</p></div></div>' +
				smileyPicker( d ) +
				( '<label class="a-label" for="a-su-name">' + __( 'Your name', 'stubli' ) + '</label><input id="a-su-name" class="a-field" type="text" maxlength="60" autocomplete="name" placeholder="' + esc( __( 'Ana Keller', 'stubli' ) ) + '" value="' ) + esc( d.name ) + ( '" aria-describedby="a-su-name-h"><p class="a-help" id="a-su-name-h">' + __( 'The name people see on your page.', 'stubli' ) + '</p>' ) +
				( '<label class="a-label" for="a-su-bio">' + __( 'One line about you', 'stubli' ) + '</label><textarea id="a-su-bio" class="a-field a-grow" rows="2" maxlength="160" placeholder="' + esc( __( 'Photos from walks around Zurich', 'stubli' ) ) + '">' ) + esc( d.bio ) + '</textarea><p class="a-count" data-su-bio-count>' + d.bio.length + ' / 160</p>' +
				( '<label class="a-label" for="a-su-pro">' + __( 'Pronouns', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></label><input id="a-su-pro" class="a-field" type="text" maxlength="30" value="' ) + esc( d.pronouns ) + '" placeholder="' + esc( __( 'she/her, they/them…', 'stubli' ) ) + '">' +
				setupFoot( __( 'Next', 'stubli' ) ) + suMove();
		} else if ( n === 1 ) {
			var used = {}, all = [];
			d.interests.forEach( function ( i ) { used[ ikey( i.name ) ] = i.count; all.push( i.name ); } );
			su.picked.concat( d.ideas ).forEach( function ( p ) { if ( all.map( ikey ).indexOf( ikey( p ) ) < 0 ) { all.push( p ); } } );
			body = ( '<h2 class="a-su-title">' + __( 'What do you like to photograph?', 'stubli' ) + '</h2><p class="muted">' + _f( __( 'Each interest becomes a collection tab on your page and a way to sort the people you follow. Pick up to %s. You can add more when you post, and rename them on your You screen.', 'stubli' ), d.max ) ) + '</p>' +
				'<div class="a-chips" role="group" aria-label="' + esc( __( 'Interests', 'stubli' ) ) + '" style="margin-top:16px">' + all.map( function ( p ) {
					var on = su.picked.some( function ( x ) { return ikey( x ) === ikey( p ); } ), has = used[ ikey( p ) ] > 0;
					return '<button type="button" class="a-chip" aria-pressed="' + on + '" data-int="' + esc( p ) + '"' + ( has ? ' aria-describedby="a-su-has"' : '' ) + '>' + ( on ? icon( 'check', 16 ) : '' ) + esc( p ) + '</button>';
				} ).join( '' ) + '</div>' +
				( d.interests.some( function ( i ) { return i.count > 0; } ) ? ( '<p class="a-help" id="a-su-has">' + __( 'Interests that already have photos stay, even if you untick them.', 'stubli' ) + '</p>' ) : '' ) +
				( '<form class="a-inline" data-su-own><label class="sr" for="a-su-own">' + __( 'Add your own interest', 'stubli' ) + '</label><input id="a-su-own" class="a-field" type="text" maxlength="40" placeholder="' + esc( __( 'Add your own', 'stubli' ) ) + '"><button type="submit" class="a-btn quiet">' + __( 'Add', 'stubli' ) + '</button></form>' ) +
				'<p class="a-help" aria-live="polite" data-su-int-msg>' + _f( __( '%s of %s chosen', 'stubli' ), su.picked.length, d.max ) + '</p>' +
				setupFoot( su.picked.length ? __( 'Next', 'stubli' ) : __( 'Skip for now', 'stubli' ) );
		} else if ( n === 2 ) {
			var a = accentOf( d.accent ), pm = d.mode === 'auto' ? ( matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' ) : d.mode;
			var tabs = ( su.picked.length ? su.picked : [ 'Photos' ] ).slice( 0, 3 );
			body = ( '<h2 class="a-su-title">' + __( 'Make it look like you', 'stubli' ) + '</h2><p class="muted">' + __( 'Choose a colour for buttons and tabs, and how your page reads.', 'stubli' ) + '</p>' ) +
				'<div class="a-su-preview" data-mode="' + pm + '" style="--accent:' + ( pm === 'dark' ? a.dark : a.hex ) + ';--accent-ink:' + ( pm === 'dark' ? '#1c1916' : a.ink ) + '" aria-hidden="true">' +
				'<div class="a-su-pv-head"><span class="a-su-pv-av">' + ( d.avatar ? '<img src="' + esc( d.avatar ) + '" alt="">' : '' ) + '</span><span><strong>' + esc( d.name ) + '</strong><small>' + esc( d.bio || __( 'One line about you', 'stubli' ) ) + '</small></span></div>' +
				'<div class="a-su-pv-tabs">' + tabs.map( function ( t, i ) { return '<span' + ( i === 0 ? ' class="is-on"' : '' ) + '>' + esc( t ) + '</span>'; } ).join( '' ) + '</div>' +
				( '<div class="a-su-pv-grid"><span></span><span></span><span></span></div><span class="a-su-pv-btn">' + __( 'Follow', 'stubli' ) + '</span></div>' ) +
				'<p class="a-label" id="a-su-col">' + _f( __( 'Colour: %s', 'stubli' ), esc( a.name ) ) + '</p><div class="a-swatches" role="radiogroup" aria-labelledby="a-su-col">' + d.accents.map( function ( c ) {
					var on = c.key === d.accent;
					return '<button type="button" role="radio" aria-checked="' + on + '" aria-label="' + esc( c.name + ( c.key === 'moss' ? __( ', the Stubli green', 'stubli' ) : '' ) ) + '" data-accent="' + c.key + '" tabindex="' + ( on ? 0 : -1 ) + '"><span style="background:' + ( pm === 'dark' ? c.dark : c.hex ) + '"></span><small>' + esc( c.name ) + '</small></button>';
				} ).join( '' ) + '</div>' +
				( d.accent === 'gold' && pm !== 'dark' ? ( '<p class="a-help" role="status">' + __( 'Gold is made for dark pages. On a light page your tabs blend in a little; Dark mode suits it best.', 'stubli' ) + '</p>' ) : '' ) +
				( '<p class="a-label" id="a-su-mode">' + __( 'Reading mode', 'stubli' ) + '</p><div class="a-chips" role="radiogroup" aria-labelledby="a-su-mode">' ) + [ [ 'auto', __( 'Automatic', 'stubli' ) ], [ 'light', __( 'Light', 'stubli' ) ], [ 'paper', __( 'Paper', 'stubli' ) ], [ 'dark', __( 'Dark', 'stubli' ) ] ].map( function ( m ) {
					return '<button type="button" class="a-chip" role="radio" aria-checked="' + ( d.mode === m[ 0 ] ) + '" data-mode-pick="' + m[ 0 ] + '">' + ( d.mode === m[ 0 ] ? icon( 'check', 16 ) : '' ) + m[ 1 ] + '</button>';
				} ).join( '' ) + '</div><p class="a-help">' + esc( MODE_HINTS[ d.mode ] ) + ( ' ' + __( 'Visitors can still switch with the Aa button.', 'stubli' ) + '</p>' ) +
				( '<p class="a-label" id="a-su-cr" style="margin-top:22px">' + __( 'Help friends find Stubli?', 'stubli' ) + '</p><p class="a-help" style="margin:-2px 0 10px">' + __( 'Show one small line on your page, “Made with Stubli · Make your own”, so visitors can see how to get their own. You can change it any time in Settings.', 'stubli' ) + '</p>' ) +
				'<div class="a-chips" role="radiogroup" aria-labelledby="a-su-cr">' + [ [ true, __( 'Yes, show it', 'stubli' ) ], [ false, __( 'Not now', 'stubli' ) ] ].map( function ( c ) {
					var on = d.credit === c[ 0 ];
					return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-credit-pick="' + ( c[ 0 ] ? 1 : 0 ) + '">' + ( on ? icon( 'check', 16 ) : '' ) + c[ 1 ] + '</button>';
				} ).join( '' ) + '</div>' +
				setupFoot( __( 'Next', 'stubli' ) );
		} else if ( n === 3 ) {
			var opts = [ '' ].concat( su.picked );
			body = ( '<h2 class="a-su-title">' + __( 'Your people', 'stubli' ) + '</h2><p class="muted">' + __( 'Their new photos will be in your Home feed. Nobody is told who else you follow.', 'stubli' ) + '</p>' ) +
				( d.follows.length ? '<ul class="a-su-people">' + d.follows.map( function ( f ) {
					return '<li><span class="a-av a-su-ini" aria-hidden="true">' + esc( initials( f.name || hostOf( f.url ) ) ) + '</span><span class="a-su-who"><strong>' + esc( f.name || hostOf( f.url ) ) + '</strong><small class="muted">' + esc( hostOf( f.url ) ) + '</small>' +
						( su.picked.length ? '<label class="a-su-sel-l" for="a-su-l' + f.id + '">' + __( 'Show under', 'stubli' ) + '</label><select id="a-su-l' + f.id + '" class="a-su-select" data-su-list="' + f.id + '">' + opts.map( function ( o ) { return '<option value="' + esc( o ) + '"' + ( o === f.list ? ' selected' : '' ) + '>' + esc( o || __( 'Just Home', 'stubli' ) ) + '</option>'; } ).join( '' ) + '</select>' : '' ) +
						'</span><span class="a-su-following">' + icon( 'check', 16 ) + ( __( 'Following', 'stubli' ) + '</span></li>' );
				} ).join( '' ) + '</ul>' : ( '<p class="a-su-empty">' + __( 'Nobody yet, and that\'s fine. If a friend sent you an invite link, paste it below and you\'ll follow each other.', 'stubli' ) + '</p>' ) ) +
				( '<label class="a-label" for="a-su-inv">' + __( 'Got an invite link?', 'stubli' ) + '</label><form class="a-inline" data-su-inv style="margin-top:0"><input id="a-su-inv" class="a-field" type="url" inputmode="url" autocomplete="off" placeholder="' + esc( __( 'theirname.com/invite/?t=…', 'stubli' ) ) + '"><button type="submit" class="a-btn quiet">' + __( 'Use link', 'stubli' ) + '</button></form><p class="a-help" aria-live="polite" data-su-inv-msg></p>' ) +
				( su.sugg && su.sugg.length ? ( '<p class="a-label">' + __( 'People they follow', 'stubli' ) + '</p><ul class="a-su-people">' ) + su.sugg.map( function ( s ) {
					var on = !! su.ticks[ s.url ];
					return '<li><button type="button" class="a-su-row" role="checkbox" aria-checked="' + on + '" data-su-tick="' + esc( s.url ) + '">' + ( s.avatar ? '<img class="a-av" src="' + esc( s.avatar ) + '" alt="">' : '<span class="a-av a-su-ini" aria-hidden="true">' + esc( initials( s.name || hostOf( s.url ) ) ) + '</span>' ) +
						'<span class="a-su-who"><strong>' + esc( s.name || hostOf( s.url ) ) + '</strong><small class="muted">' + _f( __( 'Followed by %s', 'stubli' ), esc( viaText( s.via ) ) ) + '</small></span><span class="a-su-box" aria-hidden="true">' + ( on ? icon( 'check', 18 ) : '' ) + '</span></button></li>';
				} ).join( '' ) + '</ul>' : '' ) +
				( '<p class="a-label">' + __( 'Follow someone by address', 'stubli' ) + '</p>' ) + followForm( 'setup' ) +
				( '<p class="a-label">' + __( 'Invite a friend', 'stubli' ) + '</p><p class="muted" style="margin:0 0 8px">' + __( 'Send them a link. Once they\'ve set up their Stubli, you follow each other. Each link works once, for 30 days.', 'stubli' ) + '</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>' + __( 'Create an invite link', 'stubli' ) + '</button>' ) +
				setupFoot( Object.keys( su.ticks ).some( function ( k ) { return su.ticks[ k ]; } ) ? __( 'Follow and continue', 'stubli' ) : __( 'Next', 'stubli' ) );
		} else {
			var ios = /iphone|ipad|ipod/i.test( navigator.userAgent ), times = [];
			for ( var h = 6; h <= 22; h++ ) { times.push( ( h < 10 ? '0' : '' ) + h + ':00' ); }
			if ( times.indexOf( d.digest ) < 0 ) { times.push( d.digest ); times.sort(); }
			body = ( '<h2 class="a-su-title">' + __( 'You\'re ready', 'stubli' ) + '</h2><p class="muted">' + __( 'A few last choices, then your feed.', 'stubli' ) + '</p>' ) +
				( '<label class="a-label" for="a-su-dig">' + __( 'Your daily summary', 'stubli' ) + '</label><p class="muted" style="margin:0 0 8px">' + __( 'At most one a day, and only when something is new: comments, guest book entries and new posts from your people. Never one per post.', 'stubli' ) + '</p>' ) +
				'<select id="a-su-dig" class="a-field a-su-select" data-su-dig>' + times.map( function ( t ) { return '<option' + ( t === d.digest ? ' selected' : '' ) + '>' + t + '</option>'; } ).join( '' ) + '</select>' +
				( pushSupported() && ! iosNeedsInstall() ? '<div class="a-actions" style="margin-top:10px">' + ( ds.on ? '<p class="a-ds-status">' + icon( 'check', 16 ) + ( __( 'It will arrive on this phone', 'stubli' ) + '</p>' ) : '<button type="button" class="a-btn quiet" data-su-push' + ( ds.busy ? ' disabled' : '' ) + '>' + ( ds.busy ? __( 'Asking your phone…', 'stubli' ) : __( 'Send it to this phone', 'stubli' ) ) + '</button>' ) + '</div>' : ( '<p class="a-help">' + __( 'You can turn on phone notifications later in You → Settings and more → Daily summary.', 'stubli' ) + '</p>' ) ) +
				( ds.msg ? '<p class="a-help" role="status">' + esc( ds.msg ) + '</p>' : '' ) +
				( isStandalone() ? '' : ( '<p class="a-label">' + __( 'Put Stubli on your home screen', 'stubli' ) + '</p><div class="a-su-hs"><section' ) + ( ios ? ' class="is-you"' : '' ) + ( '><strong>iPhone</strong><p class="muted">' + __( 'In Safari, tap the Share button, then', 'stubli' ) + ' <strong>' + __( 'Add to Home Screen', 'stubli' ) + '</strong>.</p></section><section' ) + ( ios ? '' : ' class="is-you"' ) + ( '><strong>' + __( 'Android', 'stubli' ) + '</strong><p class="muted">' + __( 'Open the browser menu ⋮, then', 'stubli' ) + ' <strong>' + __( 'Add to Home screen', 'stubli' ) + '</strong> or <strong>' + __( 'Install app', 'stubli' ) + '</strong>.</p></section></div><p class="a-help">' + __( 'It opens like an app, without the browser around it.', 'stubli' ) + '</p>' ) ) +
				( '<p class="a-label">' + __( 'Your site', 'stubli' ) + '</p>' ) +
				( '<label class="a-check"><span><strong>' + __( 'Keep my site out of search engines', 'stubli' ) + '</strong><span class="muted small">' + __( 'Google and others won\'t list it. Friends still find you through Stubli.', 'stubli' ) + '</span></span><input type="checkbox" data-su-hidden' ) + ( d.hidden ? ' checked' : '' ) + '></label>' +
				( '<label class="a-check"><span><strong>' + __( 'Ask AI crawlers to stay away', 'stubli' ) + '</strong><span class="muted small">' + __( 'A request that respectable bots honour.', 'stubli' ) + '</span></span><input type="checkbox" data-su-ai' ) + ( d.block_ai ? ' checked' : '' ) + '></label>' +
				'<p class="a-help">' + ( d.helper === 'active' ? __( 'Stubli also turns on comments, so replies from friends\' sites can arrive.', 'stubli' ) : __( 'Stubli also turns on comments and adds the free Webmention plugin from WordPress.org, so replies from friends\' sites can arrive.', 'stubli' ) ) + ( ' ' + __( 'You can change all of this later in Settings → Stubli.', 'stubli' ) + '</p>' ) +
				( su.helperFail ? '<div class="a-notice" role="alert"><p>' + esc( su.helperFail ) + ' <a href="' + esc( d.manual ) + ( '">' + __( 'Add Webmention in Plugins', 'stubli' ) + '</a></p></div>' ) : '' ) +
				'<p class="a-su-note">' + _f( __( 'Your first photo can wait. When you\'re ready, tap %s at the bottom.', 'stubli' ), icon( 'plus', 18 ) ) + '</p>' +
				setupFoot( su.helperFail ? __( 'Open my feed anyway', 'stubli' ) : ( su.busy && su.helperBusy ? __( 'Adding Webmention…', 'stubli' ) : __( 'Open my feed', 'stubli' ) ) );
		}
		root.innerHTML = setupHead() + '<main class="a-compose a-su" id="main">' + body + '</main>';
		bindSetup();
		var h1 = $( '.a-su-title' ); if ( h1 && su.focus ) { h1.setAttribute( 'tabindex', '-1' ); h1.focus(); su.focus = false; }
	}
	function setupSave( changes ) {
		su.busy = true; su.error = ''; renderSetup();
		return send( 'POST', 'setup', changes ).then( function ( d ) { su.d = d; su.busy = false; return d; }, function ( e ) { su.busy = false; su.error = e.message; renderSetup(); return Promise.reject( e ); } );
	}
	function setupGo( n ) { su.step = n; su.error = ''; su.focus = true; window.scrollTo( 0, 0 ); renderSetup(); if ( n === 3 && ! su.sugg ) { loadSugg(); } }
	function loadSugg() {
		su.sugg = [];
		send( 'GET', 'discover', null ).then( function ( r ) { su.sugg = ( r && r.suggestions || r || [] ).slice( 0, 6 ); if ( su.step === 3 && location.hash === '#setup' ) { renderSetup(); } }, function () {} );
	}
	function bindSetup() {
		var d = su.d, back = $( '[data-su-back]' ), later = $( '[data-su-later]' ), next = $( '[data-su-next]' );
		var bioEl = $( '#a-su-bio' );
		if ( bioEl ) {
			var grow = function () { bioEl.style.height = 'auto'; bioEl.style.height = bioEl.scrollHeight + 'px'; };
			bioEl.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); } } );
			bioEl.addEventListener( 'input', function () { if ( /\n/.test( bioEl.value ) ) { bioEl.value = bioEl.value.replace( /\s*\n+\s*/g, ' ' ); } grow(); } );
			grow();
		}
		if ( back ) { back.addEventListener( 'click', function () { setupGo( su.step - 1 ); } ); }
		later.addEventListener( 'click', function () {
			store.set( 'stubli-setup-later', '1' );
			send( 'POST', 'setup', { step: su.step } ).catch( function () {} );
			location.hash = '#home';
		} );
		if ( su.step === 0 ) {
			bindSuMove();
			var bio = $( '#a-su-bio' ), cnt = $( '[data-su-bio-count]' ), file = $( '#a-su-file' );
			[ 'a-su-name', 'a-su-bio', 'a-su-pro' ].forEach( function ( id ) {
				$( '#' + id ).addEventListener( 'input', function ( e ) { d[ { 'a-su-name': 'name', 'a-su-bio': 'bio', 'a-su-pro': 'pronouns' }[ id ] ] = e.target.value; if ( su.error && id === 'a-su-name' && e.target.value.trim() ) { su.error = ''; var nt = $( '.a-su .a-notice' ); if ( nt ) { nt.remove(); } } } );
			} );
			bio.addEventListener( 'input', function () { cnt.textContent = bio.value.length + ' / 160'; } );
			bindSmiley( d, renderSetup );
			file.addEventListener( 'change', function () {
				var f = file.files && file.files[ 0 ], m = $( '[data-su-photo-msg]' );
				if ( ! f ) { return; }
				m.textContent = __( 'Uploading…', 'stubli' );
				squareAvatar( f ).then( function ( blob ) {
					var fd = new FormData(); fd.append( 'photo', blob, 'profile.jpg' );
					return send( 'POST', 'setup/photo', fd );
				} ).then( function ( r ) { d.avatar = r.avatar; d.photo = true; d.smileyOpen = false; su.photoMsg = __( 'Lovely. That\'s your photo now.', 'stubli' ); renderSetup(); },
					function ( e ) { m.textContent = ( e && e.message ) || __( 'That photo couldn\'t be read. Try another one.', 'stubli' ); } );
			} );
			next.addEventListener( 'click', function () {
				if ( ! d.name.trim() ) { su.error = __( 'Add the name people should see.', 'stubli' ); renderSetup(); $( '#a-su-name' ).focus(); return; }
				setupSave( { name: d.name, bio: d.bio, pronouns: d.pronouns, step: 1 } ).then( function () { B.name = /^(your|my)\b/i.test( d.name.trim() ) ? '' : d.name.trim().split( /\s+/ )[ 0 ]; setupGo( 1 ); } );
			} );
		} else if ( su.step === 1 ) {
			var msg = $( '[data-su-int-msg]' );
			$$( '[data-int]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var v = b.dataset.int, i = su.picked.map( ikey ).indexOf( ikey( v ) );
					if ( i > -1 ) { su.picked.splice( i, 1 ); } else if ( su.picked.length >= d.max ) { msg.textContent = _f( __( 'That\'s %s already. Untick one to choose another.', 'stubli' ), d.max ); return; } else { su.picked.push( v ); }
					renderSetup(); var again = $( '[data-int="' + v.replace( /"/g, '\\"' ) + '"]' ); if ( again ) { again.focus(); }
				} );
			} );
			$( '[data-su-own]' ).addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var inp = $( '#a-su-own' ), v = inp.value.trim().replace( /\s+/g, ' ' );
				if ( ! v ) { inp.focus(); return; }
				if ( su.picked.length >= d.max ) { msg.textContent = _f( __( 'That\'s %s already. Untick one to add another.', 'stubli' ), d.max ); return; }
				if ( ! su.picked.some( function ( x ) { return ikey( x ) === ikey( v ); } ) ) { su.picked.push( v ); }
				renderSetup(); $( '#a-su-own' ).focus();
			} );
			next.addEventListener( 'click', function () {
				setupSave( { interests: su.picked, step: 2 } ).then( function ( r ) { su.picked = r.interests.map( function ( i ) { return i.name; } ); B.collections = su.picked.slice(); setupGo( 2 ); } );
			} );
		} else if ( su.step === 2 ) {
			var sw = $$( '[data-accent]' );
			sw.forEach( function ( b, i ) {
				b.addEventListener( 'click', function () { d.accent = b.dataset.accent; renderSetup(); $( '[data-accent="' + d.accent + '"]' ).focus(); } );
				b.addEventListener( 'keydown', function ( e ) {
					var k = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ e.key ];
					if ( ! k ) { return; }
					e.preventDefault(); var nb = sw[ ( i + k + sw.length ) % sw.length ]; d.accent = nb.dataset.accent; renderSetup(); $( '[data-accent="' + d.accent + '"]' ).focus();
				} );
			} );
			$$( '[data-mode-pick]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.mode = b.dataset.modePick; renderSetup(); $( '[data-mode-pick="' + d.mode + '"]' ).focus(); } ); } );
			$$( '[data-credit-pick]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.credit = b.dataset.creditPick === '1'; renderSetup(); $( '[data-credit-pick="' + b.dataset.creditPick + '"]' ).focus(); } ); } );
			next.addEventListener( 'click', function () {
				var c = { accent: d.accent, mode: d.mode, step: 3 };
				if ( d.credit === true || d.credit === false ) { c.credit = d.credit; } // No answer: stays off.
				setupSave( c ).then( function () { applyLook(); setupGo( 3 ); } );
			} );
		} else if ( su.step === 3 ) {
			bindFollow(); bindInvite();
			$$( '[data-su-list]' ).forEach( function ( s ) {
				s.addEventListener( 'change', function () {
					var id = +s.dataset.suList;
					send( 'PATCH', 'follows/' + id, { list_name: s.value } ).then( function () { d.follows.forEach( function ( f ) { if ( f.id === id ) { f.list = s.value; } } ); feed.loaded = false; }, function () {} );
				} );
			} );
			$$( '[data-su-tick]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var u = b.dataset.suTick; su.ticks[ u ] = ! su.ticks[ u ]; renderSetup(); $( '[data-su-tick="' + u + '"]' ).focus(); } ); } );
			$( '[data-su-inv]' ).addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var inp = $( '#a-su-inv' ), m = $( '[data-su-inv-msg]' ), btn = e.target.querySelector( 'button' ), v = inp.value.trim();
				if ( ! v ) { inp.focus(); return; }
				if ( ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				btn.disabled = true; btn.textContent = __( 'Using…', 'stubli' ); m.textContent = '';
				send( 'POST', 'setup/invite', { link: v } ).then( function ( r ) {
					d.follows = r.follows; feed.loaded = false; su.sugg = null; loadSugg();
					var who = r.result && r.result.name || __( 'them', 'stubli' );
					su.invMsg = r.result && r.result.followed_back ? _f( __( 'You and %s now follow each other.', 'stubli' ), who ) : _f( __( 'You\'re following %s. They\'ll follow you back once their site hears from yours.', 'stubli' ), who );
					renderSetup(); var mm = $( '[data-su-inv-msg]' ); if ( mm ) { mm.textContent = su.invMsg; }
				}, function ( er ) { btn.disabled = false; btn.textContent = __( 'Use link', 'stubli' ); m.textContent = er.message; } );
			} );
			next.addEventListener( 'click', function () {
				var urls = Object.keys( su.ticks ).filter( function ( k ) { return su.ticks[ k ]; } );
				su.busy = true; renderSetup();
				urls.reduce( function ( p, u ) { return p.then( function () { return send( 'POST', 'follows', { url: u } ).catch( function () {} ); } ); }, Promise.resolve() ).then( function () {
					if ( urls.length ) { feed.loaded = false; su.ticks = {}; su.sugg = null; }
					return setupSave( { step: 4 } );
				} ).then( function () { setupGo( 4 ); }, function () {} );
			} );
		} else {
			var dig = $( '[data-su-dig]' );
			dig.addEventListener( 'change', function () { d.digest = dig.value; } );
			var hid = $( '[data-su-hidden]' ), ai = $( '[data-su-ai]' );
			hid.addEventListener( 'change', function () { d.hidden = hid.checked; } );
			ai.addEventListener( 'change', function () { d.block_ai = ai.checked; } );
			var sp = $( '[data-su-push]' );
			if ( sp ) {
				sp.addEventListener( 'click', function () {
					ds.busy = true; ds.msg = ''; renderSetup();
					enablePush().then( function () { ds.busy = false; ds.msg = ''; renderSetup(); }, function ( e ) { ds.busy = false; ds.msg = ( e && e.message ) || __( 'That didn\'t work. You can try again later in You → Settings and more → Daily summary.', 'stubli' ); renderSetup(); } );
				} );
			}
			next.addEventListener( 'click', function () {
				var finish = function () {
					setupSave( { digest: d.digest, hidden: !! d.hidden, block_ai: !! d.block_ai, done: true } ).then( function () { su.helperFail = ''; B.setupDone = true; store.del( 'stubli-setup-later' ); feed.loaded = false; location.hash = '#home'; } );
				};
				if ( d.helper === 'active' || su.helperFail ) { return finish(); }
				// Add the Webmention plugin (from WordPress.org) so comments can travel between sites.
				su.busy = true; su.helperBusy = true; renderSetup();
				send( 'POST', 'setup/webmention', {} ).then( function ( r ) {
					su.busy = false; su.helperBusy = false; d.helper = ( r && r.helper ) || 'active'; finish();
				}, function ( e ) {
					su.busy = false; su.helperBusy = false; su.helperFail = ( e && e.message ) || __( 'Webmention couldn\'t be added just now.', 'stubli' ); renderSetup();
				} );
			} );
		}
	}
	// Cover photo editor: drag to move, zoom, turn a quarter at a time. What's
	// inside the 3:1 frame is what goes on your page.
	function coverEditor() {
		var c = ab.cc;
		return '<div class="a-cc">' +
			'<div class="a-cc-frame" tabindex="0" role="application" aria-label="' + esc( __( 'Cover photo: drag to move it. Arrow keys work too.', 'stubli' ) ) + '"><canvas data-cc-canvas></canvas><span class="a-cc-grid" aria-hidden="true"></span></div>' +
			'<div class="a-cc-tools"><label class="a-cc-zoom">' + icon( 'zoom', 18 ) + '<span class="sr">' + __( 'Zoom', 'stubli' ) + '</span><input type="range" min="1" max="3" step="0.01" value="' + c.zoom + '" data-cc-zoom aria-label="' + esc( __( 'Zoom', 'stubli' ) ) + '"></label>' +
			'<button type="button" class="a-btn quiet" data-cc-rot aria-label="' + esc( __( 'Turn a quarter', 'stubli' ) ) + '">' + icon( 'rotate', 18 ) + __( 'Turn', 'stubli' ) + '</button></div>' +
			'<p class="a-help">' + __( 'Drag the photo to choose what shows. Use the slider to zoom.', 'stubli' ) + '</p>' +
			'<div class="a-actions"><button type="button" class="a-btn" data-cc-use>' + __( 'Use this', 'stubli' ) + '</button><button type="button" class="a-text-btn" data-cc-cancel>' + __( 'Cancel', 'stubli' ) + '</button></div>' +
			'</div>';
	}
	// Where the picture sits: its size at this zoom and turn, and the move kept
	// inside the frame so no empty edge ever shows.
	function coverFit( c, W, H ) {
		var d = dims( c.img ), turned = c.rot % 180 !== 0, iw = turned ? d.h : d.w, ih = turned ? d.w : d.h;
		var s = Math.max( W / iw, H / ih ) * c.zoom, mx = Math.max( 0, ( iw * s - W ) / 2 ), my = Math.max( 0, ( ih * s - H ) / 2 );
		c.x = Math.max( -mx, Math.min( mx, c.x ) ); c.y = Math.max( -my, Math.min( my, c.y ) );
		return { s: s, d: d };
	}
	function coverPaint( c, canvas, W, H, k ) {
		var f = coverFit( c, W, H ), g = canvas.getContext( '2d' );
		g.setTransform( 1, 0, 0, 1, 0, 0 ); g.clearRect( 0, 0, canvas.width, canvas.height );
		g.imageSmoothingQuality = 'high';
		g.translate( ( W / 2 + c.x ) * k, ( H / 2 + c.y ) * k ); g.rotate( c.rot * Math.PI / 180 ); g.scale( f.s * k, f.s * k );
		g.drawImage( c.img, -f.d.w / 2, -f.d.h / 2 );
	}
	function bindCoverEditor() {
		var c = ab.cc; if ( ! c ) { return; }
		var frame = $( '.a-cc-frame' ), cv = $( '[data-cc-canvas]' ), dpr = Math.min( 2, window.devicePixelRatio || 1 );
		var W = frame.clientWidth, H = W / 3;
		cv.width = Math.round( W * dpr ); cv.height = Math.round( H * dpr ); cv.style.width = W + 'px'; cv.style.height = H + 'px';
		var paint = function () { coverPaint( c, cv, W, H, dpr ); };
		paint();
		var drag = null;
		frame.addEventListener( 'pointerdown', function ( e ) { drag = { x: e.clientX, y: e.clientY, cx: c.x, cy: c.y }; frame.setPointerCapture( e.pointerId ); frame.classList.add( 'is-dragging' ); } );
		frame.addEventListener( 'pointermove', function ( e ) { if ( ! drag ) { return; } c.x = drag.cx + e.clientX - drag.x; c.y = drag.cy + e.clientY - drag.y; paint(); } );
		var stop = function () { drag = null; frame.classList.remove( 'is-dragging' ); };
		frame.addEventListener( 'pointerup', stop ); frame.addEventListener( 'pointercancel', stop );
		frame.addEventListener( 'keydown', function ( e ) {
			var step = e.shiftKey ? 30 : 10, k = { ArrowLeft: [ step, 0 ], ArrowRight: [ -step, 0 ], ArrowUp: [ 0, step ], ArrowDown: [ 0, -step ] }[ e.key ];
			if ( k ) { e.preventDefault(); c.x += k[ 0 ]; c.y += k[ 1 ]; paint(); }
		} );
		frame.addEventListener( 'wheel', function ( e ) { e.preventDefault(); c.zoom = Math.max( 1, Math.min( 3, c.zoom - e.deltaY * 0.002 ) ); $( '[data-cc-zoom]' ).value = c.zoom; paint(); }, { passive: false } );
		$( '[data-cc-zoom]' ).addEventListener( 'input', function ( e ) { c.zoom = +e.target.value; paint(); } );
		$( '[data-cc-rot]' ).addEventListener( 'click', function () { c.rot = ( c.rot + 90 ) % 360; c.x = 0; c.y = 0; paint(); } );
		$( '[data-cc-cancel]' ).addEventListener( 'click', function () { if ( c.img.close ) { c.img.close(); } ab.cc = null; renderAbout(); } );
		$( '[data-cc-use]' ).addEventListener( 'click', function ( e ) {
			// As sharp as the photo allows inside the frame, at most 1800 × 600.
			var btn = e.currentTarget, out = document.createElement( 'canvas' ), ow = Math.max( 600, Math.min( 1800, Math.round( W / coverFit( c, W, H ).s ) ) );
			out.width = ow; out.height = Math.round( ow / 3 );
			coverPaint( c, out, W, H, ow / W );
			btn.disabled = true; btn.textContent = __( 'Uploading…', 'stubli' );
			toBlob( out, 0.86 ).then( function ( blob ) { var fd = new FormData(); fd.append( 'cover', blob, 'cover-' + Date.now() + '.jpg' ); return send( 'POST', 'setup/cover', fd ); } )
				.then( function ( r ) { if ( c.img.close ) { c.img.close(); } ab.cc = null; ab.d.cover = r.cover; ab.d.coverSeen = Date.now(); ab.d.coverMsg = __( 'Lovely. It\'s on your page now.', 'stubli' ); me.p = null; renderAbout(); },
					function ( err ) { btn.disabled = false; btn.textContent = __( 'Use this', 'stubli' ); toast( ( err && err.message ) || __( 'That didn\'t upload. Try again when you\'re ready.', 'stubli' ) ); } );
		} );
	}
	// Cover photo: the middle of the picture at 3:1, at most 1800 × 600, re-drawn
	// so nothing but the pixels travels (no location details).
	function wideCover( file ) {
		return loadImage( file ).then( function ( img ) {
			var dm = dims( img ), w = dm.w, h = Math.round( dm.w / 3 );
			if ( h > dm.h ) { h = dm.h; w = Math.round( dm.h * 3 ); }
			var ow = Math.min( 1800, w ), oh = Math.round( ow / 3 );
			var c = draw( img, ( dm.w - w ) / 2, ( dm.h - h ) / 2, w, h, ow, oh );
			if ( img.close ) { img.close(); }
			return toBlob( c, 0.86 );
		} );
	}
	function squareAvatar( file ) {
		return loadImage( file ).then( function ( img ) {
			var dm = dims( img ), s = Math.min( dm.w, dm.h ), out = Math.min( 512, s );
			var c = draw( img, ( dm.w - s ) / 2, ( dm.h - s ) / 2, s, s, out, out );
			if ( img.close ) { img.close(); }
			return toBlob( c, 0.88 );
		} );
	}
	function setupCard() {
		if ( B.setupDone ) { return ''; }
		var n = ( su.d && su.d.step ) || 0;
		return ( '<section class="a-card"><h2>' + __( 'Finish setting up', 'stubli' ) + '</h2><p class="muted">' ) + ( n ? _f( __( 'You\'re on step %s of 5.', 'stubli' ), n + 1 ) + ' ' : '' ) + ( __( 'Your name, interests, colour and people. About three minutes, whenever suits you.', 'stubli' ) + '</p><a class="a-btn" href="#setup">' + __( 'Continue setup', 'stubli' ) + '</a></section>' );
	}

	/* ── Composer step 1: choose and crop ── */
	function seg( active ) {
		return '<div class="a-seg" role="group" aria-label="' + esc( __( 'What to post', 'stubli' ) ) + '"><button type="button" data-go="#new" aria-pressed="' + ( active === 'photo' ) + ( '">' + __( 'Photo', 'stubli' ) + '</button><button type="button" data-go="#journal" aria-pressed="' ) + ( active === 'journal' ) + ( '">' + __( 'Journal', 'stubli' ) + '</button></div>' );
	}
	function viewPick() {
		var p = post, cur = p.photos[ p.current ];
		var ratioLabel = { portrait: __( 'Portrait', 'stubli' ), square: __( 'Square', 'stubli' ), landscape: __( 'Landscape', 'stubli' ) };
		root.innerHTML = ( '<header class="a-head"><button type="button" class="a-text-btn" data-cancel>' + __( 'Cancel', 'stubli' ) + '</button>' ) + seg( 'photo' ) +
			'<button type="button" class="a-btn" data-next' + ( p.photos.length ? '' : ' disabled' ) + ( '>' + __( 'Next', 'stubli' ) + '</button></header>' ) +
			( '<main class="a-compose" id="main"><h1 class="sr">' + __( 'New photo post', 'stubli' ) + '</h1>' ) +
			'<div class="a-preview">' + ( p.loading ? ( '<p class="muted" role="status">' + __( 'Opening your video…', 'stubli' ) + '</p>' ) : cur ? ( cur.video && cur.plain ? '<div class="a-preview-frame" style="aspect-ratio:' + ( cur.w / cur.h ) + '"><img src="' + cur.thumb + '" alt="' + esc( _f( __( 'Video %s, %s', 'stubli' ), p.current + 1, fmtDur( cur.duration ) ) ) + ( '"></div><p class="a-help">' + __( 'This browser can\'t preview this video, but it shares fine.', 'stubli' ) + '</p>' ) : cur.video ? '<div class="a-preview-frame" style="aspect-ratio:' + ( cur.w / cur.h ) + '"><video src="' + cur.src + '" poster="' + cur.thumb + '" controls playsinline preload="metadata" aria-label="' + esc( _f( __( 'Preview of video %s', 'stubli' ), p.current + 1 ) ) + '"></video></div>' : '<div class="a-preview-frame" style="aspect-ratio:' + RATIOS[ p.ratio ] + '"><img src="' + cur.thumb + '" alt="' + esc( _f( __( 'Preview of photo %s', 'stubli' ), p.current + 1 ) ) + '"></div>' ) :
				( ( B.videoTypes || [] ).length ?
					'<div class="a-preview-empty"><p>' + _f( __( 'Choose up to %s photos or videos for one post. Videos can be up to a minute.', 'stubli' ), B.maxPhotos ) + '</p><label class="a-btn">' + icon( 'photo', 18 ) + ( __( 'Choose photos or videos', 'stubli' ) + '<input class="a-file" type="file" accept="' + pickAccept() + '" multiple data-files></label></div>' ) :
					'<div class="a-preview-empty"><p>' + _f( __( 'Choose up to %s photos for one post.', 'stubli' ), B.maxPhotos ) + '</p><label class="a-btn">' + icon( 'photo', 18 ) + ( __( 'Choose photos', 'stubli' ) + '<input class="a-file" type="file" accept="' + pickAccept() + '" multiple data-files></label><p class="a-help" data-novideo>' ) + esc( B.noVideo || '' ) + '</p></div>' ) ) + '</div>' +
			( p.photos.length ? '<div class="a-ratios" role="group" aria-label="' + esc( __( 'Shape', 'stubli' ) ) + '">' + Object.keys( RATIOS ).map( function ( k ) {
				return '<button type="button" class="a-chip" data-ratio="' + k + '" aria-pressed="' + ( p.ratio === k ) + '">' + icon( k, 16 ) + ratioLabel[ k ] + '</button>';
			} ).join( '' ) + '</div>' : '' ) +
			( p.photos.some( function ( x ) { return x.video; } ) ? ( '<p class="a-help" style="margin:-8px 0 12px">' + __( 'The shape applies to photos. Videos keep their own shape.', 'stubli' ) + '</p>' ) : '' ) +
			( '<div class="a-row"><h2 class="a-label">' + __( 'Your photos', 'stubli' ) + '</h2><span class="muted small" aria-live="polite">' ) + ( p.photos.length ? _f( __( '%s selected · up to %s', 'stubli' ), p.photos.length, B.maxPhotos ) : _f( __( 'up to %s', 'stubli' ), B.maxPhotos ) ) + '</span></div>' +
			'<div class="a-thumbs">' + p.photos.map( function ( ph, i ) {
				return '<div style="position:relative"><button type="button" class="a-thumb' + ( i === p.current ? ' is-current' : '' ) + '" data-pick="' + i + '" aria-label="' + esc( ph.video ? _f( __( 'Show video %s', 'stubli' ), i + 1 ) : _f( __( 'Show photo %s', 'stubli' ), i + 1 ) ) + '"><img src="' + ph.thumb + '" alt="">' + ( ph.video ? '<span class="a-vid-dur" aria-hidden="true">' + icon( 'play', 12 ) + fmtDur( ph.duration ) + '</span>' : '' ) + '<span class="a-order" aria-hidden="true">' + ( i + 1 ) + '</span></button>' +
					'<button type="button" class="a-remove" data-remove="' + i + '" aria-label="' + esc( ph.video ? _f( __( 'Remove video %s', 'stubli' ), i + 1 ) : _f( __( 'Remove photo %s', 'stubli' ), i + 1 ) ) + '">' + icon( 'x', 16 ) + '</button></div>';
			} ).join( '' ) +
			( p.photos.length < B.maxPhotos ? '<label class="a-thumb a-add">' + icon( 'plus', 24 ) + '<span>' + ( p.photos.length ? __( 'Add more', 'stubli' ) : _x( 'Choose', 'button', 'stubli' ) ) + '</span><input class="a-file" type="file" accept="' + pickAccept() + '" multiple data-files></label>' : '' ) +
			'</div>' + ( p.error ? '<div class="a-notice" role="alert"><p>' + esc( p.error ) + '</p></div>' : '' ) +
			'<p class="a-lock">' + icon( 'lock', 14 ) + ( __( 'Location details are removed from your photos and videos before they\'re shared.', 'stubli' ) + '</p></main>' );
		p.error = '';
		bindCommon();
		$$( '[data-files]' ).forEach( function ( inp ) { inp.addEventListener( 'change', function () { addFiles( inp.files ); } ); } );
		$$( '[data-ratio]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { p.ratio = b.dataset.ratio; viewPick(); } ); } );
		$$( '[data-pick]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { p.current = +b.dataset.pick; viewPick(); } ); } );
		$$( '[data-remove]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var i = +b.dataset.remove; URL.revokeObjectURL( p.photos[ i ].thumb ); if ( p.photos[ i ].src ) { URL.revokeObjectURL( p.photos[ i ].src ); } p.photos.splice( i, 1 );
				p.current = Math.max( 0, Math.min( p.current, p.photos.length - 1 ) ); viewPick();
			} );
		} );
		var next = $( '[data-next]' );
		next.addEventListener( 'click', function () { if ( p.photos.length ) { location.hash = '#new/details'; } } );
	}
	// Read chosen files into photo entries (thumbnail, or video details), leaving out
	// what can't be used and saying why. Used by the composer and the post editor.
	function prepFiles( files, onVideo ) {
		var bad = 0, notes = [];
		// Videos the host can't take are left out before anything is read or written.
		if ( files.some( function ( f ) { return isVideo( f ) && ! canVideo( videoType( f ) ); } ) ) {
			files = files.filter( function ( f ) { return ! isVideo( f ) || canVideo( videoType( f ) ); } );
			notes.push( B.noVideo || '' );
		}
		if ( onVideo && files.some( isVideo ) ) { onVideo(); }
		return Promise.all( files.map( function ( f ) {
			if ( isVideo( f ) ) {
				if ( B.maxUpload && f.size > B.maxUpload ) { notes.push( _f( __( 'This video is %s, and your host accepts up to %s per file. Try a shorter clip.', 'stubli' ), fmtMB( f.size ), fmtMB( B.maxUpload ) ) ); return null; }
				return videoInfo( f ).catch( function () { return videoInfoFromFile( f ); } ).then( function ( v ) {
					if ( v.duration > MAX_VIDEO + 0.5 ) { URL.revokeObjectURL( v.url ); URL.revokeObjectURL( v.thumb ); notes.push( _f( __( 'This video is %s long. Videos can be up to a minute; trim it in your phone\'s Photos app, then choose it again.', 'stubli' ), fmtDur( v.duration ) ) ); return null; }
					return { file: f, thumb: v.thumb, alt: '', video: true, src: v.url, duration: v.duration, poster: v.poster, w: v.w, h: v.h };
				}, function () { notes.push( __( 'That video couldn\'t be opened here. Try an MP4 or MOV from your phone\'s camera.', 'stubli' ) ); return null; } );
			}
			return makeThumb( f ).then( function ( t ) { return { file: f, thumb: t, alt: '' }; }, function () { bad++; return null; } );
		} ) ).then( function ( got ) {
			if ( bad ) { notes.push( ( bad === 1 ? __( 'One photo couldn\'t be opened. Try a JPEG or PNG, or pick it again.', 'stubli' ) : _f( __( '%s photos couldn\'t be opened. Try a JPEG or PNG, or pick it again.', 'stubli' ), bad ) ) ); }
			return { got: got.filter( Boolean ), notes: notes };
		} );
	}
	function addFiles( list ) {
		var p = post, files = Array.prototype.slice.call( list || [] ), room = B.maxPhotos - p.photos.length;
		if ( files.length > room ) { p.error = _f( __( 'A post holds up to %s photos, so only the first %s were added.', 'stubli' ), B.maxPhotos, room ); files = files.slice( 0, room ); }
		prepFiles( files, function () { p.loading = true; viewPick(); } ).then( function ( r ) {
			p.loading = false;
			r.got.forEach( function ( g ) { p.photos.push( g ); } );
			if ( r.notes.length ) { p.error = ( p.error ? p.error + ' ' : '' ) + r.notes.join( ' ' ); }
			p.current = Math.max( 0, p.photos.length - 1 );
			viewPick();
		} );
	}

	/* ── Composer step 2: words, collection and care ── */
	function viewDetails() {
		var p = post, cols = ( B.collections || [] ).slice();
		if ( p.collection && cols.indexOf( p.collection ) < 0 ) { cols.push( p.collection ); }
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#new" aria-label="' + esc( __( 'Back to photos', 'stubli' ) ) + '">' + icon( 'back', 22 ) + ( '</a><h1>' + __( 'New post', 'stubli' ) + '</h1>' ) +
			'<button type="button" class="a-btn" data-share' + ( p.busy ? ' disabled' : '' ) + '>' + ( p.busy ? __( 'Sharing…', 'stubli' ) : __( 'Share', 'stubli' ) ) + '</button></header>' +
			'<main class="a-compose" id="main">' +
			'<div class="a-strip">' + p.photos.map( function ( ph, i ) { return '<img src="' + ph.thumb + '" alt="' + ( ph.video ? ( __( 'Video', 'stubli' ) + ' ' ) : ( __( 'Photo', 'stubli' ) + ' ' ) ) + ( i + 1 ) + '" style="aspect-ratio:' + ( ph.video ? ph.w / ph.h : RATIOS[ p.ratio ] ) + ';width:auto;height:80px">'; } ).join( '' ) + '</div>' +
			( '<label class="a-label" for="a-caption">' + __( 'Caption', 'stubli' ) + '</label>' ) +
			'<textarea id="a-caption" class="a-textarea" maxlength="' + B.maxCaption + '" placeholder="' + esc( __( 'Say something about it, or leave it empty.', 'stubli' ) ) + '">' + esc( p.caption ) + '</textarea>' +
			'<p class="a-count" data-count' + ( p.caption.length < B.maxCaption - 200 ? ' hidden' : '' ) + '>' + p.caption.length + ' / ' + B.maxCaption + '</p>' +
			( p.linkOpen ? ( '<div class="a-inline"><label class="sr" for="a-link">' + __( 'Web address', 'stubli' ) + '</label><input id="a-link" class="a-field" type="url" inputmode="url" placeholder="https://…"><button type="button" class="a-btn quiet" data-link-add>' + __( 'Add', 'stubli' ) + '</button></div><p class="a-help">' + __( 'It appears at the end of your caption and people can tap it.', 'stubli' ) + '</p>' ) :
				'<button type="button" class="a-chip" data-link style="margin-top:8px">' + icon( 'link', 16 ) + ( __( 'Add a link', 'stubli' ) + '</button>' ) ) +
			( '<h2 class="a-label" id="a-col-h">' + __( 'Which collection?', 'stubli' ) + '</h2>' ) +
			'<div class="a-chips" role="radiogroup" aria-labelledby="a-col-h">' + cols.map( function ( c ) {
				var on = ! p.addingCollection && p.collection === c;
				return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-col="' + esc( c ) + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( c ) + '</button>';
			} ).join( '' ) + '<button type="button" class="a-chip" role="radio" aria-checked="' + p.addingCollection + '" data-col-new>' + ( p.addingCollection ? icon( 'check', 16 ) : '' ) + ( __( '+ New', 'stubli' ) + '</button></div>' ) +
			( p.addingCollection ? ( '<div class="a-inline"><label class="sr" for="a-newcol">' + __( 'New collection name', 'stubli' ) + '</label><input id="a-newcol" class="a-field" maxlength="40" placeholder="' + esc( __( 'e.g. Mountains', 'stubli' ) ) + '" value="' ) + esc( p.newCollection ) + ( '"></div><p class="a-help">' + __( 'Each collection is a folder tab on your page. An emoji at the start is welcome.', 'stubli' ) + '</p>' ) : '' ) +
			( ( p.descOpen || p.photos.some( function ( x ) { return x.alt && x.alt.trim(); } ) ) ? '' : '<button type="button" class="a-btn quiet a-desc-open" data-desc-open aria-expanded="false">' + icon( 'plus', 16 ) + __( 'Add descriptions', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></button><p class="a-help">' + __( 'A few words per photo, read aloud to people who use screen readers. You can add them later too.', 'stubli' ) + '</p>' ) +
			( ( p.descOpen || p.photos.some( function ( x ) { return x.alt && x.alt.trim(); } ) ) ? '<h2 class="a-label">' + ( p.photos.some( function ( x ) { return x.video; } ) ? __( 'Describe what\'s shown for people who can\'t see it', 'stubli' ) : __( 'Describe the photos for people who can\'t see them', 'stubli' ) ) + ' <span class="muted" style="font-weight:400">' + __( '(optional)', 'stubli' ) + '</span></h2>' +
			p.photos.map( function ( ph, i ) {
				return '<div class="a-alt"><img src="' + ph.thumb + '" alt=""><label class="sr" for="a-alt-' + i + '">' + _f( __( 'Description of photo %s', 'stubli' ), i + 1 ) + '</label><input id="a-alt-' + i + '" class="a-field" data-alt="' + i + '" maxlength="300" placeholder="' + ( ph.video ? _f( __( 'Video %s: what happens in it?', 'stubli' ), i + 1 ) : _f( __( 'Photo %s: what\'s in it?', 'stubli' ), i + 1 ) ) + '" value="' + esc( ph.alt ) + '"></div>';
			} ).join( '' ) : '' ) +
			audienceChips( p.audience, 'aud' ) +
			( '<label class="a-check"><span><strong>' + __( 'Add a content note', 'stubli' ) + '</strong><span class="muted small">' + __( 'The photo stays softly blurred until someone chooses to see it.', 'stubli' ) + '</span></span><input type="checkbox" data-note' ) + ( p.note ? ' checked' : '' ) + '></label>' +
			( p.note ? ( '<div class="a-inline"><label class="sr" for="a-note">' + __( 'Content note', 'stubli' ) + '</label><input id="a-note" class="a-field" maxlength="' ) + B.maxNote + '" placeholder="e.g. Flashing lights" value="' + esc( p.noteText ) + '"></div>' : '' ) +
			'<div data-msg aria-live="polite"></div>' +
			'<p class="a-lock">' + icon( 'lock', 14 ) + ( __( 'Location details are removed from your photos and videos before they\'re shared.', 'stubli' ) + '</p></main>' );

		var cap = $( '#a-caption' ), cnt = $( '[data-count]' );
		cap.addEventListener( 'input', function () {
			p.caption = cap.value; cnt.textContent = cap.value.length + ' / ' + B.maxCaption; cnt.hidden = cap.value.length < B.maxCaption - 200;
		} );
		var lk = $( '[data-link]' );
		if ( lk ) { lk.addEventListener( 'click', function () { p.linkOpen = true; viewDetails(); $( '#a-link' ).focus(); } ); }
		var la = $( '[data-link-add]' );
		if ( la ) {
			la.addEventListener( 'click', function () {
				var v = $( '#a-link' ).value.trim();
				if ( v && ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				if ( v ) { p.caption = ( p.caption ? p.caption.replace( /\s+$/, '' ) + '\n' : '' ) + v; }
				p.linkOpen = false; viewDetails();
			} );
		}
		$$( '[data-col]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { p.collection = b.dataset.col; p.addingCollection = false; viewDetails(); } ); } );
		$( '[data-col-new]' ).addEventListener( 'click', function () { p.addingCollection = true; viewDetails(); var n = $( '#a-newcol' ); if ( n ) { n.focus(); } } );
		var nc = $( '#a-newcol' );
		if ( nc ) { nc.addEventListener( 'input', function () { p.newCollection = nc.value; } ); }
		$$( '[data-alt]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { p.photos[ +inp.dataset.alt ].alt = inp.value; p.altsChecked = false; } ); } );
		var dop = $( '[data-desc-open]' ); if ( dop ) { dop.addEventListener( 'click', function () { p.descOpen = true; viewDetails(); var f = $( '#a-alt-0' ); if ( f ) { f.focus(); } } ); }
		$$( '[data-aud]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { p.audience = b.dataset.aud; viewDetails(); $( '[data-aud="' + p.audience + '"]' ).focus(); } ); } );
		$( '[data-note]' ).addEventListener( 'change', function ( e ) { p.note = e.target.checked; viewDetails(); if ( p.note ) { $( '#a-note' ).focus(); } } );
		var nt = $( '#a-note' );
		if ( nt ) { nt.addEventListener( 'input', function () { p.noteText = nt.value; } ); }
		$( '[data-share]' ).addEventListener( 'click', share );
	}

	function msg( html ) { var m = $( '[data-msg]' ); if ( m ) { m.innerHTML = html; if ( m.scrollIntoView ) { m.scrollIntoView( { block: 'center', behavior: matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } ); } } }

	function share() {
		var p = post;
		if ( p.busy ) { return; }
		// Descriptions are optional (23 Oct): no stop before sharing.
		if ( p.addingCollection && ! p.newCollection.trim() ) {
			msg( ( '<div class="a-notice calm" role="alert"><p>' + __( 'Give the new collection a name, or pick one of your collections.', 'stubli' ) + '</p></div>' ) );
			$( '#a-newcol' ).focus();
			return;
		}
		if ( p.note && ! p.noteText.trim() ) {
			msg( ( '<div class="a-notice calm" role="alert"><p>' + __( 'Write a few words for the content note, so people know what to expect.', 'stubli' ) + '</p></div>' ) );
			$( '#a-note' ).focus();
			return;
		}
		p.busy = true;
		var btn = $( '[data-share]' ); btn.disabled = true; btn.textContent = __( 'Sharing…', 'stubli' );
		msg( '<div class="a-notice calm"><p>' + ( p.photos.some( function ( x ) { return x.video; } ) ? __( 'Removing location details and preparing…', 'stubli' ) : __( 'Preparing your photos…', 'stubli' ) ) + '</p><div class="a-progress"><span></span></div></div>' );
		var bar = function ( f ) { var s = $( '.a-progress span' ); if ( s ) { s.style.width = Math.round( f * 100 ) + '%'; } };
		var done = 0;
		var total = 0;
		Promise.all( p.photos.map( function ( ph ) {
			var job = ph.video ? stripLocation( ph.file ) : cropped( ph.file, p.ratio );
			return job.then( function ( b ) { done++; total += b.size + ( ph.video ? ph.poster.size : 0 ); bar( 0.3 * done / p.photos.length ); return b; } );
		} ) ).then( function ( blobs ) {
			if ( B.maxPost && total > B.maxPost ) { return Promise.reject( { message: _f( __( 'Together these are %s, and your host accepts up to %s at once. Share the videos in separate posts, or choose a shorter clip.', 'stubli' ), fmtMB( total ), fmtMB( B.maxPost ) ) } ); }
			var fd = new FormData();
			blobs.forEach( function ( b, i ) {
				var ph = p.photos[ i ];
				if ( ph.video ) {
					fd.append( 'photos[]', b, 'video-' + ( i + 1 ) + ( /quicktime/.test( b.type ) || /\.mov$/i.test( ph.file.name ) ? '.mov' : '.mp4' ) );
					fd.append( 'posters[' + i + ']', ph.poster, 'video-' + ( i + 1 ) + '-cover.jpg' );
				} else {
					fd.append( 'photos[]', b, 'photo-' + ( i + 1 ) + '.jpg' );
				}
				fd.append( 'alts[]', ph.alt.trim() );
			} );
			fd.append( 'caption', p.caption.trim() );
			fd.append( 'collection', p.addingCollection ? p.newCollection.trim() : p.collection );
			if ( p.note ) { fd.append( 'content_note', p.noteText.trim() ); }
			if ( p.audience === 'friends' ) { fd.append( 'audience', 'friends' ); }
			var t = $( '.a-notice p' ); if ( t ) { t.textContent = __( 'Sharing…', 'stubli' ); }
			return send( 'POST', 'posts', fd, function ( f ) { bar( 0.3 + 0.7 * f ); } );
		} ).then( function ( res ) {
			var thumb = p.photos[ 0 ].thumb, col = p.addingCollection ? p.newCollection.trim() : p.collection, aud = p.audience;
			if ( col && B.collections.indexOf( col ) < 0 ) { B.collections.unshift( col ); }
			post = null;
			if ( B.firstDay ) { B.firstDay.photo = true; }
			doneView( __( 'Shared', 'stubli' ), aud === 'friends' ? ( col ? _f( __( 'Your friends see it in their Stubli, in %s. It isn\'t on your public page.', 'stubli' ), col ) : __( 'Your friends see it in their Stubli. It isn\'t on your public page.', 'stubli' ) ) : ( col ? _f( __( 'Your post is on your page in %s.', 'stubli' ), col ) : __( 'Your post is on your page.', 'stubli' ) ), res && res.url, __( 'See your post', 'stubli' ), thumb, '#new', __( 'New post', 'stubli' ) );
		}, function ( err ) {
			p.busy = false;
			viewDetails();
			msg( '<div class="a-notice" role="alert"><p>' + esc( err && err.message || __( 'Something went wrong. Nothing was shared.', 'stubli' ) ) + ( '</p><p class="small muted">' + __( 'Your photos and words are still here.', 'stubli' ) + '</p></div>' ) );
		} );
	}

	function doneView( title, text, url, urlLabel, thumb, again, againLabel ) {
		root.innerHTML = '<main class="a-main" id="main"><div class="a-done">' + ( thumb ? '<img src="' + thumb + '" alt="">' : '<span class="a-tick">' + icon( 'check', 28 ) + '</span>' ) +
			'<h1 class="a-greet" style="margin-top:0">' + esc( title ) + '</h1><p class="muted">' + esc( text ) + '</p><div class="a-actions">' +
			( url ? '<a class="a-btn" href="' + esc( url ) + '">' + esc( urlLabel ) + '</a>' : '' ) + '<a class="a-btn quiet" href="' + again + '">' + esc( againLabel ) + '</a></div></div></main>' + bar( '' );
		$( 'h1' ).setAttribute( 'tabindex', '-1' ); $( 'h1' ).focus();
	}

	/* ── Journal ── */
	/* ── Journal: write, edit and delete entries, with simple formatting like Notes ── */
	var JFIG = /<figure\b[^>]*\bdata-photo="(\d{1,2})"[^>]*>[\s\S]*?<\/figure>/g;
	var jed = null, jSel = null, jl = { loaded: false, loading: false, items: [], page: 1, more: false, error: '' };
	function jCollapse( html ) { return String( html || '' ).replace( /\u200b/g, '' ).replace( JFIG, function ( m, n ) { return '<figure data-photo="' + n + '"></figure>'; } ); }
	function jPhoto( j, n ) { return j.photos.filter( function ( p ) { return p.n === +n; } )[ 0 ]; }
	function jFig( j, n ) {
		var ph = jPhoto( j, n );
		if ( ! ph && ! ( j.pending && j.pending[ n ] ) ) { return ''; }
		return '<figure class="a-jfig" data-photo="' + n + '" contenteditable="false">' + ( ph ? '<img src="' + esc( ph.url ) + '" alt="">' : '<span class="a-jfig-wait">' + __( 'Adding a photo…', 'stubli' ) + '</span>' ) + '<span class="a-jfig-n">' + _f( __( 'Photo %s', 'stubli' ), n ) + '</span></figure>';
	}
	function jExpand( j ) { return String( j.html || '' ).replace( JFIG, function ( m, n ) { return jFig( j, n ); } ); }
	function jUsed( html ) { var out = [], m; JFIG.lastIndex = 0; while ( ( m = JFIG.exec( html ) ) ) { out.push( +m[ 1 ] ); } return out; }
	// Drafts from before the formatting editor were plain text with [Photo N] lines.
	function jTextToHtml( text ) {
		return String( text || '' ).split( '\n' ).map( function ( line ) {
			var m = line.trim().match( /^\[Photo (\d{1,2})\]$/i );
			if ( m ) { return '<figure data-photo="' + m[ 1 ] + '"></figure>'; }
			return line.trim() ? '<p>' + esc( line ) + '</p>' : '';
		} ).join( '' );
	}
	function jWhen( iso, withTime ) {
		var d = new Date( iso );
		if ( isNaN( d ) ) { return ''; }
		var lang = document.documentElement.lang || undefined;
		try {
			return withTime ? d.toLocaleString( lang, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' } ) : d.toLocaleDateString( lang, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } );
		} catch ( e ) { return d.toDateString(); }
	}
	var FMT = [
		{ k: 'h3', label: __( 'Heading', 'stubli' ), html: '<span class="a-fmt-h" aria-hidden="true">H</span>' },
		{ k: 'bold', label: __( 'Bold', 'stubli' ), html: '<b aria-hidden="true">B</b>' },
		{ k: 'italic', label: __( 'Italic', 'stubli' ), html: '<i aria-hidden="true">I</i>' },
		{ k: 'underline', label: __( 'Underline', 'stubli' ), html: '<u aria-hidden="true">U</u>' },
		{ k: 'strikeThrough', label: __( 'Strikethrough', 'stubli' ), html: '<s aria-hidden="true">S</s>' },
		{ k: 'insertUnorderedList', label: __( 'Bulleted list', 'stubli' ), html: icon( 'ul', 20 ) },
		{ k: 'insertOrderedList', label: __( 'Numbered list', 'stubli' ), html: icon( 'ol', 20 ) },
		{ k: 'checklist', label: __( 'Checklist', 'stubli' ), html: icon( 'checklist', 20 ) },
		{ k: 'blockquote', label: __( 'Quote', 'stubli' ), html: icon( 'quote', 20 ) }
	];
	function fmtOn( k ) {
		try {
			if ( k === 'checklist' ) { return !! jListAt( 'ul.checklist' ); }
			if ( k === 'insertUnorderedList' && jListAt( 'ul.checklist' ) ) { return false; }
			if ( k === 'h3' || k === 'blockquote' ) { return String( document.queryCommandValue( 'formatBlock' ) || '' ).toLowerCase().replace( /[<>]/g, '' ) === k; }
			// Quotes and headings look italic or bold already; don't light those buttons for them.
			if ( ( k === 'italic' && fmtOn( 'blockquote' ) ) || ( k === 'bold' && fmtOn( 'h3' ) ) ) { return false; }
			return document.queryCommandState( k );
		} catch ( e ) { return false; }
	}
	function jListAt( sel ) {
		var s = window.getSelection(), n = s && s.rangeCount ? s.getRangeAt( 0 ).startContainer : null;
		if ( n && n.nodeType === 3 ) { n = n.parentNode; }
		var ed = document.getElementById( 'a-jtext' );
		var hit = n && n.closest ? n.closest( sel ) : null;
		return hit && ed && ed.contains( hit ) ? hit : null;
	}
	function fmtState() {
		var ed = document.getElementById( 'a-jtext' );
		if ( ! ed ) { return; }
		var inside = jSel && ed.contains( jSel.startContainer );
		$$( '[data-fmt]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', inside && fmtOn( b.dataset.fmt ) ? 'true' : 'false' ); } );
	}
	document.addEventListener( 'selectionchange', function () {
		var ed = document.getElementById( 'a-jtext' ), s = window.getSelection();
		if ( ed && s && s.rangeCount && ed.contains( s.anchorNode ) ) { jSel = s.getRangeAt( 0 ).cloneRange(); fmtState(); }
	} );
	function jRestore( ed ) {
		ed.focus();
		var s = window.getSelection();
		if ( jSel && ed.contains( jSel.startContainer ) ) { s.removeAllRanges(); s.addRange( jSel ); return; }
		var r = document.createRange(); r.selectNodeContents( ed ); r.collapse( false ); s.removeAllRanges(); s.addRange( r );
	}

	// Drafts save themselves to your Stubli a moment after you stop typing.
	function jHasText( j ) { return !! String( j.html || '' ).replace( /<[^>]*>/g, '' ).replace( /&nbsp;|\u200b/g, ' ' ).trim(); }
	function jSavedText( j ) {
		if ( j.saveMsg ) { return j.saveMsg; }
		if ( ! j.savedAt ) { return ''; }
		var t = j.savedAt.toLocaleTimeString( document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' } );
		return _f( __( 'Draft saved %s', 'stubli' ), t );
	}
	function jSaveNote( j, msg ) {
		j.saveMsg = msg || '';
		var el = document.querySelector( '[data-jsaved]' );
		if ( el && el.dataset.for === String( j.id || 'new' ) ) { el.textContent = jSavedText( j ); }
	}
	function jQueue( j ) {
		if ( j.id && ! j.isDraft ) { return; }
		clearTimeout( j.timer );
		j.timer = setTimeout( function () { jAutosave( j ); }, 1500 );
	}
	function jPayload( j, draft ) {
		var used = jUsed( j.html );
		var out = { title: String( j.title || '' ).trim(), html: j.html, visibility: j.visibility, photos: j.photos.filter( function ( p ) { return used.indexOf( p.n ) > -1; } ).map( function ( p ) { return { n: p.n, id: p.id, caption: String( p.caption || '' ).trim(), alt: String( p.alt || '' ).trim() }; } ) };
		if ( draft ) { out.draft = true; }
		return out;
	}
	function jAutosave( j ) {
		if ( j.busy || j.published ) { return; }
		if ( j.uploading ) { jQueue( j ); return; }
		if ( ! jHasText( j ) ) { return; }
		if ( j.saving ) { j.again = true; return; }
		var did = j.isDraft ? j.id : j.did;
		j.saving = true; jSaveNote( j, __( 'Saving draft…', 'stubli' ) );
		j.savePromise = ( did ? send( 'POST', 'journal/' + did, jPayload( j, true ) ) : send( 'POST', 'journal', jPayload( j, true ) ) ).then( function ( r ) {
			j.saving = false; j.savedAt = new Date();
			if ( ! j.isDraft ) { j.did = r.id; store.set( 'stubli-journal-did', String( r.id ) ); }
			jlReset(); jSaveNote( j );
			if ( j.again ) { j.again = false; jAutosave( j ); }
		}, function ( e ) {
			j.saving = false;
			if ( e && ( e.code === 'stubli_not_found' || e.code === 'stubli_not_draft' ) && ! j.isDraft && j.did ) { j.did = 0; store.del( 'stubli-journal-did' ); jAutosave( j ); return; }
			jSaveNote( j, e && e.code === 'offline' ? __( 'Offline. Your draft is kept on this phone and saves when you\'re back.', 'stubli' ) : __( 'Couldn\'t save the draft just now. It\'s kept on this phone.', 'stubli' ) );
		} );
	}
	window.addEventListener( 'online', function () { if ( journal && jHasText( journal ) && ! journal.savedAt ) { jAutosave( journal ); } if ( jed && jed.isDraft ) { jAutosave( jed ); } } );

	function viewJournals() {
		if ( ! jl.loaded && ! jl.loading ) {
			jl.loading = true;
			Promise.all( [ send( 'GET', 'journal?page=' + jl.page, null ), jl.page === 1 ? send( 'GET', 'journal?drafts=1', null ) : Promise.resolve( jl.drafts || [] ) ] ).then( function ( rr ) {
				var r = rr[ 0 ]; jl.drafts = Array.isArray( rr[ 1 ] ) ? rr[ 1 ] : [];
				var list = Array.isArray( r ) ? r : [];
				jl.items = jl.page === 1 ? list : jl.items.concat( list ); jl.more = list.length >= 20; jl.loaded = true; jl.loading = false; jl.error = '';
			}, function ( e ) { jl.loading = false; jl.loaded = true; jl.error = e.message; } ).then( function () { if ( location.hash === '#journals' ) { viewJournals(); } } );
		}
		var body;
		if ( ! jl.loaded ) {
			body = '<p class="muted" role="status">' + __( 'Opening your journal…', 'stubli' ) + '</p>';
		} else if ( jl.error && ! jl.items.length ) {
			body = '<div class="a-notice" role="alert"><p>' + esc( jl.error ) + '</p></div>';
		} else if ( ! jl.items.length && ! ( jl.drafts || [] ).length ) {
			body = '<div class="a-empty-you">' + smile() + '<p>' + __( 'No journal entries yet.', 'stubli' ) + '</p><a class="a-btn quiet" href="#journal">' + __( 'Write one', 'stubli' ) + '</a></div>';
		} else {
			var drafts = jl.drafts || [];
			body = ( drafts.length ? '<h2 class="a-caps">' + __( 'Drafts', 'stubli' ) + '</h2><ul class="a-jl a-jl-drafts" role="list">' + drafts.map( function ( e ) {
				return '<li><a href="' + ( e.id === journal.did ? '#journal' : '#journal/' + e.id ) + '"><span class="a-jl-date">' + esc( _f( __( 'Saved %s', 'stubli' ), jWhen( e.saved, true ) ) ) + ( e.visibility === 'only_me' ? ' <span class="a-jl-me">' + icon( 'lock', 12 ) + __( 'Only me', 'stubli' ) + '</span>' : '' ) + '</span>' +
					( e.title ? '<span class="a-jl-t">' + esc( e.title ) + '</span>' : '' ) + '<span class="a-jl-x">' + esc( e.excerpt || __( 'An empty draft', 'stubli' ) ) + '</span></a></li>';
			} ).join( '' ) + '</ul><h2 class="a-caps">' + __( 'Published', 'stubli' ) + '</h2>' : '' ) +
			( jl.items.length ? '' : '<p class="muted">' + __( 'Nothing published yet.', 'stubli' ) + '</p>' ) +
			'<ul class="a-jl" role="list">' + jl.items.map( function ( e ) {
				return '<li><a href="#journal/' + e.id + '"><span class="a-jl-date">' + esc( jWhen( e.date ) ) + ( e.visibility === 'only_me' ? ' <span class="a-jl-me">' + icon( 'lock', 12 ) + __( 'Only me', 'stubli' ) + '</span>' : '' ) + '</span>' +
					( e.title ? '<span class="a-jl-t">' + esc( e.title ) + '</span>' : '' ) + '<span class="a-jl-x">' + esc( e.excerpt || '' ) + '</span>' + ( e.edited ? '<span class="a-jl-ed">' + esc( _f( __( 'Edited %s', 'stubli' ), jWhen( e.edited, true ) ) ) + '</span>' : '' ) + '</a></li>';
			} ).join( '' ) + '</ul>' + ( jl.more ? '<button type="button" class="a-btn quiet wide" data-jl-more style="margin-top:12px">' + __( 'Show more', 'stubli' ) + '</button>' : '' );
		}
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + '</a><h1>' + __( 'Your journal', 'stubli' ) + '</h1><a class="a-btn quiet" href="#journal">' + __( 'New entry', 'stubli' ) + '</a></header>' +
			'<main class="a-main" id="main"><p class="muted" style="margin:4px 0 14px">' + __( 'Every entry, including Only-me ones and drafts. Tap one to change or delete it.', 'stubli' ) + '</p>' + body + '</main>';
		var more = $( '[data-jl-more]' );
		if ( more ) { more.addEventListener( 'click', function () { jl.page++; jl.loaded = false; more.disabled = true; more.textContent = __( 'Loading…', 'stubli' ); viewJournals(); } ); }
	}
	function jlReset() { jl.loaded = false; jl.page = 1; jl.items = []; }

	function viewJournal( id ) {
		id = +id || 0;
		if ( id && id === journal.did ) { location.replace( '#journal' ); return; }
		if ( id ) {
			if ( ! jed || jed.id !== id ) {
				jed = { id: id, loading: true, html: '', photos: [], pending: {}, visibility: 'everyone', tool: '', busy: false, error: '', uploading: 0, sure: false };
				send( 'GET', 'journal/' + id, null ).then( function ( r ) {
					jed.loading = false; jed.title = r.title || ''; jed.serverTitle = jed.title; jed.html = r.html; jed.serverHtml = r.html; jed.photos = r.photos || []; jed.visibility = r.visibility; jed.date = r.date; jed.edited = r.edited; jed.url = r.url; jed.isDraft = !! r.draft; jed.savedAt = r.draft && r.saved ? new Date( r.saved ) : null;
					// Unsaved changes to a published entry, kept on this phone, come back.
					var kept = ! r.draft && store.get( 'stubli-jedit-' + id ), keptT = ! r.draft ? store.get( 'stubli-jedit-t-' + id ) : null;
					if ( kept && kept !== r.html ) { jed.html = kept; jed.restored = true; }
					if ( typeof keptT === 'string' && keptT !== jed.title ) { jed.title = keptT; jed.restored = true; }
				}, function ( e ) { jed.loading = false; jed.fail = e.message; } ).then( function () { if ( location.hash === '#journal/' + id ) { viewJournal( id ); } } );
			}
		} else { jed = null; }
		var j = jed || journal, max = B.maxJournal, editing = !! j.id, pub = editing && ! j.isDraft;
		var head = editing ?
			'<header class="a-head"><a class="a-text-btn" href="#journals" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + '</a><h1>' + ( pub ? __( 'Edit entry', 'stubli' ) : __( 'Draft', 'stubli' ) ) + '</h1>' :
			'<header class="a-head"><button type="button" class="a-text-btn" data-cancel-j>' + __( 'Close', 'stubli' ) + '</button>' + seg( 'journal' );
		if ( j.loading || j.fail ) {
			root.innerHTML = head + '<span></span></header><main class="a-main" id="main">' + ( j.fail ? '<div class="a-notice" role="alert"><p>' + esc( j.fail ) + '</p></div><a class="a-btn quiet" href="#journals">' + __( 'Back to your journal', 'stubli' ) + '</a>' : '<p class="muted" role="status">' + __( 'Opening your entry…', 'stubli' ) + '</p>' ) + '</main>';
			if ( j.fail ) { jed = null; }
			bindCommon(); return;
		}
		// Photos whose place in the text was deleted are dropped.
		var used = jUsed( j.html );
		j.photos = j.photos.filter( function ( p ) { return used.indexOf( p.n ) > -1 || ( j.pending && j.pending[ p.n ] ); } );
		var hasText = function () { return !! String( j.html || '' ).replace( /<[^>]*>/g, '' ).replace( /&nbsp;/g, ' ' ).trim(); };
		root.innerHTML = head +
			'<button type="button" class="a-btn" data-save' + ( hasText() && ! j.busy && ! j.uploading ? '' : ' disabled' ) + '>' + ( j.busy ? ( pub ? __( 'Saving…', 'stubli' ) : __( 'Publishing…', 'stubli' ) ) : ( pub ? __( 'Save', 'stubli' ) : __( 'Publish', 'stubli' ) ) ) + '</button></header>' +
			'<main class="a-compose" id="main"><h1 class="sr">' + ( editing ? __( 'Edit journal entry', 'stubli' ) : __( 'New journal entry', 'stubli' ) ) + '</h1>' +
			'<p class="a-date">' + esc( pub ? jWhen( j.date ) : B.today ) + ( pub && j.edited ? ' · ' + esc( _f( __( 'Edited %s', 'stubli' ), jWhen( j.edited, true ) ) ) : '' ) + ( editing ? '' : ' · <a href="#journals">' + __( 'All your entries', 'stubli' ) + '</a>' ) + '</p>' +
			( j.restored ? '<div class="a-notice calm"><p>' + __( 'Your unsaved changes from before are back. Tap Save to keep them.', 'stubli' ) + '</p><button type="button" class="a-btn quiet" data-jdiscard>' + __( 'Discard them', 'stubli' ) + '</button></div>' : '' ) +
			'<label class="sr" for="a-jtitle">' + __( 'Title (optional)', 'stubli' ) + '</label><input id="a-jtitle" class="a-field a-jtitle" maxlength="120" autocomplete="off" placeholder="' + esc( __( 'Title (optional)', 'stubli' ) ) + '" value="' + esc( j.title || '' ) + '">' +
			'<div class="a-fmt" role="toolbar" aria-label="' + esc( __( 'Formatting', 'stubli' ) ) + '">' + FMT.map( function ( f ) { return '<button type="button" class="a-fmt-b" data-fmt="' + f.k + '" aria-pressed="false" aria-label="' + esc( f.label ) + '" title="' + esc( f.label ) + '">' + f.html + '</button>'; } ).join( '' ) + '</div>' +
			'<div id="a-jtext" class="a-journal-text a-rich" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' + esc( __( 'Journal entry', 'stubli' ) ) + '" data-ph="' + esc( __( 'What happened today?', 'stubli' ) ) + '">' + ( jExpand( j ) || '<p><br></p>' ) + '</div>' +
			( j.tool ? '<div class="a-inline"><label class="sr" for="a-jlink">' + ( j.tool === 'video' ? __( 'YouTube or Vimeo address', 'stubli' ) : __( 'Web address', 'stubli' ) ) + '</label><input id="a-jlink" class="a-field" type="url" inputmode="url" placeholder="' + ( j.tool === 'video' ? __( 'A YouTube or Vimeo link', 'stubli' ) : 'https://…' ) + ( '"><button type="button" class="a-btn quiet" data-jadd>' + __( 'Add', 'stubli' ) + '</button></div>' ) +
				( j.tool === 'video' ? ( '<p class="a-help">' + __( 'It shows as a cover with a play button. Nothing loads from the video site until someone presses play.', 'stubli' ) + '</p>' ) : '' ) : '' ) +
			'<div class="a-tools"><label class="a-tool" aria-label="' + esc( __( 'Add a photo', 'stubli' ) ) + '" title="' + esc( __( 'Add a photo', 'stubli' ) ) + '">' + icon( 'photo', 20 ) + '<input class="a-file" type="file" accept="image/*" multiple data-jphoto></label><button type="button" class="a-tool" data-tool="video" aria-label="' + esc( __( 'Add a video link', 'stubli' ) ) + '">' + icon( 'video', 20 ) + '</button><button type="button" class="a-tool" data-tool="link" aria-label="' + esc( __( 'Add a link', 'stubli' ) ) + '">' + icon( 'link', 20 ) + '</button>' +
			'<span class="a-count"><span data-jsaved data-for="' + ( j.id || 'new' ) + '">' + esc( pub ? '' : jSavedText( j ) ) + '</span> <span data-jcount></span></span></div>' +
			( j.uploading ? '<div class="a-notice calm" aria-live="polite"><p>' + ( j.uploading === 1 ? __( 'Adding a photo…', 'stubli' ) : _f( __( 'Adding %s photos…', 'stubli' ), j.uploading ) ) + '</p></div>' : '' ) +
			( j.photos.length ? ( '<h2 class="a-caps">' + __( 'Photos in this entry', 'stubli' ) + '</h2><p class="a-help" style="margin:-4px 0 10px">' + __( 'Each photo sits where you put it in your text. Use the arrows to move it up or down.', 'stubli' ) + '</p>' ) + j.photos.map( function ( ph ) {
				return '<div class="a-jphoto"><img src="' + esc( ph.url ) + '" alt=""><div class="a-jphoto-fields"><div class="a-jphoto-top"><strong>' + _f( __( 'Photo %s', 'stubli' ), ph.n ) + '</strong><span><button type="button" class="a-icon-btn" data-jmove="' + ph.n + ':-1" aria-label="' + esc( _f( __( 'Move photo %s up', 'stubli' ), ph.n ) ) + '">' + icon( 'up', 18 ) + '</button><button type="button" class="a-icon-btn" data-jmove="' + ph.n + ':1" aria-label="' + esc( _f( __( 'Move photo %s down', 'stubli' ), ph.n ) ) + '">' + icon( 'down', 18 ) + '</button></span></div>' +
					'<label class="a-label a-jlabel" for="a-jcap-' + ph.n + ( '">' + __( 'Caption', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(shown under the photo)', 'stubli' ) + '</span></label><input id="a-jcap-' ) + ph.n + '" class="a-field" maxlength="300" data-jcap="' + ph.n + '" value="' + esc( ph.caption ) + '">' +
					'<label class="a-label a-jlabel" for="a-jalt-' + ph.n + ( '">' + __( 'Description', 'stubli' ) + ' <span class="muted" style="font-weight:400">' + __( '(optional, for people who can\'t see it)', 'stubli' ) + '</span></label><input id="a-jalt-' ) + ph.n + '" class="a-field" maxlength="300" data-jalt="' + ph.n + '" value="' + esc( ph.alt ) + '">' +
					'<button type="button" class="a-text-btn a-jremove" data-jremove="' + ph.n + '">' + _f( __( 'Remove photo %s', 'stubli' ), ph.n ) + '</button></div></div>';
			} ).join( '' ) : '' ) +
			( '<h2 class="a-caps" id="a-vis-h">' + __( 'Who can see this', 'stubli' ) + '</h2><div class="a-vis" role="radiogroup" aria-labelledby="a-vis-h">' ) +
			'<button type="button" role="radio" aria-checked="' + ( j.visibility === 'everyone' ) + '" data-vis="everyone"><strong>' + ( j.visibility === 'everyone' ? '✓ ' : '' ) + ( __( 'Everyone', 'stubli' ) + '</strong><span>' + __( 'In your Journal tab', 'stubli' ) + '</span></button>' ) +
			'<button type="button" role="radio" aria-checked="' + ( j.visibility === 'only_me' ) + '" data-vis="only_me"><strong>' + ( j.visibility === 'only_me' ? '✓ ' : '' ) + ( __( 'Only me', 'stubli' ) + '</strong><span>' + __( 'A private note', 'stubli' ) + '</span></button></div>' ) +
			'<div data-msg aria-live="polite">' + ( j.error ? '<div class="a-notice" role="alert"><p>' + esc( j.error ) + '</p></div>' : '' ) + '</div>' +
			( j.visibility === 'only_me' && j.photos.length ? ( '<p class="a-help" style="margin-top:14px">' + __( 'Photos in an Only-me entry aren\'t shown anywhere, but anyone who has a photo\'s exact web address could open it.', 'stubli' ) + '</p>' ) : '' ) +
			( pub ? '<p class="a-help" style="margin-top:14px">' + __( 'Saving keeps the original date and adds an "Edited" time under it. The earlier version is kept, privately, in WordPress. Until you save, your changes are kept on this phone.', 'stubli' ) + '</p>' :
				'<p class="a-help" style="margin-top:14px">' + __( 'Your draft saves itself to your Stubli as you write, so you can carry on from any device. Only you can see drafts. Publishing dates the entry today. Journal entries never appear in anyone\'s feed.', 'stubli' ) + '</p>' ) +
			( editing ? '<section class="a-card a-ed-del">' + ( j.sure ?
					'<p><strong>' + ( pub ? __( 'Delete this entry?', 'stubli' ) : __( 'Delete this draft?', 'stubli' ) ) + '</strong> ' + ( pub ? __( 'It leaves your Journal tab. You can undo right after.', 'stubli' ) : __( 'Nobody has seen it. You can undo right after.', 'stubli' ) ) + '</p><div class="a-actions"><button type="button" class="a-btn a-danger" data-jdel-yes>' + ( pub ? __( 'Delete entry', 'stubli' ) : __( 'Delete draft', 'stubli' ) ) + '</button><button type="button" class="a-btn quiet" data-jdel-no>' + __( 'Keep it', 'stubli' ) + '</button></div>' :
					'<button type="button" class="a-text-btn a-danger-text" data-jdel>' + ( pub ? __( 'Delete entry…', 'stubli' ) : __( 'Delete draft…', 'stubli' ) ) + '</button>' ) + '</section>' : '' ) +
			'</main>';
		j.error = '';
		bindCommon();
		var ed = $( '#a-jtext' ), c = $( '[data-jcount]' ), save = $( '[data-save]' );
		var tt = $( '#a-jtitle' );
		tt.addEventListener( 'input', function () {
			j.title = tt.value;
			if ( ! editing ) { saveJournalDraft(); }
			if ( pub ) { store.set( 'stubli-jedit-t-' + j.id, j.title ); } else { jQueue( j ); }
		} );
		tt.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); ed.focus(); } } );
		try { document.execCommand( 'defaultParagraphSeparator', false, 'p' ); } catch ( e ) {}
		var count = function () {
			var n = ( ed.textContent || '' ).length;
			c.textContent = n + ' / ' + max;
			c.classList.toggle( 'is-over', n > max );
			ed.classList.toggle( 'is-empty', ! ( ed.textContent || '' ).trim() && ! ed.querySelector( 'figure' ) );
		};
		// Browsers sometimes put a list or quote inside a paragraph; lift it out, keeping the caret.
		var tidy = function () {
			var wrapped = Array.prototype.filter.call( ed.children, function ( el ) { return el.tagName === 'P' && el.querySelector( ':scope > ul, :scope > ol, :scope > blockquote, :scope > h3, :scope > p' ); } );
			if ( ! wrapped.length ) { return; }
			var s = window.getSelection(), r = s.rangeCount ? s.getRangeAt( 0 ) : null, sc = r && r.startContainer, so = r ? r.startOffset : 0;
			wrapped.forEach( function ( el ) { while ( el.firstChild ) { ed.insertBefore( el.firstChild, el ); } ed.removeChild( el ); } );
			if ( sc && ed.contains( sc ) ) { var nr = document.createRange(); nr.setStart( sc, Math.min( so, sc.nodeType === 3 ? sc.length : sc.childNodes.length ) ); nr.collapse( true ); s.removeAllRanges(); s.addRange( nr ); }
		};
		var sync = function () {
			tidy();
			j.html = jCollapse( ed.innerHTML );
			if ( ! editing ) { saveJournalDraft(); }
			if ( pub ) { store.set( 'stubli-jedit-' + j.id, j.html ); } else { jQueue( j ); }
			save.disabled = ! hasText() || j.uploading > 0 || j.busy;
			count();
		};
		count();
		// Checklists, like Notes: a list whose items you tick off.
		var toggleChecklist = function () {
			var on = jListAt( 'ul.checklist' );
			if ( on ) { on.classList.remove( 'checklist' ); document.execCommand( 'insertUnorderedList', false, null ); return; }
			var ul = jListAt( 'ul' );
			if ( ! ul ) { document.execCommand( 'insertUnorderedList', false, null ); tidy(); ul = jListAt( 'ul' ); }
			if ( ul ) { ul.className = 'checklist'; }
		};
		// Turning underline or strikethrough off at the end of the text: some browsers keep
		// going, so step the caret just outside the styled words first.
		var inline = function ( k ) {
			var tag = { underline: 'u', strikeThrough: 's,strike' }[ k ], sel = window.getSelection();
			if ( tag && sel.rangeCount && sel.isCollapsed ) {
				var at = sel.anchorNode, el = at && ( at.nodeType === 3 ? at.parentNode : at ).closest( tag );
				if ( el && ed.contains( el ) ) {
					var end = document.createRange(); end.selectNodeContents( el ); end.collapse( false );
					if ( sel.getRangeAt( 0 ).compareBoundaryPoints( Range.END_TO_END, end ) >= 0 || ( at.nodeType === 3 && sel.anchorOffset === at.length && ! at.nextSibling ) ) {
						var gap = document.createTextNode( '\u200b' ); el.parentNode.insertBefore( gap, el.nextSibling );
						var r = document.createRange(); r.setStart( gap, 1 ); r.collapse( true ); sel.removeAllRanges(); sel.addRange( r );
						return;
					}
				}
			}
			document.execCommand( k, false, null );
		};
		var tick = function ( li ) {
			if ( li.getAttribute( 'data-checked' ) === 'true' ) { li.removeAttribute( 'data-checked' ); } else { li.setAttribute( 'data-checked', 'true' ); }
			sync();
		};
		ed.addEventListener( 'click', function ( e ) {
			var li = e.target.closest && e.target.closest( 'ul.checklist > li' );
			if ( li && e.clientX - li.getBoundingClientRect().left < 30 ) { e.preventDefault(); tick( li ); }
		} );
		ed.addEventListener( 'input', function ( e ) {
			// A new line in a checklist starts unticked.
			if ( e.inputType === 'insertParagraph' ) { var nl = jListAt( 'ul.checklist > li' ); if ( nl && ! ( nl.textContent || '' ).trim() ) { nl.removeAttribute( 'data-checked' ); } }
			// Like Notes: "- " starts a bulleted list, "1. " a numbered one, "# " a heading, "> " a quote.
			if ( e.inputType === 'insertText' && e.data === ' ' ) {
				var s = window.getSelection(), node = s.anchorNode;
				if ( node && node.nodeType === 3 && ! node.previousSibling ) {
					var before = node.textContent.slice( 0, s.anchorOffset ).replace( / /g, ' ' ), block = node.parentNode;
					var inList = block.closest && block.closest( 'li' );
					var cmd = /^\[ ?\] $/.test( before ) ? [ 'checklist' ] : /^[-*•] $/.test( before ) ? [ 'insertUnorderedList' ] : /^1[.)] $/.test( before ) ? [ 'insertOrderedList' ] : /^# $/.test( before ) ? [ 'formatBlock', '<h3>' ] : /^> $/.test( before ) ? [ 'formatBlock', '<blockquote>' ] : null;
					if ( cmd && ! inList ) {
						// Select the typed "- " and delete it the normal way, so the caret stays on this line.
						var r = document.createRange(); r.setStart( node, 0 ); r.setEnd( node, before.length ); s.removeAllRanges(); s.addRange( r );
						document.execCommand( 'delete', false, null );
						if ( cmd[ 0 ] === 'checklist' ) { toggleChecklist(); } else { document.execCommand( cmd[ 0 ], false, cmd[ 1 ] || null ); }
					}
				}
			}
			sync();
		} );
		// Pasted text comes in plain, so the entry keeps your own simple formatting.
		ed.addEventListener( 'paste', function ( e ) {
			var t = ( e.clipboardData || window.clipboardData );
			if ( ! t ) { return; }
			e.preventDefault();
			document.execCommand( 'insertText', false, t.getData( 'text/plain' ) );
		} );
		ed.addEventListener( 'keydown', function ( e ) {
			if ( ( e.metaKey || e.ctrlKey ) && ! e.altKey ) {
				var k = { b: 'bold', i: 'italic', u: 'underline' }[ String( e.key ).toLowerCase() ];
				if ( k ) { e.preventDefault(); inline( k ); sync(); fmtState(); }
				if ( e.key === 'Enter' ) { var cl = jListAt( 'ul.checklist > li' ); if ( cl ) { e.preventDefault(); tick( cl ); } }
			}
		} );
		$$( '[data-fmt]' ).forEach( function ( b ) {
			var go = function () {
				jRestore( ed );
				var k = b.dataset.fmt;
				if ( k === 'checklist' ) { toggleChecklist(); } else if ( k === 'h3' || k === 'blockquote' ) { document.execCommand( 'formatBlock', false, fmtOn( k ) ? '<p>' : '<' + k + '>' ); } else { inline( k ); }
				var s = window.getSelection(); if ( s.rangeCount ) { jSel = s.getRangeAt( 0 ).cloneRange(); }
				sync(); fmtState();
			};
			// Keep the keyboard and the selection while tapping a button.
			b.addEventListener( 'pointerdown', function ( e ) { e.preventDefault(); go(); } );
			b.addEventListener( 'click', function ( e ) { if ( e.detail === 0 ) { go(); } } );
		} );
		var rerender = function () { viewJournal( j.id ); };
		var jp = $( '[data-jphoto]' );
		jp.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( jp.files || [] ).slice( 0, 10 );
			if ( ! files.length ) { return; }
			j.pending = j.pending || {};
			var top = function ( n ) { while ( n && n.parentNode !== ed ) { n = n.parentNode; } return n && n.parentNode === ed ? n : null; };
			var at = jSel && ed.contains( jSel.startContainer ) ? top( jSel.startContainer ) : null;
			var nums = files.map( function () {
				var n = Math.max.apply( null, [ 0 ].concat( j.photos.map( function ( p ) { return p.n; } ), Object.keys( j.pending ).map( Number ), jUsed( jCollapse( ed.innerHTML ) ) ) ) + 1;
				j.pending[ n ] = true;
				var holder = document.createElement( 'div' ); holder.innerHTML = jFig( j, n ); var fig = holder.firstChild;
				if ( at && ! ( at.textContent || '' ).trim() && at.tagName === 'P' ) { ed.replaceChild( fig, at ); } else if ( at ) { at.parentNode.insertBefore( fig, at.nextSibling ); } else { ed.appendChild( fig ); }
				if ( ! fig.nextSibling ) { var p = document.createElement( 'p' ); p.innerHTML = '<br>'; ed.appendChild( p ); }
				at = fig;
				return n;
			} );
			sync();
			j.uploading += files.length; rerender();
			files.reduce( function ( chain, f, i ) {
				return chain.then( function () {
					var n = nums[ i ];
					return resized( f ).then( function ( blob ) {
						var fd = new FormData(); fd.append( 'photo', blob, 'journal.jpg' );
						return send( 'POST', 'journal/photos', fd );
					} ).then( function ( r ) {
						j.photos.push( { n: n, id: r.id, url: r.url, caption: '', alt: '' } );
					}, function ( e ) {
						j.error = ( e && e.message ) || __( 'A photo couldn\'t be added. Try again.', 'stubli' );
						j.html = String( j.html ).replace( new RegExp( '<figure data-photo="' + n + '"></figure>' ), '' );
					} ).then( function () {
						delete j.pending[ n ]; j.uploading--;
						if ( ! editing ) { saveJournalDraft(); }
						if ( pub ) { store.set( 'stubli-jedit-' + j.id, j.html ); } else { jQueue( j ); }
						if ( location.hash === ( editing ? '#journal/' + j.id : '#journal' ) ) { rerender(); }
					} );
				} );
			}, Promise.resolve() );
		} );
		$$( '[data-jcap]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { var p = jPhoto( j, inp.dataset.jcap ); if ( p ) { p.caption = inp.value; if ( ! editing ) { saveJournalDraft(); } jQueue( j ); } } ); } );
		$$( '[data-jalt]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { var p = jPhoto( j, inp.dataset.jalt ); if ( p ) { p.alt = inp.value; if ( ! editing ) { saveJournalDraft(); } jQueue( j ); } } ); } );
		$$( '[data-jremove]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var n = +b.dataset.jremove;
				j.photos = j.photos.filter( function ( x ) { return x.n !== n; } );
				j.html = String( j.html ).replace( '<figure data-photo="' + n + '"></figure>', '' );
				if ( ! editing ) { saveJournalDraft(); }
				jQueue( j ); rerender();
			} );
		} );
		$$( '[data-jmove]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var parts = b.dataset.jmove.split( ':' ), n = parts[ 0 ], dir = +parts[ 1 ];
				var box = document.createElement( 'div' ); box.innerHTML = j.html;
				var fig = box.querySelector( 'figure[data-photo="' + n + '"]' );
				if ( ! fig ) { return; }
				while ( fig.parentNode !== box ) { fig.parentNode.parentNode.insertBefore( fig, fig.parentNode.nextSibling ); }
				var other = dir < 0 ? fig.previousElementSibling : fig.nextElementSibling;
				if ( other ) { if ( dir < 0 ) { box.insertBefore( fig, other ); } else { box.insertBefore( fig, other.nextSibling ); } }
				j.html = box.innerHTML;
				if ( ! editing ) { saveJournalDraft(); }
				jQueue( j ); rerender();
				var again = $( '[data-jmove="' + b.dataset.jmove + '"]' ); if ( again ) { again.focus(); }
			} );
		} );
		$$( '[data-tool]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { j.tool = j.tool === b.dataset.tool ? '' : b.dataset.tool; rerender(); var f = $( '#a-jlink' ); if ( f ) { f.focus(); } } ); } );
		var add = $( '[data-jadd]' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				var v = $( '#a-jlink' ).value.trim();
				if ( v && ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				if ( v ) { j.html = String( j.html || '' ) + '<p>' + esc( v ) + '</p>'; if ( ! editing ) { saveJournalDraft(); } }
				j.tool = ''; rerender();
			} );
		}
		$$( '[data-vis]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { j.visibility = b.dataset.vis; if ( ! editing ) { store.set( 'stubli-journal-vis', j.visibility ); } jQueue( j ); rerender(); } ); } );
		var dis = $( '[data-jdiscard]' );
		if ( dis ) { dis.addEventListener( 'click', function () { j.html = j.serverHtml; j.title = j.serverTitle || ''; j.restored = false; store.del( 'stubli-jedit-' + j.id ); store.del( 'stubli-jedit-t-' + j.id ); rerender(); } ); }
		var del = $( '[data-jdel]' ); if ( del ) { del.addEventListener( 'click', function () { j.sure = true; rerender(); $( '[data-jdel-no]' ).focus(); } ); }
		var no = $( '[data-jdel-no]' ); if ( no ) { no.addEventListener( 'click', function () { j.sure = false; rerender(); $( '[data-jdel]' ).focus(); } ); }
		var yes = $( '[data-jdel-yes]' );
		if ( yes ) {
			yes.addEventListener( 'click', function () {
				var eid = j.id; yes.disabled = true; yes.textContent = __( 'Deleting…', 'stubli' );
				var wasDraft = j.isDraft;
				clearTimeout( j.timer ); j.published = true;
				send( 'DELETE', 'journal/' + eid, null ).then( function () {
					store.del( 'stubli-jedit-' + eid );
					jed = null; jlReset(); location.hash = '#journals';
					toast( wasDraft ? __( 'Draft deleted.', 'stubli' ) : __( 'Entry deleted.', 'stubli' ), function () {
						send( 'POST', 'journal/' + eid + '/restore', {} ).then( function () { jlReset(); if ( location.hash === '#journals' ) { viewJournals(); } toast( __( 'It\'s back in your journal.', 'stubli' ) ); }, function ( e ) { toast( e.message ); } );
					} );
				}, function ( e ) { j.error = e.message; j.sure = false; rerender(); } );
			} );
		}
		save.addEventListener( 'click', function () {
			sync();
			if ( ! hasText() || j.busy ) { return; }
			clearTimeout( j.timer );
			j.busy = true; save.disabled = true; save.textContent = pub ? __( 'Saving…', 'stubli' ) : __( 'Publishing…', 'stubli' );
			// Let a draft save that's on its way finish first, so nothing is doubled.
			( j.savePromise || Promise.resolve() ).catch( function () {} ).then( function () {
				var target = pub || j.isDraft ? j.id : j.did;
				return send( 'POST', target ? 'journal/' + target : 'journal', jPayload( j, false ) );
			} ).then( function () {
				jlReset();
				if ( pub ) { store.del( 'stubli-jedit-' + j.id ); store.del( 'stubli-jedit-t-' + j.id ); jed = null; location.hash = '#journals'; toast( __( 'Saved. It shows when you edited it.', 'stubli' ) ); return; }
				var priv = j.visibility === 'only_me';
				j.published = true;
				if ( editing ) { jed = null; } else {
					j.html = ''; j.title = ''; j.photos = []; j.busy = false; j.did = 0; j.savedAt = null; j.saveMsg = ''; j.published = false;
					store.del( 'stubli-journal-html' ); store.del( 'stubli-journal-title' ); store.del( 'stubli-journal-draft' ); store.del( 'stubli-journal-photos' ); store.del( 'stubli-journal-did' );
				}
				doneView( __( 'Published', 'stubli' ), priv ? __( 'Only you can see this entry.', 'stubli' ) : __( 'Your entry is in your Journal tab.', 'stubli' ), B.journal, __( 'See your journal', 'stubli' ), '', '#journal', __( 'New entry', 'stubli' ) );
			}, function ( err ) {
				j.busy = false; j.error = ( err && err.message ) || __( 'Something went wrong. Your entry is still here.', 'stubli' ); rerender();
			} );
		} );
	}

	/* ── Send feedback ── */
	var fb = { kind: 'idea', text: '', address: true, shot: null, busy: false, sent: false, error: '' };
	var FB = {
		idea: { label: __( 'An idea', 'stubli' ), q: __( 'What would make Stubli better?', 'stubli' ), ph: __( 'I wish I could…', 'stubli' ) },
		broken: { label: __( 'Something\'s not working', 'stubli' ), q: __( 'What happened?', 'stubli' ), ph: __( 'I tried to … and then …', 'stubli' ) },
		love: { label: __( 'Kind words', 'stubli' ), q: __( 'What do you like?', 'stubli' ), ph: __( 'I love that…', 'stubli' ) }
	};
	function device() {
		var ua = navigator.userAgent, os = /iPhone/.test( ua ) ? 'iPhone' : /iPad/.test( ua ) ? 'iPad' : /Android/.test( ua ) ? 'Android' : /Mac OS X/.test( ua ) ? 'Mac' : /Windows/.test( ua ) ? 'Windows' : /Linux/.test( ua ) ? 'Linux' : 'Other';
		var br = /Edg\//.test( ua ) ? 'Edge' : /Firefox\//.test( ua ) ? 'Firefox' : /CriOS|Chrome\//.test( ua ) ? 'Chrome' : /Safari\//.test( ua ) ? 'Safari' : 'Browser';
		return os + ' · ' + br + ( isStandalone() ? ' · home-screen app' : '' );
	}
	function viewFeedback() {
		var host = String( B.home || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		if ( fb.sent ) {
			root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + ( '</a><h1>' + __( 'Send feedback', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main"><div class="a-done"><span class="a-tick">' ) + icon( 'check', 28 ) + '</span>' +
				( '<h2 tabindex="-1">' + __( 'Thank you', 'stubli' ) ) + ( B.name ? ', ' + esc( B.name ) : '' ) + ( '</h2><p class="muted">' + __( 'Your note is with the Stubli team.', 'stubli' ) ) + ( fb.address ? ( ' ' + __( 'If you asked a question, Celestine will reply.', 'stubli' ) ) : '' ) + '</p>' +
				( '<div class="a-actions"><button type="button" class="a-btn quiet" data-again>' + __( 'Write another', 'stubli' ) + '</button><a class="a-btn quiet" href="#home">' + __( 'Done', 'stubli' ) + '</a></div></div></main>' );
			$( '.a-done h2' ).focus();
			$( '[data-again]' ).addEventListener( 'click', function () { fb.sent = false; fb.text = ''; fb.shot = null; viewFeedback(); } );
			return;
		}
		var k = FB[ fb.kind ];
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + ( '</a><h1>' + __( 'Send feedback', 'stubli' ) + '</h1><span></span></header>' ) +
			( '<main class="a-compose" id="main"><p>' + __( 'This goes to the people who make Stubli, not to anyone you follow. Every message is read.', 'stubli' ) + '</p>' ) +
			'<div class="a-chips" role="radiogroup" aria-label="' + esc( __( 'What is it about?', 'stubli' ) ) + '">' + Object.keys( FB ).map( function ( key ) {
				var on = fb.kind === key;
				return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-kind="' + key + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( FB[ key ].label ) + '</button>';
			} ).join( '' ) + '</div>' +
			'<label class="a-label" for="a-fb">' + esc( k.q ) + '</label><textarea id="a-fb" class="a-textarea" style="min-height:160px" maxlength="4000" placeholder="' + esc( k.ph ) + '">' + esc( fb.text ) + '</textarea>' +
			( '<label class="a-check" style="margin-top:14px"><span>' + __( 'Include my address (', 'stubli' ) ) + esc( host ) + ( __( ') so you can reply', 'stubli' ) + '</span><input type="checkbox" data-addr' ) + ( fb.address ? ' checked' : '' ) + '></label>' +
			'<div style="margin-top:14px">' + ( fb.shot ? '<div class="a-alt"><img src="' + fb.shot.url + ( '" alt="' + esc( __( 'Your screenshot', 'stubli' ) ) + '"><span>' + __( 'Screenshot added ·', 'stubli' ) + ' <button type="button" class="a-text-btn" data-noshot style="min-height:auto;padding:0;text-decoration:underline">' + __( 'Remove', 'stubli' ) + '</button></span></div>' ) :
				'<label class="a-chip">' + icon( 'photo', 16 ) + ( __( 'Add a screenshot', 'stubli' ) + '<input class="a-file" type="file" accept="image/png,image/jpeg,image/webp" data-shot></label>' ) ) + '</div>' +
			( '<p class="a-help" style="margin-top:14px">' + __( 'We also send your Stubli version and phone type, to help fix problems. Nothing else.', 'stubli' ) + '</p>' ) +
			'<div data-msg aria-live="polite">' + ( fb.error ? '<div class="a-notice" role="alert"><p>' + esc( fb.error ) + '</p></div>' : '' ) + '</div>' +
			'<button type="button" class="a-btn wide" style="margin-top:14px" data-send' + ( fb.busy ? ' disabled' : '' ) + '>' + ( fb.busy ? __( 'Sending…', 'stubli' ) : __( 'Send feedback', 'stubli' ) ) + '</button></main>';
		fb.error = '';
		$$( '[data-kind]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { fb.kind = b.dataset.kind; viewFeedback(); } ); } );
		var t = $( '#a-fb' );
		t.addEventListener( 'input', function () { fb.text = t.value; } );
		$( '[data-addr]' ).addEventListener( 'change', function ( e ) { fb.address = e.target.checked; } );
		var sh = $( '[data-shot]' );
		if ( sh ) {
			sh.addEventListener( 'change', function () {
				var f = sh.files && sh.files[ 0 ];
				if ( ! f ) { return; }
				resized( f ).then( function ( b ) { fb.shot = { blob: b, url: URL.createObjectURL( b ) }; viewFeedback(); }, function () { fb.error = __( 'That image couldn\'t be opened. Try a PNG or JPEG.', 'stubli' ); viewFeedback(); } );
			} );
		}
		var ns = $( '[data-noshot]' );
		if ( ns ) { ns.addEventListener( 'click', function () { URL.revokeObjectURL( fb.shot.url ); fb.shot = null; viewFeedback(); } ); }
		$( '[data-send]' ).addEventListener( 'click', function () {
			if ( ! fb.text.trim() ) { msg( ( '<div class="a-notice calm" role="alert"><p>' + __( 'Write a few words first.', 'stubli' ) + '</p></div>' ) ); t.focus(); return; }
			fb.busy = true; viewFeedback();
			var fd = new FormData();
			fd.append( 'kind', fb.kind ); fd.append( 'message', fb.text.trim() ); fd.append( 'include_address', fb.address ? '1' : '0' ); fd.append( 'device', device() );
			if ( fb.shot ) { fd.append( 'screenshot', fb.shot.blob, 'screenshot.jpg' ); }
			send( 'POST', 'feedback/send', fd ).then( function () {
				fb.busy = false; fb.sent = true; if ( fb.shot ) { URL.revokeObjectURL( fb.shot.url ); } viewFeedback();
			}, function ( e ) { fb.busy = false; fb.error = ( e && e.message ) || __( 'The note didn\'t go through. Try again in a moment.', 'stubli' ); viewFeedback(); } );
		} );
	}

	/* ── Instagram import ──────────────────────────────────────────────
	 * Reads Instagram's "Download your information" zip (JSON format)
	 * on this device, then sends one post at a time with its original
	 * date and caption. Photos are scaled to 2048px and re-encoded, which
	 * drops location data. Running it twice never makes duplicates.
	 */
	var u8 = function ( b ) { return b.arrayBuffer().then( function ( a ) { return new DataView( a ); } ); };
	function zipOpen( file ) {
		var tailLen = Math.min( file.size, 65557 );
		return u8( file.slice( file.size - tailLen ) ).then( function ( dv ) {
			var eocd = -1;
			for ( var i = dv.byteLength - 22; i >= 0; i-- ) { if ( dv.getUint32( i, true ) === 0x06054b50 ) { eocd = i; break; } }
			if ( eocd < 0 ) { throw new Error( 'notzip' ); }
			var count = dv.getUint16( eocd + 10, true ), cdSize = dv.getUint32( eocd + 12, true ), cdOff = dv.getUint32( eocd + 16, true );
			var base = file.size - tailLen;
			var loc = eocd - 20;
			if ( ( cdOff === 0xffffffff || count === 0xffff ) && loc >= 0 && dv.getUint32( loc, true ) === 0x07064b50 ) {
				var recOff = Number( dv.getBigUint64( loc + 8, true ) );
				return u8( file.slice( recOff, recOff + 56 ) ).then( function ( r ) {
					return readCd( file, Number( r.getBigUint64( 48, true ) ), Number( r.getBigUint64( 40, true ) ) );
				} );
			}
			return readCd( file, cdOff, cdSize );
		} );
	}
	function readCd( file, off, size ) {
		return u8( file.slice( off, off + size ) ).then( function ( dv ) {
			var out = {}, p = 0, dec = new TextDecoder();
			while ( p + 46 <= dv.byteLength && dv.getUint32( p, true ) === 0x02014b50 ) {
				var method = dv.getUint16( p + 10, true ), csize = dv.getUint32( p + 20, true ), usize = dv.getUint32( p + 24, true );
				var nl = dv.getUint16( p + 28, true ), el = dv.getUint16( p + 30, true ), cl = dv.getUint16( p + 32, true ), lho = dv.getUint32( p + 42, true );
				var name = dec.decode( new Uint8Array( dv.buffer, dv.byteOffset + p + 46, nl ) );
				var e = p + 46 + nl, end = e + el;
				while ( e + 4 <= end ) {
					var id = dv.getUint16( e, true ), len = dv.getUint16( e + 2, true ), q = e + 4;
					if ( id === 1 ) {
						if ( usize === 0xffffffff ) { usize = Number( dv.getBigUint64( q, true ) ); q += 8; }
						if ( csize === 0xffffffff ) { csize = Number( dv.getBigUint64( q, true ) ); q += 8; }
						if ( lho === 0xffffffff ) { lho = Number( dv.getBigUint64( q, true ) ); }
					}
					e += 4 + len;
				}
				if ( name.slice( -1 ) !== '/' ) { out[ name ] = { file: file, method: method, csize: csize, off: lho }; }
				p = end + cl;
			}
			return out;
		} );
	}
	function zipRead( ent, type ) {
		return u8( ent.file.slice( ent.off, ent.off + 30 ) ).then( function ( h ) {
			var start = ent.off + 30 + h.getUint16( 26, true ) + h.getUint16( 28, true );
			var raw = ent.file.slice( start, start + ent.csize );
			if ( ent.method === 0 ) { return raw.slice( 0, raw.size, type ); }
			if ( ent.method === 8 && window.DecompressionStream ) {
				return new Response( raw.stream().pipeThrough( new DecompressionStream( 'deflate-raw' ) ) ).blob().then( function ( b ) { return b.slice( 0, b.size, type ); } );
			}
			throw new Error( 'method' );
		} );
	}
	function fixText( s ) { s = String( s || '' ); try { return decodeURIComponent( escape( s ) ); } catch ( e ) { return s; } }
	function mime( name ) {
		var x = name.split( '.' ).pop().toLowerCase();
		return { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', heic: 'image/heic', gif: 'image/gif', mp4: 'video/mp4', m4v: 'video/mp4', mov: 'video/quicktime' }[ x ] || '';
	}
	function resized( blob ) {
		return loadImage( blob ).then( function ( img ) {
			var d = dims( img ), s = Math.min( 1, 2048 / Math.max( d.w, d.h ) );
			var c = draw( img, 0, 0, d.w, d.h, d.w * s, d.h * s );
			if ( img.close ) { img.close(); }
			return toBlob( c, 0.88 );
		} );
	}

	var imp = null;
	function scanImport( files ) {
		imp = { state: 'scanning', posts: [], videos: 0, missing: 0, collection: __( 'From Instagram', 'stubli' ), done: 0, failed: [], stop: false };
		viewImport();
		var entries = {};
		Promise.all( Array.prototype.map.call( files, function ( f ) {
			return zipOpen( f ).then( function ( m ) { Object.keys( m ).forEach( function ( k ) { entries[ k ] = m[ k ]; } ); } );
		} ) ).then( function () {
			var names = Object.keys( entries );
			var jsons = names.filter( function ( n ) { return /(^|\/)posts_\d+\.json$/.test( n ) && ! /archived|recently_deleted/.test( n ); } );
			if ( ! jsons.length ) { throw new Error( names.some( function ( n ) { return /\.html$/.test( n ); } ) ? 'html' : 'noposts' ); }
			var find = function ( uri ) {
				if ( entries[ uri ] ) { return uri; }
				for ( var i = 0; i < names.length; i++ ) { if ( names[ i ].slice( -uri.length - 1 ) === '/' + uri ) { return names[ i ]; } }
				return '';
			};
			return Promise.all( jsons.map( function ( n ) { return zipRead( entries[ n ] ).then( function ( b ) { return b.text(); } ); } ) ).then( function ( texts ) {
				var seen = {};
				texts.forEach( function ( t ) {
					var data; try { data = JSON.parse( t ); } catch ( e ) { return; }
					var list = Array.isArray( data ) ? data : ( data && ( data.ig_posts || data.posts ) ) || [];
					list.forEach( function ( p ) {
						var media = ( p && p.media ) || [];
						var photos = [], firstImage = '';
						media.forEach( function ( m ) {
							var name = find( String( m.uri || '' ) );
							if ( ! name || ! mime( name ) ) { imp.missing++; return; }
							if ( /^video/.test( mime( name ) ) ) { imp.videos++; } else if ( ! firstImage ) { firstImage = name; }
							photos.push( name );
						} );
						if ( ! photos.length ) { return; }
						var ts = +( p.creation_timestamp || ( media[ 0 ] && media[ 0 ].creation_timestamp ) || 0 );
						var cap = fixText( p.title || ( media[ 0 ] && media[ 0 ].title ) || '' ).slice( 0, B.maxCaption );
						var key = 'ig:' + ts + ':' + ( firstImage || photos[ 0 ] ).split( '/' ).pop().replace( /[^A-Za-z0-9_.-]/g, '' ).slice( 0, 80 );
						if ( seen[ key ] ) { return; }
						seen[ key ] = 1;
						imp.posts.push( { ts: ts, caption: cap, photos: photos.slice( 0, B.maxPhotos ), cover: firstImage, extra: Math.max( 0, photos.length - B.maxPhotos ), key: key, on: true } );
					} );
				} );
				imp.posts.sort( function ( a, b ) { return a.ts - b.ts; } );
				imp.entries = entries;
				imp.state = imp.posts.length ? 'ready' : 'empty';
				viewImport();
			} );
		} ).catch( function ( e ) {
			imp.state = 'error';
			imp.error = e.message === 'html' ? __( 'This download is the HTML version. Ask Instagram for a new download and choose JSON as the format.', 'stubli' ) :
				e.message === 'notzip' ? __( 'That file isn\'t a zip file. Choose the file Instagram sent you, without unpacking it.', 'stubli' ) :
				e.message === 'noposts' ? __( 'There are no posts in this download. When you request it, tick Posts (or Content).', 'stubli' ) :
				__( 'This download couldn\'t be read here. Try it on a computer with a current browser.', 'stubli' );
			viewImport();
		} );
	}
	function when( ts ) { return ts ? new Date( ts * 1000 ).toLocaleDateString( undefined, { month: 'short', year: 'numeric' } ) : ''; }
	function viewImport() {
		var s = imp, body = '';
		if ( ! s || s.state === 'error' ) {
			body = ( '<p class="muted">' + __( 'Instagram lets you download everything you\'ve posted. Stubli reads that download on this device and brings your photos over, with their original dates and captions.', 'stubli' ) + '</p>' ) +
				( '<h2 class="a-label">' + __( '1. Ask Instagram for your download', 'stubli' ) + '</h2><ol class="a-steps"><li>' + __( 'In Instagram, open your profile, the menu, then', 'stubli' ) + ' <strong>' + __( 'Accounts Centre', 'stubli' ) + '</strong>.</li><li>' + __( 'Choose', 'stubli' ) + ' <strong>' + __( 'Your information and permissions', 'stubli' ) + '</strong> → <strong>' + __( 'Download your information', 'stubli' ) + '</strong>.</li><li>' + __( 'Pick', 'stubli' ) + ' <strong>' + __( 'Some of your information', 'stubli' ) + '</strong> → <strong>' + __( 'Posts', 'stubli' ) + '</strong>' + __( ', then', 'stubli' ) + ' <strong>' + __( 'Download to device', 'stubli' ) + '</strong>.</li><li>' + __( 'Set', 'stubli' ) + ' <strong>' + __( 'Date range: All time', 'stubli' ) + '</strong>, <strong>' + __( 'Format: JSON', 'stubli' ) + '</strong> ' + __( 'and', 'stubli' ) + ' <strong>' + __( 'Media quality: High', 'stubli' ) + '</strong>.</li></ol>' ) +
				( '<p class="a-help">' + __( 'Instagram emails you when it\'s ready, which can take a few hours. Big accounts may get several zip files: choose them all.', 'stubli' ) + '</p>' ) +
				( '<h2 class="a-label">' + __( '2. Choose the zip file', 'stubli' ) + '</h2><label class="a-btn">' ) + icon( 'plus', 18 ) + ( __( 'Choose zip files', 'stubli' ) + '<input class="a-file" type="file" accept=".zip,application/zip" multiple data-zip></label>' ) +
				( s && s.error ? '<div class="a-notice" role="alert"><p>' + esc( s.error ) + '</p></div>' : '' );
		} else if ( s.state === 'scanning' ) {
			body = ( '<div class="a-notice calm" aria-live="polite"><p>' + __( 'Reading your download…', 'stubli' ) + '</p></div>' );
		} else if ( s.state === 'empty' ) {
			body = ( '<div class="a-notice calm"><p>' + __( 'No photo or video posts found in this download.', 'stubli' ) + '</p></div>' );
		} else if ( s.state === 'ready' ) {
			var on = s.posts.filter( function ( p ) { return p.on; } ).length;
			body = '<p>' + _f( __( 'Found %s from %s to %s.', 'stubli' ), '<strong>' + _f( __( '%s posts', 'stubli' ), s.posts.length ) + '</strong>', esc( when( s.posts[ 0 ].ts ) ), esc( when( s.posts[ s.posts.length - 1 ].ts ) ) ) +
				( s.videos ? ' ' + ( ( B.videoTypes || [] ).length ? __( 'Videos up to a minute come along; longer ones stay behind.', 'stubli' ) : esc( B.noVideo || '' ) + ' ' + __( 'So the videos in this download stay behind.', 'stubli' ) ) : '' ) + '</p>' +
				( '<label class="a-label" for="a-impcol">' + __( 'Put them in this collection', 'stubli' ) + '</label><input id="a-impcol" class="a-field" maxlength="40" value="' ) + esc( s.collection ) + '">' +
				( '<div class="a-row" style="margin-top:18px"><h2 class="a-label">' + __( 'Choose posts', 'stubli' ) + '</h2><button type="button" class="a-text-btn" data-all>' ) + ( on === s.posts.length ? __( 'Select none', 'stubli' ) : __( 'Select all', 'stubli' ) ) + '</button></div>' +
				'<div class="a-thumbs a-imp-grid">' + s.posts.map( function ( p, i ) {
					return '<button type="button" class="a-thumb a-imp' + ( p.on ? ' is-on' : '' ) + '" data-tog="' + i + '" aria-pressed="' + p.on + '" aria-label="' + esc( when( p.ts ) + ( p.caption ? ': ' + p.caption.slice( 0, 60 ) : '' ) ) + '"><img alt="" data-src="' + i + '">' + ( p.photos.length > 1 ? '<span class="a-order">' + p.photos.length + '</span>' : '' ) + ( ! p.cover ? '<span class="a-vid-dur" aria-hidden="true">' + icon( 'play', 12 ) + ( __( 'Video', 'stubli' ) + '</span>' ) : '' ) + '<span class="a-imp-check" aria-hidden="true">' + icon( 'check', 16 ) + '</span></button>';
				} ).join( '' ) + '</div>' +
				( '<p class="a-help" style="margin-top:14px">' + __( 'Captions and dates come along. Comments and likes stay on Instagram. Location details are removed. Keep this screen open while it works.', 'stubli' ) + '</p>' ) +
				'<button type="button" class="a-btn wide" style="margin-top:12px" data-go-import' + ( on ? '' : ' disabled' ) + '>' + ( on === 1 ? __( 'Import 1 post', 'stubli' ) : _f( __( 'Import %s posts', 'stubli' ), on ) ) + '</button>';
		} else if ( s.state === 'running' ) {
			var total = s.queue.length;
			body = '<div class="a-notice calm" aria-live="polite"><p>' + _f( __( 'Importing %s of %s…', 'stubli' ), Math.min( s.done + 1, total ), total ) + '</p><div class="a-progress"><span style="width:' + Math.round( 100 * s.done / total ) + '%"></span></div></div>' +
				( '<p class="a-help">' + __( 'You can keep using your phone, but leave this screen open. If it stops, start again: posts already imported are skipped.', 'stubli' ) + '</p><button type="button" class="a-btn quiet" data-stop style="margin-top:8px">' + __( 'Stop', 'stubli' ) + '</button>' );
		} else if ( s.state === 'done' ) {
			var ok = s.ok || 0;
			body = '<div class="a-done" style="padding-top:12px"><span class="a-tick">' + icon( 'check', 28 ) + '</span><h2>' + ( s.stop ? __( 'Stopped', 'stubli' ) : __( 'Done', 'stubli' ) ) + '</h2><p class="muted">' + ( s.collection && ok ? ( ok === 1 ? _f( __( '1 post imported into %s.', 'stubli' ), esc( s.collection ) ) : _f( __( '%s posts imported into %s.', 'stubli' ), ok, esc( s.collection ) ) ) : ( ok === 1 ? __( '1 post imported.', 'stubli' ) : _f( __( '%s posts imported.', 'stubli' ), ok ) ) ) + ( s.dup ? ' ' + ( s.dup === 1 ? __( '1 was already on your page, so nothing was doubled.', 'stubli' ) : _f( __( '%s were already on your page, so nothing was doubled.', 'stubli' ), s.dup ) ) : '' ) +
				( s.failed.length ? ' ' + _f( __( '%s couldn\'t be imported; try again to retry them.', 'stubli' ), s.failed.length ) : '' ) + ( s.long ? ' ' + ( s.long === 1 ? __( 'One video was longer than a minute and stayed behind.', 'stubli' ) : _f( __( '%s videos were longer than a minute and stayed behind.', 'stubli' ), s.long ) ) : '' ) + ( s.bad ? ' ' + ( s.bad === 1 ? __( 'One video couldn\'t be read and stayed behind.', 'stubli' ) : _f( __( '%s videos couldn\'t be read and stayed behind.', 'stubli' ), s.bad ) ) : '' ) + ( s.novid ? ' ' + ( s.novid === 1 ? __( 'One video stayed behind, because your site can\'t take videos.', 'stubli' ) : _f( __( '%s videos stayed behind, because your site can\'t take videos.', 'stubli' ), s.novid ) ) : '' ) + ( s.stop && s.queue.length ? ' ' + _f( __( '%s still to go.', 'stubli' ), s.queue.length ) : '' ) + '</p><div class="a-actions"><a class="a-btn" href="' + esc( B.home ) + ( '">' + __( 'See your page', 'stubli' ) + '</a>' ) + ( s.failed.length || s.stop ? ( '<button type="button" class="a-btn quiet" data-retry>' + __( 'Try again', 'stubli' ) + '</button>' ) : '' ) + '</div></div>';
		}
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="' + esc( __( 'Back', 'stubli' ) ) + '">' + icon( 'back', 22 ) + ( '</a><h1>' + __( 'From Instagram', 'stubli' ) + '</h1><span></span></header><main class="a-compose" id="main">' ) + body + '</main>';
		var z = $( '[data-zip]' );
		if ( z ) { z.addEventListener( 'change', function () { if ( z.files.length ) { scanImport( z.files ); } } ); }
		var col = $( '#a-impcol' );
		if ( col ) { col.addEventListener( 'input', function () { s.collection = col.value; } ); }
		$$( '[data-tog]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var p = s.posts[ +b.dataset.tog ]; p.on = ! p.on; b.classList.toggle( 'is-on', p.on ); b.setAttribute( 'aria-pressed', p.on ); updateCount(); } ); } );
		var all = $( '[data-all]' );
		if ( all ) { all.addEventListener( 'click', function () { var on = s.posts.every( function ( p ) { return p.on; } ); s.posts.forEach( function ( p ) { p.on = ! on; } ); viewImport(); } ); }
		var go = $( '[data-go-import]' );
		if ( go ) { go.addEventListener( 'click', function () { runImport( s.posts.filter( function ( p ) { return p.on; } ) ); } ); }
		var st = $( '[data-stop]' );
		if ( st ) { st.addEventListener( 'click', function () { s.stop = true; st.disabled = true; st.textContent = __( 'Stopping after this post…', 'stubli' ); } ); }
		var rt = $( '[data-retry]' );
		if ( rt ) { rt.addEventListener( 'click', function () { runImport( s.queue ); } ); }
		lazyThumbs();
	}
	function updateCount() {
		var on = imp.posts.filter( function ( p ) { return p.on; } ).length, go = $( '[data-go-import]' );
		go.disabled = ! on; go.textContent = on === 1 ? __( 'Import 1 post', 'stubli' ) : _f( __( 'Import %s posts', 'stubli' ), on );
	}
	var thumbUrls = [];
	function lazyThumbs() {
		var imgs = $$( 'img[data-src]' );
		if ( ! imgs.length ) { return; }
		var load = function ( img ) {
			var p = imp.posts[ +img.dataset.src ];
			img.removeAttribute( 'data-src' );
			if ( ! p.cover ) { return; }
			zipRead( imp.entries[ p.cover ], mime( p.cover ) ).then( function ( b ) { var u = URL.createObjectURL( b ); thumbUrls.push( u ); img.src = u; } ).catch( function () {} );
		};
		if ( ! ( 'IntersectionObserver' in window ) ) { imgs.slice( 0, 60 ).forEach( load ); return; }
		var io = new IntersectionObserver( function ( list ) { list.forEach( function ( e ) { if ( e.isIntersecting ) { io.unobserve( e.target ); load( e.target ); } } ); }, { rootMargin: '400px' } );
		imgs.forEach( function ( i ) { io.observe( i ); } );
	}
	function runImport( queue ) {
		var s = imp, lock = null;
		thumbUrls.forEach( URL.revokeObjectURL ); thumbUrls = [];
		s.queue = queue; s.done = 0; s.ok = 0; s.dup = 0; s.long = 0; s.bad = 0; s.novid = 0; s.failed = []; s.stop = false; s.state = 'running';
		if ( navigator.wakeLock ) { navigator.wakeLock.request( 'screen' ).then( function ( l ) { lock = l; }, function () {} ); }
		viewImport();
		var next = function () {
			if ( s.stop || s.done >= queue.length ) {
				if ( lock ) { lock.release().catch( function () {} ); }
				if ( s.stop ) { s.queue = queue.slice( s.done ).concat( s.failed ); s.failed = []; }
				s.state = 'done'; viewImport(); return;
			}
			var p = queue[ s.done ];
			Promise.all( p.photos.map( function ( n ) {
				if ( /^video/.test( mime( n ) ) ) {
					if ( ! canVideo( mime( n ) ) ) { s.novid++; return null; }
					return zipRead( s.entries[ n ], mime( n ) ).then( function ( blob ) {
						return videoInfo( blob ).catch( function () { return videoInfoFromFile( blob ); } ).then( function ( v ) {
							URL.revokeObjectURL( v.url ); URL.revokeObjectURL( v.thumb );
							if ( v.duration > MAX_VIDEO + 0.5 ) { s.long++; return null; }
							return stripLocation( blob ).then( function ( clean ) { return { video: clean, poster: v.poster, ext: /quicktime/.test( mime( n ) ) ? '.mov' : '.mp4' }; } );
						} );
					} ).catch( function () { s.bad++; return null; } );
				}
				return zipRead( s.entries[ n ], mime( n ) ).then( resized );
			} ) ).then( function ( blobs ) {
				blobs = blobs.filter( Boolean );
				if ( ! blobs.length ) { return { skipped: true }; }
				var fd = new FormData();
				blobs.forEach( function ( b, i ) {
					if ( b.video ) { fd.append( 'photos[]', b.video, 'instagram-' + ( i + 1 ) + b.ext ); fd.append( 'posters[' + i + ']', b.poster, 'instagram-' + ( i + 1 ) + '-cover.jpg' ); }
					else { fd.append( 'photos[]', b, 'instagram-' + ( i + 1 ) + '.jpg' ); }
					fd.append( 'alts[]', '' );
				} );
				fd.append( 'caption', p.caption );
				fd.append( 'collection', ( s.collection || '' ).trim() );
				fd.append( 'date', String( p.ts ) );
				fd.append( 'import_key', p.key );
				return send( 'POST', 'posts', fd );
			} ).then( function ( r ) { if ( r && r.skipped ) { return; } if ( r && r.duplicate ) { s.dup++; } else { s.ok++; } }, function () { s.failed.push( p ); } ).then( function () {
				s.done++;
				var t = $( '.a-notice p' ), bar = $( '.a-progress span' );
				if ( t ) { t.textContent = _f( __( 'Importing %s of %s…', 'stubli' ), Math.min( s.done + 1, queue.length ), queue.length ); }
				if ( bar ) { bar.style.width = Math.round( 100 * s.done / queue.length ) + '%'; }
				next();
			} );
		};
		next();
	}

	/* ── Shared bits: Photo/Journal switch, Cancel ── */
	function bindCommon() {
		$$( '[data-go]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { location.hash = b.dataset.go; } ); } );
		var c = $( '[data-cancel]' );
		if ( c ) {
			c.addEventListener( 'click', function () {
				if ( post && post.photos.length && ! c.dataset.sure ) {
					c.dataset.sure = '1'; c.textContent = __( 'Discard?', 'stubli' ); c.setAttribute( 'aria-label', __( 'Discard this post. Press again to confirm.', 'stubli' ) );
					setTimeout( function () { if ( c.isConnected ) { delete c.dataset.sure; c.textContent = __( 'Cancel', 'stubli' ); c.removeAttribute( 'aria-label' ); } }, 3000 );
					return;
				}
				if ( post ) { post.photos.forEach( function ( ph ) { URL.revokeObjectURL( ph.thumb ); } ); }
				post = null; location.hash = '#home';
			} );
		}
		var cj = $( '[data-cancel-j]' );
		if ( cj ) { cj.addEventListener( 'click', function () { location.hash = '#home'; } ); }
	}

	route();
}() );
