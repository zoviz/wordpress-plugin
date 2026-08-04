<?php
/**
 * Plugin Name:       Zoviz AI Studio
 * Plugin URI:        https://github.com/zoviz/wordpress-plugin
 * Description:       Official Zoviz plugin. Generate, edit, and enhance images with Zoviz AI services — background removal, image generation, upscaling, object removal, product photography, and more.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Zoviz
 * Author URI:        https://zoviz.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zoviz-ai-studio
 * Domain Path:       /languages
 *
 * @package Zoviz
 */

defined( 'ABSPATH' ) || exit;

// The "Requires PHP" header stops activation on modern WordPress; this guard
// protects sites where that header is not enforced.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Zoviz AI Studio requires PHP 7.4 or newer. The plugin is inactive until PHP is upgraded.', 'zoviz-ai-studio' )
			);
		}
	);
	return;
}

define( 'ZOVIZ_AI_STUDIO_FILE', __FILE__ );

// Composer autoloader when available (development, built release packages),
// with a minimal PSR-4 fallback so a plain checkout still runs.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'Zoviz\\' ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( 'Zoviz\\' ) );
			$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \Zoviz\Kernel\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Zoviz\Kernel\Activation::class, 'deactivate' ) );

\Zoviz\Kernel\Plugin::boot( __FILE__ );
