<?php

namespace Zoviz\Tests\Unit\Services;

use Zoviz\DeveloperApi\Exception\ValidationException;
use Zoviz\DeveloperApi\Services\BackgroundRemoverService;
use Zoviz\DeveloperApi\Services\ImageEditorService;
use Zoviz\DeveloperApi\Services\ImageGenerator2Service;
use Zoviz\DeveloperApi\Services\ImageUpscalerService;
use Zoviz\DeveloperApi\Services\ObjectRemoverService;
use Zoviz\DeveloperApi\Services\ProductPhotographyService;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\DeveloperApi\Services\SketchToImageService;
use Zoviz\Tests\Unit\TestCase;

class ServicesTest extends TestCase {

	private function png_file(): array {
		return array(
			'path'     => dirname( __DIR__, 2 ) . '/Fixtures/result-1px.png',
			'filename' => 'input.png',
			'mime'     => 'image/png',
		);
	}

	public function test_registry_holds_all_seven_builtin_services() {
		$registry = new ServiceRegistry();

		foreach ( array(
			new BackgroundRemoverService(),
			new ImageEditorService(),
			new ImageGenerator2Service(),
			new ImageUpscalerService(),
			new ObjectRemoverService(),
			new ProductPhotographyService(),
			new SketchToImageService(),
		) as $service ) {
			$registry->register( $service );
		}

		$this->assertSame(
			array(
				'background-remover',
				'image-editor',
				'image-generator-2',
				'image-upscaler',
				'object-remover',
				'product-photography',
				'sketch-to-image',
			),
			$registry->ids()
		);
	}

	public function test_registry_rejects_duplicate_ids() {
		$registry = new ServiceRegistry();
		$registry->register( new BackgroundRemoverService() );

		$this->expectException( \InvalidArgumentException::class );

		$registry->register( new BackgroundRemoverService() );
	}

	public function test_background_remover_requires_image() {
		$this->expectException( ValidationException::class );

		( new BackgroundRemoverService() )->prepare_request( array(), array() );
	}

	public function test_background_remover_accepts_valid_image() {
		$payload = ( new BackgroundRemoverService() )->prepare_request(
			array(),
			array( 'image' => $this->png_file() )
		);

		$this->assertSame( array(), $payload['fields'] );
		$this->assertArrayHasKey( 'image', $payload['files'] );
	}

	public function test_unsupported_mime_is_rejected() {
		$file         = $this->png_file();
		$file['mime'] = 'image/gif';

		$this->expectException( ValidationException::class );

		( new BackgroundRemoverService() )->prepare_request( array(), array( 'image' => $file ) );
	}

	public function test_image_editor_requires_image_mask_and_prompt() {
		$service = new ImageEditorService();

		try {
			$service->prepare_request(
				array( 'prompt' => 'Make the sky more vibrant' ),
				array( 'image' => $this->png_file() )
			);
			$this->fail( 'Expected ValidationException for the missing mask.' );
		} catch ( ValidationException $e ) {
			$this->assertStringContainsString( 'mask', $e->getMessage() );
		}

		$payload = $service->prepare_request(
			array( 'prompt' => 'Make the sky more vibrant' ),
			array(
				'image' => $this->png_file(),
				'mask'  => $this->png_file(),
			)
		);

		$this->assertSame( 'Make the sky more vibrant', $payload['fields']['prompt'] );
		$this->assertTrue( $service->capabilities()['mask'] );
	}

	public function test_generator_uses_json_format_and_costs_two_credits() {
		$service = new ImageGenerator2Service();

		$this->assertSame( 'json', $service->request_format() );
		$this->assertSame( 2, $service->credit_cost() );
		$this->assertSame( 'none', $service->capabilities()['source'] );
	}

	public function test_generator_defaults_dimension() {
		$payload = ( new ImageGenerator2Service() )->prepare_request(
			array( 'prompt' => 'A lighthouse' ),
			array()
		);

		$this->assertSame( '1024x1024', $payload['fields']['dimension'] );
	}

	public function test_generator_rejects_custom_dimension() {
		$this->expectException( ValidationException::class );

		( new ImageGenerator2Service() )->prepare_request(
			array(
				'prompt'    => 'A lighthouse',
				'dimension' => '800x600',
			),
			array()
		);
	}

	public function test_generator_accepts_every_documented_dimension() {
		$service = new ImageGenerator2Service();

		foreach ( ImageGenerator2Service::DIMENSIONS as $dimension ) {
			$payload = $service->prepare_request(
				array(
					'prompt'    => 'A lighthouse',
					'dimension' => $dimension,
				),
				array()
			);

			$this->assertSame( $dimension, $payload['fields']['dimension'] );
		}
	}

	public function test_upscaler_endpoint_differs_from_id() {
		$service = new ImageUpscalerService();

		$this->assertSame( 'image-upscaler', $service->id() );
		$this->assertSame( 'image-upscaling', $service->endpoint() );
	}

	public function test_upscaler_requires_dimensions_within_bounds() {
		$service = new ImageUpscalerService();

		$payload = $service->prepare_request(
			array(
				'target_width'  => '2048',
				'target_height' => 2048,
			),
			array( 'image' => $this->png_file() )
		);

		$this->assertSame( '2048', $payload['fields']['target_width'] );
		$this->assertSame( '2048', $payload['fields']['target_height'] );

		$this->expectException( ValidationException::class );

		$service->prepare_request(
			array(
				'target_width'  => 100000,
				'target_height' => 2048,
			),
			array( 'image' => $this->png_file() )
		);
	}

	public function test_sketch_to_image_uses_sketch_field() {
		$service = new SketchToImageService();

		$this->assertArrayHasKey( 'sketch', $service->fields() );
		$this->assertSame( 'sketch', $service->capabilities()['source'] );

		$payload = $service->prepare_request(
			array( 'prompt' => 'A modern house with a garden' ),
			array( 'sketch' => $this->png_file() )
		);

		$this->assertArrayHasKey( 'sketch', $payload['files'] );
	}

	public function test_object_remover_requires_mask() {
		$this->expectException( ValidationException::class );

		( new ObjectRemoverService() )->prepare_request( array(), array( 'image' => $this->png_file() ) );
	}

	public function test_bulk_capability_flags() {
		$this->assertTrue( ( new BackgroundRemoverService() )->capabilities()['bulk'] );
		$this->assertTrue( ( new ImageUpscalerService() )->capabilities()['bulk'] );
		$this->assertFalse( ( new ObjectRemoverService() )->capabilities()['bulk'] );
		$this->assertFalse( ( new ImageEditorService() )->capabilities()['bulk'] );
	}
}
