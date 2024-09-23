jQuery( function($) {
	'use strict';

	$( document.body ).on( 'click', '#' + cvdbAdminScript.textDomain + '_rating-notice .notice-dismiss', function() {
		var $this = $( this );

		var settings = {
			method: 'POST',
			url: cvdbAdminScript.ajaxUrl,
			data: {
				action: cvdbAdminScript.textDomain + '_dismiss-rating-notice'
			}
		};

		if ( $this.data( 'cvdb' ) ) {
			settings.success = function() {
				$this.closest( '#' + cvdbAdminScript.textDomain + '_rating-notice' ).slideUp();
			};
		}

		$.ajax( settings );
	} );
} );
