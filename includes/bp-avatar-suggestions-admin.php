<?php


/**
* First we add a setting to the BuddyPress settings page
* using this function is allowing us to be in the xprofile / avatar section.
*/
add_action('bp_register_admin_settings', 'bp_as_avatar_settings');

function bp_as_avatar_settings() {
	if ( bp_is_active( 'xprofile' ) ) {

		// Allow avatar uploads
		add_settings_field( 'bp-disable-avatar-suggestions', __( 'Avatar Suggestions',   'bp-avatar-suggestions' ), 'bp_admin_setting_callback_avatar_suggestions',   'buddypress', 'bp_xprofile' );
		register_setting  ( 'buddypress',         'bp-disable-avatar-suggestions',   'intval'                                                                                  );

	}
}


function bp_admin_setting_callback_avatar_suggestions() {
	?>

		<input id="bp-disable-avatar-suggestions" name="bp-disable-avatar-suggestions" type="checkbox" value="1" <?php checked( bp_disable_avatar_suggestions( true ) ); ?> />
		<label for="bp-disable-avatar-suggestions"><?php _e( 'Allow registered members to choose a suggested avatar', 'bp-avatar-suggestions' ); ?></label>

	<?php
}

function bp_disable_avatar_suggestions( $default = true ) {
	return (bool) apply_filters( 'bp-disable-avatar-suggestions', (bool) bp_get_option( 'bp-disable-avatar-suggestions', $default ) );
}


/**
* Then if the setting is set we add a tab to our Avatar Suggestions page
* using these functions is allowing us to be in the BuddyPress admin area.
*/
add_action('bp_admin_tabs', 'bp_as_admin_tabs' );

function bp_as_admin_tabs(){
	
	if ( !(int)bp_get_option( 'bp-disable-avatar-suggestions' ) )
		return false;
	
	$screen = get_current_screen();
	if( strpos($screen->id, 'bp-avatar-suggestions' ) > 0 )
		$class = "nav-tab-active";
	?>
	<a href="<?php echo bp_get_admin_url( add_query_arg( array( 'page' => 'bp-avatar-suggestions'      ), 'admin.php' ) );?>" class="nav-tab <?php echo $class;?>" style="margin-left:-6px"><?php _e( 'Avatar Suggestions', 'bp-avatar-suggestions' );?></a>
	<?php
}

/**
* Inspired from BuddyPress
*/
function bp_as_add_submenu_page(){
	
	if ( !(int)bp_get_option( 'bp-disable-avatar-suggestions' ) )
		return false;
	
	$page  = bp_core_do_network_admin()  ? 'settings.php' : 'options-general.php';

	$hook_as = add_submenu_page(
		$page,
		__( 'BuddyPress Settings', 'buddypress' ),
		__( 'BuddyPress Settings', 'buddypress' ),
		'manage_options',
		'bp-avatar-suggestions',
		'bp_as_admin_settings'
	);
	
	add_action( "admin_head-$hook_as", 'bp_as_modify_admin_menu_highlight' );
	add_action( "load-$hook_as", 'bp_as_admin_load_js' );
}

add_action( bp_core_admin_hook(), 'bp_as_add_submenu_page');

/**
* Inspired from BuddyPress
*/
function bp_as_modify_admin_menu_highlight() {
	global $plugin_page, $submenu_file;
	
	// This tweaks the Settings subnav menu to show only one BuddyPress menu item
	if ( $plugin_page == 'bp-avatar-suggestions')
		$submenu_file = 'bp-components';
		
}


/**
* Inspired from BuddyPress
*/
function bp_as_modify_admin_menu() {
	
	if ( !(int)bp_get_option( 'bp-disable-avatar-suggestions' ) )
		return false;
	
 	$page  = bp_core_do_network_admin()  ? 'settings.php' : 'options-general.php';

	remove_submenu_page( $page, 'bp-avatar-suggestions' );
	
}

add_action( 'admin_head', 'bp_as_modify_admin_menu', 999 );

/**
* This is the admin page
* we're using WordPress built in media management
* we don't need to take care of file system rights, 
* creating directory or not for uploads...
*/
function bp_as_admin_settings(){
	
	if ( isset( $_POST['bp-as-admin-submit'] ) ) {
		$listing_avatar = array();
		
		if ( !check_admin_referer('bp-as-admin-setup') )
			return false;
		
		if( count( $_POST['suggestion_list_avatar_ids'] ) >= 1 ) {
			$listing_avatar = $_POST['suggestion_list_avatar_ids'];
		}
		
		if( !empty( $_POST['avatar-0-id'] ) )
			$listing_avatar[] = $_POST['avatar-0-id'];
			
		sort( $listing_avatar );
		
		if( count( $listing_avatar ) >= 1 ) {
			update_option( 'suggestion_list_avatar_array', $listing_avatar);
			$message = __('Settings updated !', 'bp-avatar-suggestions');
		}
		
	}
	
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
		
	?>
	<div class="wrap">
		
		<?php if( isset( $message ) ):?>
			<div id="message" class="updated fade">
				<p><?php echo $message;?></p>
			</div>
		<?php endif;?>
		
		<?php screen_icon( 'buddypress'); ?>
		
		<h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Avatar Suggestions', 'buddypress' ) ); ?></h2>
		
		<form action="" method="post" id="bp-admin-avatar-choose-form">
			
			<div style="margin-top:10px">
				
				<?php if( count( $suggested_avatars ) >= 1) :?>
				
					<div style="width:40%;float:left;">
						
						<table class="widefat">
							<thead>
								<tr>
									<th><?php _e( 'Avatars', 'bp-avatar-suggestions' );?></th>
									<th><?php _e( 'Actions', 'bp-avatar-suggestions' );?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th><?php _e( 'Avatars', 'bp-avatar-suggestions' );?></th>
									<th><?php _e( 'Actions', 'bp-avatar-suggestions' );?></th>
								</tr>
							</tfoot>
							<tbody>
								<?php foreach( $suggested_avatars as $attachment ) :?>
					
									<?php $avatar = wp_get_attachment_image_src( $attachment->ID, array(50, 50) );?>
					
									<tr id="avatar-<?php echo $attachment->ID;?>">
										<td>
											<img src="<?php echo $avatar[0];?>" alt="avatar choice" id="avatar-<?php echo $attachment->ID;?>-avatar" width="<?php echo $avatar[1];?>" height="<?php echo $avatar[1];?>">
										</td>
										<td>
											<p><a href="#" class="avatar_delete_image_button button-secondary" rel="<?php echo $attachment->ID;?>"><?php _e( 'Delete this avatar', 'bp-avatar-suggestions' );?></a></p>
											<input type="hidden" name="suggestion_list_avatar_ids[]" id="avatar-<?php echo $attachment->ID;?>-id" class="avatar_thumbnail_id" value="<?php echo $attachment->ID;?>">
										</td>
									</tr>
					
								<?php endforeach;?>
			
							</table>
						</div>
			
					<?php endif;?>
			
					<div style="width:40%;float:left;margin-left:10px">
						
						<table class="widefat">
							<thead>
								<tr>
									<th colspan="2"><?php _e( 'New Avatar', 'bp-avatar-suggestions' );?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th colspan="2"><?php _e( 'New Avatar', 'bp-avatar-suggestions' );?></th>
								</tr>
							</tfoot>
							<tbody>
								<tr id="avatar-0">
									<td id="avatar_image"></td>
									<td>
										<p class="submit clear"><a href="<?php echo admin_url('media-upload.php');?>" class="avatar_upload_image_button button-secondary"><?php _e( 'Add an avatar', 'bp-avatar-suggestions' );?></a></p>
										<input type="hidden" name="avatar-0-id" id="avatar-0-id" class="avatar_thumbnail_id">
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<p class="description">
											<?php _e( 'To add an avatar, click on the &#39;Add an avatar&#39; button then in the thickbox window, upload your avatar. Once done, click on the insert into post button of the thickbox. The avatar will show at the left of the &#39;Add an avatar&#39; button. Finally, use the &#39;Save Settings&#39; button to update the avatar suggestions list.', 'bp-avatar-suggestions' );?>
										</p>
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<p class="submit clear" style="text-align:center"><input class="button-primary" type="submit" name="bp-as-admin-submit" id="bp-as-admin-submit" value="<?php _e( 'Save Settings', 'bp-avatar-suggestions' ) ?>"/></p>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
			
					<br style="clear:both">
				
				</div>

				<?php wp_nonce_field( 'bp-as-admin-setup' ); ?>

			</form>
		</div>
	<?php
}

/* we need some javascript to deal with thickbox ! */
function bp_as_admin_load_js(){
	$screen = get_current_screen();
	
	if( strpos($screen->id, 'bp-avatar-suggestions' ) > 0 ) {
		wp_enqueue_script('media-upload');
		add_thickbox();
		wp_register_script( 'bp-as-admin-js', BP_AS_PLUGIN_URL_JS . '/bp-as-admin.js', array('jquery','media-upload','thickbox') );
		wp_enqueue_script( 'bp-as-admin-js' );
		wp_localize_script('bp-as-admin-js', 'bp_as_admin_vars', array( 
																	'error'  => __('OOps something went wrong.', 'bp-avatar-suggestions' )
																	) );
		
	}
	
}

function bp_as_avatar_ajax_delete(){
	$post_id = $_POST['attachmentid'];
	
	//first we need to catch the url of the image
	$deleted_url = wp_get_attachment_image_src( $post_id, array(150, 150) );
	
	$result = wp_delete_attachment( $post_id, true );
	
	if( $result->ID ){
		$attachment_ids = get_option('suggestion_list_avatar_array');
		
		foreach( $attachment_ids as $k => $v ) {
			if(  $v == $post_id )
				unset( $attachment_ids[$k] );
		}
		
		if( count( $attachment_ids ) > 0 )
			update_option('suggestion_list_avatar_array', $attachment_ids );
			
		else
			delete_option('suggestion_list_avatar_array');
			
		// we also need to delete all the user metas with the deleted_url !
		delete_metadata('user', false, 'user_avatar_choice', $deleted_url[0], true);
		
		echo $result->ID;
	}
		
		
	else
		echo 'oops';
	
	die();
}

add_action( 'wp_ajax_bp_as_admin_avatar_delete', 'bp_as_avatar_ajax_delete');
?>