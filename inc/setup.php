<?php
/**
 * Theme supports, menus and assets.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 132,
				'width'       => 400,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		register_nav_menus(
			array(
				'primary' => __( 'Primary menu', 'exfiles' ),
			)
		);
	}
);

// The site is British English (WCAG 3.1.1): screen readers use this to pick the voice.
add_filter(
	'language_attributes',
	function ( $output ) {
		return str_replace( 'lang="en-US"', 'lang="en-GB"', $output );
	}
);

add_filter(
	'document_title_separator',
	function () {
		return '|';
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'exf-fonts',
			'https://fonts.googleapis.com/css2?family=Petrona:wght@400;500;600&family=Jost:wght@300;400;500;600&display=swap',
			array(),
			null
		);

		$css = EXF_DIR . '/assets/css/main.css';
		wp_enqueue_style( 'exf-main', EXF_URI . '/assets/css/main.css', array( 'exf-fonts' ), file_exists( $css ) ? (string) filemtime( $css ) : EXF_VERSION );

		$js = EXF_DIR . '/assets/js/main.js';
		wp_enqueue_script(
			'exf-main',
			EXF_URI . '/assets/js/main.js',
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : EXF_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Motion: GSAP 3.15 + ScrollTrigger, served from the theme (free licence, no CDN).
		$defer = array(
			'in_footer' => true,
			'strategy'  => 'defer',
		);
		wp_enqueue_script( 'gsap', EXF_URI . '/assets/js/vendor/gsap/gsap.min.js', array(), '3.15.0', $defer );
		wp_enqueue_script( 'gsap-scrolltrigger', EXF_URI . '/assets/js/vendor/gsap/ScrollTrigger.min.js', array( 'gsap' ), '3.15.0', $defer );
		$motion = EXF_DIR . '/assets/js/motion.js';
		wp_enqueue_script( 'exf-motion', EXF_URI . '/assets/js/motion.js', array( 'gsap', 'gsap-scrolltrigger' ), file_exists( $motion ) ? (string) filemtime( $motion ) : EXF_VERSION, $defer );

		wp_localize_script(
			'exf-main',
			'exfContact',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'genericError' => __( 'Something went wrong. Please try again or email us directly.', 'exfiles' ),
				'sending'      => __( 'Sending…', 'exfiles' ),
			)
		);
	}
);

// Let the CSS hide scroll-reveal content before first paint (see assets/js/motion.js).
// Skipped for reduced motion; undone after 3s if motion.js never runs.
add_action(
	'wp_head',
	function () {
		echo "<script>(function(d){if(!window.matchMedia||matchMedia('(prefers-reduced-motion: reduce)').matches){return;}d.classList.add('exf-motion');setTimeout(function(){if(!window.exfMotionReady){d.classList.remove('exf-motion');}},3000);})(document.documentElement);</script>\n";
	},
	2
);

// Preconnect to Google Fonts, as the original page did.
add_filter(
	'wp_resource_hints',
	function ( $urls, $relation ) {
		if ( 'preconnect' === $relation ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	},
	10,
	2
);

// Page titles, descriptions, sharing tags and structured data: see inc/seo.php.
