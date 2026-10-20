<?php
/**
 * What a submitted questionnaire produces:
 *  - a PDF copy of the answers, saved as a document in the client's Ex File (they can see it);
 *  - an asset schedule (.xlsx) for the solicitor only.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * A one-line description: client, ex, service, submitted date.
 *
 * @param int $q Questionnaire.
 */
function exf_q_meta_line( $q ) {
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );
	$vars    = $ex_file ? exf_ex_file_email_vars( $ex_file ) : array( 'client_name' => '', 'ex_name' => '' );
	$at      = (int) get_post_meta( $q, 'q_submitted_at', true );
	return sprintf(
		/* translators: 1: client, 2: ex, 3: service, 4: date */
		__( '%1$s (separating from %2$s) · %3$s · Submitted %4$s', 'exfiles' ),
		$vars['client_name'],
		$vars['ex_name'],
		exf_q_service_name( $q ),
		$at ? wp_date( 'j F Y, g:ia', $at ) : '—'
	);
}

/**
 * Create the PDF copy and save it to the client's documents.
 *
 * @param int $q Questionnaire.
 * @return int Document ID, or 0.
 */
function exf_q_generate_pdf( $q ) {
	$data = exf_q_data( $q );
	$pdf  = exf_render_pdf(
		'questionnaire',
		array(
			'title'       => __( 'Your financial questionnaire', 'exfiles' ),
			'meta'        => exf_q_meta_line( $q ),
			'summary'     => exf_q_summary_html( $data ),
			'declaration' => exf_q_declaration_text(),
			'footer'      => exf_site_name() . ' · ' . __( 'Confidential', 'exfiles' ),
			'logo'        => function_exists( 'exf_guide_pdf_logo' ) ? exf_guide_pdf_logo() : '',
		)
	);
	if ( is_wp_error( $pdf ) ) {
		exf_q_log( $q, 'PDF failed: ' . $pdf->get_error_message() );
		return 0;
	}
	$name   = 'Financial-questionnaire-' . gmdate( 'Y-m-d' ) . '.pdf';
	$stored = exf_private_save( EXF_DOC_PRIVATE_DIR . '/' . gmdate( 'Y/m' ), $name, $pdf );
	if ( ! $stored ) {
		return 0;
	}
	$ex_file = (int) get_post_meta( $q, 'ex_file_id', true );

	// Replace the copy from an earlier submission of this questionnaire.
	$old = (int) get_post_meta( $q, 'q_pdf_doc', true );
	if ( $old && 'client_document' === get_post_type( $old ) ) {
		wp_delete_post( $old, true );
	}
	$doc = exf_document_create(
		$ex_file,
		array(
			'file'      => $stored,
			'file_name' => $name,
			'file_size' => strlen( $pdf ),
		),
		'questionnaire',
		/* translators: %s: service */
		sprintf( __( 'A copy of the answers you gave for %s.', 'exfiles' ), exf_q_service_name( $q ) ),
		'firm',
		/* translators: %s: service */
		sprintf( __( 'Your questionnaire answers (%s)', 'exfiles' ), exf_q_service_name( $q ) )
	);
	if ( $doc ) {
		update_post_meta( $doc, 'viewed_at', time() ); // Their own answers aren't "new".
		update_post_meta( $doc, 'questionnaire_id', $q );
		update_post_meta( $q, 'q_pdf_doc', $doc );
	}
	return $doc;
}

/**
 * The declaration the client confirms.
 */
function exf_q_declaration_text() {
	return (string) exf_get_option_field( 'q_declaration', EXF_PORTAL_ID, __( 'I confirm that the information I have given is true and complete to the best of my knowledge. Where I have estimated a figure or said I don’t know, I have said so. I understand The Ex Files will rely on this information, and that it has not yet been checked.', 'exfiles' ) );
}

/* -------------------------------------------------------------------------
 * Asset schedule (.xlsx)
 * ---------------------------------------------------------------------- */

/**
 * A money answer as a spreadsheet cell.
 *
 * @param mixed $v Money value.
 */
function exf_q_money_cell( $v ) {
	$v = is_array( $v ) ? $v : array();
	$c = $v['certainty'] ?? '';
	if ( "Don't know" === $c ) {
		return array( 'v' => "Don't know", 's' => 'dont_know' );
	}
	if ( 'None' === $c ) {
		return array( 'v' => 0.0, 's' => 'money' );
	}
	if ( ! isset( $v['amount'] ) || '' === $v['amount'] || ! is_numeric( $v['amount'] ) ) {
		return null;
	}
	return array( 'v' => (float) $v['amount'], 's' => 'Estimated current' === $c ? 'estimate' : 'money' );
}

/**
 * Number from a money answer for totals (Don't know and blanks count as 0).
 *
 * @param mixed $v Money value.
 */
function exf_q_money_num( $v ) {
	return ( is_array( $v ) && "Don't know" !== ( $v['certainty'] ?? '' ) && is_numeric( $v['amount'] ?? '' ) ) ? (float) $v['amount'] : 0.0;
}

/**
 * Certainty label for a cell.
 *
 * @param mixed $v Money value.
 */
function exf_q_certainty_label( $v ) {
	return is_array( $v ) ? str_replace( ' current', '', (string) ( $v['certainty'] ?? '' ) ) : '';
}

/**
 * Date cell.
 *
 * @param string $ymd Date.
 */
function exf_q_date_cell( $ymd ) {
	return $ymd ? array( 'v' => $ymd, 's' => 'date' ) : null;
}

/**
 * A header row.
 *
 * @param string[] $labels Labels.
 */
function exf_q_header_row( array $labels ) {
	return array_map(
		function ( $l ) {
			return array( 'v' => $l, 's' => 'header' );
		},
		$labels
	);
}

/**
 * Build the asset schedule workbook.
 *
 * @param int $q Questionnaire.
 * @return string|WP_Error Bytes.
 */
function exf_q_build_xlsx( $q ) {
	$d     = exf_q_data( $q );
	$x     = new Exf_Xlsx();
	$yes   = function ( $section ) use ( $d ) {
		return 'Yes' === ( $d[ $section ]['_gate'] ?? '' );
	};
	$money = 'exf_q_money_cell';
	$num   = 'exf_q_money_num';

	// Assets.
	$rows   = array(
		array( array( 'v' => __( 'Assets', 'exfiles' ), 's' => 'title' ) ),
		array( __( 'Yellow = estimate. Red = client doesn’t know. Net value = value less mortgage, charges and tax, where given.', 'exfiles' ) ),
		array(),
		exf_q_header_row( array( 'Type', 'Description', 'Owner', 'Value', 'Mortgage', 'Early repayment charge', 'CGT', 'Net value', 'Certainty (value)', 'As at', 'Basis of estimate', 'Notes' ) ),
	);
	$first  = count( $rows ) + 1;
	$assets = 0.0;
	if ( $yes( 'properties' ) ) {
		foreach ( (array) ( $d['properties']['properties'] ?? array() ) as $p ) {
			$net     = $num( $p['value'] ?? null ) - $num( $p['mortgage'] ?? null ) - $num( $p['erc'] ?? null ) - $num( $p['cgt'] ?? null );
			$assets += $net;
			$rows[]  = array( 'Property', array( 'v' => $p['address'] ?? '', 's' => 'wrap' ), $p['owner'] ?? '', $money( $p['value'] ?? null ), $money( $p['mortgage'] ?? null ), $money( $p['erc'] ?? null ), $money( $p['cgt'] ?? null ), array( 'v' => $net, 's' => 'money' ), exf_q_certainty_label( $p['value'] ?? null ), exf_q_date_cell( $p['value']['as_at'] ?? '' ), $p['value']['basis'] ?? '', array( 'v' => $p['additional'] ?? '', 's' => 'wrap' ) );
		}
	}
	if ( $yes( 'accounts' ) ) {
		foreach ( (array) ( $d['accounts']['accounts'] ?? array() ) as $a ) {
			$assets += $num( $a['balance'] ?? null );
			$rows[]  = array( 'Account' . ( ! empty( $a['kind'] ) ? ' (' . $a['kind'] . ')' : '' ), $a['bank'] ?? '', $a['owner'] ?? '', $money( $a['balance'] ?? null ), null, null, null, array( 'v' => $num( $a['balance'] ?? null ), 's' => 'money' ), exf_q_certainty_label( $a['balance'] ?? null ), exf_q_date_cell( $a['balance']['as_at'] ?? '' ), $a['balance']['basis'] ?? '', array( 'v' => $a['additional'] ?? '', 's' => 'wrap' ) );
		}
	}
	if ( $yes( 'investments' ) ) {
		foreach ( (array) ( $d['investments']['investments'] ?? array() ) as $i ) {
			$assets += $num( $i['value'] ?? null );
			$note    = trim( ( isset( $i['maturity']['amount'] ) && '' !== $i['maturity']['amount'] ? 'Maturity value £' . $i['maturity']['amount'] . ( ! empty( $i['maturity_date'] ) ? ' on ' . exf_q_date( $i['maturity_date'] ) : '' ) . '. ' : '' ) . ( $i['additional'] ?? '' ) );
			$rows[]  = array( 'Investment', $i['description'] ?? '', $i['owner'] ?? '', $money( $i['value'] ?? null ), null, null, null, array( 'v' => $num( $i['value'] ?? null ), 's' => 'money' ), exf_q_certainty_label( $i['value'] ?? null ), exf_q_date_cell( $i['value']['as_at'] ?? '' ), $i['value']['basis'] ?? '', array( 'v' => $note, 's' => 'wrap' ) );
		}
	}
	if ( $yes( 'assets' ) ) {
		foreach ( (array) ( $d['assets']['assets'] ?? array() ) as $a ) {
			$assets += $num( $a['value'] ?? null );
			$rows[]  = array( 'Other asset', $a['description'] ?? '', $a['owner'] ?? '', $money( $a['value'] ?? null ), null, null, null, array( 'v' => $num( $a['value'] ?? null ), 's' => 'money' ), exf_q_certainty_label( $a['value'] ?? null ), exf_q_date_cell( $a['value']['as_at'] ?? '' ), $a['value']['basis'] ?? '', array( 'v' => $a['additional'] ?? '', 's' => 'wrap' ) );
		}
	}
	if ( $yes( 'business' ) ) {
		foreach ( (array) ( $d['business']['businesses'] ?? array() ) as $b ) {
			$value   = 'Yes' === ( $b['know_value'] ?? '' ) ? ( $b['value'] ?? null ) : array( 'certainty' => "Don't know" );
			$net     = $num( $value ) - $num( $b['cgt'] ?? null );
			$assets += $net;
			$desc    = ( $b['name'] ?? '' ) . ( ! empty( $b['structure'] ) ? ' – ' . ( 'Other' === $b['structure'] ? ( $b['structure_other'] ?? 'Other' ) : $b['structure'] ) : '' ) . ( isset( $b['percent'] ) && '' !== $b['percent'] ? ' (' . $b['percent'] . '%)' : '' );
			$note    = trim( ( ! empty( $b['value_source'] ) ? 'Value from: ' . $b['value_source'] . '. ' : '' ) . ( $b['additional'] ?? '' ) );
			$rows[]  = array( 'Business', $desc, $b['owner'] ?? '', $money( $value ), null, null, $money( $b['cgt'] ?? null ), array( 'v' => $net, 's' => 'money' ), exf_q_certainty_label( $value ), exf_q_date_cell( $value['as_at'] ?? '' ), $value['basis'] ?? '', array( 'v' => $note, 's' => 'wrap' ) );
		}
	}
	$last   = count( $rows );
	$rows[] = array();
	$rows[] = array( array( 'v' => 'Total', 's' => 'bold' ), null, null, null, null, null, null, $last >= $first ? array( 'f' => 'SUM(H' . $first . ':H' . $last . ')', 's' => 'money_bold' ) : array( 'v' => 0, 's' => 'money_bold' ) );
	$x->sheet( 'Assets', $rows, array( 16, 36, 20, 13, 13, 13, 11, 13, 14, 12, 26, 40 ) );

	// Liabilities.
	$rows  = array(
		array( array( 'v' => __( 'Liabilities', 'exfiles' ), 's' => 'title' ) ),
		array(),
		exf_q_header_row( array( 'Creditor / type', 'Who is liable', 'Names on agreement', 'Supplementary cardholder?', 'Balance', 'Monthly repayment', 'Certainty (balance)', 'As at', 'Basis of estimate', 'Notes' ) ),
	);
	$first = count( $rows ) + 1;
	$debts = 0.0;
	if ( $yes( 'liabilities' ) ) {
		foreach ( (array) ( $d['liabilities']['liabilities'] ?? array() ) as $l ) {
			$debts += $num( $l['balance'] ?? null );
			$rows[] = array( $l['creditor'] ?? '', $l['liable'] ?? '', $l['names'] ?? '', $l['supplementary'] ?? '', $money( $l['balance'] ?? null ), $money( $l['monthly'] ?? null ), exf_q_certainty_label( $l['balance'] ?? null ), exf_q_date_cell( $l['balance']['as_at'] ?? '' ), $l['balance']['basis'] ?? '', array( 'v' => $l['additional'] ?? '', 's' => 'wrap' ) );
		}
	}
	$last   = count( $rows );
	$rows[] = array();
	$rows[] = array( array( 'v' => 'Total', 's' => 'bold' ), null, null, null, $last >= $first ? array( 'f' => 'SUM(E' . $first . ':E' . $last . ')', 's' => 'money_bold' ) : array( 'v' => 0, 's' => 'money_bold' ), $last >= $first ? array( 'f' => 'SUM(F' . $first . ':F' . $last . ')', 's' => 'money_bold' ) : null );
	$x->sheet( 'Liabilities', $rows, array( 30, 22, 26, 14, 13, 13, 14, 12, 26, 40 ) );

	// Pensions.
	$rows     = array(
		array( array( 'v' => __( 'Pensions', 'exfiles' ), 's' => 'title' ) ),
		array(),
		exf_q_header_row( array( 'Provider', 'Whose', 'Type', 'In payment / drawdown', 'CETV', 'Certainty', 'As at', 'Basis of estimate', 'Notes' ) ),
	);
	$first    = count( $rows ) + 1;
	$pensions = 0.0;
	if ( $yes( 'pensions' ) ) {
		foreach ( (array) ( $d['pensions']['pensions'] ?? array() ) as $p ) {
			$cetv      = 'Yes' === ( $p['know_cetv'] ?? '' ) ? ( $p['cetv'] ?? null ) : array( 'certainty' => "Don't know" );
			$pensions += $num( $cetv );
			$rows[]    = array( $p['provider'] ?? '', $p['owner'] ?? '', $p['kind'] ?? '', $p['in_payment'] ?? '', $money( $cetv ), exf_q_certainty_label( $cetv ), exf_q_date_cell( $cetv['as_at'] ?? '' ), $cetv['basis'] ?? '', array( 'v' => $p['additional'] ?? '', 's' => 'wrap' ) );
		}
	}
	$last   = count( $rows );
	$rows[] = array();
	$rows[] = array( array( 'v' => 'Total', 's' => 'bold' ), null, null, null, $last >= $first ? array( 'f' => 'SUM(E' . $first . ':E' . $last . ')', 's' => 'money_bold' ) : array( 'v' => 0, 's' => 'money_bold' ) );
	$x->sheet( 'Pensions', $rows, array( 28, 18, 30, 14, 13, 12, 12, 26, 40 ) );

	// Income.
	$rows    = array(
		array( array( 'v' => __( 'Income', 'exfiles' ), 's' => 'title' ) ),
		array( __( 'Monthly equivalent: weekly × 52 ÷ 12; fortnightly × 26 ÷ 12; four-weekly × 13 ÷ 12; quarterly × 4 ÷ 12; annual ÷ 12.', 'exfiles' ) ),
		exf_q_header_row( array( 'Person', 'Source', 'Detail', 'Amount', 'Frequency', 'Monthly equivalent', 'Certainty', 'As at', 'Basis of estimate' ) ),
	);
	$monthly = array(
		'client' => 0.0,
		'spouse' => 0.0,
	);
	$income  = $d['income'] ?? array();
	foreach ( array( 'client' => __( 'Client', 'exfiles' ), 'spouse' => __( 'Spouse/partner', 'exfiles' ) ) as $who => $label ) {
		$has = $income[ $who . '_has' ] ?? '';
		if ( 'Yes' !== $has ) {
			$rows[] = array( $label, '' === $has ? '—' : ( 'No' === $has ? 'No income' : array( 'v' => "Don't know", 's' => 'dont_know' ) ) );
			continue;
		}
		$g    = $income[ $who ] ?? array();
		$line = function ( $source, $detail, $m ) use ( &$rows, &$monthly, $who, $label, $money ) {
			$m   = is_array( $m ) ? $m : array();
			$per = exf_q_monthly( $m['amount'] ?? '', $m['frequency'] ?? '' );
			if ( null !== $per && "Don't know" !== ( $m['certainty'] ?? '' ) && 'Gross salary' !== $source ) {
				$monthly[ $who ] += $per;
			}
			$rows[] = array( $label, $source, $detail, $money( $m ), $m['frequency'] ?? '', null === $per ? null : array( 'v' => $per, 's' => 'money' ), exf_q_certainty_label( $m ), exf_q_date_cell( $m['as_at'] ?? '' ), $m['basis'] ?? '' );
		};
		foreach ( (array) ( $g['employment'] ?? array() ) as $e ) {
			$line( 'Gross salary', $e['occupation'] ?? '', $e['gross'] ?? null );
			$line( 'Net salary', $e['occupation'] ?? '', $e['net'] ?? null );
		}
		foreach ( (array) ( $g['dividends'] ?? array() ) as $e ) {
			$line( 'Dividends', $e['business'] ?? '', $e['amount'] ?? null );
		}
		foreach ( (array) ( $g['benefits'] ?? array() ) as $e ) {
			$line( 'Benefit', $e['benefit'] ?? '', $e['amount'] ?? null );
		}
		if ( ! empty( $g['maintenance']['certainty'] ) && 'None' !== $g['maintenance']['certainty'] ) {
			$line( 'Child maintenance', '', $g['maintenance'] );
		}
		foreach ( (array) ( $g['pension_inc'] ?? array() ) as $e ) {
			$line( 'Pension income', $e['kind'] ?? '', $e['amount'] ?? null );
		}
		foreach ( (array) ( $g['other_inc'] ?? array() ) as $e ) {
			$line( 'Other income', $e['source'] ?? '', $e['amount'] ?? null );
		}
	}
	$rows[] = array();
	$rows[] = array( array( 'v' => 'Client: monthly net income (net salary and other income)', 's' => 'bold' ), null, null, null, null, array( 'v' => round( $monthly['client'], 2 ), 's' => 'money_bold' ) );
	$rows[] = array( array( 'v' => 'Spouse/partner: monthly net income', 's' => 'bold' ), null, null, null, null, array( 'v' => round( $monthly['spouse'], 2 ), 's' => 'money_bold' ) );
	$x->sheet( 'Income', $rows, array( 16, 20, 28, 13, 13, 15, 12, 12, 26 ) );

	// Outgoings.
	$rows  = array(
		array( array( 'v' => __( 'Monthly outgoings', 'exfiles' ), 's' => 'title' ) ),
		array(),
		exf_q_header_row( array( 'Category', 'Current (as paid)', 'Future (as paid)', 'Frequency', 'Current per month', 'Future per month', 'Certainty' ) ),
	);
	$first = count( $rows ) + 1;
	$cats  = exf_q_outgoing_categories();
	foreach ( (array) ( $d['outgoings']['rows'] ?? array() ) as $i => $r ) {
		if ( '' === ( $r['current'] ?? '' ) && '' === ( $r['future'] ?? '' ) ) {
			continue;
		}
		$style  = 'Estimated' === $r['certainty'] ? 'estimate' : 'money';
		$cur    = exf_q_monthly( $r['current'], $r['frequency'] );
		$fut    = exf_q_monthly( $r['future'], $r['frequency'] );
		$rows[] = array( $cats[ $i ] ?? '', is_numeric( $r['current'] ) ? array( 'v' => (float) $r['current'], 's' => $style ) : null, is_numeric( $r['future'] ) ? array( 'v' => (float) $r['future'], 's' => $style ) : null, $r['frequency'], null === $cur ? null : array( 'v' => $cur, 's' => 'money' ), null === $fut ? null : array( 'v' => $fut, 's' => 'money' ), 'Known' === $r['certainty'] ? 'Known' : array( 'v' => $r['certainty'], 's' => 'Estimated' === $r['certainty'] ? 'estimate' : 'dont_know' ) );
	}
	foreach ( (array) ( $d['outgoings']['other'] ?? array() ) as $o ) {
		$cur    = exf_q_monthly( $o['current']['amount'] ?? '', $o['current']['frequency'] ?? '' );
		$fut    = exf_q_monthly( $o['future']['amount'] ?? '', $o['future']['frequency'] ?? '' );
		$rows[] = array( 'Other: ' . ( $o['description'] ?? '' ), $money( $o['current'] ?? null ), $money( $o['future'] ?? null ), trim( ( $o['current']['frequency'] ?? '' ) . ' / ' . ( $o['future']['frequency'] ?? '' ), ' /' ), null === $cur ? null : array( 'v' => $cur, 's' => 'money' ), null === $fut ? null : array( 'v' => $fut, 's' => 'money' ), exf_q_certainty_label( $o['current'] ?? null ) );
	}
	$last   = count( $rows );
	$rows[] = array();
	$rows[] = array( array( 'v' => 'Total per month', 's' => 'bold' ), null, null, null, $last >= $first ? array( 'f' => 'SUM(E' . $first . ':E' . $last . ')', 's' => 'money_bold' ) : array( 'v' => 0, 's' => 'money_bold' ), $last >= $first ? array( 'f' => 'SUM(F' . $first . ':F' . $last . ')', 's' => 'money_bold' ) : array( 'v' => 0, 's' => 'money_bold' ) );
	if ( ! empty( $d['outgoings']['additional'] ) ) {
		$rows[] = array();
		$rows[] = array( array( 'v' => 'Notes', 's' => 'bold' ), array( 'v' => $d['outgoings']['additional'], 's' => 'wrap' ) );
	}
	$x->sheet( 'Outgoings', $rows, array( 52, 15, 15, 13, 15, 15, 12 ) );

	// Children.
	$rows = array(
		array( array( 'v' => __( 'Children', 'exfiles' ), 's' => 'title' ) ),
		array(),
		exf_q_header_row( array( 'Name', 'Gender', 'Date of birth', 'Education', 'School fees', 'Fees frequency', 'Usually lives', "Child's parent", 'Child of the family?', 'Care arrangements / notes' ) ),
	);
	if ( $yes( 'children' ) ) {
		foreach ( (array) ( $d['children']['children'] ?? array() ) as $c ) {
			$rows[] = array( $c['name'] ?? '', $c['gender'] ?? '', exf_q_date_cell( $c['dob'] ?? '' ), trim( ( $c['education'] ?? '' ) . ( ! empty( $c['education_other'] ) ? ': ' . $c['education_other'] : '' ) ), $money( $c['school_fees'] ?? null ), $c['school_fees']['frequency'] ?? '', $c['residence'] ?? '', $c['parent'] ?? '', $c['child_of_family'] ?? '', array( 'v' => trim( ( $c['care'] ?? '' ) . ' ' . ( $c['additional'] ?? '' ) ), 's' => 'wrap' ) );
		}
	} else {
		$rows[] = array( 'No children recorded' );
	}
	$x->sheet( 'Children', $rows, array( 18, 14, 13, 34, 12, 12, 20, 16, 14, 40 ) );

	// Summary (built last, shown first).
	$about   = $d['about'] ?? array();
	$counts  = exf_q_flag_counts( $d );
	$out     = exf_q_outgoings_totals( $d );
	$summary = array(
		array( array( 'v' => __( 'Asset schedule', 'exfiles' ), 's' => 'title' ) ),
		array( exf_q_meta_line( $q ) ),
		array(),
		array( array( 'v' => 'Married / civil partnership', 's' => 'bold' ), 'No' === ( $about['married'] ?? '' ) ? array( 'v' => 'No – check suitability', 's' => 'dont_know' ) : ( $about['married'] ?? '' ) ),
		array( array( 'v' => 'Date of marriage', 's' => 'bold' ), exf_q_date_cell( $about['marriage_date'] ?? '' ) ),
		array( array( 'v' => 'Date of separation', 's' => 'bold' ), exf_q_date_cell( $about['separation_date'] ?? '' ) ),
		array( array( 'v' => 'Agreement reached / offer received / offer to make', 's' => 'bold' ), implode( ' / ', array( $about['agreement_reached'] ?? '—', $about['offer_received'] ?? '—', $about['offer_to_make'] ?? '—' ) ) ),
		array(),
		array( array( 'v' => 'Totals (known and estimated figures; "Don\'t know" counted as nil)', 's' => 'bold' ) ),
		array( 'Net assets (after mortgages, charges and tax)', array( 'v' => round( $assets, 2 ), 's' => 'money' ) ),
		array( 'Liabilities', array( 'v' => round( $debts, 2 ), 's' => 'money' ) ),
		array( array( 'v' => 'Net assets less liabilities', 's' => 'bold' ), array( 'v' => round( $assets - $debts, 2 ), 's' => 'money_bold' ) ),
		array( 'Pensions (CETVs)', array( 'v' => round( $pensions, 2 ), 's' => 'money' ) ),
		array( 'Client monthly net income', array( 'v' => round( $monthly['client'], 2 ), 's' => 'money' ) ),
		array( 'Spouse/partner monthly net income', array( 'v' => round( $monthly['spouse'], 2 ), 's' => 'money' ) ),
		array( 'Outgoings per month now', array( 'v' => round( $out['current'], 2 ), 's' => 'money' ) ),
		array( 'Outgoings per month after separating', array( 'v' => round( $out['future'], 2 ), 's' => 'money' ) ),
		array(),
		array( array( 'v' => 'Answers the client doesn\'t know', 's' => 'bold' ), $counts['dont_know'] ),
		array( array( 'v' => 'Estimated answers', 's' => 'bold' ), $counts['estimate'] ),
		array(),
		array( __( 'Client-reported information for legal review. It has not been verified.', 'exfiles' ) ),
	);
	$x->sheet( 'Summary', $summary, array( 52, 30 ), true );

	return $x->output();
}

/**
 * Create the schedule and keep it with the questionnaire (staff only).
 *
 * @param int $q Questionnaire.
 * @return bool
 */
function exf_q_generate_xlsx( $q ) {
	$bytes = exf_q_build_xlsx( $q );
	if ( is_wp_error( $bytes ) ) {
		exf_q_log( $q, 'Asset schedule failed: ' . $bytes->get_error_message() );
		return false;
	}
	$client = get_userdata( (int) get_post_field( 'post_author', $q ) );
	$name   = 'Asset-schedule-' . sanitize_file_name( $client ? $client->last_name . '-' . $client->first_name : 'client' ) . '-' . gmdate( 'Y-m-d' ) . '.xlsx';
	$stored = exf_private_save( 'schedules', $name, $bytes );
	if ( ! $stored ) {
		return false;
	}
	exf_private_delete( (string) get_post_meta( $q, 'q_xlsx', true ) );
	update_post_meta( $q, 'q_xlsx', $stored );
	update_post_meta( $q, 'q_xlsx_name', $name );
	if ( function_exists( 'exf_gdrive_enabled' ) && exf_gdrive_enabled() ) {
		exf_q_send_to_gsheets( $q );
	}
	return true;
}
