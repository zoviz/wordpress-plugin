<?php
/**
 * HTTP response DTO.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Http;

/**
 * Immutable HTTP response value object returned by HttpTransport
 * implementations.
 */
final class TransportResponse {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Response headers with lowercase names.
	 *
	 * @var array<string, string>
	 */
	private $headers;

	/**
	 * Response body. Empty when the body was streamed to a file.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Path of the file the body was streamed to, when streaming was requested.
	 *
	 * @var string|null
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param int                   $status  HTTP status code.
	 * @param array<string, string> $headers Headers (names of any case).
	 * @param string                $body    Response body.
	 * @param string|null           $file    Stream destination path, if streamed.
	 */
	public function __construct( $status, array $headers = array(), $body = '', $file = null ) {
		$this->status  = (int) $status;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->body    = (string) $body;
		$this->file    = $file;
	}

	/**
	 * HTTP status code.
	 *
	 * @return int
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * All headers, lowercase names.
	 *
	 * @return array<string, string>
	 */
	public function headers() {
		return $this->headers;
	}

	/**
	 * A single header value.
	 *
	 * @param string $name Header name, any case.
	 * @return string|null
	 */
	public function header( $name ) {
		$name = strtolower( $name );

		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
	}

	/**
	 * Response body (empty when streamed to a file).
	 *
	 * @return string
	 */
	public function body() {
		return $this->body;
	}

	/**
	 * Decoded JSON body, or null when the body is not valid JSON.
	 *
	 * @return array<string, mixed>|null
	 */
	public function json() {
		$decoded = json_decode( $this->body, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Stream destination path, when the body was streamed.
	 *
	 * @return string|null
	 */
	public function file() {
		return $this->file;
	}

	/**
	 * Whether the status code is a success (2xx).
	 *
	 * @return bool
	 */
	public function ok() {
		return $this->status >= 200 && $this->status < 300;
	}
}
