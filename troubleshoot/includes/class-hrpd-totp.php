<?php
/**
 * Two-factor checks for the Fix page's own login.
 *
 * Plugins are not loaded in the Fix page, so a 2FA plugin cannot run
 * its own login challenge here. This class reads the user's 2FA setup from
 * the database and checks the code itself for the plugins it understands:
 *
 * - HackGuard Security (authenticator app codes and recovery codes)
 * - Two Factor (authenticator app codes)
 *
 * For 2FA plugins it can't check, standalone password login is refused and
 * the user is sent to wp-admin, so 2FA is never skipped.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Two-factor detection and code checks.
 */
final class HRPD_Totp {

	public const NONE        = 'none';
	public const VERIFIABLE  = 'verifiable';
	public const UNSUPPORTED = 'unsupported';

	/**
	 * What kind of 2FA the user has.
	 *
	 * @param int $user_id User ID.
	 * @return array{status: string, provider: string}
	 */
	public static function detect( int $user_id ): array {
		// HackGuard Security.
		if ( get_user_meta( $user_id, 'hswp_2fa_enabled', true ) ) {
			$method = (string) get_user_meta( $user_id, 'hswp_2fa_method', true );
			if ( 'totp' === $method && get_user_meta( $user_id, 'hswp_totp_secret', true ) ) {
				return array(
					'status'   => self::VERIFIABLE,
					'provider' => 'hackguard',
				);
			}
			// Email codes: only recovery codes can be checked here.
			return array(
				'status'   => get_user_meta( $user_id, 'hswp_recovery_codes', true ) ? self::VERIFIABLE : self::UNSUPPORTED,
				'provider' => 'hackguard_email',
			);
		}

		// Two Factor plugin.
		$providers = get_user_meta( $user_id, '_two_factor_enabled_providers', true );
		if ( is_array( $providers ) && ! empty( $providers ) ) {
			if ( in_array( 'Two_Factor_Totp', $providers, true ) && get_user_meta( $user_id, '_two_factor_totp_key', true ) ) {
				return array(
					'status'   => self::VERIFIABLE,
					'provider' => 'two_factor',
				);
			}
			return array(
				'status'   => self::UNSUPPORTED,
				'provider' => 'two_factor',
			);
		}

		// Other common 2FA plugins this check can't verify.
		if ( get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ) || self::has_wordfence_2fa( $user_id ) ) {
			return array(
				'status'   => self::UNSUPPORTED,
				'provider' => 'other',
			);
		}

		return array(
			'status'   => self::NONE,
			'provider' => '',
		);
	}

	/**
	 * Check an authenticator or recovery code.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $provider Provider from detect().
	 * @param string $code     Code the user typed.
	 * @return bool
	 */
	public static function verify( int $user_id, string $provider, string $code ): bool {
		$code = strtolower( (string) preg_replace( '/\s+/', '', $code ) );
		if ( '' === $code ) {
			return false;
		}

		if ( 'hackguard' === $provider || 'hackguard_email' === $provider ) {
			if ( 'hackguard' === $provider && preg_match( '/^\d{6}$/', $code ) ) {
				$secret = (string) get_user_meta( $user_id, 'hswp_totp_secret', true );
				return self::check_totp( $user_id, $secret, $code, 'hswp_totp_last_used' );
			}
			return self::use_hackguard_recovery_code( $user_id, $code );
		}

		if ( 'two_factor' === $provider && preg_match( '/^\d{6}$/', $code ) ) {
			$secret = (string) get_user_meta( $user_id, '_two_factor_totp_key', true );
			return self::check_totp( $user_id, $secret, $code, 'hrpd_totp_last_step' );
		}

		return false;
	}

	/**
	 * Check a 6-digit authenticator code, allowing one step of clock drift
	 * and refusing a code that was already used.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $secret    Base32 secret.
	 * @param string $code      6-digit code.
	 * @param string $replay_key User meta key that stores the last used time step.
	 * @return bool
	 */
	private static function check_totp( int $user_id, string $secret, string $code, string $replay_key ): bool {
		if ( '' === $secret ) {
			return false;
		}

		$now  = (int) floor( time() / 30 );
		$last = (int) get_user_meta( $user_id, $replay_key, true );

		for ( $drift = -1; $drift <= 1; $drift++ ) {
			$step = $now + $drift;
			if ( hash_equals( self::code_for_step( $secret, $step ), $code ) ) {
				if ( $step <= $last ) {
					return false;
				}
				update_user_meta( $user_id, $replay_key, $step );
				return true;
			}
		}

		return false;
	}

	/**
	 * RFC 6238 code for a time step.
	 *
	 * @param string $secret Base32 secret.
	 * @param int    $step   Time step.
	 * @return string
	 */
	private static function code_for_step( string $secret, int $step ): string {
		$key    = self::base32_decode( $secret );
		$hash   = hash_hmac( 'sha1', pack( 'N*', 0, $step ), $key, true );
		$offset = ord( $hash[19] ) & 0x0f;
		$value  = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 )
			| ( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 )
			| ( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 )
			| ( ord( $hash[ $offset + 3 ] ) & 0xff );

		return str_pad( (string) ( $value % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Decode Base32 (RFC 4648 alphabet).
	 *
	 * @param string $input Base32 text.
	 * @return string Raw bytes.
	 */
	private static function base32_decode( string $input ): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$input    = strtoupper( rtrim( $input, '=' ) );
		$buffer   = 0;
		$bits     = 0;
		$output   = '';

		$length = strlen( $input );
		for ( $i = 0; $i < $length; $i++ ) {
			$value = strpos( $alphabet, $input[ $i ] );
			if ( false === $value ) {
				continue;
			}
			$buffer = ( $buffer << 5 ) | $value;
			$bits  += 5;
			if ( $bits >= 8 ) {
				$bits   -= 8;
				$output .= chr( ( $buffer >> $bits ) & 0xff );
			}
		}

		return $output;
	}

	/**
	 * Use (and burn) a HackGuard Security recovery code.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Lowercased code without spaces.
	 * @return bool
	 */
	private static function use_hackguard_recovery_code( int $user_id, string $code ): bool {
		$hashes = get_user_meta( $user_id, 'hswp_recovery_codes', true );
		if ( ! is_array( $hashes ) ) {
			return false;
		}

		foreach ( $hashes as $index => $hash ) {
			if ( is_string( $hash ) && password_verify( $code, $hash ) ) {
				unset( $hashes[ $index ] );
				update_user_meta( $user_id, 'hswp_recovery_codes', array_values( $hashes ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether Wordfence Login Security has a 2FA secret for the user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function has_wordfence_2fa( int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->base_prefix . 'wfls_2fa_secrets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off existence check during login.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off existence check during login.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $table, $user_id ) );
	}
}
