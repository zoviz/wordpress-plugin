<?php
/**
 * API key value object.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

/**
 * An API key. The secret lives only in memory on the server; it is never
 * serialized, logged, or sent to the browser.
 */
final class ApiKey {

	/**
	 * Internal key id (k_...).
	 *
	 * @var string
	 */
	private $id;

	/**
	 * User-chosen label (usually the workspace name).
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Decrypted secret, or empty string when decryption failed.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * Last four characters of the secret, stored for masked display.
	 *
	 * @var string
	 */
	private $last4;

	/**
	 * Whether the key is currently believed valid.
	 *
	 * @var bool
	 */
	private $is_valid;

	/**
	 * Creation timestamp (UTC, MySQL format).
	 *
	 * @var string
	 */
	private $created_at;

	/**
	 * Constructor.
	 *
	 * @param string $id         Internal key id.
	 * @param string $label      Label.
	 * @param string $secret     Decrypted secret ('' when unavailable).
	 * @param string $last4      Last four characters of the secret.
	 * @param bool   $is_valid   Whether the key is believed valid.
	 * @param string $created_at Creation timestamp (UTC).
	 */
	public function __construct( $id, $label, $secret, $last4, $is_valid = true, $created_at = '' ) {
		$this->id         = (string) $id;
		$this->label      = (string) $label;
		$this->secret     = (string) $secret;
		$this->last4      = (string) $last4;
		$this->is_valid   = (bool) $is_valid;
		$this->created_at = (string) $created_at;
	}

	/**
	 * Internal key id.
	 *
	 * @return string
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return $this->label;
	}

	/**
	 * Decrypted secret. Empty string when the secret could not be decrypted.
	 *
	 * @return string
	 */
	public function secret() {
		return $this->secret;
	}

	/**
	 * Whether a usable secret is present.
	 *
	 * @return bool
	 */
	public function has_secret() {
		return '' !== $this->secret;
	}

	/**
	 * Masked representation for display, e.g. "••••1a2b".
	 *
	 * @return string
	 */
	public function masked() {
		return '••••' . $this->last4;
	}

	/**
	 * Whether the key is currently believed valid.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return $this->is_valid;
	}

	/**
	 * Creation timestamp (UTC, MySQL format).
	 *
	 * @return string
	 */
	public function created_at() {
		return $this->created_at;
	}

	/**
	 * Hides the secret from var_dump()/print_r() output.
	 *
	 * @return array<string, string>
	 */
	public function __debugInfo(): array {
		return array(
			'id'     => $this->id,
			'label'  => $this->label,
			'secret' => $this->masked(),
		);
	}
}
