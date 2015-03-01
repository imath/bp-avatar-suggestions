window.wp = window.wp || {};

( function( $ ){

	var avatar_suggestions = {

		start: function() {
			this.suggestions = new this.Collections.Suggestions();
			this.suggestions.fetch();
			this.previousAvatar = $( '#item-header-avatar img' ).prop( 'src' );
			this.suggestions.on( 'add', this.inject, this );
		},

		inject: function() {
			this.view = new this.Views.Terms( { collection: this.suggestions } );
			this.view.inject( '.avatar-suggestions-list' );
		},

		feedback: function( avatar, object, item_id, message, type ) {
			$( '.' + object + '-' + item_id + '-avatar').each( function() {
				$(this).prop( 'src', avatar );
			} );

			reverse = ( type == 'updated' ) ? 'error' : 'updated';

			if ( avatar_suggestions_vars.groupCreateContext ) {
				header =  '#create-group-form';
				$( 'html, body' ).animate( { scrollTop: $( '#create-group-form' ).offset().top }, 500 );
			} else {
				header = '#item-header';
				$( 'html, body' ).animate( { scrollTop: $( '#item-header-avatar' ).offset().top }, 500 );
			}

			if ( ! $( '#message' ).length ) {
				if ( avatar_suggestions_vars.groupCreateContext ) {
					$( header ).prepend( '<div id="message" class="' + type + '"><p>' + message + '</p>' );
				} else {
					$( header ).append( '<div id="message" class="' + type + '"><p>' + message + '</p>' );
				}
			} else {
				$( header + ' #message' ).removeClass( reverse );
				$( header + ' #message' ).addClass( type );
				$( header + ' #message p' ).html( message );
			}
		}
	};

	// Extend wp.Backbone.View with .prepare() and .inject()
	avatar_suggestions.View = wp.Backbone.View.extend({
		inject: function( selector ) {
			this.render();
			$(selector).html( this.el );
			this.views.ready();
		},

		prepare: function() {
			if ( ! _.isUndefined( this.model ) && _.isFunction( this.model.toJSON ) ) {
				return this.model.toJSON();
			} else {
				return {};
			}
		}
	});

	/* ------ */
	/* MODELS */
	/* ------ */

	avatar_suggestions.Models = {};
	avatar_suggestions.vars = avatar_suggestions_vars;

	avatar_suggestions.Models.Suggestion = Backbone.Model.extend( {
		suggestion: {},
	} );

	/* ----------- */
	/* COLLECTIONS */
	/* ----------- */
	avatar_suggestions.Collections = {};

	avatar_suggestions.Collections.Suggestions = Backbone.Collection.extend( {
		model: avatar_suggestions.Models.Suggestion,

		sync: function( method, model, options ) {

			if( 'read' === method ) {
				options = options || {};
				options.context = this;
				options.data = _.extend( options.data || {}, {
					action:      'get_avatar_suggestions',
					item_id:     avatar_suggestions.vars.item_id,
					item_object: avatar_suggestions.vars.item_object,
					nonce:       avatar_suggestions.vars.nonce
				} );

				return wp.ajax.send( options );
			}
		},

		parse: function( resp, xhr ) {
			if ( ! _.isArray( resp ) ) {
				resp = [resp];
			}

			return resp;
		},

		setSuggestion: function( suggestion, options ) {
			var avatar, model = this;
			options = options || {};

			if ( suggestion.get( 'selected' ) != 1 ) {
				return;
			}

			avatar = suggestion.get( 'sizes' ).thumbnail.url;
			suggestion.set( 'saving', 1 );

			return wp.ajax.post( 'set_avatar_suggestion', {
				item_id:     avatar_suggestions.vars.item_id,
				item_object: avatar_suggestions.vars.item_object,
				avatar_url : avatar,
				nonce:       avatar_suggestions.vars.nonce
			} ).done( function( resp, status, xhr ) {
				suggestion.set( 'saving', 0 );
				avatar_suggestions.feedback(
					avatar,
					avatar_suggestions.vars.item_object,
					avatar_suggestions.vars.item_id,
					avatar_suggestions.vars.avatarSaved,
					'updated'
				);
			} ).fail( function( resp, status, xhr ) {
				suggestion.set( 'saving', 0 );
				avatar_suggestions.feedback(
					avatar_suggestions.previousAvatar,
					avatar_suggestions.vars.item_object,
					avatar_suggestions.vars.item_id,
					avatar_suggestions.vars.avatarNotSaved,
					'error'
				);
			} );
		},

		removeSuggestion: function( suggestion, options ) {
			model = this;
			options = options || {};

			if ( suggestion.get( 'selected' ) != 0 ) {
				return;
			}

			avatar = suggestion.get( 'sizes' ).thumbnail.url;
			suggestion.set( 'saving', 1 );

			return wp.ajax.post( 'remove_avatar_suggestion', {
				item_id:     avatar_suggestions.vars.item_id,
				item_object: avatar_suggestions.vars.item_object,
				nonce:       avatar_suggestions.vars.nonce
			} ).done( function( resp, status, xhr ) {
				suggestion.set( 'saving', 0 );
				avatar_suggestions.feedback(
					resp,
					avatar_suggestions.vars.item_object,
					avatar_suggestions.vars.item_id,
					avatar_suggestions.vars.avatarRemoved,
					'updated'
				);
			} ).fail( function( resp, status, xhr ) {
				suggestion.set( 'saving', 0 );
				avatar_suggestions.feedback(
					avatar,
					avatar_suggestions.vars.item_object,
					avatar_suggestions.vars.item_id,
					avatar_suggestions.vars.avatarNotRemoved,
					'error'
				);
			} );
		}
	} );

	/* ----- */
	/* VIEWS */
	/* ----- */
	avatar_suggestions.Views = {};

	// List of suggestions
	avatar_suggestions.Views.Terms = avatar_suggestions.View.extend( {
		tagName:   'ul',
		className: 'avatar-suggestions',

		initialize: function() {
			_.each( this.collection.models, this.addItemView, this );
			this.collection.on( 'change:selected', this.updateAvatar, this );
		},

		addItemView: function( suggestions ) {
			this.views.add( new avatar_suggestions.Views.Suggestion( { model: suggestions } ) );
		},

		updateAvatar: function( model ) {
			if ( 1 == model.get( 'selected' ) ) {
				this.collection.setSuggestion( model );
			} else {
				this.collection.removeSuggestion( model );
			}
		}
	} );

	// Suggestion
	avatar_suggestions.Views.Suggestion = avatar_suggestions.View.extend( {
		tagName:   'li',
		className: 'avatar-suggestion',
		template: wp.template( 'suggestion' ),

		events: {
			'click .avatar-suggestion-item': 'toggleSelection',
		},

		render: function() {
			if ( ! _.isUndefined( this.model.get( 'selected' ) ) && 1 == this.model.get( 'selected' ) ) {
				this.el.className = 'avatar-suggestion selected';
			}
			/**
			 * Call `render` directly on parent class with passed arguments
			 */
			avatar_suggestions.View.prototype.render.apply( this, arguments );
			return this;
		},

		toggleSelection: function( event ) {
			var id, current_avatar = 0;

			event.preventDefault();

			id = $( event.target ).data( 'suggestionid' );

			_.each( this.model.collection.models, function( avatar ) {
				if( ! _.isUndefined( avatar.attributes.selected ) && 1 == avatar.attributes.selected ) {
					current_avatar = avatar.attributes.id;
				}
			} );

			/* Edit one term at a time */
			if ( 0 == current_avatar && 1 != this.model.get( 'saving' ) ) {
				this.model.set( 'selected', 1 );
				$( event.target ).closest( 'li' ).addClass( 'selected' );
			} else if ( id == current_avatar && 1 != this.model.get( 'saving' ) ) {
				this.model.set( 'selected', 0 );
				$( event.target ).closest( 'li' ).removeClass( 'selected' );
			} else {
				return;
			}
		},
	} );

	avatar_suggestions.start();

} )( jQuery );
