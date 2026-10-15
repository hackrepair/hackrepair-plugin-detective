<?php
/**
 * Plugin Detective Fix page.
 *
 * Bookmark this address. It works even when wp-admin doesn't:
 * https://example.com/wp-content/plugins/hackrepair-plugin-detective/troubleshoot/
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

define( 'HRPD_STANDALONE', true );

require __DIR__ . '/includes/bootstrap.php';

HRPD_App::send_common_headers();
header( 'Content-Type: text/html; charset=utf-8' );
header(
	"Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-src "
	. implode( ' ', HRPD_App::frame_origins() )
	. "; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
);

$hrpd_main_file = HRPD_PLUGIN_DIR . 'hackrepair-plugin-detective.php';
$hrpd_asset_ver = static function ( string $relative ): string {
	$path = HRPD_TROUBLESHOOT_DIR . $relative;
	return is_file( $path ) ? (string) filemtime( $path ) : '1';
};
$hrpd_boot      = HRPD_App::boot_data();
?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', determine_locale() ) ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $hrpd_boot['strings']['appName'] . ' · ' . $hrpd_boot['siteName'] ); ?></title>
	<?php // phpcs:disable WordPress.WP.EnqueuedResources -- Standalone page rendered outside WordPress; the enqueue system does not run here. ?>
	<link rel="icon" href="<?php echo esc_url( plugins_url( 'assets/img/hackrepair-heart-64.png', $hrpd_main_file ) ); ?>" type="image/png">
	<link rel="stylesheet" href="<?php echo esc_url( plugins_url( 'troubleshoot/assets/app.css', $hrpd_main_file ) . '?ver=' . $hrpd_asset_ver( 'assets/app.css' ) ); ?>">
</head>
<body>
	<header class="hrpd-top">
		<div class="hrpd-top__brand">
			<img class="hrpd-mascot" src="<?php echo esc_url( plugins_url( 'assets/img/hackrepair-heart.png', $hrpd_main_file ) ); ?>" alt="" width="44" height="44">
			<div>
				<strong><?php echo esc_html( $hrpd_boot['strings']['appName'] ); ?></strong>
				<span><?php echo esc_html( $hrpd_boot['strings']['byline'] ); ?></span>
			</div>
		</div>
		<nav class="hrpd-top__nav" id="hrpd-nav"></nav>
	</header>
	<main id="hrpd-app" class="hrpd-main" aria-live="polite">
		<noscript><p><?php esc_html_e( 'Plugin Detective needs JavaScript turned on in your browser.', 'hackrepair-plugin-detective' ); ?></p></noscript>
	</main>
	<script type="application/json" id="hrpd-boot"><?php echo wp_json_encode( $hrpd_boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	<script src="<?php echo esc_url( plugins_url( 'troubleshoot/assets/app.js', $hrpd_main_file ) . '?ver=' . $hrpd_asset_ver( 'assets/app.js' ) ); ?>"></script>
	<?php // phpcs:enable WordPress.WP.EnqueuedResources ?>
</body>
</html>
