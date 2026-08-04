<?php
/**
 * Zoviz API server error (HTTP 5xx).
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when the Zoviz API responds with a server-side processing error.
 */
final class ApiServerException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_api_server_error';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 502;
}
