<?php

namespace Zoviz\Tests\Integration;

use Zoviz\Infrastructure\Database\Schema;

class SchemaTest extends \WP_UnitTestCase {

	public function test_install_creates_table_and_version_option() {
		global $wpdb;

		Schema::install();

		$table = Schema::jobs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $found );
		$this->assertSame( Schema::VERSION, get_option( Schema::OPTION_VERSION ) );
	}

	public function test_install_is_idempotent() {
		Schema::install();
		Schema::install();

		$this->assertSame( Schema::VERSION, get_option( Schema::OPTION_VERSION ) );
	}

	public function test_maybe_upgrade_runs_install_when_version_outdated() {
		Schema::install();
		update_option( Schema::OPTION_VERSION, '0' );

		Schema::maybe_upgrade();

		$this->assertSame( Schema::VERSION, get_option( Schema::OPTION_VERSION ) );
	}
}
