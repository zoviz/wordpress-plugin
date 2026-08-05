<?php
/**
 * Developer API component.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi;

use Zoviz\DeveloperApi\Admin\EditorIntegration;
use Zoviz\DeveloperApi\Admin\Menu;
use Zoviz\DeveloperApi\Admin\MediaLibraryIntegration;
use Zoviz\DeveloperApi\Admin\Notices;
use Zoviz\DeveloperApi\Admin\WooCommerceIntegration;
use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Jobs\JobManager;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Jobs\JobSweeper;
use Zoviz\DeveloperApi\Keys\KeyManager;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\DeveloperApi\Media\MediaImporter;
use Zoviz\DeveloperApi\Rest\CreditsController;
use Zoviz\DeveloperApi\Rest\JobsController;
use Zoviz\DeveloperApi\Rest\KeysController;
use Zoviz\DeveloperApi\Rest\NoticesController;
use Zoviz\DeveloperApi\Rest\ServicesController;
use Zoviz\DeveloperApi\Rest\SettingsController;
use Zoviz\DeveloperApi\Services\BackgroundRemoverService;
use Zoviz\DeveloperApi\Services\ImageEditorService;
use Zoviz\DeveloperApi\Services\ImageGenerator2Service;
use Zoviz\DeveloperApi\Services\ImageUpscalerService;
use Zoviz\DeveloperApi\Services\ObjectRemoverService;
use Zoviz\DeveloperApi\Services\ProductPhotographyService;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\DeveloperApi\Services\SketchToImageService;
use Zoviz\Infrastructure\Crypto\Encryptor;
use Zoviz\Infrastructure\Database\Schema;
use Zoviz\Infrastructure\Http\HttpTransport;
use Zoviz\Infrastructure\Http\WpHttpTransport;
use Zoviz\Kernel\Assets;
use Zoviz\Kernel\ComponentInterface;
use Zoviz\Kernel\Container;
use Zoviz\Kernel\Plugin;

/**
 * Integrates the Zoviz Developer API (developer.zoviz.com): API keys,
 * credits, the seven image services, jobs, and every admin surface built on
 * them. This class is the component's composition root — the only class the
 * kernel knows about.
 */
final class DeveloperApiComponent implements ComponentInterface {

	/**
	 * Component id.
	 *
	 * @return string
	 */
	public function id() {
		return 'developer-api';
	}

	/**
	 * Registers container bindings.
	 *
	 * @param Container $container The service container.
	 * @return void
	 */
	public function register( Container $container ) {
		$container->set(
			Encryptor::class,
			static function () {
				return new Encryptor();
			}
		);

		$container->set(
			HttpTransport::class,
			static function () {
				return new WpHttpTransport();
			}
		);

		$container->set(
			ApiClient::class,
			static function ( Container $c ) {
				/**
				 * Filters the Zoviz Developer API base URL (staging, tests).
				 *
				 * @since 0.1.0
				 *
				 * @param string $base_url Base URL without trailing slash.
				 */
				$base_url = apply_filters( 'zoviz_api_base_url', 'https://developer.zoviz.com' );

				return new ApiClient( $c->get( HttpTransport::class ), $base_url );
			}
		);

		$container->set(
			ServiceRegistry::class,
			static function () {
				$registry = new ServiceRegistry();

				$registry->register( new BackgroundRemoverService() );
				$registry->register( new ImageEditorService() );
				$registry->register( new ImageGenerator2Service() );
				$registry->register( new ImageUpscalerService() );
				$registry->register( new ObjectRemoverService() );
				$registry->register( new ProductPhotographyService() );
				$registry->register( new SketchToImageService() );

				/**
				 * Fires after the built-in services are registered so third
				 * parties can register additional Developer API services.
				 *
				 * @since 0.1.0
				 *
				 * @param ServiceRegistry $registry The service registry.
				 */
				do_action( 'zoviz_register_services', $registry );

				return $registry;
			}
		);

		$container->set(
			KeyRepository::class,
			static function ( Container $c ) {
				return new KeyRepository( $c->get( Encryptor::class ) );
			}
		);

		$container->set(
			KeyManager::class,
			static function ( Container $c ) {
				return new KeyManager( $c->get( ApiClient::class ), $c->get( KeyRepository::class ) );
			}
		);

		$container->set(
			Settings::class,
			static function () {
				return new Settings();
			}
		);

		$container->set(
			JobRepository::class,
			static function () {
				return new JobRepository();
			}
		);

		$container->set(
			MediaImporter::class,
			static function () {
				return new MediaImporter();
			}
		);

		$container->set(
			JobManager::class,
			static function ( Container $c ) {
				return new JobManager(
					$c->get( ApiClient::class ),
					$c->get( JobRepository::class ),
					$c->get( KeyRepository::class ),
					$c->get( ServiceRegistry::class ),
					$c->get( MediaImporter::class ),
					$c->get( Settings::class )
				);
			}
		);

		$container->set(
			JobSweeper::class,
			static function ( Container $c ) {
				return new JobSweeper(
					$c->get( JobManager::class ),
					$c->get( JobRepository::class ),
					$c->get( Settings::class )
				);
			}
		);

		$container->set(
			Assets::class,
			static function () {
				return new Assets( Plugin::instance() );
			}
		);

		$container->set(
			Menu::class,
			static function ( Container $c ) {
				return new Menu( $c->get( Assets::class ), $c->get( KeyRepository::class ) );
			}
		);

		$container->set(
			Notices::class,
			static function ( Container $c ) {
				return new Notices( $c->get( KeyRepository::class ) );
			}
		);

		$container->set(
			MediaLibraryIntegration::class,
			static function ( Container $c ) {
				return new MediaLibraryIntegration(
					$c->get( ServiceRegistry::class ),
					$c->get( JobRepository::class ),
					$c->get( Assets::class )
				);
			}
		);

		$container->set(
			EditorIntegration::class,
			static function ( Container $c ) {
				return new EditorIntegration( $c->get( Assets::class ), $c->get( KeyRepository::class ) );
			}
		);

		$container->set(
			WooCommerceIntegration::class,
			static function ( Container $c ) {
				return new WooCommerceIntegration( $c->get( Assets::class ), $c->get( KeyRepository::class ) );
			}
		);
	}

	/**
	 * Wires WordPress hooks.
	 *
	 * @param Container $container The service container.
	 * @return void
	 */
	public function boot( Container $container ) {
		// Schema lifecycle. JobSweeper no longer schedules real WP-Cron
		// events (see JobSweeper::maybe_run()); clearing legacy ones on
		// both ends covers fresh installs and in-place upgrades alike.
		add_action(
			'zoviz_activate',
			static function () {
				Schema::install();
				JobSweeper::unschedule_legacy_cron();
			}
		);
		add_action( 'zoviz_deactivate', array( JobSweeper::class, 'unschedule_legacy_cron' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( Schema::class, 'maybe_upgrade' ) );

			// Cron sweeper (backstop for jobs the browser stopped
			// polling). Deliberately admin_init, not real WP-Cron: this
			// is what keeps it from ever running off public traffic.
			add_action( 'admin_init', array( JobSweeper::class, 'maybe_run' ) );
			add_action(
				JobSweeper::HOOK_SWEEP,
				static function () use ( $container ) {
					$container->get( JobSweeper::class )->sweep();
				}
			);
			add_action(
				JobSweeper::HOOK_PRUNE,
				static function () use ( $container ) {
					$container->get( JobSweeper::class )->prune();
				}
			);

			$container->get( Menu::class )->register();
			$container->get( Notices::class )->register();
			$container->get( MediaLibraryIntegration::class )->register();
			$container->get( EditorIntegration::class )->register();

			if ( class_exists( 'WooCommerce' ) ) {
				$container->get( WooCommerceIntegration::class )->register();
			}
		}//end if

		// REST proxy: the browser never sees API keys.
		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$controllers = array(
					new JobsController(
						$container->get( JobManager::class ),
						$container->get( JobRepository::class ),
						$container->get( ServiceRegistry::class )
					),
					new KeysController( $container->get( KeyManager::class ), $container->get( KeyRepository::class ) ),
					new CreditsController( $container->get( KeyManager::class ), $container->get( KeyRepository::class ) ),
					new ServicesController( $container->get( ServiceRegistry::class ) ),
					new SettingsController( $container->get( Settings::class ) ),
					new NoticesController(),
				);

				foreach ( $controllers as $controller ) {
					$controller->register_routes();
				}
			}
		);
	}
}
