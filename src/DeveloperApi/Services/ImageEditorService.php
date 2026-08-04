<?php
/**
 * Image Editor service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * AI-powered image editing: mask the area to change and describe the edit
 * in natural language.
 */
final class ImageEditorService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'image-editor';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Image Editor', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Mask the area you want to change and describe the desired edit — the AI transforms that region while keeping the rest of the image intact.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'image-editor';
	}

	/**
	 * Field schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'image'  => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Image', 'zoviz-ai-studio' ),
			),
			'mask'   => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Mask', 'zoviz-ai-studio' ),
			),
			'prompt' => array(
				'type'     => 'string',
				'required' => true,
				'label'    => __( 'Editing instruction', 'zoviz-ai-studio' ),
			),
		);
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities() {
		return array_merge( parent::capabilities(), array( 'mask' => true ) );
	}
}
