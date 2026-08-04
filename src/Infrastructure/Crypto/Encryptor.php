<?php
/**
 * At-rest encryption for secrets.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Crypto;

/**
 * Encrypts and decrypts small secrets (API keys) at rest.
 *
 * Uses libsodium secretbox when available, falling back to OpenSSL
 * AES-256-GCM. The key is derived from the site's authentication salts, so
 * ciphertexts are bound to this installation: rotating the salts makes
 * existing ciphertexts undecryptable, which callers must treat as "secret
 * lost — ask the user to re-enter it" (never a fatal error).
 *
 * Output format is versioned: "v1s:" (sodium) or "v1o:" (OpenSSL) followed
 * by base64( nonce/iv . ciphertext ).
 */
final class Encryptor {

	/**
	 * Prefix for libsodium secretbox payloads.
	 *
	 * @var string
	 */
	const PREFIX_SODIUM = 'v1s:';

	/**
	 * Prefix for OpenSSL AES-256-GCM payloads.
	 *
	 * @var string
	 */
	const PREFIX_OPENSSL = 'v1o:';

	/**
	 * Whether an encryption backend is available on this host.
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->sodium_available() || $this->openssl_available();
	}

	/**
	 * Encrypts a secret.
	 *
	 * @param string $plaintext The secret to encrypt.
	 * @return string Versioned, base64-encoded ciphertext.
	 * @throws \RuntimeException When no encryption backend is available.
	 */
	public function encrypt( $plaintext ) {
		if ( $this->sodium_available() ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $this->key() );

			return self::PREFIX_SODIUM . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe ciphertext encoding, not obfuscation.
		}

		if ( $this->openssl_available() ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );

			if ( false === $cipher ) {
				throw new \RuntimeException( 'OpenSSL encryption failed.' );
			}

			return self::PREFIX_OPENSSL . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe ciphertext encoding, not obfuscation.
		}

		throw new \RuntimeException( 'No encryption backend (libsodium or OpenSSL) is available.' );
	}

	/**
	 * Decrypts a previously encrypted secret.
	 *
	 * @param string $ciphertext Versioned ciphertext produced by encrypt().
	 * @return string|null The plaintext, or null when decryption fails
	 *                     (tampering, salt rotation, missing backend).
	 */
	public function decrypt( $ciphertext ) {
		if ( ! is_string( $ciphertext ) || '' === $ciphertext ) {
			return null;
		}

		if ( 0 === strpos( $ciphertext, self::PREFIX_SODIUM ) ) {
			return $this->decrypt_sodium( substr( $ciphertext, strlen( self::PREFIX_SODIUM ) ) );
		}

		if ( 0 === strpos( $ciphertext, self::PREFIX_OPENSSL ) ) {
			return $this->decrypt_openssl( substr( $ciphertext, strlen( self::PREFIX_OPENSSL ) ) );
		}

		return null;
	}

	/**
	 * Decrypts a sodium secretbox payload.
	 *
	 * @param string $encoded Base64 payload without the version prefix.
	 * @return string|null
	 */
	private function decrypt_sodium( $encoded ) {
		if ( ! $this->sodium_available() ) {
			return null;
		}

		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary-safe ciphertext decoding, not obfuscation.

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $this->key() );
		} catch ( \SodiumException $e ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}

	/**
	 * Decrypts an OpenSSL AES-256-GCM payload.
	 *
	 * @param string $encoded Base64 payload without the version prefix.
	 * @return string|null
	 */
	private function decrypt_openssl( $encoded ) {
		if ( ! $this->openssl_available() ) {
			return null;
		}

		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary-safe ciphertext decoding, not obfuscation.

		if ( false === $raw || strlen( $raw ) <= 28 ) {
			return null;
		}

		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plain ? null : $plain;
	}

	/**
	 * Derives the 32-byte encryption key from the site's auth salts.
	 *
	 * @return string
	 */
	private function key() {
		$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		if ( $this->sodium_available() ) {
			return sodium_crypto_generichash( $material, '', 32 );
		}

		return hash( 'sha256', $material, true );
	}

	/**
	 * Whether libsodium is usable.
	 *
	 * @return bool
	 */
	private function sodium_available() {
		return function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' );
	}

	/**
	 * Whether OpenSSL AES-256-GCM is usable.
	 *
	 * @return bool
	 */
	private function openssl_available() {
		return function_exists( 'openssl_encrypt' )
			&& in_array( 'aes-256-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true );
	}
}
