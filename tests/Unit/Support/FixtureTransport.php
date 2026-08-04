<?php

namespace Zoviz\Tests\Unit\Support;

use Zoviz\Infrastructure\Http\HttpTransport;
use Zoviz\Infrastructure\Http\TransportException;
use Zoviz\Infrastructure\Http\TransportResponse;

/**
 * HttpTransport double: returns queued responses in order and records every
 * request for assertions. Queue an exception instance to simulate a
 * transport failure.
 */
final class FixtureTransport implements HttpTransport {

	/** @var array<int, TransportResponse|TransportException> */
	private $queue = array();

	/** @var array<int, array{method: string, url: string, headers: array, body: ?string, options: array}> */
	public $requests = array();

	public function queue( $response ): self {
		$this->queue[] = $response;

		return $this;
	}

	public function queue_json( int $status, array $data, array $headers = array() ): self {
		return $this->queue(
			new TransportResponse(
				$status,
				array_merge( array( 'content-type' => 'application/json' ), $headers ),
				(string) json_encode( $data )
			)
		);
	}

	public function queue_fixture( int $status, string $fixture ): self {
		$path = dirname( __DIR__, 2 ) . '/Fixtures/' . $fixture;

		return $this->queue(
			new TransportResponse(
				$status,
				array( 'content-type' => 'application/json' ),
				(string) file_get_contents( $path )
			)
		);
	}

	public function request( $method, $url, array $headers = array(), $body = null, array $options = array() ) {
		$this->requests[] = compact( 'method', 'url', 'headers', 'body', 'options' );

		if ( empty( $this->queue ) ) {
			throw new \LogicException( 'FixtureTransport queue is empty for ' . $method . ' ' . $url );
		}

		$next = array_shift( $this->queue );

		if ( $next instanceof TransportException ) {
			throw $next;
		}

		// Emulate streaming: when the caller asked to stream, echo the file path back.
		if ( isset( $options['stream_to'] ) && '' !== $options['stream_to'] ) {
			return new TransportResponse( $next->status(), $next->headers(), '', $options['stream_to'] );
		}

		return $next;
	}

	public function last_request(): array {
		return end( $this->requests ) ?: array();
	}
}
