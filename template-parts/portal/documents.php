<?php
/**
 * Portal: documents from us, and documents the client has sent.
 *
 * @package exfiles
 */

$exf_uid = (int) $args['user']->ID;
$exf_multi = count( exf_client_ex_files( $exf_uid ) ) > 1;
?>
<header class="portal-head">
	<p class="eyebrow"><?php esc_html_e( 'My Ex File', 'exfiles' ); ?></p>
	<h1><?php esc_html_e( 'Documents', 'exfiles' ); ?></h1>
	<p class="lead"><?php echo exf_lines( exf_portal_copy( 'documents_intro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
</header>

<?php if ( isset( $_GET['uploaded'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice-box" role="status"><p><?php esc_html_e( 'Thank you. Your document has been sent to us.', 'exfiles' ); ?></p></div>
<?php elseif ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice-box" role="status"><p><?php esc_html_e( 'The document has been removed.', 'exfiles' ); ?></p></div>
<?php endif; ?>

<?php
get_template_part(
	'template-parts/portal/upload-form',
	null,
	array(
		'ex_files' => exf_client_ex_files( $exf_uid ),
		'back'     => 'documents',
	)
);
?>

<h2 class="portal-h2"><?php esc_html_e( 'From The Ex Files', 'exfiles' ); ?></h2>
<?php
get_template_part(
	'template-parts/portal/document-list',
	null,
	array(
		'docs'      => exf_client_documents( $exf_uid, 0, 'firm' ),
		'show_file' => $exf_multi,
		'empty'     => __( "Nothing yet. When we've prepared something for you, it will appear here and we'll email you.", 'exfiles' ),
	)
);
?>

<h2 class="portal-h2"><?php esc_html_e( 'Sent by you', 'exfiles' ); ?></h2>
<?php
get_template_part(
	'template-parts/portal/document-list',
	null,
	array(
		'docs'      => exf_client_documents( $exf_uid, 0, 'client' ),
		'show_file' => $exf_multi,
		'empty'     => __( "You haven't sent us anything yet.", 'exfiles' ),
	)
);
