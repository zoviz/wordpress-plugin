<?php
/**
 * Invalid request (HTTP 400).
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when a request is invalid — either rejected locally by a service's
 * field validation or rejected by the API with a 400 response.
 */
final class ValidationException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_invalid_request';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 400;
}
