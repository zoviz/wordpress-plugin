<?php
/**
 * Sketch to Image service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Transforms hand-drawn sketches or wireframes into polished images guided
 * by a text description. Note the file field is named 'sketch'.
 */
final class SketchToImageService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'sketch-to-image';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Sketch to Image', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Transform hand-drawn sketches and wireframes into polished, realistic images guided by your text description.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'sketch-to-image';
	}

	/**
	 * Field schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'sketch' => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Sketch', 'zoviz-ai-studio' ),
			),
			'prompt' => array(
				'type'     => 'string',
				'required' => true,
				'label'    => __( 'Description', 'zoviz-ai-studio' ),
			),
		);
	}

	/**
	 * Capabilities — the primary input is a sketch upload.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities() {
		return array_merge( parent::capabilities(), array( 'source' => 'sketch' ) );
	}
}
