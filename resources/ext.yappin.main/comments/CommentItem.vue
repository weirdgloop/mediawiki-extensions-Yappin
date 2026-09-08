<template>
	<div
		class="ext-comments-comment-item"
		:class="{ 'is-highlighted': parseInt( store.singleComment ) === comment.id }"
		:data-comment-id="comment.id"
		:data-deleted="comment.deleted !== null">
		<div>
			<comment-rating v-if="!comment.deleted" :comment="comment"></comment-rating>
			<div class="comment-body">
				<div class="comment-header">
					<div class="comment-author-wrapper">
						<a
							class="comment-author mw-userlink"
							:class="{ 'mw-tempuserlink': comment.user.temp }"
							:href="userPageLink">
							{{ comment.user.anon ? $i18n( 'yappin-anon' ) : comment.user.name }}
						</a>
						<div class="comment-info">
							<span
								class="comment-rating"
								:class="{
									'rating-positive': comment.rating > 0,
									'rating-negative': comment.rating < 0
								}"
							>{{ rating }}</span>
							&#183;
							<span class="comment-date" :title="comment.created">{{ date }}</span>
							<span
								v-if="comment.edited !== null"
								class="comment-edited"
								:title="comment.edited">  {{ $i18n( 'yappin-edited', editedDate ).text() }}</span>
							<span
								v-if="targetPage"
								class="comment-page"
							>
								&#183; <span v-i18n-html="targetPageMessage"></span>
							</span>
							<span
								v-if="!store.singleComment && store.isSpecialComments && comment.parent"
								class="comment-parent"
							>
								<span v-i18n-html="targetParentMessage" @click="handleParentTextClick"></span>
							</span>
						</div>
					</div>
					<div class="comment-actions">
						<comment-action
							v-if="!store.readOnly && !comment.deleted && comment.ours"
							class="comment-action-edit"
							:disabled="store.isEditing === comment.id"
							:icon="cdxIconEdit"
							:on-click="() => store.isEditing = comment.id"
							:title="$i18n( 'yappin-action-label-edit' ).text()"
						></comment-action>
						<comment-action
							v-if="!store.readOnly && ( comment.ours && comment.deleted === null ) || store.isMod"
							class="comment-action-delete"
							:icon="comment.deleted ? cdxIconRestore : cdxIconTrash"
							:on-click="deleteComment"
							:title="$i18n(
								comment.deleted ? 'yappin-action-label-undelete' : 'yappin-action-label-delete'
							).text()"
						></comment-action>
						<comment-action
							v-if="!comment.deleted"
							class="comment-action-link"
							:on-click="linkComment"
							:icon="cdxIconLink"
							:title="$i18n( 'yappin-action-label-link' ).text()"
						></comment-action>
					</div>
				</div>
				<edit-comment-input v-if="store.isEditing === comment.id" :comment="comment"></edit-comment-input>
				<div
					v-else
					class="comment-content"
					v-html="comment.html"></div>
				<div v-if="comment.children.length > 0" class="comment-children">
					<comment-item
						v-for="c in comment.children"
						:key="c.id"
						:comment="c"
						:parent-id="comment.id"
					></comment-item>
				</div>
				<new-comment-input
					v-if="!parentId"
					:parent-id="comment.id"
					:is-writing-comment="isWritingReply"
					:on-cancel="() => isWritingReply = false"
				></new-comment-input>
			</div>
		</div>
		<div class="comment-footer">
			<button
				v-if="!parentId && !isWritingReply && !comment.deleted && !comment.parent"
				class="comment-reply-button"
				@click="isWritingReply = true"
			>
				<cdx-icon
					:icon="cdxIconShare"
					dir="rtl"
					size="small"></cdx-icon>
				<span>{{ $i18n( 'yappin-post-placeholder-child' ) }}</span>
			</button>
			<a
				v-if="comment.numChildren > 0"
				:href="singleCommentLink"
			>
				{{ $i18n( 'yappin-view-replies', comment.numChildren ) }}
			</a>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const store = require( '../store.js' );
const Comment = require( '../comment.js' );
const CommentAction = require( './CommentAction.vue' );
const CommentRating = require( './CommentRating.vue' );
const NewCommentInput = require( './NewCommentInput.vue' );
const EditCommentInput = require( './EditCommentInput.vue' );
const { CdxIcon } = require( '../codex.js' );
const {
	cdxIconTrash, cdxIconLink, cdxIconEdit, cdxIconRestore, cdxIconShare
} = require( '../icons.json' );

const api = new mw.Rest();

const config = mw.config.get( [
	'wgServer'
] );

module.exports = exports = defineComponent( {
	name: 'CommentItem',
	components: {
		NewCommentInput,
		EditCommentInput,
		CommentAction,
		CommentRating,
		CdxIcon
	},
	props: {
		comment: Comment,
		parentId: {
			type: Number,
			default: null,
			required: false
		}
	},
	setup() {
		return {
			cdxIconTrash,
			cdxIconLink,
			cdxIconEdit,
			cdxIconRestore,
			cdxIconShare
		};
	},
	data() {
		return {
			store,
			isWritingReply: false
		};
	},
	computed: {
		rating() {
			return mw.message( 'yappin-rating',
				mw.language.convertNumber( this.comment.rating ),
				this.comment.rating
			);
		},
		date() {
			return moment( this.comment.created ).fromNow();
		},
		editedDate() {
			return moment( this.comment.edited ).fromNow();
		},
		userPageLink() {
			const title = new mw.Title( this.comment.user.name, 2 ); // 2 = User
			return title.getUrl();
		},
		/**
		 * @return {mw.Title|null}
		 */
		targetPage() {
			if ( this.comment.page && this.store.isSpecialComments ) {
				return new mw.Title( this.comment.page.title, this.comment.page.ns );
			}

			return null;
		},
		targetPageMessage() {
			return mw.message(
				'yappin-comment-page-link',
				this.targetPage.getUrl(),
				this.targetPage.getPrefixedText()
			);
		},
		targetParentMessage() {
			const url = new URL( this.targetPage.getUrl(), config.wgServer );
			url.searchParams.set( 'comment', this.comment.parent );

			return mw.message(
				'yappin-comment-parent-link',
				url
			);
		},
		singleCommentLink() {
			const url = new URL( this.targetPage ? this.targetPage.getUrl() : document.location, config.wgServer );
			url.searchParams.set( 'comment', this.comment.id );
			return url;
		}
	},
	methods: {
		deleteComment() {
			api.delete( `/comments/v0/comment/${ this.$props.comment.id }/edit`, {
				delete: !this.$props.comment.deleted
			} ).then( ( data ) => {
				this.$props.comment.deleted = data.deleted;
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
		},
		linkComment() {
			// eslint-disable-next-line compat/compat
			navigator.clipboard.writeText( this.singleCommentLink.href );
			mw.notify( mw.msg( 'yappin-action-link-copied' ), { tag: 'copy-comment' } );
		}
	},
	mounted() {
		if ( parseInt( this.store.singleComment ) === this.comment.id ) {
			this.$el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}
} );
</script>
