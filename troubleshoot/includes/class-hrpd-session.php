<?php
/**
 * Who is using the Fix page, and the request-forgery token.
 *
 * Two ways in:
 * 1. Already logged in to wp-admin: the normal WordPress login cookie is
 *    recognized, so the user has already passed any 2FA.
 * 2. Logged in on the Fix page itself (when wp-admin is down): a
 *    short-lived, signed, HttpOnly cookie scoped to the Fix page folder.
 *
 * Either way the user must still be able to manage plugins on every request.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fix page session.
 */
final class HRPD_Session {

	/**
	 * Cookie name for Fix page logins.
	 */
	public const COOKIE = 'hrpd_session';

	/**
	 * Session length in seconds (2 hours).
	 */
	public const TTL = 7200;

	/**
	 * Capability required for everything in the Fix page.
	 */
	public const CAPABILITY = 'activate_plugins';

	/**
	 * Cached current user.
	 *
	 * @var WP_User|null
	 */
	private static ?WP_User $user = null;

	/**
	 * How the current user signed in: `wp`, `Fix page`, or empty.
	 *
	 * @var string
	 */
	private static string $source = '';

	/**
	 * Raw session material the CSRF token is tied to.
	 *
	 * @var string
	 */
	private static string $material = '';

	/**
	 * Work out the current user. Safe to call more than once.
	 *
	 * @return WP_User|null Authorized user, or null.
	 */
	public static function user(): ?WP_User {
		if ( null !== self::$user || '' !== self::$source ) {
			return self::$user;
		}

		self::$source = 'none';

		// 1. Normal WordPress login cookie (auth or secure_auth scheme).
		$user_id = (int) wp_validate_auth_cookie( '', '' );
		if ( $user_id > 0 ) {
			$scheme = is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
			self::accept( $user_id, 'wp', isset( $_COOKIE[ $scheme ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $scheme ] ) ) : '' );
			if ( self::$user ) {
				return self::$user;
			}
		}

		// 2. Fix page's own session cookie.
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$uid = self::verify_cookie( $raw );
		if ( $uid > 0 ) {
			self::accept( $uid, 'troubleshooter', $raw );
		}

		return self::$user;
	}

	/**
	 * How the current user signed in.
	 *
	 * @return string `wp`, `Fix page`, or `none`.
	 */
	public static function source(): string {
		self::user();
		return self::$source;
	}

	/**
	 * Start a Fix page session after a successful login.
	 *
	 * @param WP_User $user Logged-in user.
	 */
	public static function start( WP_User $user ): void {
		$expires = time() + self::TTL;
		$value   = $user->ID . '|' . $expires . '|' . self::sign( $user, $expires );

		self::set_cookie( $value, $expires );

		self::$user     = $user;
		self::$source   = 'troubleshooter';
		self::$material = $value;
		wp_set_current_user( $user->ID );
	}

	/**
	 * End the Fix page session.
	 */
	public static function end(): void {
		self::set_cookie( '', time() - YEAR_IN_SECONDS );
		self::$user     = null;
		self::$source   = 'none';
		self::$material = '';
	}

	/**
	 * Token the app must send back with every change.
	 *
	 * @return string Empty when nobody is signed in.
	 */
	public static function csrf_token(): string {
		if ( ! self::user() ) {
			return '';
		}
		return hash_hmac( 'sha256', 'hrpd-csrf|' . self::$user->ID . '|' . self::$material, wp_salt( 'nonce' ) );
	}

	/**
	 * Check the token sent with a request.
	 *
	 * @param string $token Token from the request header.
	 * @return bool
	 */
	public static function check_csrf( string $token ): bool {
		$expected = self::csrf_token();
		return '' !== $expected && '' !== $token && hash_equals( $expected, $token );
	}

	/**
	 * Path the Fix page cookie is limited to.
	 *
	 * @return string
	 */
	public static function cookie_path(): string {
		$path = (string) wp_parse_url( plugins_url( 'troubleshoot/', HRPD_PLUGIN_DIR . 'hackrepair-plugin-detective.php' ), PHP_URL_PATH );
		return '' !== $path ? $path : '/';
	}

	/**
	 * Accept a user if they can manage plugins.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $source   Sign-in source.
	 * @param string $material Cookie value the CSRF token is tied to.
	 */
	private static function accept( int $user_id, string $source, string $material ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User || ! user_can( $user, self::CAPABILITY ) ) {
			return;
		}

		self::$user     = $user;
		self::$source   = $source;
		self::$material = $material;
		wp_set_current_user( $user->ID );
	}

	/**
	 * Check a Fix page cookie and return its user ID.
	 *
	 * @param string $raw Cookie value.
	 * @return int User ID, or 0.
	 */
	private static function verify_cookie( string $raw ): int {
		$parts = explode( '|', $raw );
		if ( 3 !== count( $parts ) ) {
			return 0;
		}

		[ $user_id, $expires, $signature ] = $parts;
		$user_id                           = (int) $user_id;
		$expires                           = (int) $expires;

		if ( $user_id <= 0 || $expires < time() || $expires > time() + self::TTL ) {
			return 0;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return 0;
		}

		return hash_equals( self::sign( $user, $expires ), $signature ) ? $user_id : 0;
	}

	/**
	 * Signature tied to the user's password, so changing the password ends the session.
	 *
	 * @param WP_User $user    User.
	 * @param int     $expires Expiry timestamp.
	 * @return string
	 */
	private static function sign( WP_User $user, int $expires ): string {
		$pass_frag = substr( (string) $user->user_pass, 8, 4 );
		return hash_hmac( 'sha256', 'hrpd-session|' . $user->ID . '|' . $expires . '|' . $pass_frag, wp_salt( 'auth' ) );
	}

	/**
	 * Set or clear the Fix page cookie.
	 *
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	private static function set_cookie( string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => self::cookie_path(),
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}
}
