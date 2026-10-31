<?php
/**
 * SEO: page titles, meta descriptions, social sharing tags (Open Graph) and
 * structured data (JSON-LD) for Google.
 *
 * Each page and post has a "Search & social" box (acf-json/group_exf_seo.json):
 * title, description, sharing image and "hide from search engines". Site-wide
 * settings are in Site Settings → SEO (acf-json/group_exf_site_settings.json).
 *
 * Everything here switches itself off if an SEO plugin (Yoast, Rank Math, AIOSEO,
 * SEOPress) is active, so the two never output duplicate tags.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the theme should output SEO tags (no SEO plugin is active).
 */
function exf_seo_enabled() {
	$plugin = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	return ! $plugin && function_exists( 'get_field' );
}

/**
 * The page or post being viewed, or 0 (archives, search, 404).
 */
function exf_seo_post_id() {
	if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
		return (int) get_option( 'page_on_front' );
	}
	return is_singular() ? (int) get_queried_object_id() : 0;
}

/**
 * The business name used in titles and sharing tags.
 */
function exf_seo_site_name() {
	$name = trim( (string) exf_option( 'seo_site_name' ) );
	return '' !== $name ? $name : exf_site_name();
}

/**
 * The page's title for search results and sharing.
 *
 * @param int $id Post ID.
 */
function exf_seo_title( $id ) {
	$custom = $id ? trim( (string) get_field( 'seo_title', $id ) ) : '';
	if ( '' !== $custom ) {
		return $custom;
	}
	return wp_get_document_title();
}

/**
 * The page's description: its own, then sensible fallbacks.
 *
 * @param int $id Post ID.
 */
function exf_seo_description( $id ) {
	$text = $id ? (string) get_field( 'seo_description', $id ) : '';
	if ( '' === trim( $text ) && is_front_page() ) {
		$text = (string) exf_option( 'meta_description' );
	}
	if ( '' === trim( $text ) && $id && 'page-service.php' === get_page_template_slug( $id ) ) {
		$text = (string) get_field( 'intro', $id ); // A service's short description.
	}
	if ( '' === trim( $text ) && $id && has_excerpt( $id ) ) {
		$text = get_the_excerpt( $id );
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	return mb_strlen( $text ) > 170 ? rtrim( mb_substr( $text, 0, 157 ) ) . '…' : $text;
}

/**
 * The sharing image: the page's own, then the site default. Returns array( url, width, height, alt ) or null.
 *
 * @param int $id Post ID.
 */
function exf_seo_image( $id ) {
	$img = $id ? get_field( 'seo_image', $id ) : null;
	if ( empty( $img['url'] ) ) {
		$img = exf_option( 'seo_default_image' );
	}
	if ( empty( $img['url'] ) ) {
		return null;
	}
	return array(
		'url'    => $img['url'],
		'width'  => (int) ( $img['width'] ?? 0 ),
		'height' => (int) ( $img['height'] ?? 0 ),
		'alt'    => (string) ( $img['alt'] ?? '' ),
	);
}

/**
 * Whether search engines are told not to list this page.
 *
 * @param int $id Post ID.
 */
function exf_seo_is_noindex( $id ) {
	if ( $id && get_field( 'seo_noindex', $id ) ) {
		return true;
	}
	// Pages the theme always hides (account, basket, log-in…). The site-wide "discourage
	// search engines" setting is left out so staging shows the same output as live.
	$site_wide = array( 'wp_robots_noindex', 'wp_robots_no_robots' );
	$had       = array();
	foreach ( $site_wide as $fn ) {
		$had[ $fn ] = has_filter( 'wp_robots', $fn );
		remove_filter( 'wp_robots', $fn, (int) $had[ $fn ] );
	}
	$all = apply_filters( 'wp_robots', array() );
	foreach ( $had as $fn => $priority ) {
		if ( false !== $priority ) {
			add_filter( 'wp_robots', $fn, $priority );
		}
	}
	return ! empty( $all['noindex'] );
}

// Title: a page's own SEO title replaces the whole <title>; otherwise "Page | Business name".
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( ! exf_seo_enabled() || is_admin() ) {
			return $title;
		}
		$id     = exf_seo_post_id();
		$custom = $id ? trim( (string) get_field( 'seo_title', $id ) ) : '';
		return '' !== $custom ? $custom : $title;
	},
	20
);
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( ! exf_seo_enabled() ) {
			return $parts;
		}
		if ( isset( $parts['site'] ) ) {
			$parts['site'] = exf_seo_site_name();
		}
		if ( is_front_page() ) {
			$parts['title'] = exf_seo_site_name();
		}
		return $parts;
	}
);

// "Hide from search engines" tick-box.
add_filter(
	'wp_robots',
	function ( $robots ) {
		// The basket and internal test/preview pages are never listed.
		$always = is_page_template( array( 'page-buy.php', 'page-questionnaire-preview.php' ) )
			|| ( is_page() && in_array( get_queried_object_id(), array( (int) get_option( 'exf_section_test_page' ), (int) get_option( 'exf_questionnaire_preview_page' ) ), true ) );
		if ( $always ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'] );
			return $robots;
		}
		$id = exf_seo_enabled() ? exf_seo_post_id() : 0;
		if ( $id && get_field( 'seo_noindex', $id ) ) {
			$robots['noindex']  = true;
			$robots['follow']   = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

// Description, sharing tags and Search Console verification.
add_action(
	'wp_head',
	function () {
		if ( ! exf_seo_enabled() || is_404() || is_search() ) {
			return;
		}
		$id    = exf_seo_post_id();
		$title = exf_seo_title( $id );
		$desc  = exf_seo_description( $id );
		$image = exf_seo_image( $id );
		$url   = $id ? get_permalink( $id ) : home_url( add_query_arg( array() ) );
		if ( is_front_page() ) {
			$url = home_url( '/' );
		}

		$out = array();
		if ( '' !== $desc ) {
			$out[] = array( 'name', 'description', $desc );
		}
		if ( is_front_page() ) {
			$code = trim( (string) exf_option( 'seo_google_verification' ) );
			if ( preg_match( '/content=["\']([^"\']+)/', $code, $m ) ) {
				$code = $m[1]; // Pasted the whole tag.
			}
			if ( '' !== $code ) {
				$out[] = array( 'name', 'google-site-verification', $code );
			}
		}
		$out[] = array( 'property', 'og:locale', 'en_GB' );
		$out[] = array( 'property', 'og:type', ( $id && 'post' === get_post_type( $id ) ) ? 'article' : 'website' );
		$out[] = array( 'property', 'og:site_name', exf_seo_site_name() );
		$out[] = array( 'property', 'og:title', $title );
		if ( '' !== $desc ) {
			$out[] = array( 'property', 'og:description', $desc );
		}
		$out[] = array( 'property', 'og:url', $url );
		if ( $image ) {
			$out[] = array( 'property', 'og:image', $image['url'] );
			if ( $image['width'] && $image['height'] ) {
				$out[] = array( 'property', 'og:image:width', (string) $image['width'] );
				$out[] = array( 'property', 'og:image:height', (string) $image['height'] );
			}
			if ( '' !== $image['alt'] ) {
				$out[] = array( 'property', 'og:image:alt', $image['alt'] );
			}
		}
		$out[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

		echo "\n<!-- The Ex Files SEO -->\n";
		foreach ( $out as $tag ) {
			printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
		}
	},
	1
);

// Structured data (JSON-LD) for Google: the business, the website, this page and, on service pages, the service.
add_action(
	'wp_head',
	function () {
		if ( ! exf_seo_enabled() || is_404() || is_search() ) {
			return;
		}
		$id = exf_seo_post_id();
		if ( exf_seo_is_noindex( $id ) ) {
			return;
		}
		$home = home_url( '/' );
		$org  = array(
			'@type' => 'LegalService',
			'@id'   => $home . '#business',
			'name'  => exf_seo_site_name(),
			'url'   => $home,
		);
		$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : EXF_URI . '/assets/img/logo.png';
		if ( $logo ) {
			$org['logo']  = $logo;
			$org['image'] = $logo;
		}
		$about = trim( (string) exf_option( 'seo_business_description' ) );
		if ( '' !== $about ) {
			$org['description'] = $about;
		}
		$email = trim( (string) exf_option( 'contact_email' ) );
		if ( '' !== $email ) {
			$org['email'] = $email;
		}
		$phone = trim( (string) exf_option( 'seo_phone' ) );
		if ( '' !== $phone ) {
			$org['telephone'] = $phone;
		}
		$area = trim( (string) exf_option( 'seo_area_served' ) );
		if ( '' !== $area ) {
			$org['areaServed'] = $area;
		}
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) exf_option( 'seo_address' ) ) ) ) );
		if ( $lines ) {
			$address = array(
				'@type'          => 'PostalAddress',
				'addressCountry' => 'GB',
			);
			$last = end( $lines );
			if ( preg_match( '/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', $last ) ) {
				$address['postalCode'] = strtoupper( $last );
				array_pop( $lines );
			}
			if ( count( $lines ) > 1 ) {
				$address['addressLocality'] = array_pop( $lines );
			}
			if ( $lines ) {
				$address['streetAddress'] = implode( ', ', $lines );
			}
			$org['address'] = $address;
		}
		$sra = trim( (string) exf_option( 'seo_sra_id' ) );
		if ( '' !== $sra ) {
			$org['identifier'] = array(
				'@type'      => 'PropertyValue',
				'propertyID' => 'SRA number',
				'value'      => $sra,
			);
		}
		$founder = trim( (string) exf_option( 'seo_founder' ) );
		if ( '' !== $founder ) {
			$org['founder'] = array(
				'@type'    => 'Person',
				'name'     => $founder,
				'jobTitle' => 'Solicitor',
			);
		}
		$same = array_values( array_filter( array( exf_option( 'instagram_url' ), exf_option( 'linkedin_url' ), exf_option( 'facebook_url' ) ) ) );
		if ( $same ) {
			$org['sameAs'] = $same;
		}

		$graph = array(
			$org,
			array(
				'@type'     => 'WebSite',
				'@id'       => $home . '#website',
				'url'       => $home,
				'name'      => exf_seo_site_name(),
				'publisher' => array( '@id' => $home . '#business' ),
				'inLanguage' => 'en-GB',
			),
		);

		if ( $id ) {
			$url  = is_front_page() ? $home : get_permalink( $id );
			$page = array(
				'@type'      => 'WebPage',
				'@id'        => $url . '#webpage',
				'url'        => $url,
				'name'       => exf_seo_title( $id ),
				'isPartOf'   => array( '@id' => $home . '#website' ),
				'about'      => array( '@id' => $home . '#business' ),
				'inLanguage' => 'en-GB',
			);
			$desc = exf_seo_description( $id );
			if ( '' !== $desc ) {
				$page['description'] = $desc;
			}
			$graph[] = $page;

			if ( 'page-service.php' === get_page_template_slug( $id ) ) {
				$service = array(
					'@type'       => 'Service',
					'@id'         => $url . '#service',
					'name'        => get_the_title( $id ),
					'url'         => $url,
					'serviceType' => 'Family law: financial matters on separation',
					'provider'    => array( '@id' => $home . '#business' ),
				);
				if ( '' !== $desc ) {
					$service['description'] = $desc;
				}
				if ( '' !== $area ) {
					$service['areaServed'] = $area;
				}
				$price = get_field( 'is_free', $id ) ? 0 : get_field( 'price', $id );
				if ( is_numeric( $price ) ) {
					$service['offers'] = array(
						'@type'         => 'Offer',
						'price'         => number_format( (float) $price, 2, '.', '' ),
						'priceCurrency' => 'GBP',
						'url'           => $url,
					);
				}
				$graph[] = $service;
			}
		}

		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "</script>\n";
	},
	2
);

// Live character counts under the title and description boxes in the editor.
add_action(
	'admin_footer-post.php',
	'exf_seo_counter_script'
);
add_action(
	'admin_footer-post-new.php',
	'exf_seo_counter_script'
);

/**
 * Prints the character counter script for the Search & social box.
 */
function exf_seo_counter_script() {
	?>
	<script>
	(function () {
		var rules = { seo_title: [50, 60], seo_description: [120, 155] };
		Object.keys(rules).forEach(function (name) {
			var field = document.querySelector('.acf-field[data-name="' + name + '"]');
			var input = field && field.querySelector('input, textarea');
			if (!input) { return; }
			var note = document.createElement('p');
			note.className = 'description';
			note.setAttribute('aria-live', 'polite');
			input.insertAdjacentElement('afterend', note);
			var update = function () {
				var n = input.value.trim().length, r = rules[name];
				note.textContent = n ? n + ' characters' + (n < r[0] ? ' (a little short: aim for ' + r[0] + '–' + r[1] + ')' : n > r[1] ? ' (may be cut off in Google: aim for ' + r[0] + '–' + r[1] + ')' : ' (good length)') : 'Aim for ' + r[0] + '–' + r[1] + ' characters.';
			};
			input.addEventListener('input', update);
			update();
		});
	})();
	</script>
	<?php
}
