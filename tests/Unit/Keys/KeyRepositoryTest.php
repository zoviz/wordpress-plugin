<?php

namespace Zoviz\Tests\Unit\Keys;

use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Infrastructure\Crypto\Encryptor;
use Zoviz\Tests\Unit\Support\StubsWordPressState;
use Zoviz\Tests\Unit\TestCase;

class KeyRepositoryTest extends TestCase {

	use StubsWordPressState;

	/** @var KeyRepository */
	private $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->stub_wordpress_state();
		$this->repository = new KeyRepository( new Encryptor() );
	}

	public function test_insert_stores_encrypted_secret() {
		$key = $this->repository->insert( 'Main workspace', 'zv_secret_1a2b' );

		$this->assertStringStartsWith( 'k_', $key->id() );
		$this->assertSame( 'Main workspace', $key->label() );
		$this->assertSame( '••••1a2b', $key->masked() );

		$stored = $this->options[ KeyRepository::OPTION_KEYS ][0];
		$this->assertStringNotContainsString( 'zv_secret_1a2b', $stored['secret_enc'] );
		$this->assertSame( '1a2b', $stored['last4'] );
	}

	public function test_find_decrypts_secret() {
		$inserted = $this->repository->insert( 'Main', 'zv_secret_1a2b' );

		$found = $this->repository->find( $inserted->id() );

		$this->assertNotNull( $found );
		$this->assertSame( 'zv_secret_1a2b', $found->secret() );
		$this->assertTrue( $found->has_secret() );
	}

	public function test_all_lists_newest_first_without_decrypting() {
		$this->repository->insert( 'First', 'secret-one-1111' );
		$this->repository->insert( 'Second', 'secret-two-2222' );

		$keys = $this->repository->all();

		$this->assertCount( 2, $keys );
		$this->assertSame( 'Second', $keys[0]->label() );
		$this->assertSame( 'First', $keys[1]->label() );
		$this->assertFalse( $keys[0]->has_secret() );
	}

	public function test_set_default_and_default_key() {
		$first  = $this->repository->insert( 'First', 'secret-one-1111' );
		$second = $this->repository->insert( 'Second', 'secret-two-2222' );

		$this->assertTrue( $this->repository->set_default( $second->id() ) );
		$this->assertSame( $second->id(), $this->repository->default_key_id() );

		$default = $this->repository->default_key();
		$this->assertSame( 'secret-two-2222', $default->secret() );

		$this->assertFalse( $this->repository->set_default( 'k_missing' ) );
		$this->assertSame( $second->id(), $this->repository->default_key_id() );

		$this->assertTrue( $this->repository->set_default( $first->id() ) );
		$this->assertSame( $first->id(), $this->repository->default_key_id() );
	}

	public function test_delete_reassigns_default_to_newest_remaining() {
		$first  = $this->repository->insert( 'First', 'secret-one-1111' );
		$second = $this->repository->insert( 'Second', 'secret-two-2222' );
		$this->repository->set_default( $second->id() );

		$this->assertTrue( $this->repository->delete( $second->id() ) );
		$this->assertSame( $first->id(), $this->repository->default_key_id() );

		$this->assertTrue( $this->repository->delete( $first->id() ) );
		$this->assertSame( '', $this->repository->default_key_id() );
		$this->assertSame( 0, $this->repository->count() );
	}

	public function test_update_touches_only_allowed_fields() {
		$key = $this->repository->insert( 'Main', 'zv_secret_1a2b' );

		$this->assertTrue(
			$this->repository->update(
				$key->id(),
				array(
					'label'      => 'Renamed',
					'is_valid'   => false,
					'last_error' => 'Invalid key',
					'secret_enc' => 'ATTACK',
					'id'         => 'k_attack',
				)
			)
		);

		$row = $this->options[ KeyRepository::OPTION_KEYS ][0];
		$this->assertSame( 'Renamed', $row['label'] );
		$this->assertFalse( $row['is_valid'] );
		$this->assertSame( $key->id(), $row['id'] );
		$this->assertNotSame( 'ATTACK', $row['secret_enc'] );

		$this->assertFalse( $this->repository->update( 'k_missing', array( 'label' => 'X' ) ) );
	}

	public function test_find_with_undecryptable_secret_returns_key_without_secret() {
		$key = $this->repository->insert( 'Main', 'zv_secret_1a2b' );

		// Simulate salt rotation by corrupting the stored ciphertext.
		$this->options[ KeyRepository::OPTION_KEYS ][0]['secret_enc'] = 'v1s:not-decryptable';

		$found = $this->repository->find( $key->id() );

		$this->assertNotNull( $found );
		$this->assertFalse( $found->has_secret() );
		$this->assertSame( '••••1a2b', $found->masked() );
	}
}
