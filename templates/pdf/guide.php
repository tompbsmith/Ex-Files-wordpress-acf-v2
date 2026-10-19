<?php
/**
 * PDF: Free Divorce Guide. Rendered by exf_render_pdf( 'guide', $data ).
 *
 * Layout follows the client's precedent document: title block, date, the two
 * boxes ("The point of this guide", "Important"), the selected paragraphs, then
 * Annex A with the questionnaire answers.
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
	@page { margin: 22mm 20mm 24mm; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.5; color: #31190f; }
	h1, h2, h3 { font-family: "DejaVu Serif", serif; font-weight: normal; color: #31190f; margin: 0; }
	.cover { border-bottom: 1px solid #b19a7a; padding-bottom: 14px; margin-bottom: 18px; }
	.cover table { width: 100%; border-collapse: collapse; }
	.cover td { vertical-align: middle; padding: 0; }
	.logo { height: 34px; }
	.label { text-align: right; text-transform: uppercase; letter-spacing: 2px; font-size: 8.5pt; color: #8a7f6d; }
	h1 { font-size: 24pt; margin: 22px 0 6px; line-height: 1.15; }
	.date { font-size: 9pt; color: #8a7f6d; text-transform: uppercase; letter-spacing: 1.5px; }
	.box { border: 1px solid #b19a7a; background: #f1e9de; padding: 10px 14px; margin-top: 12px; }
	.box h3 { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1.5px; color: #5b4a34; margin-bottom: 4px; }
	.box p { margin: 0; }
	.section { margin-top: 20px; page-break-inside: avoid; }
	.section h2 { font-size: 14pt; margin-bottom: 6px; }
	.section p { margin: 0 0 8px; }
	.section ul, .section ol { margin: 0 0 8px; padding-left: 18px; }
	.section li { margin-bottom: 3px; }
	.annex { page-break-before: always; }
	.annex h2 { font-size: 18pt; margin-bottom: 4px; }
	.annex .sub { color: #8a7f6d; font-size: 9pt; margin-bottom: 14px; }
	.annex table { width: 100%; border-collapse: collapse; }
	.annex th, .annex td { text-align: left; vertical-align: top; padding: 7px 8px; border-bottom: 1px solid #ded4ca; }
	.annex th { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px; color: #5b4a34; border-bottom: 1px solid #b19a7a; }
	.annex td.q { width: 62%; }
	.footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #8a7f6d; border-top: 1px solid #ded4ca; padding-top: 5px; }
	.footer .page:after { content: counter(page); }
</style>
</head>
<body>
	<div class="footer"><?php echo esc_html( $data['footer'] ); ?> | <?php esc_html_e( 'Page', 'exfiles' ); ?> <span class="page"></span></div>

	<div class="cover">
		<table><tr>
			<td><?php if ( $data['logo'] ) : ?><img class="logo" src="<?php echo esc_attr( $data['logo'] ); ?>" alt=""><?php endif; ?></td>
			<td class="label"><?php esc_html_e( 'Divorce guide', 'exfiles' ); ?></td>
		</tr></table>
		<h1><?php echo esc_html( $data['title'] ); ?></h1>
		<div class="date"><?php echo esc_html( $data['date'] ); ?></div>

		<?php if ( $data['point'] ) : ?>
			<div class="box"><h3><?php esc_html_e( 'The point of this guide', 'exfiles' ); ?></h3><p><?php echo esc_html( $data['point'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $data['important'] ) : ?>
			<div class="box"><h3><?php esc_html_e( 'Important', 'exfiles' ); ?></h3><p><?php echo esc_html( $data['important'] ); ?></p></div>
		<?php endif; ?>
	</div>

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
