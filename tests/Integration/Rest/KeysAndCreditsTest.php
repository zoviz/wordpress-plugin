<?php

namespace Zoviz\Tests\Integration\Rest;

use Zoviz\Tests\Integration\Support\RestTestCase;

class KeysAndCreditsTest extends RestTestCase {

	public function test_keys_routes_require_admin() {
		wp_set_current_user( $this->author_id );

		$this->assertSame( 403, $this->request( 'GET', '/keys' )->get_status() );
		$this->assertSame(
			403,
			$this->request(
				'POST',
				'/keys',
				array(
					'label'  => 'X',
					'secret' => 'y',
				)
			)->get_status()
		);
	}

	public function test_create_key_validates_against_live_api() {
		wp_set_current_user( $this->admin_id );

		// Invalid key: API rejects, nothing stored.
		$this->queue_zoviz_json( 401, array( 'message' => 'Missing or invalid API key' ) );

		$response = $this->request(
			'POST',
			'/keys',
			array(
				'label'  => 'Bad key',
				'secret' => 'nope',
			)
		);

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'zoviz_invalid_api_key', $response->get_data()['code'] );
		$this->assertCount( 0, $this->request( 'GET', '/keys' )->get_data() );

		// Valid key: stored masked, promoted to default.
		$this->queue_zoviz_fixture( 200, 'credits-ok.json' );

		$response = $this->request(
			'POST',
			'/keys',
			array(
				'label'  => 'Main workspace',
				'secret' => 'zv_secret_test_1a2b',
			)
		);

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Main workspace', $data['label'] );
		$this->assertSame( '••••1a2b', $data['masked'] );
		$this->assertTrue( $data['is_default'] );
		$this->assertArrayNotHasKey( 'secret', $data );

		// The list never leaks secrets either.
		$list = $this->request( 'GET', '/keys' )->get_data();
		$this->assertCount( 1, $list );
		$this->assertArrayNotHasKey( 'secret', $list[0] );
	}

	public function test_newest_key_becomes_default_and_update_can_change_it() {
		wp_set_current_user( $this->admin_id );

		$this->queue_zoviz_fixture( 200, 'credits-ok.json' );
		$first = $this->request(
			'POST',
			'/keys',
			array(
				'label'  => 'First',
				'secret' => 'secret-one-1111',
			)
		)->get_data();

		$this->queue_zoviz_fixture( 200, 'credits-ok.json' );
		$second = $this->request(
			'POST',
			'/keys',
			array(
				'label'  => 'Second',
				'secret' => 'secret-two-2222',
			)
		)->get_data();

		$this->assertTrue( $second['is_default'] );

		// Manually promote the first key back to default.
		$updated = $this->request( 'PUT', '/keys/' . $first['id'], array( 'is_default' => true ) )->get_data();

		$this->assertTrue( $updated['is_default'] );

		// Deleting it re-points the default to the newest remaining key.
		$this->assertSame( 200, $this->request( 'DELETE', '/keys/' . $first['id'] )->get_status() );

		$list = $this->request( 'GET', '/keys' )->get_data();
		$this->assertCount( 1, $list );
		$this->assertTrue( $list[0]['is_default'] );
		$this->assertSame( $second['id'], $list[0]['id'] );
	}

	public function test_credits_for_default_key() {
		$this->store_key();
		wp_set_current_user( $this->author_id );

		$this->queue_zoviz_fixture( 200, 'credits-ok.json' );

		$response = $this->request( 'GET', '/credits' );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 1240, $data['credit'] );
		$this->assertSame( 24, $data['reserved_credit'] );
		$this->assertSame( '••••1a2b', $data['key']['masked'] );
	}

	public function test_credits_without_any_key_returns_404() {
		wp_set_current_user( $this->author_id );

		$response = $this->request( 'GET', '/credits' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'zoviz_no_api_key', $response->get_data()['code'] );
	}

	public function test_credits_uses_cache_between_requests() {
		$this->store_key();
		wp_set_current_user( $this->author_id );

		$this->queue_zoviz_fixture( 200, 'credits-ok.json' );
		$this->request( 'GET', '/credits' );

		$requests = count( $this->zoviz_requests );
		$this->request( 'GET', '/credits' );

		$this->assertCount( $requests, $this->zoviz_requests );
	}
}
