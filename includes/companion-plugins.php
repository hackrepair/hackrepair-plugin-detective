<?php
/**
 * Companion plugins for the native View Details dialog.
 *
 * @package HackRepair_Plugin_Detective
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append the companion guide only to this plugin's information.
 *
 * @param mixed  $result Plugin information or the original API result.
 * @param string $action Requested API action.
 * @param object $args API arguments.
 * @return mixed
 */
function hrpd_companion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || 'hackrepair-plugin-detective' !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
		return $result;
	}
	wp_enqueue_style( 'hackrepair-plugin-detective-brand', plugins_url( 'assets/brand/thrg-brand.css', HRPD_FILE ), array(), HRPD_VERSION );
	wp_enqueue_style( 'hackrepair-plugin-detective-brand-details', plugins_url( 'assets/brand/thrg-details.css', HRPD_FILE ), array( 'hackrepair-plugin-detective-brand' ), HRPD_VERSION );
	wp_enqueue_style( 'hrpd-plugin-details', plugins_url( 'assets/css/hrpd-plugin-details.css', HRPD_FILE ), array(), HRPD_VERSION );
	$result->version                        = HRPD_VERSION;
	$result->sections['other_THRG_plugins'] = '<div class="hrpd-toolkit"><p>More tools from The Hack Repair Guy</p><p>Three focused ways to look after your WordPress site. Use only what you need.</p><div class="hrpd-toolkit-card"><h3>Disable Other Plugin Notices</h3><p>Group plugin and theme notices into a collapsible panel while keeping WordPress core notices visible.</p><p><a href="https://github.com/hackrepair/disable-other-plugin-notices" target="_blank" rel="noopener noreferrer">Explore Disable Other Plugin Notices &rarr;</a></p></div><div class="hrpd-toolkit-card"><h3>Group Password Reset</h3><p>Reset passwords by role, choose who to exclude, and send secure reset links when needed.</p><p><a href="https://github.com/hackrepair/group-password-reset" target="_blank" rel="noopener noreferrer">Explore Group Password Reset &rarr;</a></p></div><div class="hrpd-toolkit-card"><h3>Site Inspector</h3><p>Review security-related observations, keep inspection reports, and see what changed.</p><p><a href="https://github.com/hackrepair/thrg-site-inspector" target="_blank" rel="noopener noreferrer">Explore Site Inspector &rarr;</a></p></div><p>Each plugin works independently. Links open the project pages in a new tab.</p></div>';
	return $result;
}
add_filter( 'plugins_api', 'hrpd_companion_plugin_info', 100, 3 );
