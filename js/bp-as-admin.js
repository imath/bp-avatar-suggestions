/*!
 * BP Avatar Suggestions old script
 */

;
(function($) {

	$( '.avatar_upload_image_button' ).on( 'click', function( event ) {
		event.preventDefault();

		tb_show( '', $( this ).prop( 'href' ) + '?bpas=1&amp;post_id=' + bp_as_admin_vars.bpas_post_id + '&amp;type=image&amp;TB_iframe=true' );
	} );

	window.send_to_editor = function( html ) {
	 	tb_remove();
	}

	$( 'body.settings_page_bp-avatar-suggestions' ).on( 'tb_unload', '#TB_window', function( event ) {
		event.preventDefault();
		$( location ).prop( 'href', bp_as_admin_vars.redirect );
	} );

	$( '.avatar_delete_image_button' ).click( function() {

		var trelement = $( this ).parent().parent().parent().prop( 'id' ) ;

		var button = $( this )
		button.hide();

		$.post( ajaxurl, {
			action         : 'bp_as_admin_avatar_delete',
			'attachmentid' : $(this).data('attachmentid'),
			'nonce'        : bp_as_admin_vars.bpasnonce
		},
		function( response ) {

			if ( response != -1 ) {
				$( '#'+trelement ).remove();
			} else {
				alert( bp_as_admin_vars.error );
				button.show();
			}
		} );

		return false;
	} );

} )( jQuery );
