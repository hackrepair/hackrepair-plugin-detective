<?php
/**
 * Clean up when the plugin is deleted.
 *
 * If a troubleshooting session is still open, plugins are put back first so
 * deleting Plugin Detective never leaves a site with plugins switched off.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$hrpd_case = get_option( 'hrpd_case' );
if ( is_array( $hrpd_case ) && ! empty( $hrpd_case['backup'] ) && is_array( $hrpd_case['backup'] ) ) {
	$hrpd_self    = basename( __DIR__ ) . '/hackrepair-plugin-detective.php';
	$hrpd_restore = array_values( array_diff( $hrpd_case['backup'], array( $hrpd_self ) ) );
	update_option( 'active_plugins', $hrpd_restore );
}

delete_option( 'hrpd_case' );
delete_option( 'hrpd_last_result' );
delete_option( 'hrpd_activity_log' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup of this plugin's short-lived transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_hrpd_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_hrpd_' ) . '%',
		$wpdb->esc_like( '_site_transient_hrpd_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_hrpd_' ) . '%'
	)
);

// Replay guard used for Two Factor plugin codes.
delete_metadata( 'user', 0, 'hrpd_totp_last_step', '', true );
