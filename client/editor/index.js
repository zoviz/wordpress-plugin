/**
 * Block editor entry point: a PluginSidebar for running any service against
 * the post, a toolbar dropdown on core/image blocks, quick actions on the
 * featured image panel, and a "Generate Image" button that appears in the
 * block toolbar whenever text is selected. Everything it produces is a
 * standard core/image block or a featured-image id — no custom block types,
 * so content survives the plugin being deactivated.
 */
import { registerPlugin } from '@wordpress/plugins';

import '../shared/styles.scss';
import './block-controls';
import './featured-image';
import './text-controls';
import { ZovizEditorPlugin } from './sidebar';

registerPlugin( 'zoviz-ai-studio', {
	render: ZovizEditorPlugin,
	icon: 'art',
} );
