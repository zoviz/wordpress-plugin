<?php
/**
 * Service registry.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Holds all available Developer API services. After the built-in services
 * are registered, the `zoviz_register_services` action lets third parties
 * add their own.
 */
final class ServiceRegistry {

	/**
	 * Registered services keyed by id.
	 *
	 * @var array<string, ServiceInterface>
	 */
	private $services = array();

	/**
	 * Registers a service.
	 *
	 * @param ServiceInterface $service The service.
	 * @return void
	 * @throws \InvalidArgumentException When the service id is already registered.
	 */
	public function register( ServiceInterface $service ) {
		$id = $service->id();

		if ( isset( $this->services[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Zoviz service "%s" is already registered.', esc_html( $id ) )
			);
		}

		$this->services[ $id ] = $service;
	}

	/**
	 * Returns a service by id.
	 *
	 * @param string $id Service id.
	 * @return ServiceInterface|null
	 */
	public function get( $id ) {
		return isset( $this->services[ $id ] ) ? $this->services[ $id ] : null;
	}

	/**
	 * Whether a service id exists.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->services[ $id ] );
	}

	/**
	 * All services keyed by id.
	 *
	 * @return array<string, ServiceInterface>
	 */
	public function all() {
		return $this->services;
	}

	/**
	 * All registered service ids.
	 *
	 * @return string[]
	 */
	public function ids() {
		return array_keys( $this->services );
	}
}
