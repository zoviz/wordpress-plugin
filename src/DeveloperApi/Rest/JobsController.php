<?php
/**
 * Jobs REST controller.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Rest;

use Zoviz\DeveloperApi\Jobs\Job;
use Zoviz\DeveloperApi\Jobs\JobManager;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Services\ServiceRegistry;

/**
 * /zoviz/v1/jobs — submit jobs, list and poll them, save results into the
 * Media Library, delete rows. The browser never talks to the Zoviz API
 * directly; this controller is the proxy, so API keys stay server-side.
 */
final class JobsController extends RestController {

	/**
	 * Job manager.
	 *
	 * @var JobManager
	 */
	private $manager;

	/**
	 * Job repository.
	 *
	 * @var JobRepository
	 */
	private $repository;

	/**
	 * Service registry.
	 *
	 * @var ServiceRegistry
	 */
	private $services;

	/**
	 * Constructor.
	 *
	 * @param JobManager      $manager    Job manager.
	 * @param JobRepository   $repository Job repository.
	 * @param ServiceRegistry $services   Service registry.
	 */
	public function __construct( JobManager $manager, JobRepository $repository, ServiceRegistry $services ) {
		parent::__construct();
		$this->rest_base  = 'jobs';
		$this->manager    = $manager;
		$this->repository = $repository;
		$this->services   = $services;
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
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_use_services' ),
					'args'                => $this->create_item_args(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_use_services' ),
					'args'                => $this->get_items_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'can_use_services' ),
					'args'                => array(
						'refresh' => array(
							'description' => __( 'Poll the Zoviz API for a fresh status when the job is pending.', 'zoviz-ai-studio' ),
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'can_use_services' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/save',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_item' ),
					'permission_callback' => array( $this, 'can_use_services' ),
					'args'                => $this->save_item_args(),
				),
			)
		);
	}

	/**
	 * POST /jobs — submit a job.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$service = $this->services->get( (string) $request['service'] );

		if ( null === $service ) {
			return new \WP_Error(
				'zoviz_invalid_request',
				__( 'Unknown Zoviz service.', 'zoviz-ai-studio' ),
				array( 'status' => 400 )
			);
		}

		$files = array();

		// Uploaded files (painted masks, direct uploads, sketches).
		foreach ( $service->fields() as $name => $schema ) {
			if ( 'file' !== ( isset( $schema['type'] ) ? $schema['type'] : '' ) ) {
				continue;
			}

			$upload = $this->uploaded_file( $request, $name );

			if ( is_wp_error( $upload ) ) {
				return $upload;
			}

			if ( null !== $upload ) {
				$files[ $name ] = $upload;
			}
		}

		// Media Library source: maps to the service's primary file field.
		$attachment_id = (int) $request['attachment_id'];

		if ( $attachment_id > 0 ) {
			$capabilities = $service->capabilities();
			$field        = 'sketch' === $capabilities['source'] ? 'sketch' : 'image';

			if ( ! isset( $files[ $field ] ) ) {
				$path = get_attached_file( $attachment_id );

				if ( ! $path || ! is_readable( $path ) || 'attachment' !== get_post_type( $attachment_id ) ) {
					return new \WP_Error(
						'zoviz_invalid_request',
						__( 'The selected image could not be found in the Media Library.', 'zoviz-ai-studio' ),
						array( 'status' => 400 )
					);
				}

				$files[ $field ] = array(
					'path'     => $path,
					'filename' => wp_basename( $path ),
					'mime'     => (string) get_post_mime_type( $attachment_id ),
				);
			}
		}//end if

		// Scalar fields declared by the service.
		$params = array();

		foreach ( $service->fields() as $name => $schema ) {
			if ( 'file' === ( isset( $schema['type'] ) ? $schema['type'] : '' ) ) {
				continue;
			}

			$value = $request->get_param( $name );

			if ( null !== $value && ! is_array( $value ) ) {
				$params[ $name ] = sanitize_textarea_field( (string) $value );
			}
		}

		try {
			$job = $this->manager->submit(
				$service->id(),
				$params,
				$files,
				array(
					'key_id'               => sanitize_text_field( (string) $request['key_id'] ),
					'context'              => (string) $request['context'],
					'source_attachment_id' => $attachment_id,
				)
			);
		} catch ( \Exception $e ) {
			return $this->error_from_exception( $e );
		}

		return new \WP_REST_Response( $job->to_array(), 201 );
	}

	/**
	 * GET /jobs — list jobs.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'status'   => (array) $request['status'],
			'service'  => (string) $request['service'],
			'context'  => (string) $request['context'],
			'batch_id' => (string) $request['batch_id'],
			'page'     => (int) $request['page'],
			'per_page' => (int) $request['per_page'],
		);

		if ( 'all' === $request['scope'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'zoviz_forbidden',
					__( 'You are not allowed to view other users\' jobs.', 'zoviz-ai-studio' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}
		} else {
			$args['created_by'] = get_current_user_id();
		}

		$result = $this->repository->query( $args );

		$response = new \WP_REST_Response(
			array_map(
				static function ( Job $job ) {
					return $job->to_array();
				},
				$result['jobs']
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * GET /jobs/{id} — fetch one job, polling the API when pending.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$job = $this->find_owned_job( (int) $request['id'] );

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		if ( $request['refresh'] && $job->is_pending() ) {
			$job = $this->manager->refresh( $job );
		}

		return new \WP_REST_Response( $this->with_attachment_info( $job->to_array() ) );
	}

	/**
	 * POST /jobs/{id}/save — download the result into the Media Library.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_item( $request ) {
		$job = $this->find_owned_job( (int) $request['id'] );

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$args   = array();
		$assign = $request['assign'];

		if ( ! empty( $request['title'] ) ) {
			$args['title'] = sanitize_text_field( (string) $request['title'] );
		}

		if ( ! empty( $request['alt'] ) ) {
			$args['alt'] = sanitize_text_field( (string) $request['alt'] );
		}

		if ( is_array( $assign ) && ! empty( $assign['type'] ) && 'none' !== $assign['type'] ) {
			$post_id = isset( $assign['post_id'] ) ? (int) $assign['post_id'] : 0;

			if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
				return new \WP_Error(
					'zoviz_forbidden',
					__( 'You are not allowed to attach the result to this content.', 'zoviz-ai-studio' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			$args['assign'] = array(
				'type'    => sanitize_key( (string) $assign['type'] ),
				'post_id' => $post_id,
			);
		}

		try {
			$attachment_id = $this->manager->save_to_media( $job, $args );
		} catch ( \Exception $e ) {
			return $this->error_from_exception( $e );
		}

		$job = $this->repository->find( $job->id() );

		return new \WP_REST_Response( $this->with_attachment_info( $job->to_array() ), 201 );
	}

	/**
	 * DELETE /jobs/{id} — remove a job row (the attachment stays).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$job = $this->find_owned_job( (int) $request['id'] );

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$this->repository->delete( $job->id() );

		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Finds a job and enforces ownership (owner or administrator).
	 *
	 * @param int $id Local job id.
	 * @return Job|\WP_Error
	 */
	private function find_owned_job( $id ) {
		$job = $this->repository->find( $id );

		if ( null === $job ) {
			return new \WP_Error(
				'zoviz_not_found',
				__( 'Job not found.', 'zoviz-ai-studio' ),
				array( 'status' => 404 )
			);
		}

		if ( $job->created_by() !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'zoviz_forbidden',
				__( 'You are not allowed to access this job.', 'zoviz-ai-studio' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $job;
	}

	/**
	 * Adds attachment URL info to a job payload.
	 *
	 * @param array<string, mixed> $data Job payload.
	 * @return array<string, mixed>
	 */
	private function with_attachment_info( array $data ) {
		if ( ! empty( $data['attachment_id'] ) ) {
			$data['attachment_url']  = wp_get_attachment_url( (int) $data['attachment_id'] );
			$data['attachment_edit'] = get_edit_post_link( (int) $data['attachment_id'], 'raw' );
		}

		return $data;
	}

	/**
	 * Validates and normalizes one uploaded file.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @param string           $name    File field name.
	 * @return array{path: string, filename: string, mime: string}|\WP_Error|null Null when not uploaded.
	 */
	private function uploaded_file( $request, $name ) {
		$files = $request->get_file_params();

		if ( empty( $files[ $name ] ) || ! is_array( $files[ $name ] ) ) {
			return null;
		}

		$file = $files[ $name ];

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'zoviz_invalid_request',
				sprintf(
					/* translators: %s: file field name. */
					__( 'The "%s" file upload failed.', 'zoviz-ai-studio' ),
					$name
				),
				array( 'status' => 400 )
			);
		}

		$filename = sanitize_file_name( isset( $file['name'] ) ? (string) $file['name'] : $name . '.png' );
		$checked  = wp_check_filetype_and_ext( $file['tmp_name'], $filename );
		$mime     = ! empty( $checked['type'] ) ? $checked['type'] : ( isset( $file['type'] ) ? (string) $file['type'] : '' );

		return array(
			'path'     => (string) $file['tmp_name'],
			'filename' => $filename,
			'mime'     => $mime,
		);
	}

	/**
	 * Args schema for POST /jobs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function create_item_args() {
		return array(
			'service'       => array(
				'description' => __( 'The Zoviz service to run.', 'zoviz-ai-studio' ),
				'type'        => 'string',
				'required'    => true,
				'enum'        => $this->services->ids(),
			),
			'key_id'        => array(
				'description'       => __( 'API key id; the default key is used when omitted.', 'zoviz-ai-studio' ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'attachment_id' => array(
				'description' => __( 'Media Library attachment to use as the source image.', 'zoviz-ai-studio' ),
				'type'        => 'integer',
				'default'     => 0,
				'minimum'     => 0,
			),
			'context'       => array(
				'description' => __( 'The admin surface the job was started from.', 'zoviz-ai-studio' ),
				'type'        => 'string',
				'default'     => 'workspace',
				'enum'        => array( 'workspace', 'media', 'editor', 'featured-image', 'woo-product', 'woo-gallery' ),
			),
		);
	}

	/**
	 * Args schema for GET /jobs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_items_args() {
		return array(
			'status'   => array(
				'description' => __( 'Statuses to filter by.', 'zoviz-ai-studio' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'pending_submit', 'queued', 'running', 'succeeded', 'failed', 'expired' ),
				),
				'default'     => array(),
			),
			'service'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'context'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'batch_id' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
			'scope'    => array(
				'description' => __( 'Use "all" to list every user\'s jobs (administrators only).', 'zoviz-ai-studio' ),
				'type'        => 'string',
				'enum'        => array( 'mine', 'all' ),
				'default'     => 'mine',
			),
		);
	}

	/**
	 * Args schema for POST /jobs/{id}/save.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function save_item_args() {
		return array(
			'title'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'alt'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'assign' => array(
				'description' => __( 'Optionally assign the saved attachment to content.', 'zoviz-ai-studio' ),
				'type'        => 'object',
				'properties'  => array(
					'type'    => array(
						'type' => 'string',
						'enum' => array( 'none', 'featured', 'product_image', 'product_gallery' ),
					),
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
		);
	}
}
