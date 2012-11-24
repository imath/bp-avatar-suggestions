<?php
/*
Plugin Name: BP Avatar Suggestions
Plugin URI: http://imath.owni.fr/
Description: Adds an avatar suggestions list to your BuddyPress powered community
Version: 1.0-beta1
Requires at least: 3.4.2
Tested up to: 3.5
License: GNU/GPL 2
Author: imath
Author URI: http://imath.owni.fr/
Network: true
*/

define ( 'BP_AS_PLUGIN_NAME', 'bp-avatar-suggestions' );
define ( 'BP_AS_PLUGIN_URL_JS',  plugins_url('js' , __FILE__) );
define ( 'BP_AS_PLUGIN_DIR',  WP_PLUGIN_DIR . '/' . BP_AS_PLUGIN_NAME );
define ( 'BP_AS_PLUGIN_VERSION', '1.0-beta1');

add_action('bp_include', 'bp_avatar_suggestion_init');

function bp_avatar_suggestion_init() {
	
	if ( (int)bp_get_option( 'bp-disable-avatar-suggestions' ) == 1 )
		require( BP_AS_PLUGIN_DIR . '/includes/bp-avatar-suggestions-front.php' );

	if( is_admin() )
		require( BP_AS_PLUGIN_DIR . '/includes/bp-avatar-suggestions-admin.php' );
}

/**
* bp_avatar_suggestions_load_textdomain
* translation!
* 
*/
function bp_avatar_suggestions_load_textdomain() {

	// try to get locale
	$locale = apply_filters( 'bp_checkins_load_textdomain_get_locale', get_locale() );

	// if we found a locale, try to load .mo file
	if ( !empty( $locale ) ) {
		// default .mo file path
		$mofile_default = sprintf( '%s/languages/%s-%s.mo', BP_AS_PLUGIN_DIR, BP_AS_PLUGIN_NAME, $locale );
		// final filtered file path
		$mofile = apply_filters( 'bp_checkins_load_textdomain_mofile', $mofile_default );
		// make sure file exists, and load it
		if ( file_exists( $mofile ) ) {
			load_textdomain( BP_AS_PLUGIN_NAME, $mofile );
		}
	}
}
add_action ( 'init', 'bp_avatar_suggestions_load_textdomain', 8 );


function bp_avatar_suggestions_install(){
	if( !get_option( 'bp-avatar-suggestions-version' ) || "" == get_option( 'bp-avatar-suggestions-version' ) || BP_AS_PLUGIN_VERSION != get_option( 'bp-avatar-suggestions-version' ) ){
		//might be useful one of these days..
		update_option( 'bp-avatar-suggestions-version', BP_AS_PLUGIN_VERSION );
		update_option( 'bp-disable-avatar-suggestions', 1);
	}
}

register_activation_hook( __FILE__, 'bp_avatar_suggestions_install' );
?>