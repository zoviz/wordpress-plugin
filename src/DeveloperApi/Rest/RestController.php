<?php
/**
 * REST controller base.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Exception\ApiException;

/**
 * Shared behavior for all zoviz/v1 controllers: the namespace, exception
 * mapping, and common permission callbacks.
 */
abstract class RestController extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'zoviz/v1';
	}

	/**
	 * Maps an exception to a REST-ready WP_Error.
	 *
	 * @param \Exception $e The exception.
	 * @return \WP_Error
	 */
	protected function error_from_exception( \Exception $e ) {
		if ( $e instanceof ApiException ) {
			return $e->to_wp_error();
		}

		return new \WP_Error(
			'zoviz_internal_error',
			__( 'Something went wrong while talking to Zoviz. Please try again.', 'zoviz-ai-studio' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Permission: user may run image operations.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_use_services() {
		if ( current_user_can( 'upload_files' ) ) {
			return true;
		}

		return new \WP_Error(
			'zoviz_forbidden',
			__( 'You are not allowed to use Zoviz image tools.', 'zoviz-ai-studio' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Permission: user may manage plugin configuration.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'zoviz_forbidden',
			__( 'You are not allowed to manage Zoviz settings.', 'zoviz-ai-studio' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
