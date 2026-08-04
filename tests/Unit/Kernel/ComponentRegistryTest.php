<?php

namespace Zoviz\Tests\Unit\Kernel;

use Brain\Monkey\Filters;
use Zoviz\Kernel\ComponentInterface;
use Zoviz\Kernel\ComponentRegistry;
use Zoviz\Kernel\Container;
use Zoviz\Tests\Unit\TestCase;

class ComponentRegistryTest extends TestCase {

	private function make_component( string $id ): ComponentInterface {
		return new class( $id ) implements ComponentInterface {
			private $component_id;

			public function __construct( string $component_id ) {
				$this->component_id = $component_id;
			}

			public function id() {
				return $this->component_id;
			}

			public function register( Container $container ) {}

			public function boot( Container $container ) {}
		};
	}

	public function test_all_returns_added_components_keyed_by_id() {
		$registry  = new ComponentRegistry();
		$component = $this->make_component( 'demo' );

		$registry->add( $component );

		$this->assertSame( array( 'demo' => $component ), $registry->all() );
	}

	public function test_adding_duplicate_id_throws() {
		$registry = new ComponentRegistry();
		$registry->add( $this->make_component( 'demo' ) );

		$this->expectException( \InvalidArgumentException::class );

		$registry->add( $this->make_component( 'demo' ) );
	}

	public function test_all_applies_the_zoviz_components_filter() {
		$registry = new ComponentRegistry();
		$injected = $this->make_component( 'injected' );

		Filters\expectApplied( 'zoviz_components' )
			->once()
			->andReturn( array( 'injected' => $injected ) );

		$this->assertSame( array( 'injected' => $injected ), $registry->all() );
	}

	public function test_all_discards_values_that_are_not_components() {
		$registry = new ComponentRegistry();
		$valid    = $this->make_component( 'valid' );

		Filters\expectApplied( 'zoviz_components' )
			->once()
			->andReturn(
				array(
					'valid'   => $valid,
					'garbage' => new \stdClass(),
					'scalar'  => 'nope',
				)
			);

		$this->assertSame( array( 'valid' => $valid ), $registry->all() );
	}
}
