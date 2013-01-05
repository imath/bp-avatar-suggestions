jQuery(document).ready(function($){
	
	if( '-1' != window.location.toString().indexOf('del-avatar') ) {
		$("#message").removeClass('error');
		$("#message").addClass('updated');
		$("#message").html('<p>' + avatar_list_vars.delsuccess + '</p>');
	}
	
	var avatarArray = $.parseJSON( avatar_list_vars.json_avatar );
	var output = '';
	
	if( !avatarArray.length)
		return false;
		
	if( $('a.edit').length )
		return false;		
		
	for( i=0; i< avatarArray.length; i++){
		output += '<li style="display:inline-block;margin-right:5px;"><table style="width:100%;border:solid 1px #CCC"><tr><td><input type="radio" name="avatar_choice" value="'+avatarArray[i]+'"/></td><td><img src="'+avatarArray[i]+'" alt="avatar" width="50px" height="50px"/></td></tr></table></li>';
	}
	
	actionBtn = '<a href="#" id="chosen_avatar" class="button">' + avatar_list_vars.btn_activate + '</a>';
	
	if( typeof avatar_list_vars.user_avatar_choice != 'undefined' )
		actionBtn = '<a href="#" id="delete_avatar" class="button">' + avatar_list_vars.btn_deactivate + '</a>';
	
	$('#avatar-upload').append( '<div id="avatar_list_containeer"><p><h4>' + avatar_list_vars.intro + '</h4></p><ul>'+output+'</ul><p style="clear:both;margin-top:10px">'+actionBtn+'</p></div>');
	
	$(":radio[name='avatar_choice']").each(function(){
		
		if( typeof avatar_list_vars.user_avatar_choice == 'undefined' )
			return false;
		
		if( $(this).val() == avatar_list_vars.user_avatar_choice )
			$(this).attr('checked', true);
		
		else
			$(this).hide();
		
	});
	
	$('#chosen_avatar').live('click', function(){
		avatar = $(":radio[name='avatar_choice']:checked").val();
		
		if( !avatar ) {
			alert( avatar_list_vars.noselection );
			return false;
		}
			
		
		$.post( ajaxurl, {
			action: 'user_chose_suggested_avatar',
			'url': avatar,
			'user_id':avatar_list_vars.displayeduser_id
		},
		function(response) {

			if( response != 'oops' ) {
				avatarSuccess( avatar, avatar_list_vars.success );
			}
			else {
				alert( avatar_list_vars.error );
			}
		});
		
		return false;
	});
	
	function avatarSuccess(avatar, message) {
		$('.user-'+avatar_list_vars.displayeduser_id+'-avatar').each(function(){
			$(this).attr( 'src', avatar );
		});
		$('html, body').animate({ scrollTop: $("#item-header-avatar").offset().top }, 500);
		if( !$('#message').length )
			$('#item-header').append('<div id="message" class="updated"><p>'+ message +'</p>');
		else {
			$('#item-header #message').removeClass('error');
			$('#item-header #message').addClass('updated');
			$('#item-header #message p').html( message );
		}
		
		// we also need to change the actionbtn !
		$('#chosen_avatar').html( avatar_list_vars.btn_deactivate );
		$('#chosen_avatar').attr('id', 'delete_avatar');
		
	}
	
	$('#delete_avatar').live('click', function(){
		$('#avatar-upload-form').submit();
		return false;
	});
});