<?php

namespace Zoviz\Tests\Integration\Support;

use Zoviz\Infrastructure\Database\Schema;

/**
 * Base class for REST integration tests: boots a fresh WP_REST_Server,
 * fakes the Zoviz API, and provides users for the permission matrix.
 */
abstract class RestTestCase extends \WP_UnitTestCase {

	use FakesZovizApi;

	/** @var \WP_REST_Server */
	protected $server;

	/** @var int */
	protected $admin_id;

	/** @var int */
	protected $author_id;

	/** @var int */
	protected $subscriber_id;

	public function set_up() {
		parent::set_up();

		Schema::install();
		$this->fake_zoviz_api();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $this->server );

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author_id     = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	protected function request( string $method, string $route, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/zoviz/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}

	protected function store_key( string $label = 'Test', string $secret = 'zv_secret_test_1a2b' ): string {
		$keys = \Zoviz\Kernel\Plugin::instance()->container()->get( \Zoviz\DeveloperApi\Keys\KeyRepository::class );
		$key  = $keys->insert( $label, $secret );
		$keys->set_default( $key->id() );

		return $key->id();
	}

	protected function fixture_attachment(): int {
		return self::factory()->attachment->create_upload_object(
			dirname( __DIR__, 2 ) . '/Fixtures/result-1px.png'
		);
	}
}
