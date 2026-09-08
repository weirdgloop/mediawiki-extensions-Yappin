<template>
	<div class="comment-rating-actions">
		<button
			class="comment-rating-btn"
			:title="$i18n( 'yappin-rating-upvote' )"
			data-type="upvote"
			:value="$props.comment.userRating === 1"
			:disabled="waiting"
			@click="onButtonClick"
		>
			<cdx-icon
				:icon="cdxIconUpTriangle"
				size="small"
			></cdx-icon>
		</button>
		<button
			class="comment-rating-btn"
			:title="$i18n( 'yappin-rating-downvote' )"
			data-type="downvote"
			:value="$props.comment.userRating === -1"
			:disabled="waiting"
			@click="onButtonClick"
		>
			<cdx-icon
				:icon="cdxIconDownTriangle"
				size="small"
			></cdx-icon>
		</button>
	</div>
</template>

<script>
const Comment = require( '../comment.js' );
const store = require( '../store.js' );
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( '../codex.js' );
const { cdxIconUpTriangle, cdxIconDownTriangle } = require( '../icons.json' );

const api = new mw.Rest();

const config = mw.config.get( [
	'wgContentLanguage'
] );

module.exports = exports = defineComponent( {
	name: 'CommentRating',
	components: {
		CdxIcon
	},
	props: {
		comment: {
			type: Comment,
			default: null,
			required: true
		}
	},
	setup() {
		return {
			cdxIconUpTriangle,
			cdxIconDownTriangle
		};
	},
	data() {
		return {
			store,
			waiting: false
		};
	},
	methods: {
		onButtonClick( e ) {
			this.$data.waiting = true;
			const type = e.currentTarget.dataset.type;
			let newValue = 0;

			if ( type === 'upvote' ) {
				newValue = this.$props.comment.userRating === 1 ? 0 : 1;
			} else if ( type === 'downvote' ) {
				newValue = this.$props.comment.userRating === -1 ? 0 : -1;
			}

			// Change the UI state before the API call happens for quick visual feedback
			const oldValue = this.$props.comment.userRating;
			this.$props.comment.userRating = newValue;

			api.post( `/comments/v0/comment/${ this.$props.comment.id }/vote`, {
				rating: newValue
			} ).then( ( data ) => {
				this.$props.comment.rating = data.comment.rating;
			} ).always( () => {
				this.$data.waiting = false;
			} ).fail( ( _, result ) => {
				let error;
				// Reset the UI state back to the previous value if the API call failed
				this.$props.comment.userRating = oldValue;

				if ( result.xhr.responseJSON && Object.prototype.hasOwnProperty.call(
					result.xhr.responseJSON, 'messageTranslations' ) ) {
					if ( result.xhr.responseJSON.errorKey === 'yappin-submit-error-spam' ) {
						// If the comment was rejected for spam/abuse, add a small cooldown
						this.store.globalCooldown = 10;
					}

					if ( config.wgContentLanguage in result.xhr.responseJSON.messageTranslations ) {
						error = result.xhr.responseJSON.messageTranslations[ config.wgContentLanguage ];
					} else {
						error = result.xhr.responseJSON.messageTranslations.en;
					}
				} else {
					error = mw.message( 'unknown-error' );
				}
				mw.notify( error, { type: 'error', tag: 'vote-comment-error' } );
			} );
		}
	}
} );
</script>
