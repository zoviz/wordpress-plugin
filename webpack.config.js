/**
 * Extends the @wordpress/scripts webpack config with one entry per admin
 * surface. Each entry produces build/<name>.js plus build/<name>.asset.php;
 * the DependencyExtraction plugin (part of the default config) externalizes
 * all @wordpress/* packages and React to WordPress-bundled handles.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		workspace: path.resolve( __dirname, 'client/workspace/index.js' ),
		settings: path.resolve( __dirname, 'client/settings/index.js' ),
		jobs: path.resolve( __dirname, 'client/jobs/index.js' ),
		editor: path.resolve( __dirname, 'client/editor/index.js' ),
		media: path.resolve( __dirname, 'client/media/index.js' ),
		woocommerce: path.resolve( __dirname, 'client/woocommerce/index.js' ),
	},
};
