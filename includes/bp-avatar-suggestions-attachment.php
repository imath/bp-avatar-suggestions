<?php
/**
 * Avatar Suggestions attachment class
 *
 * @package BP Avatar Suggestions
 * @subpackage Attachment
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Avatar Suggestions Attachment class
 *
 * Extends BP Attachment to manage the avatar suggestions
 *
 * @since BP Avatar Suggestions (1.3.0)
 */
class Avatar_Suggestions_Attachment extends BP_Attachment {

	/**
	 * Construct Upload parameters
	 *
	 * @since BP Avatar Suggestions (1.3.0)
	 */
	public function __construct() {
		parent::__construct( array(
			'action'                => 'avatar_suggestions_upload',
			'file_input'            => 'suggestion',
			'original_max_filesize' => bp_core_avatar_original_max_filesize(),
			'required_wp_files'     => array( 'file', 'image' ),
			'allowed_mime_types'    => bp_core_get_allowed_avatar_types(),
			'upload_error_strings'  => array(
				9  => sprintf( __( 'The width x height of the profile photo should be at least %d x %d px.', 'bp-avatar-suggestions' ), bp_core_avatar_full_width(), bp_core_avatar_full_height() ),
			),
		) );
	}

	/**
	 * Avatar Suggestions specific rule
	 *
	 * Adds an error if the width x height of the suggestion is < then the full
	 * avatar dimensions
	 *
	 * @since BP Avatar Suggestions (1.3.0)
	 *
	 * @param  array $file the temporary file attributes (before it has been moved)
	 * @return array the file with extra errors if needed
	 */
	public function validate_upload( $file = array() ) {
		$file = parent::validate_upload( $file );

		// Bail if already an error
		if ( ! empty( $file['error'] ) ) {
			return $file;
		}

		// Type will be handle by WordPress at a later time
		if ( ! isset( $file['type'] ) || ! isset( $file['tmp_name'] ) ) {
			return $file;
		}

		if (  0 === strpos( $file['type'], 'image/' ) && function_exists( 'getimagesize' ) ) {
			$uploaded_image = @getimagesize( $file['tmp_name'] );

			if ( ! isset( $uploaded_image[0] ) || ! isset( $uploaded_image[1] ) ) {
				return $file;
			}

			if ( ! isset( $uploaded_image[0] ) || ! isset( $uploaded_image[1] ) || $uploaded_image[0] < bp_core_avatar_full_width() || $uploaded_image[1] < bp_core_avatar_full_height() ) {
				// Use our custom error
				$file['error'] = 9;
			}
		}

		// Return with error code attached
		return $file;
	}

	/**
	 * Build script datas for the Uploader UI
	 *
	 * @since BP Avatar Suggestions (1.3.0)
	 *
	 * @return array the javascript localization data
	 */
	public function script_data() {
		// Get default script data
		$script_data = parent::script_data();

		// Get the post all suggestions are attached to.
		$post_id  = buddypress()->extend->avatar_suggestions->avatar_post_id;

		if ( ! empty( $post_id ) ) {
			$script_data['bp_params'] = array(
				'object'     => 'post',
				'item_id'    => $post_id,
			);
		}

		// Include our specific css
		$script_data['extra_css'] = array( 'bp-as-admin-style' );

		// Include our specific js
		$script_data['extra_js']  = array( 'bp-as-admin-js' );

		return $script_data;
	}
}
