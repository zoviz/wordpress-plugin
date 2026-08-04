<?php
/**
 * Plugin kernel.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

use Zoviz\DeveloperApi\DeveloperApiComponent;

/**
 * The plugin kernel: owns the container, the component registry, and the
 * top-level lifecycle hooks. The kernel knows nothing about any specific
 * remote API — components bring their own policies.
 */
final class Plugin {

	/**
	 * Current plugin version. Kept in sync with the plugin header by the
	 * release automation (bin/bump-version.sh).
	 *
	 * @var string
	 */
	const VERSION = '0.1.0';

	/**
	 * Plugin slug. Single rename point together with the main file header;
	 * also used as the text domain and the REST namespace vendor prefix.
	 *
	 * @var string
	 */
	const SLUG = 'zoviz-ai-studio';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Component registry.
	 *
	 * @var ComponentRegistry
	 */
	private $components;

	/**
	 * Whether components have been registered and booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Boots the kernel. Called exactly once from the main plugin file.
	 *
	 * @param string $main_file Absolute path to the main plugin file.
	 * @return Plugin
	 */
	public static function boot( $main_file ) {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		self::$instance = new self( $main_file );
		self::$instance->register_lifecycle_hooks();

		return self::$instance;
	}

	/**
	 * Returns the booted kernel instance.
	 *
	 * @return Plugin
	 * @throws \RuntimeException When called before boot().
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			throw new \RuntimeException( 'Zoviz kernel accessed before boot().' );
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param string $main_file Absolute path to the main plugin file.
	 */
	private function __construct( $main_file ) {
		$this->file       = $main_file;
		$this->container  = new Container();
		$this->components = new ComponentRegistry();
	}

	/**
	 * Wires the kernel into WordPress.
	 *
	 * On the request that activates the plugin, `plugins_loaded` has already
	 * fired by the time the plugin file is included, so components are set up
	 * immediately in that case.
	 *
	 * @return void
	 */
	private function register_lifecycle_hooks() {
		if ( did_action( 'plugins_loaded' ) ) {
			$this->setup_components();
		} else {
			add_action( 'plugins_loaded', array( $this, 'setup_components' ), 5 );
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Registers built-in components, applies the `zoviz_components` filter,
	 * then registers container bindings and boots every component.
	 *
	 * @return void
	 */
	public function setup_components() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->components->add( new DeveloperApiComponent() );

		foreach ( $this->components->all() as $component ) {
			$component->register( $this->container );
		}

		foreach ( $this->components->all() as $component ) {
			$component->boot( $this->container );
		}

		/**
		 * Fires after all components have been registered and booted.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin The plugin kernel.
		 */
		do_action( 'zoviz_booted', $this );
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'zoviz-ai-studio',
			false,
			dirname( plugin_basename( $this->file ) ) . '/languages'
		);
	}

	/**
	 * Returns the service container.
	 *
	 * @return Container
	 */
	public function container() {
		return $this->container;
	}

	/**
	 * Returns the component registry.
	 *
	 * @return ComponentRegistry
	 */
	public function components() {
		return $this->components;
	}

	/**
	 * Returns the absolute path to the main plugin file.
	 *
	 * @return string
	 */
	public function file() {
		return $this->file;
	}

	/**
	 * Returns the plugin directory path with a trailing slash.
	 *
	 * @return string
	 */
	public function dir() {
		return plugin_dir_path( $this->file );
	}

	/**
	 * Returns the plugin directory URL with a trailing slash.
	 *
	 * @return string
	 */
	public function url() {
		return plugin_dir_url( $this->file );
	}
}
