<?php
/**
 * Plugin settings accessor.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi;

/**
 * Typed accessor for the `zoviz_settings` option with defaults.
 */
final class Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'zoviz_settings';

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults() {
		return array(
			// Automatically download finished results into the Media Library
			// (the sweeper relies on this to beat remote result expiry).
			'auto_download'  => true,
			// Days to keep finished job rows before pruning.
			'retention_days' => 90,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		$stored = get_option( self::OPTION, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null when the key is unknown.
	 */
	public function get( $key ) {
		$all = $this->all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Merges and persists setting values (unknown keys are dropped).
	 *
	 * @param array<string, mixed> $values New values.
	 * @return array<string, mixed> The updated settings.
	 */
	public function update( array $values ) {
		$defaults = $this->defaults();
		$current  = $this->all();
		$next     = array_merge( $current, array_intersect_key( $values, $defaults ) );

		$next['auto_download']  = ! empty( $next['auto_download'] );
		$next['retention_days'] = max( 1, (int) $next['retention_days'] );

		update_option( self::OPTION, $next, false );

		return $next;
	}
}
