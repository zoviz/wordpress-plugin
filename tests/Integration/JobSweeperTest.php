<?php

namespace Zoviz\Tests\Integration;

use Zoviz\DeveloperApi\Jobs\Job;
use Zoviz\DeveloperApi\Jobs\JobRepository;
use Zoviz\DeveloperApi\Jobs\JobSweeper;
use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\Infrastructure\Database\Schema;
use Zoviz\Kernel\Plugin;
use Zoviz\Tests\Integration\Support\FakesZovizApi;

class JobSweeperTest extends \WP_UnitTestCase {

	use FakesZovizApi;

	/** @var JobSweeper */
	private $sweeper;

	/** @var JobRepository */
	private $repository;

	public function set_up() {
		parent::set_up();
		Schema::install();
		$this->fake_zoviz_api();

		$container        = Plugin::instance()->container();
		$this->sweeper    = $container->get( JobSweeper::class );
		$this->repository = $container->get( JobRepository::class );

		$keys = $container->get( KeyRepository::class );
		$key  = $keys->insert( 'Test', 'zv_secret_test_1a2b' );
		$keys->set_default( $key->id() );

		$this->key_id = $key->id();
	}

	/** @var string */
	private $key_id;

	public function test_sweep_finalizes_abandoned_job_into_media_library() {
		// An abandoned job: submitted earlier, browser never polled it.
		$id = $this->repository->insert(
			array(
				'remote_job_id' => 'job_2f7c9a1e',
				'api_key_id'    => $this->key_id,
				'service'       => 'background-remover',
				'status'        => Job::STATUS_QUEUED,
				'created_by'    => 1,
			)
		);

		$this->queue_zoviz_fixture( 200, 'job-succeeded.json' );
		$this->queue_zoviz_binary( 'result-1px.png', 'image/png' );

		$refreshed = $this->sweeper->sweep();

		$this->assertSame( 1, $refreshed );

		$job = $this->repository->find( $id );
		$this->assertSame( Job::STATUS_SUCCEEDED, $job->status() );
		$this->assertGreaterThan( 0, $job->attachment_id() );
	}

	public function test_sweep_ignores_terminal_jobs() {
		$this->repository->insert(
			array(
				'remote_job_id' => 'job_done',
				'api_key_id'    => $this->key_id,
				'service'       => 'background-remover',
				'status'        => Job::STATUS_SUCCEEDED,
				'created_by'    => 1,
			)
		);

		$this->assertSame( 0, $this->sweeper->sweep() );
		$this->assertCount( 0, $this->zoviz_requests );
	}

	public function test_prune_respects_retention_filter() {
		$id = $this->repository->insert(
			array(
				'remote_job_id' => 'job_old',
				'api_key_id'    => $this->key_id,
				'service'       => 'background-remover',
				'status'        => Job::STATUS_FAILED,
				'created_by'    => 1,
			)
		);
		$this->repository->update( $id, array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ) ) );

		add_filter( 'zoviz_jobs_retention_days', static fn() => 5 );

		$this->assertSame( 1, $this->sweeper->prune() );
		$this->assertNull( $this->repository->find( $id ) );
	}

	public function test_cron_events_schedule_and_unschedule() {
		JobSweeper::schedule();

		$this->assertNotFalse( wp_next_scheduled( JobSweeper::HOOK_SWEEP ) );
		$this->assertNotFalse( wp_next_scheduled( JobSweeper::HOOK_PRUNE ) );

		JobSweeper::unschedule();

		$this->assertFalse( wp_next_scheduled( JobSweeper::HOOK_SWEEP ) );
		$this->assertFalse( wp_next_scheduled( JobSweeper::HOOK_PRUNE ) );
	}

	public function test_custom_cron_interval_is_registered() {
		$schedules = wp_get_schedules();

		$this->assertArrayHasKey( JobSweeper::INTERVAL_SWEEP, $schedules );
		$this->assertSame( 2 * MINUTE_IN_SECONDS, $schedules[ JobSweeper::INTERVAL_SWEEP ]['interval'] );
	}
}
