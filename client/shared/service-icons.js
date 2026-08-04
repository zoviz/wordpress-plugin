/**
 * Dashicon per built-in service, purely decorative — used to give the
 * service navigation a scannable identity instead of a wall of text
 * links. A service id not in this map (e.g. a third-party one added via
 * `zoviz_register_services`) falls back to the plugin's own icon.
 */
export const DEFAULT_SERVICE_ICON = 'art';

export const SERVICE_ICONS = {
	'background-remover': 'images-alt2',
	'image-editor': 'edit',
	'image-generator-2': 'randomize',
	'image-upscaler': 'editor-expand',
	'object-remover': 'trash',
	'product-photography': 'camera-alt',
	'sketch-to-image': 'welcome-write-blog',
};

export function serviceIcon( id ) {
	return SERVICE_ICONS[ id ] || DEFAULT_SERVICE_ICON;
}
