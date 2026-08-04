<?php
/**
 * API keys REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Api\ApiKey;
use Zoviz\DeveloperApi\Keys\KeyManager;
use Zoviz\DeveloperApi\Keys\KeyRepository;

/**
 * /zoviz/v1/keys — key CRUD for administrators. Secrets are accepted on
 * create only and never returned; every response carries masked values.
 */
final class KeysController extends RestController {

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
		$this->rest_base  = 'keys';
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'label'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'secret' => array(
							'description' => __( 'The Zoviz API key. Validated against the live API before it is stored (encrypted).', 'zoviz-ai-studio' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>k_[a-z0-9]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'label'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'is_default' => array(
							'type' => 'boolean',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * GET /keys — list keys (masked).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$default_id = $this->repository->default_key_id();

		return new \WP_REST_Response(
			array_map(
				function ( ApiKey $key ) use ( $default_id ) {
					return $this->present( $key, $default_id );
				},
				$this->repository->all()
			)
		);
	}

	/**
	 * POST /keys — validate against the live API, store encrypted, promote
	 * to default.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		try {
			$key = $this->manager->add_key(
				(string) $request['label'],
				(string) $request['secret']
			);
		} catch ( \Exception $e ) {
			return $this->error_from_exception( $e );
		}

		return new \WP_REST_Response( $this->present( $key, $key->id() ), 201 );
	}

	/**
	 * PUT /keys/{id} — rename or set default.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id = (string) $request['id'];

		if ( null === $this->repository->find( $id ) ) {
			return $this->not_found();
		}

		if ( null !== $request->get_param( 'label' ) && '' !== (string) $request['label'] ) {
			$this->repository->update( $id, array( 'label' => (string) $request['label'] ) );
		}

		if ( true === $request->get_param( 'is_default' ) ) {
			$this->repository->set_default( $id );
		}

		$key = $this->repository->find( $id );

		return new \WP_REST_Response( $this->present( $key, $this->repository->default_key_id() ) );
	}

	/**
	 * DELETE /keys/{id} — remove a key; the newest remaining key becomes
	 * the default.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		if ( ! $this->manager->delete_key( (string) $request['id'] ) ) {
			return $this->not_found();
		}

		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Masked REST representation of a key. Never includes the secret.
	 *
	 * @param ApiKey $key        The key.
	 * @param string $default_id Current default key id.
	 * @return array<string, mixed>
	 */
	private function present( ApiKey $key, $default_id ) {
		return array(
			'id'         => $key->id(),
			'label'      => $key->label(),
			'masked'     => $key->masked(),
			'is_valid'   => $key->is_valid(),
			'is_default' => $key->id() === $default_id,
			'created_at' => $key->created_at(),
		);
	}

	/**
	 * 404 error.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return new \WP_Error(
			'zoviz_not_found',
			__( 'API key not found.', 'zoviz-ai-studio' ),
			array( 'status' => 404 )
		);
	}
}
