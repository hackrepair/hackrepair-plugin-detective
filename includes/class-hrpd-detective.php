<?php
/**
 * The search logic: works out which plugins to test next, or the answer.
 *
 * Pure logic with no WordPress calls, so it can be reasoned about and tested
 * on its own. The state is rebuilt from the list of answers every time, which
 * makes "undo my last answer" exact and keeps the stored case small.
 *
 * How the search works:
 * 1. Baseline: switch off every suspect (only "must stay on" plugins run).
 *    Still broken  -> it is not one of the suspects.
 *    Fixed         -> the problem is in the suspects; start splitting.
 * 2. Split: test the first half of the remaining suspects.
 *    Still broken  -> the problem is in that half.
 *    Fixed         -> the problem is in the other half.
 * 3. Confirm: when one plugin is left and it has not been seen breaking the
 *    site on its own, test it alone before naming it.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin conflict search.
 */
final class HRPD_Detective {

	public const PHASE_BASELINE = 'baseline';
	public const PHASE_SPLIT    = 'split';
	public const PHASE_CONFIRM  = 'confirm';

	public const OUTCOME_FIXED  = 'fixed';
	public const OUTCOME_BROKEN = 'broken';

	public const RESULT_CULPRIT     = 'culprit';
	public const RESULT_NOT_PLUGIN  = 'not_plugin';
	public const RESULT_COMBINATION = 'combination';
	public const RESULT_MIXED       = 'mixed_answers';

	/**
	 * Work out the next step from the suspects and the answers so far.
	 *
	 * Returns either:
	 * - `array( 'phase' => string, 'test' => string[], 'remaining' => string[], 'round' => int )`, or
	 * - `array( 'result' => string, 'culprit' => string, 'last_broken' => string[], 'round' => int )`.
	 *
	 * @param string[]                         $suspects Plugin files under suspicion, in a stable order.
	 * @param array<int, array<string, mixed>> $clues    Answers so far: `phase`, `tested`, `outcome`.
	 * @return array<string, mixed>
	 */
	public static function next( array $suspects, array $clues ): array {
		$remaining   = array_values( $suspects );
		$last_broken = $remaining;
		$round       = count( $clues ) + 1;

		foreach ( $clues as $clue ) {
			$phase   = (string) ( $clue['phase'] ?? '' );
			$tested  = array_values( (array) ( $clue['tested'] ?? array() ) );
			$outcome = (string) ( $clue['outcome'] ?? '' );
			$broken  = self::OUTCOME_BROKEN === $outcome;

			if ( self::PHASE_BASELINE === $phase ) {
				if ( $broken ) {
					return self::result( self::RESULT_NOT_PLUGIN, '', array(), $round );
				}
				continue;
			}

			if ( self::PHASE_CONFIRM === $phase ) {
				if ( $broken ) {
					return self::result( self::RESULT_CULPRIT, (string) ( $tested[0] ?? '' ), $tested, $round );
				}
				return self::result( self::RESULT_COMBINATION, '', $last_broken, $round );
			}

			// Split.
			if ( $broken ) {
				$remaining   = array_values( array_intersect( $remaining, $tested ) );
				$last_broken = $tested;
			} else {
				$remaining = array_values( array_diff( $remaining, $tested ) );
			}
		}

		if ( empty( $clues ) ) {
			return self::step( self::PHASE_BASELINE, array(), $remaining, $round );
		}

		if ( empty( $remaining ) ) {
			return self::result( self::RESULT_MIXED, '', $last_broken, $round );
		}

		if ( 1 === count( $remaining ) ) {
			// Already seen breaking the site on its own? Then we have our answer.
			if ( array( $remaining[0] ) === $last_broken ) {
				return self::result( self::RESULT_CULPRIT, $remaining[0], $last_broken, $round );
			}
			return self::step( self::PHASE_CONFIRM, $remaining, $remaining, $round );
		}

		$half = array_slice( $remaining, 0, intdiv( count( $remaining ), 2 ) );
		return self::step( self::PHASE_SPLIT, $half, $remaining, $round );
	}

	/**
	 * Rough number of rounds left, for the progress display.
	 *
	 * @param int $remaining Suspects still in play.
	 * @return int
	 */
	public static function rounds_left( int $remaining ): int {
		if ( $remaining <= 1 ) {
			return 1;
		}
		return (int) ceil( log( $remaining, 2 ) ) + 1;
	}

	/**
	 * Build a "test this next" step.
	 *
	 * @param string   $phase     Phase name.
	 * @param string[] $test      Suspects switched on for this round.
	 * @param string[] $remaining Suspects still in play.
	 * @param int      $round     Round number.
	 * @return array<string, mixed>
	 */
	private static function step( string $phase, array $test, array $remaining, int $round ): array {
		return array(
			'phase'     => $phase,
			'test'      => array_values( $test ),
			'remaining' => array_values( $remaining ),
			'round'     => $round,
		);
	}

	/**
	 * Build a finished result.
	 *
	 * @param string   $result      Result code.
	 * @param string   $culprit     Plugin file when one was found.
	 * @param string[] $last_broken Smallest set seen breaking the site.
	 * @param int      $round       Rounds taken.
	 * @return array<string, mixed>
	 */
	private static function result( string $result, string $culprit, array $last_broken, int $round ): array {
		return array(
			'result'      => $result,
			'culprit'     => $culprit,
			'last_broken' => array_values( $last_broken ),
			'round'       => $round,
		);
	}
}
