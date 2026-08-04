<?php

namespace Zoviz\Tests\Integration\Support;

/**
 * Intercepts every HTTP request to developer.zoviz.com via the
 * pre_http_request filter and serves queued responses. No integration test
 * ever performs live HTTP.
 */
trait FakesZovizApi {

	/** @var array<int, array{status: int, body: string, headers: array<string, string>}> */
	private $zoviz_queue = array();

	/** @var array<int, array{url: string, args: array<string, mixed>}> */
	protected $zoviz_requests = array();

	protected function fake_zoviz_api(): void {
		add_filter( 'pre_http_request', array( $this, 'intercept_zoviz_request' ), 10, 3 );
	}

	protected function queue_zoviz_json( int $status, array $data ): void {
		$this->zoviz_queue[] = array(
			'status'  => $status,
			'body'    => (string) wp_json_encode( $data ),
			'headers' => array( 'content-type' => 'application/json' ),
		);
	}

	protected function queue_zoviz_fixture( int $status, string $fixture ): void {
		$this->zoviz_queue[] = array(
			'status'  => $status,
			'body'    => (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/' . $fixture ),
			'headers' => array( 'content-type' => 'application/json' ),
		);
	}

	protected function queue_zoviz_binary( string $fixture, string $content_type ): void {
		$this->zoviz_queue[] = array(
			'status'  => 200,
			'body'    => (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/' . $fixture ),
			'headers' => array( 'content-type' => $content_type ),
		);
	}

	/**
	 * pre_http_request callback. Public because WordPress calls it.
	 *
	 * @param false|array|\WP_Error $pre  Preemptive return value.
	 * @param array                 $args Request arguments.
	 * @param string                $url  Request URL.
	 * @return false|array|\WP_Error
	 */
	public function intercept_zoviz_request( $pre, $args, $url ) {
		if ( false === strpos( $url, 'developer.zoviz.com' ) ) {
			return $pre;
		}

		$this->zoviz_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( empty( $this->zoviz_queue ) ) {
			return new \WP_Error( 'zoviz_test_queue_empty', 'FakesZovizApi queue is empty for ' . $url );
		}

		$next     = array_shift( $this->zoviz_queue );
		$body     = $next['body'];
		$filename = '';

		// Emulate streaming downloads: write the body to the requested file.
		if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
			file_put_contents( $args['filename'], $body );
			$filename = $args['filename'];
			$body     = '';
		}

		return array(
			'headers'  => $next['headers'],
			'body'     => $body,
			'response' => array(
				'code'    => $next['status'],
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => $filename,
		);
	}
}
