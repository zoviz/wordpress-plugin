<?php
/**
 * Image Upscaler service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Upscales images to a target resolution while preserving detail.
 * Note the endpoint path is 'image-upscaling' (not 'image-upscaler').
 */
final class ImageUpscalerService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'image-upscaler';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Image Upscaler', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Upscale images to higher resolutions while preserving detail and sharpness — perfect for improving low-resolution photos.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path (differs from the service id).
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'image-upscaling';
	}

	/**
	 * Possible result content types.
	 *
	 * @return string[]
	 */
	public function output_content_types() {
		return array( 'image/png', 'image/jpeg' );
	}

	/**
	 * Field schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'image'         => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Image', 'zoviz-ai-studio' ),
			),
			'target_width'  => array(
				'type'     => 'integer',
				'required' => true,
				'min'      => 1,
				'max'      => 8192,
				'label'    => __( 'Target width (px)', 'zoviz-ai-studio' ),
			),
			'target_height' => array(
				'type'     => 'integer',
				'required' => true,
				'min'      => 1,
				'max'      => 8192,
				'label'    => __( 'Target height (px)', 'zoviz-ai-studio' ),
			),
		);
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities() {
		return array_merge( parent::capabilities(), array( 'bulk' => true ) );
	}
}
