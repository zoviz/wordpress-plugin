<?php
/**
 * HTTP transport contract.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Http;

/**
 * Minimal HTTP client abstraction so components never talk to
 * wp_remote_* directly and tests can substitute a fixture transport.
 */
interface HttpTransport {

	/**
	 * Performs an HTTP request.
	 *
	 * @param string                $method  HTTP method (GET, POST, ...).
	 * @param string                $url     Absolute URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Raw request body, if any.
	 * @param array<string, mixed>  $options Options:
	 *                                       - 'timeout'   (int)    seconds, default 30.
	 *                                       - 'stream_to' (string) stream the response body to this file path.
	 * @return TransportResponse
	 * @throws TransportException When no HTTP response could be obtained.
	 */
	public function request( $method, $url, array $headers = array(), $body = null, array $options = array() );
}
