<?php
/**
 * Template Name: Questionnaire preview (staff)
 *
 * Every section of the full service questionnaire on one page, so the design can be
 * checked: light (as in the portal) or dark (the Wix dark brown panels). Each gate
 * question is answered "Yes" so the children, properties, investments and other
 * sections show in full. Nothing is saved. Staff only.
 *
 * @package exfiles
 */

if ( ! is_user_logged_in() ) {
	auth_redirect();
}
if ( ! exf_user_can_manage() ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exf_style = ( isset( $_GET['style'] ) && 'light' === sanitize_key( wp_unslash( $_GET['style'] ) ) ) ? 'light' : 'dark';
$exf_secs  = exf_q_sections();

// Answer each gate "Yes", and the "About you" questions that open parts of
// "Agreements and offers", so every section shows its questions.
$exf_data = array(
	'about' => array(
		'agreement_reached' => 'Yes',
		'offer_received'    => 'Yes',
		'offer_to_make'     => 'Yes',
	),
);
foreach ( $exf_secs as $exf_k => $exf_s ) {
	if ( ! empty( $exf_s['gate'] ) ) {
		$exf_data[ $exf_k ]['_gate'] = 'Yes';
	}
}

get_header();
?>
<section class="qp qp--<?php echo esc_attr( $exf_style ); ?>">
	<div class="wrap qp-wrap">
		<header class="qp-head">
			<p class="eyebrow"><?php esc_html_e( 'Staff preview: nothing on this page is saved', 'exfiles' ); ?></p>
			<h1><?php esc_html_e( 'Your financial questionnaire', 'exfiles' ); ?></h1>
			<p class="qp-lead"><?php esc_html_e( 'Every section of the questionnaire, with each "Do you have any…?" question answered Yes so all the questions show. Other answers still open and close questions as they do for clients, and "Add another" works.', 'exfiles' ); ?></p>
			<div class="qp-switch" role="group" aria-label="<?php esc_attr_e( 'Style', 'exfiles' ); ?>">
				<a href="<?php echo esc_url( add_query_arg( 'style', 'light' ) ); ?>"<?php echo 'light' === $exf_style ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'Light (current)', 'exfiles' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'style', 'dark' ) ); ?>"<?php echo 'dark' === $exf_style ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'Dark (Wix)', 'exfiles' ); ?></a>
			</div>
			<nav class="qp-jump" aria-label="<?php esc_attr_e( 'Sections', 'exfiles' ); ?>">
				<ol>
					<?php foreach ( array_keys( $exf_secs ) as $exf_i => $exf_k ) : ?>
						<li><a href="#qp-<?php echo esc_attr( $exf_k ); ?>"><span><?php echo (int) ( $exf_i + 1 ); ?></span> <?php echo esc_html( $exf_secs[ $exf_k ]['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ol>
			</nav>
		</header>

		<?php $exf_n = 0; ?>
		<?php foreach ( $exf_secs as $exf_k => $exf_sec ) : ?>
			<?php ++$exf_n; ?>
			<form class="guide-form portal-form q-form qp-section" id="qp-<?php echo esc_attr( $exf_k ); ?>" data-questionnaire data-preview novalidate>
				<p class="eyebrow">
					<?php
					/* translators: 1: step number, 2: number of steps */
					echo esc_html( sprintf( __( 'Section %1$d of %2$d', 'exfiles' ), $exf_n, count( $exf_secs ) ) );
					?>
				</p>
				<h2 class="q-title"><?php echo esc_html( $exf_sec['title'] ); ?></h2>
				<?php if ( ! empty( $exf_sec['intro'] ) ) : ?>
					<p class="q-intro"><?php echo esc_html( $exf_sec['intro'] ); ?></p>
				<?php endif; ?>
				<?php exf_q_render_section( $exf_k, $exf_data, array() ); ?>
				<div class="guide-nav q-nav">
					<button type="button" class="btn q-next"><?php esc_html_e( 'Next', 'exfiles' ); ?></button>
					<?php if ( $exf_n > 1 ) : ?>
						<button type="button" class="btn outline q-back"><?php esc_html_e( 'Back', 'exfiles' ); ?></button>
					<?php endif; ?>
					<button type="button" class="link-button q-save"><?php esc_html_e( 'Save and finish later', 'exfiles' ); ?></button>
				</div>
			</form>
		<?php endforeach; ?>
	</div>
</section>
<?php
get_footer();
