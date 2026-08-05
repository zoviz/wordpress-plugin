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
 * Browser-side polling is the primary finalizer. This backstop is
 * deliberately admin-only: `maybe_run()` is hooked to `admin_init`, never
 * to real WP-Cron, so it only ever runs as a side effect of someone
 * visiting wp-admin — a public front-end pageview never triggers it. A
 * pair of short-lived transients throttle it to once per interval, per
 * hook, and double as a lock against concurrent admin requests.
 */
final class JobSweeper {

	/**
	 * Cron-style hook names the sweep/prune work is fired through. No real
	 * WP-Cron event is ever scheduled against these — `maybe_run()` calls
	 * `do_action()` on them directly from `admin_init`.
	 */
	const HOOK_SWEEP = 'zoviz_sweep_jobs';
	const HOOK_PRUNE = 'zoviz_prune_jobs';

	/**
	 * Transient keys used to throttle/lock the admin-triggered runs.
	 */
	const LOCK_SWEEP = 'zoviz_sweep_lock';
	const LOCK_PRUNE = 'zoviz_prune_lock';

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
	 * Fires the sweep/prune hooks, throttled to once per interval and
	 * locked against concurrent runs, via a pair of transients. Hooked to
	 * `admin_init` only — this is what makes the backstop admin-only.
	 *
	 * @return void
	 */
	public static function maybe_run() {
		if ( false === get_transient( self::LOCK_SWEEP ) ) {
			set_transient( self::LOCK_SWEEP, 1, 2 * MINUTE_IN_SECONDS );
			do_action( self::HOOK_SWEEP ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- HOOK_SWEEP is the 'zoviz_sweep_jobs' constant; the sniff can't resolve it through self::.
		}

		if ( false === get_transient( self::LOCK_PRUNE ) ) {
			set_transient( self::LOCK_PRUNE, 1, DAY_IN_SECONDS );
			do_action( self::HOOK_PRUNE ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- HOOK_PRUNE is the 'zoviz_prune_jobs' constant; the sniff can't resolve it through self::.
		}
	}

	/**
	 * Clears any real WP-Cron events a pre-admin-only version of this
	 * plugin may have scheduled. Safe to call even if none were ever
	 * scheduled; run on both activation and deactivation so neither a
	 * fresh install nor an in-place upgrade is left with a stray
	 * perpetually-rescheduling event.
	 *
	 * @return void
	 */
	public static function unschedule_legacy_cron() {
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
