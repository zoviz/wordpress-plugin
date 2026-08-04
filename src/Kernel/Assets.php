<?php
/**
 * Built asset registration.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

/**
 * Registers scripts and styles produced by @wordpress/scripts in build/.
 * Each entry ships a generated `<entry>.asset.php` describing its
 * dependencies (all @wordpress/* packages resolve to WordPress-bundled
 * handles) and a content-hash version.
 */
final class Assets {

	/**
	 * The plugin kernel.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin kernel.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers the script (and style, when present) for a build entry.
	 *
	 * @param string $entry Entry name, e.g. 'workspace'.
	 * @return string|null The script handle, or null when the build asset is missing.
	 */
	public function register( $entry ) {
		$handle     = Plugin::SLUG . '-' . $entry;
		$asset_file = $this->plugin->dir() . 'build/' . $entry . '.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return null;
		}

		$asset = require $asset_file;

		wp_register_script(
			$handle,
			$this->plugin->url() . 'build/' . $entry . '.js',
			isset( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : Plugin::VERSION,
			true
		);

		wp_set_script_translations(
			$handle,
			'zoviz-ai-studio',
			$this->plugin->dir() . 'languages'
		);

		$style_path = $this->plugin->dir() . 'build/' . $entry . '.css';

		if ( is_readable( $style_path ) ) {
			wp_register_style(
				$handle,
				$this->plugin->url() . 'build/' . $entry . '.css',
				array( 'wp-components' ),
				isset( $asset['version'] ) ? $asset['version'] : Plugin::VERSION
			);
		}

		return $handle;
	}

	/**
	 * Registers and immediately enqueues a build entry.
	 *
	 * @param string $entry Entry name, e.g. 'workspace'.
	 * @return void
	 */
	public function enqueue( $entry ) {
		$handle = $this->register( $entry );

		if ( null === $handle ) {
			return;
		}

		wp_enqueue_script( $handle );

		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		}
	}
}
