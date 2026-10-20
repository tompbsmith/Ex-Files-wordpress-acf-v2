<?php
/**
 * PDF: a copy of the client's questionnaire answers. Rendered by
 * exf_render_pdf( 'questionnaire', $data ).
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
	@page { margin: 20mm 18mm 22mm; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.45; color: #31190f; }
	h1, h2, h3 { font-family: "DejaVu Serif", serif; font-weight: normal; margin: 0; }
	.cover { border-bottom: 1px solid #b19a7a; padding-bottom: 12px; margin-bottom: 8px; }
	.cover table { width: 100%; border-collapse: collapse; }
	.logo { height: 32px; }
	.label { text-align: right; text-transform: uppercase; letter-spacing: 2px; font-size: 8pt; color: #8a7f6d; }
	h1 { font-size: 20pt; margin: 18px 0 4px; }
	.meta { font-size: 8.5pt; color: #8a7f6d; }
	.key { margin-top: 10px; font-size: 8pt; color: #5b4a34; }
	.q-sum { margin-top: 16px; }
	.q-sum-head h3 { font-size: 13pt; border-bottom: 1px solid #b19a7a; padding-bottom: 3px; margin-bottom: 4px; }
	.q-sum-head a { display: none; }
	table.q-sum-table { width: 100%; border-collapse: collapse; }
	.q-sum-table th, .q-sum-table td { text-align: left; vertical-align: top; padding: 4px 6px; border-bottom: 1px solid #ece4da; }
	.q-sum-table th { width: 48%; font-weight: normal; color: #5b4a34; }
	.q-sum-sub th { font-weight: bold; color: #31190f; background: #f4eee6; }
	.q-sum-total th, .q-sum-total td { font-weight: bold; }
	.q-flag--estimate { background: #fbefd5; padding: 0 3px; }
	.q-flag--dont_know { background: #f3dcd6; padding: 0 3px; }
	table.q-out-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
	.q-out-table th, .q-out-table td { padding: 3px 4px; border-bottom: 1px solid #ece4da; text-align: left; }
	.q-sum-empty { color: #8a7f6d; }
	.declaration { margin-top: 18px; padding: 8px 10px; border: 1px solid #b19a7a; background: #f1e9de; font-size: 8.5pt; }
	.footer { position: fixed; bottom: -13mm; left: 0; right: 0; font-size: 7.5pt; color: #8a7f6d; border-top: 1px solid #ded4ca; padding-top: 4px; }
	.footer .page:after { content: counter(page); }
</style>
</head>
<body>
	<div class="footer"><?php echo esc_html( $data['footer'] ); ?> | <?php esc_html_e( 'Page', 'exfiles' ); ?> <span class="page"></span></div>
	<div class="cover">
		<table><tr>
			<td><?php if ( $data['logo'] ) : ?><img class="logo" src="<?php echo esc_attr( $data['logo'] ); ?>" alt=""><?php endif; ?></td>
			<td class="label"><?php esc_html_e( 'Financial questionnaire', 'exfiles' ); ?></td>
		</tr></table>
		<h1><?php echo esc_html( $data['title'] ); ?></h1>
		<div class="meta"><?php echo esc_html( $data['meta'] ); ?></div>
		<div class="key"><span class="q-flag--estimate"><?php esc_html_e( 'Highlighted in yellow', 'exfiles' ); ?></span> <?php esc_html_e( 'are estimates;', 'exfiles' ); ?> <span class="q-flag--dont_know"><?php esc_html_e( 'highlighted in red', 'exfiles' ); ?></span> <?php esc_html_e( "are answers the client doesn't know.", 'exfiles' ); ?></div>
	</div>
	<?php echo $data['summary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in exf_q_summary_html(). ?>
	<div class="declaration"><?php echo esc_html( $data['declaration'] ); ?></div>
</body>
</html>
