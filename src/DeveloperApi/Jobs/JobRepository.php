<?php
/**
 * Job persistence.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Jobs;

use Zoviz\Infrastructure\Database\Schema;

/**
 * All database access for the zoviz_jobs table lives in this class — the
 * only place (besides Schema) allowed to touch $wpdb directly.
 */
final class JobRepository {

	/**
	 * Columns that may be written via insert()/update().
	 *
	 * @var string[]
	 */
	const WRITABLE = array(
		'remote_job_id',
		'api_key_id',
		'service',
		'status',
		'content_type',
		'credits_used',
		'attachment_id',
		'source_attachment_id',
		'context',
		'batch_id',
		'params',
		'error_code',
		'error_message',
		'created_by',
		'created_at',
		'updated_at',
		'finished_at',
		'expires_at',
	);

	/**
	 * Inserts a job row. created_at/updated_at default to now (UTC).
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New row id (0 on failure).
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array_merge(
			array(
				'status'     => Job::STATUS_PENDING_SUBMIT,
				'context'    => 'workspace',
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			$data
		);

		if ( isset( $data['params'] ) && is_array( $data['params'] ) ) {
			$data['params'] = wp_json_encode( $data['params'] );
		}

		$data = array_intersect_key( $data, array_flip( self::WRITABLE ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; repository is the single access point.
		$result = $wpdb->insert( Schema::jobs_table(), $data );

		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a job row; updated_at is always refreshed.
	 *
	 * @param int                  $id   Row id.
	 * @param array<string, mixed> $data Column values.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		if ( isset( $data['params'] ) && is_array( $data['params'] ) ) {
			$data['params'] = wp_json_encode( $data['params'] );
		}

		$data               = array_intersect_key( $data, array_flip( self::WRITABLE ) );
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; repository is the single access point.
		$result = $wpdb->update( Schema::jobs_table(), $data, array( 'id' => (int) $id ) );

		return false !== $result;
	}

	/**
	 * Finds a job by local id.
	 *
	 * @param int $id Row id.
	 * @return Job|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = Schema::jobs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; name from a trusted constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );

		return null === $row ? null : Job::from_row( $row );
	}

	/**
	 * Finds a job by its remote id.
	 *
	 * @param string $remote_job_id Remote job id.
	 * @return Job|null
	 */
	public function find_by_remote_id( $remote_job_id ) {
		global $wpdb;

		$table = Schema::jobs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; name from a trusted constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE remote_job_id = %s", $remote_job_id ), ARRAY_A );

		return null === $row ? null : Job::from_row( $row );
	}

	/**
	 * Queries jobs with filters and pagination.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional filters.
	 *
	 *     @type string[] $status     Statuses to include.
	 *     @type string   $service    Service id.
	 *     @type string   $context    Originating surface.
	 *     @type string   $batch_id   Batch id.
	 *     @type int      $created_by Restrict to a user id.
	 *     @type int      $page       1-based page (default 1).
	 *     @type int      $per_page   Page size (default 20, max 100).
	 * }
	 * @return array{jobs: Job[], total: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		// Seed with a prepared no-op so the WHERE clause always has at least
		// one placeholder and every query path goes through prepare().
		$where  = array( '%d = 1' );
		$values = array( 1 );

		if ( ! empty( $args['status'] ) ) {
			$statuses     = array_map( 'strval', (array) $args['status'] );
			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$where[]      = "status IN ({$placeholders})";
			$values       = array_merge( $values, $statuses );
		}

		foreach ( array( 'service', 'context', 'batch_id' ) as $column ) {
			if ( ! empty( $args[ $column ] ) ) {
				$where[]  = "{$column} = %s";
				$values[] = (string) $args[ $column ];
			}
		}

		if ( ! empty( $args['created_by'] ) ) {
			$where[]  = 'created_by = %d';
			$values[] = (int) $args['created_by'];
		}

		$per_page = isset( $args['per_page'] ) ? min( 100, max( 1, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$table        = Schema::jobs_table();
		$where_clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Plugin-owned table; WHERE clause is built from fixed column snippets whose placeholders the sniff cannot see, and all values go through prepare().
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $values )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $values, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'jobs'  => array_map( array( Job::class, 'from_row' ), is_array( $rows ) ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * Jobs still waiting on the remote API (for the cron sweeper).
	 *
	 * @param int $limit Maximum rows.
	 * @return Job[]
	 */
	public function pending( $limit = 50 ) {
		global $wpdb;

		$table = Schema::jobs_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; name from a trusted constant.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('queued', 'running') ORDER BY id ASC LIMIT %d",
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map( array( Job::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Deletes a job row.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; repository is the single access point.
		return (bool) $wpdb->delete( Schema::jobs_table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Deletes finished rows older than the retention window.
	 *
	 * @param int $older_than_days Retention in days.
	 * @return int Number of deleted rows.
	 */
	public function prune( $older_than_days ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $older_than_days ) * DAY_IN_SECONDS ) );
		$table  = Schema::jobs_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; name from a trusted constant.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('succeeded', 'failed', 'expired') AND created_at < %s",
				$cutoff
			)
		);
		// phpcs:enable

		return false === $deleted ? 0 : (int) $deleted;
	}
}
