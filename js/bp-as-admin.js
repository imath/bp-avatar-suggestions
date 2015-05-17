/* globals bp, BP_Uploader, _, Backbone */

window.bp = window.bp || {};

( function( exports, $ ) {

	// Refresh the admin page when thickbox is closed.
	$( 'body.settings_page_bp-avatar-suggestions' ).on( 'tb_unload', '#TB_window', function( event ) {
		event.preventDefault();
		$( location ).prop( 'href', bp_as_admin_vars.redirect );
	} );

	// Bail if not set
	if ( typeof BP_Uploader === 'undefined' ) {
		return;
	}

	bp.Models      = bp.Models || {};
	bp.Collections = bp.Collections || {};
	bp.Views       = bp.Views || {};

	bp.Suggestions = {
		start: function() {
			// Init some vars
			this.views    = new Backbone.Collection();
			this.warning = null;

			// Set up View
			this.uploaderView();

			// Suggestions are uploaded files
			this.suggestions = bp.Uploader.filesUploaded;
		},

		uploaderView: function() {
			// Listen to the Queued uploads
			bp.Uploader.filesQueue.on( 'add', this.uploadProgress, this );

			// Create the BuddyPress Uploader
			var uploader = new bp.Views.Uploader();

			// Add it to views
			this.views.add( { id: 'upload', view: uploader } );

			// Display it
			uploader.inject( '.bp-avatar-suggestions' );
		},

		uploadProgress: function() {
			// Create the Uploader status view
			var suggestionStatus = new bp.Views.uploadSuggestionStatus( { collection: bp.Uploader.filesQueue } );

			if ( ! _.isUndefined( this.views.get( 'status' ) ) ) {
				this.views.set( { id: 'status', view: suggestionStatus } );
			} else {
				this.views.add( { id: 'status', view: suggestionStatus } );
			}

			// Display it
	 		suggestionStatus.inject( '.bp-avatar-status' );
		},
	}

	// Custom Uploader Files view
	bp.Views.uploadSuggestionStatus = bp.Views.uploaderStatus.extend( {
		className: 'files',

		initialize: function() {
			bp.Views.uploaderStatus.prototype.initialize.apply( this, arguments );

			this.collection.on( 'change:url', this.prependImage, this );
		},

		prependImage: function( model ) {
			if ( ! _.isUndefined( model.get( 'url' ) ) && ! $( '#' + model.get('id') + ' .filename img' ).length ) {
				$( '#' + model.get('id') + ' .filename' ).prepend( '<img src="' + model.get( 'url' ) + '"> ' );
			}
		}
	} );

	bp.Suggestions.start();

} )( bp, jQuery );
