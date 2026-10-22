/**
 * stubli.app · "Stubli in a minute": makes the app pieces respond.
 * Everything on the page reads fine without this script. Everyone in the
 * examples is made up.
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
	var setText = function ( sel, text ) { $$( sel ).forEach( function ( el ) { el.textContent = text; } ); };

	/* Greeting by the visitor's own time of day. */
	var hr = new Date().getHours();
	var greet = hr < 5 ? 'Good night' : hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : hr < 22 ? 'Good evening' : 'Good night';

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

	/* Your name, your Stubli: names change by themselves until you type. */
	var nameIn = $( '[data-sp-name]' );
	var startName = $( '[data-sp-start-name]' );
	var pageEl = $( '[data-sp-people]' );
	var people = [];
	try { people = JSON.parse( pageEl ? pageEl.getAttribute( 'data-sp-people' ) : '[]' ) || []; } catch ( e ) { people = []; }
	var closeH = $( '.st-pitch-close h2' );
	var closeWord = closeH ? closeH.textContent.replace( /[.!]\s*$/, '' ) : '';
	var slug = function ( s ) {
		return ( s.normalize ? s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ) : s ).toLowerCase().replace( /[^a-z0-9]+/g, '' ).slice( 0, 20 ) || 'yourname';
	};
	var show = function ( name, who ) {
		name = ( name || '' ).trim().replace( /\s+/g, ' ' );
		var first = name.split( ' ' )[ 0 ] || '';
		setText( '[data-sp-full]', name || ' ' );
		setText( '[data-sp-addr]', ( slug( name ) ) + '.ch' );
		setText( '[data-sp-slug]', slug( name ) );
		setText( '[data-sp-greet]', greet + ( first ? ', ' + first : '' ) );
		if ( who ) {
			setText( '[data-sp-bio]', who.bio );
			setText( '[data-sp-q]', who.q );
			setText( '[data-sp-a]', who.a );
		} else {
			setText( '[data-sp-bio]', 'Hi, I’m ' + ( first || 'new here' ) + '. Come in, have a look around, and sign the guest book on your way out.' );
			setText( '[data-sp-q]', 'A perfect Sunday…' );
			setText( '[data-sp-a]', 'Tap to write your answer.' );
		}
		$$( '[data-sp-ava]' ).forEach( function ( a ) {
			var k = a.getAttribute( 'data-sp-ava' );
			a.classList.toggle( 'is-on', who ? String( people.indexOf( who ) ) === k : 'you' === k );
		} );
		if ( closeH && first && closeWord ) { closeH.textContent = closeWord + ', ' + first + '.'; }
		if ( startName && document.activeElement !== startName ) { startName.value = name; }
	};
	var auto = !! ( nameIn && people.length && ! still );
	var who = 0, typing = null;
	var typeTo = function ( target, done ) {
		var cur = nameIn.value;
		nameIn.classList.add( 'sp-typing' );
		clearInterval( typing );
		typing = setInterval( function () {
			if ( ! auto ) { clearInterval( typing ); nameIn.classList.remove( 'sp-typing' ); return; }
			if ( target.indexOf( cur ) !== 0 ) { cur = cur.slice( 0, -1 ); } else if ( cur.length < target.length ) { cur = target.slice( 0, cur.length + 1 ); } else { clearInterval( typing ); nameIn.classList.remove( 'sp-typing' ); done(); return; }
			nameIn.value = cur;
		}, 70 );
	};
	var nextName = function () {
		if ( ! auto ) { return; }
		who = ( who + 1 ) % people.length;
		typeTo( people[ who ].name, function () { show( people[ who ].name, people[ who ] ); setTimeout( nextName, 2600 ); } );
	};
	if ( nameIn ) {
		show( nameIn.value, people[ 0 ] || null );
		var stopAuto = function () { auto = false; nameIn.classList.remove( 'sp-typing' ); };
		nameIn.addEventListener( 'focus', stopAuto );
		nameIn.addEventListener( 'input', function () { stopAuto(); show( nameIn.value, null ); } );
		if ( auto && 'IntersectionObserver' in window ) {
			var started = false;
			new IntersectionObserver( function ( es ) { es.forEach( function ( e ) { if ( e.isIntersecting && ! started ) { started = true; setTimeout( nextName, 1800 ); } } ); }, { threshold: .4 } ).observe( nameIn );
		}
	}
	if ( startName ) {
		startName.addEventListener( 'input', function () { auto = false; if ( nameIn ) { nameIn.value = startName.value; } show( startName.value, null ); } );
	}
	var next = $( '[data-sp-next]' );
	$$( '[data-sp-have]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			if ( next ) { next.textContent = 'yes' === r.value ? 'Next: add Stubli to your site in two taps. Stubli itself is completely free.' : 'Next: get your own site on WordPress.com, then add Stubli. About ten minutes, and Stubli itself is completely free.'; }
		} );
	} );

	/* House rules: each control changes the post on the left. */
	var room = $( '[data-sp-room]' );
	var friends = $( '[data-sp-friends]' );
	var aud = $( '[data-sp-aud]' );
	if ( friends && aud ) {
		friends.addEventListener( 'change', function () {
			aud.textContent = friends.checked ? 'Friends only · shared with 4 friends, not on the public page' : 'Everyone can see this';
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

	/* House rules, one at a time as you scroll, each shown working. */
	var roomsSec = $( '.st-pitch-rooms' );
	var rooms = $$( '.st-pitch-room' );
	var roomDots = $$( '.sp-rooms-progress i' );
	if ( roomsSec && rooms.length && ! still ) {
		roomsSec.classList.add( 'is-scrolly' );
		var colours = $$( '[data-sp-colour]' );
		var cset = $$( '[data-sp-cset]' );
		var setIf = function ( input, on ) { if ( input && input.checked !== on ) { input.checked = on; input.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } };
		var roomAt = -1, lastKey = '';
		var paintRooms = function () {
			var r = roomsSec.getBoundingClientRect();
			var span = roomsSec.offsetHeight - window.innerHeight;
			var p = Math.min( 1, Math.max( 0, -r.top / ( span || 1 ) ) );
			var x = p * rooms.length;
			var i = Math.min( rooms.length - 1, Math.floor( x ) );
			var sub = Math.min( 1, x - i );
			if ( i !== roomAt ) {
				roomAt = i;
				rooms.forEach( function ( el, k ) { el.classList.toggle( 'is-on', k === i ); } );
				roomDots.forEach( function ( d, k ) { d.classList.toggle( 'is-on', k === i ); } );
			}
			var key = i + ':' + ( 0 === i ? ( sub > .35 ) : 1 === i ? Math.min( 2, Math.floor( sub * 3 ) ) : 2 === i ? ( sub > .4 ) : Math.min( colours.length - 1, Math.floor( sub * colours.length ) ) );
			if ( key === lastKey ) { return; }
			lastKey = key;
			setIf( friends, i > 0 || sub > .35 );
			if ( cset.length ) { setIf( cset[ i < 1 ? 0 : i > 1 ? 2 : Math.min( 2, Math.floor( sub * 3 ) ) ], true ); }
			if ( end ) {
				var showEnd = i > 2 || ( 2 === i && sub > .4 );
				end.hidden = ! showEnd;
				if ( room ) { room.scrollTop = showEnd ? room.scrollHeight : 0; }
			}
			if ( colours.length ) { setIf( colours[ i < 3 ? 0 : Math.min( colours.length - 1, Math.floor( sub * colours.length ) ) ], true ); }
		};
		var rq = false;
		window.addEventListener( 'scroll', function () { if ( ! rq ) { rq = true; requestAnimationFrame( function () { rq = false; paintRooms(); } ); } }, { passive: true } );
		window.addEventListener( 'resize', paintRooms );
		paintRooms();
	}

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

	/* Four steps: the setup plays out as you scroll. */
	var how = $( '.st-pitch-how' );
	var items = $$( '.st-pitch-steps li' );
	var stepScreens = $$( '[data-sp-step]' );
	var dots = $$( '.sp-steps-stage .sp-progress i' );
	var stageEl = $( '.sp-steps-stage' );
	var appEl = $( '.sp-steps-app' );
	if ( how && items.length && ! still ) {
		how.classList.add( 'is-scrolly' );
		var bio = $( '.sp-bio-type span' );
		var bioText = bio ? bio.textContent : '';
		var colourDots = $$( '.sp-dots i' );
		var pops = $$( '.sp-pop' );
		var wz = $$( '[data-sp-wz]' );
		var meter = $( '.sp-meter' );
		var cur = -1;
		var paint = function () {
			var r = how.getBoundingClientRect();
			var span = how.offsetHeight - window.innerHeight;
			var p = Math.min( 1, Math.max( 0, -r.top / ( span || 1 ) ) );
			var x = p * items.length;
			var i = Math.min( items.length - 1, Math.floor( x ) );
			var sub = Math.min( 1, x - i );
			if ( i !== cur ) {
				cur = i;
				items.forEach( function ( li, k ) { li.classList.toggle( 'is-on', k === i ); li.classList.toggle( 'is-done', k < i ); li.setAttribute( 'aria-current', k === i ? 'step' : 'false' ); } );
				stepScreens.forEach( function ( s, k ) { s.classList.toggle( 'is-on', k === i ); } );
				dots.forEach( function ( d, k ) { d.classList.toggle( 'is-on', k === i ); d.classList.toggle( 'is-done', k < i ); } );
				$$( '[data-sp-scene]' ).forEach( function ( sc, k ) { sc.classList.toggle( 'is-on', k === i ); } );
			}
			if ( stageEl ) { stageEl.style.setProperty( '--p', p.toFixed( 3 ) ); }
			if ( appEl ) { appEl.style.setProperty( '--sub', sub.toFixed( 3 ) ); }
			if ( meter ) { meter.style.setProperty( '--fill', Math.min( 1, sub * 1.7 ).toFixed( 3 ) ); }
			if ( stepScreens[ 1 ] ) { stepScreens[ 1 ].classList.toggle( 'is-done', i > 1 || ( 1 === i && sub > .6 ) ); }
			if ( colourDots.length ) {
				var c = 2 === i ? Math.min( colourDots.length - 1, Math.floor( sub * 1.6 * colourDots.length ) ) : 0;
				colourDots.forEach( function ( d, k ) { d.classList.toggle( 'is-on', k === c ); } );
			}
			if ( bio ) { bio.textContent = 2 === i ? bioText.slice( 0, Math.ceil( Math.min( 1, sub * 1.4 ) * bioText.length ) ) : bioText; }
			wz.forEach( function ( el, k ) { el.classList.toggle( 'is-on', i > 2 || ( 2 === i && k <= Math.floor( sub * 1.3 * wz.length ) ) ); } );
			pops.forEach( function ( el, k ) { el.classList.toggle( 'is-in', 3 === i && sub > .12 + k * .14 ); } );
		};
		var queued = false;
		window.addEventListener( 'scroll', function () { if ( ! queued ) { queued = true; requestAnimationFrame( function () { queued = false; paint(); } ); } }, { passive: true } );
		window.addEventListener( 'resize', paint );
		paint();
		items.forEach( function ( li, k ) {
			li.tabIndex = 0;
			var go = function () {
				var top = how.getBoundingClientRect().top + window.scrollY;
				var span = how.offsetHeight - window.innerHeight;
				window.scrollTo( { top: top + span * ( k + .35 ) / items.length, behavior: 'smooth' } );
			};
			li.addEventListener( 'click', go );
			li.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key || ' ' === e.key ) { e.preventDefault(); go(); } } );
		} );
	}

	/* Make your Stubli, at hand on a phone between the opening and the end. */
	var sticky = $( '[data-sp-sticky]' );
	var hero = $( '.st-pitch-hero' );
	var close = $( '.st-pitch-close' );
	if ( sticky && hero && close && 'IntersectionObserver' in window ) {
		var seen = { hero: true, close: false, how: false, rooms: false };
		var upd = function () { sticky.classList.toggle( 'is-on', ! seen.hero && ! seen.close && ! seen.how && ! seen.rooms ); };
		var watch = function ( el, key ) { new IntersectionObserver( function ( es ) { es.forEach( function ( e ) { seen[ key ] = e.isIntersecting; } ); upd(); } ).observe( el ); };
		watch( hero, 'hero' );
		watch( close, 'close' );
		if ( how ) { watch( how, 'how' ); }
		if ( roomsSec ) { watch( roomsSec, 'rooms' ); }
	}

	/* Entrances: heading, words, then the picture, once per section. */
	if ( 'IntersectionObserver' in window && ! still ) {
		var groups = $$( ':scope > section, .st-pitch-onstage, .st-pitch-athome, .st-pitch-fact' );
		groups.forEach( function ( g ) {
			var parts = $$( ':scope > h1, :scope > h2, :scope > h3, :scope > p, :scope > .wp-block-buttons, :scope > ul, :scope > .sp-ctl, :scope > .sp-noise, :scope > .sp-calm, :scope > .sp-dialog, .st-pitch-sheet > *, .st-pitch-dark > *, .st-pitch-closewords > *', g );
			parts.forEach( function ( el, i ) { el.setAttribute( 'data-reveal', '' ); el.style.setProperty( '--d', Math.min( i, 6 ) * 80 + 'ms' ); } );
		} );
		page.classList.add( 'is-live' );
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { if ( e.isIntersecting ) { e.target.classList.add( 'is-in' ); io.unobserve( e.target ); } } );
		}, { rootMargin: '0px 0px 0px 0px' } );
		$$( '[data-reveal]' ).forEach( function ( el ) { io.observe( el ); } );
	} else {
		page.classList.add( 'is-live' );
	}
}() );
