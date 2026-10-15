<?php
/**
 * Plain-text case report: what happened, what was found, and the site details
 * a developer or support person will ask for.
 *
 * Built in one place so the Fix page and the Tools page show the same
 * text. Only uses functions available in the Fix page's lightweight
 * boot.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case report.
 */
final class HRPD_Report {

	public const OUTCOME_PENDING  = 'pending';
	public const OUTCOME_KEPT     = 'kept';
	public const OUTCOME_RESTORED = 'restored';
	public const OUTCOME_DELETED  = 'deleted';

	/**
	 * Build the report for a solved case.
	 *
	 * @param array<string, mixed>                $record    Case data.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @param string                              $outcome   pending, kept, restored or deleted.
	 * @return string
	 */
	public static function build( array $record, array $installed, string $outcome ): string {
		$result  = (array) ( $record['result'] ?? array() );
		$type    = (string) ( $result['result'] ?? '' );
		$culprit = (string) ( $result['culprit'] ?? '' );
		$rule    = str_repeat( '=', 44 );
		$out     = array();

		$out[] = __( 'Plugin Detective case report', 'hackrepair-plugin-detective' );
		$out[] = $rule;
		/* translators: 1: site name, 2: site address. */
		$out[] = sprintf( __( 'Site: %1$s (%2$s)', 'hackrepair-plugin-detective' ), self::site_name(), home_url( '/' ) );
		/* translators: date and time. */
		$out[] = sprintf( __( 'Date: %s', 'hackrepair-plugin-detective' ), wp_date( get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ) ) );
		/* translators: page address. */
		$out[] = sprintf( __( 'Problem page: %s', 'hackrepair-plugin-detective' ), (string) ( $record['url'] ?? '' ) );
		$out[] = '';

		switch ( $type ) {
			case HRPD_Detective::RESULT_CULPRIT:
				/* translators: plugin name and version. */
				$out[] = sprintf( __( 'Result: Found the problem plugin: %s.', 'hackrepair-plugin-detective' ), self::label( $culprit, $installed ) );
				$out[] = __( 'The problem went away when this plugin was switched off, and came back with it on.', 'hackrepair-plugin-detective' );
				/* translators: plugin file path. */
				$out[] = sprintf( __( 'Plugin file: %s', 'hackrepair-plugin-detective' ), $culprit );
				break;

			case HRPD_Detective::RESULT_NOT_PLUGIN:
				$out[] = __( 'Result: Not a plugin. The problem was still there with the plugins switched off, so it comes from somewhere else (theme, must-use plugins, caching, the server, or something that shouldn\'t be on the site).', 'hackrepair-plugin-detective' );
				break;

			case HRPD_Detective::RESULT_COMBINATION:
				$out[] = __( 'Result: More than one plugin is involved. No single plugin breaks the site on its own.', 'hackrepair-plugin-detective' );
				$broken = (array) ( $result['last_broken'] ?? array() );
				if ( $broken ) {
					$out[] = __( 'The problem showed up with this group switched on:', 'hackrepair-plugin-detective' );
					foreach ( $broken as $file ) {
						$out[] = '  - ' . self::label( (string) $file, $installed );
					}
				}
				break;

			default:
				$out[] = __( 'Result: The answers didn\'t add up. Every group tested came back fixed, so the problem may come and go, or a cache may be showing an older copy of the page.', 'hackrepair-plugin-detective' );
		}

		/* translators: number of checks. */
		$out[] = sprintf( __( 'Checks it took: %s', 'hackrepair-plugin-detective' ), number_format_i18n( count( (array) ( $record['clues'] ?? array() ) ) ) );
		$out[] = self::outcome_line( $type, $culprit, $installed, $outcome );
		$out[] = '';

		if ( HRPD_Detective::RESULT_CULPRIT === $type && isset( $installed[ $culprit ] ) ) {
			$bg = HRPD_Background::cached( $culprit, $installed[ $culprit ] );
			if ( $bg && 'off' !== ( $bg['status'] ?? '' ) && ! empty( $bg['summary']['lines'] ) ) {
				$out[] = sprintf(
					/* translators: 1: plugin name, 2: date checked. */
					__( 'Background check on %1$s (checked %2$s):', 'hackrepair-plugin-detective' ),
					self::name( $culprit, $installed ),
					wp_date( get_option( 'date_format', 'F j, Y' ), (int) ( $bg['checkedAt'] ?? time() ) )
				);
				foreach ( (array) $bg['summary']['lines'] as $line ) {
					$out[] = '- ' . (string) $line['text'];
				}
				foreach ( (array) ( $bg['summary']['items'] ?? array() ) as $item ) {
					$text = '    * ' . (string) $item['title'];
					if ( '' !== (string) $item['severity'] ) {
						$text .= ' [' . (string) $item['severity'] . ']';
					}
					if ( '' !== (string) $item['link'] ) {
						$text .= ' ' . (string) $item['link'];
					}
					$out[] = $text;
				}
				$out[] = '';
			}
		}

		$required = (array) ( $record['required'] ?? array() );
		$out[]    = __( 'Plugins that stayed on the whole time:', 'hackrepair-plugin-detective' ) . ' ' . ( $required ? self::labels( $required, $installed ) : __( 'none', 'hackrepair-plugin-detective' ) );

		$others = array_values( array_diff( (array) ( $record['candidates'] ?? array() ), $required, array( $culprit ) ) );
		/* translators: number of plugins. */
		$out[] = sprintf( __( 'Other active plugins (%s):', 'hackrepair-plugin-detective' ), number_format_i18n( count( $others ) ) ) . ' ' . ( $others ? self::labels( $others, $installed ) : __( 'none', 'hackrepair-plugin-detective' ) );
		$out[] = '';

		$out[] = __( 'Site details:', 'hackrepair-plugin-detective' );
		/* translators: WordPress version. */
		$out[] = '- ' . sprintf( __( 'WordPress %s', 'hackrepair-plugin-detective' ), (string) ( $GLOBALS['wp_version'] ?? '' ) );
		/* translators: PHP version. */
		$out[] = '- ' . sprintf( __( 'PHP %s', 'hackrepair-plugin-detective' ), PHP_VERSION );
		$theme = wp_get_theme();
		if ( $theme->exists() ) {
			/* translators: 1: theme name, 2: theme version. */
			$out[] = '- ' . sprintf( __( 'Theme: %1$s %2$s', 'hackrepair-plugin-detective' ), wp_strip_all_tags( (string) $theme->get( 'Name' ) ), wp_strip_all_tags( (string) $theme->get( 'Version' ) ) );
		}
		if ( ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$mu    = array_values( wp_list_pluck( get_mu_plugins(), 'Name' ) );
		$out[] = '- ' . __( 'Must-use plugins:', 'hackrepair-plugin-detective' ) . ' ' . ( $mu ? implode( ', ', array_map( 'wp_strip_all_tags', $mu ) ) : __( 'none', 'hackrepair-plugin-detective' ) );
		$drop  = array_keys( get_dropins() );
		$out[] = '- ' . __( 'Drop-ins:', 'hackrepair-plugin-detective' ) . ' ' . ( $drop ? implode( ', ', $drop ) : __( 'none', 'hackrepair-plugin-detective' ) );
		$out[] = '';

		$out[] = $rule;
		/* translators: plugin version. */
		$out[] = sprintf( __( 'Made with The Hack Repair Guy\'s Plugin Detective %s.', 'hackrepair-plugin-detective' ), HRPD_Background::plugin_version() );
		$out[] = __( 'Need a hand? https://hackrepair.com/', 'hackrepair-plugin-detective' );

		return implode( "\n", $out );
	}

	/**
	 * What state the site was left in.
	 *
	 * @param string                              $type      Result type.
	 * @param string                              $culprit   Problem plugin file.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @param string                              $outcome   pending, kept, restored or deleted.
	 * @return string
	 */
	private static function outcome_line( string $type, string $culprit, array $installed, string $outcome ): string {
		$name = self::name( $culprit, $installed );

		if ( HRPD_Detective::RESULT_CULPRIT === $type && self::OUTCOME_KEPT === $outcome ) {
			/* translators: plugin name. */
			return sprintf( __( 'Left as: %s is switched off. Every other plugin is back on.', 'hackrepair-plugin-detective' ), $name );
		}
		if ( HRPD_Detective::RESULT_CULPRIT === $type && self::OUTCOME_DELETED === $outcome ) {
			/* translators: plugin name. */
			return sprintf( __( 'Left as: %s was deleted. Every other plugin is back on.', 'hackrepair-plugin-detective' ), $name );
		}
		if ( HRPD_Detective::RESULT_CULPRIT === $type && self::OUTCOME_PENDING === $outcome ) {
			/* translators: plugin name. */
			return sprintf( __( 'Left as: not decided yet. %s is switched off for now; every other plugin is back on.', 'hackrepair-plugin-detective' ), $name );
		}
		return __( 'Left as: every plugin was put back the way it was before you started.', 'hackrepair-plugin-detective' );
	}

	/**
	 * Comma-separated names and versions.
	 *
	 * @param string[]                            $files     Plugin files.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @return string
	 */
	private static function labels( array $files, array $installed ): string {
		$labels = array();
		foreach ( $files as $file ) {
			$labels[] = self::label( (string) $file, $installed );
		}
		natcasesort( $labels );
		return implode( ', ', $labels );
	}

	/**
	 * "Name version" for one plugin.
	 *
	 * @param string                              $file      Plugin file.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @return string
	 */
	private static function label( string $file, array $installed ): string {
		$version = isset( $installed[ $file ]['Version'] ) ? wp_strip_all_tags( (string) $installed[ $file ]['Version'] ) : '';
		return trim( self::name( $file, $installed ) . ' ' . $version );
	}

	/**
	 * Plugin name, or its file when it's no longer installed.
	 *
	 * @param string                              $file      Plugin file.
	 * @param array<string, array<string, mixed>> $installed Installed plugins.
	 * @return string
	 */
	private static function name( string $file, array $installed ): string {
		return isset( $installed[ $file ]['Name'] ) ? wp_strip_all_tags( (string) $installed[ $file ]['Name'] ) : $file;
	}

	/**
	 * Site name as plain text.
	 *
	 * @return string
	 */
	private static function site_name(): string {
		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}
}
