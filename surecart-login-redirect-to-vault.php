<?php
/**
 * Plugin Name: SureCart Login Redirect to Subscriber Vault
 * Description: Redirects customers to the subscriber vault only immediately after a successful SureCart/WordPress login initiated from the Customer Dashboard. Manual visits to the Customer Dashboard are not redirected.
 * Version: 1.0.0
 * Author: Steven Polinario
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SC_Login_Redirect_To_Vault {

	/**
	 * Change these two paths if the site's URLs change.
	 */
	const DASHBOARD_PATH = '/customer-dashboard/';
	const TARGET_PATH    = '/digital-subscriber-pass-download-vault/';

	/**
	 * Short-lived browser flag. It is intentionally not persistent.
	 */
	const COOKIE_NAME = 'sc_login_redirect_vault';

	public static function init() {
		// Standard WordPress login flow.
		add_action( 'wp_login', array( __CLASS__, 'mark_successful_login' ), 10, 2 );

		// Also catches authentication flows which set the logged-in cookie directly.
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'mark_cookie_login' ), 10, 4 );

		// Server-side redirect when a new page request reaches the dashboard.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );

		// Fallback for SureCart flows that complete login through AJAX without a
		// normal page navigation. This only runs on the dashboard and only when
		// the short-lived flag exists.
		add_action( 'wp_footer', array( __CLASS__, 'fallback_script' ), 100 );
	}

	private static function dashboard_url() {
		return home_url( self::DASHBOARD_PATH );
	}

	private static function target_url() {
		return home_url( self::TARGET_PATH );
	}

	private static function is_dashboard_request() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$request_path = wp_parse_url( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
		$dashboard    = wp_parse_url( self::dashboard_url(), PHP_URL_PATH );

		return untrailingslashit( $request_path ) === untrailingslashit( $dashboard );
	}

	private static function request_started_from_dashboard() {
		$referer = wp_get_raw_referer();

		if ( ! $referer ) {
			return false;
		}

		$referer_path   = wp_parse_url( $referer, PHP_URL_PATH );
		$dashboard_path = wp_parse_url( self::dashboard_url(), PHP_URL_PATH );

		return $referer_path
			&& untrailingslashit( $referer_path ) === untrailingslashit( $dashboard_path );
	}

	private static function set_flag() {
		// Do not overwrite an existing flag.
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		// 60 seconds is deliberately short.
		setcookie(
			self::COOKIE_NAME,
			'1',
			array(
				'expires'  => time() + 60,
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		// Make the flag immediately available during this request too.
		$_COOKIE[ self::COOKIE_NAME ] = '1';
	}

	private static function clear_flag() {
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - 3600,
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	public static function mark_successful_login( $user_login, $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}

		// Only mark logins initiated from the SureCart Customer Dashboard.
		if ( self::request_started_from_dashboard() ) {
			self::set_flag();
		}
	}

	public static function mark_cookie_login( $logged_in_cookie, $expire, $expiration, $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		// SureCart may authenticate through a flow that doesn't fire wp_login.
		if ( self::request_started_from_dashboard() ) {
			self::set_flag();
		}
	}

	public static function maybe_redirect() {
		if ( ! self::is_dashboard_request() ) {
			return;
		}

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		// Consume the flag before redirecting so a later manual visit to the
		// dashboard is never redirected.
		self::clear_flag();

		wp_safe_redirect( self::target_url(), 302 );
		exit;
	}

	public static function fallback_script() {
		if ( ! self::is_dashboard_request() ) {
			return;
		}

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		$target = esc_url( self::target_url() );
		?>
		<script>
		(function () {
			var cookieName = <?php echo wp_json_encode( self::COOKIE_NAME ); ?>;
			var target = <?php echo wp_json_encode( $target ); ?>;

			function hasCookie() {
				return document.cookie.split('; ').some(function (row) {
					return row.indexOf(cookieName + '=1') === 0;
				});
			}

			if (hasCookie()) {
				// Consume the flag immediately.
				document.cookie = cookieName + '=; Max-Age=0; path=/; SameSite=Lax';
				window.location.replace(target);
			}
		}());
		</script>
		<?php
	}
}

SC_Login_Redirect_To_Vault::init();
