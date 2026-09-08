<template>
	<toolbar></toolbar>
	<button
		v-if="!store.isSpecialComments"
		v-show="!isWritingTopLevelComment"
		class="comment-input-placeholder"
		@click="isWritingTopLevelComment = true"
	>
		<span>{{ $i18n( 'yappin-post-placeholder-top-level' ).text() }}</span>
	</button>
	<new-comment-input
		v-if="store.singleComment === null"
		:is-writing-comment="isWritingTopLevelComment"
		:on-cancel="() => isWritingTopLevelComment = false"
	></new-comment-input>
	<comments-list></comments-list>
</template>

<script>
const { defineComponent } = require( 'vue' );
const store = require( './store.js' );
const NewCommentInput = require( './comments/NewCommentInput.vue' );
const CommentsList = require( './CommentsList.vue' );
const Toolbar = require( './Toolbar.vue' );

module.exports = exports = defineComponent( {
	name: 'App',
	components: {
		NewCommentInput,
		Toolbar,
		CommentsList
	},
	data() {
		return {
			store,
			isWritingTopLevelComment: false
		};
	},
	mounted() {
		const self = this;
		// When the app first loads, determine whether we should be displaying the comments in a read-only form
		const readOnly = mw.config.get( 'wgComments' ).readOnly;

		const params = new URLSearchParams( window.location.search );

		// Determine whether we should only be showing a single comment
		const singleCommentId = params.get( 'comment' );
		if ( singleCommentId ) {
			this.$data.store.singleComment = singleCommentId;
		}

		// Filters
		let targetUser = params.get( 'user' );
		if ( targetUser ) {
			targetUser = targetUser.trim();
			this.$data.store.filterByUser = targetUser.charAt( 0 ).toUpperCase() + targetUser.slice( 1 );
		}

		this.$data.store.isReadOnly = readOnly;

		setInterval( () => {
			if ( self.$data.store.globalCooldown > 0 ) {
				self.$data.store.globalCooldown -= 1;
			}
		}, 1000 );

		Vue.nextTick( () => {
			this.$data.store.ready = true;
		} );
	}
} );
</script>
