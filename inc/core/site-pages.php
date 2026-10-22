<?php
/**
 * Content for the pages rebuilt from the client's Wix site (October 2026):
 * The Brand, The Brains, Our Services, and the alternative "Open your Ex File"
 * layout. The wording is copied from the Wix pages; the images are in
 * assets/img/pages/ and are copied into the Media Library the first time.
 *
 * Everything here is ordinary Page Builder content afterwards, so it can be edited
 * in each page's sections.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * A theme image as a Media Library attachment (added once, then reused).
 *
 * @param string $file Basename in assets/img/pages/.
 * @param string $alt  Alt text.
 * @return int Attachment ID, or 0.
 */
function exf_theme_image_attachment( $file, $alt = '' ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_exf_theme_image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	$path = EXF_DIR . '/assets/img/pages/' . $file;
	if ( ! is_readable( $path ) ) {
		return 0;
	}
	$upload = wp_upload_bits( $file, null, file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}
	$type = wp_check_filetype( $upload['file'] );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'],
			'post_title'     => $alt ? $alt : pathinfo( $file, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_exf_theme_image', $file );
	return (int) $id;
}

/**
 * Paragraphs → HTML for a text field.
 *
 * @param string[] $paras Paragraphs.
 */
function exf_paras( array $paras ) {
	return implode( "\n", array_map( fn( $p ) => '<p>' . esc_html( $p ) . '</p>', $paras ) );
}

/**
 * The sections for one of these pages.
 *
 * @param string $key brand|brains|services|register_alt.
 * @return array[] Rows for the "sections" field.
 */
function exf_page_sections_content( $key ) {
	$img = 'exf_theme_image_attachment';
	switch ( $key ) {
		case 'brand':
			return array(
				array(
					'acf_fc_layout' => 'split',
					'image'         => $img( 'brand-about.jpg', __( 'A woman looking out to sea at sunset', 'exfiles' ) ),
					'image_side'    => 'left',
					'background'    => 'cream',
					'heading_tag'   => 'h1',
					'heading'       => __( 'About', 'exfiles' ),
					'text'          => exf_paras( array( __( 'Founded by family law specialist Rachael Amelia Harwin, The Ex Files was born from seeing first-hand the disconnect between what separating couples actually need and the way traditional family law is often delivered. People are told that solicitors can help them protect their finances, minimise conflict and resolve matters swiftly, yet the hourly-rate model can make accessing that advice feel like a financial risk in itself.', 'exfiles' ) ) ),
				),
				array(
					'acf_fc_layout' => 'split',
					'image'         => $img( 'brand-mission.jpg', __( 'A laptop and a cup of tea on a desk', 'exfiles' ) ),
					'image_side'    => 'right',
					'background'    => 'cream',
					'heading_tag'   => 'h2',
					'heading'       => __( 'Our Mission', 'exfiles' ),
					'text'          => exf_paras(
						array(
							__( "Our mission is simple: to give you the legal information and documents you need, when you need them, without the ever-growing solicitor's bill.", 'exfiles' ),
							__( "We believe that getting legal advice doesn't have to mean handing over control of your separation. The Ex Files gives you the tools and expert guidance to understand your position, make informed decisions and negotiate with confidence, whilst keeping you firmly in the driving seat.", 'exfiles' ),
						)
					),
				),
				array(
					'acf_fc_layout' => 'split',
					'image'         => $img( 'brand-different.jpg', __( 'An olive tree in a terracotta pot in a bright room', 'exfiles' ) ),
					'image_side'    => 'left',
					'background'    => 'cream',
					'heading_tag'   => 'h2',
					'heading'       => __( 'A different way to do family law', 'exfiles' ),
					'text'          => exf_paras(
						array(
							__( 'Unlike the traditional solicitor model, we offer fixed-fee, defined legal services designed around the decisions you actually need to make. No open-ended negotiations, no unnecessary legal back-and-forth and no adversarial approach for the sake of it. Just specialist family-law advice and professional drafting, delivered when you need it.', 'exfiles' ),
							__( "Whether you're just starting to think about separation, wondering whether the deal your ex has proposed is right for you, or you've already reached an agreement and want to make it legally binding, we're here to make separation less alien.", 'exfiles' ),
						)
					),
				),
			);

		case 'brains':
			return array(
				array(
					'acf_fc_layout' => 'split',
					'image'         => $img( 'rachael-harwin.jpg', __( 'Rachael Harwin', 'exfiles' ) ),
					'image_side'    => 'left',
					'background'    => 'cream',
					'heading_tag'   => 'h1',
					'heading'       => __( 'Behind the Brand', 'exfiles' ),
					'text'          => exf_paras(
						array(
							__( 'The Ex Files is the brand, but the brain behind the scenes is Rachael Amelia Harwin.', 'exfiles' ),
							__( 'Rachael is a Consultant Solicitor specialising in family law and is the legal expert behind The Ex Files. She brings over a decade of experience advising clients through all aspects of relationship breakdown and formation, focusing on securing fair financial settlements and sustainable, forward-looking outcomes.', 'exfiles' ),
							__( 'After completing her Bachelor of Laws in Leeds and a Master’s in Law in Cambridge, Rachael qualified as a solicitor in 2021. Following years of practice at a top national firm, she founded The Ex Files in 2026 to provide a modern, resolution-focused and cost-friendly approach to separation.', 'exfiles' ),
							__( 'Her extensive casework includes managing high-net-worth financial remedy proceedings, complex pension arrangements, and business structures. Rachael is also highly skilled in drafting pre-nuptial and cohabitation agreements, as well as handling sensitive Children Act proceedings, international child abduction, and cases involving domestic abuse or protective orders to ensure client safety and security.', 'exfiles' ),
						)
					),
				),
				array(
					'acf_fc_layout' => 'expertise',
					'heading'       => __( 'Our Expertise', 'exfiles' ),
					'intro'         => __( 'Deeply rooted in matrimonial finance, we provide the clarity and authority needed for a smooth transition through separation.', 'exfiles' ),
					'background'    => 'sand',
					'items'         => array(
						array(
							'title' => __( 'Matrimonial Finance Specialist', 'exfiles' ),
							'text'  => __( '10 years of focused expertise in financial settlements and divorce proceedings, providing authoritative guidance for complex cases.', 'exfiles' ),
						),
						array(
							'title' => __( 'Financial Disclosure Advisor', 'exfiles' ),
							'text'  => __( 'Specialist in navigating the complexities of financial disclosure, ensuring transparency and accuracy in every exchange.', 'exfiles' ),
						),
						array(
							'title' => __( 'Financial Consent Order Specialist', 'exfiles' ),
							'text'  => __( 'Expertise in drafting legally binding financial consent orders and Statements of Information to secure your financial future.', 'exfiles' ),
						),
						array(
							'title' => __( 'Independent Financial Reviewer', 'exfiles' ),
							'text'  => __( 'Independent assessment of settlement deals to ensure you are receiving a fair and equitable resolution for your claims.', 'exfiles' ),
						),
					),
				),
				array(
					'acf_fc_layout' => 'testimonials',
					'heading'       => __( 'Client Testimonials', 'exfiles' ),
					'items'         => array(
						array( 'quote' => 'I had a meeting with Rachael Harwin today. She immediately put me at ease and spoke to me in a friendly but professional manner. She answered all of my questions honestly and even did some research for me and emailed me back later in the day with the answer I needed. Within the first five minutes of talking to her I knew I had the right person to help me. Thank you so much for today Rachael!' ),
						array( 'quote' => 'Rachael Harwin and her team provided exceptional representation during my divorce and financial agreement proceedings. Their expertise, professionalism, empathy, and strategic approach made a significant difference in the outcome of my case. I am incredibly grateful for their support and highly recommend their services to anyone in need of top-notch legal representation.' ),
						array( 'quote' => 'Rachael Harwin has represented me for the last few years. She is professional and honest with her advice and clear with her communication. She is always well prepared and quick to draft and send correspondence.' ),
						array( 'quote' => 'Over the past twelve months, I have faced one of the most challenging and emotionally exhausting chapters of my life. There were moments I felt utterly broken, but throughout it all, Rachael and the entire team have been nothing short of exceptional. Rachael has supported me with unwavering calm, clarity, and compassion. Her ability to deliver precise, considered advice especially in moments of emotional overwhelm has been a lifeline.' ),
						array( 'quote' => 'Rachael Harwin looked after me very well, being dyslexic she was very patient with my form filling abilities (or lack of them) she remained professional throughout with just the right amount of empathy for the situation she’s definitely one of the best and I should know sadly it’s my third time.' ),
						array( 'quote' => 'I am currently going through a difficult divorce and my solicitor has been throughout Rachael Harwin. Rachael has proven her worth and is currently and always has been professional, attentive, keeping me up to date regarding all developments and supported me throughout all the inevitable twists and turns. I feel I could not be in better hands and I am extremely happy that I have Rachael guiding and supporting me through this extremely hard and difficult process.' ),
					),
				),
			);

		case 'services':
			$link = function ( $type ) {
				foreach ( exf_services() as $s ) {
					if ( $type === $s['type'] ) {
						return array(
							'title'  => __( 'Find out more', 'exfiles' ),
							'url'    => get_permalink( $s['id'] ),
							'target' => '',
						);
					}
				}
				return array();
			};
			return array(
				array(
					'acf_fc_layout' => 'service_list',
					'eyebrow'       => __( 'Explore our services and get in touch', 'exfiles' ),
					'heading'       => __( 'Our Services', 'exfiles' ),
					'cards'         => array(
						array(
							'image' => $img( 'service-know-your-position.jpg', __( 'A statue of Lady Justice holding scales', 'exfiles' ) ),
							'title' => __( 'Know Your Position', 'exfiles' ),
							// The Wix screenshot cuts this text off; the rest is still to come from the client.
							'text'  => __( 'Our in-house specialist family law solicitor reviews the information provided in your Ex File and prepares a personalised written report explaining the relevant legal considerations and what you could be…', 'exfiles' ),
							'link'  => $link( 'know_your_position' ),
						),
						array(
							'image' => $img( 'service-check-your-deal.jpg', __( 'A magnifying glass on a printed agreement', 'exfiles' ) ),
							'title' => __( 'Check Your Deal', 'exfiles' ),
							'text'  => __( "Already agreed? Let's check it. We'll review the financial agreement you've reached with your ex and provide a written report highlighting anything that may need further consideration before you make it legal.", 'exfiles' ),
							'link'  => $link( 'check_your_deal' ),
						),
						array(
							'image' => $img( 'service-make-it-legal.jpg', __( 'Two people shaking hands over a signed document', 'exfiles' ) ),
							'title' => __( 'Make it Legal', 'exfiles' ),
							'text'  => __( "You've agreed. Now make it binding. Any agreement you reach with your ex will only be legally binding once it has been incorporated into a financial order and sent to the court for approval. We'll prepare your…", 'exfiles' ),
							'link'  => $link( 'make_it_legal' ),
						),
					),
				),
				array(
					'acf_fc_layout'   => 'inquiry',
					'heading'         => __( 'Service Enquiry', 'exfiles' ),
					'text'            => __( "Fill in the form below to let us know which service you're interested in and how we can help – we'll get back to you as soon as possible.", 'exfiles' ),
					'button_label'    => __( 'Send Request', 'exfiles' ),
					'services'        => '',
					'success_message' => '',
				),
			);

		case 'register_alt':
			return array(
				array(
					'acf_fc_layout' => 'split',
					'image'         => $img( 'open-your-ex-file.jpg', __( 'A black document folder labelled The Ex File', 'exfiles' ) ),
					'image_side'    => 'right',
					'background'    => 'cream',
					'heading_tag'   => 'h1',
					'heading'       => __( 'Open Your Ex File', 'exfiles' ),
					'subheading'    => __( 'Conflict-of-Interest Check', 'exfiles' ),
					'text'          => exf_paras( array( __( "Before we can begin creating your account or reviewing any detailed financial information, The Ex Files must perform a professional conflict-of-interest check. This is a standard procedure in matrimonial finance specialists to ensure we haven't already been engaged by your former partner, allowing us to provide you with completely independent and unbiased advice throughout your separation process.", 'exfiles' ) ) ),
				),
			);
	}
	return array();
}

/**
 * Fill a page's sections with its starting content.
 *
 * @param string $key     Page key.
 * @param int    $page_id Page.
 */
function exf_seed_page_sections( $key, $page_id ) {
	if ( ! function_exists( 'update_field' ) ) {
		return;
	}
	$rows = exf_page_sections_content( $key );
	if ( $rows ) {
		update_field( 'field_exf_sections', $rows, $page_id );
	}
}

/**
 * Put The Brand, The Brains and Our Services in the main menu, in the Wix order:
 * Home, The Brand, The Brains, Our Services, Open Your Ex File.
 */
function exf_menu_add_site_pages() {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		return; // The built-in menu already lists them.
	}
	$menu  = (int) $locations['primary'];
	$items = (array) wp_get_nav_menu_items( $menu );
	$want  = array(
		'brand'    => __( 'The Brand', 'exfiles' ),
		'brains'   => __( 'The Brains', 'exfiles' ),
		'services' => __( 'Our Services', 'exfiles' ),
	);
	$order = array();
	$home  = null;
	foreach ( $items as $item ) {
		if ( $item->menu_item_parent ) {
			continue;
		}
		$title = strtolower( trim( $item->title ) );
		if ( 'home' === $title ) {
			$home = $item;
		}
		// The old "Our Services" link to the homepage section becomes the page.
		if ( 'our services' === $title && 'custom' === $item->type ) {
			wp_delete_post( $item->ID, true );
			continue;
		}
		foreach ( $want as $key => $label ) {
			if ( 'page' === $item->object && (int) $item->object_id === exf_page_id( $key ) ) {
				$order[ $key ] = $item->ID;
				continue 2;
			}
		}
		$order[ 'other_' . $item->ID ] = $item->ID;
	}
	foreach ( $want as $key => $label ) {
		if ( ! isset( $order[ $key ] ) && exf_page_id( $key ) ) {
			$order[ $key ] = wp_update_nav_menu_item(
				$menu,
				0,
				array(
					'menu-item-object-id' => exf_page_id( $key ),
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-title'     => $label,
					'menu-item-status'    => 'publish',
				)
			);
		}
	}
	// Home first, then the three pages, then everything else in its old order.
	$sorted = array();
	if ( $home ) {
		$sorted[] = $home->ID;
		unset( $order[ 'other_' . $home->ID ] );
	}
	foreach ( array_keys( $want ) as $key ) {
		if ( ! empty( $order[ $key ] ) && ! is_wp_error( $order[ $key ] ) ) {
			$sorted[] = (int) $order[ $key ];
			unset( $order[ $key ] );
		}
	}
	$sorted = array_merge( $sorted, array_map( 'intval', array_values( $order ) ) );
	foreach ( $sorted as $i => $id ) {
		wp_update_post(
			array(
				'ID'         => $id,
				'menu_order' => $i + 1,
			)
		);
	}
}

/*
 * One-off (1.7.35): build the Wix pages. Creates The Brand, Our Services content and
 * the alternative "Open your Ex File" page, and replaces the placeholder content
 * on The Brains (the photo is now Rachael's headshot).
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'exf_wix_pages_2026_10' ) || ! function_exists( 'update_field' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'exf_wix_pages_2026_10', time(), false );
		exf_create_missing_pages();
		foreach ( array( 'brand', 'brains', 'services', 'register_alt' ) as $key ) {
			$id = exf_page_id( $key );
			if ( ! $id ) {
				continue;
			}
			$layouts = array_column( (array) get_field( 'sections', $id, false ), 'acf_fc_layout' );
			// Replace The Brains' placeholder; fill the others only if they're empty.
			if ( 'brains' === $key || ! $layouts ) {
				exf_seed_page_sections( $key, $id );
			}
			if ( 'services' === $key ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => __( 'Our Services', 'exfiles' ),
					)
				);
			}
		}
		exf_menu_add_site_pages();
		exf_audit( 'site_pages', 'Built The Brand, The Brains, Our Services and the alternative Open your Ex File page from the Wix design.' );
	},
	20
);
