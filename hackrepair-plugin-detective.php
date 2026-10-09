<?php
/**
 * Plugin Name:       HackRepair Plugin Detective
 * Plugin URI:        https://hackrepair.com/
 * Description:       Find the plugin that broke your site in a few quick checks, even when wp-admin is down. Clears stuck maintenance mode too. By The Hack Repair Guy.
 * Version:           2.0.0
 * Requires at least: 6.9.4
 * Requires PHP:      7.4
 * Author:            Jim Walker, The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hackrepair-plugin-detective
 * Domain Path:       /languages
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

define( 'HRPD_VERSION', '2.0.0' );
define( 'HRPD_FILE', __FILE__ );
define( 'HRPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'HRPD_URL', plugin_dir_url( __FILE__ ) );

require_once HRPD_DIR . 'includes/class-hrpd-log.php';
require_once HRPD_DIR . 'includes/class-hrpd-detective.php';
require_once HRPD_DIR . 'includes/class-hrpd-case.php';
require_once HRPD_DIR . 'includes/class-hrpd-admin.php';

HRPD_Admin::init();
