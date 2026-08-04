<?php
/**
 * Component registry.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

/**
 * Holds the plugin components and exposes them through the
 * `zoviz_components` filter so third parties can add their own.
 */
final class ComponentRegistry {

	/**
	 * Registered components, keyed by component id.
	 *
	 * @var array<string, ComponentInterface>
	 */
	private $components = array();

	/**
	 * Adds a component.
	 *
	 * @param ComponentInterface $component The component.
	 * @return void
	 * @throws \InvalidArgumentException When a component id is already registered.
	 */
	public function add( ComponentInterface $component ) {
		$id = $component->id();

		if ( isset( $this->components[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Zoviz component "%s" is already registered.', esc_html( $id ) )
			);
		}

		$this->components[ $id ] = $component;
	}

	/**
	 * Returns all components after applying the `zoviz_components` filter.
	 *
	 * @return array<string, ComponentInterface>
	 */
	public function all() {
		/**
		 * Filters the registered plugin components.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, ComponentInterface> $components Components keyed by id.
		 */
		$components = apply_filters( 'zoviz_components', $this->components );

		return array_filter(
			(array) $components,
			static function ( $component ) {
				return $component instanceof ComponentInterface;
			}
		);
	}
}
