<?php
/**
 * Job orchestration.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Jobs;

use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Api\ApiKey;
use Zoviz\DeveloperApi\Exception\ApiException;
use Zoviz\DeveloperApi\Exception\AuthException;
use Zoviz\DeveloperApi\Exception\ResultExpiredException;
use Zoviz\DeveloperApi\Exception\ValidationException;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\DeveloperApi\Media\MediaImporter;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\DeveloperApi\Settings;

/**
 * The one orchestrator for job lifecycle, shared by the REST layer and the
 * cron sweeper: submit to the API, refresh status, download results into
 * the Media Library.
 */
final class JobManager {

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $client;

	/**
	 * Job repository.
	 *
	 * @var JobRepository
	 */
	private $repository;

	/**
	 * Key repository.
	 *
	 * @var KeyRepository
	 */
	private $keys;

	/**
	 * Service registry.
	 *
	 * @var ServiceRegistry
	 */
	private $services;

	/**
	 * Media importer.
	 *
	 * @var MediaImporter
	 */
	private $importer;

	/**
	 * Settings accessor.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param ApiClient       $client     API client.
	 * @param JobRepository   $repository Job repository.
	 * @param KeyRepository   $keys       Key repository.
	 * @param ServiceRegistry $services   Service registry.
	 * @param MediaImporter   $importer   Media importer.
	 * @param Settings        $settings   Settings accessor.
	 */
	public function __construct(
		ApiClient $client,
		JobRepository $repository,
		KeyRepository $keys,
		ServiceRegistry $services,
		MediaImporter $importer,
		Settings $settings
	) {
		$this->client     = $client;
		$this->repository = $repository;
		$this->keys       = $keys;
		$this->services   = $services;
		$this->importer   = $importer;
		$this->settings   = $settings;
	}

	/**
	 * Submits a job to the API and persists the local row.
	 *
	 * @param string                                                             $service_id Service id.
	 * @param array<string, mixed>                                               $params     Scalar field values.
	 * @param array<string, array{path: string, filename: string, mime: string}> $files File inputs keyed by field name.
	 * @param array<string, mixed>                                               $context    {
	 *                                                   Submission context.
	 *
	 *     @type string $key_id               API key id (default key when omitted).
	 *     @type string $context              Originating surface (default 'workspace').
	 *     @type int    $source_attachment_id Source attachment, when the input came from the Media Library.
	 *     @type string $batch_id             Batch id for bulk work.
	 * }
	 * @return Job
	 * @throws ValidationException When the service is unknown or input invalid.
	 * @throws AuthException When no usable API key exists.
	 * @throws ApiException On API failures.
	 */
	public function submit( $service_id, array $params, array $files, array $context = array() ) {
		$service = $this->services->get( $service_id );

		if ( null === $service ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: %s: service id. */
						__( 'Unknown Zoviz service "%s".', 'zoviz-ai-studio' ),
						$service_id
					)
				)
			);
		}

		$key     = $this->resolve_key( isset( $context['key_id'] ) ? (string) $context['key_id'] : '' );
		$payload = $service->prepare_request( $params, $files );
		$handle  = $this->client->submit_job( $key, $service, $payload );

		$job_id = $this->repository->insert(
			array(
				'remote_job_id'        => $handle->job_id(),
				'api_key_id'           => $key->id(),
				'service'              => $service->id(),
				'status'               => '' !== $handle->status() ? $handle->status() : Job::STATUS_QUEUED,
				'context'              => ! empty( $context['context'] ) ? (string) $context['context'] : 'workspace',
				'source_attachment_id' => ! empty( $context['source_attachment_id'] ) ? (int) $context['source_attachment_id'] : null,
				'batch_id'             => ! empty( $context['batch_id'] ) ? (string) $context['batch_id'] : null,
				'params'               => $payload['fields'],
			)
		);

		$job = $this->repository->find( $job_id );

		if ( null === $job ) {
			throw new ApiException(
				esc_html__( 'The Zoviz job could not be stored locally.', 'zoviz-ai-studio' )
			);
		}

		return $job;
	}

	/**
	 * Polls the remote API once and synchronizes the local row.
	 *
	 * When the job succeeded and auto-download is enabled, the result is
	 * downloaded into the Media Library immediately (beats remote expiry).
	 *
	 * @param Job $job The job to refresh.
	 * @return Job The refreshed job.
	 */
	public function refresh( Job $job ) {
		if ( ! $job->is_pending() || '' === $job->remote_job_id() ) {
			return $job;
		}

		$key = $this->keys->find( $job->api_key_id() );

		if ( null === $key || ! $key->has_secret() ) {
			$this->repository->update(
				$job->id(),
				array(
					'status'        => Job::STATUS_FAILED,
					'error_code'    => 'zoviz_invalid_api_key',
					'error_message' => __( 'The API key used for this job is no longer available.', 'zoviz-ai-studio' ),
					'finished_at'   => gmdate( 'Y-m-d H:i:s' ),
				)
			);

			return $this->repository->find( $job->id() );
		}

		try {
			$status = $this->client->get_job( $key, $job->remote_job_id() );
		} catch ( ApiException $e ) {
			// Transient failure: leave the row as-is; the next poll retries.
			return $job;
		}

		$update = array(
			'status'       => $status->status(),
			'content_type' => $status->content_type(),
			'credits_used' => $status->credits_used(),
			'expires_at'   => $this->to_mysql_utc( $status->expires_at() ),
			'finished_at'  => $this->to_mysql_utc( $status->finished_at() ),
		);

		if ( $status->failed() ) {
			$update['error_code']    = 'zoviz_job_failed';
			$update['error_message'] = null !== $status->error()
				? $status->error()
				: __( 'The Zoviz job failed. No credits were consumed.', 'zoviz-ai-studio' );
		}

		$this->repository->update( $job->id(), $update );
		$job = $this->repository->find( $job->id() );

		if ( null !== $job && Job::STATUS_SUCCEEDED === $job->status() && 0 === $job->attachment_id()
			&& $this->settings->get( 'auto_download' ) ) {
			try {
				$this->save_to_media( $job );
				$job = $this->repository->find( $job->id() );
			} catch ( \Exception $e ) {
				// Auto-download is best-effort; manual download stays possible.
				unset( $e );
			}
		}

		return $job;
	}

	/**
	 * Downloads the job result into the Media Library (idempotent).
	 *
	 * @param Job                  $job  The job.
	 * @param array<string, mixed> $args Optional: 'title', 'alt', 'assign' (see MediaImporter::assign()).
	 * @return int Attachment id.
	 * @throws ResultExpiredException When no local copy exists and the remote result is gone.
	 * @throws ApiException On API failures.
	 * @throws \RuntimeException When the media import fails.
	 */
	public function save_to_media( Job $job, array $args = array() ) {
		$attachment_id = $job->attachment_id();

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			$this->maybe_assign( $attachment_id, $args );

			return $attachment_id;
		}

		if ( Job::STATUS_SUCCEEDED !== $job->status() ) {
			throw new ValidationException(
				esc_html__( 'This job has no downloadable result yet.', 'zoviz-ai-studio' )
			);
		}

		if ( ! $job->remote_result_available() ) {
			$this->repository->update( $job->id(), array( 'status' => Job::STATUS_EXPIRED ) );

			throw new ResultExpiredException(
				esc_html__( 'This job result has expired and is no longer available for download.', 'zoviz-ai-studio' )
			);
		}

		$key = $this->keys->find( $job->api_key_id() );

		if ( null === $key || ! $key->has_secret() ) {
			throw new AuthException(
				esc_html__( 'The API key used for this job is no longer available.', 'zoviz-ai-studio' )
			);
		}

		$temp_path = wp_tempnam( 'zoviz-result' );

		try {
			$download = $this->client->download_result( $key, $job->remote_job_id(), $temp_path );
		} catch ( ResultExpiredException $e ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort temp file cleanup.
			$this->repository->update( $job->id(), array( 'status' => Job::STATUS_EXPIRED ) );
			throw $e;
		} catch ( \Exception $e ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort temp file cleanup.
			throw $e;
		}

		$content_type = '' !== $job->content_type() ? $job->content_type() : $download->content_type();

		$attachment_id = $this->importer->import( $download->path(), $content_type, $job, $args );

		$this->repository->update(
			$job->id(),
			array(
				'attachment_id' => $attachment_id,
				'content_type'  => $content_type,
			)
		);

		$this->maybe_assign( $attachment_id, $args );

		return $attachment_id;
	}

	/**
	 * Resolves the API key to use for a submission.
	 *
	 * @param string $key_id Requested key id ('' for the default key).
	 * @return ApiKey
	 * @throws AuthException When no usable key is available.
	 */
	private function resolve_key( $key_id ) {
		$key = '' !== $key_id ? $this->keys->find( $key_id ) : $this->keys->default_key();

		if ( null === $key ) {
			throw new AuthException(
				esc_html__( 'No Zoviz API key is configured. Please add one in the plugin settings.', 'zoviz-ai-studio' )
			);
		}

		if ( ! $key->has_secret() ) {
			throw new AuthException(
				esc_html__( 'The stored API key could not be read. Please enter it again.', 'zoviz-ai-studio' )
			);
		}

		return $key;
	}

	/**
	 * Runs the optional assignment part of save args.
	 *
	 * @param int                  $attachment_id Attachment id.
	 * @param array<string, mixed> $args          Save args.
	 * @return void
	 */
	private function maybe_assign( $attachment_id, array $args ) {
		if ( ! empty( $args['assign'] ) && is_array( $args['assign'] ) ) {
			$this->importer->assign( $attachment_id, $args['assign'] );
		}
	}

	/**
	 * Converts an ISO 8601 timestamp to MySQL UTC format.
	 *
	 * @param string $iso ISO 8601 timestamp ('' allowed).
	 * @return string|null MySQL datetime or null.
	 */
	private function to_mysql_utc( $iso ) {
		if ( '' === $iso ) {
			return null;
		}

		$timestamp = strtotime( $iso );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
