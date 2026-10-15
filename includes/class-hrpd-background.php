<?php
/**
 * Background check on the plugin a case points to.
 *
 * Asks two public sources about one plugin, by its folder name:
 * - wordpress.org: newest version, when it was last updated, and whether
 *   the plugin has been closed.
 * - WPVulnerability (wpvulnerability.com): known security problems, gathered
 *   from CVE, Wordfence, Patchstack, WPScan and others. Free, no API key,
 *   data released under CC0.
 *
 * Only the plugin's folder name is sent. Answers are cached for 12 hours.
 * Turn the whole check off with `define( 'HRPD_NO_BACKGROUND_CHECK', true );`
 * in wp-config.php.
 *
 * Shared by wp-admin and the standalone Fix page, so it only uses
 * functions available in both. The Fix page loads the HTTP API on
 * demand before calling run().
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin background check.
 */
final class HRPD_Background {

	/**
	 * How long a good answer is kept, in seconds (12 hours).
	 */
	private const CACHE_TTL = 43200;

	/**
	 * How long to wait before asking again after a failed lookup (10 minutes).
	 */
	private const RETRY_TTL = 600;

	/**
	 * Seconds to wait for each outside service.
	 */
	private const TIMEOUT = 6;

	/**
	 * Most problems listed by name.
	 */
	private const MAX_ITEMS = 5;

	/**
	 * Whether the check is allowed on this site.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return ! ( defined( 'HRPD_NO_BACKGROUND_CHECK' ) && HRPD_NO_BACKGROUND_CHECK );
	}

	/**
	 * The wordpress.org-style slug for a plugin file, or '' when there isn't one.
	 *
	 * @param string $file Plugin file, relative to the plugins folder.
	 * @return string
	 */
	public static function slug( string $file ): string {
		$slug = false !== strpos( $file, '/' ) ? dirname( $file ) : basename( $file, '.php' );
		$slug = strtolower( $slug );
		return preg_match( '/^[a-z0-9][a-z0-9._-]{0,99}$/', $slug ) ? $slug : '';
	}

	/**
	 * Cached answer for a plugin, without going online.
	 *
	 * @param string               $file Plugin file.
	 * @param array<string, mixed> $data Plugin header data from get_plugins().
	 * @return array<string, mixed>|null
	 */
	public static function cached( string $file, array $data ): ?array {
		$key = self::cache_key( $file, $data );
		if ( '' === $key ) {
			return null;
		}
		$found = get_transient( $key );
		return is_array( $found ) ? $found : null;
	}

	/**
	 * Run the background check (or return the cached answer).
	 *
	 * @param string               $file Plugin file.
	 * @param array<string, mixed> $data Plugin header data from get_plugins().
	 * @return array<string, mixed>
	 */
	public static function run( string $file, array $data ): array {
		$slug    = self::slug( $file );
		$version = (string) ( $data['Version'] ?? '' );

		if ( ! self::enabled() || '' === $slug ) {
			return array(
				'status' => 'off',
				'slug'   => $slug,
			);
		}

		$cached = self::cached( $file, $data );
		if ( null !== $cached ) {
			return $cached;
		}

		$result = array(
			'status'    => 'ok',
			'slug'      => $slug,
			'name'      => wp_strip_all_tags( (string) ( $data['Name'] ?? $slug ) ),
			'installed' => $version,
			'checkedAt' => time(),
			'wporg'     => self::wporg( $slug, (string) ( $data['UpdateURI'] ?? '' ) ),
			'vulns'     => self::vulnerabilities( $slug ),
		);

		$result = self::finish( $result );

		$failed = 'error' === $result['wporg']['state'] || 'error' === $result['vulns']['state'];
		set_transient( self::cache_key( $file, $data ), $result, $failed ? self::RETRY_TTL : self::CACHE_TTL );

		return $result;
	}

	/**
	 * Plain-English lines for a background check, shared by the screen and the report.
	 *
	 * Each line has a `tone` (ok, info, warn, bad) and `text`. Known security
	 * problems are listed under `items`.
	 *
	 * @param array<string, mixed> $bg Result from run().
	 * @return array<string, mixed>
	 */
	public static function summarize( array $bg ): array {
		$lines     = array();
		$installed = (string) ( $bg['installed'] ?? '' );
		$wporg     = (array) ( $bg['wporg'] ?? array() );
		$vulns     = (array) ( $bg['vulns'] ?? array() );
		$latest    = (string) ( $wporg['latest'] ?? '' );

		switch ( $wporg['state'] ?? '' ) {
			case 'listed':
				$updated = self::nice_date( (string) ( $wporg['updated'] ?? '' ) );
				if ( '' !== $latest && '' !== $installed && version_compare( $installed, $latest, '<' ) ) {
					$lines[] = self::line(
						'warn',
						/* translators: 1: installed version, 2: newest version, 3: date it was last updated. */
						sprintf( __( 'You have version %1$s. The newest is %2$s (last updated %3$s). An update may fix your problem.', 'hackrepair-plugin-detective' ), $installed, $latest, $updated )
					);
				} elseif ( '' !== $latest ) {
					$lines[] = self::line(
						'ok',
						/* translators: 1: installed version, 2: date it was last updated. */
						sprintf( __( 'You have the newest version (%1$s), last updated %2$s.', 'hackrepair-plugin-detective' ), '' !== $installed ? $installed : $latest, $updated )
					);
				}
				if ( ! empty( $wporg['stale'] ) ) {
					$lines[] = self::line( 'warn', __( 'It hasn\'t been updated in over two years. It may be abandoned, so keep an eye out for a replacement.', 'hackrepair-plugin-detective' ) );
				}
				break;

			case 'closed':
				$reason  = (string) ( $wporg['closedReason'] ?? '' );
				$lines[] = self::line(
					'bad',
					'' !== $reason
						/* translators: 1: date the plugin was closed, 2: reason given by wordpress.org. */
						? sprintf( __( 'wordpress.org closed this plugin on %1$s (reason: %2$s). It won\'t get any more updates, so plan to replace it.', 'hackrepair-plugin-detective' ), self::nice_date( (string) ( $wporg['closedDate'] ?? '' ) ), $reason )
						/* translators: %s: date the plugin was closed. */
						: sprintf( __( 'wordpress.org closed this plugin on %s. It won\'t get any more updates, so plan to replace it.', 'hackrepair-plugin-detective' ), self::nice_date( (string) ( $wporg['closedDate'] ?? '' ) ) )
				);
				break;

			case 'missing':
				$lines[] = self::line( 'info', __( 'It isn\'t listed on wordpress.org, so it\'s probably a premium or custom plugin. Ask whoever sold or built it for an update.', 'hackrepair-plugin-detective' ) );
				break;

			case 'noupdates':
				$lines[] = self::line( 'info', __( 'Its author has switched off update checks for this plugin, so WordPress won\'t tell you when a new version is out. Ask whoever supplied it whether there is one.', 'hackrepair-plugin-detective' ) );
				break;

			case 'skipped':
				$lines[] = self::line(
					'info',
					/* translators: %s: the website the plugin gets its updates from. */
					sprintf( __( 'Its updates come from %s, not wordpress.org. Check there for a newer version.', 'hackrepair-plugin-detective' ), (string) ( $wporg['host'] ?? '' ) )
				);
				break;

			case 'error':
				$lines[] = self::line( 'info', __( 'I couldn\'t reach wordpress.org from this server, so I couldn\'t check for a newer version.', 'hackrepair-plugin-detective' ) );
				break;
		}

		$items = array();
		if ( 'ok' === ( $vulns['state'] ?? '' ) ) {
			$affecting = (array) ( $vulns['affecting'] ?? array() );
			$count     = count( $affecting );
			$version   = '' !== $installed ? $installed : __( 'the one you have', 'hackrepair-plugin-detective' );

			if ( 0 === $count ) {
				$lines[] = self::line(
					'ok',
					/* translators: %s: installed version. */
					sprintf( __( 'No known security holes in version %s.', 'hackrepair-plugin-detective' ), $version )
				);
			} else {
				$lines[] = self::line(
					'bad',
					sprintf(
						/* translators: 1: number of security problems, 2: installed version, 3: most serious level (low, medium, high, critical). */
						_n( '%1$s known security hole affects version %2$s (most serious: %3$s).', '%1$s known security holes affect version %2$s (most serious: %3$s).', $count, 'hackrepair-plugin-detective' ),
						number_format_i18n( $count ),
						$version,
						self::severity_label( (string) ( $vulns['worst'] ?? '' ) )
					)
				);

				$unfixed = (int) ( $vulns['unfixed'] ?? 0 );
				if ( $unfixed > 0 ) {
					$lines[] = self::line( 'bad', __( 'At least one has no fix yet. Switch this plugin off and look for a replacement.', 'hackrepair-plugin-detective' ) );
				} elseif ( '' !== $latest && ! self::any_affects( $latest, (array) ( $vulns['all'] ?? array() ) ) && 'listed' === ( $wporg['state'] ?? '' ) ) {
					$lines[] = self::line(
						'warn',
						/* translators: %s: newest version. */
						sprintf( __( 'Updating to the newest version (%s) fixes them. Do that before turning it back on.', 'hackrepair-plugin-detective' ), $latest )
					);
				} elseif ( '' !== (string) ( $vulns['fixedIn'] ?? '' ) ) {
					$lines[] = self::line(
						'warn',
						/* translators: %s: version that fixes the problems. */
						sprintf( __( 'Version %s or newer fixes them. Update before turning it back on.', 'hackrepair-plugin-detective' ), (string) $vulns['fixedIn'] )
					);
				}

				foreach ( array_slice( $affecting, 0, self::MAX_ITEMS ) as $item ) {
					$items[] = array(
						'title'    => (string) $item['title'],
						'severity' => self::severity_label( (string) $item['severity'] ),
						'link'     => (string) $item['link'],
					);
				}
				if ( $count > self::MAX_ITEMS ) {
					$items[] = array(
						/* translators: %s: number of further problems not listed. */
						'title'    => sprintf( __( '…and %s more.', 'hackrepair-plugin-detective' ), number_format_i18n( $count - self::MAX_ITEMS ) ),
						'severity' => '',
						'link'     => '',
					);
				}
			}
		} elseif ( 'error' === ( $vulns['state'] ?? '' ) ) {
			$lines[] = self::line( 'info', __( 'I couldn\'t reach the vulnerability database from this server, so I couldn\'t check for known security holes.', 'hackrepair-plugin-detective' ) );
		}

		$tone = 'ok';
		foreach ( $lines as $line ) {
			if ( 'bad' === $line['tone'] ) {
				$tone = 'bad';
				break;
			}
			if ( 'warn' === $line['tone'] ) {
				$tone = 'warn';
			}
		}

		return array(
			'tone'   => $tone,
			'lines'  => $lines,
			'items'  => $items,
			'credit' => __( 'Vulnerability data from WPVulnerability (wpvulnerability.com). Version details from wordpress.org.', 'hackrepair-plugin-detective' ),
		);
	}

	/**
	 * Ask wordpress.org about a plugin.
	 *
	 * @param string $slug       Plugin slug.
	 * @param string $update_uri The plugin's Update URI header, if any.
	 * @return array<string, mixed>
	 */
	private static function wporg( string $slug, string $update_uri ): array {
		if ( '' !== $update_uri ) {
			$host = strtolower( (string) wp_parse_url( $update_uri, PHP_URL_HOST ) );
			if ( '' === $host ) {
				$host = strtolower( trim( $update_uri ) );
			}
			// "Update URI: false" is the author's way of saying nobody should push updates to this plugin.
			if ( in_array( $host, array( 'false', '0', 'no', 'off' ), true ) ) {
				return array( 'state' => 'noupdates' );
			}
			if ( ! in_array( $host, array( 'w.org', 'wordpress.org', 'downloads.wordpress.org' ), true ) ) {
				return array(
					'state' => 'skipped',
					'host'  => sanitize_text_field( $host ),
				);
			}
		}

		$fields = array( 'sections', 'description', 'screenshots', 'banners', 'icons', 'contributors', 'reviews', 'ratings', 'versions', 'tags', 'donate_link', 'compatibility' );
		$url    = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=' . rawurlencode( $slug );
		foreach ( $fields as $field ) {
			$url .= '&request%5Bfields%5D%5B' . $field . '%5D=0';
		}

		$body = self::get_json( $url );
		if ( null === $body ) {
			return array( 'state' => 'error' );
		}

		if ( isset( $body['error'] ) ) {
			if ( 'closed' === $body['error'] ) {
				return array(
					'state'        => 'closed',
					'closedDate'   => sanitize_text_field( (string) ( $body['closed_date'] ?? '' ) ),
					'closedReason' => sanitize_text_field( (string) ( $body['reason_text'] ?? '' ) ),
				);
			}
			return array( 'state' => 'missing' );
		}

		if ( empty( $body['version'] ) ) {
			return array( 'state' => 'error' );
		}

		$updated = sanitize_text_field( (string) ( $body['last_updated'] ?? '' ) );
		$time    = '' !== $updated ? strtotime( str_replace( 'GMT', 'UTC', $updated ) ) : false;

		return array(
			'state'   => 'listed',
			'latest'  => sanitize_text_field( (string) $body['version'] ),
			'updated' => $time ? gmdate( 'Y-m-d', $time ) : '',
			'stale'   => $time && ( time() - $time ) > 2 * 365 * DAY_IN_SECONDS,
			'link'    => 'https://wordpress.org/plugins/' . $slug . '/',
		);
	}

	/**
	 * Ask WPVulnerability about a plugin.
	 *
	 * Keeps every listed problem (so the newest version can be checked
	 * against them) and works out which ones affect the installed version.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string, mixed>
	 */
	private static function vulnerabilities( string $slug ): array {
		$body = self::get_json( 'https://www.wpvulnerability.net/plugin/' . rawurlencode( $slug ) . '/' );
		if ( null === $body || ! empty( $body['error'] ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return array( 'state' => 'error' );
		}

		$all = array();
		foreach ( (array) ( $body['data']['vulnerability'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$op    = (array) ( $entry['operator'] ?? array() );
			$all[] = array(
				'title'      => self::title( $entry ),
				'severity'   => self::severity( (array) ( $entry['impact'] ?? array() ) ),
				'link'       => self::best_link( (array) ( $entry['source'] ?? array() ) ),
				'minVersion' => sanitize_text_field( (string) ( $op['min_version'] ?? '' ) ),
				'minOp'      => sanitize_key( (string) ( $op['min_operator'] ?? '' ) ),
				'maxVersion' => sanitize_text_field( (string) ( $op['max_version'] ?? '' ) ),
				'maxOp'      => sanitize_key( (string) ( $op['max_operator'] ?? '' ) ),
				'unfixed'    => '1' === (string) ( $op['unfixed'] ?? '0' ),
			);
		}

		return array(
			'state' => 'ok',
			'all'   => $all,
		);
	}

	/**
	 * Work out which known problems affect a version.
	 *
	 * Fills `affecting`, `worst`, `unfixed` and `fixedIn` on a result from run().
	 *
	 * @param array<string, mixed> $result Background check result.
	 * @return array<string, mixed>
	 */
	private static function match_installed( array $result ): array {
		$version = (string) ( $result['installed'] ?? '' );
		$order   = array(
			''         => 0,
			'low'      => 1,
			'medium'   => 2,
			'high'     => 3,
			'critical' => 4,
		);

		$affecting = array();
		$worst     = '';
		$unfixed   = 0;
		$fixed_in  = '';

		foreach ( (array) ( $result['vulns']['all'] ?? array() ) as $item ) {
			if ( '' === $version || ! self::affects( $version, $item ) ) {
				continue;
			}
			$affecting[] = $item;
			if ( ( $order[ $item['severity'] ] ?? 0 ) > ( $order[ $worst ] ?? 0 ) ) {
				$worst = $item['severity'];
			}
			if ( $item['unfixed'] ) {
				++$unfixed;
			} elseif ( 'lt' === $item['maxOp'] && '' !== $item['maxVersion'] && ( '' === $fixed_in || version_compare( $item['maxVersion'], $fixed_in, '>' ) ) ) {
				$fixed_in = $item['maxVersion'];
			}
		}

		usort(
			$affecting,
			static function ( array $a, array $b ) use ( $order ): int {
				return ( $order[ $b['severity'] ] ?? 0 ) <=> ( $order[ $a['severity'] ] ?? 0 );
			}
		);

		$result['vulns']['affecting'] = $affecting;
		$result['vulns']['worst']     = $worst;
		$result['vulns']['unfixed']   = $unfixed;
		$result['vulns']['fixedIn']   = $fixed_in;

		return $result;
	}

	/**
	 * Final pass that turns raw answers into the stored result.
	 *
	 * Kept separate so tests can feed it canned answers.
	 *
	 * @param array<string, mixed> $result Background check result with raw `vulns.all`.
	 * @return array<string, mixed>
	 */
	public static function finish( array $result ): array {
		if ( 'ok' === ( $result['vulns']['state'] ?? '' ) ) {
			$result = self::match_installed( $result );
		}
		$result['summary'] = self::summarize( $result );
		return $result;
	}

	/**
	 * True when a known problem applies to a version.
	 *
	 * @param string               $version Version to test.
	 * @param array<string, mixed> $item    Known problem.
	 * @return bool
	 */
	public static function affects( string $version, array $item ): bool {
		$ops = array(
			'lt' => '<',
			'le' => '<=',
			'gt' => '>',
			'ge' => '>=',
			'eq' => '==',
		);

		$min_version = (string) ( $item['minVersion'] ?? '' );
		$min_op      = (string) ( $item['minOp'] ?? '' );
		$max_version = (string) ( $item['maxVersion'] ?? '' );
		$max_op      = (string) ( $item['maxOp'] ?? '' );

		if ( '' !== $min_version && isset( $ops[ $min_op ] ) && ! version_compare( $version, $min_version, $ops[ $min_op ] ) ) {
			return false;
		}

		if ( '' !== $max_version && isset( $ops[ $max_op ] ) ) {
			return version_compare( $version, $max_version, $ops[ $max_op ] );
		}

		// No upper limit: only counts when the problem is marked as not fixed yet.
		return ! empty( $item['unfixed'] );
	}

	/**
	 * True when any listed problem applies to a version.
	 *
	 * @param string                           $version Version to test.
	 * @param array<int, array<string, mixed>> $items   Known problems.
	 * @return bool
	 */
	private static function any_affects( string $version, array $items ): bool {
		foreach ( $items as $item ) {
			if ( self::affects( $version, $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetch a URL and decode its JSON, or null on any failure.
	 *
	 * @param string $url Address.
	 * @return array<string, mixed>|null
	 */
	private static function get_json( string $url ): ?array {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 2,
				'user-agent'  => 'HackRepairPluginDetective/' . self::plugin_version(),
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		// wordpress.org answers "not found" and "closed" with a 404 and a JSON body.
		if ( ! is_array( $body ) || ( 200 !== $code && 404 !== $code ) ) {
			return null;
		}

		return $body;
	}

	/**
	 * A readable title for a known problem, preferring the clearest source.
	 *
	 * @param array<string, mixed> $entry WPVulnerability entry.
	 * @return string
	 */
	private static function title( array $entry ): string {
		$sources = (array) ( $entry['source'] ?? array() );

		// Wordfence first: its titles read "Plugin <= 1.2.3 - What the problem is".
		usort(
			$sources,
			static function ( $a, $b ): int {
				$wa = false !== strpos( (string) ( $a['link'] ?? '' ), 'wordfence.com' );
				$wb = false !== strpos( (string) ( $b['link'] ?? '' ), 'wordfence.com' );
				return (int) $wb <=> (int) $wa;
			}
		);

		foreach ( $sources as $source ) {
			$name = self::clean( (string) ( $source['name'] ?? '' ) );
			if ( '' === $name || 0 === strpos( $name, 'CVE-' ) ) {
				continue;
			}
			// "Plugin <= 1.2.3 - Unauthenticated Stored Cross-Site Scripting".
			if ( preg_match( '/(?:<=|<|>=|>)\s*[0-9][0-9a-z.\-]*\s+-\s+(.+)$/iu', $name, $m ) ) {
				return self::shorten( $m[1] );
			}
			// "Plugin <= 1.2.3 is vulnerable to Cross Site Scripting (XSS)".
			if ( preg_match( '/is vulnerable to\s+(.+)$/iu', $name, $m ) ) {
				return self::shorten( ucfirst( $m[1] ) );
			}
		}

		// The CVE text, which usually starts with the kind of problem.
		foreach ( $sources as $source ) {
			$text = self::clean( preg_replace( '/^\[[a-z]{2}\]\s*/', '', (string) ( $source['description'] ?? '' ) ) );
			if ( '' !== $text && 0 === strpos( (string) ( $source['name'] ?? '' ), 'CVE-' ) ) {
				$text = preg_replace( '/\s+(?:vulnerability\s+)?in\s+.+$/iu', '', strtok( $text, '.' ) );
				return self::shorten( (string) $text );
			}
		}

		$cwe = (array) ( $entry['impact']['cwe'] ?? array() );
		if ( ! empty( $cwe[0]['name'] ) ) {
			return self::shorten( self::clean( (string) $cwe[0]['name'] ) );
		}

		return self::shorten( self::clean( (string) ( $entry['name'] ?? '' ) ) );
	}

	/**
	 * Trim a title to a readable length.
	 *
	 * @param string $text Title.
	 * @return string
	 */
	private static function shorten( string $text ): string {
		$text = trim( $text );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 140 ) {
			return rtrim( mb_substr( $text, 0, 137 ) ) . '…';
		}
		return $text;
	}

	/**
	 * The most useful link for a known problem.
	 *
	 * @param array<int, array<string, mixed>> $sources Sources.
	 * @return string
	 */
	private static function best_link( array $sources ): string {
		$rank = array( 'wordfence.com', 'patchstack.com', 'wpscan.com', 'cve.org' );
		foreach ( $rank as $host ) {
			foreach ( $sources as $source ) {
				$link = (string) ( $source['link'] ?? '' );
				if ( false !== strpos( $link, $host ) && 0 === strpos( $link, 'https://' ) ) {
					return esc_url_raw( $link );
				}
			}
		}
		return '';
	}

	/**
	 * Severity word from the impact block: low, medium, high, critical, or ''.
	 *
	 * @param array<string, mixed> $impact Impact data.
	 * @return string
	 */
	private static function severity( array $impact ): string {
		$letters = array(
			'l' => 'low',
			'm' => 'medium',
			'h' => 'high',
			'c' => 'critical',
		);
		$value   = strtolower( (string) ( $impact['cvss3']['severity'] ?? ( $impact['cvss']['severity'] ?? '' ) ) );
		if ( isset( $letters[ $value ] ) ) {
			return $letters[ $value ];
		}
		return in_array( $value, $letters, true ) ? $value : '';
	}

	/**
	 * Translated severity word.
	 *
	 * @param string $severity low, medium, high, critical or ''.
	 * @return string
	 */
	private static function severity_label( string $severity ): string {
		switch ( $severity ) {
			case 'critical':
				return __( 'critical', 'hackrepair-plugin-detective' );
			case 'high':
				return __( 'high', 'hackrepair-plugin-detective' );
			case 'medium':
				return __( 'medium', 'hackrepair-plugin-detective' );
			case 'low':
				return __( 'low', 'hackrepair-plugin-detective' );
		}
		return __( 'not rated', 'hackrepair-plugin-detective' );
	}

	/**
	 * Plain text from a feed field.
	 *
	 * @param string $text Text that may hold HTML entities.
	 * @return string
	 */
	private static function clean( string $text ): string {
		// Remove real tags only. Feed titles use "<=" and "<" for version ranges,
		// which a general tag stripper would treat as the start of a tag.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = (string) preg_replace( '/<\/?[a-z][^<>]*>/i', '', $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * One display line.
	 *
	 * @param string $tone ok, info, warn or bad.
	 * @param string $text Text.
	 * @return array<string, string>
	 */
	private static function line( string $tone, string $text ): array {
		return array(
			'tone' => $tone,
			'text' => $text,
		);
	}

	/**
	 * Date in the site's format, from Y-m-d.
	 *
	 * @param string $ymd Date.
	 * @return string
	 */
	private static function nice_date( string $ymd ): string {
		$time = '' !== $ymd ? strtotime( $ymd . ' 12:00:00 UTC' ) : false;
		if ( ! $time ) {
			return __( 'on an unknown date', 'hackrepair-plugin-detective' );
		}
		return wp_date( (string) get_option( 'date_format', 'F j, Y' ), $time, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Cache key for a plugin and version.
	 *
	 * @param string               $file Plugin file.
	 * @param array<string, mixed> $data Plugin header data.
	 * @return string
	 */
	private static function cache_key( string $file, array $data ): string {
		$slug = self::slug( $file );
		return '' === $slug ? '' : 'hrpd_bg_' . md5( $slug . '|' . (string) ( $data['Version'] ?? '' ) . '|' . self::plugin_version() );
	}

	/**
	 * This plugin's version, in wp-admin or the Fix page.
	 *
	 * @return string
	 */
	public static function plugin_version(): string {
		if ( defined( 'HRPD_VERSION' ) ) {
			return HRPD_VERSION;
		}
		$main = dirname( __DIR__ ) . '/hackrepair-plugin-detective.php';
		$data = is_readable( $main ) ? get_file_data( $main, array( 'Version' => 'Version' ) ) : array();
		return (string) ( $data['Version'] ?? '' );
	}
}
