<?php
/**
 * Notices REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

/**
 * /zoviz/v1/notices — per-user notice dismissal. Dismissing snoozes a
 * notice; it reappears after the snooze window while its condition
 * persists (e.g. the workspace is still out of credits).
 */
final class NoticesController extends RestController {

	/**
	 * User meta key holding dismissals.
	 *
	 * @var string
	 */
	const META_KEY = 'zoviz_dismissed_notices';

	/**
	 * Default snooze in seconds (7 days).
	 *
	 * @var int
	 */
	const DEFAULT_SNOOZE = 604800;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->rest_base = 'notices';
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-z0-9\-]+)/dismiss',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'dismiss' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * POST /notices/{id}/dismiss — snooze a notice for the current user.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function dismiss( $request ) {
		$notice_id = sanitize_key( (string) $request['id'] );
		$dismissed = get_user_meta( get_current_user_id(), self::META_KEY, true );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();

		/**
		 * Filters how long a dismissed Zoviz notice stays hidden.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $seconds   Snooze duration in seconds.
		 * @param string $notice_id The notice being dismissed.
		 */
		$snooze = (int) apply_filters( 'zoviz_notice_snooze_seconds', self::DEFAULT_SNOOZE, $notice_id );

		$dismissed[ $notice_id ] = time() + $snooze;

		update_user_meta( get_current_user_id(), self::META_KEY, $dismissed );

		return new \WP_REST_Response(
			array(
				'dismissed'    => true,
				'snooze_until' => $dismissed[ $notice_id ],
			)
		);
	}

	/**
	 * Whether a notice is currently snoozed for a user.
	 *
	 * @param string $notice_id Notice id.
	 * @param int    $user_id   User id (current user when 0).
	 * @return bool
	 */
	public static function is_snoozed( $notice_id, $user_id = 0 ) {
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();
		$dismissed = get_user_meta( $user_id, self::META_KEY, true );

		return is_array( $dismissed )
			&& isset( $dismissed[ $notice_id ] )
			&& (int) $dismissed[ $notice_id ] > time();
	}
}
