<?php
/**
 * Product Photography service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Generates studio-quality product photography from a simple product image,
 * with automatic scene, lighting, and shadow styling.
 */
final class ProductPhotographyService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'product-photography';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Product Photography', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Turn a simple product photo into a studio-quality scene with professional lighting, shadows, and background styling.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'product-photography';
	}

	/**
	 * Possible result content types.
	 *
	 * @return string[]
	 */
	public function output_content_types() {
		return array( 'image/jpeg', 'image/png', 'image/webp' );
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
				'label'    => __( 'Product image', 'zoviz-ai-studio' ),
			),
		);
	}
}
