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
 * Returns a question key, "q3a" (information screen), "final" (email + services)
 * or "exit" (the guide isn't for this visitor).
 *
 * Open question 1 in the build plan: after Q3A the guide finishes (P03 + P19) rather
 * than going on to the finances question. Change the "q3a" line to return 'q6' if
 * the client decides otherwise. The JavaScript in assets/js/guide.js mirrors this.
 *
 * @param string $step   Current step.
 * @param string $answer Its answer.
 */
function exf_guide_next_step( $step, $answer ) {
	switch ( $step ) {
		case 'q1':
			return 'yes' === $answer ? 'q2' : 'exit';
		case 'q2':
			return 'yes' === $answer ? 'q3' : 'q2a';
		case 'q2a':
			return 'yes' === $answer ? 'final' : 'exit';
		case 'q3':
			return 'yes' === $answer ? 'q4' : 'q3a';
		case 'q3a':
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
 * Walk the answers from Q1 and work out where the visitor ends up.
 *
 * @param array $answers Raw answers keyed by question.
 * @return array {
 *     @type string $outcome "final", "exit" or "incomplete".
 *     @type array  $answers Valid answers on the path, in order.
 *     @type string[] $path  Steps visited, including "q3a".
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

		if ( 'q3a' === $step ) {
			$step = exf_guide_next_step( 'q3a', '' );
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

	if ( 'yes' === $a( 'q2' ) ) {
		if ( 'yes' === $a( 'q3' ) ) {
			$tags[] = 'DIVORCE_ELIGIBILITY';
		} elseif ( in_array( $a( 'q3' ), array( 'no', 'unsure' ), true ) ) {
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
	if ( in_array( $answers['q3'] ?? '', array( 'no', 'unsure' ), true ) ) {
		return __( 'Married, eligibility not confirmed', 'exfiles' );
	}
	$questions = exf_guide_questions();
	$parts     = array();
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
