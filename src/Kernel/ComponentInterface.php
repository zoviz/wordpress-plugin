<?php
/**
 * Component contract.
 *
 * @package Zoviz
 */

namespace Zoviz\Kernel;

/**
 * A component is a self-contained feature area (for example the Developer
 * API integration). Components may use entirely different remote APIs and
 * policies; the kernel only knows this contract.
 */
interface ComponentInterface {

	/**
	 * Unique component id, e.g. 'developer-api'.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Registers container bindings. No hooks may be added here.
	 *
	 * @param Container $container The service container.
	 * @return void
	 */
	public function register( Container $container );

	/**
	 * Wires WordPress hooks. No container bindings may be added here.
	 *
	 * @param Container $container The service container.
	 * @return void
	 */
	public function boot( Container $container );
}
