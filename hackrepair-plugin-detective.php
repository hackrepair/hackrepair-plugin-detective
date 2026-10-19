<?php
/**
 * Plugin Name:       The Hack Repair Guy's Plugin Detective
 * Plugin URI:        https://hackrepair.com/
 * Description:       Find the plugin that broke your site in a few quick checks, even when wp-admin is down. Clears stuck maintenance mode too. By Jim Walker, The Hack Repair Guy.
 * Version:           3.4.5
 * Requires at least: 6.9.4
 * Requires PHP:      7.4
 * Author:            Jim Walker, The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * Update URI:        https://github.com/hackrepair/hackrepair-plugin-detective
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hackrepair-plugin-detective
 * Domain Path:       /languages
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

define( 'HRPD_VERSION', '3.4.5' );
define( 'HRPD_FILE', __FILE__ );
define( 'HRPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'HRPD_URL', plugin_dir_url( __FILE__ ) );

require_once HRPD_DIR . 'includes/class-hrpd-log.php';
require_once HRPD_DIR . 'includes/class-hrpd-detective.php';
require_once HRPD_DIR . 'includes/class-hrpd-case.php';
require_once HRPD_DIR . 'includes/class-hrpd-background.php';
require_once HRPD_DIR . 'includes/class-hrpd-report.php';
require_once HRPD_DIR . 'includes/class-hrpd-admin.php';

$hrpd_update_checker = HRPD_DIR . 'plugin-update-checker/plugin-update-checker.php';

if ( is_readable( $hrpd_update_checker ) ) {
	require_once $hrpd_update_checker;
}
unset( $hrpd_update_checker );

require_once HRPD_DIR . 'includes/class-hrpd-updater.php';

HRPD_Admin::init();

/*
 * Deliberately NOT gated by is_admin() -- WordPress's own update-check and
 * auto-update machinery runs on cron, where is_admin() is false. Gating
 * this the same way an admin-only boot is gated would silently break
 * update checks and auto-updates.
 */
$hrpd_updater = new HRPD_Updater();
$hrpd_updater->init();
unset( $hrpd_updater );

require_once __DIR__ . '/includes/companion-plugins.php';
