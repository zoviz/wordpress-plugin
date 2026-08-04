<?php
/**
 * Lightweight service container.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

/**
 * A minimal lazy service registry. Factories run once; the resolved value is
 * memoized. Intentionally tiny — no autowiring, no reflection.
 */
final class Container {

	/**
	 * Registered factories, keyed by service id.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Memoized resolved services, keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private $resolved = array();

	/**
	 * Registers a service factory.
	 *
	 * Registering an id again replaces the factory and clears the memoized
	 * instance; tests rely on this to swap implementations.
	 *
	 * @param string   $id      Service id.
	 * @param callable $factory Factory receiving this container, returning the service.
	 * @return void
	 */
	public function set( $id, callable $factory ) {
		$this->factories[ $id ] = $factory;
		unset( $this->resolved[ $id ] );
	}

	/**
	 * Resolves a service.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws \RuntimeException When the id is unknown.
	 */
	public function get( $id ) {
		if ( array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \RuntimeException(
				sprintf( 'Unknown service "%s" requested from the Zoviz container.', esc_html( $id ) )
			);
		}

		$this->resolved[ $id ] = call_user_func( $this->factories[ $id ], $this );

		return $this->resolved[ $id ];
	}

	/**
	 * Whether a service id is registered.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->resolved );
	}
}
