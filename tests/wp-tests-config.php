<?php
/**
 * WordPress test-suite configuration. Defaults match the wp-env tests
 * containers; every value can be overridden through environment variables
 * for other setups (CI services, local MySQL).
 *
 * @package Zoviz
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Constants and globals are dictated by the WordPress test suite.

$zoviz_env = static function ( $name, $default_value ) {
	$value = getenv( $name );

	return false === $value || '' === $value ? $default_value : $value;
};

define( 'ABSPATH', rtrim( $zoviz_env( 'WP_ABSPATH', '/var/www/html' ), '/' ) . '/' );

define( 'DB_NAME', $zoviz_env( 'WORDPRESS_DB_NAME', 'tests-wordpress' ) );
define( 'DB_USER', $zoviz_env( 'WORDPRESS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', $zoviz_env( 'WORDPRESS_DB_PASSWORD', 'password' ) );
define( 'DB_HOST', $zoviz_env( 'WORDPRESS_DB_HOST', 'tests-mysql' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the test suite.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
