/* Stubli: the daily summary. No caching, no tracking. */
/* The app's address is this worker's scope; the icon comes with each notification. */
var APP = self.registration.scope;
self.addEventListener( 'install', function () { self.skipWaiting(); } );
self.addEventListener( 'activate', function ( e ) { e.waitUntil( self.clients.claim() ); } );
self.addEventListener( 'push', function ( e ) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch ( err ) { d = {}; }
	var opts = { body: d.body || '', tag: 'stubli-daily', renotify: false, data: { url: d.url || APP } };
	if ( d.icon ) { opts.icon = d.icon; opts.badge = d.icon; }
	e.waitUntil( self.registration.showNotification( d.title || 'Stubli', opts ) );
} );
self.addEventListener( 'notificationclick', function ( e ) {
	e.notification.close();
	var url = ( e.notification.data && e.notification.data.url ) || APP;
	e.waitUntil( self.clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( function ( list ) {
		for ( var i = 0; i < list.length; i++ ) { if ( list[ i ].url.indexOf( APP ) === 0 && 'focus' in list[ i ] ) { list[ i ].navigate( url ); return list[ i ].focus(); } }
		return self.clients.openWindow( url );
	} ) );
} );
