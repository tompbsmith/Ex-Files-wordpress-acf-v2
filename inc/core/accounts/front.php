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
