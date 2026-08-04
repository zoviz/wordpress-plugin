/**
 * Adds Zoviz quick actions under the featured image panel: generate one
 * from scratch, or process the current featured image. Opens the sidebar
 * the same way the block toolbar does.
 */
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { requestRun } from './state';

const QUICK_ACTIONS = [
	{
		service: 'image-generator-2',
		label: __( 'Generate', 'zoviz-ai-studio' ),
	},
	{
		service: 'background-remover',
		label: __( 'Remove Background', 'zoviz-ai-studio' ),
		needsMedia: true,
	},
	{
		service: 'image-upscaler',
		label: __( 'Upscale', 'zoviz-ai-studio' ),
		needsMedia: true,
	},
];

export const withZovizFeaturedImageActions = createHigherOrderComponent(
	( PostFeaturedImage ) => ( props ) => {
		const media = useSelect( ( select ) => {
			const id =
				select( editorStore ).getEditedPostAttribute(
					'featured_media'
				);

			return id ? select( 'core' ).getMedia( id ) : null;
		}, [] );

		const run = ( serviceId ) => {
			requestRun( {
				serviceId,
				source: media
					? {
							attachmentId: media.id,
							url: media.source_url,
							title: '',
					  }
					: null,
				target: { type: 'featured' },
			} );
		};

		return (
			<>
				<PostFeaturedImage { ...props } />
				<div className="zoviz-featured-image-actions">
					{ QUICK_ACTIONS.filter(
						( action ) => ! action.needsMedia || media
					).map( ( action ) => (
						<Button
							key={ action.service }
							variant="link"
							onClick={ () => run( action.service ) }
						>
							{ action.label }
						</Button>
					) ) }
				</div>
			</>
		);
	},
	'withZovizFeaturedImageActions'
);

addFilter(
	'editor.PostFeaturedImage',
	'zoviz-ai-studio/featured-image-actions',
	withZovizFeaturedImageActions
);
