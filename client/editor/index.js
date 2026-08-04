/**
 * Block editor entry point: a PluginSidebar for running any service against
 * the post, a toolbar dropdown on core/image blocks, and quick actions on
 * the featured image panel. Everything it produces is a standard
 * core/image block or a featured-image id — no custom block types, so
 * content survives the plugin being deactivated.
 */
import { registerPlugin } from '@wordpress/plugins';

import '../shared/styles.scss';
import './block-controls';
import './featured-image';
import { ZovizEditorPlugin } from './sidebar';

registerPlugin( 'zoviz-ai-studio', {
	render: ZovizEditorPlugin,
	icon: 'art',
} );
