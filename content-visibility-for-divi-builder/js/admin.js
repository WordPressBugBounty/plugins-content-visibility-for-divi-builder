jQuery( function( $ ) {
	'use strict';

	$( document.body ).on( 'click', '#' + cvdbAdminScript.textDomain + '_rating-notice .notice-dismiss', function() {
		var $this = $( this );

		wp.apiFetch( {
			path: '/cvdb/v1/notices/rating/dismiss',
			method: 'POST'
		} ).then( function() {
			if ( $this.data( 'cvdb' ) ) {
				$this.closest( '#' + cvdbAdminScript.textDomain + '_rating-notice' ).slideUp();
			}
		} );
	} );
} );
