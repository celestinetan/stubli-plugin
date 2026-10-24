/* Stubli theme: reading mode, carousel, content notes, click-to-play video,
 * and the "from your own Stubli" address form. No dependencies. */
( function () {
	'use strict';
	var T = window.stubliText || {};
	var root = document.documentElement;
	var store = {
		get: function ( k ) { try { return localStorage.getItem( k ); } catch ( e ) { return null; } },
		set: function ( k, v ) { try { localStorage.setItem( k, v ); } catch ( e ) {} }
	};

	/* Coming back with the browser's Back button, Safari can show the page as it
	   was before. When you're signed in, reload it so changes from the app show. */
	window.addEventListener( 'pageshow', function ( e ) { if ( e.persisted && document.body && document.body.classList.contains( 'logged-in' ) ) { location.reload(); } } );

	/* Reading mode: Auto → Light → Paper → Dark. */
	var order = [ 'auto', 'light', 'paper', 'dark' ];
	var dark = window.matchMedia ? matchMedia( '(prefers-color-scheme: dark)' ) : null;
	function apply( pref ) {
		var mode = pref === 'auto' ? ( dark && dark.matches ? 'dark' : 'light' ) : pref;
		root.setAttribute( 'data-mode', mode );
		root.setAttribute( 'data-mode-pref', pref );
		document.querySelectorAll( '[data-st-mode]' ).forEach( function ( b ) {
			var label = ( T.modes && T.modes[ pref ] ) || pref;
			b.querySelector( '.st-mode-label' ).textContent = label;
			b.setAttribute( 'aria-label', ( T.readingMode || 'Reading mode' ) + ': ' + label );
		} );
	}
	// The owner's default (set in the head) applies until a visitor picks their own.
	var pref = store.get( 'stubli-mode' ) || root.getAttribute( 'data-mode-pref' ) || 'auto';
	if ( order.indexOf( pref ) < 0 ) { pref = 'auto'; }
	apply( pref );
	if ( dark && dark.addEventListener ) {
		dark.addEventListener( 'change', function () { if ( pref === 'auto' ) { apply( pref ); } } );
	}
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-st-mode]' );
		if ( ! b ) { return; }
		pref = order[ ( order.indexOf( pref ) + 1 ) % order.length ];
		store.set( 'stubli-mode', pref );
		apply( pref );
	} );

	/* Videos: tap to play, with sound. Nothing downloads before the tap. */
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-st-video]' );
		if ( ! b ) { return; }
		var v = document.createElement( 'video' ), img = b.querySelector( 'img' );
		v.controls = true; v.playsInline = true; v.setAttribute( 'playsinline', '' ); v.preload = 'auto';
		if ( img ) { v.poster = img.currentSrc || img.src; if ( img.naturalWidth ) { v.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight; } }
		if ( b.getAttribute( 'data-label' ) ) { v.setAttribute( 'aria-label', b.getAttribute( 'data-label' ) ); }
		var src = document.createElement( 'source' ); src.src = b.getAttribute( 'data-st-video' ); src.type = b.getAttribute( 'data-type' ) || 'video/mp4';
		v.appendChild( src ); v.className = 'st-video-file';
		b.parentNode.replaceChild( v, b );
		v.focus();
		var p = v.play(); if ( p && p.catch ) { p.catch( function () {} ); }
	} );

	/* Get started guide: one step at a time; the address keeps the step, so Back works. */
	var gs = document.querySelector( '[data-st-gs]' );
	if ( gs ) {
		var steps = gs.querySelectorAll( '[data-gs-step]' ), prog = gs.querySelector( '[data-gs-progress]' );
		var names = { 1: T.gs1 || 'A home for your site', 2: T.gs2 || 'Install Stubli', 3: T.gs3 || 'Stubli sets itself up', 4: T.gs4 || 'Invite your people' };
		gs.classList.add( 'is-live' );
		var show = function ( focus ) {
			var id = ( location.hash || '' ).replace( '#gs-', '' ), cur = gs.querySelector( '[data-gs-step="' + id + '"]' ) || steps[ 0 ];
			steps.forEach( function ( s ) { s.hidden = s !== cur; } );
			var n = +cur.getAttribute( 'data-gs-n' );
			// A short checklist of the four steps, not a slideshow bar: people swiped
			// sideways on phones when it looked like stories.
			prog.hidden = false;
			prog.innerHTML = '';
			var list = document.createElement( 'ol' ); list.className = 'st-gs-list';
			[ 1, 2, 3, 4 ].forEach( function ( k ) {
				var li = document.createElement( 'li' );
				li.className = k < n ? 'is-done' : ( k === n ? 'is-now' : '' );
				if ( k === n ) { li.setAttribute( 'aria-current', 'step' ); }
				var mark = document.createElement( 'span' ); mark.className = 'st-gs-mark'; mark.setAttribute( 'aria-hidden', 'true' ); mark.textContent = k < n ? '✓' : String( k );
				li.appendChild( mark ); li.appendChild( document.createTextNode( names[ k ] ) );
				list.appendChild( li );
			} );
			prog.appendChild( list );
			if ( focus ) { var h = cur.querySelector( 'h2' ); h.setAttribute( 'tabindex', '-1' ); h.focus(); window.scrollTo( 0, Math.max( 0, gs.getBoundingClientRect().top + window.scrollY - 80 ) ); }
		};
		window.addEventListener( 'hashchange', function () { if ( /^#gs-/.test( location.hash ) || ! location.hash ) { show( true ); } } );
		// Coming from the welcome page's "Do you have a WordPress site?": skip that question.
		try { var have = new URLSearchParams( location.search ).get( 'have' ); if ( ! location.hash && ( 'yes' === have || 'no' === have ) ) { history.replaceState( null, '', '#gs-' + ( 'yes' === have ? 'check' : 'host' ) ); } } catch ( e ) {}
		show( false );
		// "Type your address" → open that page on the visitor's own WordPress.
		gs.querySelectorAll( '[data-gs-site]' ).forEach( function ( f ) {
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var err = f.querySelector( '.st-error' ), v = ( f.addr.value || '' ).trim();
				if ( ! /^https?:\/\//i.test( v ) ) { v = 'https://' + v; }
				var u;
				try { u = new URL( v ); } catch ( x ) { u = null; }
				if ( ! u || u.hostname.indexOf( '.' ) < 1 ) { err.textContent = T.badAddr || 'That doesn\'t look like a web address. Try something like yourname.com.'; err.hidden = false; f.addr.focus(); return; }
				err.hidden = true;
				var base = u.origin + u.pathname.replace( /\/(wp-admin|wp-login\.php).*$/, '' ).replace( /\/+$/, '' );
				window.open( base + '/' + f.getAttribute( 'data-path' ), '_blank', 'noopener' );
			} );
		} );
	}

	/* Carousel: "1 of N", dots, arrow keys. */
	document.querySelectorAll( '[data-st-carousel]' ).forEach( function ( c ) {
		var track = c.querySelector( '.st-carousel-track' );
		var slides = track.children;
		var count = c.querySelector( '.st-carousel-count' );
		var dots = c.querySelectorAll( '.st-dots span' );
		if ( slides.length < 2 ) { return; }
		var current = -1;
		function update() {
			var i = Math.round( track.scrollLeft / Math.max( 1, track.clientWidth ) );
			i = Math.max( 0, Math.min( slides.length - 1, i ) );
			if ( i === current ) { return; }
			current = i;
			if ( count ) { count.textContent = ( T.of || '%1$d of %2$d' ).replace( '%1$d', i + 1 ).replace( '%2$d', slides.length ); }
			dots.forEach( function ( d, n ) { d.classList.toggle( 'is-on', n === i ); } );
		}
		track.addEventListener( 'scroll', function () { window.requestAnimationFrame( update ); }, { passive: true } );
		track.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' ) { return; }
			e.preventDefault();
			var next = Math.max( 0, Math.min( slides.length - 1, current + ( e.key === 'ArrowRight' ? 1 : -1 ) ) );
			track.scrollTo( { left: next * track.clientWidth, behavior: matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
		} );
		update();
	} );

	/* Full screen: tap or click a photo to see it as large as the screen allows, at
	   full size. Swipe or arrow keys for the next photo; close with ✕, Esc, Back or
	   a swipe down. Pinch to zoom is the browser's own. */
	var lb = null;
	function lbClose( fromHistory ) {
		if ( ! lb ) { return; }
		var o = lb; lb = null;
		document.removeEventListener( 'keydown', o.onKey, true );
		o.el.remove();
		document.documentElement.classList.remove( 'st-lb-open' );
		if ( ! fromHistory && history.state && history.state.stLb ) { history.back(); }
		if ( o.from && o.from.focus ) { o.from.focus( { preventScroll: true } ); }
	}
	window.addEventListener( 'popstate', function () { if ( lb ) { lbClose( true ); } } );
	function lbOpen( btn ) {
		var c = btn.closest( '[data-st-carousel]' );
		if ( c && c.classList.contains( 'has-note' ) && ! c.classList.contains( 'is-revealed' ) ) { return; }
		var all = Array.prototype.slice.call( ( c || document ).querySelectorAll( '[data-st-zoom]' ) );
		var start = Math.max( 0, all.indexOf( btn ) ), n = all.length;
		var el = document.createElement( 'div' );
		el.className = 'st-lb'; el.setAttribute( 'role', 'dialog' ); el.setAttribute( 'aria-modal', 'true' );
		var track = document.createElement( 'div' ); track.className = 'st-lb-track';
		all.forEach( function ( b, i ) {
			var f = document.createElement( 'figure' ), img = document.createElement( 'img' ), small = b.querySelector( 'img' );
			img.alt = b.getAttribute( 'data-alt' ) || '';
			img.decoding = 'async';
			// Show the picture already on the page at once, then swap in the full-size file.
			if ( small ) { img.src = small.currentSrc || small.src; }
			var full = new Image(); full.onload = function () { img.src = full.src; }; if ( Math.abs( i - start ) <= 1 ) { full.src = b.getAttribute( 'data-st-zoom' ); } else { img.setAttribute( 'data-full', b.getAttribute( 'data-st-zoom' ) ); img._full = full; }
			f.appendChild( img ); track.appendChild( f );
		} );
		var mk = function ( cls, label, svg ) { var b = document.createElement( 'button' ); b.type = 'button'; b.className = cls; b.setAttribute( 'aria-label', label ); b.innerHTML = svg; return b; };
		var ico = function ( d ) { return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>'; };
		var close = mk( 'st-lb-close', T.close || 'Close', ico( 'M6 6l12 12M18 6L6 18' ) );
		var count = document.createElement( 'p' ); count.className = 'st-lb-count'; count.setAttribute( 'aria-live', 'polite' );
		el.appendChild( track ); el.appendChild( close );
		var prev = null, next = null;
		if ( n > 1 ) {
			prev = mk( 'st-lb-nav st-lb-prev', T.prevPhoto || 'Previous photo', ico( 'M15 5l-7 7 7 7' ) );
			next = mk( 'st-lb-nav st-lb-next', T.nextPhoto || 'Next photo', ico( 'M9 5l7 7-7 7' ) );
			el.appendChild( prev ); el.appendChild( next ); el.appendChild( count );
		}
		document.body.appendChild( el );
		document.documentElement.classList.add( 'st-lb-open' );
		var reduce = matchMedia( '(prefers-reduced-motion: reduce)' ).matches, cur = -1;
		var go = function ( i, smooth ) { i = Math.max( 0, Math.min( n - 1, i ) ); track.scrollTo( { left: i * track.clientWidth, behavior: smooth && ! reduce ? 'smooth' : 'auto' } ); };
		var update = function () {
			var i = Math.max( 0, Math.min( n - 1, Math.round( track.scrollLeft / Math.max( 1, track.clientWidth ) ) ) );
			if ( i === cur ) { return; }
			cur = i;
			el.setAttribute( 'aria-label', ( T.photoOf || 'Photo %1$d of %2$d' ).replace( '%1$d', i + 1 ).replace( '%2$d', n ) );
			if ( n > 1 ) { count.textContent = ( T.of || '%1$d of %2$d' ).replace( '%1$d', i + 1 ).replace( '%2$d', n ); prev.disabled = i === 0; next.disabled = i === n - 1; }
			// Load the full-size files next to this one.
			[ i - 1, i, i + 1 ].forEach( function ( k ) { var im = track.children[ k ] && track.children[ k ].firstChild; if ( im && im._full && ! im._full.src ) { im._full.src = im.getAttribute( 'data-full' ); } } );
		};
		track.addEventListener( 'scroll', function () { window.requestAnimationFrame( update ); }, { passive: true } );
		go( start, false ); update();
		close.addEventListener( 'click', function () { lbClose( false ); } );
		if ( prev ) { prev.addEventListener( 'click', function () { go( cur - 1, true ); } ); next.addEventListener( 'click', function () { go( cur + 1, true ); } ); }
		// A tap on the dark space around the photo closes, like most viewers.
		el.addEventListener( 'click', function ( e ) { if ( e.target === el || e.target.tagName === 'FIGURE' ) { lbClose( false ); } } );
		// Swipe down to close.
		var y0 = null, x0 = 0;
		track.addEventListener( 'touchstart', function ( e ) { if ( e.touches.length === 1 && ( ! window.visualViewport || window.visualViewport.scale <= 1.01 ) ) { y0 = e.touches[ 0 ].clientY; x0 = e.touches[ 0 ].clientX; } else { y0 = null; } }, { passive: true } );
		track.addEventListener( 'touchend', function ( e ) { if ( y0 === null ) { return; } var t = e.changedTouches[ 0 ], dy = t.clientY - y0, dx = Math.abs( t.clientX - x0 ); y0 = null; if ( dy > 90 && dx < dy / 2 ) { lbClose( false ); } }, { passive: true } );
		var onKey = function ( e ) {
			if ( e.key === 'Escape' ) { e.preventDefault(); lbClose( false ); return; }
			if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) { e.preventDefault(); go( cur + ( e.key === 'ArrowRight' ? 1 : -1 ), true ); return; }
			if ( e.key === 'Tab' ) {
				// Keep the keyboard inside the viewer.
				var f = Array.prototype.filter.call( el.querySelectorAll( 'button' ), function ( b ) { return ! b.disabled; } );
				var a = f.indexOf( document.activeElement );
				e.preventDefault();
				f[ ( a + ( e.shiftKey ? -1 : 1 ) + f.length ) % f.length ].focus();
			}
		};
		document.addEventListener( 'keydown', onKey, true );
		lb = { el: el, from: btn, onKey: onKey };
		try { history.pushState( { stLb: 1 }, '' ); } catch ( x ) {}
		close.focus( { preventScroll: true } );
		var onResize = function () { if ( lb && lb.el === el ) { go( cur, false ); } else { window.removeEventListener( 'resize', onResize ); } };
		window.addEventListener( 'resize', onResize );
	}
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-st-zoom]' );
		if ( ! b ) { return; }
		e.preventDefault();
		lbOpen( b );
	} );

	/* Content note: blurred until the visitor chooses to look. */
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-st-reveal]' );
		if ( ! b ) { return; }
		var c = b.closest( '[data-st-carousel]' );
		c.classList.add( 'is-revealed' );
		var track = c.querySelector( '.st-carousel-track' );
		if ( track ) { track.focus(); }
	} );

	/* Video: nothing loads from the video site until play is pressed. */
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.st-video-play' );
		if ( ! b ) { return; }
		var box = b.closest( '[data-st-video]' );
		var f = document.createElement( 'iframe' );
		f.src = box.getAttribute( 'data-st-video' );
		f.allow = 'autoplay; fullscreen; picture-in-picture';
		f.allowFullscreen = true;
		f.title = b.getAttribute( 'aria-label' ) || 'Video';
		box.replaceChildren( f );
	} );

	/* From your own Stubli: remember the address and go to that site. */
	document.querySelectorAll( '[data-st-remote]' ).forEach( function ( form ) {
		var input = form.querySelector( 'input' );
		var err = form.querySelector( '.st-error' );
		var saved = store.get( 'stubli-home' );
		if ( saved && ! input.value ) { input.value = saved; }
		input.addEventListener( 'input', function () { err.hidden = true; input.removeAttribute( 'aria-invalid' ); } );
		input.setAttribute( 'type', 'text' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var raw = input.value.trim();
			if ( ! /^https?:\/\//i.test( raw ) ) { raw = 'https://' + raw; }
			var url;
			try { url = new URL( raw ); } catch ( x ) { url = null; }
			if ( ! url || url.hostname.indexOf( '.' ) < 1 && url.hostname !== 'localhost' && ! /^\d+\.\d+\.\d+\.\d+$/.test( url.hostname ) ) {
				err.textContent = T.badAddress || 'That doesn\'t look like a website address.';
				err.hidden = false;
				input.setAttribute( 'aria-invalid', 'true' );
				input.focus();
				return;
			}
			var home = url.origin + url.pathname.replace( /\/+$/, '' );
			store.set( 'stubli-home', url.host + url.pathname.replace( /\/+$/, '' ) );
			var dest = home + '/app/?act=1&do=' + encodeURIComponent( form.getAttribute( 'data-do' ) ) + '&url=' + encodeURIComponent( form.getAttribute( 'data-target' ) );
			if ( form.getAttribute( 'data-invite' ) ) { dest += '&invite=' + encodeURIComponent( form.getAttribute( 'data-invite' ) ); }
			if ( form.getAttribute( 'data-p' ) && form.getAttribute( 'data-p' ) !== '-1' ) { dest += '&p=' + encodeURIComponent( form.getAttribute( 'data-p' ) ); }
			window.location.href = dest;
		} );
	} );
	/* Feedback form on stubli.app. */
	document.querySelectorAll( '[data-st-feedback]' ).forEach( function ( form ) {
		var q = form.querySelector( '[data-st-q]' ), ta = form.querySelector( 'textarea' ), err = form.querySelector( '.st-error' ), btn = form.querySelector( 'button[type="submit"]' );
		form.querySelectorAll( 'input[name="kind"]' ).forEach( function ( r ) {
			r.addEventListener( 'change', function () { q.textContent = r.getAttribute( 'data-q' ); ta.placeholder = r.getAttribute( 'data-ph' ); } );
		} );
		var ua = navigator.userAgent;
		var device = ( /iPhone/.test( ua ) ? 'iPhone' : /iPad/.test( ua ) ? 'iPad' : /Android/.test( ua ) ? 'Android' : /Mac OS X/.test( ua ) ? 'Mac' : /Windows/.test( ua ) ? 'Windows' : /Linux/.test( ua ) ? 'Linux' : 'Other' ) + ' · ' +
			( /Edg\//.test( ua ) ? 'Edge' : /Firefox\//.test( ua ) ? 'Firefox' : /CriOS|Chrome\//.test( ua ) ? 'Chrome' : /Safari\//.test( ua ) ? 'Safari' : 'Browser' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			err.hidden = true;
			if ( ! ta.value.trim() ) { err.textContent = 'Write a few words first.'; err.hidden = false; ta.focus(); return; }
			var fd = new FormData( form );
			fd.set( 'device', device ); fd.set( 'version', form.getAttribute( 'data-version' ) || '' ); fd.set( 'source', 'web' );
			var shot = form.querySelector( 'input[type="file"]' );
			if ( shot && ( ! shot.files.length || shot.files[ 0 ].size > 8 * 1024 * 1024 ) ) { fd.delete( 'screenshot' ); }
			btn.disabled = true; btn.textContent = 'Sending…';
			fetch( form.getAttribute( 'data-url' ), { method: 'POST', body: fd, credentials: 'omit' } ).then( function ( r ) {
				return r.json().then( function ( d ) { if ( ! r.ok ) { throw new Error( ( d && d.message ) || 'The note didn\'t go through.' ); } } );
			} ).then( function () {
				form.hidden = true;
				btn.disabled = false; btn.textContent = 'Send feedback';
				var done = document.querySelector( '[data-st-feedback-done]' ); done.hidden = false; done.focus();
			} ).catch( function ( x ) {
				err.textContent = ( x && x.message ) || 'The note didn\'t go through. Try again in a moment.'; err.hidden = false;
				btn.disabled = false; btn.textContent = 'Send feedback';
			} );
		} );
	} );
}() );

/* Prompt cards behind the profile card: arrows, dots and swipe. */
( function () {
	document.querySelectorAll( '[data-st-stack]' ).forEach( function ( stack ) {
		var cards = Array.prototype.slice.call( stack.querySelectorAll( '[data-st-card]' ) );
		var nav = stack.parentNode.querySelector( '[data-st-stack-nav]' );
		if ( cards.length < 2 || ! nav ) { return; }
		var dots = nav.querySelectorAll( '.st-stack-dots span' ), status = nav.querySelector( '[data-st-stack-status]' ), i = 0;
		stack.classList.add( 'is-live' ); nav.hidden = false;
		function show( n, announce ) {
			i = ( n + cards.length ) % cards.length;
			cards.forEach( function ( c, k ) {
				var pos = ( k - i + cards.length ) % cards.length;
				c.setAttribute( 'data-pos', pos > 2 ? 'hide' : String( pos ) );
				if ( pos === 0 ) { c.removeAttribute( 'aria-hidden' ); c.inert = false; } else { c.setAttribute( 'aria-hidden', 'true' ); c.inert = true; }
			} );
			Array.prototype.forEach.call( dots, function ( d, k ) { d.classList.toggle( 'is-on', k === i ); } );
			if ( announce && status ) { status.textContent = ( i + 1 ) + ' / ' + cards.length + ': ' + ( cards[ i ].getAttribute( 'aria-label' ) || '' ); }
		}
		nav.querySelector( '[data-st-prev]' ).addEventListener( 'click', function () { show( i - 1, true ); } );
		nav.querySelector( '[data-st-next]' ).addEventListener( 'click', function () { show( i + 1, true ); } );
		var x0 = null, y0 = null;
		stack.addEventListener( 'touchstart', function ( e ) { x0 = e.touches[ 0 ].clientX; y0 = e.touches[ 0 ].clientY; }, { passive: true } );
		stack.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) { return; }
			var dx = e.changedTouches[ 0 ].clientX - x0, dy = e.changedTouches[ 0 ].clientY - y0; x0 = null;
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) { show( dx < 0 ? i + 1 : i - 1, true ); }
		} );
		show( 0, false );
	} );
}() );


/* Reply to a prompt card: tabs, and the guest note form. */
( function () {
	var tabs = document.querySelector( '[data-st-note-tabs]' );
	if ( tabs ) {
		var btns = Array.prototype.slice.call( tabs.querySelectorAll( '[role=tab]' ) );
		var pick = function ( b, focus ) {
			btns.forEach( function ( x ) {
				var on = x === b;
				x.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				x.tabIndex = on ? 0 : -1;
				document.getElementById( x.getAttribute( 'aria-controls' ) ).hidden = ! on;
			} );
			if ( focus ) { b.focus(); }
		};
		btns.forEach( function ( b, k ) {
			b.addEventListener( 'click', function () { pick( b ); } );
			b.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) { e.preventDefault(); pick( btns[ ( k + ( e.key === 'ArrowRight' ? 1 : -1 ) + btns.length ) % btns.length ], true ); }
			} );
		} );
		pick( btns[ 0 ] );
	}
	var form = document.querySelector( '[data-st-note]' );
	if ( ! form ) { return; }
	var err = form.querySelector( '.st-error' ), btn = form.querySelector( 'button[type=submit]' );
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var get = function ( n ) { return ( form.elements[ n ] && form.elements[ n ].value || '' ).trim(); };
		var missing = ! get( 'text' ) ? 'text' : ! get( 'name' ) ? 'name' : ! get( 'contact' ) ? 'contact' : '';
		if ( missing ) {
			err.textContent = { text: 'Write a few words first.', name: 'Add your name, so they know who it\'s from.', contact: 'Add an email, phone number or other way to reach you, so they can write back.' }[ missing ];
			err.hidden = false; form.elements[ missing ].focus(); return;
		}
		err.hidden = true; btn.disabled = true; btn.textContent = 'Sending…';
		fetch( form.getAttribute( 'data-url' ), {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { text: get( 'text' ), name: get( 'name' ), contact: get( 'contact' ), website: get( 'website' ), prompt: form.getAttribute( 'data-prompt' ) } )
		} ).then( function ( r ) { return r.json().then( function ( d ) { return { ok: r.ok, d: d }; } ); } ).then( function ( res ) {
			if ( ! res.ok ) { throw new Error( ( res.d && res.d.message ) || '' ); }
			form.hidden = true;
			var done = document.querySelector( '[data-st-note-done]' ); done.hidden = false; done.focus();
			if ( tabs ) { tabs.hidden = true; }
		} ).catch( function ( x ) {
			btn.disabled = false; btn.textContent = 'Send note';
			err.textContent = x.message || 'The note didn\'t go through. Check your connection and try again.'; err.hidden = false;
		} );
	} );
}() );


/* Guest book: the owner pins or unpins an entry right on the page. */
( function () {
	var list = document.querySelector( '[data-st-gb]' );
	if ( ! list ) { return; }
	list.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-st-gb-pin]' );
		if ( ! b || b.disabled ) { return; }
		var on = b.getAttribute( 'data-pinned' ) !== '1';
		b.disabled = true;
		fetch( list.getAttribute( 'data-api' ) + b.getAttribute( 'data-st-gb-pin' ) + '/pin', { method: on ? 'POST' : 'DELETE', credentials: 'same-origin', headers: { 'X-WP-Nonce': list.getAttribute( 'data-nonce' ) } } )
			.then( function ( r ) { if ( ! r.ok ) { throw new Error( 'no' ); } location.reload(); } )
			.catch( function () { b.disabled = false; } );
	} );
}() );



/* Latest, on wider screens: as many of the newest posts as fill the card
   beside the profile, whole rows only. */
( function () {
	var box = document.querySelector( '[data-st-latest]' );
	if ( ! box ) { return; }
	var fit = function () {
		var rows = box.querySelectorAll( '.st-latest-row' );
		Array.prototype.forEach.call( rows, function ( r ) { r.classList.remove( 'is-cut' ); } );
		if ( getComputedStyle( box ).position !== 'absolute' ) { return; }
		var h = box.clientHeight;
		Array.prototype.forEach.call( rows, function ( r ) { if ( r.offsetTop + r.offsetHeight > h + 1 ) { r.classList.add( 'is-cut' ); } } );
	};
	fit();
	window.addEventListener( 'resize', fit );
	window.addEventListener( 'load', fit );
	if ( window.ResizeObserver ) { new ResizeObserver( fit ).observe( box ); }
}() );
