<?php
/**
 * Bootstrap for the integration test suite (wp-phpunit inside wp-env).
 *
 * @package Zoviz
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$zoviz_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( false === $zoviz_wp_phpunit_dir || '' === $zoviz_wp_phpunit_dir ) {
	$zoviz_wp_phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( false === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) || '' === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Test bootstrap must point wp-phpunit at its config.
}

require_once $zoviz_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/zoviz-ai-studio.php';
	}
);

require $zoviz_wp_phpunit_dir . '/includes/bootstrap.php';
