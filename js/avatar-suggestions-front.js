/* globals bp, _, BP_Uploader, Backbone */

window.bp = window.bp || {};

( function( exports, $ ) {

	// Bail if not set
	if ( typeof BP_Uploader === 'undefined' ) {
		return;
	}

	bp.Models      = bp.Models || {};
	bp.Collections = bp.Collections || {};
	bp.Views       = bp.Views || {};

	bp.AvatarSuggestions = {
		start: function() {
			bp.Avatar.nav.on( 'bp-avatar-view:changed', _.bind( this.setView, this ) );

			// Disable the Suggestions
			bp.Avatar.navItems.on( 'change:hide', this.showHideNav, this );
		},

		setView: function( view ) {
			if ( 'avatar_suggestions' !== view ) {
				return;
			}

			// Create the view
			var AvatarSuggestionsView = new bp.Views.SuggestionsAvatar();

			// Add it to Avatar views
			bp.Avatar.views.add( { id: 'avatar_suggestions', view: AvatarSuggestionsView } );

			// Display it
	        AvatarSuggestionsView.inject( '.bp-avatar' );
		},

		showHideNav: function( nav ) {
			if ( 'delete' !== nav.get( 'id' ) ) {
				return;
			}

			var suggestionNav = _.findWhere( bp.Avatar.navItems.models, { id: 'avatar_suggestions' } );

			if ( 0 === nav.get( 'hide' ) ) {
				suggestionNav.set( { active: 0, hide: 1 } );
			} else if ( 1 === suggestionNav.get( 'hide' ) ) {
				suggestionNav.set( { hide: 0 } );
			}
		}
	};

	bp.Models.Suggestion = Backbone.Model.extend( {
		suggestion: {},

		initialize: function() {
			this.on( 'change:selected', this.updateAvatar, this );
		},

		updateAvatar: function( model ) {
			if ( 1 === model.get( 'selected' ) ) {
				model.setSuggestion();
			} else {
				model.removeSuggestion();
			}
		},

		setSuggestion: function() {
			var avatar, suggestionView, self = this;

			if ( this.get( 'selected' ) !== 1 ) {
				return;
			}

			avatar = this.get( 'sizes' ).thumbnail.url;
			this.set( 'saving', 1 );

			// Remove the suggestions view
			if ( ! _.isUndefined( bp.Avatar.views.get( 'avatar_suggestions' ) ) ) {
				suggestionView = bp.Avatar.views.get( 'avatar_suggestions' );
				suggestionView.get( 'view' ).remove();
				bp.Avatar.views.remove( { id: 'avatar_suggestions', view: suggestionView } );
			}

			// Set the avatar suggestion for the object
			return wp.ajax.post( 'set_avatar_suggestion', {
				item_id:     BP_Uploader.settings.defaults.multipart_params.bp_params.item_id,
				item_object: BP_Uploader.settings.defaults.multipart_params.bp_params.object,
				avatar_url : avatar,
				nonce:       BP_Uploader.strings.avatar_suggestions.nonce
			} ).done( function( resp ) {
				self.set( 'saving', 0 );

				var avatarStatus = new bp.Views.AvatarStatus( {
					value : BP_Uploader.strings.avatar_suggestions[ resp.feedback_code ],
					type : 'success'
				} );

				bp.Avatar.views.add( {
					id   : 'status',
					view : avatarStatus
				} );

				avatarStatus.inject( '.bp-avatar-status' );

				// Update each avatars of the page
				$( '.' + self.get( 'object' ) + '-' + resp.item_id + '-avatar' ).each( function() {
					$(this).prop( 'src', resp.avatar );
				} );

			} ).fail( function( resp ) {
				self.set( 'saving', 0 );

				var avatarStatus = new bp.Views.AvatarStatus( {
					value : BP_Uploader.strings.avatar_suggestions[ resp.feedback_code ],
					type : 'error'
				} );

				bp.Avatar.views.add( {
					id   : 'status',
					view : avatarStatus
				} );

				avatarStatus.inject( '.bp-avatar-status' );
			} );
		},

		removeSuggestion: function() {
			var avatar, suggestionView, self = this;

			if ( this.get( 'selected' ) !== 0 ) {
				return;
			}

			avatar = this.get( 'sizes' ).thumbnail.url;
			this.set( 'saving', 1 );

			// Remove the suggestions view
			if ( ! _.isUndefined( bp.Avatar.views.get( 'avatar_suggestions' ) ) ) {
				suggestionView = bp.Avatar.views.get( 'avatar_suggestions' );
				suggestionView.get( 'view' ).remove();
				bp.Avatar.views.remove( { id: 'avatar_suggestions', view: suggestionView } );
			}

			return wp.ajax.post( 'remove_avatar_suggestion', {
				item_id:     BP_Uploader.settings.defaults.multipart_params.bp_params.item_id,
				item_object: BP_Uploader.settings.defaults.multipart_params.bp_params.object,
				nonce:       BP_Uploader.strings.avatar_suggestions.nonce
			} ).done( function( resp ) {
				self.set( 'saving', 0 );

				var avatarStatus = new bp.Views.AvatarStatus( {
					value : BP_Uploader.strings.avatar_suggestions[ resp.feedback_code ],
					type : 'success'
				} );

				bp.Avatar.views.add( {
					id   : 'status',
					view : avatarStatus
				} );

				avatarStatus.inject( '.bp-avatar-status' );

				// Update each avatars of the page
				$( '.' + self.get( 'object' ) + '-' + resp.item_id + '-avatar' ).each( function() {
					$(this).prop( 'src', resp.avatar );
				} );

			} ).fail( function( resp ) {
				self.set( 'saving', 0 );

				var avatarStatus = new bp.Views.AvatarStatus( {
					value : BP_Uploader.strings.avatar_suggestions[ resp.feedback_code ],
					type : 'error'
				} );

				bp.Avatar.views.add( {
					id   : 'status',
					view : avatarStatus
				} );

				avatarStatus.inject( '.bp-avatar-status' );
			} );
		}
	} );

	bp.Collections.Suggestions = Backbone.Collection.extend( {
		model: bp.Models.Suggestion,

		sync: function( method, model, options ) {

			if ( 'read' === method ) {
				options = options || {};
				options.context = this;
				options.data = _.extend( options.data || {}, {
					action:      'get_avatar_suggestions',
					item_id:     BP_Uploader.settings.defaults.multipart_params.bp_params.item_id,
					item_object: BP_Uploader.settings.defaults.multipart_params.bp_params.object,
					nonce:       BP_Uploader.strings.avatar_suggestions.nonce
				} );

				return wp.ajax.send( options );
			}
		},

		parse: function( resp ) {
			if ( ! _.isArray( resp ) ) {
				resp = [resp];
			}

			return resp;
		}
	} );

	// Suggestion list
	bp.Views.SuggestionsAvatar = bp.View.extend( {
		tagName: 'ul',
		id: 'bp-suggestions-avatar',

		initialize: function() {
			// First inform we're about to try to fetch suggestions
			bp.Avatar.displayWarning( BP_Uploader.strings.avatar_suggestions.fetching );

			// Fetch the suggestions
			bp.AvatarSuggestions.items = new bp.Collections.Suggestions();
			bp.AvatarSuggestions.items.fetch( { success : this.suggestionsSuccess, error : this.suggestionsFail } );

			// Catch events on the collection
			bp.AvatarSuggestions.items.on( 'add', this.addItemView, this );
		},

		addItemView: function( item ) {
			// We have at list an item, remove the warning
			bp.Avatar.removeWarning();

			this.views.add( new bp.Views.SuggestionsAvatarItem( { model: item } ) );
		},

		suggestionsSuccess: function( collection ) {
			var warning = BP_Uploader.strings.avatar_suggestions.fetchingSuccess;

			// If one suggestion is selected, use another warning
			_.each( collection.models, function( avatar ) {
				if( ! _.isUndefined( avatar.attributes.selected ) && 1 === avatar.attributes.selected ) {
					warning = BP_Uploader.strings.avatar_suggestions.suggestionSelected;
				}
			} );

			bp.Avatar.displayWarning( warning );
		},

		suggestionsFail: function() {
			bp.Avatar.displayWarning( BP_Uploader.strings.avatar_suggestions.fetchingFailed );
		}
	} );

	// Suggestions view
	bp.Views.SuggestionsAvatarItem = bp.View.extend( {
		tagName:   'li',
		className: 'avatar-suggestion',
		template: bp.template( 'suggestion' ),

		events: {
			'click .avatar-suggestion-item': 'toggleSelection'
		},

		render: function() {
			if ( ! _.isUndefined( this.model.get( 'selected' ) ) && 1 === this.model.get( 'selected' ) ) {
				this.el.className = 'avatar-suggestion selected';
			}

			bp.View.prototype.render.apply( this, arguments );
			return this;
		},

		toggleSelection: function( event ) {
			var id, current_avatar = 0;

			event.preventDefault();

			id = $( event.target ).data( 'suggestionid' );

			_.each( this.model.collection.models, function( avatar ) {
				if( ! _.isUndefined( avatar.attributes.selected ) && 1 === avatar.attributes.selected ) {
					current_avatar = avatar.attributes.id;
				}
			} );

			/* Edit one avatar at a time */
			if ( 0 === current_avatar && 1 !== this.model.get( 'saving' ) ) {
				this.model.set( 'selected', 1 );
				$( event.target ).closest( 'li' ).addClass( 'selected' );
			} else if ( id === current_avatar && 1 !== this.model.get( 'saving' ) ) {
				this.model.set( 'selected', 0 );
				$( event.target ).closest( 'li' ).removeClass( 'selected' );
			} else {
				return;
			}
		}
	} );

	bp.AvatarSuggestions.start();

} )( bp, jQuery );
