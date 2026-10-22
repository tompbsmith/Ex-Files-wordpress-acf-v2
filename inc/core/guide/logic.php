<?php
/**
 * Guide branching, tags and paragraph selection.
 *
 * The server re-walks the answers from Q1 on every submission, so it never trusts
 * which questions the browser says were shown. Answers to questions that aren't on
 * the visitor's path (e.g. left over after going back and changing an answer) are
 * dropped.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * The step after a question, given its answer.
 *
 * Returns a question key, an information screen ("q3a" or "q3n"), "final"
 * (email + services) or "exit" (the guide isn't for this visitor).
 *
 * Eligibility (Q3), as the client set out in October 2026:
 *   Yes       → carries on to Q4.
 *   No        → "q3n" screen, then the guide ends (P01–P03 + P19).
 *   Not sure  → the route questions r1, r2, … one at a time:
 *               Yes to any but the last → eligible, carries on to Q4;
 *               Yes to only the last    → "q3a" screen (can't confirm), then Q4;
 *               No to all               → treated as No ("q3n").
 * The JavaScript in assets/js/guide.js mirrors this.
 *
 * @param string $step   Current step.
 * @param string $answer Its answer.
 */
function exf_guide_next_step( $step, $answer ) {
	$routes = exf_guide_route_keys();
	$pos    = array_search( $step, $routes, true );
	if ( false !== $pos ) {
		$last = count( $routes ) - 1 === $pos;
		if ( 'yes' === $answer ) {
			return $last ? 'q3a' : 'q4';
		}
		return $last ? 'q3n' : $routes[ $pos + 1 ];
	}
	switch ( $step ) {
		case 'q1':
			return 'yes' === $answer ? 'q2' : 'exit';
		case 'q2':
			return 'yes' === $answer ? 'q3' : 'q2a';
		case 'q2a':
			return 'yes' === $answer ? 'final' : 'exit';
		case 'q3':
			if ( 'yes' === $answer ) {
				return 'q4';
			}
			return 'unsure' === $answer && $routes ? $routes[0] : 'q3n';
		case 'q3a':
			return 'q4';
		case 'q3n':
			return 'final';
		case 'q4':
			return 'yes' === $answer ? 'q5' : 'q5a';
		case 'q5':
		case 'q5a':
			return 'q6';
		case 'q6':
			return 'final';
	}
	return 'exit';
}

/**
 * Information screens: steps with no answer.
 */
function exf_guide_info_steps() {
	return array( 'q3a', 'q3n' );
}

/**
 * Walk the answers from Q1 and work out where the visitor ends up.
 *
 * @param array $answers Raw answers keyed by question.
 * @return array {
 *     @type string $outcome "final", "exit" or "incomplete".
 *     @type array  $answers Valid answers on the path, in order.
 *     @type string[] $path  Steps visited, including information screens.
 * }
 */
function exf_guide_walk( array $answers ) {
	$questions = exf_guide_questions();
	$step      = 'q1';
	$path      = array();
	$valid     = array();

	for ( $guard = 0; $guard < 20; $guard++ ) {
		if ( 'final' === $step || 'exit' === $step ) {
			return array(
				'outcome' => $step,
				'answers' => $valid,
				'path'    => $path,
			);
		}
		$path[] = $step;

		if ( in_array( $step, exf_guide_info_steps(), true ) ) {
			$step = exf_guide_next_step( $step, '' );
			continue;
		}

		$answer = isset( $answers[ $step ] ) ? (string) $answers[ $step ] : '';
		if ( ! isset( $questions[ $step ]['options'][ $answer ] ) ) {
			break;
		}
		$valid[ $step ] = $answer;
		$step           = exf_guide_next_step( $step, $answer );
	}

	return array(
		'outcome' => 'incomplete',
		'answers' => $valid,
		'path'    => $path,
	);
}

/**
 * Tags triggered by a completed set of answers (the sheet's "Guide content tag(s)" column).
 *
 * @param array $answers Valid answers from exf_guide_walk().
 * @return string[]
 */
function exf_guide_tags( array $answers ) {
	$tags = array();
	$a    = function ( $q ) use ( $answers ) {
		return isset( $answers[ $q ] ) ? $answers[ $q ] : '';
	};

	if ( 'no' === $a( 'q2' ) && 'yes' === $a( 'q2a' ) ) {
		$tags[] = 'UNMARRIED_FINANCES';
	}

	if ( 'yes' === $a( 'q2' ) && '' !== $a( 'q3' ) ) {
		// Everyone married gets P01–P02; anyone not confirmed as eligible also gets P03.
		$tags[] = 'DIVORCE_ELIGIBILITY';
		if ( ! exf_guide_eligible( $answers ) ) {
			$tags[] = 'NOT_EW';
		}
	}

	if ( 'no' === $a( 'q4' ) ) {
		$tags[] = 'DIVORCE_OPTIONS';
	}

	$stages = array(
		'sole_no_response'  => 'STAGE_ISSUED_NO_RESPONSE',
		'sole_20w'          => 'STAGE_SOLE_20W',
		'joint_20w'         => 'STAGE_JOINT_20W',
		'sole_conditional'  => 'STAGE_SOLE_CONDITIONAL',
		'joint_conditional' => 'STAGE_JOINT_CONDITIONAL',
		'final'             => 'STAGE_FINAL',
	);
	if ( isset( $stages[ $a( 'q5' ) ] ) ) {
		$tags[] = $stages[ $a( 'q5' ) ];
	}

	$finance = array(
		'no_assets' => 'FINANCE_NO_ASSETS',
		'guidance'  => 'FINANCE_GUIDANCE',
		'fairness'  => 'FINANCE_FAIRNESS',
		'binding'   => 'FINANCE_BINDING',
	);
	if ( isset( $finance[ $a( 'q6' ) ] ) ) {
		$tags[] = $finance[ $a( 'q6' ) ];
	}

	$tags[] = 'CTA';
	return $tags;
}

/**
 * Whether the answers confirm eligibility to divorce in England & Wales: Yes to Q3,
 * or Yes to any route question except the last.
 *
 * @param array $answers Valid answers.
 */
function exf_guide_eligible( array $answers ) {
	if ( 'yes' === ( $answers['q3'] ?? '' ) ) {
		return true;
	}
	$routes = exf_guide_route_keys();
	array_pop( $routes );
	foreach ( $routes as $r ) {
		if ( 'yes' === ( $answers[ $r ] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Paragraph IDs for a set of tags: de-duplicated, in P-number order, and only ones that exist.
 *
 * @param string[] $tags Tags.
 * @return string[]
 */
function exf_guide_paragraph_ids( array $tags ) {
	$map        = exf_guide_mapping();
	$paragraphs = exf_guide_paragraphs();
	$ids        = array();

	foreach ( $tags as $tag ) {
		foreach ( $map[ $tag ] ?? array() as $id ) {
			if ( isset( $paragraphs[ $id ] ) ) {
				$ids[ $id ] = (int) substr( $id, 1 );
			}
		}
	}
	asort( $ids );
	return array_keys( $ids );
}

/**
 * Annex A rows: every question the visitor saw, with the answer they gave.
 *
 * @param array $answers Valid answers from exf_guide_walk().
 * @return array[] [ [ 'question' => …, 'answer' => … ], … ]
 */
function exf_guide_annex_rows( array $answers ) {
	$questions = exf_guide_questions();
	$rows      = array();
	foreach ( $answers as $q => $value ) {
		if ( isset( $questions[ $q ]['options'][ $value ] ) ) {
			$rows[] = array(
				'question' => $questions[ $q ]['label'],
				'answer'   => $questions[ $q ]['options'][ $value ],
			);
		}
	}
	return $rows;
}

/**
 * A short label for the route taken, for the admin list.
 *
 * @param array $answers Valid answers.
 */
function exf_guide_route_label( array $answers ) {
	if ( ( $answers['q2'] ?? '' ) === 'no' ) {
		return __( 'Unmarried, financial guidance', 'exfiles' );
	}
	$questions = exf_guide_questions();
	$parts     = array();
	if ( isset( $answers['q3'] ) && ! exf_guide_eligible( $answers ) ) {
		if ( ! isset( $answers['q4'] ) ) {
			return __( 'Married, not eligible in England & Wales', 'exfiles' );
		}
		$parts[] = __( 'Eligibility not confirmed', 'exfiles' );
	} elseif ( 'unsure' === ( $answers['q3'] ?? '' ) ) {
		$parts[] = __( 'Eligible (after the eligibility questions)', 'exfiles' );
	}
	if ( isset( $answers['q5'] ) ) {
		$parts[] = $questions['q5']['options'][ $answers['q5'] ];
	} elseif ( isset( $answers['q5a'] ) ) {
		$parts[] = __( 'Not started divorce', 'exfiles' );
	}
	if ( isset( $answers['q6'] ) ) {
		$parts[] = $questions['q6']['options'][ $answers['q6'] ];
	}
	return implode( ' · ', $parts );
}
