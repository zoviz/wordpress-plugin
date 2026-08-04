<?php
/**
 * Services catalog REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Services\ServiceInterface;
use Zoviz\DeveloperApi\Services\ServiceRegistry;

/**
 * /zoviz/v1/services — the service catalog. The JS layer renders service
 * forms directly from this schema, so adding a PHP service automatically
 * produces its UI.
 */
final class ServicesController extends RestController {

	/**
	 * Service registry.
	 *
	 * @var ServiceRegistry
	 */
	private $services;

	/**
	 * Constructor.
	 *
	 * @param ServiceRegistry $services Service registry.
	 */
	public function __construct( ServiceRegistry $services ) {
		parent::__construct();
		$this->rest_base = 'services';
		$this->services  = $services;
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_use_services' ),
				),
			)
		);
	}

	/**
	 * GET /services — full catalog.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		return new \WP_REST_Response(
			array_values(
				array_map(
					static function ( ServiceInterface $service ) {
						return array(
							'id'                   => $service->id(),
							'label'                => $service->label(),
							'description'          => $service->description(),
							'credit_cost'          => $service->credit_cost(),
							'fields'               => $service->fields(),
							'capabilities'         => $service->capabilities(),
							'accepted_mimes'       => $service->accepted_mimes(),
							'output_content_types' => $service->output_content_types(),
						);
					},
					$this->services->all()
				)
			)
		);
	}
}
