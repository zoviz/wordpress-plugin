<?php

namespace Zoviz\Tests\Integration\Rest;

use Zoviz\Tests\Integration\Support\RestTestCase;

class JobsControllerTest extends RestTestCase {

	public function set_up() {
		parent::set_up();
		$this->store_key();
	}

	private function submit_job_as( int $user_id ): array {
		wp_set_current_user( $user_id );
		$this->queue_zoviz_json(
			202,
			array(
				'job_id'     => 'job_' . uniqid(),
				'sync_mode'  => false,
				'status'     => 'queued',
				'created_at' => '2026-08-03T10:00:00.000Z',
			)
		);

		$response = $this->request(
			'POST',
			'/jobs',
			array(
				'service'       => 'background-remover',
				'attachment_id' => $this->fixture_attachment(),
			)
		);

		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );

		return $response->get_data();
	}

	public function test_subscriber_cannot_submit_jobs() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->request( 'POST', '/jobs', array( 'service' => 'background-remover' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_author_submits_job_from_attachment() {
		$data = $this->submit_job_as( $this->author_id );

		$this->assertStringStartsWith( 'job_', $data['remote_job_id'] );
		$this->assertSame( 'queued', $data['status'] );
		$this->assertSame( $this->author_id, $data['created_by'] );
		$this->assertGreaterThan( 0, $data['source_attachment_id'] );
	}

	public function test_unknown_service_is_rejected_by_schema() {
		wp_set_current_user( $this->author_id );

		$response = $this->request( 'POST', '/jobs', array( 'service' => 'nope' ) );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_missing_image_returns_validation_error() {
		wp_set_current_user( $this->author_id );

		$response = $this->request( 'POST', '/jobs', array( 'service' => 'background-remover' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'zoviz_invalid_request', $response->get_data()['code'] );
	}

	public function test_insufficient_credits_carries_buy_url() {
		wp_set_current_user( $this->author_id );
		$this->queue_zoviz_fixture( 402, 'error-402.json' );

		$response = $this->request(
			'POST',
			'/jobs',
			array(
				'service'       => 'background-remover',
				'attachment_id' => $this->fixture_attachment(),
			)
		);

		$this->assertSame( 402, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'zoviz_insufficient_credits', $data['code'] );
		$this->assertSame(
			'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
			$data['data']['buy_url']
		);
	}

	public function test_generator_accepts_prompt_without_image() {
		wp_set_current_user( $this->author_id );
		$this->queue_zoviz_fixture( 202, 'job-queued.json' );

		$response = $this->request(
			'POST',
			'/jobs',
			array(
				'service'   => 'image-generator-2',
				'prompt'    => 'A snowy japanese village at dusk',
				'dimension' => '1344x768',
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '1344x768', $response->get_data()['params']['dimension'] );
	}

	public function test_list_returns_own_jobs_only() {
		$this->submit_job_as( $this->author_id );
		$this->submit_job_as( $this->admin_id );

		wp_set_current_user( $this->author_id );
		$response = $this->request( 'GET', '/jobs' );

		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( $this->author_id, $response->get_data()[0]['created_by'] );
	}

	public function test_scope_all_requires_admin() {
		$this->submit_job_as( $this->author_id );
		$this->submit_job_as( $this->admin_id );

		wp_set_current_user( $this->author_id );
		$this->assertSame( 403, $this->request( 'GET', '/jobs', array( 'scope' => 'all' ) )->get_status() );

		wp_set_current_user( $this->admin_id );
		$response = $this->request( 'GET', '/jobs', array( 'scope' => 'all' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
		$this->assertSame( '2', $response->get_headers()['X-WP-Total'] );
	}

	public function test_get_item_refreshes_pending_job() {
		$data = $this->submit_job_as( $this->author_id );

		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );

		$response = $this->request( 'GET', '/jobs/' . $data['id'] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'succeeded', $response->get_data()['status'] );
		$this->assertGreaterThan( 0, $response->get_data()['attachment_id'] );
		$this->assertArrayHasKey( 'attachment_url', $response->get_data() );
	}

	public function test_attachment_exists_reflects_manual_deletion() {
		$data = $this->submit_job_as( $this->author_id );

		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );

		$response      = $this->request( 'GET', '/jobs/' . $data['id'] );
		$attachment_id = $response->get_data()['attachment_id'];

		$this->assertTrue( $response->get_data()['attachment_exists'] );
		$this->assertNotSame( '', $response->get_data()['attachment_url'] );

		// The Media Library entry gets deleted out-of-band (not via pruning).
		wp_delete_attachment( $attachment_id, true );

		$response = $this->request( 'GET', '/jobs/' . $data['id'], array( 'refresh' => false ) );

		$this->assertFalse( $response->get_data()['attachment_exists'] );
		$this->assertSame( '', $response->get_data()['attachment_url'] );
		$this->assertSame( '', $response->get_data()['attachment_edit'] );

		// The list endpoint reports the same, deleted-attachment state.
		$list = $this->request( 'GET', '/jobs' );

		$this->assertFalse( $list->get_data()[0]['attachment_exists'] );
	}

	public function test_get_item_denies_non_owner() {
		$data = $this->submit_job_as( $this->author_id );

		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $other );

		$response = $this->request( 'GET', '/jobs/' . $data['id'], array( 'refresh' => false ) );

		$this->assertSame( 403, $response->get_status() );

		// Administrators may access any job.
		wp_set_current_user( $this->admin_id );
		$this->assertSame( 200, $this->request( 'GET', '/jobs/' . $data['id'], array( 'refresh' => false ) )->get_status() );
	}

	public function test_save_assign_requires_edit_permission_on_target() {
		update_option( 'zoviz_settings', array( 'auto_download' => false ) );

		$data = $this->submit_job_as( $this->author_id );
		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->request( 'GET', '/jobs/' . $data['id'] );

		// A post the author cannot edit.
		$admins_post = self::factory()->post->create( array( 'post_author' => $this->admin_id ) );

		$response = $this->request(
			'POST',
			'/jobs/' . $data['id'] . '/save',
			array(
				'assign' => array(
					'type'    => 'featured',
					'post_id' => $admins_post,
				),
			)
		);

		$this->assertSame( 403, $response->get_status() );

		// The author's own post works.
		$own_post = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );

		$response = $this->request(
			'POST',
			'/jobs/' . $data['id'] . '/save',
			array(
				'assign' => array(
					'type'    => 'featured',
					'post_id' => $own_post,
				),
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame(
			$response->get_data()['attachment_id'],
			(int) get_post_thumbnail_id( $own_post )
		);
	}

	public function test_delete_job_row() {
		$data = $this->submit_job_as( $this->author_id );

		$response = $this->request( 'DELETE', '/jobs/' . $data['id'] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 404, $this->request( 'GET', '/jobs/' . $data['id'] )->get_status() );
	}
}
