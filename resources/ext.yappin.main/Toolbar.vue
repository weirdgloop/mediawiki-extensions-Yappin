<template>
	<div class="comment-list-toolbar">
		<h3 :style="{ visibility: store.isSpecialComments ? 'hidden' : null }">
			{{ $i18n( 'yappin-container-header' ).text() }}
		</h3>
		<div class="comment-list-options">
			<div class="comment-list-option-sort-method">
				<cdx-field>
					<cdx-select
						v-model:selected="store.sortMethod"
						:menu-items="SORT_OPTIONS"
						@update:selected="store.setSortMethod"
					></cdx-select>
					<template #label>
						{{ $i18n( 'yappin-sort-label' ).text() }}
					</template>
				</cdx-field>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxSelect, CdxField } = require( './codex.js' );
const store = require( './store.js' );
const { SORT_OPTIONS } = require( './util.js' );

module.exports = exports = defineComponent( {
	name: 'Toolbar',
	components: {
		CdxSelect,
		CdxField
	},
	setup() {
		return {
			SORT_OPTIONS
		};
	},
	data() {
		return {
			store
		};
	}
} );
</script>
