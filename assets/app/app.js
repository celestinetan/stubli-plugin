/* Stubli app: feed, composer, journal, Discover, What's new, setup wizard. No dependencies. */
( function () {
	'use strict';
	var B = window.STUBLI || {};
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
		photo: '<rect x="3.5" y="5" width="17" height="14" rx="3"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 2.5-2 4.5 3.5"/>',
		lock: '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
		check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		portrait: '<rect x="7" y="4" width="10" height="16" rx="2"/>',
		square: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
		landscape: '<rect x="3" y="7.5" width="18" height="9" rx="2"/>',
		play: '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
		chev: '<path d="M9 6l6 6-6 6"/>',
		menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
		book: '<path d="M12 6.5C10.3 5.2 7.9 4.5 4 4.5v14c3.9 0 6.3.7 8 2 1.7-1.3 4.1-2 8-2v-14c-3.9 0-6.3.7-8 2z"/><path d="M12 6.5v14"/>',
		pen: '<path d="M15.5 4.5l4 4L9 19l-5 1 1-5z"/><path d="M13 7l4 4"/>',
		mark: '<path d="M6.5 3.5h11v17L12 16.5l-5.5 4z"/>'
	};
	function icon( n, w ) {
		return '<svg viewBox="0 0 24 24" width="' + ( w || 22 ) + '" height="' + ( w || 22 ) + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + I[ n ] + '</svg>';
	}
	function wordmark() {
		return '<span class="a-brand"><span class="a-wordmark" role="img" aria-label="Stubli"><span aria-hidden="true">st</span><svg viewBox="0 0 60 80" aria-hidden="true"><path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/></svg><span aria-hidden="true">bli</span></span><span class="a-beta">Beta</span></span>';
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
		var d = daypart(), s = size || 44, id = 'a-sky-clip' + ( ++skyN );
		var f = function ( n ) { return ( +n ).toFixed( 1 ); };
		var ray = function ( cx, cy, a, r1, r2 ) { var c = Math.cos( a * Math.PI / 180 ), n = Math.sin( a * Math.PI / 180 ); return '<line x1="' + f( cx + r1 * c ) + '" y1="' + f( cy + r1 * n ) + '" x2="' + f( cx + r2 * c ) + '" y2="' + f( cy + r2 * n ) + '"/>'; };
		var star = function ( x, y, r, i ) { return '<path class="a-sky-star a-sky-star' + i + '" d="M' + x + ' ' + ( y - r ) + 'Q' + x + ' ' + y + ' ' + ( x + r ) + ' ' + y + 'Q' + x + ' ' + y + ' ' + x + ' ' + ( y + r ) + 'Q' + x + ' ' + y + ' ' + ( x - r ) + ' ' + y + 'Q' + x + ' ' + y + ' ' + x + ' ' + ( y - r ) + 'z" fill="currentColor" stroke="none"/>'; };
		// The Stubli smile: two dots and a curve. Asleep at night.
		var face = function ( cx, cy, r, asleep ) {
			var ex = r * 0.36, ey = cy - r * 0.16, er = Math.max( 0.95, r * 0.13 ), m = r * 0.38;
			var eyes = asleep ?
				'<path d="M' + f( cx - ex - er * 1.3 ) + ' ' + f( ey ) + 'q' + f( er * 1.3 ) + ' ' + f( er * 1.4 ) + ' ' + f( er * 2.6 ) + ' 0M' + f( cx + ex - er * 1.3 ) + ' ' + f( ey ) + 'q' + f( er * 1.3 ) + ' ' + f( er * 1.4 ) + ' ' + f( er * 2.6 ) + ' 0" stroke-width="1.3"/>' :
				'<circle cx="' + f( cx - ex ) + '" cy="' + f( ey ) + '" r="' + f( er ) + '" fill="currentColor" stroke="none"/><circle cx="' + f( cx + ex ) + '" cy="' + f( ey ) + '" r="' + f( er ) + '" fill="currentColor" stroke="none"/>';
			return eyes + '<path d="M' + f( cx - m ) + ' ' + f( cy + r * 0.18 ) + 'a' + f( m ) + ' ' + f( m ) + ' 0 0 0 ' + f( 2 * m ) + ' 0" stroke-width="1.5"/>';
		};
		var ridge = 'M2 37L11 27.5L15 31L22 22.5L28.5 30L32 26.5L42 37';
		var alps = '<path class="a-sky-alps" d="' + ridge + '"/><path d="M19.9 25.1L21.2 25.9L22.3 24.9L23.3 25.8L24.1 25" stroke-width="1.1" opacity=".7"/>';
		var clip = '<defs><clipPath id="' + id + '"><path d="M0 0H44V37H42L32 26.5L28.5 30L22 22.5L15 31L11 27.5L2 37H0Z"/></clipPath></defs>';
		var body;
		if ( d === 'afternoon' ) {
			body = '<g clip-path="url(#' + id + ')"><g class="a-sky-rays">' + [ 0, 45, 90, 135, 180, 225, 270, 315 ].map( function ( a ) { return ray( 22, 13, a, 9.5, 12.5 ); } ).join( '' ) + '</g><circle cx="22" cy="13" r="7"/>' + face( 22, 13, 7 ) + '</g>' + alps;
		} else if ( d === 'morning' ) {
			body = '<g clip-path="url(#' + id + ')"><g class="a-sky-rise"><circle cx="32.5" cy="22.5" r="6.5"/>' + face( 32.5, 22.5, 6.5 ) + [ 215, 250, 290, 325 ].map( function ( a ) { return ray( 32.5, 22.5, a, 9, 12 ); } ).join( '' ) + '</g></g>' + alps;
		} else if ( d === 'evening' ) {
			body = '<g clip-path="url(#' + id + ')"><path class="a-sky-glow" d="M1.5 24.5a10 10 0 0 1 20 0" stroke-dasharray="1.5 3.2"/><g class="a-sky-set"><circle cx="11.5" cy="23.5" r="6"/>' + face( 11.5, 23.5, 6 ) + '</g></g>' + alps;
		} else {
			body = '<circle cx="25" cy="13" r="7.5"/>' + face( 25, 13, 7.5, true ) + star( 8, 9, 3.2, 1 ) + star( 39, 22, 2.6, 2 ) + star( 12, 19.5, 2.1, 3 ) + '<g opacity=".6">' + alps + '</g>';
		}
		return '<svg class="a-sky a-sky-' + d + '" width="' + s + '" height="' + s + '" viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + clip + body + '</svg>';
	}
	function endLine() {
		return { morning: 'Have a lovely morning.', afternoon: 'Enjoy the rest of your afternoon.', evening: 'Have a calm evening.', night: 'Sleep well.' }[ daypart() ];
	}
	document.addEventListener( 'visibilitychange', function () { document.documentElement.classList.toggle( 'a-hidden', document.hidden ); } );
	function greeting() {
		var part = { morning: 'Good morning', afternoon: 'Good afternoon', evening: 'Good evening', night: 'Good evening' }[ daypart() ];
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
					return refreshNonce().then( function () { resolve( send( method, path, body instanceof FormData ? body : JSON.parse( body ), onProgress, true ) ); }, function () { reject( { message: 'Your session ended. Please sign in again and try once more.' } ); } );
				}
				reject( { message: ( d && d.message ) || 'Something went wrong on the way. Try again in a moment.', code: d && d.code } );
			};
			x.onerror = function () { reject( { message: 'No connection right now. Try again when you\'re back online.', code: 'offline' } ); };
			x.send( body );
		} );
	}

	/* ── Photos: load, thumbnail, crop (re-encoding drops location data) ── */
	var RATIOS = { portrait: 4 / 5, square: 1, landscape: 1.91 };
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
	var journal = { text: store.get( 'stubli-journal-draft' ) || '', visibility: store.get( 'stubli-journal-vis' ) || 'everyone', tool: '', busy: false, error: '', uploading: 0, photos: [] };
	try { journal.photos = JSON.parse( store.get( 'stubli-journal-photos' ) || '[]' ) || []; } catch ( e ) { journal.photos = []; }
	function saveJournalDraft() { store.set( 'stubli-journal-draft', journal.text ); store.set( 'stubli-journal-photos', JSON.stringify( journal.photos ) ); }

	/* ── Router ── */
	function route() {
		var h = ( location.hash || '#home' ).slice( 1 );
		window.scrollTo( 0, 0 );
		if ( h === 'new' ) { if ( ! post ) { newPost(); } return viewPick(); }
		if ( h === 'new/details' ) { if ( ! post || ! post.photos.length ) { location.replace( '#new' ); return; } return viewDetails(); }
		if ( h === 'journal' ) { return viewJournal(); }
		if ( h === 'you' ) { return viewYou(); }
		if ( h === 'more' ) { return viewMore(); }
		if ( h === 'about' ) { return viewAbout(); }
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
			[ 'home', '#home', 'Home', 'home' ],
			[ 'discover', '#discover', 'Discover', 'discover' ],
			[ 'new', '#new', 'New post', 'plus' ],
			[ 'whatsnew', '#whatsnew', 'What\'s new', 'news' ],
			[ 'you', '#you', 'You', 'you' ]
		];
		return '<div class="a-bar"><nav aria-label="Stubli">' + items.map( function ( i ) {
			var waiting = ( B.waiting || 0 ) + ( B.requests || 0 ), dot = i[ 0 ] === 'whatsnew' && waiting > 0;
			return '<a href="' + esc( i[ 1 ] ) + '"' + ( i[ 0 ] === active ? ' aria-current="page"' : '' ) + ' aria-label="' + esc( i[ 2 ] + ( dot ? ', ' + waiting + ' waiting for your OK' : '' ) ) + '">' + icon( i[ 3 ], 25 ) + ( dot ? '<span class="a-dot" aria-hidden="true"></span>' : '' ) + '</a>';
		} ).join( '' ) + '</nav></div>';
	}

	/* ── Home ── */
	function isStandalone() { return ( window.matchMedia && matchMedia( '(display-mode: standalone)' ).matches ) || navigator.standalone; }
	function viewMore() {
		var ios = /iphone|ipad|ipod/i.test( navigator.userAgent );
		var host = String( B.home || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you" aria-label="Back">' + icon( 'back', 22 ) + '</a><h1>Settings and more</h1><span></span></header><main class="a-main" id="main">' +
			'<section class="a-card"><h2>Your page</h2><p class="muted">' + esc( host ) + '</p><div class="a-actions"><a class="a-btn" href="' + esc( B.home ) + '">See your page</a><a class="a-btn quiet" href="' + esc( B.settings ) + '">Profile and settings</a></div></section>' +
			summaryCard() +
			'<section class="a-card"><h2>Share something</h2><p class="muted">Photos go to your page and your followers\' feeds. Journal entries stay in your Journal tab.</p>' +
			'<div class="a-actions"><a class="a-btn" href="#new">' + icon( 'photo', 18 ) + 'New photo post</a><a class="a-btn quiet" href="#journal">Journal entry</a></div></section>' +
			'<section class="a-card" data-invite-card><h2>Invite a friend</h2><p class="muted">Send them a link. Once they\'ve set up their own Stubli, you follow each other automatically. Each link works once, for 30 days.</p>' +
			'<div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>Create an invite link</button></section>' +
			peopleCard() +
			'<section class="a-card"><h2>Bring your photos from Instagram</h2><p class="muted">Your posts arrive with their original dates and captions.</p><a class="a-btn quiet" href="#import">Start</a></section>' +
			keepCard() +
			'<section class="a-card"><h2>Tell us what you think</h2><p class="muted">Ideas, things that don\'t work, kind words: every note goes to the people who make Stubli.</p><a class="a-btn quiet" href="#feedback">Send feedback</a></section>' +
			( isStandalone() ? '' : '<section class="a-card"><h2>Put Stubli on your home screen</h2><p class="muted">' + ( ios ? 'In Safari, tap the Share button, then <strong>Add to Home Screen</strong>.' : 'Open your browser\'s menu, then choose <strong>Add to Home screen</strong> or <strong>Install app</strong>.' ) + ' It opens like an app, without the browser around it.</p></section>' ) +
			'<p class="a-help a-thanks">Stubli is free and always will be. Celestine makes it and pays for it; if you enjoy it, you can <a href="https://buymeacoffee.com/celestinetan" target="_blank" rel="noopener">leave him a tip</a>. It unlocks nothing; it just keeps Stubli going.</p>' +
			'</main>' + bar( 'you' );
		bindInvite(); bindFollow(); bindSummary( redrawSummary ); bindKeep(); bindPeople();
		if ( ! pp.loaded && ! pp.loading ) { pp.loading = true; send( 'GET', 'follows', null ).then( function ( r ) { pp.list = Array.isArray( r ) ? r : []; pp.loaded = true; pp.loading = false; if ( location.hash === '#more' ) { redrawPeople(); } }, function () { pp.loading = false; } ); }
		bindMove();
		if ( ! mv.loaded ) { mv.loaded = true; Promise.all( [ send( 'GET', 'move/link', null ), send( 'GET', 'move/in', null ) ] ).then( function ( r ) { mv.out = r[ 0 ]; mv.inState = r[ 1 ]; if ( r[ 1 ].state === 'moving' ) { runMove( function () { if ( location.hash === '#more' ) { redrawMove(); } } ); } if ( location.hash === '#more' ) { redrawMove(); } }, function () {} ); }
		if ( ! kp.loaded && ! kp.loading ) { kp.loading = true; send( 'GET', 'archive', null ).then( function ( r ) { kp.ar = r; kp.loaded = true; kp.loading = false; if ( r.state === 'packing' ) { packArchive(); } else if ( location.hash === '#more' ) { redrawKeep(); } }, function () { kp.loaded = true; kp.loading = false; } ); }
		if ( ! ds.loaded && ! ds.loading ) { ds.loading = true; loadSummary().then( function () { ds.loading = false; if ( location.hash === '#more' ) { redrawSummary(); } } ); }
	}


	/* ── You: your own profile, like your page (agreed 22 Oct) ── */
	var me = { p: null, loading: false, i: 0, coll: '', inviteOpen: false };
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
			var inner = c.bio ? bioHtml : '<p class="a-pcard-q">' + esc( c.q ) + '</p><p class="a-pcard-a">' + esc( c.a ) + '</p>' + ( opts && opts.reply ? opts.reply( k - 1 ) : '' );
			return '<div class="a-pcard' + ( c.bio ? ' a-pcard-bio' : '' ) + '" data-pos="' + ( pos > 2 ? 'hide' : pos ) + '"' + ( front ? '' : ' aria-hidden="true" inert' ) + '>' + inner + '</div>';
		} ).join( '' );
		var nav = n > 1 ? '<div class="a-stack-nav"><button type="button" class="a-stack-btn" data-stack-prev aria-label="Previous card">' + icon( 'back', 18 ) + '</button><span class="a-stack-dots" aria-hidden="true">' + deck.map( function ( _, k ) { return '<span' + ( k === i ? ' class="is-on"' : '' ) + '></span>'; } ).join( '' ) + '</span><button type="button" class="a-stack-btn" data-stack-next aria-label="Next card">' + icon( 'chev', 18 ) + '</button><span class="sr" aria-live="polite" data-stack-status></span></div>' : '';
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
		if ( ! list.length ) { return '<div class="a-empty-you">' + smile() + '<p>' + ( me.coll ? 'Nothing in ' + esc( me.coll ) + ' yet.' : 'Your page is waiting for its first photo.' ) + '</p><a class="a-btn quiet" href="#new">Post a photo</a></div>'; }
		var firstMissing = mine.items.filter( function ( i ) { return i.missing; } )[ 0 ];
		return ( mine.missing && firstMissing && ! me.coll ? '<div class="a-notice calm"><p>' + ( mine.missing === 1 ? 'One photo has' : mine.missing + ' photos have' ) + ' no description yet. A few words help people who use screen readers.</p><a class="a-btn quiet" href="#edit/' + firstMissing.id + '">Add descriptions</a></div>' : '' ) +
			'<div class="a-mine-grid">' + list.map( function ( i ) {
				var label = ( i.title || ( i.video ? 'Video post' : 'Photo post' ) ) + ( i.count > 1 ? ', ' + i.count + ' photos' : '' ) + ( i.missing ? ', needs a description' : '' ) + ( i.friends ? ', friends only' : '' );
				return '<a href="#edit/' + i.id + '" aria-label="Edit: ' + esc( label ) + '">' + ( i.thumb ? '<img src="' + esc( i.thumb ) + '" alt="" loading="lazy">' : '' ) +
					( i.count > 1 ? '<span class="a-mine-n" aria-hidden="true">' + i.count + '</span>' : i.video ? '<span class="a-mine-n" aria-hidden="true">' + icon( 'play', 12 ) + '</span>' : '' ) +
					( i.friends ? '<span class="a-mine-friends" aria-hidden="true">Friends</span>' : '' ) + '</a>';
			} ).join( '' ) + '</div>' +
			( mine.more && ! me.coll ? '<button type="button" class="a-btn quiet wide" data-mine-more style="margin-top:12px">Show more</button>' : '' );
	}
	function smile() {
		return '<svg class="a-smile" viewBox="0 0 60 80" width="26" height="35" aria-hidden="true" focusable="false"><path d="M10.5 35.5V52a19.5 19.5 0 0 0 39 0V35.5" fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round"/><circle cx="19" cy="15" r="6.2" fill="currentColor"/><circle cx="41" cy="15" r="6.2" fill="currentColor"/></svg>';
	}
	function viewYou() {
		if ( ! me.p || ! mine.loaded ) {
			root.innerHTML = '<main class="a-main" id="main"><div class="a-top a-top-row">' + wordmark() + '<a class="a-text-btn" href="#more" aria-label="Settings and more">' + icon( 'menu', 22 ) + '</a></div><h1 class="sr">You</h1><div class="a-feed-skel" aria-hidden="true"><span style="height:150px"></span><span style="height:220px"></span></div><p class="sr" role="status">Loading…</p></main>' + bar( 'you' );
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
			( p.bio ? '<p class="a-you-bio">' + esc( unent( p.bio ) ) + '</p>' : '<p class="a-you-bio muted"><a href="#about">Add a line about you</a></p>' ) +
			( p.prompts && p.prompts.length ? '' : '<a class="a-text-btn a-you-add" href="#about">Add cards about you</a>' ) ) : '';
		var latest = mine.loaded && mine.items[ 0 ];
		var colls = [];
		mine.items.forEach( function ( i ) { if ( i.coll && colls.indexOf( i.coll ) < 0 ) { colls.push( i.coll ); } } );
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top a-top-row">' + wordmark() + '<a class="a-text-btn" href="#more" aria-label="Settings and more">' + icon( 'menu', 22 ) + '</a></div>' +
			'<h1 class="sr">You</h1>' +
			'<div data-you-stack>' + ( p ? stackHtml( deckOf( p ), me.i, bio, null ) : '<div class="a-feed-skel" aria-hidden="true"><span style="height:150px"></span></div>' ) + '</div>' +
			'<div class="a-you-btns"><a class="a-btn quiet" href="#about">Edit page</a><button type="button" class="a-btn quiet" data-share>Share</button><button type="button" class="a-btn" data-invite-toggle aria-expanded="' + me.inviteOpen + '">Invite</button></div>' +
			'<section class="a-card" data-invite-card' + ( me.inviteOpen ? '' : ' hidden' ) + '><p class="muted" style="margin-top:0">Send a friend a link. Once they\'ve set up their own Stubli, you follow each other automatically. Each link works once, for 30 days.</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>Create an invite link</button></section>' +
			( latest ? '<a class="a-latest" href="' + esc( latest.url || ( '#edit/' + latest.id ) ) + '">' + ( latest.thumb ? '<img src="' + esc( latest.thumb ) + '" alt="" width="44" height="44">' : '' ) + '<span><span class="muted">Latest · ' + esc( gentle( latest.date ) ) + '</span><span class="a-latest-t">' + esc( latest.title || 'Photo post' ) + ( latest.coll ? ' <span class="muted">in ' + esc( latest.coll ) + '</span>' : '' ) + '</span></span>' + icon( 'chev', 18 ) + '</a>' : '' ) +
			'<div class="a-ctabs-row"><div class="a-ctabs" role="group" aria-label="Collections">' + [ '' ].concat( colls ).map( function ( c ) { return '<button type="button" class="a-ctab" aria-pressed="' + ( me.coll === c ) + '" data-coll="' + esc( c ) + '">' + esc( c || 'All' ) + '</button>'; } ).join( '' ) + '</div>' +
				'<a class="a-ctab a-ctab-icon" href="' + esc( B.journal ) + '" aria-label="Journal" title="Journal">' + icon( 'book', 18 ) + '</a>' +
				'<span class="a-ctabs-gap"></span>' +
				( p.guestbook ? '<a class="a-ctab a-ctab-icon a-ctab-green" href="' + esc( p.guestbook ) + '" aria-label="Guest book" title="Guest book">' + icon( 'pen', 18 ) + '</a>' : '' ) +
				'<a class="a-ctab a-ctab-icon a-ctab-green" href="' + esc( B.home + 'links/' ) + '" aria-label="Links" title="Links">' + icon( 'mark', 18 ) + '</a></div>' +
			'<div class="a-you-grid" data-you-grid>' + youGrid() + '</div>' +
			'</main>' + bar( 'you' );
		var redrawStack = function () { var w = $( '[data-you-stack]' ); if ( w ) { w.innerHTML = stackHtml( deckOf( me.p ), me.i, bio, null ); bindStack( function () { return me.i; }, function ( v ) { me.i = v; }, redrawStack ); } };
		bindStack( function () { return me.i; }, function ( v ) { me.i = v; }, redrawStack );
		bindInvite();
		var it = $( '[data-invite-toggle]' ); if ( it ) { it.addEventListener( 'click', function () { me.inviteOpen = ! me.inviteOpen; var c = $( '[data-invite-card]' ); c.hidden = ! me.inviteOpen; it.setAttribute( 'aria-expanded', me.inviteOpen ); if ( me.inviteOpen ) { var bb = $( '[data-invite]' ); if ( bb ) { bb.focus(); } } } ); }
		var sh = $( '[data-share]' ); if ( sh ) { sh.addEventListener( 'click', function () {
			if ( navigator.share ) { navigator.share( { title: name, url: B.home } ).catch( function () {} ); return; }
			var ok = function () { toast( 'Link to your page copied.' ); };
			if ( navigator.clipboard ) { navigator.clipboard.writeText( B.home ).then( ok, function () { toast( B.home ); } ); } else { toast( B.home ); }
		} ); }
		$$( '[data-coll]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { me.coll = b.dataset.coll; swap( '[data-you-grid]', '<div class="a-you-grid" data-you-grid>' + youGrid() + '</div>', bindYouGrid ); $$( '[data-coll]' ).forEach( function ( x ) { x.setAttribute( 'aria-pressed', x.dataset.coll === me.coll ); } ); } ); } );
		bindYouGrid();
	}
	function bindYouGrid() {
		var m = $( '[data-mine-more]' );
		if ( m ) { m.addEventListener( 'click', function () { m.disabled = true; m.textContent = 'Loading…'; mine.page++; loadMine( false ).then( function () { if ( location.hash === '#you' ) { viewYou(); } } ); } ); }
	}

	/* ── Edit page: your line and your prompt cards ── */
	var ab = null;
	function viewAbout() {
		if ( ! ab ) {
			ab = { loaded: false, cards: [], choices: [], max: 3, maxA: 150, bio: '', picking: null, own: '', busy: false, error: '' };
			root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit page</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:200px"></span></div></main>';
			Promise.all( [ send( 'GET', 'prompts', null ), me.p ? Promise.resolve( me.p ) : send( 'GET', 'profile', null ) ] ).then( function ( r ) {
				ab.cards = ( r[ 0 ].prompts || [] ).slice(); ab.choices = r[ 0 ].choices || []; ab.max = r[ 0 ].max || 3; ab.maxA = r[ 0 ].max_answer || 150;
				me.p = me.p || r[ 1 ]; ab.bio = String( r[ 1 ].bio || '' ); ab.loaded = true;
				if ( location.hash === '#about' ) { renderAbout(); }
			}, function ( e ) { ab = null; root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit page</h1><span></span></header><main class="a-compose" id="main"><div class="a-notice" role="alert"><p>' + esc( e.message ) + '</p><a class="a-btn quiet" href="#you">Back</a></div></main>'; } );
			return;
		}
		if ( ab.loaded ) { renderAbout(); }
	}
	function renderAbout() {
		var used = ab.cards.map( function ( c ) { return c.q; } );
		var picker = function ( slot ) {
			return '<div class="a-picker" role="group" aria-label="Choose a prompt">' + ab.choices.filter( function ( q ) { return used.indexOf( q ) < 0; } ).map( function ( q ) { return '<button type="button" class="a-chip" data-pick-q="' + esc( q ) + '" data-slot="' + slot + '">' + esc( q ) + '</button>'; } ).join( '' ) +
				'</div><div class="a-inline"><label class="sr" for="a-own-q">Your own prompt</label><input id="a-own-q" class="a-field" maxlength="80" placeholder="Or write your own…" value="' + esc( ab.own ) + '"><button type="button" class="a-btn quiet" data-own-q data-slot="' + slot + '">Use</button></div>' +
				'<button type="button" class="a-text-btn" data-pick-cancel>Cancel</button>';
		};
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit page</h1><button type="button" class="a-btn" data-ab-save' + ( ab.busy ? ' disabled' : '' ) + '>' + ( ab.busy ? 'Saving…' : 'Save' ) + '</button></header>' +
			'<main class="a-compose" id="main">' + ( ab.error ? '<div class="a-notice" role="alert"><p>' + esc( ab.error ) + '</p></div>' : '' ) +
			'<label class="a-label" for="a-ab-bio" style="margin-top:4px">One line about you</label><textarea id="a-ab-bio" class="a-field a-grow" rows="2" maxlength="160">' + esc( ab.bio ) + '</textarea><p class="a-count" data-ab-bio-n>' + ab.bio.length + ' / 160</p>' +
			'<h2 class="a-label">Cards about you</h2><p class="muted" style="margin:-4px 0 12px">Up to ' + ab.max + ' cards sit behind your bio on your page. Short answers work best, and people can reply to each one.</p>' +
			ab.cards.map( function ( c, k ) {
				return '<div class="a-card a-pedit">' + ( ab.picking === k ? picker( k ) :
					'<div class="a-row"><label class="a-label" style="margin:0" for="a-pa-' + k + '">' + esc( c.q ) + '</label><span><button type="button" class="a-text-btn" data-change="' + k + '">Change</button><button type="button" class="a-text-btn" data-remove="' + k + '" aria-label="Remove ' + esc( c.q ) + '">Remove</button></span></div>' +
					'<textarea id="a-pa-' + k + '" class="a-field a-grow a-pedit-a" rows="2" maxlength="' + ab.maxA + '" data-answer="' + k + '">' + esc( c.a ) + '</textarea><p class="a-count" data-answer-n="' + k + '">' + c.a.length + ' / ' + ab.maxA + '</p>' ) + '</div>';
			} ).join( '' ) +
			( ab.cards.length < ab.max ? ( ab.picking === 'new' ? '<div class="a-card a-pedit">' + picker( 'new' ) + '</div>' : '<button type="button" class="a-btn quiet wide" data-add-card>' + icon( 'plus', 18 ) + 'Add a card</button>' ) : '' ) +
			'</main>';
		var grow = function ( t ) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
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
			if ( empty ) { ab.error = 'Write an answer for "' + empty.q + '", or remove that card.'; renderAbout(); return; }
			ab.busy = true; ab.error = ''; renderAbout();
			Promise.all( [ send( 'POST', 'prompts', { prompts: ab.cards } ), send( 'POST', 'setup', { bio: ab.bio } ) ] ).then( function ( r ) {
				me.p = null; me.i = 0; ab = null; location.hash = '#you'; toast( 'Your page is updated.' );
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
		if ( /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( c ) ) { return { href: 'mailto:' + c, label: 'Write back by email' }; }
		if ( /^\+?[\d\s()\-]{7,}$/.test( c ) ) { return { href: 'tel:' + c.replace( /[^\d+]/g, '' ), label: 'Call or text' }; }
		return null;
	}
	function noteCard( n, isRequest ) {
		var d = nt.done[ n.id ];
		if ( d ) { return '<div class="a-card a-note is-done" data-note-card="' + n.id + '"><p role="status" style="margin:0">' + esc( d ) + '</p></div>'; }
		var who = n.kind === 'guest' ? 'no Stubli' : hostOf( n.site ), c = n.kind === 'guest' ? contactLink( n.contact ) : null;
		return '<div class="a-card a-note" data-note-card="' + n.id + '"><div class="a-wait-head">' + face( n.avatar, n.name, 36 ) +
			'<span><strong>' + esc( unent( n.name ) ) + '</strong> <span class="muted">' + esc( who ) + ( isRequest ? ' · Request' : '' ) + ' · ' + esc( gentle( n.date ) ) + '</span></span></div>' +
			( n.prompt ? '<p class="muted small a-note-re">Replying to “' + esc( n.prompt.q ) + '”</p>' : '' ) +
			'<p class="a-note-text">' + esc( n.text ) + '</p>' +
			( n.kind === 'guest' ? '<p class="a-note-contact">Reach ' + esc( unent( n.name ) ) + ' at <strong>' + esc( n.contact ) + '</strong></p>' : '' ) +
			'<div class="a-wait-acts">' +
			( isRequest ? '<button type="button" class="a-btn" data-note="accept" data-id="' + n.id + '">' + ( n.kind === 'guest' ? 'Keep' : 'Accept' ) + '</button>' : '' ) +
			( ! isRequest && n.kind !== 'guest' ? '<a class="a-btn" href="#note/' + n.id + '">' + ( n.replied ? 'Reply again' : 'Reply' ) + '</a>' : '' ) +
			( ! isRequest && c ? '<a class="a-btn" href="' + esc( c.href ) + '">' + esc( c.label ) + '</a>' : '' ) +
			'<button type="button" class="a-btn quiet" data-note="delete" data-id="' + n.id + '">Delete</button>' +
			'<button type="button" class="a-text-btn a-block" data-note="block" data-id="' + n.id + '">Block</button></div></div>';
	}
	function notesSection() {
		if ( ! nt.loaded || ( ! nt.inbox.length && ! nt.requests.length ) ) { return ''; }
		var open = nt.requests.filter( function ( n ) { return ! nt.done[ n.id ]; } ).length + nt.inbox.filter( function ( n ) { return ! nt.done[ n.id ]; } ).length;
		return '<h2 class="a-label">Notes' + ( open ? ' · ' + open : '' ) + '</h2>' + nt.requests.map( function ( n ) { return noteCard( n, true ); } ).join( '' ) + nt.inbox.slice( 0, 20 ).map( function ( n ) { return noteCard( n, false ); } ).join( '' );
	}
	function bindNotes( redraw ) {
		$$( '[data-note]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = +b.dataset.id, act = b.dataset.note;
				$$( '[data-note][data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = true; } );
				send( 'POST', 'notes/' + id, { action: act } ).then( function () {
					var n = nt.requests.concat( nt.inbox ).filter( function ( x ) { return x.id === id; } )[ 0 ];
					if ( act === 'accept' && n ) { nt.requests = nt.requests.filter( function ( x ) { return x.id !== id; } ); nt.inbox.unshift( n ); }
					else { nt.done[ id ] = act === 'block' ? 'Blocked quietly. Nothing more from them will arrive, and they aren\'t told.' : 'Deleted.'; }
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
			root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#whatsnew">Close</a><h1>Reply</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:160px"></span></div></main>';
			loadNotes().then( function () { if ( nt.inbox.some( function ( x ) { return x.id === id; } ) ) { viewNote( id ); } else { location.replace( '#whatsnew' ); } } );
			return;
		}
		if ( ! nc || nc.id !== id ) { nc = { id: id, text: '', busy: false, error: '' }; }
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#whatsnew">Close</a><h1>Reply to ' + esc( unent( n.name ) ) + '</h1><button type="button" class="a-btn" data-nc-send' + ( nc.busy || ! nc.text.trim() ? ' disabled' : '' ) + '>' + ( nc.busy ? 'Sending…' : 'Send' ) + '</button></header>' +
			'<main class="a-compose" id="main"><div class="a-card a-note"><p class="muted small" style="margin:0 0 4px">' + esc( unent( n.name ) ) + ' wrote</p><p class="a-note-text" style="margin:0">' + esc( n.text ) + '</p></div>' +
			( nc.error ? '<div class="a-notice" role="alert"><p>' + esc( nc.error ) + '</p></div>' : '' ) +
			'<label class="a-label" for="a-nc">Your note</label><textarea id="a-nc" class="a-textarea" maxlength="1000">' + esc( nc.text ) + '</textarea>' +
			'<p class="a-help">Private: only ' + esc( unent( n.name ) ) + ' sees it, in their own Stubli.</p></main>';
		var t = $( '#a-nc' ), b = $( '[data-nc-send]' );
		t.addEventListener( 'input', function () { nc.text = t.value; b.disabled = ! nc.text.trim() || nc.busy; } );
		b.addEventListener( 'click', function () {
			nc.busy = true; nc.error = ''; b.disabled = true; b.textContent = 'Sending…';
			send( 'POST', 'notes/send', { to: n.site, text: nc.text, reply: n.id } ).then( function () { n.replied = true; nc = null; location.hash = '#whatsnew'; toast( 'Sent. Only ' + unent( n.name ) + ' sees it.' ); }, function ( e ) { nc.busy = false; nc.error = e.message; viewNote( id ); } );
		} );
		t.focus();
	}

	/* ── Your people: who you follow, and who is a friend (Q44) ── */
	var pp = { loaded: false, loading: false, list: [], open: false, msg: '' };
	function peopleCard() {
		var list = pp.list || [], shown = pp.open ? list : list.slice( 0, 6 );
		return '<section class="a-card" data-people><h2>Your people</h2>' +
			( list.length ? '<p class="muted">Tick your friends. They see your posts marked "Friends", in their own Stubli; nobody else does.</p><ul class="a-people">' + shown.map( function ( f ) {
				var host = hostOf( f.site_url ), name = f.name || host;
				return '<li><span class="a-people-who"><strong>' + esc( name ) + '</strong><small class="muted">' + esc( host ) + ( f.list_name ? ' · ' + esc( f.list_name ) : '' ) + '</small></span>' +
					'<label class="a-friend"><input type="checkbox" data-friend="' + f.id + '"' + ( f.friend ? ' checked' : '' ) + '><span>Friend</span><span class="sr"> – ' + esc( name ) + '</span></label></li>';
			} ).join( '' ) + '</ul>' + ( list.length > 6 && ! pp.open ? '<button type="button" class="a-btn quiet wide" data-people-all>Show all ' + list.length + '</button>' : '' ) : ( pp.loaded ? '<p class="muted">You don\'t follow anyone yet.</p>' : '' ) ) +
			( pp.msg ? '<p class="a-help" role="status">' + esc( pp.msg ) + '</p>' : '' ) +
			'<p class="a-label">Follow someone</p><p class="muted" style="margin:0 0 8px">Know someone with a Stubli? Type their address.</p>' + followForm( 'you' ) + '</section>';
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
					pp.msg = on ? ( r.sent ? name + ' is a friend now.' : name + ' is a friend now. Their Stubli couldn\'t be reached just now, so they\'ll see your friends posts once it can (we keep trying for a week).' ) : name + ' is no longer a friend. Your friends posts leave their feed at its next check.';
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
			body = '<p class="a-ds-status" role="status">Packing your archive… ' + ( a.total ? ( a.done + ' of ' + a.total + ' photos and videos' ) : '' ) + '</p><div class="a-progress" aria-hidden="true"><span style="width:' + ( a.total ? Math.round( 100 * a.done / a.total ) : 5 ) + '%"></span></div><p class="a-help">You can leave this screen open; it carries on by itself.</p>';
		} else if ( a.state === 'ready' ) {
			body = '<p class="a-ds-status" role="status">' + icon( 'check', 16 ) + 'Your archive is ready (' + esc( a.size ) + ')</p><div class="a-actions"><a class="a-btn" href="' + esc( a.download ) + '">Download</a><button type="button" class="a-btn quiet" data-keep-new>Make a new one</button></div><p class="a-help">Kept on your site until ' + esc( a.expires ) + ', then removed.</p>';
		} else {
			body = '<button type="button" class="a-btn quiet" data-keep-start' + ( kp.busy || a.supported === false ? ' disabled' : '' ) + '>' + ( kp.busy ? 'Starting…' : 'Download everything' ) + '</button>' + ( a.supported === false ? '<p class="a-help">This host can\'t make zip files. Ask your host to turn on PHP\'s Zip extension.</p>' : '' );
		}
		return '<section class="a-card" data-keep><h2>Yours to keep</h2><p class="muted">One zip with your original photos and videos, captions, journal, comments, guest book and the people you follow. It opens offline in any browser.</p>' + body +
			( kp.msg ? '<div class="a-notice" role="alert"><p>' + esc( kp.msg ) + '</p></div>' : '' ) + '</section>' + moveCard();
	}
	/* Move: out (this site is the old one) and in (this site is the new one). */
	var mv = { out: null, link: '', expires: '', inState: null, busy: false, msg: '', copied: false };
	function moveProgress( st ) {
		if ( ! st || st.state === 'none' ) { return ''; }
		if ( st.state === 'moving' ) {
			return '<p class="a-ds-status" role="status">Copying from ' + esc( hostOf( st.from ) ) + '… ' + st.done + ' of ' + st.total + '</p><div class="a-progress" aria-hidden="true"><span style="width:' + ( st.total ? Math.round( 100 * st.done / st.total ) : 5 ) + '%"></span></div><p class="a-help">Photos and videos arrive at full size, so this can take a while. Keep this screen open.</p>';
		}
		return '<p class="a-ds-status" role="status">' + icon( 'check', 16 ) + 'Everything from ' + esc( hostOf( st.from ) ) + ' is here' + ( st.skipped ? ' (' + st.skipped + ' item' + ( st.skipped > 1 ? 's' : '' ) + ' couldn\'t be copied)' : '' ) + '.</p><p class="a-help">' + ( st.told ? 'Your old site now points here, and the people who follow you switch to this address within a few hours.' : 'Your old site couldn\'t be told about the move. Your followers can follow you here by your new address.' ) + '</p>';
	}
	function moveCard() {
		var o = mv.out || {}, out;
		if ( o.moved_to ) {
			out = '<p class="a-ds-status">' + icon( 'check', 16 ) + 'This Stubli has moved to ' + esc( hostOf( o.moved_to ) ) + '.</p><p class="a-help">Your followers switch to the new address by themselves. You can keep this site as it is, or close it once they have.</p>';
		} else if ( mv.link ) {
			out = '<label class="a-label" for="a-mv-link">Your move link</label><div class="a-inline" style="margin-top:0"><input id="a-mv-link" class="a-field" type="text" readonly value="' + esc( mv.link ) + '"><button type="button" class="a-btn quiet" data-mv-copy>' + ( mv.copied ? 'Copied' : 'Copy' ) + '</button></div>' +
				'<p class="a-help">Paste it into your new Stubli, in its setup or under You. It works until ' + esc( mv.expires ) + '. Keep it private: it lets the new site copy everything, including entries only you can see.</p><button type="button" class="a-btn quiet" data-mv-stop>Stop this link</button>';
		} else {
			out = '<p class="muted">Moving to a new WordPress, for example your own server? Make a move link here and paste it into your new Stubli. It copies everything, and your followers come along.</p><button type="button" class="a-btn quiet" data-mv-make' + ( mv.busy ? ' disabled' : '' ) + '>Make a move link</button>' + ( o.has_link ? '<p class="a-help">A move link is already active. Making a new one stops the old one.</p>' : '' );
		}
		var st = mv.inState, inn = moveProgress( st );
		if ( ! inn ) {
			inn = '<form class="a-inline" data-mv-in style="margin-top:0"><label class="sr" for="a-mv-in">Move link from your old Stubli</label><input id="a-mv-in" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="oldsite.com/?stubli_move=…"><button type="submit" class="a-btn quiet"' + ( mv.busy ? ' disabled' : '' ) + '>Start</button></form>';
		}
		return '<section class="a-card" data-move><h2>Move to another site</h2>' + out +
			'<p class="a-label" style="margin-top:18px">Moving here from another Stubli?</p>' + inn +
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
			}, function ( e ) { mv.running = false; mv.msg = e.message + ' Tap Start again to carry on where it stopped.'; mv.inState = null; onUpdate(); } );
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
			mine.more = r.more; mine.total = r.total; mine.missing = r.missing; mine.loaded = true;
		}, function () { mine.loaded = true; } );
	}
	function mineSection() {
		if ( ! mine.loaded ) { return '<section class="a-card" data-mine><h2>Your posts</h2><div class="a-mine-grid" aria-hidden="true"><span></span><span></span><span></span></div></section>'; }
		if ( ! mine.items.length ) { return '<div data-mine hidden></div>'; }
		var firstMissing = mine.items.filter( function ( i ) { return i.missing; } )[ 0 ];
		return '<section class="a-card" data-mine><h2>Your posts</h2><p class="muted">Tap a photo to change its caption, collection or descriptions, or to delete it.</p>' +
			( mine.missing && firstMissing ? '<div class="a-notice calm"><p>' + ( mine.missing === 1 ? 'One photo has' : mine.missing + ' photos have' ) + ' no description yet. A few words help people who use screen readers.</p><a class="a-btn quiet" href="#edit/' + firstMissing.id + '">Add descriptions</a></div>' : '' ) +
			'<div class="a-mine-grid">' + mine.items.map( function ( i ) {
				var label = ( i.title || ( i.video ? 'Video post' : 'Photo post' ) ) + ( i.video && i.count === 1 ? ', video' : '' ) + ( i.count > 1 ? ', ' + i.count + ' photos' : '' ) + ( i.missing ? ', needs a description' : '' ) + ( i.friends ? ', friends only' : '' );
				return '<a href="#edit/' + i.id + '" aria-label="Edit: ' + esc( label ) + '">' + ( i.thumb ? '<img src="' + esc( i.thumb ) + '" alt="" loading="lazy"' + ( i.note ? ' class="is-noted"' : '' ) + '>' : '' ) +
					( i.count > 1 ? '<span class="a-mine-n" aria-hidden="true">' + i.count + '</span>' : i.video ? '<span class="a-mine-n" aria-hidden="true">' + icon( 'play', 12 ) + '</span>' : '' ) + ( i.missing ? '<span class="a-mine-dot" aria-hidden="true"></span>' : '' ) + ( i.friends ? '<span class="a-mine-friends" aria-hidden="true">Friends</span>' : '' ) + '</a>';
			} ).join( '' ) + '</div>' +
			( mine.more ? '<button type="button" class="a-btn quiet wide" data-mine-more style="margin-top:12px">Show more</button>' : '' ) + '</section>';
	}
	function bindMine() {
		var m = $( '[data-mine-more]' );
		if ( m ) { m.addEventListener( 'click', function () { m.disabled = true; m.textContent = 'Loading…'; mine.page++; loadMine( false ).then( function () { swap( '[data-mine]', mineSection(), bindMine ); } ); } ); }
	}
	function toast( text, undo ) {
		var old = document.querySelector( '.a-toast' ); if ( old ) { old.remove(); }
		var t = document.createElement( 'div' ); t.className = 'a-toast'; t.setAttribute( 'role', 'status' );
		t.innerHTML = '<span>' + esc( text ) + '</span>' + ( undo ? '<button type="button" class="a-text-btn">Undo</button>' : '' );
		document.body.appendChild( t );
		if ( undo ) { t.querySelector( 'button' ).addEventListener( 'click', function () { t.remove(); undo(); } ); }
		setTimeout( function () { if ( t.isConnected ) { t.remove(); } }, undo ? 10000 : 4000 );
	}

	var ed = null;
	function viewEdit( id ) {
		if ( ed && ed.id === id && ed.d ) { return renderEdit(); }
		ed = { id: id, d: null, busy: false, error: '', sure: false, newCol: '', adding: false };
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit post</h1><span></span></header><main class="a-compose" id="main"><div class="a-feed-skel" aria-hidden="true"><span style="height:200px"></span></div><p class="sr" role="status">Loading…</p></main>';
		send( 'GET', 'mine/' + id, null ).then( function ( d ) {
			if ( ! ed || ed.id !== id ) { return; }
			ed.d = d; ed.noteOn = !! d.note; renderEdit();
		}, function ( e ) {
			root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit post</h1><span></span></header><main class="a-compose" id="main"><div class="a-notice" role="alert"><p>' + esc( e.message ) + '</p><a class="a-btn quiet" href="#you">Back to your posts</a></div></main>';
		} );
	}
	/* Who can see a post: Everyone or Friends (design Q44). */
	function audienceChips( on, attr ) {
		var friends = on === 'friends';
		return '<h2 class="a-label" id="a-aud-' + attr + '">Who can see it</h2><div class="a-chips" role="radiogroup" aria-labelledby="a-aud-' + attr + '">' +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ! friends + '" data-' + attr + '="everyone">' + ( ! friends ? icon( 'check', 16 ) : '' ) + 'Everyone</button>' +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + friends + '" data-' + attr + '="friends">' + ( friends ? icon( 'check', 16 ) : '' ) + 'Friends</button></div>' +
			'<p class="a-help">' + ( friends ? ( B.friends ? 'Only the ' + ( B.friends === 1 ? 'friend' : B.friends + ' friends' ) + ' you chose see it, in their own Stubli. It isn\'t on your public page.' : 'You haven\'t chosen any friends yet. Tick them under You → Your people; until then, nobody else sees this post.' ) : 'On your page and in your followers\' feeds.' ) + '</p>';
	}
	function renderEdit() {
		var d = ed.d, cols = ( B.collections || [] ).slice();
		if ( d.collection && cols.indexOf( d.collection ) < 0 ) { cols.push( d.collection ); }
		var missing = d.photos.filter( function ( p ) { return ! p.alt.trim(); } ).length;
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#you">Close</a><h1>Edit post</h1><button type="button" class="a-btn" data-ed-save' + ( ed.busy ? ' disabled' : '' ) + '>' + ( ed.busy ? 'Saving…' : 'Save' ) + '</button></header>' +
			'<main class="a-compose" id="main">' +
			'<h2 class="a-label" id="a-ed-desc">Descriptions</h2>' + ( missing ? '<p class="a-help" style="margin:-4px 0 10px">' + ( missing === 1 ? 'One photo needs' : missing + ' photos need' ) + ' a few words: what\'s in it, for people who can\'t see it.</p>' : '' ) +
			d.photos.map( function ( ph, i ) {
				return '<div class="a-alt"><img src="' + esc( ph.src ) + '" alt=""><label class="sr" for="a-ed-alt-' + i + '">Description of photo ' + ( i + 1 ) + '</label><input id="a-ed-alt-' + i + '" class="a-field" data-ed-alt="' + i + '" maxlength="300" placeholder="' + ( ph.video ? 'Video ' + ( i + 1 ) + ': what happens in it?' : 'Photo ' + ( i + 1 ) + ': what\'s in it?' ) + '" value="' + esc( ph.alt ) + '"></div>';
			} ).join( '' ) +
			'<label class="a-label" for="a-ed-cap">Caption</label><textarea id="a-ed-cap" class="a-textarea" maxlength="' + B.maxCaption + '">' + esc( d.caption ) + '</textarea>' +
			'<p class="a-count" data-ed-count' + ( d.caption.length < B.maxCaption - 200 ? ' hidden' : '' ) + '>' + d.caption.length + ' / ' + B.maxCaption + '</p>' +
			'<h2 class="a-label" id="a-ed-col">Collection</h2><div class="a-chips" role="radiogroup" aria-labelledby="a-ed-col">' +
			cols.map( function ( c ) { var on = ! ed.adding && d.collection === c; return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-ed-col="' + esc( c ) + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( c ) + '</button>'; } ).join( '' ) +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ( ! ed.adding && ! d.collection ) + '" data-ed-col="">' + ( ! ed.adding && ! d.collection ? icon( 'check', 16 ) : '' ) + 'None</button>' +
			'<button type="button" class="a-chip" role="radio" aria-checked="' + ed.adding + '" data-ed-col-new>' + ( ed.adding ? icon( 'check', 16 ) : '' ) + '+ New</button></div>' +
			( ed.adding ? '<div class="a-inline"><label class="sr" for="a-ed-newcol">New collection name</label><input id="a-ed-newcol" class="a-field" maxlength="40" placeholder="e.g. Mountains" value="' + esc( ed.newCol ) + '"></div>' : '' ) +
			audienceChips( d.audience, 'ed-aud' ) +
			'<label class="a-check"><span><strong>Content note</strong><span class="muted small">The photo stays softly blurred until someone chooses to see it.</span></span><input type="checkbox" data-ed-note' + ( ed.noteOn ? ' checked' : '' ) + '></label>' +
			( ed.noteOn ? '<div class="a-inline"><label class="sr" for="a-ed-notetext">Content note</label><input id="a-ed-notetext" class="a-field" maxlength="' + B.maxNote + '" placeholder="e.g. Flashing lights" value="' + esc( d.note ) + '"></div>' : '' ) +
			( ed.error ? '<div class="a-notice" role="alert"><p>' + esc( ed.error ) + '</p></div>' : '' ) +
			'<p class="a-help" style="margin-top:16px">Changes reach your followers\' feeds the next time their Stubli checks for news, usually within 15 minutes.</p>' +
			'<p style="margin:16px 0"><a href="' + esc( d.url ) + '">See the post</a></p>' +
			'<section class="a-ed-delete">' + ( ed.sure ?
				'<p><strong>Delete this post?</strong> It leaves your page, and your followers\' feeds the next time they check. You can undo right after.</p><div class="a-actions"><button type="button" class="a-btn a-danger" data-ed-del-yes>Delete post</button><button type="button" class="a-btn quiet" data-ed-del-no>Keep it</button></div>' :
				'<button type="button" class="a-text-btn a-danger-text" data-ed-del>Delete post…</button>' ) + '</section>' +
			'</main>';
		bindEdit();
	}
	function bindEdit() {
		var d = ed.d;
		$$( '[data-ed-alt]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { d.photos[ +inp.dataset.edAlt ].alt = inp.value; } ); } );
		var cap = $( '#a-ed-cap' ), cnt = $( '[data-ed-count]' );
		cap.addEventListener( 'input', function () { d.caption = cap.value; cnt.textContent = cap.value.length + ' / ' + B.maxCaption; cnt.hidden = cap.value.length < B.maxCaption - 200; } );
		$$( '[data-ed-col]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.collection = b.dataset.edCol; ed.adding = false; renderEdit(); } ); } );
		$( '[data-ed-col-new]' ).addEventListener( 'click', function () { ed.adding = true; renderEdit(); $( '#a-ed-newcol' ).focus(); } );
		var nc = $( '#a-ed-newcol' ); if ( nc ) { nc.addEventListener( 'input', function () { ed.newCol = nc.value; } ); }
		$$( '[data-ed-aud]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { d.audience = b.dataset.edAud; renderEdit(); $( '[data-ed-aud="' + d.audience + '"]' ).focus(); } ); } );
		$( '[data-ed-note]' ).addEventListener( 'change', function ( e ) { ed.noteOn = e.target.checked; renderEdit(); if ( ed.noteOn ) { $( '#a-ed-notetext' ).focus(); } } );
		var nt = $( '#a-ed-notetext' ); if ( nt ) { nt.addEventListener( 'input', function () { d.note = nt.value; } ); }
		$( '[data-ed-save]' ).addEventListener( 'click', function () {
			var alts = {}; d.photos.forEach( function ( p ) { alts[ p.id ] = p.alt; } );
			var col = ed.adding ? ed.newCol.trim() : d.collection;
			if ( ed.adding && ! col ) { ed.error = 'Give the new collection a name, or choose one above.'; renderEdit(); return; }
			if ( ed.noteOn && ! d.note.trim() ) { ed.error = 'Write a short content note, like "Flashing lights", or turn it off.'; renderEdit(); return; }
			ed.busy = true; ed.error = ''; renderEdit();
			send( 'POST', 'mine/' + ed.id, { caption: d.caption, collection: col, note: ed.noteOn ? d.note : '', alts: alts, audience: d.audience || 'everyone' } ).then( function ( r ) {
				if ( col && ( B.collections || [] ).indexOf( col ) < 0 ) { ( B.collections = B.collections || [] ).push( col ); }
				ed = null; mine.loaded = false; location.hash = '#you'; toast( 'Saved.' );
			}, function ( e ) { ed.busy = false; ed.error = e.message; renderEdit(); } );
		} );
		var del = $( '[data-ed-del]' ); if ( del ) { del.addEventListener( 'click', function () { ed.sure = true; renderEdit(); $( '[data-ed-del-no]' ).focus(); } ); }
		var no = $( '[data-ed-del-no]' ); if ( no ) { no.addEventListener( 'click', function () { ed.sure = false; renderEdit(); $( '[data-ed-del]' ).focus(); } ); }
		var yes = $( '[data-ed-del-yes]' );
		if ( yes ) {
			yes.addEventListener( 'click', function () {
				var id = ed.id; yes.disabled = true; yes.textContent = 'Deleting…';
				send( 'DELETE', 'mine/' + id, null ).then( function () {
					ed = null; mine.loaded = false; feed.loaded = false; location.hash = '#you';
					toast( 'Post deleted.', function () {
						send( 'POST', 'mine/' + id + '/restore', {} ).then( function () { mine.loaded = false; if ( location.hash === '#you' ) { viewYou(); } toast( 'It\'s back on your page.' ); }, function ( e ) { toast( e.message ); } );
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
		if ( ! pushSupported() ) { return Promise.reject( { message: 'This browser can\'t show notifications.' } ); }
		return Notification.requestPermission().then( function ( p ) {
			if ( p !== 'granted' ) { return Promise.reject( { message: 'Notifications are turned off for Stubli. You can allow them in your phone\'s settings, or use email below.' } ); }
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
		if ( ! ds.loaded || ! ds.info ) { return '<section class="a-card" data-summary><h2>Daily summary</h2><p class="muted">Loading…</p></section>'; }
		var i = ds.info, phone;
		if ( iosNeedsInstall() ) {
			phone = '<p class="a-help">On iPhone, add Stubli to your Home Screen first (Share, then Add to Home Screen). Open it from there to turn this on.</p>';
		} else if ( ! pushSupported() ) {
			phone = '<p class="a-help">This browser can\'t show notifications. Email below works everywhere.</p>';
		} else {
			phone = '<p class="a-ds-status">' + icon( ds.on ? 'check' : 'news', 16 ) + ( ds.on ? 'On for this phone' : 'Off for this phone' ) + '</p><div class="a-actions">' +
				( ds.on ? '<button type="button" class="a-btn quiet" data-ds-test' + ( ds.busy ? ' disabled' : '' ) + '>Send a test</button><button type="button" class="a-text-btn" data-ds-off' + ( ds.busy ? ' disabled' : '' ) + '>Turn off</button>' :
					'<button type="button" class="a-btn" data-ds-on' + ( ds.busy ? ' disabled' : '' ) + '>' + ( ds.busy ? 'Asking your phone…' : 'Send it to this phone' ) + '</button>' ) + '</div>';
		}
		return '<section class="a-card" data-summary><h2>Daily summary</h2><p class="muted">At most one a day, and only when something is new: comments waiting for your OK, new posts from your people. Never one per post.</p>' +
			'<label class="a-label" for="a-ds-time" style="margin-top:4px">Time</label><select id="a-ds-time" class="a-field a-su-select" data-ds-time>' + timeOptions( i.time ) + '</select>' +
			'<p class="a-label">On your phone</p>' + phone +
			'<label class="a-check"><span><strong>Also by email</strong><span class="muted small">To ' + esc( i.address ) + '. Handy if notifications don\'t reach you.</span></span><input type="checkbox" data-ds-email' + ( i.email ? ' checked' : '' ) + '></label>' +
			'<p class="a-help" aria-live="polite" data-ds-msg>' + esc( ds.msg ) + '</p></section>';
	}
	function bindSummary( rerender ) {
		var m = function ( t ) { ds.msg = t; var e = $( '[data-ds-msg]' ); if ( e ) { e.textContent = t; } };
		var tm = $( '[data-ds-time]' ); if ( tm ) { tm.addEventListener( 'change', function () { send( 'POST', 'settings', { digest_time: tm.value } ).then( function () { ds.info.time = tm.value; m( 'Saved. It arrives around ' + tm.value + ' when something is new.' ); }, function ( e ) { m( e.message ); } ); } ); }
		var em = $( '[data-ds-email]' ); if ( em ) { em.addEventListener( 'change', function () { send( 'POST', 'settings', { digest_email: em.checked } ).then( function () { ds.info.email = em.checked; m( em.checked ? 'Email is on.' : 'Email is off.' ); }, function ( e ) { em.checked = ! em.checked; m( e.message ); } ); } ); }
		var on = $( '[data-ds-on]' ); if ( on ) { on.addEventListener( 'click', function () { ds.busy = true; ds.msg = ''; rerender(); enablePush().then( function () { ds.busy = false; ds.msg = 'On. Your summary arrives on this phone once a day, when something is new.'; rerender(); }, function ( e ) { ds.busy = false; ds.msg = ( e && e.message ) || 'That didn\'t work. Try again.'; rerender(); } ); } ); }
		var off = $( '[data-ds-off]' ); if ( off ) { off.addEventListener( 'click', function () { ds.busy = true; rerender(); disablePush().then( function () { ds.busy = false; ds.msg = 'Off for this phone.'; rerender(); }, function () { ds.busy = false; rerender(); } ); } ); }
		var te = $( '[data-ds-test]' ); if ( te ) { te.addEventListener( 'click', function () { te.disabled = true; send( 'POST', 'push/test', {} ).then( function ( r ) { te.disabled = false; m( r.sent ? 'Sent. It should appear in a moment.' : 'Your phone\'s notification service didn\'t accept it. Turn it off and on again.' ); }, function ( e ) { te.disabled = false; m( e.message ); } ); } ); }
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
		return '<form class="a-inline" data-follow="' + where + '"><label class="sr" for="a-follow-' + where + '">Their Stubli address</label><input id="a-follow-' + where + '" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="theirname.com"><button type="submit" class="a-btn quiet">Follow</button></form><div class="a-help" data-follow-msg aria-live="polite"></div>';
	}
	function bindFollow() {
		$$( '[data-follow]' ).forEach( function ( f ) {
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var inp = f.querySelector( 'input' ), m = f.nextElementSibling, btn = f.querySelector( 'button' ), v = inp.value.trim();
				if ( ! v ) { inp.focus(); return; }
				if ( ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				btn.disabled = true; btn.textContent = 'Following…'; m.textContent = '';
				send( 'POST', 'follows', { url: v } ).then( function ( r ) { pp.loaded = false;
					m.textContent = 'You\'re following ' + ( r && r.name ? r.name : v ) + '. Their photos appear in Home.';
					inp.value = ''; btn.disabled = false; btn.textContent = 'Follow'; feed.loaded = false;
					if ( location.hash === '#setup' && su.loaded ) { send( 'GET', 'setup', null ).then( function ( d ) { su.d.follows = d.follows; renderSetup(); var mm = $( '[data-follow-msg]' ); if ( mm ) { mm.textContent = 'You\'re following ' + ( r && r.name ? r.name : v ) + '.'; } }, function () {} ); }
				}, function ( err ) {
					m.textContent = ( err && err.message ) || 'That didn\'t work. Check the address and try again.';
					btn.disabled = false; btn.textContent = 'Follow';
				} );
			} );
		} );
	}

	/* ── Home: the feed ── */
	var feed = { items: [], page: 1, more: true, list: '', lists: [], loaded: false, loading: false, seen: +( store.get( 'stubli-seen' ) || 0 ), newSince: 0, open: {} };
	function gentle( iso ) {
		var t = Date.parse( iso ), d = Math.floor( ( Date.now() - t ) / 86400000 );
		if ( ! t ) { return ''; }
		if ( d < 1 ) { var h = Math.floor( ( Date.now() - t ) / 3600000 ); return h < 1 ? 'just now' : h + ' h ago'; }
		if ( d === 1 ) { return 'yesterday'; }
		if ( d < 14 ) { return d + ' days ago'; }
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
				feed.error = ( e && e.code === 'offline' ) ? 'No connection right now. Your feed comes back when you\'re online.' : 'Your feed didn\'t load this time.';
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
	function renderHome() {
		var body = '';
		if ( ! feed.loaded ) {
			body = '<div class="a-feed-skel" aria-hidden="true"><span></span><span></span></div><p class="sr" role="status">Loading your feed…</p>';
		} else if ( feed.error ) {
			body = '<div class="a-notice" role="alert"><p>' + esc( feed.error ) + '</p><button type="button" class="a-btn quiet" data-feed-retry>Try again</button></div>';
		} else if ( ! ( feed.follows || [] ).length ) {
			body = '<section class="a-card"><h2>Your feed is waiting</h2><p class="muted">Follow someone and their new photos will appear here, newest first. No ads, no suggestions, just the people you chose.</p>' + followForm( 'home' ) + '</section>' +
				'<section class="a-card"><h2>Or invite a friend</h2><p class="muted">When they\'ve set up their Stubli, you follow each other automatically.</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>Create an invite link</button></section>';
		} else {
			var pills = '';
			if ( feed.lists.length ) {
				pills = '<div class="a-chips a-filter" role="group" aria-label="Show">' + [ '' ].concat( feed.lists ).map( function ( l ) {
					return '<button type="button" class="a-chip" aria-pressed="' + ( feed.list === l ) + '" data-list="' + esc( l ) + '">' + esc( l || 'All' ) + '</button>';
				} ).join( '' ) + '</div>';
			}
			body = pills + ( feed.items.length ? feed.items.map( item ).join( '' ) : '<p class="muted" style="margin:24px 0">Nothing here yet. New posts arrive every 15 minutes or so.</p>' ) +
				( feed.more && feed.items.length ? '<button type="button" class="a-btn quiet wide" data-older>Older posts</button>' : '' ) +
				( feed.items.length && ! feed.more ? '<div class="a-end">' + sky( 28 ) + '<strong>That\'s everything for now</strong><span class="muted">You\'re all caught up. ' + esc( endLine() ) + '</span></div>' : '' );
		}
		var sub = feed.stale ? 'Offline. Showing your feed from last time.' : feed.loaded && feed.newSince ? feed.newSince + ' new since your last visit' : '';
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div>' +
			'<h1 class="a-greet a-greet-sky">' + sky( 36 ) + '<span>' + esc( greeting() ) + '</span></h1>' + ( sub ? '<p class="muted" style="margin:-2px 0 14px">' + esc( sub ) + '</p>' : '' ) +
			setupCard() + body + '</main>' + bar( 'home' );
		bindFollow(); bindInvite();
		var fr = $( '[data-feed-retry]' ); if ( fr ) { fr.addEventListener( 'click', retryFeed ); }
		$$( '[data-list]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { feed.list = b.dataset.list; loadFeed( true ).then( renderHome ); } ); } );
		var older = $( '[data-older]' );
		if ( older ) { older.addEventListener( 'click', function () { older.disabled = true; older.textContent = 'Loading…'; var y = window.scrollY; loadFeed( false ).then( function () { renderHome(); window.scrollTo( 0, y ); } ); } ); }
		bindItems();
	}
	function item( it ) {
		var imgs = ( it.images && it.images.length ? it.images : ( it.image ? [ { url: it.image, alt: '' } ] : [] ) );
		var isNew = Date.parse( it.published ) > ( feed.seenBefore || 0 ) && feed.seenBefore;
		var note = it.content_note && ! feed.open[ it.id ];
		var c = feed.open[ 'c' + it.id ];
		return '<article class="a-item" data-item="' + it.id + '">' +
			'<header class="a-item-head">' + face( it.author.avatar, it.author.name, 32 ) +
			'<span><a href="' + esc( it.author.url ) + '"><strong>' + esc( unent( it.author.name ) ) + '</strong></a> <span class="muted">' + esc( gentle( it.published ) ) + '</span>' + ( it.audience === 'friends' ? ' <span class="a-friends-tag">For friends</span>' : '' ) + ( isNew ? ' <span class="a-new">New</span>' : '' ) + '</span></header>' +
			( imgs.length ? '<div class="a-photos' + ( note ? ' has-note' : '' ) + '"><div class="a-track" tabindex="0" aria-label="Photos">' + imgs.map( function ( im ) {
					if ( im.video && /^https?:\/\//.test( im.video ) ) {
						return '<button type="button" class="a-play" data-play="' + esc( im.video ) + '" data-type="' + esc( im.type || 'video/mp4' ) + '" aria-label="' + esc( note ? 'Play video' : ( im.alt ? 'Play video: ' + im.alt : 'Play video' ) ) + '"><img src="' + esc( im.url ) + '" alt="" loading="lazy"><span class="a-play-icon" aria-hidden="true">' + icon( 'play', 28 ) + '</span></button>';
					}
					return '<img src="' + esc( im.url ) + '" alt="' + esc( note ? '' : ( im.alt || '' ) ) + '" loading="lazy">';
				} ).join( '' ) + '</div>' +
				( imgs.length > 1 ? '<span class="a-count-pill" aria-hidden="true">1 of ' + imgs.length + '</span>' : '' ) +
				( note ? '<button type="button" class="a-reveal" data-reveal="' + it.id + '"><strong>' + esc( it.content_note ) + '</strong><span>Show photo</span></button>' : '' ) + '</div>' : '' ) +
			( it.caption_html ? '<div class="a-caption">' + it.caption_html + '</div>' : '' ) +
			'<div class="a-item-acts"><button type="button" class="a-text-btn" data-comment="' + it.id + '" aria-expanded="' + ( c ? 'true' : 'false' ) + '">Comment</button>' + ( it.my_comments ? '<span class="muted small">You commented</span>' : '' ) + '<a class="a-text-btn muted" href="' + esc( it.permalink ) + '">Open</a></div>' +
			( c ? '<div class="a-comment-box"><label class="sr" for="a-cm-' + it.id + '">Your comment</label><textarea id="a-cm-' + it.id + '" class="a-textarea" maxlength="2200" placeholder="Say something kind…">' + esc( c.text || '' ) + '</textarea><div class="a-actions" style="margin-top:8px"><button type="button" class="a-btn" data-send-comment="' + it.id + '">Send</button><button type="button" class="a-text-btn" data-comment="' + it.id + '">Cancel</button></div><p class="a-help" data-cm-msg="' + it.id + '" aria-live="polite">' + esc( c.msg || 'It\'s saved on your own site too. It shows on their post right away if they follow you; otherwise after their OK.' ) + '</p></div>' : '' ) +
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
				b.disabled = true; b.textContent = 'Sending…';
				send( 'POST', 'items/' + id + '/comments', { text: t.value.trim() } ).then( function ( r ) {
					var it = feed.items.filter( function ( x ) { return String( x.id ) === String( id ); } )[ 0 ];
					if ( it ) { it.my_comments = ( it.my_comments || 0 ) + 1; }
					var msg = r && r.status === 'paused' ? 'Saved. They\'re taking a break, so it will show when they\'re back.' : r && r.status === 'not_sent' ? 'Saved on your site, but their site didn\'t answer. It will try again later.' : 'Sent. Thank you.';
					feed.open[ k ] = null; var y = window.scrollY; renderHome(); window.scrollTo( 0, y );
					var art = $( '[data-item="' + id + '"] .a-item-acts' ); if ( art ) { art.insertAdjacentHTML( 'afterend', '<p class="a-help" role="status">' + esc( msg ) + '</p>' ); }
				}, function ( e ) { b.disabled = false; b.textContent = 'Send'; m.textContent = ( e && e.message ) || 'Couldn\'t send that. Try again.'; } );
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
			tr.addEventListener( 'scroll', function () { var i = Math.round( tr.scrollLeft / Math.max( 1, tr.clientWidth ) ); pill.textContent = ( i + 1 ) + ' of ' + n; }, { passive: true } );
		} );
	}

	function bindInvite() {
		var b = $( '[data-invite]' ), out = $( '[data-invite-out]' );
		if ( ! b ) { return; }
		b.addEventListener( 'click', function () {
			b.disabled = true; b.textContent = 'Creating…';
			send( 'POST', 'invites', {} ).then( function ( r ) {
				out.innerHTML = '<label class="sr" for="a-inv">Invite link</label><input id="a-inv" class="a-field" readonly value="' + esc( r.url ) + '">' +
					'<div class="a-actions" style="margin-top:8px">' + ( navigator.share ? '<button type="button" class="a-btn" data-inv-share>Share…</button>' : '' ) + '<button type="button" class="a-btn quiet" data-inv-copy>Copy link</button></div><p class="a-help" aria-live="polite" data-inv-msg></p>';
				b.hidden = true;
				var f = $( '#a-inv' ), m = $( '[data-inv-msg]' );
				f.addEventListener( 'focus', function () { f.select(); } );
				$( '[data-inv-copy]' ).addEventListener( 'click', function () {
					var ok = function () { m.textContent = 'Copied. Send it to one friend.'; };
					if ( navigator.clipboard ) { navigator.clipboard.writeText( r.url ).then( ok, function () { f.select(); m.textContent = 'Select the link and copy it.'; } ); } else { f.select(); document.execCommand( 'copy' ); ok(); }
				} );
				var sh = $( '[data-inv-share]' );
				if ( sh ) { sh.addEventListener( 'click', function () { navigator.share( { title: 'Join me on Stubli', text: 'I made you an invite to Stubli, a calm place for photos.', url: r.url } ).catch( function () {} ); } ); }
			}, function ( e ) {
				b.disabled = false; b.textContent = 'Create an invite link';
				out.innerHTML = '<div class="a-notice" role="alert"><p>' + esc( e.message ) + '</p></div>';
			} );
		} );
	}



	/* ── Discover: friends of friends ── */
	var disc = { loaded: false, list: [], followed: {}, i: 0 };
	function viewDiscover() {
		renderDiscover();
		disc.error = '';
		send( 'GET', 'discover', null ).then( function ( l ) { disc.list = Array.isArray( l ) ? l : []; disc.loaded = true; disc.error = ''; }, function ( e ) { disc.loaded = true; disc.error = ( e && e.message ) || 'Couldn\'t load suggestions.'; } ).then( function () { if ( location.hash === '#discover' ) { renderDiscover(); } } );
	}
	function viaText( v ) { return v.length > 2 ? v.slice( 0, 2 ).join( ', ' ) + ' and ' + ( v.length - 2 ) + ' more' : v.join( ' and ' ); }
	function renderDiscover() {
		var body;
		if ( ! disc.loaded ) {
			body = '<div class="a-feed-skel" aria-hidden="true"><span style="height:220px"></span><span style="height:220px"></span></div><p class="sr" role="status">Loading…</p>';
		} else if ( disc.error ) {
			body = '<div class="a-notice" role="alert"><p>' + esc( disc.error ) + '</p><button type="button" class="a-btn quiet" data-disc-retry>Try again</button></div>';
		} else if ( ! disc.list.length ) {
			body = '<p class="muted">No suggestions yet. They come from the public lists of the people you follow, so they appear once you follow a few people who share theirs.</p>';
		} else {
			var at = Math.min( disc.i, disc.list.length );
			body = at >= disc.list.length ? '<div class="a-end">' + sky( 28 ) + '<strong>That\'s everyone for now</strong><span class="muted">New suggestions come as the people you follow follow others.</span><button type="button" class="a-text-btn" data-disc-again>See them again</button></div>' : [ disc.list[ at ] ].map( function ( s ) {
				var host = hostOf( s.url ), done = disc.followed[ s.url ], pr = s.prompts && s.prompts[ 0 ];
				return '<article class="a-card a-disc">' +
					'<div class="a-wait-head">' + ( s.avatar ? '<img src="' + esc( s.avatar ) + '" alt="" width="40" height="40">' : '<span class="a-av"></span>' ) +
					'<span><a href="' + esc( s.url ) + '"><strong>' + esc( s.name || host ) + '</strong></a> <span class="muted">' + esc( host ) + '</span><span class="muted small a-ctx">Followed by ' + esc( viaText( s.via ) ) + '</span></span>' +
					'<button type="button" class="a-btn' + ( done ? ' quiet' : '' ) + ' a-disc-follow" data-disc="' + esc( s.url ) + '"' + ( done ? ' disabled' : '' ) + '>' + ( done ? 'Following' : 'Follow' ) + '</button></div>' +
					( s.bio ? '<p class="a-disc-bio">' + esc( s.bio ) + '</p>' : '' ) +
					( pr ? '<div class="a-pcard a-disc-card"><p class="a-pcard-q">' + esc( pr.q ) + '</p><p class="a-pcard-a">' + esc( pr.a ) + '</p><a class="a-text-btn a-pcard-reply" href="' + esc( B.home + 'app/?act=1&do=note&p=0&url=' + encodeURIComponent( s.url ) ) + '">Reply to this</a></div>' : '' ) +
					( s.recent && s.recent.length ? '<div class="a-wn-thumbs">' + s.recent.map( function ( r ) { return '<a href="' + esc( r.link ) + '"><img src="' + esc( r.url ) + '" alt="' + esc( r.note ? 'Photo with a content note: ' + r.note : r.alt ) + '" loading="lazy"' + ( r.note ? ' class="is-noted"' : '' ) + '></a>'; } ).join( '' ) + '</div>' : '' ) +
					'<p class="a-help" data-disc-msg="' + esc( s.url ) + '" aria-live="polite"></p>' +
					'<div class="a-actions"><button type="button" class="a-btn quiet" data-disc-next>' + ( done ? 'Next' : 'Not now' ) + '</button></div></article>' +
					'<p class="muted small" style="text-align:center">' + ( at + 1 ) + ' of ' + disc.list.length + '</p>';
			} ).join( '' );
		}
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div><h1 class="a-greet">Discover</h1>' +
			'<p class="muted" style="margin:-2px 0 14px">Sites followed by people you follow. No rankings, no algorithm: the newest photos come first.</p>' +
			followForm( 'discover' ) + '<div style="height:12px"></div>' + body + '</main>' + bar( 'discover' );
		var dr = $( '[data-disc-retry]' ); if ( dr ) { dr.addEventListener( 'click', function () { disc.loaded = false; viewDiscover(); } ); }
		var dn = $( '[data-disc-next]' ); if ( dn ) { dn.addEventListener( 'click', function () { disc.i++; renderDiscover(); var h = $( '.a-disc strong, .a-end strong' ); if ( h ) { h.setAttribute( 'tabindex', '-1' ); h.focus(); } } ); }
		var da = $( '[data-disc-again]' ); if ( da ) { da.addEventListener( 'click', function () { disc.i = 0; renderDiscover(); } ); }
		bindFollow();
		$$( '[data-disc]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var url = b.dataset.disc, m = $( '[data-disc-msg="' + url + '"]' );
				b.disabled = true; b.textContent = 'Following…';
				send( 'POST', 'follows', { url: url } ).then( function () {
					disc.followed[ url ] = true; feed.loaded = false; b.textContent = 'Following'; b.classList.add( 'quiet' );
					m.textContent = 'Their new photos will appear in Home.';
				}, function ( e ) { b.disabled = false; b.textContent = 'Follow'; m.textContent = ( e && e.message ) || 'That didn\'t work. Try again.'; } );
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
			body = '<div class="a-feed-skel" aria-hidden="true"><span style="height:160px"></span><span style="height:160px"></span></div><p class="sr" role="status">Loading…</p>';
		} else {
			body += notesSection();
			var waiting = wn.queue.filter( function ( c ) { return ! c.later || wn.done[ c.id ]; } );
			var later = wn.queue.filter( function ( c ) { return c.later && ! wn.done[ c.id ]; } );
			var open = waiting.filter( function ( c ) { return ! wn.done[ c.id ]; } ).length;
			body += '<h2 class="a-label">Waiting for your OK' + ( open ? ' · ' + open : '' ) + '</h2>';
			// Many comments from one site: show two, fold the rest.
			var perHost = {}, folded = {};
			body += waiting.length ? waiting.map( function ( c ) {
				var h = hostOf( c.author.url );
				perHost[ h ] = ( perHost[ h ] || 0 ) + 1;
				if ( perHost[ h ] > 2 && ! ( wn.expand || {} )[ h ] && ! wn.done[ c.id ] ) { folded[ h ] = ( folded[ h ] || 0 ) + 1; return ''; }
				return waitCard( c );
			} ).join( '' ) + Object.keys( folded ).map( function ( h ) {
				return '<button type="button" class="a-btn quiet wide" data-wn-more="' + esc( h ) + '">' + folded[ h ] + ' more from ' + esc( h ) + '</button>';
			} ).join( '' ) : '<p class="muted">Nothing is waiting. Comments from people you follow appear on your page straight away.</p>';
			if ( later.length ) {
				body += '<button type="button" class="a-text-btn" data-later-toggle aria-expanded="' + wn.showLater + '">Set aside · ' + later.length + '</button>' + ( wn.showLater ? later.map( waitCard ).join( '' ) : '' );
			}
			var fresh = wn.items.filter( function ( i ) { return Date.parse( i.published ) > wn.since; } );
			var groups = {};
			fresh.forEach( function ( i ) { var k = i.list || ''; ( groups[ k ] = groups[ k ] || [] ).push( i ); } );
			var keys = Object.keys( groups ).sort( function ( a, b ) { return ( a === '' ) - ( b === '' ) || a.localeCompare( b ); } );
			body += '<h2 class="a-label">From people you follow</h2>';
			body += keys.length ? keys.map( function ( k ) {
				var g = groups[ k ], names = [];
				g.forEach( function ( i ) { var n = String( i.author.name || '' ).split( ' ' )[ 0 ]; if ( names.indexOf( n ) < 0 ) { names.push( n ); } } );
				var who = names.length > 2 ? names.slice( 0, 2 ).join( ', ' ) + ' and others' : names.join( ' and ' );
				return '<section class="a-wn-group"><div class="a-row"><h3 class="a-wn-h"><strong>' + esc( k || 'Everyone' ) + '</strong> <span class="muted">' + g.length + ' new from ' + esc( who ) + '</span></h3><a class="a-text-btn" href="#home" data-open-list="' + esc( k ) + '">Open</a></div>' +
					'<div class="a-wn-thumbs">' + g.slice( 0, 3 ).map( function ( i ) { var im = ( i.images && i.images[ 0 ] ) || { url: i.image, alt: '' }; return '<a href="' + esc( i.permalink ) + '"><img src="' + esc( im.url ) + '" alt="' + esc( i.content_note ? i.author.name + ': ' + i.content_note : i.author.name + ( im.alt ? ': ' + im.alt : '' ) ) + '" loading="lazy"' + ( i.content_note ? ' class="is-noted"' : '' ) + '></a>'; } ).join( '' ) + '</div></section>';
			} ).join( '' ) : '<p class="muted">Nothing new since your last look.</p>';
			var recent = wn.activity.filter( function ( a ) { return a.status === 'approved' && Date.parse( a.date ) > Date.now() - 14 * 86400000; } ).slice( 0, 12 );
			body += '<h2 class="a-label">On your page</h2>';
			body += recent.length ? '<ul class="a-wn-list">' + recent.map( function ( a ) {
				return '<li><strong>' + esc( unent( a.author.name ) ) + '</strong> ' + ( a.where === 'guestbook' ? 'signed your guest book' : 'commented on <a href="' + esc( a.post_url ) + '">' + esc( unent( a.post_title ) || 'your post' ) + '</a>' ) + ' <span class="muted">· ' + esc( gentle( a.date ) ) + '</span><span class="a-wn-quote">' + esc( String( a.text || '' ).slice( 0, 140 ) ) + '</span></li>';
			} ).join( '' ) + '</ul>' : '<p class="muted">No new comments or guest book entries in the last two weeks.</p>';
		}
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div><h1 class="a-greet">What\'s new</h1><p class="muted" style="margin:-2px 0 6px">Since your last look</p>' + body + '</main>' + bar( 'whatsnew' );
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
				}, function ( e ) { $$( '[data-id="' + id + '"]' ).forEach( function ( x ) { x.disabled = false; } ); alertIn( id, ( e && e.message ) || 'That didn\'t work. Try again.' ); } );
			} );
		} );
	}
	function alertIn( id, text ) { var card = $( '[data-card="' + id + '"]' ); if ( card ) { card.insertAdjacentHTML( 'beforeend', '<p class="a-help" role="alert">' + esc( text ) + '</p>' ); } }
	function waitCard( c ) {
		var d = wn.done[ c.id ], host = hostOf( c.author.url );
		var ctx = ( c.where === 'guestbook' ? 'Guest book' : 'Comment on ' + ( c.post && c.post.title ? unent( c.post.title ) : 'your post' ) ) + ( c.via ? ' · followed by ' + c.via : '' );
		if ( d ) {
			var msg = d.status === 'show' ? 'Shown on your page.' : d.status === 'later' ? 'Set aside. It stays hidden; you\'ll find it under Set aside.' : 'Blocked quietly. Nothing from ' + host + ' will show here, and they aren\'t told.';
			return '<div class="a-card a-wait is-done" data-card="' + c.id + '"><p role="status" style="margin:0">' + esc( msg ) + '</p>' + ( d.status === 'block' ? '<button type="button" class="a-text-btn" data-wn="undo" data-id="' + c.id + '">Undo</button>' : '' ) + '</div>';
		}
		return '<div class="a-card a-wait" data-card="' + c.id + '"><div class="a-wait-head">' + face( c.author.avatar, c.author.name, 36 ) +
			'<span><strong>' + esc( unent( c.author.name ) ) + '</strong> <span class="muted">' + esc( host ) + '</span><span class="muted small a-ctx">' + esc( ctx ) + '</span></span></div>' +
			'<p class="a-wait-text">' + esc( c.text ) + '</p>' +
			'<div class="a-wait-acts"><button type="button" class="a-btn" data-wn="show" data-id="' + c.id + '">Show it</button>' + ( c.later ? '' : '<button type="button" class="a-btn quiet" data-wn="later" data-id="' + c.id + '">Not now</button>' ) +
			'<button type="button" class="a-text-btn a-block" data-wn="block" data-id="' + c.id + '">Block this site</button></div></div>';
	}

	/* ── Setup wizard (DESIGN §13): Hello · Interests · Look · Your people · Ready ── */
	var STEPS = [ 'Hello', 'Interests', 'Look', 'Your people', 'Ready' ];
	var MODE_HINTS = {
		auto: 'Follows the phone: light by day, dark at night.',
		light: 'Bright linen and ink, like a fresh page.',
		paper: 'Warm paper with a book font for reading.',
		dark: 'A dark, quiet ground that\'s easy on the eyes at night.'
	};
	var su = { loaded: false, d: null, step: 0, busy: false, error: '', picked: [], own: '', sugg: null, ticks: {}, photoBusy: false, photoMsg: '' };
	function viewSetup() {
		if ( su.loaded ) { return renderSetup(); }
		if ( su.loading ) { return; }
		su.loading = true;
		root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div><div class="a-feed-skel" aria-hidden="true"><span style="height:280px"></span></div><p class="sr" role="status">Loading…</p></main>';
		send( 'GET', 'setup', null ).then( function ( d ) {
			su.d = d; su.loaded = true; su.loading = false; su.step = d.done ? 0 : Math.min( 4, d.step || 0 );
			su.picked = d.interests.map( function ( i ) { return i.name; } );
			renderSetup();
		}, function ( e ) {
			su.loading = false;
			root.innerHTML = '<main class="a-main" id="main"><div class="a-top">' + wordmark() + '</div><div class="a-notice" role="alert"><p>' + esc( e.message ) + '</p><a class="a-btn quiet" href="#home">Go to Home</a></div></main>';
		} );
	}
	function accentCss( a ) {
		if ( ! a || a.key === 'moss' ) { return ''; }
		return ':root,[data-mode="light"],[data-mode="paper"]{--accent:' + a.hex + ';--accent-ink:' + a.ink + '}[data-mode="dark"]{--accent:' + a.dark + ';--accent-ink:#1c1916}';
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
	function ikey( s ) { return String( s ).toLowerCase().replace( /[^\p{L}\p{N}]+/gu, '' ); }
	function initials( n ) { return String( n || '?' ).trim().split( /\s+/ ).slice( 0, 2 ).map( function ( w ) { return w.charAt( 0 ); } ).join( '' ).toUpperCase(); }
	function setupHead() {
		var n = su.step;
		return '<header class="a-head">' + ( n > 0 ? '<button type="button" class="a-text-btn" data-su-back>' + icon( 'back', 20 ) + 'Back</button>' : '<span></span>' ) +
			'<h1>Step ' + ( n + 1 ) + ' of 5</h1><button type="button" class="a-text-btn" data-su-later>Finish later</button></header>' +
			'<div class="a-su-progress" role="progressbar" aria-label="Setup" aria-valuemin="1" aria-valuemax="5" aria-valuenow="' + ( n + 1 ) + '" aria-valuetext="Step ' + ( n + 1 ) + ' of 5: ' + STEPS[ n ] + '">' +
			STEPS.map( function ( s, i ) { return '<span' + ( i <= n ? ' class="is-on"' : '' ) + '></span>'; } ).join( '' ) + '</div>';
	}
	function setupFoot( label ) {
		return ( su.error ? '<div class="a-notice" role="alert"><p>' + esc( su.error ) + '</p></div>' : '' ) +
			'<div class="a-su-foot"><button type="button" class="a-btn wide" data-su-next' + ( su.busy ? ' disabled' : '' ) + '>' + esc( su.busy ? 'Saving…' : label ) + '</button></div>';
	}
	function suMove() {
		var st = mv.inState;
		if ( st && st.state === 'done' ) {
			return '<section class="a-su-move">' + moveProgress( st ) + '<button type="button" class="a-btn wide" data-su-move-go>Continue</button></section>';
		}
		if ( st && st.state === 'moving' ) { return '<section class="a-su-move">' + moveProgress( st ) + '</section>'; }
		return '<section class="a-su-move"><button type="button" class="a-link" data-su-move-open aria-expanded="' + !! su.moveOpen + '">Moving here from another Stubli?</button>' +
			( su.moveOpen ? '<p class="muted">On your old Stubli, open You → Move to another site → Make a move link. Paste it here and everything comes along: photos, journal, guest book, people and settings.</p><form class="a-inline" data-su-move style="margin-top:0"><label class="sr" for="a-su-mv">Move link</label><input id="a-su-mv" class="a-field" type="text" inputmode="url" autocomplete="off" placeholder="oldsite.com/?stubli_move=…"><button type="submit" class="a-btn quiet"' + ( mv.busy ? ' disabled' : '' ) + '>Start</button></form>' + ( mv.msg ? '<div class="a-notice" role="alert"><p>' + esc( mv.msg ) + '</p></div>' : '' ) : '' ) + '</section>';
	}
	function bindSuMove() {
		var redraw = function () { if ( location.hash === '#setup' ) { renderSetup(); } };
		var op = $( '[data-su-move-open]' ); if ( op ) { op.addEventListener( 'click', function () { su.moveOpen = ! su.moveOpen; renderSetup(); var f = $( '#a-su-mv' ); if ( f ) { f.focus(); } } ); }
		var fm = $( '[data-su-move]' ); if ( fm ) { fm.addEventListener( 'submit', function ( e ) { e.preventDefault(); var v = $( '#a-su-mv' ).value.trim(); if ( v ) { startMove( v, redraw ).catch( function () {} ); } } ); }
		var go = $( '[data-su-move-go]' ); if ( go ) { go.addEventListener( 'click', function () { send( 'GET', 'setup', null ).then( function ( d ) { su.d = d; su.picked = d.interests.map( function ( i ) { return i.name; } ); B.name = /^(your|my)\b/i.test( d.name ) ? '' : d.name.split( /\s+/ )[ 0 ]; applyLook(); setupGo( 4 ); } ); } ); }
	}
	function renderSetup() {
		var d = su.d, n = su.step, body = '';
		if ( n === 0 ) {
			body = '<h2 class="a-su-title">Hello! Whose Stubli is this?</h2><p class="muted">This is what people see at the top of your page. You can change it any time.</p>' +
				'<div class="a-su-photo"><label class="a-su-av" for="a-su-file">' + ( d.avatar ? '<img src="' + esc( d.avatar ) + '" alt="Your photo">' : '<span aria-hidden="true">' + esc( initials( d.name ) ) + '</span>' ) + '</label>' +
				'<div><label class="a-btn quiet" for="a-su-file">' + icon( 'photo', 18 ) + ( d.avatar ? 'Change photo' : 'Add a photo' ) + '</label><input id="a-su-file" class="a-file" type="file" accept="image/*"><p class="a-help" aria-live="polite" data-su-photo-msg>' + esc( su.photoMsg || 'Optional. A face, a place, anything that feels like you.' ) + '</p></div></div>' +
				'<label class="a-label" for="a-su-name">Your name</label><input id="a-su-name" class="a-field" type="text" maxlength="60" autocomplete="name" placeholder="Ana Keller" value="' + esc( d.name ) + '" aria-describedby="a-su-name-h"><p class="a-help" id="a-su-name-h">The name people see on your page.</p>' +
				'<label class="a-label" for="a-su-bio">One line about you</label><textarea id="a-su-bio" class="a-field a-grow" rows="2" maxlength="160" placeholder="Photos from walks around Zurich">' + esc( d.bio ) + '</textarea><p class="a-count" data-su-bio-count>' + d.bio.length + ' / 160</p>' +
				'<label class="a-label" for="a-su-pro">Pronouns <span class="muted" style="font-weight:400">(optional)</span></label><input id="a-su-pro" class="a-field" type="text" maxlength="30" value="' + esc( d.pronouns ) + '" placeholder="she/her, they/them…">' +
				setupFoot( 'Next' ) + suMove();
		} else if ( n === 1 ) {
			var used = {}, all = [];
			d.interests.forEach( function ( i ) { used[ ikey( i.name ) ] = i.count; all.push( i.name ); } );
			su.picked.concat( d.ideas ).forEach( function ( p ) { if ( all.map( ikey ).indexOf( ikey( p ) ) < 0 ) { all.push( p ); } } );
			body = '<h2 class="a-su-title">What do you like to photograph?</h2><p class="muted">Each interest becomes a tab on your page and a way to sort the people you follow. Pick up to ' + d.max + '. You can change them later.</p>' +
				'<div class="a-chips" role="group" aria-label="Interests" style="margin-top:16px">' + all.map( function ( p ) {
					var on = su.picked.some( function ( x ) { return ikey( x ) === ikey( p ); } ), has = used[ ikey( p ) ] > 0;
					return '<button type="button" class="a-chip" aria-pressed="' + on + '" data-int="' + esc( p ) + '"' + ( has ? ' aria-describedby="a-su-has"' : '' ) + '>' + ( on ? icon( 'check', 16 ) : '' ) + esc( p ) + '</button>';
				} ).join( '' ) + '</div>' +
				( d.interests.some( function ( i ) { return i.count > 0; } ) ? '<p class="a-help" id="a-su-has">Interests that already have photos stay, even if you untick them.</p>' : '' ) +
				'<form class="a-inline" data-su-own><label class="sr" for="a-su-own">Add your own interest</label><input id="a-su-own" class="a-field" type="text" maxlength="40" placeholder="Add your own"><button type="submit" class="a-btn quiet">Add</button></form>' +
				'<p class="a-help" aria-live="polite" data-su-int-msg>' + su.picked.length + ' of ' + d.max + ' chosen</p>' +
				setupFoot( su.picked.length ? 'Next' : 'Skip for now' );
		} else if ( n === 2 ) {
			var a = accentOf( d.accent ), pm = d.mode === 'auto' ? ( matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' ) : d.mode;
			var tabs = ( su.picked.length ? su.picked : [ 'Photos' ] ).slice( 0, 3 );
			body = '<h2 class="a-su-title">Make it look like you</h2><p class="muted">Choose a colour for buttons and tabs, and how your page reads.</p>' +
				'<div class="a-su-preview" data-mode="' + pm + '" style="--accent:' + ( pm === 'dark' ? a.dark : a.hex ) + ';--accent-ink:' + ( pm === 'dark' ? '#1c1916' : a.ink ) + '" aria-hidden="true">' +
				'<div class="a-su-pv-head"><span class="a-su-pv-av">' + ( d.avatar ? '<img src="' + esc( d.avatar ) + '" alt="">' : '' ) + '</span><span><strong>' + esc( d.name ) + '</strong><small>' + esc( d.bio || 'One line about you' ) + '</small></span></div>' +
				'<div class="a-su-pv-tabs">' + tabs.map( function ( t, i ) { return '<span' + ( i === 0 ? ' class="is-on"' : '' ) + '>' + esc( t ) + '</span>'; } ).join( '' ) + '</div>' +
				'<div class="a-su-pv-grid"><span></span><span></span><span></span></div><span class="a-su-pv-btn">Follow</span></div>' +
				'<p class="a-label" id="a-su-col">Colour: ' + esc( a.name ) + '</p><div class="a-swatches" role="radiogroup" aria-labelledby="a-su-col">' + d.accents.map( function ( c ) {
					var on = c.key === d.accent;
					return '<button type="button" role="radio" aria-checked="' + on + '" aria-label="' + esc( c.name + ( c.key === 'moss' ? ', the Stubli green' : '' ) ) + '" data-accent="' + c.key + '" tabindex="' + ( on ? 0 : -1 ) + '"><span style="background:' + ( pm === 'dark' ? c.dark : c.hex ) + '"></span><small>' + esc( c.name ) + '</small></button>';
				} ).join( '' ) + '</div>' +
				( d.accent === 'gold' && pm !== 'dark' ? '<p class="a-help" role="status">Gold is made for dark pages. On a light page your tabs blend in a little; Dark mode suits it best.</p>' : '' ) +
				'<p class="a-label" id="a-su-mode">Reading mode</p><div class="a-chips" role="radiogroup" aria-labelledby="a-su-mode">' + [ [ 'auto', 'Automatic' ], [ 'light', 'Light' ], [ 'paper', 'Paper' ], [ 'dark', 'Dark' ] ].map( function ( m ) {
					return '<button type="button" class="a-chip" role="radio" aria-checked="' + ( d.mode === m[ 0 ] ) + '" data-mode-pick="' + m[ 0 ] + '">' + ( d.mode === m[ 0 ] ? icon( 'check', 16 ) : '' ) + m[ 1 ] + '</button>';
				} ).join( '' ) + '</div><p class="a-help">' + esc( MODE_HINTS[ d.mode ] ) + ' Visitors can still switch with the Aa button.</p>' +
				'<label class="a-check" style="margin-top:18px"><span><strong>Show "Made with Stubli" in my footer</strong><span class="muted small">A small link to stubli.app, so visitors can find out how to get their own. Off unless you tick it.</span></span><input type="checkbox" data-su-credit' + ( d.credit ? ' checked' : '' ) + '></label>' +
				setupFoot( 'Next' );
		} else if ( n === 3 ) {
			var opts = [ '' ].concat( su.picked );
			body = '<h2 class="a-su-title">Your people</h2><p class="muted">Their new photos will be in your Home feed. Nobody is told who else you follow.</p>' +
				( d.follows.length ? '<ul class="a-su-people">' + d.follows.map( function ( f ) {
					return '<li><span class="a-av a-su-ini" aria-hidden="true">' + esc( initials( f.name || hostOf( f.url ) ) ) + '</span><span class="a-su-who"><strong>' + esc( f.name || hostOf( f.url ) ) + '</strong><small class="muted">' + esc( hostOf( f.url ) ) + '</small>' +
						( su.picked.length ? '<label class="sr" for="a-su-l' + f.id + '">Interest for ' + esc( f.name ) + '</label><select id="a-su-l' + f.id + '" class="a-su-select" data-su-list="' + f.id + '">' + opts.map( function ( o ) { return '<option value="' + esc( o ) + '"' + ( o === f.list ? ' selected' : '' ) + '>' + esc( o || 'No interest' ) + '</option>'; } ).join( '' ) + '</select>' : '' ) +
						'</span><span class="a-su-following">' + icon( 'check', 16 ) + 'Following</span></li>';
				} ).join( '' ) + '</ul>' : '<p class="a-su-empty">Nobody yet, and that\'s fine. If a friend sent you an invite link, paste it below and you\'ll follow each other.</p>' ) +
				'<label class="a-label" for="a-su-inv">Got an invite link?</label><form class="a-inline" data-su-inv style="margin-top:0"><input id="a-su-inv" class="a-field" type="url" inputmode="url" autocomplete="off" placeholder="theirname.com/invite/?t=…"><button type="submit" class="a-btn quiet">Use link</button></form><p class="a-help" aria-live="polite" data-su-inv-msg></p>' +
				( su.sugg && su.sugg.length ? '<p class="a-label">People they follow</p><ul class="a-su-people">' + su.sugg.map( function ( s ) {
					var on = !! su.ticks[ s.url ];
					return '<li><button type="button" class="a-su-row" role="checkbox" aria-checked="' + on + '" data-su-tick="' + esc( s.url ) + '">' + ( s.avatar ? '<img class="a-av" src="' + esc( s.avatar ) + '" alt="">' : '<span class="a-av a-su-ini" aria-hidden="true">' + esc( initials( s.name || hostOf( s.url ) ) ) + '</span>' ) +
						'<span class="a-su-who"><strong>' + esc( s.name || hostOf( s.url ) ) + '</strong><small class="muted">Followed by ' + esc( viaText( s.via ) ) + '</small></span><span class="a-su-box" aria-hidden="true">' + ( on ? icon( 'check', 18 ) : '' ) + '</span></button></li>';
				} ).join( '' ) + '</ul>' : '' ) +
				'<p class="a-label">Follow someone by address</p>' + followForm( 'setup' ) +
				'<p class="a-label">Invite a friend</p><p class="muted" style="margin:0 0 8px">Send them a link. Once they\'ve set up their Stubli, you follow each other. Each link works once, for 30 days.</p><div data-invite-out></div><button type="button" class="a-btn quiet" data-invite>Create an invite link</button>' +
				setupFoot( Object.keys( su.ticks ).some( function ( k ) { return su.ticks[ k ]; } ) ? 'Follow and continue' : 'Next' );
		} else {
			var ios = /iphone|ipad|ipod/i.test( navigator.userAgent ), times = [];
			for ( var h = 6; h <= 22; h++ ) { times.push( ( h < 10 ? '0' : '' ) + h + ':00' ); }
			if ( times.indexOf( d.digest ) < 0 ) { times.push( d.digest ); times.sort(); }
			body = '<h2 class="a-su-title">You\'re ready</h2><p class="muted">A few last choices, then your feed.</p>' +
				'<label class="a-label" for="a-su-dig">Your daily summary</label><p class="muted" style="margin:0 0 8px">At most one a day, with new comments and guest book entries. Never one per post.</p>' +
				'<select id="a-su-dig" class="a-field a-su-select" data-su-dig>' + times.map( function ( t ) { return '<option' + ( t === d.digest ? ' selected' : '' ) + '>' + t + '</option>'; } ).join( '' ) + '</select>' +
				( pushSupported() && ! iosNeedsInstall() ? '<div class="a-actions" style="margin-top:10px">' + ( ds.on ? '<p class="a-ds-status">' + icon( 'check', 16 ) + 'It will arrive on this phone</p>' : '<button type="button" class="a-btn quiet" data-su-push' + ( ds.busy ? ' disabled' : '' ) + '>' + ( ds.busy ? 'Asking your phone…' : 'Send it to this phone' ) + '</button>' ) + '</div>' : '<p class="a-help">You can turn on phone notifications later in You → Daily summary.</p>' ) +
				( ds.msg ? '<p class="a-help" role="status">' + esc( ds.msg ) + '</p>' : '' ) +
				( isStandalone() ? '' : '<p class="a-label">Put Stubli on your home screen</p><div class="a-su-hs"><section' + ( ios ? ' class="is-you"' : '' ) + '><strong>iPhone</strong><p class="muted">In Safari, tap the Share button, then <strong>Add to Home Screen</strong>.</p></section><section' + ( ios ? '' : ' class="is-you"' ) + '><strong>Android</strong><p class="muted">Open the browser menu ⋮, then <strong>Add to Home screen</strong> or <strong>Install app</strong>.</p></section></div><p class="a-help">It opens like an app, without the browser around it.</p>' ) +
				'<p class="a-label">Your site</p>' +
				'<label class="a-check"><span><strong>Keep my site out of search engines</strong><span class="muted small">Google and others won\'t list it. Friends still find you through Stubli.</span></span><input type="checkbox" data-su-hidden' + ( d.hidden ? ' checked' : '' ) + '></label>' +
				'<label class="a-check"><span><strong>Ask AI crawlers to stay away</strong><span class="muted small">A request that respectable bots honour.</span></span><input type="checkbox" data-su-ai' + ( d.block_ai ? ' checked' : '' ) + '></label>' +
				'<p class="a-help">' + ( d.helper === 'active' ? 'Stubli also turns on comments, so replies from friends\' sites can arrive.' : 'Stubli also turns on comments and adds the free Webmention plugin from WordPress.org, so replies from friends\' sites can arrive.' ) + ' You can change all of this later in Settings → Stubli.</p>' +
				( su.helperFail ? '<div class="a-notice" role="alert"><p>' + esc( su.helperFail ) + ' <a href="' + esc( d.manual ) + '">Add Webmention in Plugins</a></p></div>' : '' ) +
				'<p class="a-su-note">Your first photo can wait. When you\'re ready, tap ' + icon( 'plus', 18 ) + ' at the bottom.</p>' +
				setupFoot( su.helperFail ? 'Open my feed anyway' : ( su.busy && su.helperBusy ? 'Adding Webmention…' : 'Open my feed' ) );
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
			file.addEventListener( 'change', function () {
				var f = file.files && file.files[ 0 ], m = $( '[data-su-photo-msg]' );
				if ( ! f ) { return; }
				m.textContent = 'Uploading…';
				squareAvatar( f ).then( function ( blob ) {
					var fd = new FormData(); fd.append( 'photo', blob, 'profile.jpg' );
					return send( 'POST', 'setup/photo', fd );
				} ).then( function ( r ) { d.avatar = r.avatar; su.photoMsg = 'Lovely. That\'s your photo now.'; renderSetup(); },
					function ( e ) { m.textContent = ( e && e.message ) || 'That photo couldn\'t be read. Try another one.'; } );
			} );
			next.addEventListener( 'click', function () {
				if ( ! d.name.trim() ) { su.error = 'Add the name people should see.'; renderSetup(); $( '#a-su-name' ).focus(); return; }
				setupSave( { name: d.name, bio: d.bio, pronouns: d.pronouns, step: 1 } ).then( function () { B.name = /^(your|my)\b/i.test( d.name.trim() ) ? '' : d.name.trim().split( /\s+/ )[ 0 ]; setupGo( 1 ); } );
			} );
		} else if ( su.step === 1 ) {
			var msg = $( '[data-su-int-msg]' );
			$$( '[data-int]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var v = b.dataset.int, i = su.picked.map( ikey ).indexOf( ikey( v ) );
					if ( i > -1 ) { su.picked.splice( i, 1 ); } else if ( su.picked.length >= d.max ) { msg.textContent = 'That\'s ' + d.max + ' already. Untick one to choose another.'; return; } else { su.picked.push( v ); }
					renderSetup(); var again = $( '[data-int="' + v.replace( /"/g, '\\"' ) + '"]' ); if ( again ) { again.focus(); }
				} );
			} );
			$( '[data-su-own]' ).addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var inp = $( '#a-su-own' ), v = inp.value.trim().replace( /\s+/g, ' ' );
				if ( ! v ) { inp.focus(); return; }
				if ( su.picked.length >= d.max ) { msg.textContent = 'That\'s ' + d.max + ' already. Untick one to add another.'; return; }
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
			var cr = $( '[data-su-credit]' );
			cr.addEventListener( 'change', function () { d.credit = cr.checked; } );
			next.addEventListener( 'click', function () { setupSave( { accent: d.accent, mode: d.mode, credit: !! d.credit, step: 3 } ).then( function () { applyLook(); setupGo( 3 ); } ); } );
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
				btn.disabled = true; btn.textContent = 'Using…'; m.textContent = '';
				send( 'POST', 'setup/invite', { link: v } ).then( function ( r ) {
					d.follows = r.follows; feed.loaded = false; su.sugg = null; loadSugg();
					var who = r.result && r.result.name || 'them';
					su.invMsg = r.result && r.result.followed_back ? 'You and ' + who + ' now follow each other.' : 'You\'re following ' + who + '. They\'ll follow you back once their site hears from yours.';
					renderSetup(); var mm = $( '[data-su-inv-msg]' ); if ( mm ) { mm.textContent = su.invMsg; }
				}, function ( er ) { btn.disabled = false; btn.textContent = 'Use link'; m.textContent = er.message; } );
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
					enablePush().then( function () { ds.busy = false; ds.msg = ''; renderSetup(); }, function ( e ) { ds.busy = false; ds.msg = ( e && e.message ) || 'That didn\'t work. You can try again later in You → Daily summary.'; renderSetup(); } );
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
					su.busy = false; su.helperBusy = false; su.helperFail = ( e && e.message ) || 'Webmention couldn\'t be added just now.'; renderSetup();
				} );
			} );
		}
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
		return '<section class="a-card"><h2>Finish setting up</h2><p class="muted">' + ( n ? 'You\'re on step ' + ( n + 1 ) + ' of 5. ' : '' ) + 'Your name, interests, colour and people. About three minutes, whenever suits you.</p><a class="a-btn" href="#setup">Continue setup</a></section>';
	}

	/* ── Composer step 1: choose and crop ── */
	function seg( active ) {
		return '<div class="a-seg" role="group" aria-label="What to post"><button type="button" data-go="#new" aria-pressed="' + ( active === 'photo' ) + '">Photo</button><button type="button" data-go="#journal" aria-pressed="' + ( active === 'journal' ) + '">Journal</button></div>';
	}
	function viewPick() {
		var p = post, cur = p.photos[ p.current ];
		var ratioLabel = { portrait: 'Portrait', square: 'Square', landscape: 'Landscape' };
		root.innerHTML = '<header class="a-head"><button type="button" class="a-text-btn" data-cancel>Cancel</button>' + seg( 'photo' ) +
			'<button type="button" class="a-btn" data-next' + ( p.photos.length ? '' : ' disabled' ) + '>Next</button></header>' +
			'<main class="a-compose" id="main"><h1 class="sr">New photo post</h1>' +
			'<div class="a-preview">' + ( p.loading ? '<p class="muted" role="status">Opening your video…</p>' : cur ? ( cur.video && cur.plain ? '<div class="a-preview-frame" style="aspect-ratio:' + ( cur.w / cur.h ) + '"><img src="' + cur.thumb + '" alt="Video ' + ( p.current + 1 ) + ', ' + fmtDur( cur.duration ) + '"></div><p class="a-help">This browser can\'t preview this video, but it shares fine.</p>' : cur.video ? '<div class="a-preview-frame" style="aspect-ratio:' + ( cur.w / cur.h ) + '"><video src="' + cur.src + '" poster="' + cur.thumb + '" controls playsinline preload="metadata" aria-label="Preview of video ' + ( p.current + 1 ) + '"></video></div>' : '<div class="a-preview-frame" style="aspect-ratio:' + RATIOS[ p.ratio ] + '"><img src="' + cur.thumb + '" alt="Preview of photo ' + ( p.current + 1 ) + '"></div>' ) :
				'<div class="a-preview-empty"><p>Choose up to ' + B.maxPhotos + ' photos or videos for one post. Videos can be up to a minute.</p><label class="a-btn">' + icon( 'photo', 18 ) + 'Choose photos or videos<input class="a-file" type="file" accept="image/*,video/mp4,video/quicktime" multiple data-files></label></div>' ) + '</div>' +
			( p.photos.length ? '<div class="a-ratios" role="group" aria-label="Shape">' + Object.keys( RATIOS ).map( function ( k ) {
				return '<button type="button" class="a-chip" data-ratio="' + k + '" aria-pressed="' + ( p.ratio === k ) + '">' + icon( k, 16 ) + ratioLabel[ k ] + '</button>';
			} ).join( '' ) + '</div>' : '' ) +
			( p.photos.some( function ( x ) { return x.video; } ) ? '<p class="a-help" style="margin:-8px 0 12px">The shape applies to photos. Videos keep their own shape.</p>' : '' ) +
			'<div class="a-row"><h2 class="a-label">Your photos</h2><span class="muted small" aria-live="polite">' + ( p.photos.length ? p.photos.length + ' selected · up to ' + B.maxPhotos : 'up to ' + B.maxPhotos ) + '</span></div>' +
			'<div class="a-thumbs">' + p.photos.map( function ( ph, i ) {
				return '<div style="position:relative"><button type="button" class="a-thumb' + ( i === p.current ? ' is-current' : '' ) + '" data-pick="' + i + '" aria-label="Show ' + ( ph.video ? 'video' : 'photo' ) + ' ' + ( i + 1 ) + '"><img src="' + ph.thumb + '" alt="">' + ( ph.video ? '<span class="a-vid-dur" aria-hidden="true">' + icon( 'play', 12 ) + fmtDur( ph.duration ) + '</span>' : '' ) + '<span class="a-order" aria-hidden="true">' + ( i + 1 ) + '</span></button>' +
					'<button type="button" class="a-remove" data-remove="' + i + '" aria-label="Remove ' + ( ph.video ? 'video' : 'photo' ) + ' ' + ( i + 1 ) + '">' + icon( 'x', 16 ) + '</button></div>';
			} ).join( '' ) +
			( p.photos.length < B.maxPhotos ? '<label class="a-thumb a-add">' + icon( 'plus', 24 ) + '<span>' + ( p.photos.length ? 'Add more' : 'Choose' ) + '</span><input class="a-file" type="file" accept="image/*,video/mp4,video/quicktime" multiple data-files></label>' : '' ) +
			'</div>' + ( p.error ? '<div class="a-notice" role="alert"><p>' + esc( p.error ) + '</p></div>' : '' ) +
			'<p class="a-lock">' + icon( 'lock', 14 ) + 'Location details are removed from your photos and videos before they\'re shared.</p></main>';
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
	function addFiles( list ) {
		var p = post, files = Array.prototype.slice.call( list || [] ), room = B.maxPhotos - p.photos.length;
		if ( files.length > room ) { p.error = 'A post holds up to ' + B.maxPhotos + ' photos, so only the first ' + room + ' were added.'; files = files.slice( 0, room ); }
		var bad = 0, notes = [];
		if ( files.some( isVideo ) ) { p.loading = true; viewPick(); }
		Promise.all( files.map( function ( f ) {
			if ( isVideo( f ) ) {
				if ( B.maxUpload && f.size > B.maxUpload ) { notes.push( 'This video is ' + fmtMB( f.size ) + ', and your host accepts up to ' + fmtMB( B.maxUpload ) + ' per file. Try a shorter clip.' ); return null; }
				return videoInfo( f ).catch( function () { return videoInfoFromFile( f ); } ).then( function ( v ) {
					if ( v.duration > MAX_VIDEO + 0.5 ) { URL.revokeObjectURL( v.url ); URL.revokeObjectURL( v.thumb ); notes.push( 'This video is ' + fmtDur( v.duration ) + ' long. Videos can be up to a minute; trim it in your phone\'s Photos app, then choose it again.' ); return null; }
					return { file: f, thumb: v.thumb, alt: '', video: true, src: v.url, duration: v.duration, poster: v.poster, w: v.w, h: v.h };
				}, function () { notes.push( 'That video couldn\'t be opened here. Try an MP4 or MOV from your phone\'s camera.' ); return null; } );
			}
			return makeThumb( f ).then( function ( t ) { return { file: f, thumb: t, alt: '' }; }, function () { bad++; return null; } );
		} ) ).then( function ( got ) {
			p.loading = false;
			got.forEach( function ( g ) { if ( g ) { p.photos.push( g ); } } );
			if ( bad ) { notes.push( ( bad === 1 ? 'One photo' : bad + ' photos' ) + ' couldn\'t be opened. Try a JPEG or PNG, or pick it again.' ); }
			if ( notes.length ) { p.error = ( p.error ? p.error + ' ' : '' ) + notes.join( ' ' ); }
			p.current = Math.max( 0, p.photos.length - 1 );
			viewPick();
		} );
	}

	/* ── Composer step 2: words, collection and care ── */
	function viewDetails() {
		var p = post, cols = ( B.collections || [] ).slice();
		if ( p.collection && cols.indexOf( p.collection ) < 0 ) { cols.push( p.collection ); }
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="#new" aria-label="Back to photos">' + icon( 'back', 22 ) + '</a><h1>New post</h1>' +
			'<button type="button" class="a-btn" data-share' + ( p.busy ? ' disabled' : '' ) + '>' + ( p.busy ? 'Sharing…' : 'Share' ) + '</button></header>' +
			'<main class="a-compose" id="main">' +
			'<div class="a-strip">' + p.photos.map( function ( ph, i ) { return '<img src="' + ph.thumb + '" alt="' + ( ph.video ? 'Video ' : 'Photo ' ) + ( i + 1 ) + '" style="aspect-ratio:' + ( ph.video ? ph.w / ph.h : RATIOS[ p.ratio ] ) + ';width:auto;height:80px">'; } ).join( '' ) + '</div>' +
			'<label class="a-label" for="a-caption">Caption</label>' +
			'<textarea id="a-caption" class="a-textarea" maxlength="' + B.maxCaption + '" placeholder="Say something about it, or leave it empty.">' + esc( p.caption ) + '</textarea>' +
			'<p class="a-count" data-count' + ( p.caption.length < B.maxCaption - 200 ? ' hidden' : '' ) + '>' + p.caption.length + ' / ' + B.maxCaption + '</p>' +
			( p.linkOpen ? '<div class="a-inline"><label class="sr" for="a-link">Web address</label><input id="a-link" class="a-field" type="url" inputmode="url" placeholder="https://…"><button type="button" class="a-btn quiet" data-link-add>Add</button></div><p class="a-help">It appears at the end of your caption and people can tap it.</p>' :
				'<button type="button" class="a-chip" data-link style="margin-top:8px">' + icon( 'link', 16 ) + 'Add a link</button>' ) +
			'<h2 class="a-label" id="a-col-h">Which collection?</h2>' +
			'<div class="a-chips" role="radiogroup" aria-labelledby="a-col-h">' + cols.map( function ( c ) {
				var on = ! p.addingCollection && p.collection === c;
				return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-col="' + esc( c ) + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( c ) + '</button>';
			} ).join( '' ) + '<button type="button" class="a-chip" role="radio" aria-checked="' + p.addingCollection + '" data-col-new>' + ( p.addingCollection ? icon( 'check', 16 ) : '' ) + '+ New</button></div>' +
			( p.addingCollection ? '<div class="a-inline"><label class="sr" for="a-newcol">New collection name</label><input id="a-newcol" class="a-field" maxlength="40" placeholder="e.g. Mountains" value="' + esc( p.newCollection ) + '"></div><p class="a-help">Each collection is a folder tab on your page. An emoji at the start is welcome.</p>' : '' ) +
			'<h2 class="a-label">Describe ' + ( p.photos.some( function ( x ) { return x.video; } ) ? 'what\'s shown' : 'the photos' ) + ' for people who can\'t see it</h2>' +
			p.photos.map( function ( ph, i ) {
				return '<div class="a-alt"><img src="' + ph.thumb + '" alt=""><label class="sr" for="a-alt-' + i + '">Description of photo ' + ( i + 1 ) + '</label><input id="a-alt-' + i + '" class="a-field" data-alt="' + i + '" maxlength="300" placeholder="' + ( ph.video ? 'Video ' + ( i + 1 ) + ': what happens in it?' : 'Photo ' + ( i + 1 ) + ': what\'s in it?' ) + '" value="' + esc( ph.alt ) + '"></div>';
			} ).join( '' ) +
			audienceChips( p.audience, 'aud' ) +
			'<label class="a-check"><span><strong>Add a content note</strong><span class="muted small">The photo stays softly blurred until someone chooses to see it.</span></span><input type="checkbox" data-note' + ( p.note ? ' checked' : '' ) + '></label>' +
			( p.note ? '<div class="a-inline"><label class="sr" for="a-note">Content note</label><input id="a-note" class="a-field" maxlength="' + B.maxNote + '" placeholder="e.g. Flashing lights" value="' + esc( p.noteText ) + '"></div>' : '' ) +
			'<div data-msg aria-live="polite"></div>' +
			'<p class="a-lock">' + icon( 'lock', 14 ) + 'Location details are removed from your photos before they\'re shared.</p></main>';

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
		var missing = p.photos.map( function ( ph, i ) { return ph.alt.trim() ? 0 : i + 1; } ).filter( Boolean );
		if ( missing.length && ! p.altsChecked ) {
			msg( '<div class="a-notice calm" role="alert"><p>' + ( missing.length === 1 ? 'Photo ' + missing[ 0 ] + ' has' : 'Photos ' + missing.join( ', ' ) + ' have' ) + ' no description yet. People using a screen reader will only hear "photo".</p><div class="a-actions"><button type="button" class="a-btn quiet" data-fix>Add a description</button><button type="button" class="a-text-btn" data-anyway>Share anyway</button></div></div>' );
			$( '[data-fix]' ).addEventListener( 'click', function () { var f = $( '#a-alt-' + ( missing[ 0 ] - 1 ) ); f.focus(); f.scrollIntoView( { block: 'center' } ); } );
			$( '[data-anyway]' ).addEventListener( 'click', function () { p.altsChecked = true; share(); } );
			return;
		}
		if ( p.addingCollection && ! p.newCollection.trim() ) {
			msg( '<div class="a-notice calm" role="alert"><p>Give the new collection a name, or pick one of your collections.</p></div>' );
			$( '#a-newcol' ).focus();
			return;
		}
		if ( p.note && ! p.noteText.trim() ) {
			msg( '<div class="a-notice calm" role="alert"><p>Write a few words for the content note, so people know what to expect.</p></div>' );
			$( '#a-note' ).focus();
			return;
		}
		p.busy = true;
		var btn = $( '[data-share]' ); btn.disabled = true; btn.textContent = 'Sharing…';
		msg( '<div class="a-notice calm"><p>' + ( p.photos.some( function ( x ) { return x.video; } ) ? 'Removing location details and preparing…' : 'Preparing your photos…' ) + '</p><div class="a-progress"><span></span></div></div>' );
		var bar = function ( f ) { var s = $( '.a-progress span' ); if ( s ) { s.style.width = Math.round( f * 100 ) + '%'; } };
		var done = 0;
		var total = 0;
		Promise.all( p.photos.map( function ( ph ) {
			var job = ph.video ? stripLocation( ph.file ) : cropped( ph.file, p.ratio );
			return job.then( function ( b ) { done++; total += b.size + ( ph.video ? ph.poster.size : 0 ); bar( 0.3 * done / p.photos.length ); return b; } );
		} ) ).then( function ( blobs ) {
			if ( B.maxPost && total > B.maxPost ) { return Promise.reject( { message: 'Together these are ' + fmtMB( total ) + ', and your host accepts up to ' + fmtMB( B.maxPost ) + ' at once. Share the videos in separate posts, or choose a shorter clip.' } ); }
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
			var t = $( '.a-notice p' ); if ( t ) { t.textContent = 'Sharing…'; }
			return send( 'POST', 'posts', fd, function ( f ) { bar( 0.3 + 0.7 * f ); } );
		} ).then( function ( res ) {
			var thumb = p.photos[ 0 ].thumb, col = p.addingCollection ? p.newCollection.trim() : p.collection, aud = p.audience;
			if ( col && B.collections.indexOf( col ) < 0 ) { B.collections.unshift( col ); }
			post = null;
			doneView( 'Shared', aud === 'friends' ? 'Your friends see it in their Stubli' + ( col ? ', in ' + col : '' ) + '. It isn\'t on your public page.' : 'Your post is on your page' + ( col ? ' in ' + col : '' ) + '.', res && res.url, 'See your post', thumb, '#new', 'New post' );
		}, function ( err ) {
			p.busy = false;
			viewDetails();
			msg( '<div class="a-notice" role="alert"><p>' + esc( err && err.message || 'Something went wrong. Nothing was shared.' ) + '</p><p class="small muted">Your photos and words are still here.</p></div>' );
		} );
	}

	function doneView( title, text, url, urlLabel, thumb, again, againLabel ) {
		root.innerHTML = '<main class="a-main" id="main"><div class="a-done">' + ( thumb ? '<img src="' + thumb + '" alt="">' : '<span class="a-tick">' + icon( 'check', 28 ) + '</span>' ) +
			'<h1 class="a-greet" style="margin-top:0">' + esc( title ) + '</h1><p class="muted">' + esc( text ) + '</p><div class="a-actions">' +
			( url ? '<a class="a-btn" href="' + esc( url ) + '">' + esc( urlLabel ) + '</a>' : '' ) + '<a class="a-btn quiet" href="' + again + '">' + esc( againLabel ) + '</a></div></div></main>' + bar( '' );
		$( 'h1' ).setAttribute( 'tabindex', '-1' ); $( 'h1' ).focus();
	}

	/* ── Journal ── */
	function viewJournal() {
		var j = journal, max = B.maxJournal;
		root.innerHTML = '<header class="a-head"><button type="button" class="a-text-btn" data-cancel-j>Close</button>' + seg( 'journal' ) +
			'<button type="button" class="a-btn" data-save' + ( j.text.trim() && ! j.busy ? '' : ' disabled' ) + '>' + ( j.busy ? 'Saving…' : 'Save' ) + '</button></header>' +
			'<main class="a-compose" id="main"><h1 class="sr">New journal entry</h1><p class="a-date">' + esc( B.today ) + '</p>' +
			'<label class="sr" for="a-jtext">Journal entry</label><textarea id="a-jtext" class="a-journal-text" maxlength="' + max + '" placeholder="What happened today?">' + esc( j.text ) + '</textarea>' +
			( j.tool ? '<div class="a-inline"><label class="sr" for="a-jlink">' + ( j.tool === 'video' ? 'YouTube or Vimeo address' : 'Web address' ) + '</label><input id="a-jlink" class="a-field" type="url" inputmode="url" placeholder="' + ( j.tool === 'video' ? 'A YouTube or Vimeo link' : 'https://…' ) + '"><button type="button" class="a-btn quiet" data-jadd>Add</button></div>' +
				( j.tool === 'video' ? '<p class="a-help">It shows as a cover with a play button. Nothing loads from the video site until someone presses play.</p>' : '' ) : '' ) +
			'<div class="a-tools"><label class="a-tool" aria-label="Add a photo" title="Add a photo">' + icon( 'photo', 20 ) + '<input class="a-file" type="file" accept="image/*" multiple data-jphoto></label><button type="button" class="a-tool" data-tool="video" aria-label="Add a video link">' + icon( 'video', 20 ) + '</button><button type="button" class="a-tool" data-tool="link" aria-label="Add a link">' + icon( 'link', 20 ) + '</button>' +
			'<span class="a-count" data-jcount>' + ( j.text.trim() ? 'Draft saved · ' : '' ) + j.text.length + ' / ' + max + '</span></div>' +
			( j.uploading ? '<div class="a-notice calm" aria-live="polite"><p>Adding ' + ( j.uploading === 1 ? 'a photo' : j.uploading + ' photos' ) + '…</p></div>' : '' ) +
			( j.photos.length ? '<h2 class="a-caps">Photos in this entry</h2><p class="a-help" style="margin:-4px 0 10px">Each one appears where its [Photo] line is in your text. Move the line to move the photo.</p>' + j.photos.map( function ( ph ) {
				return '<div class="a-jphoto"><img src="' + esc( ph.url ) + '" alt=""><div class="a-jphoto-fields"><strong>Photo ' + ph.n + '</strong>' +
					'<label class="a-label a-jlabel" for="a-jcap-' + ph.n + '">Caption <span class="muted" style="font-weight:400">(shown under the photo)</span></label><input id="a-jcap-' + ph.n + '" class="a-field" maxlength="300" data-jcap="' + ph.n + '" value="' + esc( ph.caption ) + '">' +
					'<label class="a-label a-jlabel" for="a-jalt-' + ph.n + '">Description <span class="muted" style="font-weight:400">(for people who can\'t see it)</span></label><input id="a-jalt-' + ph.n + '" class="a-field" maxlength="300" data-jalt="' + ph.n + '" value="' + esc( ph.alt ) + '">' +
					'<button type="button" class="a-text-btn a-jremove" data-jremove="' + ph.n + '">Remove photo ' + ph.n + '</button></div></div>';
			} ).join( '' ) : '' ) +
			'<h2 class="a-caps" id="a-vis-h">Who can see this</h2><div class="a-vis" role="radiogroup" aria-labelledby="a-vis-h">' +
			'<button type="button" role="radio" aria-checked="' + ( j.visibility === 'everyone' ) + '" data-vis="everyone"><strong>' + ( j.visibility === 'everyone' ? '✓ ' : '' ) + 'Everyone</strong><span>In your Journal tab</span></button>' +
			'<button type="button" role="radio" aria-checked="' + ( j.visibility === 'only_me' ) + '" data-vis="only_me"><strong>' + ( j.visibility === 'only_me' ? '✓ ' : '' ) + 'Only me</strong><span>A private note</span></button></div>' +
			'<div data-msg aria-live="polite">' + ( j.error ? '<div class="a-notice" role="alert"><p>' + esc( j.error ) + '</p></div>' : '' ) + '</div>' +
			( j.visibility === 'only_me' && j.photos.length ? '<p class="a-help" style="margin-top:14px">Photos in an Only-me entry aren\'t shown anywhere, but anyone who has a photo\'s exact web address could open it.</p>' : '' ) +
			'<p class="a-help" style="margin-top:14px">Journal entries never appear in anyone\'s feed. Visitors find them in your Journal tab. Your draft is saved as you type.</p></main>';
		j.error = '';
		bindCommon();
		var t = $( '#a-jtext' ), c = $( '[data-jcount]' ), save = $( '[data-save]' );
		var grow = function () { t.style.height = 'auto'; t.style.height = Math.max( t.scrollHeight, window.innerHeight * 0.4 ) + 'px'; };
		t.addEventListener( 'input', function () {
			j.text = t.value; saveJournalDraft(); c.textContent = ( j.text.trim() ? 'Draft saved · ' : '' ) + j.text.length + ' / ' + max; save.disabled = ! j.text.trim() || j.uploading > 0; grow();
		} );
		grow();
		if ( j.uploading ) { save.disabled = true; }
		var jp = $( '[data-jphoto]' );
		jp.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( jp.files || [] ).slice( 0, 10 ), at = t.selectionStart != null ? t.selectionStart : j.text.length;
			if ( ! files.length ) { return; }
			j.uploading += files.length; j.text = t.value; viewJournal();
			files.reduce( function ( chain, f ) {
				return chain.then( function () {
					return resized( f ).then( function ( blob ) {
						var fd = new FormData(); fd.append( 'photo', blob, 'journal.jpg' );
						return send( 'POST', 'journal/photos', fd );
					} ).then( function ( r ) {
						var n = j.photos.reduce( function ( m, p ) { return Math.max( m, p.n ); }, 0 ) + 1;
						j.photos.push( { n: n, id: r.id, url: r.url, caption: '', alt: '' } );
						var before = j.text.slice( 0, at ).replace( /[ \t]+$/, '' ), after = j.text.slice( at ).replace( /^[ \t]+/, '' );
						var ins = ( before && ! /\n$/.test( before ) ? '\n' : '' ) + '[Photo ' + n + ']' + ( after && ! /^\n/.test( after ) ? '\n' : '' ) + ( after ? '' : '\n' );
						j.text = before + ins + after; at = ( before + ins ).length;
					}, function ( e ) { j.error = ( e && e.message ) || 'A photo couldn\'t be added. Try again.'; } ).then( function () { j.uploading--; saveJournalDraft(); viewJournal(); } );
				} );
			}, Promise.resolve() );
		} );
		$$( '[data-jcap]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { var p = j.photos.filter( function ( x ) { return x.n === +inp.dataset.jcap; } )[ 0 ]; if ( p ) { p.caption = inp.value; saveJournalDraft(); } } ); } );
		$$( '[data-jalt]' ).forEach( function ( inp ) { inp.addEventListener( 'input', function () { var p = j.photos.filter( function ( x ) { return x.n === +inp.dataset.jalt; } )[ 0 ]; if ( p ) { p.alt = inp.value; saveJournalDraft(); } } ); } );
		$$( '[data-jremove]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var n = +b.dataset.jremove;
				j.photos = j.photos.filter( function ( x ) { return x.n !== n; } );
				j.text = j.text.replace( new RegExp( '^[ \\t]*\\[Photo ' + n + '\\][ \\t]*\\n?', 'mi' ), '' );
				saveJournalDraft(); viewJournal();
			} );
		} );
		$$( '[data-tool]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { j.tool = j.tool === b.dataset.tool ? '' : b.dataset.tool; viewJournal(); var f = $( '#a-jlink' ); if ( f ) { f.focus(); } } ); } );
		var add = $( '[data-jadd]' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				var v = $( '#a-jlink' ).value.trim();
				if ( v && ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				if ( v ) { j.text = ( j.text ? j.text.replace( /\s+$/, '' ) + '\n\n' : '' ) + v; saveJournalDraft(); }
				j.tool = ''; viewJournal();
			} );
		}
		$$( '[data-vis]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { j.visibility = b.dataset.vis; store.set( 'stubli-journal-vis', j.visibility ); viewJournal(); } ); } );
		save.addEventListener( 'click', function () {
			if ( ! j.text.trim() || j.busy ) { return; }
			j.busy = true; save.disabled = true; save.textContent = 'Saving…';
			send( 'POST', 'journal', { text: j.text.trim(), visibility: j.visibility, photos: j.photos.map( function ( p ) { return { n: p.n, id: p.id, caption: p.caption.trim(), alt: p.alt.trim() }; } ) } ).then( function ( res ) {
				var priv = j.visibility === 'only_me';
				j.text = ''; j.photos = []; j.busy = false; store.del( 'stubli-journal-draft' ); store.del( 'stubli-journal-photos' );
				doneView( 'Saved', priv ? 'Only you can see this entry.' : 'Your entry is in your Journal tab.', B.journal, 'See your journal', '', '#journal', 'New entry' );
			}, function ( err ) {
				j.busy = false; j.error = ( err && err.message ) || 'Something went wrong. Your entry is still here.'; viewJournal();
			} );
		} );
	}



	/* ── Send feedback ── */
	var fb = { kind: 'idea', text: '', address: true, shot: null, busy: false, sent: false, error: '' };
	var FB = {
		idea: { label: 'An idea', q: 'What would make Stubli better?', ph: 'I wish I could…' },
		broken: { label: 'Something\'s not working', q: 'What happened?', ph: 'I tried to … and then …' },
		love: { label: 'Kind words', q: 'What do you like?', ph: 'I love that…' }
	};
	function device() {
		var ua = navigator.userAgent, os = /iPhone/.test( ua ) ? 'iPhone' : /iPad/.test( ua ) ? 'iPad' : /Android/.test( ua ) ? 'Android' : /Mac OS X/.test( ua ) ? 'Mac' : /Windows/.test( ua ) ? 'Windows' : /Linux/.test( ua ) ? 'Linux' : 'Other';
		var br = /Edg\//.test( ua ) ? 'Edge' : /Firefox\//.test( ua ) ? 'Firefox' : /CriOS|Chrome\//.test( ua ) ? 'Chrome' : /Safari\//.test( ua ) ? 'Safari' : 'Browser';
		return os + ' · ' + br + ( isStandalone() ? ' · home-screen app' : '' );
	}
	function viewFeedback() {
		var host = String( B.home || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		if ( fb.sent ) {
			root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="Back">' + icon( 'back', 22 ) + '</a><h1>Send feedback</h1><span></span></header><main class="a-compose" id="main"><div class="a-done"><span class="a-tick">' + icon( 'check', 28 ) + '</span>' +
				'<h2 tabindex="-1">Thank you' + ( B.name ? ', ' + esc( B.name ) : '' ) + '</h2><p class="muted">Your note is with the Stubli team.' + ( fb.address ? ' If you asked a question, Celestine will reply.' : '' ) + '</p>' +
				'<div class="a-actions"><button type="button" class="a-btn quiet" data-again>Write another</button><a class="a-btn quiet" href="#home">Done</a></div></div></main>';
			$( '.a-done h2' ).focus();
			$( '[data-again]' ).addEventListener( 'click', function () { fb.sent = false; fb.text = ''; fb.shot = null; viewFeedback(); } );
			return;
		}
		var k = FB[ fb.kind ];
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="Back">' + icon( 'back', 22 ) + '</a><h1>Send feedback</h1><span></span></header>' +
			'<main class="a-compose" id="main"><p>This goes to the people who make Stubli, not to anyone you follow. Every message is read.</p>' +
			'<div class="a-chips" role="radiogroup" aria-label="What is it about?">' + Object.keys( FB ).map( function ( key ) {
				var on = fb.kind === key;
				return '<button type="button" class="a-chip" role="radio" aria-checked="' + on + '" data-kind="' + key + '">' + ( on ? icon( 'check', 16 ) : '' ) + esc( FB[ key ].label ) + '</button>';
			} ).join( '' ) + '</div>' +
			'<label class="a-label" for="a-fb">' + esc( k.q ) + '</label><textarea id="a-fb" class="a-textarea" style="min-height:160px" maxlength="4000" placeholder="' + esc( k.ph ) + '">' + esc( fb.text ) + '</textarea>' +
			'<label class="a-check" style="margin-top:14px"><span>Include my address (' + esc( host ) + ') so you can reply</span><input type="checkbox" data-addr' + ( fb.address ? ' checked' : '' ) + '></label>' +
			'<div style="margin-top:14px">' + ( fb.shot ? '<div class="a-alt"><img src="' + fb.shot.url + '" alt="Your screenshot"><span>Screenshot added · <button type="button" class="a-text-btn" data-noshot style="min-height:auto;padding:0;text-decoration:underline">Remove</button></span></div>' :
				'<label class="a-chip">' + icon( 'photo', 16 ) + 'Add a screenshot<input class="a-file" type="file" accept="image/png,image/jpeg,image/webp" data-shot></label>' ) + '</div>' +
			'<p class="a-help" style="margin-top:14px">We also send your Stubli version and phone type, to help fix problems. Nothing else.</p>' +
			'<div data-msg aria-live="polite">' + ( fb.error ? '<div class="a-notice" role="alert"><p>' + esc( fb.error ) + '</p></div>' : '' ) + '</div>' +
			'<button type="button" class="a-btn wide" style="margin-top:14px" data-send' + ( fb.busy ? ' disabled' : '' ) + '>' + ( fb.busy ? 'Sending…' : 'Send feedback' ) + '</button></main>';
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
				resized( f ).then( function ( b ) { fb.shot = { blob: b, url: URL.createObjectURL( b ) }; viewFeedback(); }, function () { fb.error = 'That image couldn\'t be opened. Try a PNG or JPEG.'; viewFeedback(); } );
			} );
		}
		var ns = $( '[data-noshot]' );
		if ( ns ) { ns.addEventListener( 'click', function () { URL.revokeObjectURL( fb.shot.url ); fb.shot = null; viewFeedback(); } ); }
		$( '[data-send]' ).addEventListener( 'click', function () {
			if ( ! fb.text.trim() ) { msg( '<div class="a-notice calm" role="alert"><p>Write a few words first.</p></div>' ); t.focus(); return; }
			fb.busy = true; viewFeedback();
			var fd = new FormData();
			fd.append( 'kind', fb.kind ); fd.append( 'message', fb.text.trim() ); fd.append( 'include_address', fb.address ? '1' : '0' ); fd.append( 'device', device() );
			if ( fb.shot ) { fd.append( 'screenshot', fb.shot.blob, 'screenshot.jpg' ); }
			send( 'POST', 'feedback/send', fd ).then( function () {
				fb.busy = false; fb.sent = true; if ( fb.shot ) { URL.revokeObjectURL( fb.shot.url ); } viewFeedback();
			}, function ( e ) { fb.busy = false; fb.error = ( e && e.message ) || 'The note didn\'t go through. Try again in a moment.'; viewFeedback(); } );
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
		imp = { state: 'scanning', posts: [], videos: 0, missing: 0, collection: 'From Instagram', done: 0, failed: [], stop: false };
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
			imp.error = e.message === 'html' ? 'This download is the HTML version. Ask Instagram for a new download and choose JSON as the format.' :
				e.message === 'notzip' ? 'That file isn\'t a zip file. Choose the file Instagram sent you, without unpacking it.' :
				e.message === 'noposts' ? 'There are no posts in this download. When you request it, tick Posts (or Content).' :
				'This download couldn\'t be read here. Try it on a computer with a current browser.';
			viewImport();
		} );
	}
	function when( ts ) { return ts ? new Date( ts * 1000 ).toLocaleDateString( undefined, { month: 'short', year: 'numeric' } ) : ''; }
	function viewImport() {
		var s = imp, body = '';
		if ( ! s || s.state === 'error' ) {
			body = '<p class="muted">Instagram lets you download everything you\'ve posted. Stubli reads that download on this device and brings your photos over, with their original dates and captions.</p>' +
				'<h2 class="a-label">1. Ask Instagram for your download</h2><ol class="a-steps"><li>In Instagram, open your profile, the menu, then <strong>Accounts Centre</strong>.</li><li>Choose <strong>Your information and permissions</strong> → <strong>Download your information</strong>.</li><li>Pick <strong>Some of your information</strong> → <strong>Posts</strong>, then <strong>Download to device</strong>.</li><li>Set <strong>Date range: All time</strong>, <strong>Format: JSON</strong> and <strong>Media quality: High</strong>.</li></ol>' +
				'<p class="a-help">Instagram emails you when it\'s ready, which can take a few hours. Big accounts may get several zip files: choose them all.</p>' +
				'<h2 class="a-label">2. Choose the zip file</h2><label class="a-btn">' + icon( 'plus', 18 ) + 'Choose zip files<input class="a-file" type="file" accept=".zip,application/zip" multiple data-zip></label>' +
				( s && s.error ? '<div class="a-notice" role="alert"><p>' + esc( s.error ) + '</p></div>' : '' );
		} else if ( s.state === 'scanning' ) {
			body = '<div class="a-notice calm" aria-live="polite"><p>Reading your download…</p></div>';
		} else if ( s.state === 'empty' ) {
			body = '<div class="a-notice calm"><p>No photo or video posts found in this download.</p></div>';
		} else if ( s.state === 'ready' ) {
			var on = s.posts.filter( function ( p ) { return p.on; } ).length;
			body = '<p>Found <strong>' + s.posts.length + ' posts</strong> from ' + esc( when( s.posts[ 0 ].ts ) ) + ' to ' + esc( when( s.posts[ s.posts.length - 1 ].ts ) ) + '.' +
				( s.videos ? ' Videos up to a minute come along; longer ones stay behind.' : '' ) + '</p>' +
				'<label class="a-label" for="a-impcol">Put them in this collection</label><input id="a-impcol" class="a-field" maxlength="40" value="' + esc( s.collection ) + '">' +
				'<div class="a-row" style="margin-top:18px"><h2 class="a-label">Choose posts</h2><button type="button" class="a-text-btn" data-all>' + ( on === s.posts.length ? 'Select none' : 'Select all' ) + '</button></div>' +
				'<div class="a-thumbs a-imp-grid">' + s.posts.map( function ( p, i ) {
					return '<button type="button" class="a-thumb a-imp' + ( p.on ? ' is-on' : '' ) + '" data-tog="' + i + '" aria-pressed="' + p.on + '" aria-label="' + esc( when( p.ts ) + ( p.caption ? ': ' + p.caption.slice( 0, 60 ) : '' ) ) + '"><img alt="" data-src="' + i + '">' + ( p.photos.length > 1 ? '<span class="a-order">' + p.photos.length + '</span>' : '' ) + ( ! p.cover ? '<span class="a-vid-dur" aria-hidden="true">' + icon( 'play', 12 ) + 'Video</span>' : '' ) + '<span class="a-imp-check" aria-hidden="true">' + icon( 'check', 16 ) + '</span></button>';
				} ).join( '' ) + '</div>' +
				'<p class="a-help" style="margin-top:14px">Captions and dates come along. Comments and likes stay on Instagram. Location details are removed. Keep this screen open while it works.</p>' +
				'<button type="button" class="a-btn wide" style="margin-top:12px" data-go-import' + ( on ? '' : ' disabled' ) + '>Import ' + on + ' post' + ( on === 1 ? '' : 's' ) + '</button>';
		} else if ( s.state === 'running' ) {
			var total = s.queue.length;
			body = '<div class="a-notice calm" aria-live="polite"><p>Importing ' + Math.min( s.done + 1, total ) + ' of ' + total + '…</p><div class="a-progress"><span style="width:' + Math.round( 100 * s.done / total ) + '%"></span></div></div>' +
				'<p class="a-help">You can keep using your phone, but leave this screen open. If it stops, start again: posts already imported are skipped.</p><button type="button" class="a-btn quiet" data-stop style="margin-top:8px">Stop</button>';
		} else if ( s.state === 'done' ) {
			var ok = s.ok || 0;
			body = '<div class="a-done" style="padding-top:12px"><span class="a-tick">' + icon( 'check', 28 ) + '</span><h2>' + ( s.stop ? 'Stopped' : 'Done' ) + '</h2><p class="muted">' + ok + ' post' + ( ok === 1 ? '' : 's' ) + ' imported' + ( s.collection && ok ? ' into ' + esc( s.collection ) : '' ) + '.' + ( s.dup ? ' ' + s.dup + ' ' + ( s.dup === 1 ? 'was' : 'were' ) + ' already on your page, so nothing was doubled.' : '' ) +
				( s.failed.length ? ' ' + s.failed.length + ' couldn\'t be imported; try again to retry them.' : '' ) + ( s.long ? ' ' + ( s.long === 1 ? 'One video was' : s.long + ' videos were' ) + ' longer than a minute and stayed behind.' : '' ) + ( s.bad ? ' ' + ( s.bad === 1 ? 'One video' : s.bad + ' videos' ) + ' couldn\'t be read and stayed behind.' : '' ) + ( s.stop && s.queue.length ? ' ' + s.queue.length + ' still to go.' : '' ) + '</p><div class="a-actions"><a class="a-btn" href="' + esc( B.home ) + '">See your page</a>' + ( s.failed.length || s.stop ? '<button type="button" class="a-btn quiet" data-retry>Try again</button>' : '' ) + '</div></div>';
		}
		root.innerHTML = '<header class="a-head"><a class="a-text-btn" href="' + cameFrom + '" aria-label="Back">' + icon( 'back', 22 ) + '</a><h1>From Instagram</h1><span></span></header><main class="a-compose" id="main">' + body + '</main>';
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
		if ( st ) { st.addEventListener( 'click', function () { s.stop = true; st.disabled = true; st.textContent = 'Stopping after this post…'; } ); }
		var rt = $( '[data-retry]' );
		if ( rt ) { rt.addEventListener( 'click', function () { runImport( s.queue ); } ); }
		lazyThumbs();
	}
	function updateCount() {
		var on = imp.posts.filter( function ( p ) { return p.on; } ).length, go = $( '[data-go-import]' );
		go.disabled = ! on; go.textContent = 'Import ' + on + ' post' + ( on === 1 ? '' : 's' );
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
		s.queue = queue; s.done = 0; s.ok = 0; s.dup = 0; s.long = 0; s.bad = 0; s.failed = []; s.stop = false; s.state = 'running';
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
				if ( t ) { t.textContent = 'Importing ' + Math.min( s.done + 1, queue.length ) + ' of ' + queue.length + '…'; }
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
					c.dataset.sure = '1'; c.textContent = 'Discard?'; c.setAttribute( 'aria-label', 'Discard this post. Press again to confirm.' );
					setTimeout( function () { if ( c.isConnected ) { delete c.dataset.sure; c.textContent = 'Cancel'; c.removeAttribute( 'aria-label' ); } }, 3000 );
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
