<?php
/**
 * WooCommerce integration.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Admin;

use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Kernel\Assets;
use Zoviz\Kernel\Plugin;

/**
 * Everything WooCommerce-specific: HPOS compatibility declaration and the
 * product side metabox. Every touchpoint is feature-detected — the plugin
 * is fully functional with WooCommerce absent, and this class is never
 * registered unless it's active (see DeveloperApiComponent::boot()).
 */
final class WooCommerceIntegration {

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
	 * Wires the hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Declares compatibility with WooCommerce's High-Performance Order
	 * Storage. The plugin never touches order data, but every extension is
	 * expected to declare a position either way.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		// Referenced by string, not `use`, so WooCommerce stays an entirely
		// optional dependency for static analysis as well as at runtime.
		$features_util = '\Automattic\WooCommerce\Utilities\FeaturesUtil';

		if ( class_exists( $features_util ) ) {
			$features_util::declare_compatibility( 'custom_order_tables', Plugin::instance()->file(), true );
		}
	}

	/**
	 * Adds the "Zoviz AI Studio" side metabox on the product editor.
	 *
	 * @return void
	 */
	public function add_metabox() {
		add_meta_box(
			'zoviz-ai-studio',
			__( 'Zoviz AI Studio', 'zoviz-ai-studio' ),
			array( $this, 'render_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Renders the metabox shell.
	 *
	 * @return void
	 */
	public function render_metabox() {
		echo '<div id="zoviz-woocommerce-root"></div>';
	}

	/**
	 * Enqueues the bundle on the product editor screen only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		global $post;

		$this->assets->enqueue( 'woocommerce' );

		wp_add_inline_script(
			Plugin::SLUG . '-woocommerce',
			'window.zovizStudio = ' . wp_json_encode( $this->boot_data( $post ) ) . ';',
			'before'
		);
	}

	/**
	 * Data the product app needs at boot. Never includes secrets.
	 *
	 * @param \WP_Post|null $post The product post.
	 * @return array<string, mixed>
	 */
	private function boot_data( $post ) {
		$product = ( $post && function_exists( 'wc_get_product' ) ) ? wc_get_product( $post ) : null;

		return array(
			'keyCount'     => $this->keys->count(),
			'defaultKeyId' => $this->keys->default_key_id(),
			'isAdmin'      => current_user_can( 'manage_options' ),
			'pricingUrl'   => 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
			'settingsUrl'  => admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
			'product'      => array(
				'id'         => $post ? (int) $post->ID : 0,
				'title'      => $post ? get_the_title( $post ) : '',
				'imageId'    => $product ? (int) $product->get_image_id() : 0,
				'imageUrl'   => $product && $product->get_image_id()
					? (string) wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' )
					: '',
				'galleryIds' => $product ? array_map( 'intval', $product->get_gallery_image_ids() ) : array(),
			),
		);
	}
}
