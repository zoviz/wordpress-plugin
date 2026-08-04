<?php
/**
 * Object Remover service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Removes unwanted objects from images: paint white over the object and the
 * surrounding area is seamlessly filled in.
 */
final class ObjectRemoverService extends AbstractAsyncService {

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'object-remover';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Object Remover', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Remove unwanted objects, people, or text from images. Paint over the object to remove and the area is filled in seamlessly.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'object-remover';
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
			'mask'  => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Mask', 'zoviz-ai-studio' ),
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
