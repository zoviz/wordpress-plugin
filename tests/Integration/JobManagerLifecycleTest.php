<?php

namespace Zoviz\Tests\Integration;

use Zoviz\DeveloperApi\Exception\ResultExpiredException;
use Zoviz\DeveloperApi\Jobs\Job;
use Zoviz\DeveloperApi\Jobs\JobManager;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Infrastructure\Database\Schema;
use Zoviz\Kernel\Plugin;
use Zoviz\Tests\Integration\Support\FakesZovizApi;

class JobManagerLifecycleTest extends \WP_UnitTestCase {

	use FakesZovizApi;

	/** @var JobManager */
	private $manager;

	/** @var JobRepository */
	private $repository;

	/** @var KeyRepository */
	private $keys;

	public function set_up() {
		parent::set_up();
		Schema::install();
		$this->fake_zoviz_api();

		$container        = Plugin::instance()->container();
		$this->manager    = $container->get( JobManager::class );
		$this->repository = $container->get( JobRepository::class );
		$this->keys       = $container->get( KeyRepository::class );

		// Store a key directly (validation is KeyManager's job, not under test).
		$key = $this->keys->insert( 'Test workspace', 'zv_secret_test_1a2b' );
		$this->keys->set_default( $key->id() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function image_files(): array {
		$tmp = wp_tempnam( 'zoviz-input' );
		copy( dirname( __DIR__ ) . '/Fixtures/result-1px.png', $tmp );

		return array(
			'image' => array(
				'path'     => $tmp,
				'filename' => 'input.png',
				'mime'     => 'image/png',
			),
		);
	}

	public function test_submit_creates_local_row_with_remote_id() {
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );

		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array( 'context' => 'media' ) );

		$this->assertSame( 'job_2f7c9a1e', $job->remote_job_id() );
		$this->assertSame( Job::STATUS_QUEUED, $job->status() );
		$this->assertSame( 'media', $job->context() );
		$this->assertSame( get_current_user_id(), $job->created_by() );

		// The API received a multipart POST with the Bearer header.
		$request = end( $this->zoviz_requests );
		$this->assertStringContainsString( '/api/v1/remove-background', $request['url'] );
		$this->assertSame( 'Bearer zv_secret_test_1a2b', $request['args']['headers']['Authorization'] );
	}

	public function test_refresh_syncs_success_and_auto_downloads_to_media() {
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );
		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array() );

		// Poll: succeeded, then the auto-download streams the binary result.
		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );

		$job = $this->manager->refresh( $job );

		$this->assertSame( Job::STATUS_SUCCEEDED, $job->status() );
		$this->assertSame( 1, $job->credits_used() );
		$this->assertGreaterThan( 0, $job->attachment_id() );

		// The attachment is real and carries provenance meta.
		$attachment_id = $job->attachment_id();
		$this->assertSame( 'attachment', get_post_type( $attachment_id ) );
		$this->assertSame( 'job_2f7c9a1e', get_post_meta( $attachment_id, '_zoviz_job_id', true ) );
		$this->assertSame( 'background-remover', get_post_meta( $attachment_id, '_zoviz_service', true ) );
		$this->assertFileExists( get_attached_file( $attachment_id ) );
	}

	public function test_refresh_records_failure_without_attachment() {
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );
		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array() );

		$this->queue_zoviz_fixture( 200, 'job-failed.json' );

		$job = $this->manager->refresh( $job );

		$this->assertSame( Job::STATUS_FAILED, $job->status() );
		$this->assertSame( 0, $job->attachment_id() );
		$this->assertNotSame( '', $job->error_message() );
	}

	public function test_save_to_media_is_idempotent() {
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );
		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array() );

		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );
		$job = $this->manager->refresh( $job );

		$first = $job->attachment_id();

		// No further HTTP request may happen; the local copy is served.
		$requests_before = count( $this->zoviz_requests );
		$second          = $this->manager->save_to_media( $this->repository->find( $job->id() ) );

		$this->assertSame( $first, $second );
		$this->assertCount( $requests_before, $this->zoviz_requests );
	}

	public function test_save_to_media_marks_job_expired_when_remote_result_gone() {
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );
		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array() );

		// Job succeeded, but auto-download is off and the result later expires.
		update_option( 'zoviz_settings', array( 'auto_download' => false ) );
		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$job = $this->manager->refresh( $job );

		$this->assertSame( Job::STATUS_SUCCEEDED, $job->status() );
		$this->assertSame( 0, $job->attachment_id() );

		// Simulate remote expiry.
		$this->repository->update( $job->id(), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );

		$this->expectException( ResultExpiredException::class );

		try {
			$this->manager->save_to_media( $this->repository->find( $job->id() ) );
		} finally {
			$this->assertSame( Job::STATUS_EXPIRED, $this->repository->find( $job->id() )->status() );
		}
	}

	public function test_save_to_media_assigns_featured_image() {
		$post_id = self::factory()->post->create();

		$this->queue_zoviz_fixture( 202, 'job-queued.json' );
		$job = $this->manager->submit( 'background-remover', array(), $this->image_files(), array() );

		update_option( 'zoviz_settings', array( 'auto_download' => false ) );
		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$job = $this->manager->refresh( $job );

		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );
		$attachment_id = $this->manager->save_to_media(
			$this->repository->find( $job->id() ),
			array(
				'assign' => array(
					'type'    => 'featured',
					'post_id' => $post_id,
				),
			)
		);

		$this->assertSame( $attachment_id, (int) get_post_thumbnail_id( $post_id ) );
	}
}
