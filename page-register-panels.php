<?php
/**
 * Template Name: Open Your Ex File (panels)
 *
 * The alternative "Create your Ex File" layout, from the Wix design: Page Builder
 * sections at the top (e.g. an "Image and text" section), then "Your details" and
 * "Partner's details" side by side. It posts to the same registration handler as the
 * step-by-step form (inc/core/accounts/register.php), which maps the combined
 * "address and postcode" boxes onto the usual fields.
 *
 * @package exfiles
 */

$exf_errors = $GLOBALS['exf_register_errors'] ?? array();
$exf_values = $GLOBALS['exf_register_values'] ?? array();
$exf_basket = function_exists( 'exf_basket_get' ) ? exf_basket_get() : null;
$exf_bsvc   = function_exists( 'exf_basket_service' ) ? exf_basket_service( $exf_basket ) : null;

/**
 * One underlined field on a dark panel.
 *
 * @param string $name  Field name.
 * @param string $label Label.
 * @param array  $o     type, required, autocomplete, options.
 */
$exf_pf = function ( $name, $label, array $o = array() ) use ( $exf_values, $exf_errors ) {
	$o     = wp_parse_args( $o, array( 'type' => 'text', 'required' => false, 'autocomplete' => 'off', 'options' => array() ) );
	$id    = 'exf-p-' . str_replace( '_', '-', $name );
	$error = $exf_errors[ $name ] ?? '';
	$value = (string) ( $exf_values[ $name ] ?? '' );
	$req   = $o['required'] ? ' required aria-required="true"' : '';
	$aria  = $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : '';
	echo '<div class="pfield' . ( $error ? ' has-error' : '' ) . ( 'date' === $o['type'] ? ' pfield--date' : '' ) . '">';
	if ( 'date' === $o['type'] ) {
		printf( '<div class="pfield-label" id="%s-legend">%s</div>', esc_attr( $id ), esc_html( $label ) );
		echo exf_date_parts_html( $name, $id, $value, $req . $aria, 'bday' === $o['autocomplete'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} elseif ( 'select' === $o['type'] ) {
		printf( '<label class="pfield-label" for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
		printf( '<select id="%1$s" name="%2$s"%3$s%4$s>', esc_attr( $id ), esc_attr( $name ), $req, $aria ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $o['options'] as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, (string) $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	} else {
		printf( '<label class="pfield-label" for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" autocomplete="%5$s"%6$s%7$s>',
			esc_attr( $o['type'] ),
			esc_attr( $id ),
			esc_attr( $name ),
			'password' === $o['type'] ? '' : esc_attr( $value ),
			esc_attr( $o['autocomplete'] ),
			$req, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$aria // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	if ( $error ) {
		printf( '<p class="pfield-error" id="%s-error">%s</p>', esc_attr( $id ), wp_kses( $error, array( 'a' => array( 'href' => array() ) ) ) );
	}
	echo '</div>';
};

get_header();

if ( exf_acf_ready() && get_field( 'sections' ) ) {
	exf_render_sections( get_queried_object_id() );
} else {
	echo '<section class="split"><div class="wrap"><h1 class="split-heading">' . esc_html( get_the_title() ) . '</h1></div></section>';
}
?>
<section class="register-panels">
	<div class="wrap">
		<?php
		if ( function_exists( 'exf_basket_summary' ) ) {
			exf_basket_summary( $exf_basket );
		}
		?>
		<?php if ( $exf_errors ) : ?>
			<div class="notice-box notice-box--error" role="alert">
				<p>
					<?php
					echo isset( $exf_errors['_form'] )
						? wp_kses( $exf_errors['_form'], array( 'a' => array( 'href' => array() ) ) )
						: esc_html__( 'Please check the highlighted details below.', 'exfiles' );
					?>
				</p>
			</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="register-panels-form" novalidate>
			<?php wp_nonce_field( 'exf_register' ); ?>
			<input type="hidden" name="exf_register" value="1">
			<input type="hidden" name="layout" value="panels">

			<div class="register-panels-grid">
				<fieldset class="register-panel">
					<legend class="register-panel-title"><?php esc_html_e( 'Your Details', 'exfiles' ); ?></legend>
					<?php
					$exf_pf( 'first_name', __( 'First Name', 'exfiles' ), array( 'required' => true, 'autocomplete' => 'given-name' ) );
					$exf_pf( 'middle_names', __( 'Middle Name(s) (optional)', 'exfiles' ), array( 'autocomplete' => 'additional-name' ) );
					$exf_pf( 'last_name', __( 'Surname', 'exfiles' ), array( 'required' => true, 'autocomplete' => 'family-name' ) );
					$exf_pf( 'dob', __( 'Date of Birth', 'exfiles' ), array( 'type' => 'date', 'required' => true, 'autocomplete' => 'bday' ) );
					$exf_pf( 'prev_names', __( 'Previous/Maiden Name(s) (optional)', 'exfiles' ) );
					$exf_pf( 'email', __( 'Email Address', 'exfiles' ), array( 'type' => 'email', 'required' => true, 'autocomplete' => 'email' ) );
					$exf_pf( 'phone', __( 'Telephone Number', 'exfiles' ), array( 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel' ) );
					$exf_pf( 'address_full', __( 'Address & Postcode', 'exfiles' ), array( 'required' => true, 'autocomplete' => 'street-address' ) );
					$exf_pf( 'password', __( 'Choose a Password (at least 10 characters)', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password' ) );
					$exf_pf( 'password2', __( 'Confirm Password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password' ) );
					?>
				</fieldset>
				<fieldset class="register-panel">
					<legend class="register-panel-title"><?php esc_html_e( "Partner's Details", 'exfiles' ); ?></legend>
					<?php
					$exf_pf( 'ex_first_name', __( 'First Name', 'exfiles' ), array( 'required' => true ) );
					$exf_pf( 'ex_middle_names', __( 'Middle Name(s) (optional)', 'exfiles' ) );
					$exf_pf( 'ex_last_name', __( 'Surname', 'exfiles' ), array( 'required' => true ) );
					$exf_pf( 'ex_dob', __( 'Date of Birth (if known)', 'exfiles' ), array( 'type' => 'date' ) );
					$exf_pf( 'ex_prev_names', __( 'Previous/Maiden Name(s) (optional)', 'exfiles' ) );
					$exf_pf( 'ex_email', __( 'Email Address (if known)', 'exfiles' ), array( 'type' => 'email' ) );
					$exf_pf( 'ex_phone', __( 'Telephone Number (if known)', 'exfiles' ), array( 'type' => 'tel' ) );
					$exf_pf( 'ex_address_full', __( 'Address & Postcode (if known)', 'exfiles' ) );
					$exf_pf( 'relationship', __( 'Are you married or in a civil partnership?', 'exfiles' ), array( 'type' => 'select', 'required' => true, 'options' => array( '' => __( 'Please choose', 'exfiles' ) ) + exf_relationship_options() ) );
					?>
				</fieldset>
			</div>

			<div class="register-panels-confirm">
				<label class="register-panels-check">
					<input type="checkbox" name="terms" value="1" required <?php checked( ! empty( $exf_values['terms'] ) ); ?>>
					<span>
						<?php
						$exf_privacy = get_privacy_policy_url();
						echo wp_kses(
							$exf_privacy
								/* translators: %s: privacy policy URL */
								? sprintf( __( 'I confirm that the details provided are accurate and I agree to the terms of business and the <a href="%s" target="_blank">privacy notice</a>.', 'exfiles' ), esc_url( $exf_privacy ) )
								: __( 'I confirm that the details provided are accurate and I agree to the terms of business and the privacy notice.', 'exfiles' ),
							array( 'a' => array( 'href' => array(), 'target' => array() ) )
						);
						?>
					</span>
				</label>
				<?php if ( isset( $exf_errors['terms'] ) ) : ?>
					<p class="pfield-error"><?php esc_html_e( 'Please tick to confirm.', 'exfiles' ); ?></p>
				<?php endif; ?>
				<div class="hp-field" aria-hidden="true">
					<label for="exf-p-company">Company</label>
					<input type="text" id="exf-p-company" name="company" tabindex="-1" autocomplete="off">
				</div>
				<button type="submit" class="btn"><?php echo esc_html( $exf_bsvc && ! $exf_bsvc['free'] ? __( 'Agree and Continue to Payment', 'exfiles' ) : __( 'Agree and Submit', 'exfiles' ) ); ?></button>
				<p class="register-panels-login">
					<?php
					/* translators: %s: login URL */
					echo wp_kses( sprintf( __( 'Already have an Ex File? <a href="%s">Log in</a>.', 'exfiles' ), esc_url( exf_page_url( 'login' ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
				</p>
			</div>
		</form>
	</div>
</section>
<?php
get_footer();
