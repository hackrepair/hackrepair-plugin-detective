<?php
/**
 * Update checks against this plugin's GitHub releases.
 *
 * @package HackRepair_Plugin_Detective
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;

/**
 * Points WordPress at GitHub releases for this plugin's updates.
 *
 * This plugin is not distributed on the WordPress.org repository, so it
 * would otherwise never show an "update available" notice. This class
 * wires in the Plugin Update Checker library (vendored in
 * `plugin-update-checker/`) so WordPress checks this plugin's GitHub
 * releases the same way it checks wordpress.org for everything else.
 *
 * A release's own uploaded zip asset is used instead of GitHub's
 * auto-generated source archive, because the auto-generated archive
 * extracts into a version-stamped folder name (e.g.
 * `hackrepair-plugin-detective-2.0.0/`) which would change the
 * installed plugin's directory name and deactivate it on update.
 *
 * The `hackrepair/hackrepair-plugin-detective` repository is private
 * while the plugin is in review. GitHub releases on a private repo are
 * invisible to an anonymous update check, so this checker effectively
 * does nothing until the repository is switched to public -- at that
 * point update checks and auto-updates start working with no plugin
 * change required.
 *
 * @since 2.1.0
 */
final class HRPD_Updater {

	const REPOSITORY_URL = 'https://github.com/hackrepair/hackrepair-plugin-detective/';
	const BRANCH         = 'main';
	const ASSET_PATTERN  = '/^hackrepair-plugin-detective\.[0-9]+(?:\.[0-9]+){2}\.zip$/';

	/**
	 * Underlying Plugin Update Checker instance.
	 *
	 * @var \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	private $checker = null;

	/**
	 * Build and configure the update checker.
	 *
	 * Safe to call even if the vendored library failed to load for some
	 * reason (e.g. a stripped-down copy of the plugin folder) — it just
	 * no-ops rather than fataling.
	 */
	public function init() {
		// Registered before the library check below: the details window works
		// from this plugin's own readme.txt, so it doesn't depend on GitHub.
		add_filter( 'plugins_api', array( $this, 'local_plugin_info' ), 30, 3 );

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$this->checker = PucFactory::buildUpdateChecker(
			self::REPOSITORY_URL,
			HRPD_FILE,
			'hackrepair-plugin-detective'
		);

		$this->checker->setBranch( self::BRANCH );

		// The GitHub copy of the readme lists screenshot captions only. The
		// images ship inside this plugin, so add them to the details window.
		$this->checker->addFilter( 'pre_inject_info', array( $this, 'add_screenshots' ) );

		$api = $this->checker->getVcsApi();

		// A different loaded version of the update checker may lack one of the
		// controls below. Disable its hooks instead of offering a source archive.
		if ( ! $api || ! method_exists( $api, 'enableReleaseAssets' ) ||
			! method_exists( $api, 'setReleaseFilter' ) || ! method_exists( $this->checker, 'addFilter' ) ) {
			$this->checker->removeHooks();
			$this->checker = null;
			return;
		}

		require_once __DIR__ . '/class-hrpd-update-notices.php';
		new HRPD_Update_Notices( $this->checker );

		$api->enableReleaseAssets( self::ASSET_PATTERN, Api::REQUIRE_RELEASE_ASSETS );

		// The library normally tries a tag and then a branch when a release is
		// rejected. Both alternatives download an auto-generated source archive.
		$this->checker->addFilter(
			'vcs_update_detection_strategies',
			static function ( $strategies ) {
				return isset( $strategies[ Api::STRATEGY_LATEST_RELEASE ] )
					? array( Api::STRATEGY_LATEST_RELEASE => $strategies[ Api::STRATEGY_LATEST_RELEASE ] )
					: array();
			}
		);

		// GitHubApi selects the first asset matching ASSET_PATTERN. Validate
		// that same first match against the release tag before it is selected.
		$api->setReleaseFilter(
			static function ( $version, $release ) {
				if ( ! is_string( $version ) || ! preg_match( '/^[0-9]+(?:\.[0-9]+){2}$/', $version ) ||
					! isset( $release->tag_name, $release->assets ) ||
					'v' . $version !== $release->tag_name || ! is_array( $release->assets ) ) {
					return false;
				}

				$expected = 'hackrepair-plugin-detective.' . $version . '.zip';
				foreach ( $release->assets as $asset ) {
					if ( isset( $asset->name ) && is_string( $asset->name ) &&
						preg_match( self::ASSET_PATTERN, $asset->name ) ) {
						return $expected === $asset->name;
					}
				}

				return false;
			},
			Api::RELEASE_FILTER_SKIP_PRERELEASE,
			1
		);
	}

	/**
	 * Expose the underlying checker instance, mostly for debugging.
	 *
	 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	public function checker() {
		return $this->checker;
	}

	/**
	 * Build the Screenshots tab from the images in assets/screenshots/.
	 *
	 * Captions come from the readme's Screenshots list, matched by number
	 * (screenshot-1.png is the first caption). Missing images are skipped.
	 *
	 * @param array $captions Captions in readme order.
	 * @return string HTML, or an empty string when there is nothing to show.
	 */
	private function screenshots_html( $captions ) {
		$html = '';
		foreach ( array_values( (array) $captions ) as $i => $caption ) {
			$n = $i + 1;
			if ( ! is_readable( HRPD_DIR . 'assets/screenshots/screenshot-' . $n . '.png' ) ) {
				continue;
			}
			$caption = wp_strip_all_tags( $caption );
			$html   .= '<li><img src="' . esc_url( HRPD_URL . 'assets/screenshots/screenshot-' . $n . '.png' ) . '" alt="' . esc_attr( $caption ) . '" /><p>' . esc_html( $caption ) . '</p></li>';
		}
		return '' === $html ? '' : '<ol>' . $html . '</ol>';
	}

	/**
	 * Parsed captions from the installed readme.txt.
	 *
	 * @return array
	 */
	private function local_captions() {
		$readme_file = HRPD_DIR . 'readme.txt';
		if ( ! is_readable( $readme_file ) || ! class_exists( 'PucReadmeParser' ) ) {
			return array();
		}
		$parser = new \PucReadmeParser();
		$readme = $parser->parse_readme( $readme_file );
		return ! empty( $readme['screenshots'] ) ? (array) $readme['screenshots'] : array();
	}

	/**
	 * Filter: put the shipped screenshots into the update checker's answer.
	 *
	 * @param object|null $info Plugin info from the update checker.
	 * @return object|null
	 */
	public function add_screenshots( $info ) {
		if ( ! is_object( $info ) || ! isset( $info->sections ) ) {
			return $info;
		}
		$html = $this->screenshots_html( $this->local_captions() );
		if ( '' !== $html ) {
			$info->sections['screenshots'] = $html;
		}
		return $info;
	}

	/**
	 * Fill the Plugins screen's "View details" window from the installed readme.txt.
	 *
	 * The update checker answers this window from the GitHub copy of readme.txt.
	 * When that copy can't be reached (the repository is private, GitHub is
	 * down, or the site blocks outside requests) WordPress would otherwise
	 * show "Plugin not found." This runs after the update checker and only
	 * steps in when nobody else has answered.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function local_plugin_info( $result, $action = null, $args = null ) {
		if ( false !== $result || 'plugin_information' !== $action || ! is_object( $args ) ||
			! isset( $args->slug ) || 'hackrepair-plugin-detective' !== $args->slug ) {
			return $result;
		}

		$readme_file = HRPD_DIR . 'readme.txt';
		if ( ! is_readable( $readme_file ) || ! class_exists( 'PucReadmeParser' ) ) {
			return $result;
		}

		$parser = new \PucReadmeParser();
		$readme = $parser->parse_readme( $readme_file );
		if ( empty( $readme['sections'] ) ) {
			return $result;
		}

		// The readme parser files the FAQ under its long heading; WordPress's
		// details window calls that tab "faq".
		$wanted = array(
			'description'                => 'description',
			'installation'               => 'installation',
			'frequently_asked_questions' => 'faq',
			'changelog'                  => 'changelog',
		);

		$sections = array();
		foreach ( $wanted as $from => $to ) {
			if ( ! empty( $readme['sections'][ $from ] ) ) {
				$sections[ $to ] = $readme['sections'][ $from ];
			}
		}

		$shots = $this->screenshots_html( isset( $readme['screenshots'] ) ? $readme['screenshots'] : array() );
		if ( '' !== $shots ) {
			$sections['screenshots'] = $shots;
		}

		$info = new \stdClass();

		$info->name         = "The Hack Repair Guy's Plugin Detective";
		$info->slug         = 'hackrepair-plugin-detective';
		$info->version      = HRPD_VERSION;
		$info->author       = '<a href="https://hackrepair.com/">Jim Walker, The Hack Repair Guy</a>';
		$info->homepage     = 'https://hackrepair.com/';
		$info->requires     = isset( $readme['requires_at_least'] ) ? $readme['requires_at_least'] : '';
		$info->tested       = isset( $readme['tested_up_to'] ) ? $readme['tested_up_to'] : '';
		$info->requires_php = isset( $readme['requires_php'] ) ? $readme['requires_php'] : '';
		$info->last_updated = gmdate( 'Y-m-d H:i:s', (int) filemtime( HRPD_FILE ) );
		$info->sections     = $sections;
		$info->banners      = array(
			'low'  => HRPD_URL . 'assets/banner-772x250.png',
			'high' => HRPD_URL . 'assets/banner-1544x500.png',
		);
		$info->icons        = array(
			'1x' => HRPD_URL . 'assets/icon-128x128.png',
			'2x' => HRPD_URL . 'assets/icon-256x256.png',
		);

		return $info;
	}
}
