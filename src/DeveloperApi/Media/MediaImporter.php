<?php
/**
 * Media Library import of job results.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Media;

use Zoviz\DeveloperApi\Jobs\Job;

/**
 * Imports downloaded result files into the Media Library as NEW attachments
 * (originals are never touched) and assigns them to targets (featured
 * image, WooCommerce product image or gallery). Provenance is recorded in
 * attachment meta so results remain traceable to their job and source.
 */
final class MediaImporter {

	/**
	 * Imports a result file as a new attachment.
	 *
	 * @param string               $file_path    Absolute path of the downloaded file (consumed by the sideload).
	 * @param string               $content_type MIME type reported by the API.
	 * @param Job                  $job          The originating job.
	 * @param array<string, mixed> $args         Optional: 'title', 'alt'.
	 * @return int Attachment id.
	 * @throws \RuntimeException When the sideload fails.
	 */
	public function import( $file_path, $content_type, Job $job, array $args = array() ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$title = ! empty( $args['title'] )
			? (string) $args['title']
			: $this->default_title( $job );

		$file_array = array(
			'name'     => $this->filename( $job, $content_type, $title ),
			'tmp_name' => $file_path,
			'type'     => $content_type,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			throw new \RuntimeException( esc_html( $attachment_id->get_error_message() ) );
		}

		update_post_meta( $attachment_id, '_zoviz_job_id', $job->remote_job_id() );
		update_post_meta( $attachment_id, '_zoviz_service', $job->service() );

		if ( $job->source_attachment_id() > 0 ) {
			update_post_meta( $attachment_id, '_zoviz_source_attachment_id', $job->source_attachment_id() );
		}

		$alt = ! empty( $args['alt'] ) ? (string) $args['alt'] : $this->source_alt( $job );

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		return (int) $attachment_id;
	}

	/**
	 * Assigns an attachment to a target.
	 *
	 * @param int                  $attachment_id Attachment id.
	 * @param array<string, mixed> $target        {
	 *     Assignment target.
	 *
	 *     @type string $type    One of 'none', 'featured', 'product_image', 'product_gallery'.
	 *     @type int    $post_id Target post/product id.
	 * }
	 * @return bool Whether an assignment was performed.
	 */
	public function assign( $attachment_id, array $target ) {
		$type    = isset( $target['type'] ) ? (string) $target['type'] : 'none';
		$post_id = isset( $target['post_id'] ) ? (int) $target['post_id'] : 0;

		if ( 'none' === $type || $post_id <= 0 ) {
			return false;
		}

		if ( 'featured' === $type ) {
			return (bool) set_post_thumbnail( $post_id, $attachment_id );
		}

		// Product targets require WooCommerce.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return false;
		}

		if ( 'product_image' === $type ) {
			$product->set_image_id( $attachment_id );
			$product->save();

			return true;
		}

		if ( 'product_gallery' === $type ) {
			$gallery   = $product->get_gallery_image_ids();
			$gallery[] = (int) $attachment_id;
			$product->set_gallery_image_ids( array_values( array_unique( $gallery ) ) );
			$product->save();

			return true;
		}

		return false;
	}

	/**
	 * Default attachment title derived from the job.
	 *
	 * @param Job $job The job.
	 * @return string
	 */
	private function default_title( $job ) {
		$source = $job->source_attachment_id() > 0 ? get_the_title( $job->source_attachment_id() ) : '';

		if ( '' !== $source && null !== $source ) {
			return sprintf(
				/* translators: 1: original image title, 2: Zoviz service id. */
				__( '%1$s (Zoviz %2$s)', 'zoviz-ai-studio' ),
				$source,
				$job->service()
			);
		}

		return sprintf(
			/* translators: 1: Zoviz service id, 2: remote job id. */
			__( 'Zoviz %1$s result %2$s', 'zoviz-ai-studio' ),
			$job->service(),
			$job->remote_job_id()
		);
	}

	/**
	 * Builds a filename with the proper extension for the content type.
	 *
	 * @param Job    $job          The job.
	 * @param string $content_type MIME type.
	 * @param string $title        Attachment title.
	 * @return string
	 */
	private function filename( $job, $content_type, $title ) {
		$extensions = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		);

		$extension = isset( $extensions[ $content_type ] ) ? $extensions[ $content_type ] : 'png';
		$base      = sanitize_title( $title );

		if ( '' === $base ) {
			$base = 'zoviz-' . $job->service();
		}

		return $base . '.' . $extension;
	}

	/**
	 * Alt text of the source attachment, if any.
	 *
	 * @param Job $job The job.
	 * @return string
	 */
	private function source_alt( $job ) {
		if ( $job->source_attachment_id() <= 0 ) {
			return '';
		}

		return (string) get_post_meta( $job->source_attachment_id(), '_wp_attachment_image_alt', true );
	}
}
