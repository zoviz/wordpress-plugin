<?php
/**
 * Base Developer API exception.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Base class for all Zoviz Developer API errors. Each subclass carries a
 * stable machine-readable error code and a suggested HTTP status, and
 * converts cleanly to a WP_Error for REST responses.
 */
class ApiException extends \RuntimeException {

	/**
	 * Machine-readable error code (also the REST error code).
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_api_error';

	/**
	 * Suggested HTTP status for REST responses.
	 *
	 * @var int
	 */
	protected $http_status = 500;

	/**
	 * Extra data merged into the WP_Error data array.
	 *
	 * @var array<string, mixed>
	 */
	protected $data = array();

	/**
	 * Constructor.
	 *
	 * @param string               $message Human-readable, translated message.
	 * @param array<string, mixed> $data    Optional extra data for WP_Error.
	 */
	public function __construct( $message = '', array $data = array() ) {
		parent::__construct( $message );
		$this->data = $data;
	}

	/**
	 * Machine-readable error code.
	 *
	 * @return string
	 */
	public function error_code() {
		return $this->error_code;
	}

	/**
	 * Suggested HTTP status.
	 *
	 * @return int
	 */
	public function http_status() {
		return $this->http_status;
	}

	/**
	 * Extra error data.
	 *
	 * @return array<string, mixed>
	 */
	public function data() {
		return $this->data;
	}

	/**
	 * Converts the exception to a WP_Error suitable for REST responses.
	 *
	 * @return \WP_Error
	 */
	public function to_wp_error() {
		return new \WP_Error(
			$this->error_code,
			$this->getMessage(),
			array_merge( array( 'status' => $this->http_status ), $this->data )
		);
	}
}
