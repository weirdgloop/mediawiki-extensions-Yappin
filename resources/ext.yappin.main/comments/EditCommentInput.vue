<template>
	<div class="comment-input-container">
		<div>
			<div class="ve-area-wrapper">
				<textarea
					ref="input"
					rows="5"
				></textarea>
			</div>
			<div class="comment-input-actions">
				<cdx-button
					:disabled="store.globalCooldown"
					action="progressive"
					weight="primary"
					@click="submitComment">
					<span v-if="store.globalCooldown">{{ $i18n( 'yappin-submit-cooldown', store.globalCooldown ).text() }}</span>
					<span v-else>{{ $i18n( 'yappin-post-edit' ).text() }}</span>
				</cdx-button>
				<cdx-button action="destructive" @click="store.isEditing = null">
					{{ $i18n( 'cancel' ).text() }}
				</cdx-button>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '../codex.js' );
const store = require( '../store.js' );
const Comment = require( '../comment.js' );

const api = new mw.Rest();

const config = mw.config.get( [
	'wgContentLanguage'
] );

module.exports = exports = defineComponent( {
	name: 'EditCommentInput',
	components: {
		CdxButton
	},
	props: {
		comment: {
			type: Comment,
			default: null,
			required: true
		}
	},
	data() {
		return {
			store,
			ve: null
		};
	},
	methods: {
		submitComment() {
			const body = {};

			if ( this.$data.ve ) {
				// We're going to pass the raw HTML from VE to our API. However, the API will parse it using Parsoid
				// which will sanitize it before saving it in the database.
				body.html = this.$data.ve.target.getSurface().getHtml();
			} else {
				// If we're not using VE, just send the raw value of the input as wikitext.
				body.wikitext = $( this.$refs.input ).val();
			}

			// Use .ajax here rather than .post to circumvent bug: https://bugs.jquery.com/ticket/12326/
			api.ajax( `/comments/v0/comment/${ this.$props.comment.id }/edit`, {
				type: 'PUT',
				data: JSON.stringify( body ),
				dataType: 'json',
				contentType: 'application/json'
			} ).then( ( data ) => {
				const newComment = new Comment( data.comment );
				this.$props.comment.html = newComment.html;
				this.$props.comment.wikitext = newComment.wikitext;
				this.$props.comment.edited = newComment.edited;
				this.$data.store.isEditing = null;
			} ).fail( ( _, result ) => {
				let error;
				if ( result.xhr.responseJSON && Object.prototype.hasOwnProperty.call(
					result.xhr.responseJSON, 'messageTranslations' ) ) {
					if ( result.xhr.responseJSON.errorKey === 'yappin-submit-error-spam' ) {
						// If the comment was rejected for spam/abuse, add a small cooldown
						this.$data.store.globalCooldown = 10;
					}

					if ( config.wgContentLanguage in result.xhr.responseJSON.messageTranslations ) {
						error = result.xhr.responseJSON.messageTranslations[ config.wgContentLanguage ];
					} else {
						error = result.xhr.responseJSON.messageTranslations.en;
					}
				} else {
					error = mw.message( 'unknown-error' );
				}
				mw.notify( error, { type: 'error', tag: 'post-comment-error' } );
			} );
		}
	},
	mounted() {
		const $input = $( this.$refs.input );

		const canUseVe = mw.commentsExt.ve.Editor.static.isSupported();
		$input.val( canUseVe ? this.$props.comment.html : this.$props.comment.wikitext );

		if ( canUseVe ) {
			// Create the VE instance for this editor
			this.$data.ve = new mw.commentsExt.ve.Editor( $input, this.$props.comment.html );
		}
	}
} );
</script>
