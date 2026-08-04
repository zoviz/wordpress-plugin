<?php
/**
 * WordPress HTTP API transport.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Http;

/**
 * HttpTransport implementation backed by the WordPress HTTP API
 * (wp_remote_request). Supports streaming large binary responses straight
 * to a file so results never have to fit in memory.
 */
final class WpHttpTransport implements HttpTransport {

	/**
	 * Performs an HTTP request via wp_remote_request().
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     Absolute URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Raw request body.
	 * @param array<string, mixed>  $options See HttpTransport::request().
	 * @return TransportResponse
	 * @throws TransportException When the request fails at transport level.
	 */
	public function request( $method, $url, array $headers = array(), $body = null, array $options = array() ) {
		$args = array(
			'method'      => strtoupper( $method ),
			'headers'     => $headers,
			'timeout'     => isset( $options['timeout'] ) ? (int) $options['timeout'] : 30,
			'redirection' => 3,
		);

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$stream_to = isset( $options['stream_to'] ) ? (string) $options['stream_to'] : '';

		if ( '' !== $stream_to ) {
			$args['stream']   = true;
			$args['filename'] = $stream_to;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new TransportException( esc_html( $response->get_error_message() ) );
		}

		$headers_out = wp_remote_retrieve_headers( $response );

		if ( is_object( $headers_out ) && method_exists( $headers_out, 'getAll' ) ) {
			$headers_out = $headers_out->getAll();
		}

		return new TransportResponse(
			(int) wp_remote_retrieve_response_code( $response ),
			is_array( $headers_out ) ? $this->flatten_headers( $headers_out ) : array(),
			'' !== $stream_to ? '' : wp_remote_retrieve_body( $response ),
			'' !== $stream_to ? $stream_to : null
		);
	}

	/**
	 * Normalizes multi-value headers to single strings.
	 *
	 * @param array<string, string|string[]> $headers Raw headers.
	 * @return array<string, string>
	 */
	private function flatten_headers( array $headers ) {
		$flat = array();

		foreach ( $headers as $name => $value ) {
			$flat[ (string) $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return $flat;
	}
}
