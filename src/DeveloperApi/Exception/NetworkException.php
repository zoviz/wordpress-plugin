<?php
/**
 * Network failure reaching the Zoviz API.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when no HTTP response could be obtained from the Zoviz API
 * (DNS, TLS, timeouts, blocked outbound requests).
 */
final class NetworkException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_network_error';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 502;
}
