<?php

namespace Zoviz\Tests\Integration;

use Zoviz\DeveloperApi\Admin\MediaLibraryIntegration;
use Zoviz\DeveloperApi\Admin\Menu;
use Zoviz\DeveloperApi\Jobs\Job;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\Infrastructure\Database\Schema;
use Zoviz\Kernel\Plugin;

class MediaLibraryIntegrationTest extends \WP_UnitTestCase {

	/** @var MediaLibraryIntegration */
	private $integration;

	/** @var JobRepository */
	private $jobs;

	public function set_up() {
		parent::set_up();
		Schema::install();

		$container         = Plugin::instance()->container();
		$this->integration = $container->get( MediaLibraryIntegration::class );
		$this->jobs        = $container->get( JobRepository::class );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function create_attachment( $mime_type = 'image/png' ) {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'zoviz-test.png',
				'post_mime_type' => $mime_type,
				'post_type'      => 'attachment',
			)
		);

		return get_post( $id );
	}

	public function test_eligible_attachment_gets_workspace_row_action() {
		$attachment = $this->create_attachment();

		$actions = $this->integration->add_row_actions( array(), $attachment );

		$this->assertArrayHasKey( 'zoviz', $actions );
		$this->assertStringContainsString( 'page=' . Menu::SLUG_WORKSPACE, $actions['zoviz'] );
		$this->assertStringContainsString( 'attachment=' . $attachment->ID, $actions['zoviz'] );
	}

	public function test_non_image_attachment_gets_no_row_action() {
		$attachment = $this->create_attachment( 'application/pdf' );

		$actions = $this->integration->add_row_actions( array(), $attachment );

		$this->assertArrayNotHasKey( 'zoviz', $actions );
	}

	public function test_js_data_reports_eligibility_and_job_count() {
		$attachment = $this->create_attachment();

		$this->jobs->insert(
			array(
				'remote_job_id'        => 'job_' . uniqid(),
				'api_key_id'           => 'k_testkey',
				'service'              => 'background-remover',
				'status'               => Job::STATUS_SUCCEEDED,
				'source_attachment_id' => $attachment->ID,
				'created_by'           => get_current_user_id(),
			)
		);

		$response = $this->integration->add_js_data( array(), $attachment );

		$this->assertSame(
			array(
				'eligible' => true,
				'jobs'     => 1,
			),
			$response['zoviz']
		);
	}
}
