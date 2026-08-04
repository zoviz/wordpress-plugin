<?php
/**
 * Authentication failure (HTTP 401).
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when the Zoviz API rejects the API key (missing or invalid).
 */
final class AuthException extends ApiException {

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_invalid_api_key';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 401;
}
