/**
 * Adds a Zoviz dropdown to the block toolbar whenever text is selected
 * inside a RichText field (a paragraph, heading, list item, etc.) — the
 * text-selection counterpart to the core/image toolbar dropdown in
 * `block-controls.js`. Reads the current text selection straight from the
 * block editor store — the same offsets that power the built-in
 * bold/italic/link toolbar — so the dropdown sits next to them instead of
 * being buried in RichText's generic "More" overflow. Each action opens the
 * sidebar preloaded with the selected text as the prompt; the toolbar itself
 * never submits a job.
 */
import {
	BlockControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { DropdownMenu, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { create } from '@wordpress/rich-text';

import { requestRun } from './state';

const QUICK_ACTIONS = [
	{
		service: 'image-generator-2',
		label: __( 'Generate Image', 'zoviz-ai-studio' ),
	},
];

/**
 * Reads the plain text currently selected within this block, if any.
 *
 * @param {string} clientId   The block's client id.
 * @param {Object} attributes The block's current attributes.
 * @return {string} The selected text, or an empty string.
 */
function useSelectedText( clientId, attributes ) {
	return useSelect(
		( select ) => {
			const { getSelectionStart, getSelectionEnd } =
				select( blockEditorStore );
			const start = getSelectionStart();
			const end = getSelectionEnd();

			if (
				! start ||
				! end ||
				start.clientId !== clientId ||
				end.clientId !== clientId ||
				! start.attributeKey ||
				start.attributeKey !== end.attributeKey ||
				start.offset === end.offset
			) {
				return '';
			}

			// Some blocks (e.g. core/paragraph) store this attribute as a
			// RichTextData instance rather than a plain string — coerce it
			// so create() always gets HTML text.
			const html = attributes[ start.attributeKey ];

			if ( ! html ) {
				return '';
			}

			const from = Math.min( start.offset, end.offset );
			const to = Math.max( start.offset, end.offset );

			return create( { html: html.toString() } )
				.text.slice( from, to )
				.trim();
		},
		[ clientId, attributes ]
	);
}

export const withZovizTextControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { clientId, isSelected, attributes } = props;
		const selectedText = useSelectedText( clientId, attributes || {} );

		if ( ! isSelected || ! selectedText ) {
			return <BlockEdit { ...props } />;
		}

		const run = ( serviceId ) => {
			requestRun( {
				serviceId,
				source: null,
				target: { type: 'after-block', clientId },
				values: { prompt: selectedText },
			} );
		};

		return (
			<>
				<BlockEdit { ...props } />
				<BlockControls group="inline">
					<ToolbarGroup>
						<DropdownMenu
							icon="art"
							label={ __( 'Zoviz AI Studio', 'zoviz-ai-studio' ) }
							controls={ QUICK_ACTIONS.map( ( action ) => ( {
								title: action.label,
								onClick: () => run( action.service ),
							} ) ) }
						/>
					</ToolbarGroup>
				</BlockControls>
			</>
		);
	},
	'withZovizTextControls'
);

addFilter(
	'editor.BlockEdit',
	'zoviz-ai-studio/text-controls',
	withZovizTextControls
);
