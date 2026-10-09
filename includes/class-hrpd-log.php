<?php
/**
 * Small activity log shared by the admin screens and the standalone troubleshooter.
 *
 * Keeps the most recent entries in one non-autoloaded option so a site owner
 * can see who opened a case, which plugins were switched, and any failed
 * troubleshooter logins. Never stores passwords, codes, or cookies.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity log.
 */
final class HRPD_Log {

	/**
	 * Option that holds the log entries.
	 */
	public const OPTION = 'hrpd_activity_log';

	/**
	 * Maximum number of entries kept.
	 */
	public const MAX_ENTRIES = 50;

	/**
	 * Add an entry.
	 *
	 * @param string               $event   Short machine name, e.g. `case_opened`.
	 * @param string               $message Plain-English description.
	 * @param array<string, mixed> $context Extra non-secret details.
	 */
	public static function add( string $event, string $message, array $context = array() ): void {
		$entries = self::all();

		array_unshift(
			$entries,
			array(
				'time'    => time(),
				'event'   => sanitize_key( $event ),
				'message' => sanitize_text_field( $message ),
				'user'    => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'ip'      => self::client_ip(),
				'context' => self::clean_context( $context ),
			)
		);

		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate audit trail when the site owner has debug logging on.
			error_log( '[HRPD] ' . sanitize_key( $event ) . ': ' . sanitize_text_field( $message ) );
		}
	}

	/**
	 * All entries, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Remove every entry.
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Client IP from the connection itself. Forwarded headers are not trusted.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Keep context small and text-only.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, string>
	 */
	private static function clean_context( array $context ): array {
		$clean = array();
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			$clean[ sanitize_key( (string) $key ) ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
		}
		return $clean;
	}
}
