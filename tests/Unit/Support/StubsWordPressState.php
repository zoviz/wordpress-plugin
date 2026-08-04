<?php

namespace Zoviz\Tests\Unit\Support;

use Brain\Monkey\Functions;

/**
 * Provides in-memory doubles for the WordPress options and transients APIs
 * so repositories can be unit-tested without WordPress.
 */
trait StubsWordPressState {

	/** @var array<string, mixed> */
	protected $options = array();

	/** @var array<string, mixed> */
	protected $transients = array();

	protected function stub_wordpress_state(): void {
		$options    =& $this->options;
		$transients =& $this->transients;

		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default_value;
			}
		);

		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( &$options ) {
				unset( $options[ $name ] );
				return true;
			}
		);

		Functions\when( 'get_transient' )->alias(
			static function ( $name ) use ( &$transients ) {
				return array_key_exists( $name, $transients ) ? $transients[ $name ] : false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			static function ( $name, $value ) use ( &$transients ) {
				$transients[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			static function ( $name ) use ( &$transients ) {
				unset( $transients[ $name ] );
				return true;
			}
		);

		Functions\when( 'wp_generate_password' )->alias(
			static function ( $length = 12 ) {
				return substr( bin2hex( random_bytes( 16 ) ), 0, $length );
			}
		);

		Functions\when( 'wp_salt' )->alias(
			static fn( $scheme = 'auth' ) => 'unit-salt-' . $scheme
		);

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number, $decimals = 0 ) => number_format( (float) $number, (int) $decimals )
		);
	}
}
