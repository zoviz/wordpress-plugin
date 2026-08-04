<?php
/**
 * Activation / deactivation lifecycle.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

/**
 * Activation and deactivation callbacks. Registered at the top level of the
 * main plugin file, as required for activation hooks to fire reliably.
 */
final class Activation {

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'zoviz_activated_version', Plugin::VERSION, false );

		/**
		 * Fires on plugin activation, after core setup.
		 *
		 * Components use this to install schema and schedule cron events.
		 *
		 * @since 0.1.0
		 */
		do_action( 'zoviz_activate' );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires on plugin deactivation.
		 *
		 * Components use this to unschedule their cron events.
		 *
		 * @since 0.1.0
		 */
		do_action( 'zoviz_deactivate' );
	}
}
