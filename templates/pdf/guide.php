<?php
/**
 * PDF: Free Divorce Guide. Rendered by exf_render_pdf( 'guide', $data ).
 *
 * Layout follows the client's design (October 2026): cream pages with the logo,
 * "Divorce guide" and a rule at the top of every page; a centred title; the date
 * row; one pink box ("The point of this guide", "Important"); the selected
 * paragraphs, centred under bold headings; then Annex A with the answers.
 *
 * @package exfiles
 *
 * @var array $data
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<style>
	@page { margin: 47mm 20mm 24mm; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; line-height: 1.4; color: #232323; text-align: center; }
	h1, h2, h3 { font-family: "DejaVu Serif", serif; font-weight: bold; color: #232323; margin: 0; }
	/* Cream pages: Dompdf paints the root background over the whole of every page. */
	html { background-color: #f7f4e2; }
	/* Logo, "Divorce guide" and the rule under them, on every page. */
	.head { position: fixed; top: -37mm; left: 0; right: 0; height: 30mm; border-bottom: 1px solid #1f1f1f; }
	.head table { width: 100%; border-collapse: collapse; }
	.head td { vertical-align: middle; padding: 0; }
	.logo { height: 22mm; }
	.label { text-align: right; font-family: "DejaVu Serif", serif; font-weight: bold; text-transform: uppercase; font-size: 10pt; letter-spacing: 0.5px; }
	.footer { position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #6b6b6b; }
	.footer .bar { color: #232323; padding: 0 6px; }
	.footer .num { color: #232323; }
	.footer .page:after { content: counter(page); }
	h1 { font-size: 23pt; text-transform: uppercase; margin: 2mm 0 5mm; line-height: 1.15; }
	.date { width: 100%; border-collapse: collapse; border: 1px solid #d6d1c2; font-size: 9pt; }
	.date td { padding: 5px 8px; text-align: center; }
	.date .k { width: 23%; background: #eae6d8; text-transform: uppercase; }
	.date .v { background: #fbfaf4; }
	.box { background: #f0d9d5; padding: 8px 18px 12px; margin-top: 8mm; }
	.box h3 { font-family: "DejaVu Sans", sans-serif; font-weight: normal; font-size: 9pt; text-transform: uppercase; margin: 6px 0 2px; }
	.box p { margin: 0; }
	.section { margin-top: 12px; }
	.section h2 { font-size: 11.5pt; margin-bottom: 4px; page-break-after: avoid; }
	.section p { margin: 0 0 8px; }
	.section ul, .section ol { margin: 0 0 8px; padding: 0; list-style-position: inside; }
	.section li { margin-bottom: 4px; }
	.annex { page-break-before: always; }
	.annex h2 { font-size: 14pt; margin-bottom: 4px; }
	.annex .sub { color: #6b6b6b; font-size: 8.5pt; margin: 0 0 12px; }
	.annex table { width: 100%; border-collapse: collapse; border: 1px solid #d6d1c2; text-align: left; }
	.annex th, .annex td { vertical-align: top; padding: 6px 9px; border-bottom: 1px solid #d6d1c2; }
	.annex th { text-align: left; background: #eae6d8; font-weight: normal; font-size: 8.5pt; text-transform: uppercase; }
	.annex td { background: #fbfaf4; }
	.annex td.q { width: 62%; border-right: 1px solid #d6d1c2; }
</style>
</head>
<body>
	<div class="head">
		<table><tr>
			<td><?php if ( $data['logo'] ) : ?><img class="logo" src="<?php echo esc_attr( $data['logo'] ); ?>" alt=""><?php endif; ?></td>
			<td class="label"><?php esc_html_e( 'Divorce guide', 'exfiles' ); ?></td>
		</tr></table>
	</div>
	<div class="footer"><?php echo esc_html( $data['footer'] ); ?><span class="bar">|</span><span class="num"><?php esc_html_e( 'Page', 'exfiles' ); ?> <span class="page"></span></span></div>

	<h1><?php echo esc_html( $data['title'] ); ?></h1>
	<table class="date"><tr>
		<td class="k"><?php esc_html_e( 'Date', 'exfiles' ); ?></td>
		<td class="v"><?php echo esc_html( $data['date'] ); ?></td>
	</tr></table>

	<?php if ( $data['point'] || $data['important'] ) : ?>
		<div class="box">
			<?php if ( $data['point'] ) : ?>
				<h3><?php esc_html_e( 'The point of this guide', 'exfiles' ); ?></h3><p><?php echo esc_html( $data['point'] ); ?></p>
			<?php endif; ?>
			<?php if ( $data['important'] ) : ?>
				<h3><?php esc_html_e( 'Important', 'exfiles' ); ?></h3><p><?php echo esc_html( $data['important'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $data['paragraphs'] as $exf_p ) : ?>
		<div class="section">
			<h2><?php echo esc_html( $exf_p['heading'] ); ?></h2>
			<?php echo wp_kses_post( wpautop( $exf_p['body'] ) ); ?>
		</div>
	<?php endforeach; ?>

	<div class="annex">
		<h2><?php esc_html_e( 'Annex A: Your answers', 'exfiles' ); ?></h2>
		<p class="sub">
			<?php
			/* translators: %s: date and time */
			echo esc_html( sprintf( __( 'Completed online on %s. This guide is based on these answers.', 'exfiles' ), $data['completed'] ) );
			?>
		</p>
		<table>
			<thead><tr><th><?php esc_html_e( 'Question', 'exfiles' ); ?></th><th><?php esc_html_e( 'Your answer', 'exfiles' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $data['annex'] as $exf_row ) : ?>
					<tr><td class="q"><?php echo esc_html( $exf_row['question'] ); ?></td><td><?php echo esc_html( $exf_row['answer'] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</body>
</html>
