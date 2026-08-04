<?php

namespace Zoviz\Tests\Unit\Infrastructure;

use Brain\Monkey\Functions;
use Zoviz\Infrastructure\Crypto\Encryptor;
use Zoviz\Tests\Unit\TestCase;

class EncryptorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->alias(
			static fn( $scheme = 'auth' ) => 'test-salt-' . $scheme
		);
	}

	public function test_is_available_on_this_environment() {
		$this->assertTrue( ( new Encryptor() )->is_available() );
	}

	public function test_roundtrip() {
		$encryptor = new Encryptor();
		$secret    = 'zv_live_0123456789abcdef';

		$ciphertext = $encryptor->encrypt( $secret );

		$this->assertNotSame( $secret, $ciphertext );
		$this->assertStringNotContainsString( $secret, $ciphertext );
		$this->assertSame( $secret, $encryptor->decrypt( $ciphertext ) );
	}

	public function test_ciphertexts_are_versioned() {
		$ciphertext = ( new Encryptor() )->encrypt( 'secret' );

		$this->assertMatchesRegularExpression( '/^v1[so]:/', $ciphertext );
	}

	public function test_decrypt_returns_null_on_tampered_payload() {
		$encryptor  = new Encryptor();
		$ciphertext = $encryptor->encrypt( 'secret' );

		// Flip a character near the end of the base64 payload.
		$tampered = substr( $ciphertext, 0, -2 ) . ( '=' === substr( $ciphertext, -2, 1 ) ? 'x=' : 'AB' );

		$this->assertNull( $encryptor->decrypt( $tampered ) );
	}

	public function test_decrypt_returns_null_when_salts_changed() {
		$encryptor  = new Encryptor();
		$ciphertext = $encryptor->encrypt( 'secret' );

		Functions\when( 'wp_salt' )->alias(
			static fn( $scheme = 'auth' ) => 'rotated-salt-' . $scheme
		);

		$this->assertNull( $encryptor->decrypt( $ciphertext ) );
	}

	public function test_decrypt_returns_null_on_garbage() {
		$encryptor = new Encryptor();

		$this->assertNull( $encryptor->decrypt( '' ) );
		$this->assertNull( $encryptor->decrypt( 'not-a-ciphertext' ) );
		$this->assertNull( $encryptor->decrypt( 'v9x:AAAA' ) );
		$this->assertNull( $encryptor->decrypt( 'v1s:%%%invalid-base64%%%' ) );
	}
}
