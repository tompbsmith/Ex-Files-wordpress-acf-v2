<?php
/**
 * Questionnaire answers: cleaning, validation and the read-only summary.
 *
 * Answers are stored as one array in the post meta "q_data": section key => values.
 * Only visible answers are kept: a field hidden by an earlier answer (or a section
 * switched to No) is dropped when the step is saved, not just hidden.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_Q_CERTAINTY = array( 'Known current', 'Estimated current', "Don't know" );

/**
 * A questionnaire's answers.
 *
 * @param int $q Questionnaire.
 */
function exf_q_data( $q ) {
	$data = get_post_meta( $q, 'q_data', true );
	return is_array( $data ) ? exf_q_normalise( $data ) : array();
}

/**
 * Bring stored answers into the current shape: answers saved before a question
 * was added or changed are cleaned against today's schema, so every repeater is a
 * list and every money answer an array. Cleaning is idempotent for current data.
 *
 * @param array $data Stored answers.
 */
function exf_q_normalise( array $data ) {
	$sections = exf_q_sections();
	$out      = array();
	foreach ( $data as $key => $values ) {
		if ( isset( $sections[ $key ] ) && is_array( $values ) ) {
			$out[ $key ] = exf_q_clean_section( $key, $values, $out + $data );
		}
	}
	return $out;
}

/**
 * Whether a field is shown, given the values beside it and all answers.
 *
 * @param array $field  Field definition.
 * @param array $scope  Values in the same scope.
 * @param array $all    All answers.
 */
function exf_q_field_visible( array $field, array $scope, array $all ) {
	if ( ! empty( $field['show_if'] ) ) {
		list( $key, $values ) = $field['show_if'];
		if ( ! in_array( (string) ( $scope[ $key ] ?? '' ), $values, true ) ) {
			return false;
		}
	}
	if ( ! empty( $field['show_data'] ) ) {
		list( $section, $key, $values ) = $field['show_data'];
		if ( ! in_array( (string) ( $all[ $section ][ $key ] ?? '' ), $values, true ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Tidy an amount typed by a client ("£1,250.50" → "1250.50"). Anything that
 * still isn't a number is kept as typed, so the error can be shown.
 *
 * @param mixed $raw Raw input.
 */
function exf_q_clean_amount( $raw ) {
	$raw = trim( sanitize_text_field( (string) $raw ) );
	$num = str_replace( array( '£', ',', ' ' ), '', $raw );
	return is_numeric( $num ) ? (string) ( 0 + $num ) : $raw;
}

/**
 * Clean a date from a date input (Y-m-d), or '' if it isn't one.
 *
 * @param mixed $raw Raw input.
 */
function exf_q_clean_date( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	$norm = exf_norm_dob( $raw );
	return $norm ? $norm : $raw;
}

/**
 * Clean one money answer.
 *
 * @param array $field Field definition.
 * @param mixed $in    Raw input.
 */
function exf_q_clean_money( array $field, $in ) {
	$in        = is_array( $in ) ? $in : array();
	$certainty = sanitize_text_field( (string) ( $in['certainty'] ?? '' ) );
	$certainty = in_array( $certainty, EXF_Q_CERTAINTY, true ) ? $certainty : '';
	$out       = array( 'certainty' => $certainty );
	if ( in_array( $certainty, array( 'Known current', 'Estimated current' ), true ) || ( '' === $certainty && ! empty( $in['amount'] ) ) ) {
		$out['amount'] = exf_q_clean_amount( $in['amount'] ?? '' );
		$out['as_at']  = exf_q_clean_date( $in['as_at'] ?? '' );
		if ( 'Estimated current' === $certainty ) {
			$out['basis'] = sanitize_text_field( (string) ( $in['basis'] ?? '' ) );
		}
		if ( ! empty( $field['freq'] ) ) {
			$freqs            = exf_q_freq_options( $field );
			$f                = sanitize_text_field( (string) ( $in['frequency'] ?? '' ) );
			$out['frequency'] = in_array( $f, $freqs, true ) ? $f : '';
		}
	}
	return $out;
}

/**
 * Frequency choices for a money field.
 *
 * @param array $field Field definition.
 */
function exf_q_freq_options( array $field ) {
	if ( is_array( $field['freq'] ?? null ) ) {
		return $field['freq'];
	}
	return array( 'Weekly', 'Fortnightly', 'Four-weekly', 'Monthly', 'Quarterly', 'Annual', 'Other' );
}

/**
 * Clean the answers for a list of fields, dropping anything hidden.
 *
 * @param array $fields Field definitions.
 * @param mixed $in     Raw input.
 * @param array $all    All answers (for show_data).
 * @return array
 */
function exf_q_clean_fields( array $fields, $in, array $all ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	foreach ( $fields as $key => $field ) {
		if ( ! exf_q_field_visible( $field, $out, $all ) ) {
			continue;
		}
		$raw = $in[ $key ] ?? null;
		switch ( $field['type'] ) {
			case 'textarea':
				$out[ $key ] = sanitize_textarea_field( (string) $raw );
				break;
			case 'date':
				$out[ $key ] = exf_q_clean_date( $raw );
				break;
			case 'select':
			case 'radio':
				$v           = sanitize_text_field( (string) $raw );
				$out[ $key ] = in_array( $v, $field['options'], true ) ? $v : '';
				break;
			case 'number':
				$out[ $key ] = exf_q_clean_amount( $raw );
				break;
			case 'money':
				$out[ $key ] = exf_q_clean_money( $field, $raw );
				break;
			case 'group':
				$out[ $key ] = exf_q_clean_fields( $field['fields'], $raw, $all );
				break;
			case 'repeater':
				$items = array();
				foreach ( (array) $raw as $item ) {
					$clean = exf_q_clean_fields( $field['fields'], $item, $all );
					if ( exf_q_has_answer( $clean ) ) {
						$items[] = $clean;
					}
				}
				$out[ $key ] = $items;
				break;
			case 'outgoings':
				$rows = array();
				foreach ( exf_q_outgoing_categories() as $i => $cat ) {
					$r          = is_array( $raw[ $i ] ?? null ) ? $raw[ $i ] : array();
					$certainty  = sanitize_text_field( (string) ( $r['certainty'] ?? '' ) );
					$freq       = sanitize_text_field( (string) ( $r['frequency'] ?? 'Monthly' ) );
					$rows[ $i ] = array(
						'current'   => exf_q_clean_amount( $r['current'] ?? '' ),
						'future'    => exf_q_clean_amount( $r['future'] ?? '' ),
						'frequency' => isset( exf_q_frequencies()[ $freq ] ) ? $freq : 'Monthly',
						'certainty' => in_array( $certainty, array( 'Known', 'Estimated', "Don't know" ), true ) ? $certainty : 'Known',
					);
				}
				$out[ $key ] = $rows;
				break;
			default:
				$out[ $key ] = sanitize_text_field( (string) $raw );
		}
	}
	return $out;
}

/**
 * Whether anything was entered in a cleaned array.
 *
 * @param mixed $values Values.
 */
function exf_q_has_answer( $values ) {
	if ( is_array( $values ) ) {
		foreach ( $values as $k => $v ) {
			if ( 'frequency' === $k ) {
				continue;
			}
			if ( exf_q_has_answer( $v ) ) {
				return true;
			}
		}
		return false;
	}
	return '' !== trim( (string) $values );
}

/**
 * Clean one section's posted answers.
 *
 * @param string $key Section key.
 * @param mixed  $in  Raw input.
 * @param array  $all All answers.
 */
function exf_q_clean_section( $key, $in, array $all ) {
	$section = exf_q_sections()[ $key ];
	$in      = is_array( $in ) ? $in : array();
	$out     = array();
	if ( ! empty( $section['gate'] ) ) {
		$gate         = sanitize_text_field( (string) ( $in['_gate'] ?? '' ) );
		$out['_gate'] = in_array( $gate, EXF_Q_YND, true ) ? $gate : '';
		if ( "Don't know" === $out['_gate'] ) {
			$out['_gate_note'] = sanitize_textarea_field( (string) ( $in['_gate_note'] ?? '' ) );
		}
		if ( 'Yes' !== $out['_gate'] ) {
			return $out;
		}
	}
	return $out + exf_q_clean_fields( $section['fields'], $in, $all );
}

/* -------------------------------------------------------------------------
 * Validation. Error keys are the input names, e.g. q[properties][0][value].
 * ---------------------------------------------------------------------- */

/**
 * Check a section's answers.
 *
 * @param string $key    Section key.
 * @param array  $values Cleaned answers.
 * @param array  $all    All answers.
 * @return array name => message
 */
function exf_q_validate_section( $key, array $values, array $all ) {
	$section = exf_q_sections()[ $key ];
	$errors  = array();
	if ( ! empty( $section['gate'] ) ) {
		if ( '' === ( $values['_gate'] ?? '' ) ) {
			$errors['q[_gate]'] = __( 'Please choose an answer.', 'exfiles' );
		}
		if ( 'Yes' !== ( $values['_gate'] ?? '' ) ) {
			return $errors;
		}
	}
	return $errors + exf_q_validate_fields( $section['fields'], $values, 'q', $all );
}

/**
 * Check answers for a list of fields.
 *
 * @param array  $fields Field definitions.
 * @param array  $values Cleaned values.
 * @param string $prefix Input name prefix.
 * @param array  $all    All answers.
 */
function exf_q_validate_fields( array $fields, array $values, $prefix, array $all ) {
	$errors = array();
	foreach ( $fields as $key => $field ) {
		if ( ! exf_q_field_visible( $field, $values, $all ) ) {
			continue;
		}
		$name = $prefix . '[' . $key . ']';
		$v    = $values[ $key ] ?? '';
		switch ( $field['type'] ) {
			case 'money':
				$errors += exf_q_validate_money( $field, is_array( $v ) ? $v : array(), $name );
				break;
			case 'group':
				$errors += exf_q_validate_fields( $field['fields'], is_array( $v ) ? $v : array(), $name, $all );
				break;
			case 'repeater':
				foreach ( ( is_array( $v ) ? $v : array() ) as $i => $item ) {
					if ( is_array( $item ) ) {
						$errors += exf_q_validate_fields( $field['fields'], $item, $name . '[' . $i . ']', $all );
					}
				}
				break;
			case 'outgoings':
				foreach ( array_filter( is_array( $v ) ? $v : array(), 'is_array' ) as $i => $row ) {
					foreach ( array( 'current', 'future' ) as $col ) {
						$row[ $col ] = $row[ $col ] ?? '';
						if ( '' !== $row[ $col ] && ( ! is_numeric( $row[ $col ] ) || $row[ $col ] < 0 ) ) {
							$errors[ $name . '[' . $i . '][' . $col . ']' ] = __( 'Please enter an amount in pounds, like 250 or 99.50.', 'exfiles' );
						}
					}
				}
				break;
			case 'number':
				if ( '' !== $v && ( ! is_numeric( $v ) || $v < 0 || ( false !== strpos( $field['attrs'] ?? '', 'max="100"' ) && $v > 100 ) ) ) {
					$errors[ $name ] = __( 'Please enter a number.', 'exfiles' );
				}
				break;
			case 'date':
				if ( '' !== $v && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
					$errors[ $name ] = __( 'Please enter a valid date.', 'exfiles' );
				}
				break;
			default:
				if ( ! empty( $field['required'] ) && '' === (string) $v ) {
					$errors[ $name ] = __( 'Please choose an answer.', 'exfiles' );
				}
		}
	}
	return $errors;
}

/**
 * Check a money answer.
 *
 * @param array  $field Field definition.
 * @param array  $v     Cleaned value.
 * @param string $name  Input name.
 */
function exf_q_validate_money( array $field, array $v, $name ) {
	$errors    = array();
	$certainty = $v['certainty'] ?? '';
	if ( '' === $certainty ) {
		if ( ! empty( $v['amount'] ) ) {
			$errors[ $name . '[certainty]' ] = __( "Please say whether this is known, estimated or you don't know.", 'exfiles' );
		} elseif ( empty( $field['optional'] ) ) {
			$errors[ $name . '[certainty]' ] = __( "Please choose Known current, Estimated current or Don't know.", 'exfiles' );
		}
		return $errors;
	}
	if ( "Don't know" === $certainty ) {
		return $errors;
	}
	$amount = $v['amount'] ?? '';
	if ( '' === $amount ) {
		$errors[ $name . '[amount]' ] = __( 'Please enter the amount.', 'exfiles' );
	} elseif ( ! is_numeric( $amount ) || $amount < 0 ) {
		$errors[ $name . '[amount]' ] = __( 'Please enter an amount in pounds, like 2500 or 99.50.', 'exfiles' );
	}
	if ( '' === ( $v['as_at'] ?? '' ) ) {
		$errors[ $name . '[as_at]' ] = __( 'Please give the date of this figure.', 'exfiles' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v['as_at'] ) ) {
		$errors[ $name . '[as_at]' ] = __( 'Please enter a valid date.', 'exfiles' );
	}
	if ( 'Estimated current' === $certainty && '' === trim( (string) ( $v['basis'] ?? '' ) ) ) {
		$errors[ $name . '[basis]' ] = __( 'Please tell us how you estimated it.', 'exfiles' );
	}
	if ( ! empty( $field['freq'] ) && '' === ( $v['frequency'] ?? '' ) ) {
		$errors[ $name . '[frequency]' ] = __( 'Please say how often it is paid.', 'exfiles' );
	}
	return $errors;
}

/**
 * Errors across every section that applies.
 *
 * @param array $data All answers.
 * @return array section key => errors
 */
function exf_q_validate_all( array $data ) {
	$out = array();
	foreach ( exf_q_visible_sections( $data ) as $key ) {
		$values = $data[ $key ] ?? null;
		if ( null === $values ) {
			$out[ $key ] = array( '_section' => __( 'Not started', 'exfiles' ) );
			continue;
		}
		$errors = exf_q_validate_section( $key, $values, $data );
		if ( $errors ) {
			$out[ $key ] = $errors;
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Summary rows, for the review page, the admin and the PDF.
 * ---------------------------------------------------------------------- */

/**
 * Format pounds.
 *
 * @param float|string $amount Amount.
 */
function exf_q_pounds( $amount ) {
	if ( ! is_numeric( $amount ) ) {
		return (string) $amount;
	}
	$amount = (float) $amount;
	return '£' . number_format( $amount, floor( $amount ) === $amount ? 0 : 2 );
}

/**
 * Format a stored date.
 *
 * @param string $ymd Y-m-d.
 */
function exf_q_date( $ymd ) {
	$ts = $ymd ? strtotime( $ymd . ' 12:00' ) : false;
	return $ts ? wp_date( 'j M Y', $ts ) : (string) $ymd;
}

/**
 * Money answer as text, with its flag.
 *
 * @param array $field Field definition.
 * @param array $v     Value.
 * @return array [ text, flag ]
 */
function exf_q_money_text( array $field, array $v ) {
	$c = $v['certainty'] ?? '';
	if ( "Don't know" === $c ) {
		return array( __( "Don't know", 'exfiles' ), 'dont_know' );
	}
	if ( '' === $c && empty( $v['amount'] ) ) {
		return array( '', '' );
	}
	$text = exf_q_pounds( $v['amount'] ?? '' );
	if ( ! empty( $v['frequency'] ) ) {
		$text   .= ' ' . strtolower( $v['frequency'] );
		$monthly = exf_q_monthly( $v['amount'] ?? '', $v['frequency'] );
		if ( null !== $monthly && 'Monthly' !== $v['frequency'] ) {
			/* translators: %s: amount */
			$text .= ' (' . sprintf( __( '%s a month', 'exfiles' ), exf_q_pounds( $monthly ) ) . ')';
		}
	}
	$bits = array();
	if ( 'Estimated current' === $c ) {
		$bits[] = __( 'estimate', 'exfiles' );
	}
	if ( ! empty( $v['as_at'] ) ) {
		/* translators: %s: date */
		$bits[] = sprintf( __( 'as at %s', 'exfiles' ), exf_q_date( $v['as_at'] ) );
	}
	if ( ! empty( $v['basis'] ) ) {
		/* translators: %s: how it was estimated */
		$bits[] = sprintf( __( 'based on: %s', 'exfiles' ), $v['basis'] );
	}
	if ( $bits ) {
		$text .= ' – ' . implode( '; ', $bits );
	}
	return array( $text, 'Estimated current' === $c ? 'estimate' : '' );
}

/**
 * Summary rows for a list of fields.
 *
 * @param array $fields Field definitions.
 * @param array $values Values.
 * @param array $all    All answers.
 * @param int   $depth  Nesting.
 * @return array[] Rows: [ type => row|heading, label, value, flag, depth ]
 */
function exf_q_summary_fields( array $fields, array $values, array $all, $depth = 0 ) {
	$rows = array();
	foreach ( $fields as $key => $field ) {
		if ( ! exf_q_field_visible( $field, $values, $all ) ) {
			continue;
		}
		$v = $values[ $key ] ?? '';
		switch ( $field['type'] ) {
			case 'group':
				$rows[] = array( 'type' => 'heading', 'label' => $field['label'], 'depth' => $depth );
				$rows   = array_merge( $rows, exf_q_summary_fields( $field['fields'], is_array( $v ) ? $v : array(), $all, $depth + 1 ) );
				break;
			case 'repeater':
				$items = array_values( array_filter( is_array( $v ) ? $v : array(), 'is_array' ) );
				if ( ! $items ) {
					break;
				}
				foreach ( $items as $i => $item ) {
					$rows[] = array( 'type' => 'heading', 'label' => ucfirst( $field['item'] ) . ' ' . ( $i + 1 ), 'depth' => $depth );
					$rows   = array_merge( $rows, exf_q_summary_fields( $field['fields'], $item, $all, $depth + 1 ) );
				}
				break;
			case 'outgoings':
				$rows[] = array( 'type' => 'outgoings', 'label' => $field['label'], 'rows' => array_filter( is_array( $v ) ? $v : array(), 'is_array' ), 'depth' => $depth );
				break;
			case 'money':
				list( $text, $flag ) = exf_q_money_text( $field, is_array( $v ) ? $v : array() );
				if ( '' !== $text ) {
					$rows[] = array( 'type' => 'row', 'label' => $field['label'], 'value' => $text, 'flag' => $flag, 'depth' => $depth );
				}
				break;
			default:
				if ( '' === (string) $v ) {
					break;
				}
				$text   = 'date' === $field['type'] ? exf_q_date( $v ) : (string) $v;
				$text  .= 'number' === $field['type'] && false !== strpos( $field['label'], '%' ) ? '%' : '';
				$flag   = in_array( $v, array( "Don't know", 'Not sure' ), true ) ? 'dont_know' : ( 'date' === $field['type'] ? 'date' : '' );
				$rows[] = array( 'type' => 'row', 'label' => $field['label'], 'value' => $text, 'flag' => $flag, 'depth' => $depth );
		}
	}
	return $rows;
}

/**
 * Summary rows for a section.
 *
 * @param string $key  Section key.
 * @param array  $data All answers.
 */
function exf_q_summary_section( $key, array $data ) {
	$section = exf_q_sections()[ $key ];
	$values  = $data[ $key ] ?? array();
	$rows    = array();
	if ( ! empty( $section['gate'] ) ) {
		$gate   = $values['_gate'] ?? '';
		$rows[] = array(
			'type'  => 'row',
			'label' => $section['gate'],
			'value' => '' === $gate ? '—' : $gate,
			'flag'  => "Don't know" === $gate ? 'dont_know' : '',
			'depth' => 0,
		);
		if ( ! empty( $values['_gate_note'] ) ) {
			$rows[] = array( 'type' => 'row', 'label' => __( 'What you told us', 'exfiles' ), 'value' => $values['_gate_note'], 'flag' => '', 'depth' => 0 );
		}
		if ( 'Yes' !== $gate ) {
			return $rows;
		}
	}
	return array_merge( $rows, exf_q_summary_fields( $section['fields'], $values, $data ) );
}

/**
 * Monthly totals of the outgoings.
 *
 * @param array $data All answers.
 * @return array [ current, future ]
 */
function exf_q_outgoings_totals( array $data ) {
	$o     = $data['outgoings'] ?? array();
	$total = array(
		'current' => 0.0,
		'future'  => 0.0,
	);
	foreach ( (array) ( $o['rows'] ?? array() ) as $row ) {
		foreach ( array( 'current', 'future' ) as $col ) {
			$m = exf_q_monthly( $row[ $col ] ?? '', $row['frequency'] ?? 'Monthly' );
			if ( null !== $m ) {
				$total[ $col ] += $m;
			}
		}
	}
	foreach ( (array) ( $o['other'] ?? array() ) as $item ) {
		foreach ( array( 'current', 'future' ) as $col ) {
			$m = exf_q_monthly( $item[ $col ]['amount'] ?? '', $item[ $col ]['frequency'] ?? '' );
			if ( null !== $m ) {
				$total[ $col ] += $m;
			}
		}
	}
	return $total;
}

/**
 * Count of answers flagged Don't know and estimated, across the questionnaire.
 *
 * @param array $data All answers.
 * @return array [ dont_know, estimate ]
 */
function exf_q_flag_counts( array $data ) {
	$counts = array(
		'dont_know' => 0,
		'estimate'  => 0,
	);
	foreach ( exf_q_visible_sections( $data ) as $key ) {
		foreach ( exf_q_summary_section( $key, $data ) as $row ) {
			if ( ! empty( $row['flag'] ) && isset( $counts[ $row['flag'] ] ) ) {
				++$counts[ $row['flag'] ];
			}
		}
	}
	return $counts;
}

/**
 * The summary as HTML (review page, admin, PDF).
 *
 * @param array    $data     All answers.
 * @param callable $edit_url Optional: section key => edit URL.
 * @param array    $errors   Optional: section key => errors.
 */
function exf_q_summary_html( array $data, $edit_url = null, array $errors = array() ) {
	$sections = exf_q_sections();
	$html     = '';
	$n        = 0;
	foreach ( exf_q_visible_sections( $data ) as $key ) {
		++$n;
		$html .= '<section class="q-sum">';
		$html .= '<div class="q-sum-head"><h3>' . esc_html( $n . '. ' . $sections[ $key ]['title'] ) . '</h3>';
		if ( $edit_url ) {
			$html .= '<a href="' . esc_url( call_user_func( $edit_url, $key ) ) . '">' . esc_html__( 'Edit', 'exfiles' ) . '</a>';
		}
		$html .= '</div>';
		if ( ! empty( $errors[ $key ] ) ) {
			$html .= '<p class="q-sum-error">' . esc_html(
				isset( $errors[ $key ]['_section'] )
					? __( "You haven't completed this section yet.", 'exfiles' )
					/* translators: %d: number of answers */
					: sprintf( _n( '%d answer needs attention.', '%d answers need attention.', count( $errors[ $key ] ), 'exfiles' ), count( $errors[ $key ] ) )
			) . '</p>';
		}
		$rows = exf_q_summary_section( $key, $data );
		if ( ! $rows ) {
			$html .= '<p class="q-sum-empty">' . esc_html__( 'Nothing entered.', 'exfiles' ) . '</p>';
		}
		$html .= '<table class="q-sum-table"><tbody>';
		foreach ( $rows as $row ) {
			$pad = $row['depth'] ? ' style="padding-left:' . ( 10 + 18 * $row['depth'] ) . 'px"' : '';
			if ( 'heading' === $row['type'] ) {
				$html .= '<tr class="q-sum-sub"><th colspan="2"' . $pad . '>' . esc_html( $row['label'] ) . '</th></tr>';
			} elseif ( 'outgoings' === $row['type'] ) {
				$html .= '<tr><td colspan="2">' . exf_q_outgoings_table_html( $row['rows'] ) . '</td></tr>';
			} else {
				$flag  = $row['flag'] ? ' class="q-flag q-flag--' . esc_attr( $row['flag'] ) . '"' : '';
				$html .= '<tr><th scope="row"' . $pad . '>' . esc_html( $row['label'] ) . '</th><td><span' . $flag . '>' . nl2br( esc_html( $row['value'] ) ) . '</span></td></tr>';
			}
		}
		if ( 'outgoings' === $key ) {
			$t     = exf_q_outgoings_totals( $data );
			$html .= '<tr class="q-sum-total"><th scope="row">' . esc_html__( 'Total per month (current / future)', 'exfiles' ) . '</th><td>' . esc_html( exf_q_pounds( round( $t['current'], 2 ) ) . ' / ' . exf_q_pounds( round( $t['future'], 2 ) ) ) . '</td></tr>';
		}
		$html .= '</tbody></table></section>';
	}
	return $html;
}

/**
 * Outgoings as a small table (rows with an amount only).
 *
 * @param array $rows Outgoing rows.
 */
function exf_q_outgoings_table_html( array $rows ) {
	$cats = exf_q_outgoing_categories();
	$body = '';
	foreach ( $rows as $i => $r ) {
		if ( '' === ( $r['current'] ?? '' ) && '' === ( $r['future'] ?? '' ) ) {
			continue;
		}
		$flag  = 'Known' === ( $r['certainty'] ?? 'Known' ) ? '' : ' class="q-flag q-flag--' . ( 'Estimated' === $r['certainty'] ? 'estimate' : 'dont_know' ) . '"';
		$body .= '<tr><td>' . esc_html( $cats[ $i ] ?? '' ) . '</td><td>' . esc_html( '' === $r['current'] ? '—' : exf_q_pounds( $r['current'] ) ) . '</td><td>' . esc_html( '' === $r['future'] ? '—' : exf_q_pounds( $r['future'] ) ) . '</td><td>' . esc_html( strtolower( $r['frequency'] ) ) . '</td><td><span' . $flag . '>' . esc_html( $r['certainty'] ) . '</span></td></tr>';
	}
	if ( '' === $body ) {
		return '<p class="q-sum-empty">' . esc_html__( 'No outgoings entered.', 'exfiles' ) . '</p>';
	}
	return '<table class="q-out-table"><thead><tr><th>' . esc_html__( 'Category', 'exfiles' ) . '</th><th>' . esc_html__( 'Current', 'exfiles' ) . '</th><th>' . esc_html__( 'Future', 'exfiles' ) . '</th><th>' . esc_html__( 'How often', 'exfiles' ) . '</th><th>' . esc_html__( 'Certainty', 'exfiles' ) . '</th></tr></thead><tbody>' . $body . '</tbody></table>';
}
