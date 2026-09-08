<template>
	<div class="ext-comments-comments-list">
		<div
			v-if="store.singleComment !== null && initialLoadCompleted && store.comments.length"
			class="comment-info-full"
		>
			<span>{{ $i18n( 'yappin-single-mode-banner' ) }}</span>
			&#183;
			<a :href="urlWithoutComment">{{ $i18n( 'yappin-viewall' ) }}</a>
		</div>
		<div
			v-else-if="store.filterByUser"
			class="comment-info-full"
		>
			<span>{{ $i18n( 'yappin-user-filter-banner', store.filterByUser ) }}</span>
			&#183;
			<a :href="urlWithoutUser">{{ $i18n( 'yappin-viewall' ) }}</a>
		</div>
		<comment-item
			v-for="c in store.comments"
			:key="c.id"
			:comment="c"
		></comment-item>
		<button
			v-if="moreContinue && !loading"
			:disabled="loading"
			class="comment-info-full"
			@click="loadComments"
		>
			{{ $i18n( 'yappin-continue' ).text() }}
		</button>
		<div
			v-if="loading"
			class="comment-info-full"
		>
			{{ $i18n( 'yappin-loading' ).text() }}
		</div>
		<div
			v-else-if="error"
			class="mw-message-box mw-message-box-error"
		>
			{{ $i18n( 'yappin-load-error', error ).text() }}
		</div>
		<div
			v-else-if="initialLoadCompleted && !store.comments.length"
			class="comment-info-full"
		>
			{{ $i18n( 'yappin-empty' ).text() }}
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { isElementInView } = require( './util.js' );
const store = require( './store.js' );
const Comment = require( './comment.js' );
const CommentItem = require( './comments/CommentItem.vue' );

const api = new mw.Rest();

const config = mw.config.get( [
	'wgArticleId',
	'wgComments'
] );

module.exports = exports = defineComponent( {
	name: 'CommentsList',
	components: {
		CommentItem
	},
	data() {
		return {
			store,
			// For tracking whether this component has ever been scrolled into view
			elementSeen: false,
			// Whether the first API call to get comments has been completed
			initialLoadCompleted: false,
			// Whether we are currently making a request and therefore should be in a loading state
			loading: false,
			moreContinue: null,
			error: null
		};
	},
	computed: {
		urlWithoutComment() {
			const url = new URL( document.location );
			url.searchParams.delete( 'comment' );
			return url;
		},
		urlWithoutUser() {
			const url = new URL( document.location );
			url.searchParams.delete( 'user' );
			return url;
		}
	},
	methods: {
		resetComments() {
			this.$data.store.comments = [];
			this.$data.moreContinue = null;
			this.loadComments();
		},
		loadComments() {
			if ( !this.store.ready ) {
				return;
			}
			this.$data.error = null;

			if ( this.$data.store.singleComment ) {
				// Attempt to get the requested comment so that we can display it
				api.get(`/comments/v0/comment/${ this.$data.store.singleComment }?sort=${ this.$data.store.sortMethod }` )
					.done( ( res ) => {
						const comment = new Comment( res.comment );
						if ( ( comment.page && comment.page.id === config.wgArticleId ) || this.$data.store.isSpecialComments ) {
							this.$data.store.comments = [ comment ];
							this.$data.store.isMod = res.isMod;
						}
					} )
					.fail( ( _, data ) => {
						if ( data && data.xhr && data.xhr.status ) {
							this.$data.error = data.xhr.status;
						} else {
							this.$data.error = true;
						}
					} )
					.always( () => {
						this.$data.initialLoadCompleted = true;
					} )
			} else {
				// Get a list of all comments for the current page
				const qsp = new URLSearchParams( {
					limit: config.wgComments.resultsPerPage,
					sort: this.$data.store.sortMethod,
					user: this.$data.store.filterByUser || ''
				} );
				if ( this.$data.moreContinue ) {
					qsp.set( 'continue', this.$data.moreContinue );
				}

				this.$data.loading = true;

				let path;
				if ( this.$data.store.isSpecialComments ) {
					path = `/comments/v0/all?${ qsp.toString() }`;
				} else {
					path = `/comments/v0/page/${ config.wgArticleId }?${ qsp.toString() }`;
				}

				api.get( path )
					.done( ( res ) => {
						const comments = [];
						for ( const data of res.comments ) {
							comments.push( new Comment( data ) );
						}
						this.$data.store.comments = this.$data.store.comments.concat( comments );
						this.$data.store.isMod = res.isMod;
						this.$data.moreContinue = res.query.continue;
					} )
					.fail( ( _, data ) => {
						if ( data && data.xhr && data.xhr.status ) {
							this.$data.error = data.xhr.status;
						} else {
							this.$data.error = true;
						}
					} )
					.always( () => {
						if ( this.$data.initialLoadCompleted !== true ) {
							this.$data.initialLoadCompleted = true;
						}
						this.$data.loading = false;
					} )
			}
		},
		checkVisible() {
			// If URL params specify a comment we want to see, then always load comment list
			const shouldLoad = isElementInView( this.$el ) || this.store.singleComment;
			if ( shouldLoad && this.$data.store.ready && !this.$data.elementSeen ) {
				this.$data.elementSeen = true;
				this.loadComments();
			}
		}
	},
	watch: {
		'store.sortMethod': {
			immediate: false,
			handler() {
				// When the sort method changes, reset the list and make a request again
				this.resetComments();
			}
		},
		'store.ready': function( val ) {
			if ( val === true ) {
				this.checkVisible();
			}
		},
		'store.singleComment': {
			immediate: false,
			handler() {
				this.resetComments();
			}
		},
		'store.filterByUser': {
			immediate: false,
			handler() {
				this.resetComments();
			}
		}
	},
	mounted() {
		$( window ).on( 'DOMContentLoaded load resize scroll', this.checkVisible );
	}
} );
</script>
