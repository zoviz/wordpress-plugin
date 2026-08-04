<?php
/**
 * Background Remover service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Removes the background from an image, producing a transparent PNG.
 */
final class BackgroundRemoverService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'background-remover';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Background Remover', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Automatically remove the background from any image, producing a transparent PNG with clean, precise cutouts around complex edges like hair and fur.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'remove-background';
	}

	/**
	 * Field schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'image' => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Image', 'zoviz-ai-studio' ),
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
