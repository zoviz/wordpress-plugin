<?php

namespace Zoviz\Tests\Unit\Kernel;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Zoviz\Kernel\Activation;
use Zoviz\Kernel\Plugin;
use Zoviz\Tests\Unit\TestCase;

class ActivationTest extends TestCase {

	public function test_activate_stores_version_and_fires_hook() {
		Functions\expect( 'update_option' )
			->once()
			->with( 'zoviz_activated_version', Plugin::VERSION, false );

		Actions\expectDone( 'zoviz_activate' )->once();

		Activation::activate();
	}

	public function test_deactivate_fires_hook() {
		Actions\expectDone( 'zoviz_deactivate' )->once();

		Activation::deactivate();
	}
}
