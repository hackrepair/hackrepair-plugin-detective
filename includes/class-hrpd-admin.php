<?php
/**
 * Admin side (wp-admin): Tools page, admin bar link, and the "plugins are switched off" safety notice.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
final class HRPD_Admin {

	public const PAGE_SLUG  = 'hackrepair-plugin-detective';
	public const CAPABILITY = 'activate_plugins';

	/**
	 * Hook everything up.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		if ( is_multisite() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 1000 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_admin_bar_style' ) );
		add_action( 'admin_post_hrpd_restore', array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_hrpd_clear_log', array( __CLASS__, 'handle_clear_log' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HRPD_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Troubleshooter address.
	 *
	 * @param string $problem_url Optional page to start on.
	 * @return string
	 */
	public static function troubleshooter_url( string $problem_url = '' ): string {
		$url = plugins_url( 'troubleshoot/', HRPD_FILE );
		if ( '' !== $problem_url ) {
			$url = add_query_arg( 'url', rawurlencode( $problem_url ), $url );
		}
		return $url;
	}

	/**
	 * Tools > Plugin Detective.
	 */
	public static function register_page(): void {
		add_management_page(
			__( 'Plugin Detective', 'hackrepair-plugin-detective' ),
			__( 'Plugin Detective', 'hackrepair-plugin-detective' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings-style link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Troubleshoot', 'hackrepair-plugin-detective' )
			)
		);
		return $links;
	}

	/**
	 * "Troubleshoot" in the admin bar, starting on the current page.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( self::CAPABILITY ) || is_multisite() ) {
			return;
		}

		$current = '';
		if ( ! is_admin() && isset( $_SERVER['REQUEST_URI'] ) ) {
			$current = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		$bar->add_node(
			array(
				'id'    => 'hrpd-troubleshoot',
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Troubleshoot', 'hackrepair-plugin-detective' ) . '</span>',
				'href'  => self::troubleshooter_url( $current ),
				'meta'  => array(
					'title' => __( 'Find the plugin causing a problem on this page', 'hackrepair-plugin-detective' ),
				),
			)
		);
	}

	/**
	 * Admin assets: the page stylesheet on our screen, the admin bar icon everywhere it shows.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( string $hook_suffix ): void {
		self::enqueue_admin_bar_style();

		// Our page, or anywhere the "plugins are switched off" notice shows.
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix && ! ( HRPD_Case::is_open() && current_user_can( self::CAPABILITY ) ) ) {
			return;
		}

		wp_enqueue_style( 'hrpd-admin', HRPD_URL . 'assets/css/admin.css', array(), self::asset_version( 'assets/css/admin.css' ) );

		if ( 'tools_page_' . self::PAGE_SLUG === $hook_suffix ) {
			wp_enqueue_script( 'hrpd-admin', HRPD_URL . 'assets/js/admin.js', array(), self::asset_version( 'assets/js/admin.js' ), true );
			wp_localize_script(
				'hrpd-admin',
				'hrpdAdmin',
				array( 'copied' => __( 'Copied', 'hackrepair-plugin-detective' ) )
			);
		}
	}

	/**
	 * Admin bar icon style, only for users who see the link.
	 */
	public static function enqueue_admin_bar_style(): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', '#wpadminbar #wp-admin-bar-hrpd-troubleshoot .ab-icon:before{content:"\f179";top:2px}' );
	}

	/**
	 * Notices shown across wp-admin.
	 */
	public static function notices(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( is_multisite() ) {
			self::notice( 'warning', __( 'Plugin Detective doesn\'t support multisite yet, so its tools are hidden on this network. Nothing on your sites was changed.', 'hackrepair-plugin-detective' ) );
			return;
		}

		$case = HRPD_Case::get();
		if ( $case ) {
			$off = count( array_diff( (array) $case['backup'], HRPD_Case::active_plugins() ) );
			if ( HRPD_Case::STATUS_SETUP === $case['status'] || $off > 0 ) {
				$message = $off > 0
					? sprintf(
						/* translators: %d: number of plugins switched off. */
						_n( 'Plugin Detective has %d plugin switched off for troubleshooting.', 'Plugin Detective has %d plugins switched off for troubleshooting.', $off, 'hackrepair-plugin-detective' ),
						$off
					)
					: __( 'A Plugin Detective troubleshooting session is still open.', 'hackrepair-plugin-detective' );

				printf(
					'<div class="notice notice-warning hrpd-notice"><p><strong>%1$s</strong> %2$s</p><div class="hrpd-notice__actions"><a class="button button-primary" href="%3$s">%4$s</a> %5$s</div></div>',
					esc_html( $message ),
					esc_html__( 'Finish the session or put everything back when you\'re done.', 'hackrepair-plugin-detective' ),
					esc_url( self::troubleshooter_url() ),
					esc_html__( 'Continue troubleshooting', 'hackrepair-plugin-detective' ),
					self::restore_button() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in restore_button().
				);
			}
		}

		if ( is_plugin_active( 'plugin-detective/plugin-detective.php' ) ) {
			self::notice( 'warning', __( 'The older Plugin Detective plugin is still active. Deactivate it so the two don\'t trip over each other. HackRepair Plugin Detective replaces it.', 'hackrepair-plugin-detective' ) );
		}
	}

	/**
	 * Tools page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You need to be able to manage plugins to use Plugin Detective.', 'hackrepair-plugin-detective' ) );
		}

		$case        = HRPD_Case::get();
		$last        = get_option( HRPD_Case::LAST_RESULT_OPTION, null );
		$maintenance = is_file( ABSPATH . '.maintenance' );
		$log         = HRPD_Log::all();
		$bookmark    = self::troubleshooter_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag after our own redirect.
		$done = isset( $_GET['hrpd_done'] ) ? sanitize_key( wp_unslash( $_GET['hrpd_done'] ) ) : '';
		?>
		<div class="wrap hrpd-wrap">
			<div class="hrpd-head">
				<img class="hrpd-mascot" src="<?php echo esc_url( HRPD_URL . 'assets/img/hackrepair-heart.png' ); ?>" alt="" width="56" height="56">
				<div>
					<h1><?php esc_html_e( 'Plugin Detective', 'hackrepair-plugin-detective' ); ?></h1>
					<p class="hrpd-byline"><?php esc_html_e( 'by Jim Walker, The Hack Repair Guy', 'hackrepair-plugin-detective' ); ?></p>
				</div>
			</div>

			<?php if ( 'restored' === $done ) : ?>
				<div class="hrpd-callout" role="status"><p><?php esc_html_e( 'Done. Every plugin is back the way it was before troubleshooting.', 'hackrepair-plugin-detective' ); ?></p></div>
			<?php elseif ( 'cleared' === $done ) : ?>
				<div class="hrpd-callout" role="status"><p><?php esc_html_e( 'Activity log cleared.', 'hackrepair-plugin-detective' ); ?></p></div>
			<?php endif; ?>

			<h2 class="hrpd-section"><?php esc_html_e( 'Find what broke your site', 'hackrepair-plugin-detective' ); ?></h2>
			<div class="hrpd-card">
				<p><?php esc_html_e( 'Something stopped working after an update or a new plugin? Plugin Detective switches plugins off in halves and asks you, each time, whether the problem is still there. A site with 40 plugins usually takes six or seven checks.', 'hackrepair-plugin-detective' ); ?></p>
				<p><?php esc_html_e( 'Tip: go to the page with the problem first, then click "Troubleshoot" in the admin bar. You\'ll start right on that page.', 'hackrepair-plugin-detective' ); ?></p>
				<p>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $bookmark ); ?>">
						<?php echo $case ? esc_html__( 'Continue troubleshooting', 'hackrepair-plugin-detective' ) : esc_html__( 'Open the troubleshooter', 'hackrepair-plugin-detective' ); ?>
					</a>
				</p>
				<?php if ( $case ) : ?>
					<div class="hrpd-callout hrpd-callout--warning">
						<p><strong><?php esc_html_e( 'A troubleshooting session is open.', 'hackrepair-plugin-detective' ); ?></strong>
						<?php
						printf(
							/* translators: %s: how long ago the session started. */
							esc_html__( 'Started %s ago. Some plugins may be switched off right now.', 'hackrepair-plugin-detective' ),
							esc_html( human_time_diff( (int) $case['opened_at'] ) )
						);
						?>
						</p>
						<div><?php echo self::restore_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?></div>
					</div>
				<?php elseif ( is_array( $last ) ) : ?>
					<p class="hrpd-muted">
						<?php
						printf(
							/* translators: 1: result summary, 2: time ago. */
							esc_html__( 'Last session: %1$s (%2$s ago).', 'hackrepair-plugin-detective' ),
							esc_html( self::describe_result( $last ) ),
							esc_html( human_time_diff( (int) $last['closed_at'] ) )
						);
						?>
					</p>
					<?php if ( ! empty( $last['report'] ) && is_string( $last['report'] ) ) : ?>
						<details class="hrpd-report-wrap">
							<summary><?php esc_html_e( 'Case report from that session', 'hackrepair-plugin-detective' ); ?></summary>
							<p><?php esc_html_e( 'A plain-English summary of what was found, with the site details a developer will ask for. Send it to the plugin\'s developer, your web person, or The Hack Repair Guy. It includes your site address and plugin list, so only send it to people you trust.', 'hackrepair-plugin-detective' ); ?></p>
							<textarea id="hrpd-report-text" class="large-text code hrpd-report" rows="16" readonly><?php echo esc_textarea( $last['report'] ); ?></textarea>
							<p>
								<button type="button" class="button" data-hrpd-copy="hrpd-report-text"><?php esc_html_e( 'Copy report', 'hackrepair-plugin-detective' ); ?></button>
								<a class="button" download="<?php echo esc_attr( 'plugin-detective-report-' . gmdate( 'Y-m-d', (int) $last['closed_at'] ) . '.txt' ); ?>" href="<?php echo esc_attr( 'data:text/plain;charset=utf-8,' . rawurlencode( $last['report'] ) ); ?>"><?php esc_html_e( 'Download (.txt)', 'hackrepair-plugin-detective' ); ?></a>
							</p>
						</details>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<h2 class="hrpd-section"><?php esc_html_e( 'Bookmark this for emergencies', 'hackrepair-plugin-detective' ); ?></h2>
			<div class="hrpd-card">
				<p><?php esc_html_e( 'If a plugin ever takes your whole site down (white screen, critical error, or wp-admin won\'t load), this address still works. Sign in there with your WordPress username and password.', 'hackrepair-plugin-detective' ); ?></p>
				<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $bookmark ); ?>" aria-label="<?php esc_attr_e( 'Emergency troubleshooter address', 'hackrepair-plugin-detective' ); ?>"></p>
				<p class="hrpd-muted"><?php esc_html_e( 'If your account uses two-factor login through HackGuard Security or the Two Factor plugin, you\'ll be asked for your code there too. Other two-factor plugins: log in to wp-admin first.', 'hackrepair-plugin-detective' ); ?></p>
			</div>

			<h2 class="hrpd-section"><?php esc_html_e( 'Maintenance mode', 'hackrepair-plugin-detective' ); ?></h2>
			<div class="hrpd-card">
				<?php if ( $maintenance ) : ?>
					<p><span class="hrpd-chip hrpd-chip--warn"><?php esc_html_e( 'Stuck', 'hackrepair-plugin-detective' ); ?></span> <?php esc_html_e( 'A .maintenance file is sitting in your site\'s main folder. If visitors see "Briefly unavailable for scheduled maintenance," open the troubleshooter to remove it.', 'hackrepair-plugin-detective' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( add_query_arg( 'restore', '1', $bookmark ) ); ?>"><?php esc_html_e( 'Fix maintenance mode', 'hackrepair-plugin-detective' ); ?></a></p>
				<?php else : ?>
					<p><span class="hrpd-chip hrpd-chip--ok"><?php esc_html_e( 'All clear', 'hackrepair-plugin-detective' ); ?></span> <?php esc_html_e( 'Your site isn\'t stuck in maintenance mode.', 'hackrepair-plugin-detective' ); ?></p>
				<?php endif; ?>
			</div>

			<h2 class="hrpd-section"><?php esc_html_e( 'Recent activity', 'hackrepair-plugin-detective' ); ?></h2>
			<div class="hrpd-card">
				<?php if ( empty( $log ) ) : ?>
					<p class="hrpd-muted"><?php esc_html_e( 'Nothing yet. Sessions, plugin changes and troubleshooter sign-ins will show up here.', 'hackrepair-plugin-detective' ); ?></p>
				<?php else : ?>
					<table class="widefat striped hrpd-log">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'When', 'hackrepair-plugin-detective' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What happened', 'hackrepair-plugin-detective' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Details', 'hackrepair-plugin-detective' ); ?></th>
								<th scope="col"><?php esc_html_e( 'IP address', 'hackrepair-plugin-detective' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $log, 0, 20 ) as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] ) ); ?></td>
									<td><?php echo esc_html( (string) $entry['message'] ); ?></td>
									<td><?php echo esc_html( self::describe_context( (array) ( $entry['context'] ?? array() ) ) ); ?></td>
									<td><code><?php echo esc_html( (string) $entry['ip'] ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hrpd-inline-form">
						<input type="hidden" name="action" value="hrpd_clear_log">
						<?php wp_nonce_field( 'hrpd_clear_log' ); ?>
						<button type="submit" class="button-link"><?php esc_html_e( 'Clear the activity log', 'hackrepair-plugin-detective' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="hrpd-help">
				<img class="hrpd-mascot" src="<?php echo esc_url( HRPD_URL . 'assets/img/hackrepair-heart.png' ); ?>" alt="" width="44" height="44">
				<p><strong><?php esc_html_e( 'Still stuck?', 'hackrepair-plugin-detective' ); ?></strong>
				<?php esc_html_e( 'Some problems aren\'t plugin problems. A hacked site can look a lot like a plugin conflict.', 'hackrepair-plugin-detective' ); ?>
				<a href="https://hackrepair.com/" target="_blank" rel="noopener"><?php esc_html_e( 'The Hack Repair Guy can help.', 'hackrepair-plugin-detective' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Put every plugin back (admin-post handler).
	 */
	public static function handle_restore(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You need to be able to manage plugins to do that.', 'hackrepair-plugin-detective' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpd_restore' );

		HRPD_Case::abandon();

		wp_safe_redirect( add_query_arg( 'hrpd_done', 'restored', admin_url( 'tools.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	/**
	 * Clear the activity log (admin-post handler).
	 */
	public static function handle_clear_log(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You need to be able to manage plugins to do that.', 'hackrepair-plugin-detective' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'hrpd_clear_log' );

		HRPD_Log::clear();

		wp_safe_redirect( add_query_arg( 'hrpd_done', 'cleared', admin_url( 'tools.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	/**
	 * "Put my plugins back" form button.
	 *
	 * @return string
	 */
	private static function restore_button(): string {
		return sprintf(
			'<form method="post" action="%1$s" class="hrpd-inline-form"><input type="hidden" name="action" value="hrpd_restore">%2$s<button type="submit" class="button">%3$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'hrpd_restore', '_wpnonce', true, false ),
			esc_html__( 'Put my plugins back', 'hackrepair-plugin-detective' )
		);
	}

	/**
	 * Plain-English summary of a finished session.
	 *
	 * @param array<string, mixed> $last Last result.
	 * @return string
	 */
	private static function describe_result( array $last ): string {
		switch ( $last['result'] ?? '' ) {
			case HRPD_Detective::RESULT_CULPRIT:
				$plugins = HRPD_Case::installed_plugins();
				$file    = (string) ( $last['culprit'] ?? '' );
				$name    = isset( $plugins[ $file ]['Name'] ) ? wp_strip_all_tags( (string) $plugins[ $file ]['Name'] ) : (string) ( $last['culprit_name'] ?? $file );
				return empty( $last['kept_off'] )
					/* translators: %s: plugin name. */
					? sprintf( __( 'found %s, then turned it back on', 'hackrepair-plugin-detective' ), $name )
					/* translators: %s: plugin name. */
					: sprintf( __( 'found %s and left it switched off', 'hackrepair-plugin-detective' ), $name );
			case HRPD_Detective::RESULT_NOT_PLUGIN:
				return __( 'not caused by a plugin', 'hackrepair-plugin-detective' );
			case HRPD_Detective::RESULT_COMBINATION:
				return __( 'more than one plugin involved', 'hackrepair-plugin-detective' );
			case HRPD_Detective::RESULT_MIXED:
				return __( 'the answers didn\'t add up', 'hackrepair-plugin-detective' );
			default:
				return __( 'stopped early, plugins put back', 'hackrepair-plugin-detective' );
		}
	}

	/**
	 * Log context as one line.
	 *
	 * @param array<string, string> $context Context.
	 * @return string
	 */
	private static function describe_context( array $context ): string {
		$parts = array();
		foreach ( $context as $key => $value ) {
			$parts[] = str_replace( '_', ' ', (string) $key ) . ': ' . $value;
		}
		return implode( '; ', $parts );
	}

	/**
	 * Print a standard notice.
	 *
	 * @param string $type    success|warning|error|info.
	 * @param string $message Message.
	 */
	private static function notice( string $type, string $message ): void {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	/**
	 * Cache-busting version from file time.
	 *
	 * @param string $relative Path inside the plugin.
	 * @return string
	 */
	private static function asset_version( string $relative ): string {
		$path = HRPD_DIR . $relative;
		return is_file( $path ) ? HRPD_VERSION . '.' . filemtime( $path ) : HRPD_VERSION;
	}
}
