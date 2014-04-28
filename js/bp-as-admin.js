jQuery(document).ready(function($) {

	var avatarToMod;

	$('.avatar_upload_image_button').click( function() {
		avatarToMod = $(this).parents('table').find('tbody tr').first().prop('id');
	 	formfield = $('#'+avatarToMod+' .avatar_thumbnail_id').prop('name');
		
		tb_show('', $(this).prop('href') + '?type=image&amp;TB_iframe=true');
	 	return false;
	});
 
	window.send_to_editor = function( html ) {
		imgurl = $('img',html).prop('src');
		imgPostIdtoParse = $('img',html).prop( 'class' );
		imgPostIdArray = imgPostIdtoParse.split( 'wp-image-' );
		imgId = parseInt( imgPostIdArray[1] );
		
		
		$('#'+avatarToMod+' #avatar_image').append('<img src="'+imgurl+'" alt="avatar choice" id="avatar-'+imgId+'-avatar" width="50px" height="50px">');
		
		$('#'+avatarToMod+' .avatar_thumbnail_id').val( imgId );
		
	 	tb_remove();
	}

	$('.avatar_delete_image_button').click(function() {
		
		var trelement = $(this).parent().parent().parent().prop( 'id' ) ;
		
		var button = $(this)
		button.hide();
		
		$.post( ajaxurl, {
			action         : 'bp_as_admin_avatar_delete',
			'attachmentid' : $(this).data('attachmentid'),
			'nonce'        : bp_as_admin_vars.bpasnonce
		},
		function( response ) {
			
			if ( response != -1 ) {
				$('#'+trelement).remove();
			} else {
				alert( bp_as_admin_vars.error );
				button.show();
			}
		});
		
		return false;
	});

});