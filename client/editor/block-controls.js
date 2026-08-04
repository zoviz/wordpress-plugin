/**
 * Adds a Zoviz dropdown to the core/image block toolbar. Each action opens
 * the sidebar preloaded with this image so the user can fill in whatever
 * else the service needs (a mask, a prompt) before running — the toolbar
 * itself never submits a job.
 */
import { BlockControls } from '@wordpress/block-editor';
import { DropdownMenu, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import { requestRun } from './state';

const QUICK_ACTIONS = [
	{ service: 'image-editor', label: __( 'Edit', 'zoviz-ai-studio' ) },
	{
		service: 'background-remover',
		label: __( 'Remove Background', 'zoviz-ai-studio' ),
	},
	{
		service: 'object-remover',
		label: __( 'Remove Object', 'zoviz-ai-studio' ),
	},
	{ service: 'image-upscaler', label: __( 'Upscale', 'zoviz-ai-studio' ) },
	{
		service: 'product-photography',
		label: __( 'Product Photography', 'zoviz-ai-studio' ),
	},
];

export const withZovizBlockControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { url, id } = props.attributes || {};

		if ( 'core/image' !== props.name || ! props.isSelected || ! url ) {
			return <BlockEdit { ...props } />;
		}

		const run = ( serviceId ) => {
			requestRun( {
				serviceId,
				source: { attachmentId: id || 0, url, title: '' },
				target: { type: 'block', clientId: props.clientId },
			} );
		};

		return (
			<>
				<BlockEdit { ...props } />
				<BlockControls>
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
	'withZovizBlockControls'
);

addFilter(
	'editor.BlockEdit',
	'zoviz-ai-studio/block-controls',
	withZovizBlockControls
);
