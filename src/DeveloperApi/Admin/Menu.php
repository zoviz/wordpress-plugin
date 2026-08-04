<?php
/**
 * Admin menu and page shells.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Admin;

use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Kernel\Assets;
use Zoviz\Kernel\Plugin;

/**
 * Registers the Zoviz AI Studio admin pages. Pages are thin shells — a
 * root div plus the built React bundle; all logic lives in REST + JS.
 */
final class Menu {

	/**
	 * Page slugs.
	 */
	const SLUG_WORKSPACE = Plugin::SLUG;
	const SLUG_JOBS      = Plugin::SLUG . '-jobs';
	const SLUG_SETTINGS  = Plugin::SLUG . '-settings';

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
	 * Hook suffix => entry name for enqueueing.
	 *
	 * @var array<string, string>
	 */
	private $entries = array();

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
	 * Wires the admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the menu and submenu pages.
	 *
	 * @return void
	 */
	public function add_pages() {
		$workspace = add_menu_page(
			__( 'Zoviz AI Studio', 'zoviz-ai-studio' ),
			__( 'Zoviz AI Studio', 'zoviz-ai-studio' ),
			'upload_files',
			self::SLUG_WORKSPACE,
			array( $this, 'render_workspace' ),
			'dashicons-art',
			59
		);

		add_submenu_page(
			self::SLUG_WORKSPACE,
			__( 'Workspace', 'zoviz-ai-studio' ),
			__( 'Workspace', 'zoviz-ai-studio' ),
			'upload_files',
			self::SLUG_WORKSPACE,
			array( $this, 'render_workspace' )
		);

		$jobs = add_submenu_page(
			self::SLUG_WORKSPACE,
			__( 'Jobs', 'zoviz-ai-studio' ),
			__( 'Jobs', 'zoviz-ai-studio' ),
			'upload_files',
			self::SLUG_JOBS,
			array( $this, 'render_jobs' )
		);

		$settings = add_submenu_page(
			self::SLUG_WORKSPACE,
			__( 'Zoviz Settings', 'zoviz-ai-studio' ),
			__( 'Settings', 'zoviz-ai-studio' ),
			'manage_options',
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);

		$this->entries = array(
			(string) $workspace => 'workspace',
			(string) $jobs      => 'jobs',
			(string) $settings  => 'settings',
		);
	}

	/**
	 * Enqueues the bundle for the current plugin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! isset( $this->entries[ $hook_suffix ] ) ) {
			return;
		}

		$entry = $this->entries[ $hook_suffix ];

		if ( 'workspace' === $entry ) {
			// The workspace offers "pick from Media Library".
			wp_enqueue_media();
		}

		$this->assets->enqueue( $entry );

		wp_add_inline_script(
			Plugin::SLUG . '-' . $entry,
			'window.zovizStudio = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * Data every admin app needs at boot. Never includes secrets.
	 *
	 * @return array<string, mixed>
	 */
	public function boot_data() {
		return array(
			'keyCount'     => $this->keys->count(),
			'defaultKeyId' => $this->keys->default_key_id(),
			'isAdmin'      => current_user_can( 'manage_options' ),
			'pricingUrl'   => 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
			'workspaceUrl' => admin_url( 'admin.php?page=' . self::SLUG_WORKSPACE ),
			'jobsUrl'      => admin_url( 'admin.php?page=' . self::SLUG_JOBS ),
			'settingsUrl'  => admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ),
		);
	}

	/**
	 * Workspace page shell.
	 *
	 * @return void
	 */
	public function render_workspace() {
		$this->render_shell( 'workspace', __( 'Zoviz AI Studio', 'zoviz-ai-studio' ) );
	}

	/**
	 * Jobs page shell.
	 *
	 * @return void
	 */
	public function render_jobs() {
		$this->render_shell( 'jobs', __( 'Zoviz Jobs', 'zoviz-ai-studio' ) );
	}

	/**
	 * Settings page shell.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->render_shell( 'settings', __( 'Zoviz Settings', 'zoviz-ai-studio' ) );
	}

	/**
	 * Prints the page shell.
	 *
	 * @param string $entry Entry name (root id suffix).
	 * @param string $title Page title.
	 * @return void
	 */
	private function render_shell( $entry, $title ) {
		printf(
			'<div class="wrap"><h1 class="screen-reader-text">%s</h1><div id="zoviz-%s-root"></div></div>',
			esc_html( $title ),
			esc_attr( $entry )
		);
	}
}
