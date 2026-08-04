<?php
/**
 * Uninstall cleanup for Zoviz AI Studio.
 *
 * Removes the plugin's table, options, transients, and user meta. Media
 * Library attachments created from job results are deliberately kept —
 * they are the user's content.
 *
 * @package Zoviz
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop the jobs table.
$zoviz_jobs_table = $wpdb->prefix . 'zoviz_jobs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup of the plugin's own table; identifier built from $wpdb->prefix and a constant.
$wpdb->query( "DROP TABLE IF EXISTS {$zoviz_jobs_table}" );

// Delete options.
$zoviz_options = array(
	'zoviz_schema_version',
	'zoviz_activated_version',
	'zoviz_api_keys',
	'zoviz_default_key_id',
	'zoviz_settings',
	'zoviz_crypto_canary',
);

foreach ( $zoviz_options as $zoviz_option ) {
	delete_option( $zoviz_option );
}

// Delete credit-cache transients (names contain per-key ids, so match by prefix).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_zoviz_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_zoviz_' ) . '%'
	)
);

// Delete per-user notice dismissals.
delete_metadata( 'user', 0, 'zoviz_dismissed_notices', '', true );
