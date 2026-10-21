<?php
/**
 * Branded HTML emails.
 *
 * Every email the site sends as plain text (the Ex Files emails, the contact
 * form, the Free Divorce Guide, the test email and WordPress's own emails) is
 * wrapped in the site's design on the way out: logo, cream and brown colours,
 * Petrona headings and Jost text. The wording stays plain text in Email Copy;
 * this file only decides how it looks. A plain-text version is sent alongside
 * for email apps that don't show HTML.
 *
 * Emails that are already HTML (for example from a form plugin) are left alone.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Brand colours and fonts, matching assets/css/main.css.
 */
function exf_email_brand() {
	return apply_filters(
		'exf_email_brand',
		array(
			'page'       => '#ded4ca', // --cream-dark: the area around the email.
			'card'       => '#f1e9de', // --cream: the email itself.
			'text'       => '#31190f', // --brown-dark.
			'muted'      => '#8a7f6d', // --muted.
			'rule'       => '#b19a7a', // --tan.
			'button'     => '#5a4e38', // --brown-btn.
			'button_ink' => '#f1e9de',
			'heading'    => "'Petrona', Georgia, 'Times New Roman', serif",
			'body'       => "'Jost', 'Helvetica Neue', Helvetica, Arial, sans-serif",
		)
	);
}

/**
 * Absolute URL of the logo for emails: the site logo, else the bundled one.
 */
function exf_email_logo_url() {
	$src = '';
	if ( get_theme_mod( 'custom_logo' ) ) {
		$src = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
	}
	return $src ? $src : EXF_URI . '/assets/img/logo.png';
}

/**
 * Footer lines: the Email Copy footer if set, else the website footer's tagline and email.
 *
 * @return string Plain text, one item per line.
 */
function exf_email_footer_text() {
	$custom = function_exists( 'exf_get_option_field' ) && defined( 'EXF_EMAILS_ID' ) ? trim( (string) exf_get_option_field( 'email_footer', EXF_EMAILS_ID, '' ) ) : '';
	if ( '' !== $custom ) {
		return $custom;
	}
	$tagline = function_exists( 'exf_option' ) ? exf_option( 'footer_tagline', 'Making separation feel less alien' ) : 'Making separation feel less alien';
	$email   = function_exists( 'exf_option' ) ? exf_option( 'contact_email', 'info@theexfiles.co.uk' ) : 'info@theexfiles.co.uk';
	return trim( $tagline . "\n" . $email );
}

/**
 * Button label for a link that sits on its own line, from where it goes.
 *
 * @param string $url Link.
 */
function exf_email_button_label( $url ) {
	$rules = array(
		'verify='              => __( 'Confirm my email', 'exfiles' ),
		'confirm_email='       => __( 'Confirm my new email', 'exfiles' ),
		'action=rp'            => __( 'Choose a new password', 'exfiles' ),
		'action=resetpass'     => __( 'Choose a new password', 'exfiles' ),
		'/questionnaire/'      => __( 'Open your questionnaire', 'exfiles' ),
		'/my-ex-file/documents' => __( 'View your documents', 'exfiles' ),
		'/my-ex-file/ex-files' => __( 'Open your Ex File', 'exfiles' ),
		'/my-ex-file/buy'      => __( 'Choose your service', 'exfiles' ),
		'/my-ex-file'          => __( 'Go to My Ex File', 'exfiles' ),
		'exf_guide_download'   => __( 'Download your guide', 'exfiles' ),
		'/wp-admin/'           => __( 'Open in the admin', 'exfiles' ),
		'confirmaction'        => __( 'Confirm request', 'exfiles' ),
	);
	foreach ( $rules as $needle => $label ) {
		if ( false !== strpos( $url, $needle ) ) {
			return apply_filters( 'exf_email_button_label', $label, $url );
		}
	}
	return apply_filters( 'exf_email_button_label', __( 'Open link', 'exfiles' ), $url );
}

/**
 * Plain text → email-safe HTML body content.
 *
 * Blank lines make paragraphs; a link on its own line becomes a button; lines
 * starting "- " become a list; other links are clickable.
 *
 * @param string $text Plain text.
 */
function exf_email_text_to_html( $text ) {
	$b      = exf_email_brand();
	$text   = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $text ) );
	$blocks = preg_split( "/\n[ \t]*\n/", $text );
	$p      = 'margin:0 0 18px 0;font-family:' . $b['body'] . ';font-size:16px;line-height:1.6;color:' . $b['text'] . ';';
	$out    = '';

	$linkify = function ( $line ) use ( $b ) {
		$label = '';
		// "Service: Know Your Position" style lines: make the label stand out.
		if ( preg_match( '/^([A-Z][A-Za-z ]{1,24}):\s+(\S.*)$/', $line, $lm ) ) {
			$label = '<strong style="font-weight:500;">' . esc_html( $lm[1] ) . ':</strong> ';
			$line  = $lm[2];
		}
		$safe = esc_html( $line );
		$safe = preg_replace_callback(
			'#\bhttps?://[^\s<]+#',
			function ( $m ) use ( $b ) {
				$url = rtrim( html_entity_decode( $m[0] ), '.,);' );
				$rest = substr( html_entity_decode( $m[0] ), strlen( $url ) );
				return '<a href="' . esc_url( $url ) . '" style="color:' . $b['text'] . ';text-decoration:underline;word-break:break-all;">' . esc_html( $url ) . '</a>' . esc_html( $rest );
			},
			$safe
		);
		// Email addresses, but only in text that isn't already a link.
		$parts  = preg_split( '#(<a\b[^>]*>.*?</a>)#s', $safe, -1, PREG_SPLIT_DELIM_CAPTURE );
		$result = '';
		foreach ( $parts as $part ) {
			if ( 0 === strpos( $part, '<a ' ) ) {
				$result .= $part;
				continue;
			}
			$result .= preg_replace_callback(
				'/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
				function ( $m ) use ( $b ) {
					return '<a href="mailto:' . esc_attr( $m[0] ) . '" style="color:' . $b['text'] . ';text-decoration:underline;">' . $m[0] . '</a>';
				},
				$part
			);
		}
		return $label . $result;
	};

	foreach ( $blocks as $block ) {
		$lines = array_values( array_filter( array_map( 'rtrim', explode( "\n", $block ) ), 'strlen' ) );
		if ( ! $lines ) {
			continue;
		}
		$buffer = array();
		$list   = array();
		$flush  = function () use ( &$buffer, &$out, $p ) {
			if ( $buffer ) {
				$out   .= '<p style="' . $p . '">' . implode( '<br>', $buffer ) . '</p>';
				$buffer = array();
			}
		};
		$flush_list = function () use ( &$list, &$out, $b ) {
			if ( $list ) {
				$out .= '<ul style="margin:0 0 18px 0;padding:0 0 0 22px;font-family:' . $b['body'] . ';font-size:16px;line-height:1.6;color:' . $b['text'] . ';">';
				foreach ( $list as $li ) {
					$out .= '<li style="margin:0 0 4px 0;">' . $li . '</li>';
				}
				$out .= '</ul>';
				$list = array();
			}
		};
		foreach ( $lines as $line ) {
			$trim = trim( $line );
			if ( preg_match( '#^https?://\S+$#', $trim ) ) {
				$flush();
				$flush_list();
				$out .= exf_email_button( $trim, exf_email_button_label( $trim ) );
				continue;
			}
			if ( preg_match( '/^[-•]\s+(.+)$/u', $trim, $m ) ) {
				$flush();
				$list[] = $linkify( $m[1] );
				continue;
			}
			$flush_list();
			$buffer[] = $linkify( $line );
		}
		$flush();
		$flush_list();
	}
	return $out;
}

/**
 * A "bulletproof" button that works in Outlook too, with the link written out underneath.
 *
 * @param string $url   Link.
 * @param string $label Button text.
 */
function exf_email_button( $url, $label ) {
	$b   = exf_email_brand();
	$url = esc_url( $url );
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 10px 0;"><tr>'
		. '<td bgcolor="' . $b['button'] . '" style="background:' . $b['button'] . ';border-radius:2px;">'
		. '<a href="' . $url . '" style="display:inline-block;padding:14px 28px;font-family:' . $b['heading'] . ';font-size:18px;letter-spacing:.03em;color:' . $b['button_ink'] . ';text-decoration:none;">' . esc_html( $label ) . '</a>'
		. '</td></tr></table>'
		. '<p style="margin:0 0 22px 0;font-family:' . $b['body'] . ';font-size:12px;line-height:1.5;color:' . $b['muted'] . ';word-break:break-all;">'
		. esc_html__( 'Or copy this link into your browser:', 'exfiles' ) . '<br><a href="' . $url . '" style="color:' . $b['muted'] . ';text-decoration:underline;">' . esc_html( $url ) . '</a></p>';
}

/**
 * The full branded HTML email.
 *
 * @param string $subject Subject (shown as the heading).
 * @param string $text    Plain-text body.
 */
function exf_email_html( $subject, $text ) {
	$b        = exf_email_brand();
	$name     = function_exists( 'exf_site_name' ) ? exf_site_name() : get_bloginfo( 'name' );
	$home     = home_url( '/' );
	$preheader = '';
	foreach ( preg_split( "/\n+/", trim( (string) $text ) ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line && ! preg_match( '/^(hello|hi|dear)\b/i', $line ) && ! preg_match( '#^https?://#', $line ) ) {
			$preheader = wp_html_excerpt( $line, 110, '…' );
			break;
		}
	}
	$footer = array();
	foreach ( preg_split( "/\n+/", exf_email_footer_text() ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$footer[] = is_email( $line )
			? '<a href="mailto:' . esc_attr( $line ) . '" style="color:' . $b['muted'] . ';text-decoration:none;">' . esc_html( $line ) . '</a>'
			: esc_html( $line );
	}

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en-GB" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title><?php echo esc_html( $subject ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Petrona:wght@400;500&amp;family=Jost:wght@400;500&amp;display=swap" rel="stylesheet">
<style>
	body { margin:0; padding:0; -webkit-text-size-adjust:100%; }
	a { color:<?php echo esc_attr( $b['text'] ); ?>; }
	@media (max-width:620px) {
		.exf-card { padding:28px 22px !important; }
		.exf-h1 { font-size:24px !important; }
	}
</style>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $b['page'] ); ?>;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;"><?php echo esc_html( $preheader ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $b['page'] ); ?>" style="background:<?php echo esc_attr( $b['page'] ); ?>;">
<tr><td align="center" style="padding:32px 12px;">
	<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
		<tr><td align="center" style="padding:0 0 24px 0;">
			<a href="<?php echo esc_url( $home ); ?>" style="text-decoration:none;"><img src="<?php echo esc_url( exf_email_logo_url() ); ?>" width="170" alt="<?php echo esc_attr( $name ); ?>" style="display:block;width:170px;max-width:60%;height:auto;border:0;font-family:<?php echo esc_attr( $b['heading'] ); ?>;font-size:26px;color:<?php echo esc_attr( $b['text'] ); ?>;"></a>
		</td></tr>
		<tr><td class="exf-card" bgcolor="<?php echo esc_attr( $b['card'] ); ?>" style="background:<?php echo esc_attr( $b['card'] ); ?>;padding:40px 44px;border-top:3px solid <?php echo esc_attr( $b['button'] ); ?>;">
			<h1 class="exf-h1" style="margin:0 0 24px 0;font-family:<?php echo esc_attr( $b['heading'] ); ?>;font-weight:400;font-size:28px;line-height:1.2;color:<?php echo esc_attr( $b['text'] ); ?>;"><?php echo esc_html( $subject ); ?></h1>
			<?php echo exf_email_text_to_html( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
		</td></tr>
		<?php if ( $footer ) : ?>
		<tr><td align="center" style="padding:24px 20px 8px 20px;font-family:<?php echo esc_attr( $b['body'] ); ?>;font-size:12px;line-height:1.8;letter-spacing:.12em;text-transform:uppercase;color:<?php echo esc_attr( $b['muted'] ); ?>;">
			<?php echo implode( '<br>', $footer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
		</td></tr>
		<?php endif; ?>
	</table>
</td></tr>
</table>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/*
 * Wrap plain-text emails on their way out. The plain text is kept and sent as the
 * alternative part (see phpmailer_init below).
 */
$GLOBALS['exf_email_alt'] = null;

add_filter(
	'wp_mail',
	function ( $args ) {
		$GLOBALS['exf_email_alt'] = null;
		if ( ! apply_filters( 'exf_email_html_enabled', true, $args ) ) {
			return $args;
		}
		$headers = $args['headers'] ?? array();
		$list    = is_array( $headers ) ? $headers : preg_split( "/\r?\n/", (string) $headers );
		$list    = array_values( array_filter( array_map( 'trim', (array) $list ), 'strlen' ) );
		foreach ( $list as $h ) {
			if ( preg_match( '/^content-type:\s*text\/html/i', $h ) ) {
				return $args; // Already HTML.
			}
		}
		$message = (string) ( $args['message'] ?? '' );
		if ( preg_match( '/<(html|body|table|p|div|br)\b/i', $message ) ) {
			return $args; // Looks like HTML already.
		}
		$list   = array_values(
			array_filter(
				$list,
				function ( $h ) {
					return ! preg_match( '/^content-type:/i', $h );
				}
			)
		);
		$list[] = 'Content-Type: text/html; charset=UTF-8';

		$GLOBALS['exf_email_alt'] = $message;
		$args['headers']          = $list;
		$args['message']          = exf_email_html( wp_specialchars_decode( (string) ( $args['subject'] ?? '' ), ENT_QUOTES ), $message );
		return $args;
	},
	99
);

add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		if ( null !== $GLOBALS['exf_email_alt'] ) {
			$phpmailer->AltBody          = $GLOBALS['exf_email_alt']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$GLOBALS['exf_email_alt'] = null;
		}
	},
	99
);
