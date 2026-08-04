<?php
/**
 * Async job handle value object.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

/**
 * The handle returned when a job is submitted with sync_mode=false
 * (HTTP 202).
 */
final class JobHandle {

	/**
	 * Remote job id.
	 *
	 * @var string
	 */
	private $job_id;

	/**
	 * Initial status (normally "queued").
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Creation timestamp reported by the API (ISO 8601).
	 *
	 * @var string
	 */
	private $created_at;

	/**
	 * Constructor.
	 *
	 * @param string $job_id     Remote job id.
	 * @param string $status     Initial status.
	 * @param string $created_at Creation timestamp (ISO 8601).
	 */
	public function __construct( $job_id, $status = 'queued', $created_at = '' ) {
		$this->job_id     = (string) $job_id;
		$this->status     = (string) $status;
		$this->created_at = (string) $created_at;
	}

	/**
	 * Builds an instance from the 202 response payload.
	 *
	 * @param array<string, mixed> $data Decoded JSON payload.
	 * @return JobHandle
	 */
	public static function from_array( array $data ) {
		return new self(
			isset( $data['job_id'] ) ? (string) $data['job_id'] : '',
			isset( $data['status'] ) ? (string) $data['status'] : 'queued',
			isset( $data['created_at'] ) ? (string) $data['created_at'] : ''
		);
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
	 * Initial status.
	 *
	 * @return string
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * Creation timestamp (ISO 8601).
	 *
	 * @return string
	 */
	public function created_at() {
		return $this->created_at;
	}
}
