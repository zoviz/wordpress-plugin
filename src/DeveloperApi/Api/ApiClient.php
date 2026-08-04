<?php
/**
 * Zoviz Developer API client.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

use Zoviz\DeveloperApi\Exception\ApiServerException;
use Zoviz\DeveloperApi\Exception\AuthException;
use Zoviz\DeveloperApi\Exception\InsufficientCreditsException;
use Zoviz\DeveloperApi\Exception\NetworkException;
use Zoviz\DeveloperApi\Exception\ResultExpiredException;
use Zoviz\DeveloperApi\Exception\ValidationException;
use Zoviz\DeveloperApi\Services\ServiceInterface;
use Zoviz\Infrastructure\Http\HttpTransport;
use Zoviz\Infrastructure\Http\MultipartBuilder;
use Zoviz\Infrastructure\Http\TransportException;
use Zoviz\Infrastructure\Http\TransportResponse;

/**
 * Typed client for developer.zoviz.com. Every job is submitted with
 * sync_mode=false; results are fetched by polling the jobs endpoints. All
 * HTTP failures are mapped to the typed exception hierarchy in a single
 * place.
 */
final class ApiClient {

	/**
	 * Default upload ceiling in bytes (the multipart body must fit in
	 * memory). Filterable via `zoviz_max_upload_bytes`.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_UPLOAD_BYTES = 15728640;

	/**
	 * HTTP transport.
	 *
	 * @var HttpTransport
	 */
	private $transport;

	/**
	 * API base URL, no trailing slash.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Constructor.
	 *
	 * @param HttpTransport $transport HTTP transport.
	 * @param string        $base_url  API base URL.
	 */
	public function __construct( HttpTransport $transport, $base_url = 'https://developer.zoviz.com' ) {
		$this->transport = $transport;
		$this->base_url  = rtrim( $base_url, '/' );
	}

	/**
	 * Submits an async job (sync_mode=false, expects HTTP 202).
	 *
	 * Failures are mapped to the typed exception hierarchy: AuthException
	 * (401), InsufficientCreditsException (402), ValidationException (400),
	 * ApiServerException (5xx), NetworkException (transport).
	 *
	 * @param ApiKey                                                                                                          $key     API key.
	 * @param ServiceInterface                                                                                                $service Target service.
	 * @param array{fields: array<string, string>, files: array<string, array{path: string, filename: string, mime: string}>} $payload Normalized payload from ServiceInterface::prepare_request().
	 * @return JobHandle
	 * @throws AuthException When the API key is rejected (401).
	 * @throws InsufficientCreditsException When credits are exhausted (402).
	 * @throws ValidationException When the request is invalid (400) or too large.
	 * @throws ApiServerException On server errors or unreadable responses.
	 * @throws NetworkException When the API is unreachable.
	 */
	public function submit_job( ApiKey $key, ServiceInterface $service, array $payload ) {
		$fields = isset( $payload['fields'] ) ? $payload['fields'] : array();
		$files  = isset( $payload['files'] ) ? $payload['files'] : array();
		$url    = $this->base_url . '/api/v1/' . ltrim( $service->endpoint(), '/' );

		if ( 'json' === $service->request_format() ) {
			$body    = wp_json_encode( array_merge( $fields, array( 'sync_mode' => false ) ) );
			$headers = $this->headers( $key, array( 'Content-Type' => 'application/json' ) );
		} else {
			$builder = new MultipartBuilder();

			foreach ( $fields as $name => $value ) {
				$builder->add_field( $name, $value );
			}
			$builder->add_field( 'sync_mode', 'false' );

			foreach ( $files as $name => $file ) {
				$builder->add_file( $name, $file['path'], $file['filename'], $file['mime'] );
			}

			$this->assert_upload_size( $builder );

			$body    = $builder->body();
			$headers = $this->headers( $key, array( 'Content-Type' => $builder->content_type() ) );
		}

		$response = $this->send( 'POST', $url, $headers, $body, array( 'timeout' => $this->timeout( 'submit', 60 ) ) );

		if ( ! $response->ok() ) {
			$this->throw_for_response( $response );
		}

		$data = $response->json();

		if ( null === $data || empty( $data['job_id'] ) ) {
			throw new ApiServerException(
				esc_html__( 'Zoviz returned an unexpected response while creating the job.', 'zoviz-ai-studio' )
			);
		}

		return JobHandle::from_array( $data );
	}

	/**
	 * Fetches the status of a job.
	 *
	 * @param ApiKey $key    API key.
	 * @param string $job_id Remote job id.
	 * @return JobStatusResponse
	 * @throws AuthException When the API key is rejected (401).
	 * @throws ValidationException When the job id is rejected (4xx).
	 * @throws ApiServerException On server errors or unreadable responses.
	 * @throws NetworkException When the API is unreachable.
	 */
	public function get_job( ApiKey $key, $job_id ) {
		$url      = $this->base_url . '/api/v1/jobs/' . rawurlencode( $job_id );
		$response = $this->send( 'GET', $url, $this->headers( $key ), null, array( 'timeout' => $this->timeout( 'status', 30 ) ) );

		if ( ! $response->ok() ) {
			$this->throw_for_response( $response );
		}

		$data = $response->json();

		if ( null === $data ) {
			throw new ApiServerException(
				esc_html__( 'Zoviz returned an unexpected response while checking the job status.', 'zoviz-ai-studio' )
			);
		}

		return JobStatusResponse::from_array( $data );
	}

	/**
	 * Downloads the binary result of a succeeded job, streaming to a file.
	 *
	 * @param ApiKey $key       API key.
	 * @param string $job_id    Remote job id.
	 * @param string $dest_path Absolute destination path (pre-created temp file).
	 * @return DownloadedFile
	 * @throws ResultExpiredException When the result is gone (404/410).
	 * @throws AuthException When the API key is rejected (401).
	 * @throws ValidationException When the request is rejected (4xx).
	 * @throws ApiServerException On server errors.
	 * @throws NetworkException When the API is unreachable.
	 */
	public function download_result( ApiKey $key, $job_id, $dest_path ) {
		$url      = $this->base_url . '/api/v1/jobs/' . rawurlencode( $job_id ) . '/result';
		$response = $this->send(
			'GET',
			$url,
			$this->headers( $key ),
			null,
			array(
				'timeout'   => $this->timeout( 'download', 120 ),
				'stream_to' => $dest_path,
			)
		);

		if ( in_array( $response->status(), array( 404, 410 ), true ) ) {
			throw new ResultExpiredException(
				esc_html__( 'This job result is no longer available for download from Zoviz.', 'zoviz-ai-studio' )
			);
		}

		if ( ! $response->ok() ) {
			$this->throw_for_response( $response );
		}

		$content_type = $response->header( 'content-type' );

		return new DownloadedFile( $dest_path, null !== $content_type ? $content_type : 'application/octet-stream' );
	}

	/**
	 * Fetches the credit balance. Also serves as API key validation.
	 *
	 * @param ApiKey $key API key.
	 * @return Credits
	 * @throws AuthException When the API key is rejected (401).
	 * @throws ApiServerException On server errors or unreadable responses.
	 * @throws NetworkException When the API is unreachable.
	 */
	public function get_credits( ApiKey $key ) {
		$url      = $this->base_url . '/api/v1/credits';
		$response = $this->send( 'GET', $url, $this->headers( $key ), null, array( 'timeout' => $this->timeout( 'credits', 15 ) ) );

		if ( ! $response->ok() ) {
			$this->throw_for_response( $response );
		}

		$data = $response->json();

		if ( null === $data || ! array_key_exists( 'credit', $data ) ) {
			throw new ApiServerException(
				esc_html__( 'Zoviz returned an unexpected response while checking the credit balance.', 'zoviz-ai-studio' )
			);
		}

		return Credits::from_array( $data );
	}

	/**
	 * Builds request headers.
	 *
	 * @param ApiKey                $key   API key.
	 * @param array<string, string> $extra Extra headers.
	 * @return array<string, string>
	 */
	private function headers( ApiKey $key, array $extra = array() ) {
		return array_merge(
			array(
				'Authorization' => 'Bearer ' . $key->secret(),
				'Accept'        => 'application/json',
			),
			$extra
		);
	}

	/**
	 * Sends a request, wrapping transport failures.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     URL.
	 * @param array<string, string> $headers Headers.
	 * @param string|null           $body    Body.
	 * @param array<string, mixed>  $options Transport options.
	 * @return TransportResponse
	 * @throws NetworkException When the transport fails.
	 */
	private function send( $method, $url, array $headers, $body, array $options ) {
		try {
			return $this->transport->request( $method, $url, $headers, $body, $options );
		} catch ( TransportException $e ) {
			throw new NetworkException(
				esc_html(
					sprintf(
						/* translators: %s: underlying transport error message. */
						__( 'Could not reach the Zoviz API: %s', 'zoviz-ai-studio' ),
						$e->getMessage()
					)
				)
			);
		}
	}

	/**
	 * Maps a non-2xx response to a typed exception. Single mapping point
	 * for the whole client.
	 *
	 * @param TransportResponse $response The failed response.
	 * @return never
	 * @throws AuthException For 401.
	 * @throws InsufficientCreditsException For 402.
	 * @throws ValidationException For other 4xx.
	 * @throws ApiServerException For 5xx and everything else.
	 */
	private function throw_for_response( TransportResponse $response ) {
		$api_message = $this->extract_message( $response );

		switch ( true ) {
			case 401 === $response->status():
				throw new AuthException(
					esc_html__( 'The Zoviz API key is missing or invalid. Please check it in the plugin settings.', 'zoviz-ai-studio' )
				);

			case 402 === $response->status():
				throw new InsufficientCreditsException(
					esc_html__( 'Your Zoviz workspace does not have enough credits for this request.', 'zoviz-ai-studio' )
				);

			case $response->status() >= 400 && $response->status() < 500:
				throw new ValidationException(
					esc_html(
						'' !== $api_message
							? $api_message
							: __( 'Zoviz rejected the request as invalid.', 'zoviz-ai-studio' )
					),
					array( 'api_status' => absint( $response->status() ) )
				);

			default:
				throw new ApiServerException(
					esc_html(
						'' !== $api_message
							? $api_message
							: __( 'Zoviz could not process the request due to a server error. Please try again.', 'zoviz-ai-studio' )
					),
					array( 'api_status' => absint( $response->status() ) )
				);
		}//end switch
	}

	/**
	 * Extracts a human-readable message from an error response body.
	 *
	 * @param TransportResponse $response The response.
	 * @return string
	 */
	private function extract_message( TransportResponse $response ) {
		$data = $response->json();

		if ( null === $data ) {
			return '';
		}

		foreach ( array( 'message', 'error', 'detail' ) as $field ) {
			if ( ! empty( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				return sanitize_text_field( $data[ $field ] );
			}
		}

		return '';
	}

	/**
	 * Enforces the upload size ceiling.
	 *
	 * @param MultipartBuilder $builder The multipart builder.
	 * @return void
	 * @throws ValidationException When attached files exceed the ceiling.
	 */
	private function assert_upload_size( MultipartBuilder $builder ) {
		/**
		 * Filters the maximum total upload size in bytes for Zoviz requests.
		 *
		 * @since 0.1.0
		 *
		 * @param int $max_bytes Maximum total size of attached files.
		 */
		$max_bytes = (int) apply_filters( 'zoviz_max_upload_bytes', self::DEFAULT_MAX_UPLOAD_BYTES );

		if ( $builder->files_size() > $max_bytes ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: %s: maximum upload size in megabytes. */
						__( 'The selected image is too large. The maximum upload size is %s MB.', 'zoviz-ai-studio' ),
						number_format_i18n( $max_bytes / 1048576, 1 )
					)
				)
			);
		}
	}

	/**
	 * Resolves a per-context HTTP timeout.
	 *
	 * @param string $context Request context: submit|status|download|credits.
	 * @param int    $default_seconds Default seconds.
	 * @return int
	 */
	private function timeout( $context, $default_seconds ) {
		/**
		 * Filters HTTP timeouts for Zoviz API requests.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $seconds Timeout in seconds.
		 * @param string $context Request context: submit|status|download|credits.
		 */
		return (int) apply_filters( 'zoviz_http_timeout', $default_seconds, $context );
	}
}
