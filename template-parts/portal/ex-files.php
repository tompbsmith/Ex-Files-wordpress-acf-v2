<?php
/**
 * Portal: all Ex Files.
 *
 * @package exfiles
 */

$exf_uid   = (int) $args['user']->ID;
$exf_files = exf_client_ex_files( $exf_uid );
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1><?php esc_html_e( 'Ex Files', 'exfiles' ); ?></h1>
	<p class="lead"><?php esc_html_e( 'Each separation has its own Ex File, with its own services and documents.', 'exfiles' ); ?></p>
</header>
<div class="portal-files">
	<?php foreach ( $exf_files as $exf_id ) : ?>
		<?php get_template_part( 'template-parts/portal/file-card', null, array( 'ex_file' => $exf_id ) ); ?>
	<?php endforeach; ?>
</div>
<?php if ( EXF_STATUS_ACTIVE === exf_user_status( $exf_uid ) ) : ?>
	<p class="account-new-sep"><a href="<?php echo esc_url( add_query_arg( 'new_separation', 1, exf_page_url( 'buy' ) ) ); ?>"><?php esc_html_e( '+ Open an Ex File for a different separation', 'exfiles' ); ?></a></p>
<?php endif; ?>
