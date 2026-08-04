<?php
/**
 * Cron sweeper for abandoned jobs.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Jobs;

use Zoviz\DeveloperApi\Settings;

/**
 * Backstop for jobs the browser stopped polling: refreshes pending jobs
 * (which auto-downloads succeeded results into the Media Library before
 * the remote copy expires), marks expired results, and prunes old rows.
 *
 * Browser-side polling is the primary finalizer — WP-Cron only fires on
 * traffic, so this is deliberately a safety net, time-boxed per run.
 */
final class JobSweeper {

	/**
	 * Cron hook names and the custom interval id.
	 */
	const HOOK_SWEEP     = 'zoviz_sweep_jobs';
	const HOOK_PRUNE     = 'zoviz_prune_jobs';
	const INTERVAL_SWEEP = 'zoviz_two_minutes';

	/**
	 * Maximum jobs refreshed per sweep run.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 25;

	/**
	 * Maximum seconds a sweep run may take.
	 *
	 * @var int
	 */
	const TIME_BUDGET = 20;

	/**
	 * Job manager.
	 *
	 * @var JobManager
	 */
	private $manager;

	/**
	 * Job repository.
	 *
	 * @var JobRepository
	 */
	private $repository;

	/**
	 * Settings accessor.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param JobManager    $manager    Job manager.
	 * @param JobRepository $repository Job repository.
	 * @param Settings      $settings   Settings accessor.
	 */
	public function __construct( JobManager $manager, JobRepository $repository, Settings $settings ) {
		$this->manager    = $manager;
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	/**
	 * Adds the custom cron interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Registered schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_interval( $schedules ) {
		$schedules[ self::INTERVAL_SWEEP ] = array(
			'interval' => 2 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every two minutes (Zoviz job sweeper)', 'zoviz-ai-studio' ),
		);

		return $schedules;
	}

	/**
	 * Schedules the cron events (activation).
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK_SWEEP ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL_SWEEP, self::HOOK_SWEEP );
		}

		if ( ! wp_next_scheduled( self::HOOK_PRUNE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_PRUNE );
		}
	}

	/**
	 * Unschedules the cron events (deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK_SWEEP );
		wp_clear_scheduled_hook( self::HOOK_PRUNE );
	}

	/**
	 * Refreshes pending jobs, time-boxed and row-limited. Idempotent.
	 *
	 * @return int Number of jobs refreshed.
	 */
	public function sweep() {
		$deadline  = time() + self::TIME_BUDGET;
		$refreshed = 0;

		foreach ( $this->repository->pending( self::BATCH_SIZE ) as $job ) {
			if ( time() >= $deadline ) {
				break;
			}

			$this->manager->refresh( $job );
			++$refreshed;
		}

		return $refreshed;
	}

	/**
	 * Prunes finished job rows past the retention window.
	 *
	 * @return int Number of deleted rows.
	 */
	public function prune() {
		/**
		 * Filters how many days finished Zoviz job rows are retained.
		 *
		 * @since 0.1.0
		 *
		 * @param int $days Retention in days.
		 */
		$days = (int) apply_filters( 'zoviz_jobs_retention_days', (int) $this->settings->get( 'retention_days' ) );

		return $this->repository->prune( $days );
	}
}
