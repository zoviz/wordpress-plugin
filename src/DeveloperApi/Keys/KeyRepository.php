<?php
/**
 * API key storage.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Keys;

use Zoviz\DeveloperApi\Api\ApiKey;
use Zoviz\Infrastructure\Crypto\Encryptor;

/**
 * Persists API keys in the `zoviz_api_keys` option (autoload off; the
 * cardinality is single digits, so a table would be overkill). Secrets are
 * stored encrypted; the last four characters are stored separately so the
 * UI can show a masked value without decrypting.
 */
final class KeyRepository {

	/**
	 * Option holding all key rows.
	 *
	 * @var string
	 */
	const OPTION_KEYS = 'zoviz_api_keys';

	/**
	 * Option holding the default key id.
	 *
	 * @var string
	 */
	const OPTION_DEFAULT = 'zoviz_default_key_id';

	/**
	 * Secret encryptor.
	 *
	 * @var Encryptor
	 */
	private $encryptor;

	/**
	 * Constructor.
	 *
	 * @param Encryptor $encryptor Secret encryptor.
	 */
	public function __construct( Encryptor $encryptor ) {
		$this->encryptor = $encryptor;
	}

	/**
	 * All keys, newest first. Secrets are NOT decrypted (use find()).
	 *
	 * @return ApiKey[]
	 */
	public function all() {
		$keys = array();

		foreach ( $this->rows() as $row ) {
			$keys[] = $this->to_key( $row, false );
		}

		return $keys;
	}

	/**
	 * Finds a key by id, decrypting its secret.
	 *
	 * @param string $id Key id.
	 * @return ApiKey|null Null when the id is unknown.
	 */
	public function find( $id ) {
		foreach ( $this->rows() as $row ) {
			if ( $row['id'] === $id ) {
				return $this->to_key( $row, true );
			}
		}

		return null;
	}

	/**
	 * Inserts a new key with an already-validated secret.
	 *
	 * @param string $label  Label.
	 * @param string $secret Plaintext secret.
	 * @return ApiKey
	 * @throws \RuntimeException When encryption is unavailable.
	 */
	public function insert( $label, $secret ) {
		$rows = $this->rows();

		$row = array(
			'id'                => 'k_' . strtolower( wp_generate_password( 12, false, false ) ),
			'label'             => $label,
			'secret_enc'        => $this->encryptor->encrypt( $secret ),
			'last4'             => substr( $secret, -4 ),
			'is_valid'          => true,
			'last_error'        => '',
			'created_at'        => gmdate( 'Y-m-d H:i:s' ),
			'last_validated_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		// Newest first.
		array_unshift( $rows, $row );
		$this->save( $rows );

		return $this->to_key( $row, false, $secret );
	}

	/**
	 * Updates mutable fields of a key row.
	 *
	 * @param string               $id     Key id.
	 * @param array<string, mixed> $fields Allowed: label, is_valid, last_error, last_validated_at.
	 * @return bool Whether a row was updated.
	 */
	public function update( $id, array $fields ) {
		$allowed = array( 'label', 'is_valid', 'last_error', 'last_validated_at' );
		$rows    = $this->rows();
		$found   = false;

		foreach ( $rows as $index => $row ) {
			if ( $row['id'] !== $id ) {
				continue;
			}

			foreach ( $allowed as $field ) {
				if ( array_key_exists( $field, $fields ) ) {
					$rows[ $index ][ $field ] = $fields[ $field ];
				}
			}

			$found = true;
			break;
		}

		if ( $found ) {
			$this->save( $rows );
		}

		return $found;
	}

	/**
	 * Deletes a key.
	 *
	 * @param string $id Key id.
	 * @return bool Whether a row was deleted.
	 */
	public function delete( $id ) {
		$rows     = $this->rows();
		$filtered = array();

		foreach ( $rows as $row ) {
			if ( $row['id'] !== $id ) {
				$filtered[] = $row;
			}
		}

		if ( count( $filtered ) === count( $rows ) ) {
			return false;
		}

		$this->save( $filtered );

		if ( $this->default_key_id() === $id ) {
			$newest = isset( $filtered[0] ) ? $filtered[0]['id'] : '';
			update_option( self::OPTION_DEFAULT, $newest, false );
		}

		return true;
	}

	/**
	 * Number of stored keys.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->rows() );
	}

	/**
	 * The default key id ('' when none).
	 *
	 * @return string
	 */
	public function default_key_id() {
		return (string) get_option( self::OPTION_DEFAULT, '' );
	}

	/**
	 * The default key with decrypted secret, or null.
	 *
	 * @return ApiKey|null
	 */
	public function default_key() {
		$id = $this->default_key_id();

		if ( '' === $id ) {
			return null;
		}

		return $this->find( $id );
	}

	/**
	 * Sets the default key.
	 *
	 * @param string $id Key id.
	 * @return bool Whether the id exists and was set.
	 */
	public function set_default( $id ) {
		foreach ( $this->rows() as $row ) {
			if ( $row['id'] === $id ) {
				update_option( self::OPTION_DEFAULT, $id, false );
				return true;
			}
		}

		return false;
	}

	/**
	 * Reads raw rows from the option.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows() {
		$rows = get_option( self::OPTION_KEYS, array() );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Persists rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Key rows.
	 * @return void
	 */
	private function save( array $rows ) {
		update_option( self::OPTION_KEYS, array_values( $rows ), false );
	}

	/**
	 * Builds an ApiKey from a row.
	 *
	 * @param array<string, mixed> $row            Raw row.
	 * @param bool                 $decrypt        Whether to decrypt the secret.
	 * @param string|null          $known_plaintext Plaintext secret when already known (insert path).
	 * @return ApiKey
	 */
	private function to_key( array $row, $decrypt, $known_plaintext = null ) {
		$secret = '';

		if ( null !== $known_plaintext ) {
			$secret = $known_plaintext;
		} elseif ( $decrypt ) {
			$decrypted = $this->encryptor->decrypt( isset( $row['secret_enc'] ) ? $row['secret_enc'] : '' );
			$secret    = null === $decrypted ? '' : $decrypted;
		}

		return new ApiKey(
			isset( $row['id'] ) ? $row['id'] : '',
			isset( $row['label'] ) ? $row['label'] : '',
			$secret,
			isset( $row['last4'] ) ? $row['last4'] : '',
			! empty( $row['is_valid'] ),
			isset( $row['created_at'] ) ? $row['created_at'] : ''
		);
	}
}
