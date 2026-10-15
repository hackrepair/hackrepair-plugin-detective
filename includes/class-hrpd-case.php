<?php
/**
 * Troubleshooting case: storage, plugin switching, and every way back out.
 *
 * Shared by the wp-admin screens and the standalone troubleshooter. Only
 * calls WordPress functions that are available in the troubleshooter's
 * lightweight (SHORTINIT) boot.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * One active case at a time, stored in a single non-autoloaded option.
 */
final class HRPD_Case {

	/**
	 * Option holding the open case.
	 */
	public const OPTION = 'hrpd_case';

	/**
	 * Option holding the last finished case summary.
	 */
	public const LAST_RESULT_OPTION = 'hrpd_last_result';

	public const STATUS_SETUP   = 'setup';
	public const STATUS_TESTING = 'testing';
	public const STATUS_SOLVED  = 'solved';

	/**
	 * Main plugin file of this plugin, relative to the plugins folder.
	 *
	 * @return string
	 */
	public static function self_basename(): string {
		return basename( dirname( __DIR__ ) ) . '/hackrepair-plugin-detective.php';
	}

	/**
	 * Installed plugins, keyed by plugin file.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		wp_cache_delete( 'plugins', 'plugins' );
		$plugins = get_plugins();
		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Plugins WordPress currently has switched on.
	 *
	 * @return string[]
	 */
	public static function active_plugins(): array {
		$active = get_option( 'active_plugins', array() );
		return is_array( $active ) ? array_values( array_filter( $active, 'is_string' ) ) : array();
	}

	/**
	 * The open case, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get(): ?array {
		$record = get_option( self::OPTION, null );
		return is_array( $record ) && ! empty( $record['id'] ) ? $record : null;
	}

	/**
	 * True when a case is open and plugins may be switched off.
	 *
	 * @return bool
	 */
	public static function is_open(): bool {
		return null !== self::get();
	}

	/**
	 * Open a new case. Nothing is switched off yet.
	 *
	 * @param string $url     Page where the problem shows.
	 * @param int    $user_id Who opened it.
	 * @return array<string, mixed>
	 */
	public static function open( string $url, int $user_id ): array {
		$existing = self::get();
		if ( $existing ) {
			self::restore_backup( $existing );
		}

		$installed = self::installed_plugins();
		$backup    = self::active_plugins();
		$self      = self::self_basename();

		$candidates = array_values(
			array_filter(
				$backup,
				static function ( string $file ) use ( $installed, $self ): bool {
					return $file !== $self && isset( $installed[ $file ] );
				}
			)
		);

		$record = array(
			'id'         => bin2hex( random_bytes( 8 ) ),
			'version'    => 1,
			'status'     => self::STATUS_SETUP,
			'opened_at'  => time(),
			'updated_at' => time(),
			'opened_by'  => $user_id,
			'url'        => $url,
			'backup'     => $backup,
			'candidates' => $candidates,
			'required'   => array(),
			'suspects'   => array(),
			'clues'      => array(),
			'result'     => null,
		);

		self::save( $record );
		HRPD_Log::add( 'case_opened', 'Troubleshooting case opened.', array( 'active_plugins' => count( $backup ) ) );

		return $record;
	}

	/**
	 * Start testing with the plugins the site cannot run without.
	 *
	 * @param string[] $required Plugin files that must stay on.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function start( array $required ) {
		$record = self::get();
		if ( ! $record ) {
			return new WP_Error( 'hrpd_no_case', __( 'There is no open case. Start a new one.', 'hackrepair-plugin-detective' ) );
		}

		$candidates         = (array) $record['candidates'];
		$required           = array_values( array_intersect( $candidates, array_map( 'strval', $required ) ) );
		$record['required'] = $required;
		$record['suspects'] = array_values( array_diff( $candidates, $required ) );
		$record['clues']    = array();
		$record['result']   = null;
		$record['status']   = self::STATUS_TESTING;

		return self::advance( $record );
	}

	/**
	 * Record "fixed" or "still broken" for the current round.
	 *
	 * @param string $outcome `fixed` or `broken`.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function answer( string $outcome ) {
		$record = self::get();
		if ( ! $record || self::STATUS_TESTING !== $record['status'] ) {
			return new WP_Error( 'hrpd_not_testing', __( 'There is no test running right now.', 'hackrepair-plugin-detective' ) );
		}
		if ( ! in_array( $outcome, array( HRPD_Detective::OUTCOME_FIXED, HRPD_Detective::OUTCOME_BROKEN ), true ) ) {
			return new WP_Error( 'hrpd_bad_answer', __( 'That answer was not recognized.', 'hackrepair-plugin-detective' ) );
		}

		$step = HRPD_Detective::next( (array) $record['suspects'], (array) $record['clues'] );
		if ( isset( $step['result'] ) ) {
			return self::advance( $record );
		}

		$record['clues'][] = array(
			'phase'   => $step['phase'],
			'tested'  => $step['test'],
			'outcome' => $outcome,
			'at'      => time(),
		);

		return self::advance( $record );
	}

	/**
	 * Take back the last answer and re-run that round.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function undo() {
		$record = self::get();
		if ( ! $record || empty( $record['clues'] ) ) {
			return new WP_Error( 'hrpd_nothing_to_undo', __( 'There is no answer to take back.', 'hackrepair-plugin-detective' ) );
		}

		array_pop( $record['clues'] );
		$record['status'] = self::STATUS_TESTING;
		$record['result'] = null;

		return self::advance( $record );
	}

	/**
	 * Finish the case.
	 *
	 * `keep`    - leave the site as it is now (culprit switched off).
	 * `restore` - put every plugin back exactly as it was before the case.
	 * `delete`  - remove the problem plugin's files, then put everything else back.
	 *
	 * @param string $mode `keep`, `restore` or `delete`.
	 * @return array<string, mixed>|WP_Error Summary of what was done, or why the plugin couldn't be deleted.
	 */
	public static function finish( string $mode ) {
		$record = self::get();
		if ( ! $record ) {
			return array( 'done' => true );
		}

		// Testing never started, so no plugin was switched. Close the case
		// without rewriting the plugin list or recording a result.
		if ( self::STATUS_SETUP === $record['status'] ) {
			delete_option( self::OPTION );
			HRPD_Log::add( 'case_closed', 'Case cancelled before testing. No plugins were changed.' );
			return array(
				'done'     => true,
				'kept_off' => false,
			);
		}

		$found_culprit = self::STATUS_SOLVED === $record['status']
			&& HRPD_Detective::RESULT_CULPRIT === ( $record['result']['result'] ?? '' );
		$delete        = 'delete' === $mode && $found_culprit;
		$keep_culprit_off = ( 'keep' === $mode && $found_culprit ) || $delete;

		if ( $delete ) {
			$removed = self::delete_plugin( (string) $record['result']['culprit'] );
			if ( is_wp_error( $removed ) ) {
				// Leave the case open and the plugin switched off so the owner can pick again.
				return $removed;
			}
		}

		if ( $delete ) {
			$culprit = (string) $record['result']['culprit'];
			self::write_active( array_values( array_diff( (array) $record['backup'], array( $culprit ) ) ) );
			HRPD_Log::add( 'plugin_deleted', 'Case closed. Problem plugin deleted.', array( 'plugin' => $culprit ) );
		} elseif ( $keep_culprit_off ) {
			$culprit = (string) $record['result']['culprit'];
			self::write_active( array_values( array_diff( (array) $record['backup'], array( $culprit ) ) ) );
			HRPD_Log::add( 'case_closed', 'Case closed. Problem plugin left switched off.', array( 'plugin' => $culprit ) );
		} else {
			self::restore_backup( $record );
			HRPD_Log::add( 'case_closed', 'Case closed. All plugins put back the way they were.' );
		}

		$culprit_file = (string) ( $record['result']['culprit'] ?? '' );
		$installed    = self::installed_plugins();
		$report       = '';
		if ( self::STATUS_SOLVED === $record['status'] && class_exists( 'HRPD_Report' ) ) {
			$report = HRPD_Report::build( $record, $installed, $delete ? HRPD_Report::OUTCOME_DELETED : ( $keep_culprit_off ? HRPD_Report::OUTCOME_KEPT : HRPD_Report::OUTCOME_RESTORED ) );
		}
		$culprit_name = isset( $installed[ $culprit_file ]['Name'] ) ? wp_strip_all_tags( (string) $installed[ $culprit_file ]['Name'] ) : $culprit_file;

		update_option(
			self::LAST_RESULT_OPTION,
			array(
				'closed_at'    => time(),
				'result'       => $record['result']['result'] ?? 'abandoned',
				'culprit'      => $culprit_file,
				'culprit_name' => $culprit_name,
				'kept_off'     => $keep_culprit_off && ! $delete,
				'deleted'      => $delete,
				'rounds'       => count( (array) $record['clues'] ),
				'report'       => $report,
			),
			false
		);

		delete_option( self::OPTION );

		return array(
			'done'     => true,
			'kept_off' => $keep_culprit_off && ! $delete,
			'deleted'  => $delete,
		);
	}

	/**
	 * Delete a plugin's files.
	 *
	 * Removes files only. The plugin's own uninstall routine is not run, so its
	 * settings and data stay in the database in case the owner reinstalls it.
	 * Never touches this plugin or anything outside the plugins folder.
	 *
	 * @param string $file Plugin file, e.g. `folder/plugin.php`.
	 * @return true|WP_Error
	 */
	private static function delete_plugin( string $file ) {
		$failed = new WP_Error( 'hrpd_delete_failed', __( 'I couldn\'t delete that plugin from here. It is still switched off. You can delete it from the Plugins page in WordPress.', 'hackrepair-plugin-detective' ) );

		$installed = self::installed_plugins();
		if ( '' === $file || $file === self::self_basename() || ! isset( $installed[ $file ] ) || false !== strpos( $file, '..' ) ) {
			return $failed;
		}

		$folder = dirname( $file );
		if ( '.' !== $folder && dirname( self::self_basename() ) === $folder ) {
			return $failed;
		}

		$root   = realpath( WP_PLUGIN_DIR );
		$target = '.' === $folder ? WP_PLUGIN_DIR . '/' . $file : WP_PLUGIN_DIR . '/' . $folder;
		if ( false === $root || ! file_exists( $target ) && ! is_link( $target ) ) {
			return $failed;
		}

		if ( ! is_link( $target ) ) {
			$real = realpath( $target );
			if ( false === $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return $failed;
			}
		}

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}
		$fs = new WP_Filesystem_Direct( null );

		if ( ! self::remove_path( $fs, $target ) ) {
			wp_cache_delete( 'plugins', 'plugins' );
			return $failed;
		}

		wp_cache_delete( 'plugins', 'plugins' );
		return true;
	}

	/**
	 * Remove a file or folder. Symbolic links are removed, never followed.
	 *
	 * @param WP_Filesystem_Direct $fs   Filesystem.
	 * @param string               $path Path.
	 * @return bool
	 */
	private static function remove_path( $fs, string $path ): bool {
		if ( is_link( $path ) || is_file( $path ) ) {
			return (bool) $fs->delete( $path, false, 'f' );
		}
		if ( ! is_dir( $path ) ) {
			return true;
		}
		$ok = true;
		foreach ( (array) scandir( $path ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$ok = self::remove_path( $fs, $path . '/' . $name ) && $ok;
		}
		return $ok && (bool) $fs->rmdir( $path );
	}

	/**
	 * Put the original plugin list back and close the case.
	 *
	 * @return bool True when a case was open.
	 */
	public static function abandon(): bool {
		if ( ! self::is_open() ) {
			return false;
		}
		self::finish( 'restore' );
		return true;
	}

	/**
	 * Work out the next round and switch plugins to match.
	 *
	 * @param array<string, mixed> $record Case data.
	 * @return array<string, mixed>
	 */
	private static function advance( array $record ): array {
		$step = HRPD_Detective::next( (array) $record['suspects'], (array) $record['clues'] );

		if ( isset( $step['result'] ) ) {
			$record['status'] = self::STATUS_SOLVED;
			$record['result'] = $step;

			if ( HRPD_Detective::RESULT_CULPRIT === $step['result'] ) {
				// Leave the site running without the problem plugin so the owner can check it.
				self::write_active( array_values( array_diff( (array) $record['backup'], array( $step['culprit'] ) ) ) );
				HRPD_Log::add( 'culprit_found', 'Problem plugin found and switched off.', array( 'plugin' => $step['culprit'] ) );
			} else {
				self::restore_backup( $record );
				HRPD_Log::add( 'case_result', 'Search finished without a single plugin to blame.', array( 'result' => $step['result'] ) );
			}
		} else {
			$keep_on = array_merge( (array) $record['required'], (array) $step['test'] );
			self::apply_test( $record, $keep_on );
		}

		$record['updated_at'] = time();
		self::save( $record );

		return $record;
	}

	/**
	 * Switch on only the given suspects plus required plugins and this plugin.
	 *
	 * Plugins that were off before the case stay off.
	 *
	 * @param array<string, mixed> $record    Case data.
	 * @param string[]             $keep_on Plugin files to leave on.
	 */
	private static function apply_test( array $record, array $keep_on ): void {
		$self   = self::self_basename();
		$active = array_values(
			array_filter(
				(array) $record['backup'],
				static function ( string $file ) use ( $keep_on, $self ): bool {
					return $file === $self || in_array( $file, $keep_on, true );
				}
			)
		);
		self::write_active( $active );
	}

	/**
	 * Put back the plugin list saved when the case opened.
	 *
	 * @param array<string, mixed> $record Case data.
	 */
	private static function restore_backup( array $record ): void {
		self::write_active( (array) $record['backup'] );
	}

	/**
	 * Write the active plugin list, keeping only installed plugins.
	 *
	 * @param string[] $files Plugin files.
	 */
	private static function write_active( array $files ): void {
		$installed = self::installed_plugins();
		$self      = self::self_basename();
		$clean     = array();

		foreach ( $files as $file ) {
			$file = (string) $file;
			if ( ( isset( $installed[ $file ] ) || $file === $self ) && ! in_array( $file, $clean, true ) ) {
				$clean[] = $file;
			}
		}

		update_option( 'active_plugins', $clean );
	}

	/**
	 * Save the case.
	 *
	 * @param array<string, mixed> $record Case data.
	 */
	private static function save( array $record ): void {
		update_option( self::OPTION, $record, false );
	}
}
