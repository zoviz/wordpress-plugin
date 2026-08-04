<?php
/**
 * Insufficient credits (HTTP 402).
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Exception;

/**
 * Thrown when the workspace does not have enough credits for the request.
 * Always carries the buy-credits deep link so every surface can offer the
 * user a direct path to top up.
 */
final class InsufficientCreditsException extends ApiException {

	/**
	 * Where users buy more credits. The navigation_source parameter
	 * attributes the visit to the WordPress plugin.
	 *
	 * @var string
	 */
	const BUY_URL = 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress';

	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	protected $error_code = 'zoviz_insufficient_credits';

	/**
	 * Suggested HTTP status.
	 *
	 * @var int
	 */
	protected $http_status = 402;

	/**
	 * The buy-credits URL.
	 *
	 * @return string
	 */
	public static function buy_url() {
		return self::BUY_URL;
	}

	/**
	 * Converts to WP_Error, always including the buy_url in data.
	 *
	 * @return \WP_Error
	 */
	public function to_wp_error() {
		$this->data['buy_url'] = self::BUY_URL;

		return parent::to_wp_error();
	}
}
