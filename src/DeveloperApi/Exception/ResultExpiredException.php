<?php
/**
 * Job result no longer available.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when a job result has been deleted from the Zoviz API (results are
 * retained only until the job's expires_at timestamp).
 */
final class ResultExpiredException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_result_expired';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 410;
}
