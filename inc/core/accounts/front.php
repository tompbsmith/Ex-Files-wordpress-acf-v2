<?php
/**
 * Front-end helpers for the registration and account pages.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_page_template( 'page-register.php' ) ) {
			return;
		}
		$js = EXF_DIR . '/assets/js/register.js';
		wp_enqueue_script( 'exf-register', EXF_URI . '/assets/js/register.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : EXF_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'exf-register',
			'exfRegister',
			array(
				'required'  => __( 'Please fill in the highlighted fields.', 'exfiles' ),
				'mismatch'  => __( "The passwords don't match.", 'exfiles' ),
				'short'     => __( 'Please use at least 10 characters.', 'exfiles' ),
				'exDob'     => __( "Please enter their date of birth, or tick that you don't know it.", 'exfiles' ),
				'stepLabel' => __( 'Step %1$s of %2$s', 'exfiles' ),
				'sending'   => __( 'Opening your Ex File…', 'exfiles' ),
			)
		);
	}
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page_template( array( 'page-register.php', 'page-account.php' ) ) ) {
			$robots['noindex'] = is_page_template( 'page-account.php' ) || ! empty( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return $robots;
	}
);

/**
 * Value for an <input type="date"> from a stored Ymd or Y-m-d date.
 *
 * @param string $value Stored date.
 */
function exf_date_input_value( $value ) {
	return exf_norm_dob( (string) $value );
}

/**
 * A date as three boxes (Day / Month / Year), the pattern GOV.UK uses for
 * dates of birth: quicker than a calendar for dates years back, and the same in
 * every browser. Posted as name[d], name[m], name[y]; exf_merge_date_parts()
 * turns that back into one value before any form handler runs.
 *
 * @param string $name  Field name.
 * @param string $label Label.
 * @param array  $args  Field args.
 * @param string $id    Base id.
 * @param string $error Error message.
 * @param array  $desc  Ids describing the field.
 */
function exf_form_date_field( $name, $label, array $args, $id, $error, array $desc ) {
	$raw  = (string) ( $args['values'][ $name ] ?? '' );
	$req  = $args['required'] ? ' required aria-required="true"' : '';
	$aria = $error ? ' aria-invalid="true"' : '';
	// A labelled group rather than fieldset/legend: a legend can't take part in the
	// grid that lines up the boxes of side-by-side dates.
	echo '<div role="group" aria-labelledby="' . esc_attr( $id . '-legend' ) . '" class="field field--date' . ( $error ? ' has-error' : '' ) . ( $args['class'] ? ' ' . esc_attr( $args['class'] ) : '' ) . '"' . ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . '>';
	printf( '<div class="field-legend" id="%s">%s%s</div>', esc_attr( $id . '-legend' ), esc_html( $label ), $args['required'] ? '' : ' <span class="optional">' . esc_html__( '(optional)', 'exfiles' ) . '</span>' );
	echo exf_date_parts_html( $name, $id, $raw, $req . $aria . $args['attrs'], 'bday' === $args['autocomplete'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( $args['note'] ) {
		printf( '<p class="field-note" id="%s-note">%s</p>', esc_attr( $id ), esc_html( $args['note'] ) );
	}
	if ( $error ) {
		printf( '<p class="field-error" id="%s-error">%s</p>', esc_attr( $id ), wp_kses( $error, array( 'a' => array( 'href' => array() ) ) ) );
	}
	echo '</div>';
}

/**
 * The three Day / Month / Year boxes, named name[d], name[m], name[y].
 *
 * @param string $name  Field name.
 * @param string $id    Id for the Day box (Month and Year get -m / -y).
 * @param string $raw   Saved value: Y-m-d, Ymd or d/m/y as typed.
 * @param string $attrs Extra attributes for each box (already escaped).
 * @param bool   $bday  Offer the browser's saved date of birth.
 * @return string
 */
function exf_date_parts_html( $name, $id, $raw, $attrs = '', $bday = false ) {
	$raw   = (string) $raw;
	$parts = array( '', '', '' );
	if ( preg_match( '/^(\d{4})-?(\d{1,2})-?(\d{1,2})$/', $raw, $m ) ) {
		$parts = array( ltrim( $m[3], '0' ), ltrim( $m[2], '0' ), $m[1] );
	} elseif ( preg_match( '#^(\d{0,2})/(\d{0,2})/(\d{0,4})$#', $raw, $m ) ) {
		$parts = array( $m[1], $m[2], $m[3] );
	}
	$boxes = array(
		'd' => array( __( 'Day', 'exfiles' ), 2, '[0-9]{1,2}', 'bday-day', $parts[0] ),
		'm' => array( __( 'Month', 'exfiles' ), 2, '[0-9]{1,2}', 'bday-month', $parts[1] ),
		'y' => array( __( 'Year', 'exfiles' ), 4, '[0-9]{4}', 'bday-year', $parts[2] ),
	);
	$html = '<div class="date-parts">';
	foreach ( $boxes as $k => $b ) {
		$html .= sprintf(
			'<label class="date-part date-part--%1$s"><span>%2$s</span><input type="text" inputmode="numeric" id="%3$s" name="%4$s" value="%5$s" maxlength="%6$d" pattern="%7$s" autocomplete="%8$s"%9$s></label>',
			esc_attr( $k ),
			esc_html( $b[0] ),
			esc_attr( $id . ( 'd' === $k ? '' : '-' . $k ) ),
			esc_attr( $name . '[' . $k . ']' ),
			esc_attr( $b[4] ),
			(int) $b[1],
			esc_attr( $b[2] ),
			esc_attr( $bday ? $b[3] : 'off' ),
			$attrs
		);
	}
	return $html . '</div>';
}

/**
 * Turn posted Day / Month / Year boxes into one value: Y-m-d when it's a real
 * date, "d/m/y" as typed otherwise (so the form's own check reports it), or ''.
 * Runs before any form handler, so handlers see a plain string as before.
 */
function exf_merge_date_parts() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- reshaping only; each handler checks its nonce.
	foreach ( $_POST as $key => $value ) {
		if ( is_array( $value ) ) {
			$_POST[ $key ] = exf_merge_date_parts_in( $value );
			if ( isset( $_REQUEST[ $key ] ) ) {
				$_REQUEST[ $key ] = $_POST[ $key ];
			}
		}
	}
}

/**
 * Merge date boxes at any depth (the questionnaire nests them, e.g. q[properties][0][value][as_at]).
 *
 * @param array $value Posted array.
 * @return array|string
 */
function exf_merge_date_parts_in( array $value ) {
	$keys = array_keys( $value );
	sort( $keys );
	if ( array( 'd', 'm', 'y' ) === $keys ) {
		$d = trim( (string) $value['d'] );
		$m = trim( (string) $value['m'] );
		$y = trim( (string) $value['y'] );
		if ( '' === $d . $m . $y ) {
			return '';
		}
		if ( ctype_digit( $d . $m . $y ) && 4 === strlen( $y ) && checkdate( (int) $m, (int) $d, (int) $y ) ) {
			return sprintf( '%04d-%02d-%02d', $y, $m, $d );
		}
		return $d . '/' . $m . '/' . $y;
	}
	foreach ( $value as $k => $v ) {
		if ( is_array( $v ) ) {
			$value[ $k ] = exf_merge_date_parts_in( $v );
		}
	}
	return $value;
}
add_action( 'init', 'exf_merge_date_parts', 0 );

/**
 * Print a labelled form field.
 *
 * @param string $name   Field name.
 * @param string $label  Label.
 * @param array  $args   type (text|email|tel|date|password|select|textarea), required, values, errors, autocomplete, options, note, attrs.
 */
function exf_form_field( $name, $label, array $args = array() ) {
	$args  = wp_parse_args(
		$args,
		array(
			'type'         => 'text',
			'required'     => false,
			'values'       => array(),
			'errors'       => array(),
			'autocomplete' => '',
			'options'      => array(),
			'note'         => '',
			'attrs'        => '',
			'class'        => '',
		)
	);
	$id    = 'exf-' . str_replace( '_', '-', $name );
	$value = $args['values'][ $name ] ?? '';
	if ( 'date' === $args['type'] ) {
		$value = exf_date_input_value( $value );
	}
	$error = $args['errors'][ $name ] ?? '';
	$req   = $args['required'] ? ' required aria-required="true"' : '';
	$desc  = array();
	if ( $args['note'] ) {
		$desc[] = $id . '-note';
	}
	if ( $error ) {
		$desc[] = $id . '-error';
	}
	$aria = ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . ( $error ? ' aria-invalid="true"' : '' );
	$ac   = $args['autocomplete'] ? ' autocomplete="' . esc_attr( $args['autocomplete'] ) . '"' : '';

	if ( 'date' === $args['type'] ) {
		exf_form_date_field( $name, $label, $args, $id, $error, $desc );
		return;
	}

	echo '<div class="field' . ( $error ? ' has-error' : '' ) . ( $args['class'] ? ' ' . esc_attr( $args['class'] ) : '' ) . '">';
	printf( '<label for="%s">%s%s</label>', esc_attr( $id ), esc_html( $label ), $args['required'] ? '' : ' <span class="optional">' . esc_html__( '(optional)', 'exfiles' ) . '</span>' );

	if ( 'select' === $args['type'] ) {
		printf( '<select id="%1$s" name="%2$s"%3$s%4$s%5$s>', esc_attr( $id ), esc_attr( $name ), $req, $aria, $args['attrs'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $args['options'] as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	} elseif ( 'textarea' === $args['type'] ) {
		printf( '<textarea id="%1$s" name="%2$s"%3$s%4$s%5$s>%6$s</textarea>', esc_attr( $id ), esc_attr( $name ), $req, $aria, $args['attrs'], esc_textarea( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s>',
			esc_attr( $args['type'] ),
			esc_attr( $id ),
			esc_attr( $name ),
			'password' === $args['type'] ? '' : esc_attr( $value ),
			$req, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$ac, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$args['attrs'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	if ( $args['note'] ) {
		printf( '<p class="field-note" id="%s-note">%s</p>', esc_attr( $id ), esc_html( $args['note'] ) );
	}
	if ( $error ) {
		printf( '<p class="field-error" id="%s-error">%s</p>', esc_attr( $id ), wp_kses( $error, array( 'a' => array( 'href' => array() ) ) ) );
	}
	echo '</div>';
}
