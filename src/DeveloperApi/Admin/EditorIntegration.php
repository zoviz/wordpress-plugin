<?php
/**
 * Block editor integration.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Admin;

use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Kernel\Assets;
use Zoviz\Kernel\Plugin;

/**
 * Enqueues the `editor` bundle for users who can process images. The bundle
 * registers a PluginSidebar plus a BlockEdit filter on core/image and a
 * PostFeaturedImage filter — all built from `@wordpress/editor` and
 * `@wordpress/block-editor` only (no `__experimental` APIs), so generated
 * results are always plain `core/image` blocks or a featured-image id that
 * survive the plugin being deactivated.
 */
final class EditorIntegration {

	/**
	 * Asset registrar.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Key repository (for boot data only — no secrets).
	 *
	 * @var KeyRepository
	 */
	private $keys;

	/**
	 * Constructor.
	 *
	 * @param Assets        $assets Asset registrar.
	 * @param KeyRepository $keys   Key repository.
	 */
	public function __construct( Assets $assets, KeyRepository $keys ) {
		$this->assets = $assets;
		$this->keys   = $keys;
	}

	/**
	 * Wires the hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the editor bundle for eligible users.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$this->assets->enqueue( 'editor' );

		wp_add_inline_script(
			Plugin::SLUG . '-editor',
			'window.zovizStudio = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * Data the editor app needs at boot. Never includes secrets.
	 *
	 * @return array<string, mixed>
	 */
	private function boot_data() {
		return array(
			'keyCount'     => $this->keys->count(),
			'defaultKeyId' => $this->keys->default_key_id(),
			'isAdmin'      => current_user_can( 'manage_options' ),
			'pricingUrl'   => 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
			'settingsUrl'  => admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
		);
	}
}
