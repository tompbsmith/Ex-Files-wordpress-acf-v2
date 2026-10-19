<?php
/**
 * PDF rendering with Dompdf.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a PHP template in templates/pdf/ to PDF bytes.
 *
 * @param string $template Template name without .php, e.g. "guide".
 * @param array  $data     Variables for the template (available as $data).
 * @return string|WP_Error PDF bytes.
 */
function exf_render_pdf( $template, array $data ) {
	if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
		return new WP_Error( 'exf_no_dompdf', __( 'The PDF library is missing.', 'exfiles' ) );
	}
	$file = EXF_DIR . '/templates/pdf/' . sanitize_key( $template ) . '.php';
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'exf_no_template', __( 'The PDF template is missing.', 'exfiles' ) );
	}

	ob_start();
	include $file;
	$html = (string) ob_get_clean();

	// Dompdf caches font metrics; keep that out of the theme folder, which a deploy replaces.
	$font_dir = exf_private_dir( 'pdf-fonts' );

	$options = new \Dompdf\Options();
	$options->set( 'defaultFont', 'DejaVu Sans' );
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'isPhpEnabled', false );
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'chroot', array( EXF_DIR ) );
	if ( $font_dir ) {
		$options->set( 'fontCache', $font_dir );
		$options->set( 'tempDir', $font_dir );
	}

	try {
		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		return (string) $dompdf->output();
	} catch ( \Throwable $e ) {
		return new WP_Error( 'exf_pdf_failed', $e->getMessage() );
	}
}

/**
 * A theme image as a data: URI, for PDFs (Dompdf can't fetch remote URLs here).
 *
 * @param string $path Absolute file path.
 */
function exf_pdf_image_data_uri( $path ) {
	if ( ! $path || ! is_readable( $path ) ) {
		return '';
	}
	$type = wp_check_filetype( $path );
	if ( ! $type['type'] || 0 !== strpos( $type['type'], 'image/' ) ) {
		return '';
	}
	return 'data:' . $type['type'] . ';base64,' . base64_encode( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}
