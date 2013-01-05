<?php

add_action('bp_actions', 'bp_as_front_load_js');

function bp_as_front_load_js(){
	
	if( bp_is_user_change_avatar() ) {
		global $bp;
		
		if ( !(int)bp_get_option( 'bp-disable-avatar-suggestions' ) )
			return false;
		
		$attachment_ids = get_option( 'suggestion_list_avatar_array' );

		if( is_array( $attachment_ids ) ) {
			$args = array(
				'post_type' => 'attachment',
				'include' => $attachment_ids
			);

			$suggested_avatars = get_posts( $args );
		}
		else 
			$suggested_avatars = array();
			
		if( count( $suggested_avatars ) >= 1) {
			
			foreach( $suggested_avatars as $attachment ){
				$avatar = wp_get_attachment_image_src( $attachment->ID, array(150, 150) );
				
				$avatar_list[] = $avatar[0];
			}
			
		}
		
		if( $avatar_list ) {
			
			if( bp_get_user_has_avatar( $bp->displayed_user->id ) )
				return false;
			
			$avatar_list_array = array(
						'json_avatar'      => json_encode( $avatar_list ),
						'displayeduser_id' => $bp->displayed_user->id,
						'success'          => __('Bravo, i also like this one!', 'bp-avatar-suggestions' ),
						'error'            => __('OOps something went wrong.', 'bp-avatar-suggestions' ),
						'delsuccess'       => __('Avatar deleted.', 'bp-avatar-suggestions' ),
						'noselection'      => __('Please select an avatar', 'bp-avatar-suggestions' ),
						'intro'            => __('Or choose one of the suggested avatars below:', 'bp-avatar-suggestions' ),
						'btn_activate'     => __('Activate', 'bp-avatar-suggestions' ),
						'btn_deactivate'   => __('Deactivate', 'bp-avatar-suggestions' )
					);
			
			$user_choice = get_user_meta( $bp->displayed_user->id, 'user_avatar_choice', true );
			
			if( !empty( $user_choice ) )
					$avatar_list_array['user_avatar_choice'] = $user_choice ;
			
			
			wp_enqueue_script( 'bp-as-front-js', BP_AS_PLUGIN_URL_JS .'/bp-as-front.js', array('jquery'), "1.0", 1 );
			wp_localize_script('bp-as-front-js', 'avatar_list_vars', $avatar_list_array );
		}
		
	}
}

add_action( 'wp_ajax_user_chose_suggested_avatar', 'bp_as_avatar_ajax_user_set');

function bp_as_avatar_ajax_user_set(){
	$user_id = $_POST['user_id'];
	$avatar = $_POST['url'];
	
	if( update_user_meta( $user_id, 'user_avatar_choice', $avatar ) ) {
		
		do_action('xprofile_avatar_uploaded');
		echo 1;
		
	} else {
		
		echo 'oops';
		
	}
		
	die();
}

add_filter('bp_core_fetch_avatar', 'bp_as_fetch_suggested_avatar', 1, 9 );

function bp_as_fetch_suggested_avatar($image, $params, $item_id, $avatar_dir, $css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir) {
	
	if( $params['object'] != "user") return $image;
	
	if( strpos( $image, 'mystery-man.jpg' ) > 0 ) {
		
		$user_choice = get_user_meta( $item_id, 'user_avatar_choice', true );

		if( empty( $user_choice ) )
			return $image;
		
		$image = preg_replace('/src="([^"]*)"/i', 'src="' .$user_choice.'"', $image );

   	}
	
	/* in case you need to filter with your own function... */
	return apply_filters( 'bp_as_fetch_suggested_avatar', $image, $params, $item_id, $css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir );
	
}

function bp_as_front_avatar_delete(){
	global $bp;
	
	if( !empty( $_POST['avatar_choice'] ) ) {
		
		delete_user_meta( $bp->displayed_user->id, 'user_avatar_choice' );
		
		do_action( 'bp_core_delete_existing_avatar');
		
		// avoid the upload new avatar bug.
		if( empty( $_FILES["file"]["name"] ) )
			bp_core_redirect( wp_get_referer() . "?del-avatar");
		
	}
	
}

add_action('xprofile_screen_change_avatar', 'bp_as_front_avatar_delete');
?>