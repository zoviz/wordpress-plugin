<?php

namespace Zoviz\Tests\Integration;

use Zoviz\DeveloperApi\Jobs\Job;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\Infrastructure\Database\Schema;

class JobRepositoryTest extends \WP_UnitTestCase {

	/** @var JobRepository */
	private $repository;

	public function set_up() {
		parent::set_up();
		Schema::install();
		$this->repository = new JobRepository();
	}

	private function make_job( array $overrides = array() ): int {
		static $counter = 0;
		++$counter;

		return $this->repository->insert(
			array_merge(
				array(
					'remote_job_id' => 'job_' . $counter . '_' . uniqid(),
					'api_key_id'    => 'k_testkey',
					'service'       => 'background-remover',
					'status'        => Job::STATUS_QUEUED,
					'created_by'    => 1,
				),
				$overrides
			)
		);
	}

	public function test_insert_and_find_roundtrip() {
		$id = $this->make_job(
			array(
				'remote_job_id' => 'job_roundtrip',
				'context'       => 'media',
				'params'        => array( 'prompt' => 'hello' ),
			)
		);

		$this->assertGreaterThan( 0, $id );

		$job = $this->repository->find( $id );

		$this->assertNotNull( $job );
		$this->assertSame( 'job_roundtrip', $job->remote_job_id() );
		$this->assertSame( 'background-remover', $job->service() );
		$this->assertSame( 'media', $job->context() );
		$this->assertSame( array( 'prompt' => 'hello' ), $job->params() );
		$this->assertTrue( $job->is_pending() );
		$this->assertNotSame( '', $job->created_at() );
	}

	public function test_find_by_remote_id() {
		$this->make_job( array( 'remote_job_id' => 'job_remote_lookup' ) );

		$job = $this->repository->find_by_remote_id( 'job_remote_lookup' );

		$this->assertNotNull( $job );
		$this->assertSame( 'job_remote_lookup', $job->remote_job_id() );
		$this->assertNull( $this->repository->find_by_remote_id( 'job_missing' ) );
	}

	public function test_update_refreshes_status_and_updated_at() {
		$id = $this->make_job();

		$this->assertTrue(
			$this->repository->update(
				$id,
				array(
					'status'       => Job::STATUS_SUCCEEDED,
					'credits_used' => 1,
					'content_type' => 'image/png',
					'finished_at'  => gmdate( 'Y-m-d H:i:s' ),
				)
			)
		);

		$job = $this->repository->find( $id );

		$this->assertSame( Job::STATUS_SUCCEEDED, $job->status() );
		$this->assertSame( 1, $job->credits_used() );
		$this->assertFalse( $job->is_pending() );
	}

	public function test_query_filters_and_paginates() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->make_job( array( 'service' => 'background-remover' ) );
		}
		$this->make_job(
			array(
				'service' => 'image-upscaler',
				'status'  => Job::STATUS_FAILED,
			)
		);
		$this->make_job( array( 'created_by' => 99 ) );

		$all = $this->repository->query();
		$this->assertSame( 5, $all['total'] );

		$failed = $this->repository->query( array( 'status' => array( Job::STATUS_FAILED ) ) );
		$this->assertSame( 1, $failed['total'] );
		$this->assertSame( 'image-upscaler', $failed['jobs'][0]->service() );

		$mine = $this->repository->query( array( 'created_by' => 99 ) );
		$this->assertSame( 1, $mine['total'] );

		$paged = $this->repository->query(
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);
		$this->assertSame( 5, $paged['total'] );
		$this->assertCount( 2, $paged['jobs'] );

		// Newest first: page 2 items are older than page 1 items.
		$page1 = $this->repository->query(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);
		$this->assertGreaterThan( $paged['jobs'][0]->id(), $page1['jobs'][0]->id() );
	}

	public function test_pending_returns_only_waiting_jobs() {
		$this->make_job( array( 'status' => Job::STATUS_QUEUED ) );
		$this->make_job( array( 'status' => Job::STATUS_RUNNING ) );
		$this->make_job( array( 'status' => Job::STATUS_SUCCEEDED ) );
		$this->make_job( array( 'status' => Job::STATUS_PENDING_SUBMIT ) );

		$pending = $this->repository->pending();

		$this->assertCount( 2, $pending );
	}

	public function test_prune_removes_old_finished_rows_only() {
		$old_finished = $this->make_job( array( 'status' => Job::STATUS_SUCCEEDED ) );
		$old_pending  = $this->make_job( array( 'status' => Job::STATUS_QUEUED ) );
		$fresh        = $this->make_job( array( 'status' => Job::STATUS_FAILED ) );

		// Age two rows by 100 days.
		$old_date = gmdate( 'Y-m-d H:i:s', time() - 100 * DAY_IN_SECONDS );
		$this->repository->update( $old_finished, array( 'created_at' => $old_date ) );
		$this->repository->update( $old_pending, array( 'created_at' => $old_date ) );

		$deleted = $this->repository->prune( 90 );

		$this->assertSame( 1, $deleted );
		$this->assertNull( $this->repository->find( $old_finished ) );
		$this->assertNotNull( $this->repository->find( $old_pending ) );
		$this->assertNotNull( $this->repository->find( $fresh ) );
	}

	public function test_delete() {
		$id = $this->make_job();

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->find( $id ) );
		$this->assertFalse( $this->repository->delete( $id ) );
	}
}
