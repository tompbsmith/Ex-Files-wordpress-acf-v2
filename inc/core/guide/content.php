<?php
/**
 * Guide content: what's saved in Ex Files → Divorce Guide Content, falling back to
 * the defaults in defaults.php for anything left empty. The guide works out of the
 * box before anyone has opened the options page.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paragraphs keyed by ID: [ 'P01' => [ 'heading' => …, 'body' => html ], … ].
 */
function exf_guide_paragraphs() {
	$saved = exf_get_option_field( 'guide_paragraphs', EXF_GUIDE_ID, array() );
	$out   = array();

	if ( $saved ) {
		foreach ( $saved as $row ) {
			$id = strtoupper( trim( (string) ( $row['para_id'] ?? '' ) ) );
			if ( '' !== $id ) {
				$out[ $id ] = array(
					'heading' => (string) ( $row['heading'] ?? '' ),
					'body'    => (string) ( $row['body'] ?? '' ),
				);
			}
		}
		return $out;
	}

	foreach ( exf_guide_default_paragraphs() as $id => $p ) {
		$out[ $id ] = array(
			'heading' => $p[0],
			'body'    => $p[1],
		);
	}
	return $out;
}

/**
 * Tag => paragraph IDs.
 *
 * @return array<string,string[]>
 */
function exf_guide_mapping() {
	$map = array();
	foreach ( exf_guide_default_mapping() as $tag => $row ) {
		$map[ $tag ] = $row[1];
	}

	$saved = exf_get_option_field( 'guide_mapping', EXF_GUIDE_ID, array() );
	foreach ( (array) $saved as $row ) {
		$tag = (string) ( $row['tag'] ?? '' );
		if ( isset( $map[ $tag ] ) ) {
			$map[ $tag ] = exf_guide_parse_ids( (string) ( $row['paragraphs'] ?? '' ) );
		}
	}
	return $map;
}

/**
 * "P01, p2 ,P10" => [ 'P01', 'P02', 'P10' ].
 *
 * @param string $list Comma or space separated IDs.
 * @return string[]
 */
function exf_guide_parse_ids( $list ) {
	$ids = array();
	foreach ( preg_split( '/[\s,;]+/', strtoupper( $list ) ) as $id ) {
		if ( preg_match( '/^P(\d{1,3})$/', $id, $m ) ) {
			$ids[] = 'P' . str_pad( $m[1], 2, '0', STR_PAD_LEFT );
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Tooltips: [ [ 'heading' => …, 'text' => … ], … ].
 */
function exf_guide_tooltips() {
	$saved = exf_get_option_field( 'guide_tooltips', EXF_GUIDE_ID, array() );
	if ( $saved ) {
		return array_map(
			function ( $row ) {
				return array(
					'heading' => (string) ( $row['heading'] ?? '' ),
					'text'    => (string) ( $row['text'] ?? '' ),
				);
			},
			$saved
		);
	}
	return array_map(
		function ( $t ) {
			return array(
				'heading' => $t[1],
				'text'    => $t[2],
			);
		},
		exf_guide_default_tooltips()
	);
}

/**
 * Jurisdiction routes: [ [ 'question' => …, 'explanation' => … ], … ].
 */
function exf_guide_routes() {
	$saved = exf_get_option_field( 'guide_routes', EXF_GUIDE_ID, array() );
	if ( $saved ) {
		return array_map(
			function ( $row ) {
				return array(
					'question'    => (string) ( $row['question'] ?? '' ),
					'explanation' => (string) ( $row['explanation'] ?? '' ),
				);
			},
			$saved
		);
	}
	return array_map(
		function ( $r ) {
			return array(
				'question'    => $r[0],
				'explanation' => $r[1],
			);
		},
		exf_guide_default_routes()
	);
}

/**
 * One piece of editable guide copy.
 *
 * @param string $key Key from exf_guide_default_copy().
 */
function exf_guide_copy( $key ) {
	$defaults = exf_guide_default_copy();
	return exf_get_option_field( $key, EXF_GUIDE_ID, $defaults[ $key ] ?? '' );
}

/**
 * Copy the defaults into the options page so the client can edit them.
 * Only fills fields that are empty; never overwrites edits.
 *
 * @return int Number of fields filled.
 */
function exf_guide_load_defaults() {
	if ( ! function_exists( 'update_field' ) ) {
		return 0;
	}
	$filled = 0;
	$empty  = function ( $name ) {
		$v = get_field( $name, EXF_GUIDE_ID );
		return null === $v || '' === $v || false === $v || array() === $v;
	};

	if ( $empty( 'guide_paragraphs' ) ) {
		$rows = array();
		foreach ( exf_guide_default_paragraphs() as $id => $p ) {
			$rows[] = array(
				'field_exf_gp_id'      => $id,
				'field_exf_gp_heading' => $p[0],
				'field_exf_gp_body'    => $p[1],
			);
		}
		update_field( 'field_exf_guide_paragraphs', $rows, EXF_GUIDE_ID );
		++$filled;
	}

	if ( $empty( 'guide_mapping' ) ) {
		$rows = array();
		foreach ( exf_guide_default_mapping() as $tag => $m ) {
			$rows[] = array(
				'field_exf_gm_tag'        => $tag,
				'field_exf_gm_paragraphs' => implode( ', ', $m[1] ),
			);
		}
		update_field( 'field_exf_guide_mapping', $rows, EXF_GUIDE_ID );
		++$filled;
	}

	if ( $empty( 'guide_tooltips' ) ) {
		$rows = array();
		foreach ( exf_guide_default_tooltips() as $t ) {
			$rows[] = array(
				'field_exf_gt_heading' => $t[1],
				'field_exf_gt_text'    => $t[2],
			);
		}
		update_field( 'field_exf_guide_tooltips', $rows, EXF_GUIDE_ID );
		++$filled;
	}

	if ( $empty( 'guide_routes' ) ) {
		$rows = array();
		foreach ( exf_guide_default_routes() as $r ) {
			$rows[] = array(
				'field_exf_gr_question'    => $r[0],
				'field_exf_gr_explanation' => $r[1],
			);
		}
		update_field( 'field_exf_guide_routes', $rows, EXF_GUIDE_ID );
		++$filled;
	}

	foreach ( exf_guide_default_copy() as $key => $value ) {
		if ( $empty( $key ) ) {
			update_field( 'field_exf_g_' . $key, $value, EXF_GUIDE_ID );
			++$filled;
		}
	}

	return $filled;
}

/*
 * One-off (1.7.32): the eligibility screen's wording changed at the client's request.
 * Replace the saved copy only if it's still the old default, so edits are kept.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_guide_q3a_2026_10' ) || ! function_exists( 'get_field' ) || ! current_user_can( EXF_CAP ) ) {
			return;
		}
		update_option( 'exf_guide_q3a_2026_10', time(), false );
		$old   = '<p>Based on your answer, we cannot confirm that you are eligible to apply for a divorce in England &amp; Wales.</p><p>If you are not eligible to divorce here, a lawyer qualified in the country you are connected with can advise you on divorcing there. Your guide will still explain this, and we may be able to help you understand your financial position.</p>';
		$saved = (string) get_field( 'q3a_text', EXF_GUIDE_ID, false );
		$norm  = function ( $html ) {
			return preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( html_entity_decode( $html ) ) ) );
		};
		if ( '' !== $saved && $norm( $saved ) === $norm( $old ) ) {
			update_field( 'field_exf_g_q3a_text', exf_guide_default_copy()['q3a_text'], EXF_GUIDE_ID );
		}
	}
);
