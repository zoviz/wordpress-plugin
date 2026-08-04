<?php
/**
 * Image Generator 2 service.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Generates images from text descriptions. JSON request format, two credits
 * per request, curated set of exact output dimensions.
 */
final class ImageGenerator2Service extends AbstractAsyncService {

	/**
	 * The exact dimension values accepted by the API.
	 *
	 * @var string[]
	 */
	const DIMENSIONS = array(
		'1024x1024',
		'1152x896',
		'896x1152',
		'1344x768',
		'768x1344',
		'1536x640',
	);

	/**
	 * Service id.
	 *
	 * @return string
	 */
	public function id() {
		return 'image-generator-2';
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Image Generator', 'zoviz-ai-studio' );
	}

	/**
	 * Translated description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Generate high-quality images from text descriptions, with a curated set of cinematic aspect ratios from square to ultra-wide 21:9.', 'zoviz-ai-studio' );
	}

	/**
	 * Remote endpoint path.
	 *
	 * @return string
	 */
	public function endpoint() {
		return 'image-generator-2';
	}

	/**
	 * Request encoding — this service is JSON, unlike the multipart default.
	 *
	 * @return string
	 */
	public function request_format() {
		return 'json';
	}

	/**
	 * Credits per request.
	 *
	 * @return int
	 */
	public function credit_cost() {
		return 2;
	}

	/**
	 * Field schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'prompt'    => array(
				'type'     => 'string',
				'required' => true,
				'label'    => __( 'Prompt', 'zoviz-ai-studio' ),
			),
			'dimension' => array(
				'type'     => 'enum',
				'required' => false,
				'default'  => '1024x1024',
				'options'  => self::DIMENSIONS,
				'label'    => __( 'Dimensions', 'zoviz-ai-studio' ),
			),
		);
	}

	/**
	 * Capabilities — pure generation, no source image.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities() {
		return array_merge( parent::capabilities(), array( 'source' => 'none' ) );
	}
}
