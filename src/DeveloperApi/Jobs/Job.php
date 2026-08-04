<?php
/**
 * Job entity.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Jobs;

/**
 * A local job row mirroring the zoviz_jobs table. All timestamps are UTC
 * MySQL datetimes.
 */
final class Job {

	/**
	 * Local statuses. pending_submit exists for queued bulk work that has
	 * not been sent to the API yet; expired marks jobs whose remote result
	 * vanished before it could be downloaded.
	 */
	const STATUS_PENDING_SUBMIT = 'pending_submit';
	const STATUS_QUEUED         = 'queued';
	const STATUS_RUNNING        = 'running';
	const STATUS_SUCCEEDED      = 'succeeded';
	const STATUS_FAILED         = 'failed';
	const STATUS_EXPIRED        = 'expired';

	/**
	 * Row values keyed by column name.
	 *
	 * @var array<string, mixed>
	 */
	private $row;

	/**
	 * Builds a Job from a database row.
	 *
	 * @param array<string, mixed>|object $row Database row.
	 * @return Job
	 */
	public static function from_row( $row ) {
		$job      = new self();
		$job->row = (array) $row;

		return $job;
	}

	/**
	 * Local row id.
	 *
	 * @return int
	 */
	public function id() {
		return isset( $this->row['id'] ) ? (int) $this->row['id'] : 0;
	}

	/**
	 * Remote job id ('' until submitted).
	 *
	 * @return string
	 */
	public function remote_job_id() {
		return isset( $this->row['remote_job_id'] ) ? (string) $this->row['remote_job_id'] : '';
	}

	/**
	 * Id of the API key used.
	 *
	 * @return string
	 */
	public function api_key_id() {
		return isset( $this->row['api_key_id'] ) ? (string) $this->row['api_key_id'] : '';
	}

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function service() {
		return isset( $this->row['service'] ) ? (string) $this->row['service'] : '';
	}

	/**
	 * Local status.
	 *
	 * @return string
	 */
	public function status() {
		return isset( $this->row['status'] ) ? (string) $this->row['status'] : self::STATUS_PENDING_SUBMIT;
	}

	/**
	 * Whether the job is waiting on the remote API.
	 *
	 * @return bool
	 */
	public function is_pending() {
		return in_array( $this->status(), array( self::STATUS_PENDING_SUBMIT, self::STATUS_QUEUED, self::STATUS_RUNNING ), true );
	}

	/**
	 * Result content type.
	 *
	 * @return string
	 */
	public function content_type() {
		return isset( $this->row['content_type'] ) ? (string) $this->row['content_type'] : '';
	}

	/**
	 * Credits consumed.
	 *
	 * @return int|null
	 */
	public function credits_used() {
		return isset( $this->row['credits_used'] ) ? (int) $this->row['credits_used'] : null;
	}

	/**
	 * Result attachment id (0 when not downloaded yet).
	 *
	 * @return int
	 */
	public function attachment_id() {
		return isset( $this->row['attachment_id'] ) ? (int) $this->row['attachment_id'] : 0;
	}

	/**
	 * Source attachment id (0 when the input was an upload).
	 *
	 * @return int
	 */
	public function source_attachment_id() {
		return isset( $this->row['source_attachment_id'] ) ? (int) $this->row['source_attachment_id'] : 0;
	}

	/**
	 * Originating surface (workspace|media|editor|...).
	 *
	 * @return string
	 */
	public function context() {
		return isset( $this->row['context'] ) ? (string) $this->row['context'] : 'workspace';
	}

	/**
	 * Batch id ('' when not part of a batch).
	 *
	 * @return string
	 */
	public function batch_id() {
		return isset( $this->row['batch_id'] ) ? (string) $this->row['batch_id'] : '';
	}

	/**
	 * Scalar request parameters (never file contents).
	 *
	 * @return array<string, mixed>
	 */
	public function params() {
		if ( empty( $this->row['params'] ) ) {
			return array();
		}

		$decoded = json_decode( (string) $this->row['params'], true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Machine-readable error code.
	 *
	 * @return string
	 */
	public function error_code() {
		return isset( $this->row['error_code'] ) ? (string) $this->row['error_code'] : '';
	}

	/**
	 * Human-readable error message.
	 *
	 * @return string
	 */
	public function error_message() {
		return isset( $this->row['error_message'] ) ? (string) $this->row['error_message'] : '';
	}

	/**
	 * Id of the user who created the job.
	 *
	 * @return int
	 */
	public function created_by() {
		return isset( $this->row['created_by'] ) ? (int) $this->row['created_by'] : 0;
	}

	/**
	 * Creation timestamp (UTC MySQL).
	 *
	 * @return string
	 */
	public function created_at() {
		return isset( $this->row['created_at'] ) ? (string) $this->row['created_at'] : '';
	}

	/**
	 * Completion timestamp (UTC MySQL, '' while pending).
	 *
	 * @return string
	 */
	public function finished_at() {
		return isset( $this->row['finished_at'] ) ? (string) $this->row['finished_at'] : '';
	}

	/**
	 * Remote result expiry timestamp (UTC MySQL, '' when unknown).
	 *
	 * @return string
	 */
	public function expires_at() {
		return isset( $this->row['expires_at'] ) ? (string) $this->row['expires_at'] : '';
	}

	/**
	 * Whether the remote result should still be downloadable.
	 *
	 * @return bool
	 */
	public function remote_result_available() {
		if ( self::STATUS_SUCCEEDED !== $this->status() ) {
			return false;
		}

		$expires_at = $this->expires_at();

		if ( '' === $expires_at || null === $expires_at ) {
			return true;
		}

		return strtotime( $expires_at . ' UTC' ) > time();
	}

	/**
	 * Whether a result can be obtained right now (local copy or remote).
	 *
	 * @return bool
	 */
	public function downloadable() {
		return $this->attachment_id() > 0 || $this->remote_result_available();
	}

	/**
	 * REST/UI representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'id'                   => $this->id(),
			'remote_job_id'        => $this->remote_job_id(),
			'api_key_id'           => $this->api_key_id(),
			'service'              => $this->service(),
			'status'               => $this->status(),
			'content_type'         => $this->content_type(),
			'credits_used'         => $this->credits_used(),
			'attachment_id'        => $this->attachment_id(),
			'source_attachment_id' => $this->source_attachment_id(),
			'context'              => $this->context(),
			'batch_id'             => $this->batch_id(),
			'params'               => $this->params(),
			'error_code'           => $this->error_code(),
			'error_message'        => $this->error_message(),
			'created_by'           => $this->created_by(),
			'created_at'           => $this->created_at(),
			'finished_at'          => $this->finished_at(),
			'expires_at'           => $this->expires_at(),
			'downloadable'         => $this->downloadable(),
		);
	}
}
