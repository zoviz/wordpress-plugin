<?php
/**
 * Credits REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Keys\KeyManager;
use Zoviz\DeveloperApi\Keys\KeyRepository;

/**
 * /zoviz/v1/credits — the workspace credit balance for a key (cached
 * briefly server-side). Feeds the CreditBadge shown on every surface.
 */
final class CreditsController extends RestController {

	/**
	 * Key manager.
	 *
	 * @var KeyManager
	 */
	private $manager;

	/**
	 * Key repository.
	 *
	 * @var KeyRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param KeyManager    $manager    Key manager.
	 * @param KeyRepository $repository Key repository.
	 */
	public function __construct( KeyManager $manager, KeyRepository $repository ) {
		parent::__construct();
		$this->rest_base  = 'credits';
		$this->manager    = $manager;
		$this->repository = $repository;
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
					'permission_callback' => array( $this, 'can_use_services' ),
					'args'                => array(
						'key_id' => array(
							'description'       => __( 'API key id; the default key is used when omitted.', 'zoviz-ai-studio' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'force'  => array(
							'description' => __( 'Bypass the short server-side cache.', 'zoviz-ai-studio' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * GET /credits — balance for a key's workspace.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$key_id = (string) $request['key_id'];
		$key    = '' !== $key_id ? $this->repository->find( $key_id ) : $this->repository->default_key();

		if ( null === $key ) {
			return new \WP_Error(
				'zoviz_no_api_key',
				__( 'No Zoviz API key is configured. Please add one in the plugin settings.', 'zoviz-ai-studio' ),
				array( 'status' => 404 )
			);
		}

		try {
			$credits = $this->manager->credits_for( $key->id(), (bool) $request['force'] );
		} catch ( \Exception $e ) {
			return $this->error_from_exception( $e );
		}

		return new \WP_REST_Response(
			array_merge(
				$credits->to_array(),
				array(
					'key' => array(
						'id'     => $key->id(),
						'label'  => $key->label(),
						'masked' => $key->masked(),
					),
				)
			)
		);
	}
}
