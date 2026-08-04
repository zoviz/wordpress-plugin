<?php
/**
 * Job status value object.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

/**
 * The payload returned by GET /api/v1/jobs/{job_id}.
 */
final class JobStatusResponse {

	/**
	 * Remote job id.
	 *
	 * @var string
	 */
	private $job_id;

	/**
	 * Remote service identifier.
	 *
	 * @var string
	 */
	private $service;

	/**
	 * Job status: queued | running | succeeded | failed.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Credits consumed by the job (final on success).
	 *
	 * @var int|null
	 */
	private $credits_used;

	/**
	 * Result content type for binary outputs.
	 *
	 * @var string|null
	 */
	private $content_type;

	/**
	 * Relative URL for downloading a binary result.
	 *
	 * @var string|null
	 */
	private $result_url;

	/**
	 * Inline result for JSON-output services.
	 *
	 * @var mixed|null
	 */
	private $result;

	/**
	 * Failure message when status is "failed".
	 *
	 * @var string|null
	 */
	private $error;

	/**
	 * Timestamps (ISO 8601 strings, empty when absent).
	 *
	 * @var string
	 */
	private $created_at;

	/**
	 * Last update timestamp.
	 *
	 * @var string
	 */
	private $updated_at;

	/**
	 * Completion timestamp.
	 *
	 * @var string
	 */
	private $finished_at;

	/**
	 * Result expiry timestamp — authoritative for download availability.
	 *
	 * @var string
	 */
	private $expires_at;

	/**
	 * Builds an instance from the status endpoint payload.
	 *
	 * @param array<string, mixed> $data Decoded JSON payload.
	 * @return JobStatusResponse
	 */
	public static function from_array( array $data ) {
		$self = new self();

		$self->job_id       = isset( $data['job_id'] ) ? (string) $data['job_id'] : '';
		$self->service      = isset( $data['service'] ) ? (string) $data['service'] : '';
		$self->status       = isset( $data['status'] ) ? (string) $data['status'] : '';
		$self->credits_used = isset( $data['credits_used'] ) ? (int) $data['credits_used'] : null;
		$self->content_type = isset( $data['content_type'] ) ? (string) $data['content_type'] : null;
		$self->result_url   = isset( $data['result_url'] ) ? (string) $data['result_url'] : null;
		$self->result       = isset( $data['result'] ) ? $data['result'] : null;
		$self->error        = isset( $data['error'] ) ? (string) $data['error'] : null;
		$self->created_at   = isset( $data['created_at'] ) ? (string) $data['created_at'] : '';
		$self->updated_at   = isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '';
		$self->finished_at  = isset( $data['finished_at'] ) ? (string) $data['finished_at'] : '';
		$self->expires_at   = isset( $data['expires_at'] ) ? (string) $data['expires_at'] : '';

		return $self;
	}

	/**
	 * Remote job id.
	 *
	 * @return string
	 */
	public function job_id() {
		return $this->job_id;
	}

	/**
	 * Remote service identifier.
	 *
	 * @return string
	 */
	public function service() {
		return $this->service;
	}

	/**
	 * Job status.
	 *
	 * @return string
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * Whether the job reached a terminal state.
	 *
	 * @return bool
	 */
	public function is_terminal() {
		return in_array( $this->status, array( 'succeeded', 'failed' ), true );
	}

	/**
	 * Whether the job succeeded.
	 *
	 * @return bool
	 */
	public function succeeded() {
		return 'succeeded' === $this->status;
	}

	/**
	 * Whether the job failed.
	 *
	 * @return bool
	 */
	public function failed() {
		return 'failed' === $this->status;
	}

	/**
	 * Credits consumed.
	 *
	 * @return int|null
	 */
	public function credits_used() {
		return $this->credits_used;
	}

	/**
	 * Binary result content type.
	 *
	 * @return string|null
	 */
	public function content_type() {
		return $this->content_type;
	}

	/**
	 * Binary result URL (relative).
	 *
	 * @return string|null
	 */
	public function result_url() {
		return $this->result_url;
	}

	/**
	 * Inline JSON result.
	 *
	 * @return mixed|null
	 */
	public function result() {
		return $this->result;
	}

	/**
	 * Failure message.
	 *
	 * @return string|null
	 */
	public function error() {
		return $this->error;
	}

	/**
	 * Creation timestamp.
	 *
	 * @return string
	 */
	public function created_at() {
		return $this->created_at;
	}

	/**
	 * Last update timestamp.
	 *
	 * @return string
	 */
	public function updated_at() {
		return $this->updated_at;
	}

	/**
	 * Completion timestamp.
	 *
	 * @return string
	 */
	public function finished_at() {
		return $this->finished_at;
	}

	/**
	 * Result expiry timestamp.
	 *
	 * @return string
	 */
	public function expires_at() {
		return $this->expires_at;
	}
}
