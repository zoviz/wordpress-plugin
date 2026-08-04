<?php
/**
 * Developer API component.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi;

use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Keys\KeyManager;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\DeveloperApi\Services\BackgroundRemoverService;
use Zoviz\DeveloperApi\Services\ImageEditorService;
use Zoviz\DeveloperApi\Services\ImageGenerator2Service;
use Zoviz\DeveloperApi\Services\ImageUpscalerService;
use Zoviz\DeveloperApi\Services\ObjectRemoverService;
use Zoviz\DeveloperApi\Services\ProductPhotographyService;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\DeveloperApi\Services\SketchToImageService;
use Zoviz\Infrastructure\Crypto\Encryptor;
use Zoviz\Infrastructure\Http\HttpTransport;
use Zoviz\Infrastructure\Http\WpHttpTransport;
use Zoviz\Kernel\ComponentInterface;
use Zoviz\Kernel\Container;

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
	}

	/**
	 * Wires WordPress hooks.
	 *
	 * @param Container $container The service container.
	 * @return void
	 */
	public function boot( Container $container ) {
		// Hooks arrive with the REST layer and admin surfaces.
	}
}
