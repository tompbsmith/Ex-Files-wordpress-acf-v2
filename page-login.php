<?php
/**
 * Template Name: Log in
 *
 * Log in, forgotten password and choose a new password. Handling is in
 * inc/core/portal/login.php.
 *
 * @package exfiles
 */

$exf_state  = exf_login_state();
$exf_view   = $exf_state['view'];
$exf_errors = $exf_state['errors'];
$exf_values = $exf_state['values'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
$exf_titles   = array(
	'login'        => __( 'Log in to your Ex File', 'exfiles' ),
	'lostpassword' => __( 'Forgotten your password?', 'exfiles' ),
	'resetpass'    => __( 'Choose a new password', 'exfiles' ),
);

get_header();
?>
<section class="guide-form-section login-section">
	<div class="wrap guide-form-wrap login-wrap">
		<header class="guide-form-head">
			<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
			<h1><?php echo esc_html( $exf_titles[ $exf_view ] ); ?></h1>
			<?php if ( 'lostpassword' === $exf_view && ! $exf_state['notice'] ) : ?>
				<p class="lead"><?php esc_html_e( "Enter your email address and we'll send you a link to choose a new one.", 'exfiles' ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $exf_state['notice'] ) : ?>
			<div class="notice-box" role="status"><p><?php echo esc_html( $exf_state['notice'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $exf_errors['_form'] ) ) : ?>
			<div class="notice-box notice-box--error" role="alert"><p><?php echo wp_kses( $exf_errors['_form'], array( 'a' => array( 'href' => array() ) ) ); ?></p></div>
		<?php endif; ?>

		<?php if ( 'login' === $exf_view ) : ?>
			<form method="post" class="guide-form login-form" action="<?php echo esc_url( exf_page_url( 'login' ) ); ?>">
				<?php wp_nonce_field( 'exf_login' ); ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $exf_redirect ); ?>">
				<?php
				exf_form_field( 'log', __( 'Email address', 'exfiles' ), array( 'type' => 'text', 'required' => true, 'autocomplete' => 'username', 'values' => $exf_values, 'attrs' => ' autocapitalize="off" spellcheck="false"' ) );
				exf_form_field( 'pwd', __( 'Password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password' ) );
				?>
				<label class="gq-option gq-option--check"><input type="checkbox" name="rememberme" value="1"><span><?php esc_html_e( 'Keep me logged in on this device', 'exfiles' ); ?></span></label>
				<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Log in', 'exfiles' ); ?></button></div>
				<p class="login-links">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgotten your password?', 'exfiles' ); ?></a>
					&nbsp;·&nbsp;
					<a href="<?php echo esc_url( exf_page_url( 'register' ) ); ?>"><?php esc_html_e( 'New here? Open your Ex File', 'exfiles' ); ?></a>
				</p>
			</form>

		<?php elseif ( 'lostpassword' === $exf_view ) : ?>
			<?php if ( ! $exf_state['notice'] ) : ?>
				<form method="post" class="guide-form login-form" action="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', exf_page_url( 'login' ) ) ); ?>">
					<?php wp_nonce_field( 'exf_lostpassword' ); ?>
					<?php exf_form_field( 'user_login', __( 'Email address', 'exfiles' ), array( 'type' => 'email', 'required' => true, 'autocomplete' => 'email' ) ); ?>
					<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Send me a link', 'exfiles' ); ?></button></div>
				</form>
			<?php endif; ?>
			<p class="login-links"><a href="<?php echo esc_url( exf_page_url( 'login' ) ); ?>"><?php esc_html_e( 'Back to log in', 'exfiles' ); ?></a></p>

		<?php else : ?>
			<form method="post" class="guide-form login-form" action="<?php echo esc_url( add_query_arg( 'action', 'resetpass', exf_page_url( 'login' ) ) ); ?>">
				<?php wp_nonce_field( 'exf_resetpass' ); ?>
				<input type="hidden" name="key" value="<?php echo esc_attr( $exf_values['key'] ); ?>">
				<input type="hidden" name="login" value="<?php echo esc_attr( $exf_values['login'] ); ?>">
				<?php
				exf_form_field( 'new_password', __( 'New password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'errors' => $exf_errors, 'note' => __( 'At least 10 characters.', 'exfiles' ) ) );
				exf_form_field( 'new_password_confirm', __( 'Confirm new password', 'exfiles' ), array( 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password' ) );
				?>
				<div class="guide-nav"><button type="submit" class="btn"><?php esc_html_e( 'Save new password', 'exfiles' ); ?></button></div>
			</form>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
