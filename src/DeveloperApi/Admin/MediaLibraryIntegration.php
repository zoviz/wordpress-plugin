<?php
/**
 * Media Library integration.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Admin;

use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Services\ServiceInterface;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\Kernel\Assets;

/**
 * Adds Zoviz actions to eligible image attachments: list-table row actions,
 * an "Attachment Details" modal field, and a link on the classic attachment
 * edit screen. Every action deep-links to the Workspace page — the modal's
 * `attachment_fields_to_edit` field is the only stable extension point
 * inside the Backbone media views; nothing here overrides a Backbone view.
 */
final class MediaLibraryIntegration {

	/**
	 * Service registry (drives eligibility + one-click shortcuts).
	 *
	 * @var ServiceRegistry
	 */
	private $services;

	/**
	 * Job repository (drives the "has been processed" badge).
	 *
	 * @var JobRepository
	 */
	private $jobs;

	/**
	 * Asset registrar (styles the action links — no interactive JS ships here).
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Constructor.
	 *
	 * @param ServiceRegistry $services Service registry.
	 * @param JobRepository   $jobs     Job repository.
	 * @param Assets          $assets   Asset registrar.
	 */
	public function __construct( ServiceRegistry $services, JobRepository $jobs, Assets $assets ) {
		$this->services = $services;
		$this->jobs     = $jobs;
		$this->assets   = $assets;
	}

	/**
	 * Wires the hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'media_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 10, 2 );
		add_action( 'attachment_submitbox_misc_actions', array( $this, 'render_submitbox_action' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_js_data' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the small stylesheet the action links above use. There is no
	 * interactive script here (no Backbone view overrides), so this loads
	 * broadly rather than trying to enumerate every screen the modal or the
	 * attachment edit screen can appear on.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( current_user_can( 'upload_files' ) ) {
			$this->assets->enqueue( 'media' );
		}
	}

	/**
	 * Adds row actions on the Media Library list table.
	 *
	 * @param string[] $actions Existing row actions, keyed by action id.
	 * @param \WP_Post $post    The attachment post.
	 * @return string[]
	 */
	public function add_row_actions( $actions, $post ) {
		if ( ! $this->is_eligible( $post ) ) {
			return $actions;
		}

		foreach ( $this->quick_services() as $service ) {
			$actions[ 'zoviz-' . $service->id() ] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->workspace_url( $post->ID, $service->id() ) ),
				esc_html( $service->label() )
			);
		}

		$actions['zoviz'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->workspace_url( $post->ID ) ),
			esc_html__( 'Zoviz AI Studio', 'zoviz-ai-studio' )
		);

		return $actions;
	}

	/**
	 * Adds an action-buttons field to the "Attachment Details" modal.
	 *
	 * @param array<string, mixed> $form_fields Existing fields, keyed by field id.
	 * @param \WP_Post             $post        The attachment post.
	 * @return array<string, mixed>
	 */
	public function add_attachment_field( $form_fields, $post ) {
		if ( ! $this->is_eligible( $post ) ) {
			return $form_fields;
		}

		$form_fields['zoviz'] = array(
			'label' => __( 'Zoviz AI Studio', 'zoviz-ai-studio' ),
			'input' => 'html',
			'html'  => $this->action_buttons_html( $post->ID ),
		);

		return $form_fields;
	}

	/**
	 * Adds a link on the classic attachment edit screen's submit box.
	 *
	 * @return void
	 */
	public function render_submitbox_action() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! $this->is_eligible( $post ) ) {
			return;
		}

		printf(
			'<div class="misc-pub-section zoviz-misc-actions"><a href="%1$s">%2$s</a></div>',
			esc_url( $this->workspace_url( $post->ID ) ),
			esc_html__( 'Open in Zoviz AI Studio', 'zoviz-ai-studio' )
		);
	}

	/**
	 * Adds `zoviz` eligibility + job-count data to the JS attachment model,
	 * consumed by the media grid without any Backbone view overrides.
	 *
	 * @param array<string, mixed> $response   Attachment data for JS.
	 * @param \WP_Post             $attachment The attachment post.
	 * @return array<string, mixed>
	 */
	public function add_js_data( $response, $attachment ) {
		$response['zoviz'] = array(
			'eligible' => $this->is_eligible( $attachment ),
			'jobs'     => $this->jobs->count_by_source_attachment( $attachment->ID ),
		);

		return $response;
	}

	/**
	 * Whether an attachment is a supported image for at least one service.
	 *
	 * @param \WP_Post $attachment The attachment post.
	 * @return bool
	 */
	private function is_eligible( $attachment ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		return in_array( get_post_mime_type( $attachment ), $this->eligible_mimes(), true );
	}

	/**
	 * Union of accepted input MIME types across every registered service.
	 *
	 * @return string[]
	 */
	private function eligible_mimes() {
		$mimes = array();

		foreach ( $this->services->all() as $service ) {
			$mimes = array_merge( $mimes, $service->accepted_mimes() );
		}

		return array_values( array_unique( $mimes ) );
	}

	/**
	 * Services runnable in one click from a Media Library row: a plain
	 * source image, no mask, and no other required field lacking a default.
	 *
	 * @return ServiceInterface[]
	 */
	private function quick_services() {
		$quick = array();

		foreach ( $this->services->all() as $service ) {
			$capabilities = $service->capabilities();

			if ( 'image' !== $capabilities['source'] || ! empty( $capabilities['mask'] ) ) {
				continue;
			}

			if ( ! $this->has_only_image_field( $service ) ) {
				continue;
			}

			$quick[] = $service;
		}

		return $quick;
	}

	/**
	 * Whether a service needs nothing beyond the source image to run.
	 *
	 * @param ServiceInterface $service The service.
	 * @return bool
	 */
	private function has_only_image_field( ServiceInterface $service ) {
		foreach ( $service->fields() as $name => $schema ) {
			if ( 'image' === $name ) {
				continue;
			}

			if ( ! empty( $schema['required'] ) && ! isset( $schema['default'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Renders the button links shared by the modal field.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return string
	 */
	private function action_buttons_html( $attachment_id ) {
		$links = array();

		foreach ( $this->quick_services() as $service ) {
			$links[] = sprintf(
				'<a class="button" href="%1$s">%2$s</a>',
				esc_url( $this->workspace_url( $attachment_id, $service->id() ) ),
				esc_html( $service->label() )
			);
		}

		$links[] = sprintf(
			'<a class="button button-primary" href="%1$s">%2$s</a>',
			esc_url( $this->workspace_url( $attachment_id ) ),
			esc_html__( 'Open in Zoviz AI Studio', 'zoviz-ai-studio' )
		);

		return '<div class="zoviz-media-actions">' . implode( '', $links ) . '</div>';
	}

	/**
	 * Builds a Workspace deep link preloading an attachment and, optionally,
	 * a preselected service.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $service_id    Optional service id to preselect.
	 * @return string
	 */
	private function workspace_url( $attachment_id, $service_id = '' ) {
		$args = array(
			'page'       => Menu::SLUG_WORKSPACE,
			'attachment' => (int) $attachment_id,
		);

		if ( '' !== $service_id ) {
			$args['service'] = $service_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
