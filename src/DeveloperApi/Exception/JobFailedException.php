<?php
/**
 * Remote job failed.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when a Zoviz job finished with status "failed". Failed jobs
 * consume no credits.
 */
final class JobFailedException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_job_failed';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 500;
}
