<?php

namespace Zoviz\Tests\Unit\Api;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Api\ApiKey;
use Zoviz\DeveloperApi\Exception\ApiServerException;
use Zoviz\DeveloperApi\Exception\AuthException;
use Zoviz\DeveloperApi\Exception\InsufficientCreditsException;
use Zoviz\DeveloperApi\Exception\NetworkException;
use Zoviz\DeveloperApi\Exception\ResultExpiredException;
use Zoviz\DeveloperApi\Exception\ValidationException;
use Zoviz\DeveloperApi\Services\BackgroundRemoverService;
use Zoviz\DeveloperApi\Services\ImageGenerator2Service;
use Zoviz\Infrastructure\Http\TransportException;
use Zoviz\Tests\Unit\Support\FixtureTransport;
use Zoviz\Tests\Unit\TestCase;

class ApiClientTest extends TestCase {

	/** @var FixtureTransport */
	private $transport;

	/** @var ApiClient */
	private $client;

	/** @var ApiKey */
	private $key;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number, $decimals = 0 ) => number_format( (float) $number, (int) $decimals )
		);

		$this->transport = new FixtureTransport();
		$this->client    = new ApiClient( $this->transport, 'https://developer.zoviz.com' );
		$this->key       = new ApiKey( 'k_test', 'Main', 'zv_secret_1a2b', '1a2b' );
	}

	private function image_payload(): array {
		return array(
			'fields' => array(),
			'files'  => array(
				'image' => array(
					'path'     => dirname( __DIR__, 2 ) . '/Fixtures/result-1px.png',
					'filename' => 'input.png',
					'mime'     => 'image/png',
				),
			),
		);
	}

	public function test_submit_multipart_job_returns_handle() {
		$this->transport->queue_fixture( 202, 'job-queued.json' );

		$handle = $this->client->submit_job( $this->key, new BackgroundRemoverService(), $this->image_payload() );

		$this->assertSame( 'job_2f7c9a1e', $handle->job_id() );
		$this->assertSame( 'queued', $handle->status() );

		$request = $this->transport->last_request();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://developer.zoviz.com/api/v1/remove-background', $request['url'] );
		$this->assertSame( 'Bearer zv_secret_1a2b', $request['headers']['Authorization'] );
		$this->assertStringStartsWith( 'multipart/form-data; boundary=', $request['headers']['Content-Type'] );
		$this->assertStringContainsString( 'name="sync_mode"', $request['body'] );
		$this->assertStringContainsString( 'filename="input.png"', $request['body'] );
	}

	public function test_submit_json_job_sends_json_with_sync_mode_false() {
		$this->transport->queue_fixture( 202, 'job-queued.json' );

		$this->client->submit_job(
			$this->key,
			new ImageGenerator2Service(),
			array(
				'fields' => array(
					'prompt'    => 'A snowy japanese village at dusk',
					'dimension' => '1344x768',
				),
				'files'  => array(),
			)
		);

		$request = $this->transport->last_request();
		$this->assertSame( 'https://developer.zoviz.com/api/v1/image-generator-2', $request['url'] );
		$this->assertSame( 'application/json', $request['headers']['Content-Type'] );

		$body = json_decode( $request['body'], true );
		$this->assertSame( 'A snowy japanese village at dusk', $body['prompt'] );
		$this->assertSame( '1344x768', $body['dimension'] );
		$this->assertFalse( $body['sync_mode'] );
	}

	public function test_401_maps_to_auth_exception() {
		$this->transport->queue_json( 401, array( 'message' => 'Missing or invalid API key' ) );

		$this->expectException( AuthException::class );

		$this->client->get_credits( $this->key );
	}

	public function test_402_maps_to_insufficient_credits_with_buy_url() {
		$this->transport->queue_fixture( 402, 'error-402.json' );

		try {
			$this->client->submit_job( $this->key, new BackgroundRemoverService(), $this->image_payload() );
			$this->fail( 'Expected InsufficientCreditsException.' );
		} catch ( InsufficientCreditsException $e ) {
			$error = $e->to_wp_error();
			$data  = $error->get_error_data();

			$this->assertSame( 'zoviz_insufficient_credits', $error->get_error_code() );
			$this->assertSame( 402, $data['status'] );
			$this->assertSame(
				'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
				$data['buy_url']
			);
		}
	}

	public function test_400_maps_to_validation_exception_with_api_message() {
		$this->transport->queue_json( 400, array( 'message' => 'Image file is required or invalid format' ) );

		try {
			$this->client->submit_job( $this->key, new BackgroundRemoverService(), $this->image_payload() );
			$this->fail( 'Expected ValidationException.' );
		} catch ( ValidationException $e ) {
			$this->assertSame( 'Image file is required or invalid format', $e->getMessage() );
		}
	}

	public function test_500_maps_to_server_exception() {
		$this->transport->queue_json( 500, array( 'message' => 'Processing error' ) );

		$this->expectException( ApiServerException::class );

		$this->client->get_job( $this->key, 'job_2f7c9a1e' );
	}

	public function test_transport_failure_maps_to_network_exception() {
		$this->transport->queue( new TransportException( 'cURL error 28: timeout' ) );

		$this->expectException( NetworkException::class );

		$this->client->get_credits( $this->key );
	}

	public function test_get_job_parses_status_payload() {
		$this->transport->queue_fixture( 200, 'job-succeeded.json' );

		$status = $this->client->get_job( $this->key, 'job_2f7c9a1e' );

		$this->assertTrue( $status->succeeded() );
		$this->assertTrue( $status->is_terminal() );
		$this->assertSame( 1, $status->credits_used() );
		$this->assertSame( 'image/png', $status->content_type() );
		$this->assertSame( '2126-08-03T16:01:12.000Z', $status->expires_at() );
	}

	public function test_download_streams_to_destination() {
		$this->transport->queue(
			new \Zoviz\Infrastructure\Http\TransportResponse( 200, array( 'content-type' => 'image/png' ), '' )
		);

		$file = $this->client->download_result( $this->key, 'job_2f7c9a1e', '/tmp/zoviz-result.png' );

		$this->assertSame( '/tmp/zoviz-result.png', $file->path() );
		$this->assertSame( 'image/png', $file->content_type() );
		$this->assertSame( '/tmp/zoviz-result.png', $this->transport->last_request()['options']['stream_to'] );
	}

	public function test_download_404_maps_to_result_expired() {
		$this->transport->queue_json( 404, array( 'message' => 'Gone' ) );

		$this->expectException( ResultExpiredException::class );

		$this->client->download_result( $this->key, 'job_2f7c9a1e', '/tmp/zoviz-result.png' );
	}

	public function test_get_credits_parses_balance() {
		$this->transport->queue_fixture( 200, 'credits-ok.json' );

		$credits = $this->client->get_credits( $this->key );

		$this->assertSame( 1240, $credits->credit() );
		$this->assertSame( 24, $credits->reserved_credit() );
	}

	public function test_upload_size_cap_is_enforced() {
		Filters\expectApplied( 'zoviz_max_upload_bytes' )->once()->andReturn( 10 );

		$this->expectException( ValidationException::class );

		$this->client->submit_job( $this->key, new BackgroundRemoverService(), $this->image_payload() );
	}
}
