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
		var names = { 1: T.gs1 || 'A home for your site', 2: T.gs2 || 'Install Stubli', 3: T.gs3 || 'Stubli sets itself up' };
		gs.classList.add( 'is-live' );
		var show = function ( focus ) {
			var id = ( location.hash || '' ).replace( '#gs-', '' ), cur = gs.querySelector( '[data-gs-step="' + id + '"]' ) || steps[ 0 ];
			steps.forEach( function ( s ) { s.hidden = s !== cur; } );
			var n = +cur.getAttribute( 'data-gs-n' );
			prog.hidden = false;
			prog.innerHTML = '';
			prog.appendChild( document.createTextNode( ( T.stepOf || 'Step %1$d of 3' ).replace( '%1$d', n ) + ' · ' + names[ n ] ) );
			var bar = document.createElement( 'span' ), fill = document.createElement( 'i' ); fill.style.width = ( n / 3 * 100 ) + '%'; bar.appendChild( fill ); prog.appendChild( bar );
			if ( focus ) { var h = cur.querySelector( 'h2' ); h.setAttribute( 'tabindex', '-1' ); h.focus(); window.scrollTo( 0, Math.max( 0, gs.getBoundingClientRect().top + window.scrollY - 80 ) ); }
		};
		window.addEventListener( 'hashchange', function () { if ( /^#gs-/.test( location.hash ) || ! location.hash ) { show( true ); } } );
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
