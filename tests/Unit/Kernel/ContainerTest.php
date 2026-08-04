<?php

namespace Zoviz\Tests\Unit\Kernel;

use Zoviz\Kernel\Container;
use Zoviz\Tests\Unit\TestCase;

class ContainerTest extends TestCase {

	public function test_get_resolves_registered_factory() {
		$container = new Container();
		$container->set( 'answer', static fn() => 42 );

		$this->assertSame( 42, $container->get( 'answer' ) );
	}

	public function test_get_memoizes_the_resolved_value() {
		$container = new Container();
		$calls     = 0;

		$container->set(
			'service',
			static function () use ( &$calls ) {
				++$calls;
				return new \stdClass();
			}
		);

		$first  = $container->get( 'service' );
		$second = $container->get( 'service' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $calls );
	}

	public function test_factory_receives_the_container() {
		$container = new Container();
		$container->set( 'dep', static fn() => 'value' );
		$container->set( 'service', static fn( Container $c ) => $c->get( 'dep' ) . '!' );

		$this->assertSame( 'value!', $container->get( 'service' ) );
	}

	public function test_set_replaces_factory_and_clears_memoization() {
		$container = new Container();
		$container->set( 'service', static fn() => 'first' );
		$this->assertSame( 'first', $container->get( 'service' ) );

		$container->set( 'service', static fn() => 'second' );
		$this->assertSame( 'second', $container->get( 'service' ) );
	}

	public function test_has_reports_registration() {
		$container = new Container();

		$this->assertFalse( $container->has( 'service' ) );

		$container->set( 'service', static fn() => 1 );

		$this->assertTrue( $container->has( 'service' ) );
	}

	public function test_get_unknown_service_throws() {
		$container = new Container();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'unknown-service' );

		$container->get( 'unknown-service' );
	}
}
