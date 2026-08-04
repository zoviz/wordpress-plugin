<?php
/**
 * Admin notices.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Admin;

use Zoviz\DeveloperApi\Keys\KeyRepository;
use Zoviz\DeveloperApi\Rest\NoticesController;

/**
 * The critical account notices: default key ran out of credits, or the
 * default key became invalid. Conditions are evaluated from CACHED state
 * only (options + transients written by earlier API interactions) — an
 * admin page load never triggers a remote request. Notices are
 * dismissible (snoozed per user) and scoped to screens where the plugin
 * is relevant, per the directory's no-dashboard-hijacking rules.
 */
final class Notices {

	/**
	 * Notice ids (also used by the REST dismiss endpoint).
	 */
	const NOTICE_NO_CREDITS  = 'no-credits';
	const NOTICE_INVALID_KEY = 'invalid-key';

	/**
	 * Screens the notices may appear on.
	 *
	 * @var string[]
	 */
	const SCREENS = array( 'upload', 'attachment', 'post', 'page', 'product', 'media' );

	/**
	 * Key repository.
	 *
	 * @var KeyRepository
	 */
	private $keys;

	/**
	 * Constructor.
	 *
	 * @param KeyRepository $keys Key repository.
	 */
	public function __construct( KeyRepository $keys ) {
		$this->keys = $keys;
	}

	/**
	 * Wires the hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Renders applicable notices on relevant screens.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'upload_files' ) || ! $this->is_relevant_screen() ) {
			return;
		}

		$default_id = $this->keys->default_key_id();

		if ( '' === $default_id ) {
			return;
		}

		$key = null;

		foreach ( $this->keys->all() as $candidate ) {
			if ( $candidate->id() === $default_id ) {
				$key = $candidate;
				break;
			}
		}

		if ( null === $key ) {
			return;
		}

		if ( ! $key->is_valid() && ! NoticesController::is_snoozed( self::NOTICE_INVALID_KEY ) ) {
			$this->print_notice(
				self::NOTICE_INVALID_KEY,
				'error',
				sprintf(
					/* translators: %s: API key label. */
					__( 'Your Zoviz API key "%s" is no longer valid. Image tools will not work until it is replaced.', 'zoviz-ai-studio' ),
					$key->label()
				),
				admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
				__( 'Open Zoviz settings', 'zoviz-ai-studio' )
			);

			return;
		}

		$cached = get_transient( 'zoviz_credits_' . $default_id );

		if ( is_array( $cached ) && isset( $cached['credit'] ) && (int) $cached['credit'] <= 0
			&& ! NoticesController::is_snoozed( self::NOTICE_NO_CREDITS ) ) {
			$this->print_notice(
				self::NOTICE_NO_CREDITS,
				'error',
				sprintf(
					/* translators: %s: workspace/API key label. */
					__( 'Your Zoviz workspace "%s" has run out of credits. Image tools will not work until you top up.', 'zoviz-ai-studio' ),
					$key->label()
				),
				'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
				__( 'Buy credits', 'zoviz-ai-studio' )
			);
		}
	}

	/**
	 * Whether the current screen should show plugin notices.
	 *
	 * @return bool
	 */
	private function is_relevant_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( null === $screen ) {
			return false;
		}

		if ( false !== strpos( (string) $screen->id, 'zoviz-ai-studio' ) ) {
			return true;
		}

		return in_array( (string) $screen->id, self::SCREENS, true )
			|| in_array( (string) $screen->post_type, array( 'post', 'page', 'product', 'attachment' ), true );
	}

	/**
	 * Prints a dismissible notice wired to the REST snooze endpoint.
	 *
	 * @param string $id         Notice id.
	 * @param string $level      Notice level (error|warning).
	 * @param string $message    Message text.
	 * @param string $action_url Action link URL.
	 * @param string $action     Action link text.
	 * @return void
	 */
	private function print_notice( $id, $level, $message, $action_url, $action ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible zoviz-notice" data-zoviz-notice="%2$s"><p><strong>%3$s</strong> <a href="%4$s" target="_blank" rel="noopener">%5$s</a></p></div>',
			esc_attr( $level ),
			esc_attr( $id ),
			esc_html( $message ),
			esc_url( $action_url ),
			esc_html( $action )
		);

		// Snooze on dismiss via the REST endpoint (vanilla JS: the plugin
		// bundles are not loaded on every screen this notice appears on).
		static $printed_script = false;

		if ( $printed_script ) {
			return;
		}
		$printed_script = true;

		$endpoint = esc_url_raw( rest_url( 'zoviz/v1/notices/%s/dismiss' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );

		printf(
			'<script>document.addEventListener("click",function(e){var b=e.target.closest(".zoviz-notice .notice-dismiss");if(!b){return;}var n=b.closest(".zoviz-notice");fetch("%s".replace("%%s",n.getAttribute("data-zoviz-notice")),{method:"POST",headers:{"X-WP-Nonce":"%s"}});});</script>',
			esc_url( $endpoint ),
			esc_attr( $nonce )
		);
	}
}
