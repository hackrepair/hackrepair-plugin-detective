<?php
/**
 * Standalone Fix page boot.
 *
 * Starts WordPress in its lightweight SHORTINIT mode through the site's own
 * wp-load.php, then loads only the core files the Fix page needs.
 * Must-use plugins, regular plugins and the theme are never loaded, so none
 * of them can crash the Fix page while it is fixing them.
 *
 * Maintenance note: when a WordPress release adds, renames or moves one of
 * the core files in hrpd_core_files(), update the list and note it in
 * the readme.txt changelog.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

// This file is included by fix/index.php and fix/api.php only.
if ( ! defined( 'HRPD_STANDALONE' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'HRPD_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'HRPD_FIX_DIR', dirname( __DIR__ ) . '/' );

/**
 * Find the folder that holds wp-load.php by walking up from the plugin.
 *
 * Handles the standard layout and installs that keep WordPress in a `wp/`
 * subfolder.
 *
 * @return string Absolute path ending in a slash, or an empty string.
 */
function hrpd_find_wp_root(): string {
	$dir = dirname( HRPD_PLUGIN_DIR );
	for ( $i = 0; $i < 8; $i++ ) {
		if ( is_file( $dir . '/wp-load.php' ) ) {
			return $dir . '/';
		}
		if ( is_file( $dir . '/wp/wp-load.php' ) ) {
			return $dir . '/wp/';
		}
		$parent = dirname( $dir );
		if ( $parent === $dir ) {
			break;
		}
		$dir = $parent;
	}
	return '';
}

/**
 * Plain error page used before WordPress is available.
 *
 * @param string $message Message to show.
 */
function hrpd_boot_fail( string $message ): void {
	http_response_code( 500 );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-store' );
	echo '<!doctype html><meta charset="utf-8"><title>Plugin Detective</title>';
	echo '<div style="font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:10vh auto;padding:0 16px">';
	echo '<h1 style="font-size:22px">Plugin Detective can\'t start</h1><p>' . htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress is not loaded yet; htmlspecialchars() escapes the message.
	echo '<p>Still stuck? <a href="https://hackrepair.com/">The Hack Repair Guy can help.</a></p></div>';
	exit;
}

/**
 * Core files needed on top of SHORTINIT, in load order.
 *
 * @return string[] Paths relative to wp-includes/.
 */
function hrpd_core_files(): array {
	return array(
		'l10n.php',
		'class-wp-textdomain-registry.php',
		'class-wp-locale.php',
		'class-wp-locale-switcher.php',
		'capabilities.php',
		'class-wp-roles.php',
		'class-wp-role.php',
		'class-wp-user.php',
		'user.php',
		'class-wp-session-tokens.php',
		'class-wp-user-meta-session-tokens.php',
		'class-wp-application-passwords.php',
		'kses.php',
		'link-template.php',
		'http.php',
		'general-template.php',
		'theme.php',
		'class-wp-theme.php',
		'pluggable.php',
	);
}

/**
 * Core files for WordPress's HTTP API, loaded only when the Fix page
 * needs to go online (the background check on a problem plugin).
 *
 * @return string[] Paths relative to wp-includes/.
 */
function hrpd_http_files(): array {
	return array(
		'class-wp-http.php',
		'class-wp-http-streams.php',
		'class-wp-http-curl.php',
		'class-wp-http-proxy.php',
		'class-wp-http-cookie.php',
		'class-wp-http-encoding.php',
		'class-wp-http-response.php',
		'class-wp-http-requests-response.php',
		'class-wp-http-requests-hooks.php',
	);
}

/**
 * Load WordPress's HTTP API on demand.
 *
 * @return bool False when a file is missing (the caller skips going online).
 */
function hrpd_load_http(): bool {
	if ( class_exists( 'WP_Http', false ) ) {
		return true;
	}
	foreach ( hrpd_http_files() as $file ) {
		$path = ABSPATH . WPINC . '/' . $file;
		if ( ! is_file( $path ) ) {
			return false;
		}
	}
	foreach ( hrpd_http_files() as $file ) {
		require_once ABSPATH . WPINC . '/' . $file;
	}
	return class_exists( 'WP_Http', false ) && function_exists( 'wp_remote_get' );
}

$hrpd_wp_root = hrpd_find_wp_root();
if ( '' === $hrpd_wp_root ) {
	hrpd_boot_fail( 'WordPress could not be found above the plugin folder. Use the Plugin Detective link in wp-admin under Tools instead.' );
}

/*
 * Hooks registered before WordPress loads (core turns these into real hooks):
 * - Skip a stuck .maintenance file so the Fix page can remove it.
 * - Skip the page-cache drop-in; the Fix page must never be served from cache.
 */
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Pre-seeding core's hook table before plugin.php loads is the documented way to add early hooks.
$GLOBALS['wp_filter']['enable_maintenance_mode'][10][]              = array(
	'function'      => static function (): bool {
		return false;
	},
	'accepted_args' => 1,
);
$GLOBALS['wp_filter']['enable_loading_advanced_cache_dropin'][10][] = array(
	'function'      => static function (): bool {
		return false;
	},
	'accepted_args' => 1,
);
// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'SHORTINIT', true );
define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared page-cache convention honored by caching plugins and hosts.

require $hrpd_wp_root . 'wp-load.php';

foreach ( hrpd_core_files() as $hrpd_file ) {
	$hrpd_path = ABSPATH . WPINC . '/' . $hrpd_file;
	if ( ! is_file( $hrpd_path ) ) {
		hrpd_boot_fail( 'A WordPress core file is missing (' . $hrpd_file . '). WordPress may need to be reinstalled, or this version of Plugin Detective needs an update.' );
	}
	require_once $hrpd_path;
}
unset( $hrpd_file, $hrpd_path, $hrpd_wp_root );

// Constants normally set later in wp-settings.php.
wp_plugin_directory_constants();
wp_cookie_constants();
wp_ssl_constants();

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core global normally created in wp-settings.php; plugin_basename() needs it.
$GLOBALS['wp_plugin_paths'] = array();

if ( class_exists( 'WP_Textdomain_Registry' ) ) {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global that SHORTINIT does not create.
	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
	if ( method_exists( $GLOBALS['wp_textdomain_registry'], 'init' ) ) {
		$GLOBALS['wp_textdomain_registry']->init();
	}
}

/*
 * default-filters.php hooks up functions from core files the Fix page
 * doesn't load (REST API, cron, and so on). Drop those hooks so firing an
 * action like `auth_cookie_malformed` can't call a missing function.
 */
foreach ( $GLOBALS['wp_filter'] as $hrpd_hook_name => $hrpd_hook ) {
	if ( ! $hrpd_hook instanceof WP_Hook ) {
		continue;
	}
	foreach ( $hrpd_hook->callbacks as $hrpd_priority => $hrpd_callbacks ) {
		foreach ( $hrpd_callbacks as $hrpd_callback ) {
			$hrpd_function = $hrpd_callback['function'];
			if ( is_string( $hrpd_function ) && false === strpos( $hrpd_function, '::' ) && ! function_exists( $hrpd_function ) ) {
				remove_filter( $hrpd_hook_name, $hrpd_function, $hrpd_priority );
			}
		}
	}
}
unset( $hrpd_hook_name, $hrpd_hook, $hrpd_priority, $hrpd_callbacks, $hrpd_callback, $hrpd_function );

// Block parsing isn't loaded here; this core kses filter depends on it.
remove_filter( 'pre_kses', 'wp_pre_kses_block_attributes', 10 );

/*
 * The theme and init stages never run in the Fix page. Mark them as
 * done so core's translation loader doesn't treat every string as "too early".
 */
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core action counter; see note above.
$GLOBALS['wp_actions']['after_setup_theme'] = 1;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core action counter; see note above.
$GLOBALS['wp_actions']['init'] = 1;

register_theme_directory( get_theme_root() );
wp_set_internal_encoding();

if ( is_multisite() ) {
	hrpd_boot_fail( 'Plugin Detective does not support WordPress multisite yet.' );
}

require_once HRPD_PLUGIN_DIR . 'includes/class-hrpd-log.php';
require_once HRPD_PLUGIN_DIR . 'includes/class-hrpd-detective.php';
require_once HRPD_PLUGIN_DIR . 'includes/class-hrpd-case.php';
require_once HRPD_PLUGIN_DIR . 'includes/class-hrpd-background.php';
require_once HRPD_PLUGIN_DIR . 'includes/class-hrpd-report.php';
require_once HRPD_FIX_DIR . 'includes/class-hrpd-totp.php';
require_once HRPD_FIX_DIR . 'includes/class-hrpd-session.php';
require_once HRPD_FIX_DIR . 'includes/class-hrpd-login.php';
require_once HRPD_FIX_DIR . 'includes/class-hrpd-strings.php';
require_once HRPD_FIX_DIR . 'includes/class-hrpd-app.php';

load_plugin_textdomain( 'hackrepair-plugin-detective', false, basename( HRPD_PLUGIN_DIR ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Standalone boot; just-in-time loading does not run here.
