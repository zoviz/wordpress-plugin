<?php

namespace Zoviz\Tests\Unit\Keys;

use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Exception\AuthException;
use Zoviz\DeveloperApi\Keys\KeyManager;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Infrastructure\Crypto\Encryptor;
use Zoviz\Tests\Unit\Support\FixtureTransport;
use Zoviz\Tests\Unit\Support\StubsWordPressState;
use Zoviz\Tests\Unit\TestCase;

class KeyManagerTest extends TestCase {

	use StubsWordPressState;

	/** @var FixtureTransport */
	private $transport;

	/** @var KeyRepository */
	private $repository;

	/** @var KeyManager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();
		$this->stub_wordpress_state();

		$this->transport  = new FixtureTransport();
		$this->repository = new KeyRepository( new Encryptor() );
		$this->manager    = new KeyManager(
			new ApiClient( $this->transport ),
			$this->repository
		);
	}

	public function test_add_key_validates_against_live_api_before_persisting() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );

		$key = $this->manager->add_key( 'Main workspace', ' zv_secret_1a2b ' );

		// The validation call used the trimmed candidate secret.
		$this->assertSame( 'Bearer zv_secret_1a2b', $this->transport->last_request()['headers']['Authorization'] );

		// Persisted and promoted to default.
		$this->assertSame( 1, $this->repository->count() );
		$this->assertSame( $key->id(), $this->repository->default_key_id() );

		// Credits were cached from the validation response.
		$this->assertSame( 1240, $this->transients[ 'zoviz_credits_' . $key->id() ]['credit'] );
	}

	public function test_add_key_rejects_invalid_key_and_stores_nothing() {
		$this->transport->queue_json( 401, array( 'message' => 'Missing or invalid API key' ) );

		try {
			$this->manager->add_key( 'Bad', 'nope' );
			$this->fail( 'Expected AuthException.' );
		} catch ( AuthException $e ) {
			$this->assertSame( 0, $this->repository->count() );
			$this->assertSame( '', $this->repository->default_key_id() );
		}
	}

	public function test_newest_key_becomes_default() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );
		$this->transport->queue_fixture( 200, 'credits-ok.json' );

		$first  = $this->manager->add_key( 'First', 'secret-one-1111' );
		$second = $this->manager->add_key( 'Second', 'secret-two-2222' );

		$this->assertSame( $second->id(), $this->repository->default_key_id() );
		$this->assertNotSame( $first->id(), $this->repository->default_key_id() );
	}

	public function test_credits_for_uses_cache_until_forced() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );
		$key = $this->manager->add_key( 'Main', 'zv_secret_1a2b' );

		// Cache hit: no new HTTP request.
		$requests_before = count( $this->transport->requests );
		$credits         = $this->manager->credits_for( $key->id() );

		$this->assertSame( 1240, $credits->credit() );
		$this->assertCount( $requests_before, $this->transport->requests );

		// Forced refresh performs a request.
		$this->transport->queue_json(
			200,
			array(
				'credit'          => 5,
				'reserved_credit' => 0,
			)
		);

		$refreshed = $this->manager->credits_for( $key->id(), true );

		$this->assertSame( 5, $refreshed->credit() );
		$this->assertCount( $requests_before + 1, $this->transport->requests );
	}

	public function test_credits_for_marks_key_invalid_on_auth_failure() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );
		$key = $this->manager->add_key( 'Main', 'zv_secret_1a2b' );

		$this->transport->queue_json( 401, array( 'message' => 'Missing or invalid API key' ) );

		try {
			$this->manager->credits_for( $key->id(), true );
			$this->fail( 'Expected AuthException.' );
		} catch ( AuthException $e ) {
			$row = $this->options[ KeyRepository::OPTION_KEYS ][0];
			$this->assertFalse( $row['is_valid'] );
			$this->assertArrayNotHasKey( 'zoviz_credits_' . $key->id(), $this->transients );
		}
	}

	public function test_delete_key_clears_cache_and_repoints_default() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );
		$this->transport->queue_fixture( 200, 'credits-ok.json' );

		$first  = $this->manager->add_key( 'First', 'secret-one-1111' );
		$second = $this->manager->add_key( 'Second', 'secret-two-2222' );

		$this->assertTrue( $this->manager->delete_key( $second->id() ) );
		$this->assertSame( $first->id(), $this->repository->default_key_id() );
		$this->assertArrayNotHasKey( 'zoviz_credits_' . $second->id(), $this->transients );
	}
}
