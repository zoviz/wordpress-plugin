<?php
/**
 * Settings REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Settings;

/**
 * /zoviz/v1/settings — read/update plugin settings (administrators).
 */
final class SettingsController extends RestController {

	/**
	 * Settings accessor.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings accessor.
	 */
	public function __construct( Settings $settings ) {
		parent::__construct();
		$this->rest_base = 'settings';
		$this->settings  = $settings;
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'auto_download'  => array(
							'description' => __( 'Automatically save finished results into the Media Library.', 'zoviz-ai-studio' ),
							'type'        => 'boolean',
						),
						'retention_days' => array(
							'description' => __( 'Days to keep finished job history.', 'zoviz-ai-studio' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 3650,
						),
					),
				),
			)
		);
	}

	/**
	 * GET /settings.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		return new \WP_REST_Response( $this->settings->all() );
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function update_item( $request ) {
		$values = array();

		foreach ( array( 'auto_download', 'retention_days' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$values[ $key ] = $request->get_param( $key );
			}
		}

		return new \WP_REST_Response( $this->settings->update( $values ) );
	}
}
