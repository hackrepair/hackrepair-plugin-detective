<?php
/**
 * Troubleshooter login for when wp-admin is down.
 *
 * - Goes through wp_authenticate(), the same check WordPress uses.
 * - One generic error for every failure, so usernames can't be probed.
 * - 5 failed tries from one address locks that address out for 15 minutes.
 * - Enforces two-factor codes the troubleshooter can check, and refuses
 *   password-only login when the user has 2FA it can't check.
 *
 * Site owners can turn this login off with
 * `define( 'HRPD_REQUIRE_WP_LOGIN', true );` in wp-config.php.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone login.
 */
final class HRPD_Login {

	public const MAX_FAILURES    = 5;
	public const LOCKOUT_SECONDS = 900;
	public const CHALLENGE_TTL   = 300;
	public const CHALLENGE_TRIES = 5;

	/**
	 * Whether password login on the troubleshooter is allowed on this site.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return ! ( defined( 'HRPD_REQUIRE_WP_LOGIN' ) && HRPD_REQUIRE_WP_LOGIN );
	}

	/**
	 * Check a username and password.
	 *
	 * @param string $username Username or email.
	 * @param string $password Password, exactly as typed.
	 * @return array<string, mixed>|WP_Error `array( 'ok' => true )` or `array( 'challenge' => token, 'provider' => string )`.
	 */
	public static function password( string $username, string $password ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'hrpd_login_disabled', __( 'Troubleshooter login is turned off on this site. Log in to wp-admin first, then open Plugin Detective.', 'hackrepair-plugin-detective' ) );
		}

		$locked = self::locked_for();
		if ( $locked > 0 ) {
			return self::lockout_error( $locked );
		}

		$username = sanitize_user( $username );
		if ( '' === $username || '' === $password ) {
			return self::failed( $username );
		}

		$user = wp_authenticate( $username, $password );
		if ( ! $user instanceof WP_User || ! user_can( $user, HRPD_Session::CAPABILITY ) ) {
			return self::failed( $username );
		}

		$two_factor = HRPD_Totp::detect( $user->ID );
		$emergency  = defined( 'HRPD_EMERGENCY_PASSWORD_LOGIN' ) && HRPD_EMERGENCY_PASSWORD_LOGIN;

		if ( HRPD_Totp::UNSUPPORTED === $two_factor['status'] && ! $emergency ) {
			HRPD_Log::add( 'login_refused_2fa', 'Troubleshooter login refused: user has two-factor login the troubleshooter cannot check.', array( 'user' => $user->user_login ) );
			return new WP_Error( 'hrpd_2fa_unsupported', __( 'Your account uses two-factor login that the troubleshooter can\'t check. Log in to wp-admin first, then open Plugin Detective.', 'hackrepair-plugin-detective' ) );
		}

		if ( HRPD_Totp::VERIFIABLE === $two_factor['status'] && ! $emergency ) {
			return array(
				'challenge' => self::create_challenge( $user->ID, $two_factor['provider'] ),
				'provider'  => $two_factor['provider'],
			);
		}

		self::clear_failures();
		HRPD_Session::start( $user );
		HRPD_Log::add( 'login', 'Signed in to the troubleshooter.', array( 'user' => $user->user_login ) );

		return array( 'ok' => true );
	}

	/**
	 * Check the second-step code.
	 *
	 * @param string $challenge Challenge token from password().
	 * @param string $code      Code the user typed.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function second_step( string $challenge, string $code ) {
		$locked = self::locked_for();
		if ( $locked > 0 ) {
			return self::lockout_error( $locked );
		}

		$key  = self::challenge_key( $challenge );
		$data = '' !== $challenge ? get_transient( $key ) : false;

		if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
			return new WP_Error( 'hrpd_challenge_expired', __( 'That sign-in took too long. Start again with your username and password.', 'hackrepair-plugin-detective' ) );
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user instanceof WP_User || ! HRPD_Totp::verify( $user->ID, (string) $data['provider'], $code ) ) {
			$data['tries'] = (int) ( $data['tries'] ?? 0 ) + 1;
			if ( $data['tries'] >= self::CHALLENGE_TRIES ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $data, self::CHALLENGE_TTL );
			}
			self::record_failure();
			HRPD_Log::add( 'login_failed_2fa', 'Wrong two-factor code on the troubleshooter.', array( 'user' => $user instanceof WP_User ? $user->user_login : '' ) );
			return new WP_Error( 'hrpd_bad_code', __( 'That code didn\'t work. Check your authenticator app and try again.', 'hackrepair-plugin-detective' ) );
		}

		delete_transient( $key );
		self::clear_failures();
		HRPD_Session::start( $user );
		HRPD_Log::add( 'login', 'Signed in to the troubleshooter with two-factor.', array( 'user' => $user->user_login ) );

		return array( 'ok' => true );
	}

	/**
	 * Seconds left on a lockout for this address.
	 *
	 * @return int
	 */
	public static function locked_for(): int {
		$data = get_transient( self::failure_key() );
		if ( ! is_array( $data ) || (int) ( $data['count'] ?? 0 ) < self::MAX_FAILURES ) {
			return 0;
		}
		return max( 0, (int) $data['until'] - time() );
	}

	/**
	 * Record a failed password and return the generic error.
	 *
	 * @param string $username Attempted username (for the activity log only).
	 * @return WP_Error
	 */
	private static function failed( string $username ): WP_Error {
		self::record_failure();
		HRPD_Log::add( 'login_failed', 'Failed troubleshooter login.', array( 'user' => $username ) );

		$locked = self::locked_for();
		if ( $locked > 0 ) {
			return self::lockout_error( $locked );
		}

		return new WP_Error( 'hrpd_login_failed', __( 'That username and password didn\'t work, or that account can\'t manage plugins.', 'hackrepair-plugin-detective' ) );
	}

	/**
	 * Lockout error.
	 *
	 * @param int $seconds Seconds left.
	 * @return WP_Error
	 */
	private static function lockout_error( int $seconds ): WP_Error {
		$minutes = max( 1, (int) ceil( $seconds / 60 ) );
		return new WP_Error(
			'hrpd_locked_out',
			sprintf(
				/* translators: %d: minutes until the user can try again. */
				_n( 'Too many tries. Take a breather and try again in %d minute.', 'Too many tries. Take a breather and try again in %d minutes.', $minutes, 'hackrepair-plugin-detective' ),
				$minutes
			)
		);
	}

	/**
	 * Count a failure for this address.
	 */
	private static function record_failure(): void {
		$key  = self::failure_key();
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array( 'count' => 0 );

		$data['count'] = (int) $data['count'] + 1;
		$data['until'] = time() + self::LOCKOUT_SECONDS;

		set_transient( $key, $data, self::LOCKOUT_SECONDS );

		if ( self::MAX_FAILURES === $data['count'] ) {
			HRPD_Log::add( 'lockout', 'Troubleshooter login locked for 15 minutes after repeated failures.' );
		}
	}

	/**
	 * Clear failures for this address.
	 */
	private static function clear_failures(): void {
		delete_transient( self::failure_key() );
	}

	/**
	 * Transient key for this address.
	 *
	 * @return string
	 */
	private static function failure_key(): string {
		return 'hrpd_fail_' . substr( hash( 'sha256', HRPD_Log::client_ip() . '|' . wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Create a short-lived second-step challenge.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $provider 2FA provider.
	 * @return string Challenge token.
	 */
	private static function create_challenge( int $user_id, string $provider ): string {
		$token = bin2hex( random_bytes( 24 ) );
		set_transient(
			self::challenge_key( $token ),
			array(
				'user_id'  => $user_id,
				'provider' => $provider,
				'tries'    => 0,
			),
			self::CHALLENGE_TTL
		);
		return $token;
	}

	/**
	 * Transient key for a challenge token.
	 *
	 * @param string $token Challenge token.
	 * @return string
	 */
	private static function challenge_key( string $token ): string {
		return 'hrpd_2fa_' . substr( hash( 'sha256', $token ), 0, 32 );
	}
}
