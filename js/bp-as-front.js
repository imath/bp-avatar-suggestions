jQuery(document).ready(function($){
	var deleteBtn, actionBtn;
	
	if ( '-1' != window.location.toString().indexOf('del-avatar') ) {
		$("#message").removeClass('error');
		$("#message").addClass('updated');
		$("#message").html('<p>' + avatar_list_vars.delsuccess + '</p>');
	}
	
	var avatarArray = $.parseJSON( avatar_list_vars.json_avatar );
	var output = '';
	
	if ( ! avatarArray.length )
		return false;
		
	if ( $('a.edit').length )
		return false;		
		
	for ( i=0; i< avatarArray.length; i++){
		output += '<li style="display:inline-block;margin-right:5px;"><table style="width:100%;border:solid 1px #CCC"><tr><td style="padding-left:5px"><input type="radio" name="avatar_choice" value="'+avatarArray[i]+'"/></td><td><img src="'+avatarArray[i]+'" alt="avatar" width="50px" height="50px"/></td></tr></table></li>';
	}
	
	actionBtn = '<a href="#" id="chosen_avatar" class="button suggestion-action">' + avatar_list_vars.btn_activate + '</a>';
	deleteBtn = '&nbsp;<a href="#" id="delete_avatar" class="button suggestion-action">' + avatar_list_vars.btn_deactivate + '</a>';
	
	if ( typeof avatar_list_vars.user_avatar_choice != 'undefined' )
		actionBtn += deleteBtn;
	
	$( '#avatar-upload').append( '<div id="avatar_list_container"><p><h4>' + avatar_list_vars.intro + '</h4></p><ul>'+output+'</ul><p style="clear:both;margin-top:10px" class="avatar-actions">'+actionBtn+'</p></div>');
	
	setRadio();
	
	$( '#avatar_list_container').on( 'click', 'a.suggestion-action', function( event ){
		event.preventDefault();

		avatar = $( ":radio[name='avatar_choice']:checked" ).val();
		
		if ( ! avatar ) {
			alert( avatar_list_vars.noselection );
			return;
		}

		var action = $( this ).prop( 'id' ).replace( '_avatar', '' );

		switch( action ) {

			case 'chosen' :

				if ( typeof avatar_list_vars.user_avatar_choice != 'undefined' && avatar == avatar_list_vars.user_avatar_choice ) {
					alert( avatar_list_vars.alreadyactive );
					return;
				} else {
					$.post( ajaxurl, {
						action: 'user_chose_suggested_avatar',
						'url'    : avatar,
						'user_id': avatar_list_vars.displayeduser_id,
						'nonce'  : avatar_list_vars.bpasnonce
					},
					
					function( response ) {

						if ( response != -1 ) {
							avatar_list_vars.user_avatar_choice = avatar;
							avatarSuccess( avatar, avatar_list_vars.success );
						}
						else {
							alert( avatar_list_vars.error );
							setRadio();
						}
					});
					return;
				}
			break;

			case 'delete' :

				if ( typeof avatar_list_vars.user_avatar_choice != 'undefined' && avatar != avatar_list_vars.user_avatar_choice ) {
					alert( avatar_list_vars.nodeactivate );
					setRadio();
					return;
				} else {
					$( '#avatar-upload-form' ).submit();
					return;
				}
			break;

			default:
				alert( avatar_list_vars.unknownaction );
			break;
		}

		return;
	});
	
	function avatarSuccess(avatar, message) {
		$('.user-'+avatar_list_vars.displayeduser_id+'-avatar').each(function(){
			$(this).prop( 'src', avatar );
		});

		$( 'html, body').animate( { scrollTop: $( "#item-header-avatar" ).offset().top }, 500 );

		if ( ! $('#message').length ) {
			$( '#item-header' ).append( '<div id="message" class="updated"><p>'+ message +'</p>' );
		} else {
			$('#item-header #message').removeClass('error');
			$('#item-header #message').addClass('updated');
			$('#item-header #message p').html( message );
		}

		if ( ! $( 'p.avatar-actions #delete_avatar').length ) {
			$( 'p.avatar-actions' ).append( deleteBtn );
		}
		
	}

	function setRadio() {
		$( ":radio[name='avatar_choice']" ).each( function(){

			if ( $( this ).prop( 'checked' ) )
				$( this ).prop( 'checked', false );
			
			if ( typeof avatar_list_vars.user_avatar_choice != 'undefined' && $( this ).val() == avatar_list_vars.user_avatar_choice )
				$( this ).prop( 'checked', true );
			
		});
	}
	
});