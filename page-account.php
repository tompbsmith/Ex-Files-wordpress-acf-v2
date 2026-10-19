<?php
/**
 * Template Name: My Ex File (account)
 *
 * The client portal. One WordPress page with sections as rewrite endpoints
 * (see inc/core/portal/portal.php); each section is a file in template-parts/portal/.
 *
 * @package exfiles
 */

$exf_user     = wp_get_current_user();
$exf_uid      = (int) $exf_user->ID;
$exf_is_staff = current_user_can( EXF_CAP );
list( $exf_section, $exf_value ) = exf_portal_current();

// One Ex File: only the client's own.
if ( 'ex-files' === $exf_section && '' !== $exf_value && ! exf_portal_owns_ex_file( absint( $exf_value ) ) ) {
	$exf_section = 'not-found';
}

get_header();
?>
<section class="guide-form-section account-section portal-section">
	<div class="wrap portal-wrap">

		<?php if ( ! exf_is_client( $exf_uid ) ) : ?>
			<header class="guide-form-head">
				<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
				<h1><?php esc_html_e( 'Your Ex File', 'exfiles' ); ?></h1>
			</header>
			<div class="guide-card account-card">
				<p>
					<?php
					echo $exf_is_staff
						? esc_html__( "You're logged in as staff, so you don't have an Ex File. Client accounts are managed under Ex Files in the admin.", 'exfiles' )
						: esc_html__( "You don't have an Ex File yet.", 'exfiles' );
					?>
				</p>
				<?php if ( ! $exf_is_staff ) : ?>
					<p style="margin-top:20px"><a class="btn" href="<?php echo esc_url( exf_page_url( 'register' ) ); ?>"><?php esc_html_e( 'Open your Ex File', 'exfiles' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php else : ?>

			<div class="portal">
				<nav class="portal-nav" aria-label="<?php esc_attr_e( 'My Ex File', 'exfiles' ); ?>">
					<p class="portal-nav-name">
						<?php echo esc_html( trim( $exf_user->first_name . ' ' . $exf_user->last_name ) ); ?>
					</p>
					<ul>
						<?php foreach ( exf_portal_sections() as $exf_slug => $exf_label ) : ?>
							<?php
							$exf_current = $exf_slug === $exf_section || ( 'not-found' === $exf_section && 'ex-files' === $exf_slug );
							$exf_badge   = '';
							if ( 'documents' === $exf_slug ) {
								$exf_new   = count( exf_client_new_documents( $exf_uid ) );
								$exf_badge = $exf_new ? '<span class="portal-badge">' . (int) $exf_new . '</span>' : '';
							}
							?>
							<li><a href="<?php echo esc_url( exf_portal_url( $exf_slug ) ); ?>"<?php echo $exf_current ? ' class="active" aria-current="page"' : ''; ?>><?php echo esc_html( $exf_label ) . $exf_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
						<?php endforeach; ?>
						<li class="portal-nav-logout"><a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Log out', 'exfiles' ); ?></a></li>
					</ul>
				</nav>

				<div class="portal-main">
					<?php
					if ( 'not-found' === $exf_section ) {
						echo '<div class="notice-box notice-box--error" role="alert"><p>' . esc_html__( "We couldn't find that Ex File.", 'exfiles' ) . '</p></div>';
						exf_portal_part( 'ex-files', array( 'user' => $exf_user ) );
					} elseif ( 'ex-files' === $exf_section && '' !== $exf_value ) {
						exf_portal_part( 'ex-file', array( 'user' => $exf_user, 'ex_file' => absint( $exf_value ) ) );
					} else {
						exf_portal_part( $exf_section, array( 'user' => $exf_user ) );
					}
					?>
				</div>
			</div>

		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
