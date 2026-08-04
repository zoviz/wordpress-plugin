<?php
/**
 * Database schema management.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Database;

/**
 * Installs and upgrades the plugin's custom tables via dbDelta. The current
 * schema version is stored in an option; maybe_upgrade() runs on admin init
 * so plugin updates apply schema changes without requiring re-activation.
 */
final class Schema {

	/**
	 * Current schema version. Bump when the table definition changes.
	 *
	 * @var string
	 */
	const VERSION = '1';

	/**
	 * Option storing the installed schema version.
	 *
	 * @var string
	 */
	const OPTION_VERSION = 'zoviz_schema_version';

	/**
	 * The jobs table name with prefix.
	 *
	 * @return string
	 */
	public static function jobs_table() {
		global $wpdb;

		return $wpdb->prefix . 'zoviz_jobs';
	}

	/**
	 * Installs or upgrades the schema (idempotent via dbDelta).
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::jobs_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			remote_job_id VARCHAR(64) NULL,
			api_key_id VARCHAR(32) NOT NULL,
			service VARCHAR(64) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending_submit',
			content_type VARCHAR(100) NULL,
			credits_used INT UNSIGNED NULL,
			attachment_id BIGINT UNSIGNED NULL,
			source_attachment_id BIGINT UNSIGNED NULL,
			context VARCHAR(40) NOT NULL DEFAULT 'workspace',
			batch_id VARCHAR(32) NULL,
			params LONGTEXT NULL,
			error_code VARCHAR(40) NULL,
			error_message TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			finished_at DATETIME NULL,
			expires_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY remote_job_id (remote_job_id),
			KEY status (status),
			KEY created_by (created_by),
			KEY batch_id (batch_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::OPTION_VERSION, self::VERSION );
	}

	/**
	 * Runs install() when the stored schema version is outdated. Cheap
	 * option check; safe to call on every admin request.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION_VERSION ) !== self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Drops the plugin tables. Uninstall only.
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;

		$table = self::jobs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of the plugin's own table; identifier cannot be a placeholder.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix and a constant.

		delete_option( self::OPTION_VERSION );
	}
}
