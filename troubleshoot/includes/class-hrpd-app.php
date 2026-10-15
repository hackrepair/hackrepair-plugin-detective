<?php
/**
 * Troubleshooter page data and JSON API.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Troubleshooter app.
 */
final class HRPD_App {

	/**
	 * Actions that work before sign-in.
	 */
	private const PUBLIC_ACTIONS = array( 'status', 'login', 'verify_code' );

	/**
	 * Actions that need a signed-in user and the CSRF token.
	 */
	private const PRIVATE_ACTIONS = array( 'logout', 'overview', 'open_case', 'start_case', 'answer', 'undo', 'finish', 'abandon', 'remove_maintenance', 'background' );

	/**
	 * Largest request body accepted, in bytes.
	 */
	private const MAX_BODY = 65536;

	/**
	 * Send the security headers shared by the page and the API.
	 */
	public static function send_common_headers(): void {
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Frame-Options: DENY' );
	}

	/**
	 * Handle one API request and exit.
	 */
	public static function handle_api(): void {
		// Anything WordPress or PHP prints before the JSON would break it.
		ob_start();

		self::send_common_headers();
		header( 'Content-Type: application/json; charset=utf-8' );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			self::respond( new WP_Error( 'hrpd_method', 'POST only.' ), 405 );
		}

		if ( ! self::same_origin() || ! isset( $_SERVER['HTTP_X_HRPD'] ) ) {
			self::respond( new WP_Error( 'hrpd_origin', __( 'That request didn\'t come from the troubleshooter.', 'hackrepair-plugin-detective' ) ), 403 );
		}

		$body    = (string) file_get_contents( 'php://input', false, null, 0, self::MAX_BODY );
		$request = json_decode( $body, true );
		if ( ! is_array( $request ) ) {
			self::respond( new WP_Error( 'hrpd_bad_request', __( 'The troubleshooter sent something it couldn\'t read. Reload the page and try again.', 'hackrepair-plugin-detective' ) ), 400 );
		}

		$action = isset( $request['action'] ) && is_string( $request['action'] ) ? sanitize_key( $request['action'] ) : '';

		if ( in_array( $action, self::PUBLIC_ACTIONS, true ) ) {
			self::respond( self::run_public( $action, $request ) );
		}

		if ( ! in_array( $action, self::PRIVATE_ACTIONS, true ) ) {
			self::respond( new WP_Error( 'hrpd_unknown_action', __( 'Unknown request.', 'hackrepair-plugin-detective' ) ), 400 );
		}

		if ( ! HRPD_Session::user() ) {
			self::respond( new WP_Error( 'hrpd_signed_out', __( 'You\'ve been signed out. Sign in again to keep going.', 'hackrepair-plugin-detective' ) ), 401 );
		}

		$token = isset( $_SERVER['HTTP_X_HRPD_CSRF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HRPD_CSRF'] ) ) : '';
		if ( ! HRPD_Session::check_csrf( $token ) ) {
			self::respond( new WP_Error( 'hrpd_csrf', __( 'This page has gone stale. Reload it and try again.', 'hackrepair-plugin-detective' ) ), 403 );
		}

		self::respond( self::run_private( $action, $request ) );
	}

	/**
	 * Data the page needs to start.
	 *
	 * @return array<string, mixed>
	 */
	public static function boot_data(): array {
		$user = HRPD_Session::user();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page parameters.
		$requested_url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
		$restore       = isset( $_GET['restore'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'api'          => plugins_url( 'troubleshoot/api.php', HRPD_PLUGIN_DIR . 'hackrepair-plugin-detective.php' ),
			'adminUrl'     => admin_url(),
			'toolsUrl'     => admin_url( 'tools.php?page=hackrepair-plugin-detective' ),
			'home'         => home_url( '/' ),
			'siteName'     => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			'signedIn'     => (bool) $user,
			'csrf'         => HRPD_Session::csrf_token(),
			'loginEnabled' => HRPD_Login::enabled(),
			'initialUrl'   => self::safe_site_url( $requested_url ),
			'restore'      => $restore,
			'helpUrl'      => 'https://hackrepair.com/',
			'mascotUrl'    => plugins_url( 'assets/img/hackrepair-heart.png', HRPD_PLUGIN_DIR . 'hackrepair-plugin-detective.php' ),
			'strings'      => HRPD_Strings::all(),
		);
	}

	/**
	 * Origins the troubleshooter may frame (the site itself).
	 *
	 * @return string[]
	 */
	public static function frame_origins(): array {
		$origins = array( "'self'" );
		$home    = wp_parse_url( home_url() );
		if ( ! empty( $home['scheme'] ) && ! empty( $home['host'] ) ) {
			$origins[] = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		}
		return array_unique( $origins );
	}

	/**
	 * Run an action that works before sign-in.
	 *
	 * @param string               $action  Action.
	 * @param array<string, mixed> $request Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function run_public( string $action, array $request ) {
		switch ( $action ) {
			case 'login':
				$result = HRPD_Login::password( self::text( $request, 'username' ), self::raw( $request, 'password' ) );
				break;

			case 'verify_code':
				$result = HRPD_Login::second_step( self::text( $request, 'challenge' ), self::text( $request, 'code' ) );
				break;

			default:
				$result = array( 'ok' => true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['signedIn'] = (bool) HRPD_Session::user();
		$result['csrf']     = HRPD_Session::csrf_token();
		return $result;
	}

	/**
	 * Run an action for a signed-in user.
	 *
	 * @param string               $action  Action.
	 * @param array<string, mixed> $request Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function run_private( string $action, array $request ) {
		switch ( $action ) {
			case 'logout':
				HRPD_Session::end();
				return array( 'signedIn' => false );

			case 'overview':
				return self::overview();

			case 'open_case':
				HRPD_Case::open( self::safe_site_url( self::text( $request, 'url' ) ), (int) HRPD_Session::user()->ID );
				return self::overview();

			case 'start_case':
				$required = isset( $request['required'] ) && is_array( $request['required'] ) ? array_map( 'strval', $request['required'] ) : array();
				$result   = HRPD_Case::start( $required );
				return is_wp_error( $result ) ? $result : self::overview();

			case 'answer':
				$result = HRPD_Case::answer( self::text( $request, 'outcome' ) );
				return is_wp_error( $result ) ? $result : self::overview();

			case 'undo':
				$result = HRPD_Case::undo();
				return is_wp_error( $result ) ? $result : self::overview();

			case 'finish':
				$mode = self::text( $request, 'mode' );
				$mode = in_array( $mode, array( 'keep', 'delete' ), true ) ? $mode : 'restore';
				$done = HRPD_Case::finish( $mode );
				return is_wp_error( $done ) ? $done : self::overview();

			case 'abandon':
				HRPD_Case::abandon();
				return self::overview();

			case 'remove_maintenance':
				return self::remove_maintenance();

			case 'background':
				return self::background();
		}

		return new WP_Error( 'hrpd_unknown_action', __( 'Unknown request.', 'hackrepair-plugin-detective' ) );
	}

	/**
	 * Everything the app shows.
	 *
	 * @return array<string, mixed>
	 */
	private static function overview(): array {
		$user      = HRPD_Session::user();
		$installed = HRPD_Case::installed_plugins();
		$record    = HRPD_Case::get();

		if ( ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$theme = wp_get_theme();

		return array(
			'user'        => array(
				'name'   => $user ? $user->display_name : '',
				'source' => HRPD_Session::source(),
			),
			'csrf'        => HRPD_Session::csrf_token(),
			'plugins'     => self::plugin_list( $installed, HRPD_Case::active_plugins() ),
			'theme'       => $theme->exists() ? $theme->get( 'Name' ) : '',
			'muPlugins'   => array_values( wp_list_pluck( get_mu_plugins(), 'Name' ) ),
			'dropins'     => array_keys( get_dropins() ),
			'maintenance' => is_file( ABSPATH . '.maintenance' ),
			'case'        => $record ? self::case_view( $record, $installed ) : null,
			'lastResult'  => get_option( HRPD_Case::LAST_RESULT_OPTION, null ),
		);
	}

	/**
	 * Active plugins (other than this one) with display details.
	 *
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @param string[]                            $active    Active plugin files.
	 * @return array<int, array<string, string>>
	 */
	private static function plugin_list( array $installed, array $active ): array {
		$list = array();
		foreach ( $active as $file ) {
			if ( HRPD_Case::self_basename() === $file || ! isset( $installed[ $file ] ) ) {
				continue;
			}
			$list[] = self::plugin_info( $file, $installed );
		}
		return $list;
	}

	/**
	 * Display details for one plugin.
	 *
	 * @param string                              $file      Plugin file.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @return array<string, string>
	 */
	private static function plugin_info( string $file, array $installed ): array {
		$data = $installed[ $file ] ?? array();
		return array(
			'file'    => $file,
			'name'    => wp_strip_all_tags( (string) ( $data['Name'] ?? $file ) ),
			'version' => wp_strip_all_tags( (string) ( $data['Version'] ?? '' ) ),
		);
	}

	/**
	 * Case details for the app.
	 *
	 * @param array<string, mixed>                $record      Case data.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @return array<string, mixed>
	 */
	private static function case_view( array $record, array $installed ): array {
		$names = static function ( array $files ) use ( $installed ): array {
			$out = array();
			foreach ( $files as $file ) {
				$out[] = self::plugin_info( (string) $file, $installed );
			}
			return $out;
		};

		// Step 2 lists every candidate; show them A to Z by name, not by folder.
		$candidates = $names( (array) $record['candidates'] );
		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		$view = array(
			'id'         => $record['id'],
			'status'     => $record['status'],
			'url'        => $record['url'],
			'candidates' => $candidates,
			'required'   => $names( (array) $record['required'] ),
			'answers'    => count( (array) $record['clues'] ),
			'step'       => null,
			'result'     => null,
			'checkUrl'   => add_query_arg( 'hrpd_check', $record['id'] . '-' . count( (array) $record['clues'] ), (string) $record['url'] ),
		);

		if ( HRPD_Case::STATUS_TESTING === $record['status'] ) {
			$step         = HRPD_Detective::next( (array) $record['suspects'], (array) $record['clues'] );
			$view['step'] = array(
				'phase'      => $step['phase'] ?? '',
				'round'      => (int) ( $step['round'] ?? 1 ),
				'testing'    => $names( (array) ( $step['test'] ?? array() ) ),
				'remaining'  => count( (array) ( $step['remaining'] ?? array() ) ),
				'roundsLeft' => HRPD_Detective::rounds_left( count( (array) ( $step['remaining'] ?? array() ) ) ),
			);
		}

		if ( HRPD_Case::STATUS_SOLVED === $record['status'] && is_array( $record['result'] ) ) {
			$culprit        = (string) ( $record['result']['culprit'] ?? '' );
			$view['result'] = array(
				'result'     => (string) $record['result']['result'],
				'culprit'    => '' !== $culprit ? self::plugin_info( $culprit, $installed ) : null,
				'lastBroken' => $names( (array) ( $record['result']['last_broken'] ?? array() ) ),
				'report'     => HRPD_Report::build( $record, $installed, HRPD_Report::OUTCOME_PENDING ),
				'checkable'  => '' !== $culprit && HRPD_Background::enabled() && '' !== HRPD_Background::slug( $culprit ),
			);
		}

		return $view;
	}

	/**
	 * Background check on the plugin the open case found, plus the refreshed report.
	 *
	 * Uses the case on file, never a plugin named by the browser.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function background() {
		$record = HRPD_Case::get();
		if ( ! $record || HRPD_Case::STATUS_SOLVED !== $record['status'] ||
			HRPD_Detective::RESULT_CULPRIT !== ( $record['result']['result'] ?? '' ) ) {
			return new WP_Error( 'hrpd_no_culprit', __( 'There\'s no problem plugin to check right now.', 'hackrepair-plugin-detective' ) );
		}

		$installed = HRPD_Case::installed_plugins();
		$culprit   = (string) $record['result']['culprit'];
		if ( ! isset( $installed[ $culprit ] ) ) {
			return new WP_Error( 'hrpd_no_culprit', __( 'There\'s no problem plugin to check right now.', 'hackrepair-plugin-detective' ) );
		}

		if ( HRPD_Background::enabled() && null === HRPD_Background::cached( $culprit, $installed[ $culprit ] ) && ! hrpd_load_http() ) {
			$bg = array(
				'status'  => 'ok',
				'summary' => HRPD_Background::summarize(
					array(
						'wporg' => array( 'state' => 'error' ),
						'vulns' => array( 'state' => 'error' ),
					)
				),
			);
		} else {
			$bg = HRPD_Background::run( $culprit, $installed[ $culprit ] );
		}

		return array(
			'caseId'     => $record['id'],
			'background' => self::background_view( $bg ),
			'report'     => HRPD_Report::build( $record, $installed, HRPD_Report::OUTCOME_PENDING ),
		);
	}

	/**
	 * The parts of a background check the screen needs.
	 *
	 * @param array<string, mixed> $bg Background check result.
	 * @return array<string, mixed>
	 */
	private static function background_view( array $bg ): array {
		if ( 'off' === ( $bg['status'] ?? '' ) ) {
			return array( 'status' => 'off' );
		}
		$summary = (array) ( $bg['summary'] ?? array() );
		return array(
			'status' => 'ok',
			'tone'   => (string) ( $summary['tone'] ?? 'ok' ),
			'lines'  => array_values( (array) ( $summary['lines'] ?? array() ) ),
			'items'  => array_values( (array) ( $summary['items'] ?? array() ) ),
			'credit' => (string) ( $summary['credit'] ?? '' ),
			'link'   => (string) ( $bg['wporg']['link'] ?? '' ),
		);
	}

	/**
	 * Remove a stuck .maintenance file.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function remove_maintenance() {
		$file = ABSPATH . '.maintenance';
		if ( ! is_file( $file ) ) {
			return self::overview();
		}

		wp_delete_file( $file );
		if ( is_file( $file ) ) {
			return new WP_Error( 'hrpd_maintenance', __( 'The maintenance file couldn\'t be removed. The server may not allow it; delete .maintenance from the site\'s main folder with your host\'s file manager.', 'hackrepair-plugin-detective' ) );
		}

		HRPD_Log::add( 'maintenance_removed', 'Removed a stuck .maintenance file.' );
		return self::overview();
	}

	/**
	 * Keep problem-page URLs on this site.
	 *
	 * @param string $url Requested URL.
	 * @return string
	 */
	private static function safe_site_url( string $url ): string {
		$home = home_url( '/' );
		if ( '' === $url ) {
			return $home;
		}

		$url       = esc_url_raw( $url, array( 'http', 'https' ) );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( $home, PHP_URL_HOST );

		if ( ! $url_host || strtolower( (string) $url_host ) !== strtolower( (string) $home_host ) ) {
			return $home;
		}

		return remove_query_arg( 'hrpd_check', $url );
	}

	/**
	 * True when the request comes from this site's own pages.
	 *
	 * @return bool
	 */
	private static function same_origin(): bool {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( '' === $origin ) {
			// Browsers always send Origin on POST from scripts; allow absent for old browsers, the custom header still blocks forms.
			return true;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$o    = wp_parse_url( $origin );
		$seen = ( $o['host'] ?? '' ) . ( isset( $o['port'] ) ? ':' . $o['port'] : '' );
		return '' !== $host && strtolower( $seen ) === strtolower( $host );
	}

	/**
	 * Read a text field from the request.
	 *
	 * @param array<string, mixed> $request Request body.
	 * @param string               $key     Field.
	 * @return string
	 */
	private static function text( array $request, string $key ): string {
		return isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? sanitize_text_field( (string) $request[ $key ] ) : '';
	}

	/**
	 * Read a field exactly as sent (passwords).
	 *
	 * @param array<string, mixed> $request Request body.
	 * @param string               $key     Field.
	 * @return string
	 */
	private static function raw( array $request, string $key ): string {
		return isset( $request[ $key ] ) && is_string( $request[ $key ] ) ? $request[ $key ] : '';
	}

	/**
	 * Send JSON and stop.
	 *
	 * @param array<string, mixed>|WP_Error $result Result.
	 * @param int                           $status HTTP status when not an error.
	 */
	private static function respond( $result, int $status = 200 ): void {
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( is_wp_error( $result ) ) {
			if ( 200 === $status ) {
				$status = 422;
			}
			$payload = array(
				'ok'    => false,
				'error' => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
			);
		} else {
			$payload = array(
				'ok'   => true,
				'data' => $result,
			);
		}

		status_header( $status );
		echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		exit;
	}
}
