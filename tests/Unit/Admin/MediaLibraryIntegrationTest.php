<?php

namespace Zoviz\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Zoviz\DeveloperApi\Admin\MediaLibraryIntegration;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Services\BackgroundRemoverService;
use Zoviz\DeveloperApi\Services\ImageEditorService;
use Zoviz\DeveloperApi\Services\ImageUpscalerService;
use Zoviz\DeveloperApi\Services\ObjectRemoverService;
use Zoviz\DeveloperApi\Services\ProductPhotographyService;
use Zoviz\DeveloperApi\Services\ServiceRegistry;
use Zoviz\DeveloperApi\Services\SketchToImageService;
use Zoviz\Kernel\Assets;
use Zoviz\Tests\Unit\Support\WpPost;
use Zoviz\Tests\Unit\TestCase;

class MediaLibraryIntegrationTest extends TestCase {

	private function full_registry(): ServiceRegistry {
		$registry = new ServiceRegistry();
		$registry->register( new BackgroundRemoverService() );
		$registry->register( new ImageEditorService() );
		$registry->register( new ImageUpscalerService() );
		$registry->register( new ObjectRemoverService() );
		$registry->register( new ProductPhotographyService() );
		$registry->register( new SketchToImageService() );

		return $registry;
	}

	/**
	 * Assets is `final` and needs a booted Plugin kernel to construct
	 * normally; none of these tests exercise enqueue(), so a Plugin-less
	 * instance built via reflection is a fine stand-in.
	 */
	private function fake_assets(): Assets {
		return ( new \ReflectionClass( Assets::class ) )->newInstanceWithoutConstructor();
	}

	private function stub_url_functions(): void {
		Functions\when( 'admin_url' )->alias(
			static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
	}

	public function test_ineligible_mime_gets_no_row_actions() {
		$integration = new MediaLibraryIntegration( $this->full_registry(), new JobRepository(), $this->fake_assets() );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_mime_type' )->justReturn( 'application/pdf' );

		$actions = $integration->add_row_actions( array( 'edit' => '<a>Edit</a>' ), new WpPost( 42, 'application/pdf' ) );

		$this->assertSame( array( 'edit' => '<a>Edit</a>' ), $actions );
	}

	public function test_unprivileged_user_gets_no_row_actions() {
		$integration = new MediaLibraryIntegration( $this->full_registry(), new JobRepository(), $this->fake_assets() );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'get_post_mime_type' )->justReturn( 'image/png' );

		$actions = $integration->add_row_actions( array(), new WpPost( 42, 'image/png' ) );

		$this->assertSame( array(), $actions );
	}

	public function test_eligible_image_gets_quick_and_generic_row_actions() {
		$integration = new MediaLibraryIntegration( $this->full_registry(), new JobRepository(), $this->fake_assets() );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_mime_type' )->justReturn( 'image/png' );
		$this->stub_url_functions();

		$actions = $integration->add_row_actions( array(), new WpPost( 42, 'image/png' ) );

		// Only services needing nothing but the source image qualify as
		// one-click shortcuts: background-remover and product-photography.
		// image-editor/object-remover need a mask, image-upscaler needs
		// target dimensions, sketch-to-image isn't source=image.
		$this->assertSame(
			array( 'zoviz-background-remover', 'zoviz-product-photography', 'zoviz' ),
			array_keys( $actions )
		);
		$this->assertStringContainsString( 'attachment=42', $actions['zoviz'] );
		$this->assertStringContainsString( 'service=background-remover', $actions['zoviz-background-remover'] );
	}

	public function test_attachment_field_renders_action_buttons() {
		$integration = new MediaLibraryIntegration( $this->full_registry(), new JobRepository(), $this->fake_assets() );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_mime_type' )->justReturn( 'image/png' );
		$this->stub_url_functions();

		$fields = $integration->add_attachment_field( array(), new WpPost( 7, 'image/png' ) );

		$this->assertArrayHasKey( 'zoviz', $fields );
		$this->assertSame( 'html', $fields['zoviz']['input'] );
		$this->assertStringContainsString( 'attachment=7', $fields['zoviz']['html'] );
	}
}
