<?php
/**
 * Finding and creating the functional pages (guide form, guide sent, and later the portal).
 *
 * Pages are found by their template, so the client can rename or move them freely.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * The pages the site needs: key => [ template, default title, default slug, parent key, how it's found ].
 *
 * Most are found by their template, so they can be renamed or moved freely. Pages
 * that share a template (Sections pages, service pages) are remembered by ID instead
 * ("option": stored as exf_page_{key}).
 */
function exf_required_pages() {
	$pages = array(
		'guide_landing' => array( 'page-sections.php', __( 'Free Divorce Guide', 'exfiles' ), 'free-divorce-guide', '', 'option' ),
		'guide_form'    => array( 'page-guide-form.php', __( 'Get your free guide', 'exfiles' ), 'start', 'guide_landing', 'template' ),
		'guide_sent'    => array( 'page-guide-sent.php', __( 'Your guide is on its way', 'exfiles' ), 'sent', 'guide_landing', 'template' ),
		'register'      => array( 'page-register.php', __( 'Open your Ex File', 'exfiles' ), 'open-your-ex-file', '', 'template' ),
		'account'       => array( 'page-account.php', __( 'My Ex File', 'exfiles' ), 'my-ex-file', '', 'template' ),
		'login'         => array( 'page-login.php', __( 'Log in', 'exfiles' ), 'log-in', '', 'template' ),
		'buy'           => array( 'page-buy.php', __( 'Choose your service', 'exfiles' ), 'buy', 'account', 'template' ),
		'services'      => array( 'page-sections.php', __( 'Our services', 'exfiles' ), 'services', '', 'option' ),
	);
	foreach ( exf_default_services() as $key => $svc ) {
		$pages[ $key ] = array( 'page-service.php', $svc[0], $svc[1], 'services', 'option' );
	}
	return $pages;
}

/**
 * ID of the published page using a template.
 *
 * @param string $template Template file name.
 * @return int 0 when there isn't one.
 */
function exf_page_id_by_template( $template ) {
	$cache = wp_cache_get( 'tpl_' . $template, 'exf_pages' );
	if ( false !== $cache ) {
		return (int) $cache;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $template, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$id  = $ids ? (int) $ids[0] : 0;
	wp_cache_set( 'tpl_' . $template, $id, 'exf_pages', 5 * MINUTE_IN_SECONDS );
	return $id;
}

/**
 * ID of one of the required pages, or 0 if it doesn't exist (or is in the Bin).
 *
 * @param string $key Key from exf_required_pages().
 */
function exf_page_id( $key ) {
	$pages = exf_required_pages();
	if ( ! isset( $pages[ $key ] ) ) {
		return 0;
	}
	if ( 'option' === $pages[ $key ][4] ) {
		$id = (int) get_option( 'exf_page_' . $key );
		return $id && in_array( get_post_status( $id ), array( 'publish', 'draft', 'private', 'pending' ), true ) ? $id : 0;
	}
	return exf_page_id_by_template( $pages[ $key ][0] );
}

/**
 * URL of one of the required pages.
 *
 * @param string $key Key from exf_required_pages().
 */
function exf_page_url( $key ) {
	$id = exf_page_id( $key );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$pages = exf_required_pages();
	return isset( $pages[ $key ] ) && 'option' === $pages[ $key ][4] ? home_url( '/' . $pages[ $key ][2] . '/' ) : home_url( '/' );
}

/**
 * Create any required pages that are missing. Existing pages are left alone.
 *
 * @return string[] Titles of the pages created.
 */
function exf_create_missing_pages() {
	$created = array();
	$new     = array();

	foreach ( exf_required_pages() as $key => $page ) {
		list( $template, $title, $slug, $parent_key, $by ) = $page;

		if ( exf_page_id( $key ) ) {
			continue;
		}
		$parent = $parent_key ? exf_page_id( $parent_key ) : 0;

		// A page remembered by ID may already exist at the expected address (e.g. made by hand).
		if ( 'option' === $by ) {
			$path    = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
			$by_path = get_page_by_path( $path );
			if ( $by_path && ( 'page-service.php' !== $template || 'page-service.php' === get_page_template_slug( $by_path->ID ) ) ) {
				update_option( 'exf_page_' . $key, $by_path->ID, false );
				continue;
			}
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $parent,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_wp_page_template', $template );
		if ( 'option' === $by ) {
			update_option( 'exf_page_' . $key, $id, false );
		}
		wp_cache_delete( 'tpl_' . $template, 'exf_pages' );
		$new[ $key ] = $id;
		$created[]   = $title;
	}

	// Content for new pages, once all of them exist (so links between them work).
	if ( isset( $new['guide_landing'] ) ) {
		exf_seed_guide_landing_sections( $new['guide_landing'] );
	}
	foreach ( exf_default_services() as $key => $svc ) {
		if ( isset( $new[ $key ] ) ) {
			exf_seed_service_page( $new[ $key ], $svc[2] );
		}
	}

	return $created;
}

/**
 * Fill a new service page's Service settings. Prices are left empty on purpose:
 * the client sets them, and the Buy button stays hidden until they do.
 *
 * @param int   $page_id  Page.
 * @param array $settings Field name => value.
 */
function exf_seed_service_page( $page_id, array $settings ) {
	foreach ( $settings as $name => $value ) {
		if ( function_exists( 'update_field' ) ) {
			update_field( 'field_exf_svc_' . $name, $value, $page_id );
		} else {
			update_post_meta( $page_id, $name, $value );
		}
	}
}

/**
 * Give a new guide landing page a starting set of sections, using the existing layouts.
 *
 * @param int $page_id Page ID.
 */
function exf_seed_guide_landing_sections( $page_id ) {
	if ( ! function_exists( 'update_field' ) ) {
		return;
	}
	update_field(
		'field_exf_sections',
		array(
			array(
				'acf_fc_layout' => 'guide',
				'heading'       => __( 'Your free, personalised divorce guide', 'exfiles' ),
				'text'          => __( "Answer a few anonymous questions about where you are in your separation. We'll email you a guide written around your answers, explaining the divorce process and the financial steps that apply to you.", 'exfiles' ),
				'card_heading'  => __( 'Free and anonymous', 'exfiles' ),
				'card_text'     => __( "We don't ask for your name or your ex's details. Just your email address, so we can send you the guide.", 'exfiles' ),
				'card_button'   => array(
					'title'  => __( 'Start the questions', 'exfiles' ),
					'url'    => exf_page_url( 'guide_form' ),
					'target' => '',
				),
			),
		),
		$page_id
	);
}

// Ex Files → Settings: button to create the pages, and a notice while any are missing.
add_action( 'admin_post_exf_create_pages', 'exf_handle_create_pages' );

/**
 * Handle the "Create missing pages" button.
 */
function exf_handle_create_pages() {
	if ( ! current_user_can( 'edit_pages' ) || ! exf_user_can_manage() ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'exfiles' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'exf_create_pages' );
	$created = exf_create_missing_pages();
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'        => 'exf-settings',
				'exf_created' => count( $created ),
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/**
 * Whether missing pages are created automatically after a deploy.
 *
 * Setting "page_setup" in Ex Files → Settings:
 *   auto   always create them automatically
 *   manual never: show a "Create missing pages" button instead
 *   ''     (default) automatic on test sites, manual on the live site
 *
 * A test site is one whose WordPress environment type isn't "production", or
 * whose address is on InstaWP, Local or localhost.
 */
function exf_pages_auto_create() {
	$mode = (string) exf_setting( 'page_setup', '' );
	if ( 'auto' === $mode ) {
		return true;
	}
	if ( 'manual' === $mode ) {
		return false;
	}
	return exf_is_test_site();
}

/**
 * Whether this looks like a test or development copy of the site.
 */
function exf_is_test_site() {
	if ( 'production' !== wp_get_environment_type() ) {
		return true;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return (bool) preg_match( '/(\.instawp\.(site|xyz|co|io)|\.local|\.test|^localhost)$/i', $host );
}

/**
 * Titles of required pages that don't exist.
 *
 * @return string[]
 */
function exf_missing_pages() {
	$missing = array();
	foreach ( exf_required_pages() as $key => $page ) {
		if ( ! exf_page_id( $key ) ) {
			$missing[] = $page[1];
		}
	}
	return $missing;
}

/*
 * After a deploy (a new theme version or a new required page), create missing pages
 * automatically the first time an admin who can manage Ex Files loads WP admin.
 */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! exf_user_can_manage() || ! current_user_can( 'edit_pages' ) || ! exf_pages_auto_create() ) {
			return;
		}
		$signature = md5( EXF_VERSION . '|' . implode( ',', array_keys( exf_required_pages() ) ) );
		if ( get_option( 'exf_pages_signature' ) === $signature ) {
			return;
		}
		update_option( 'exf_pages_signature', $signature, false );

		$created = exf_create_missing_pages();
		if ( $created ) {
			set_transient( 'exf_pages_auto_created', $created, DAY_IN_SECONDS );
		}
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! exf_user_can_manage() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// Pages created automatically: say so once, on whichever screen loads next.
		$auto = get_transient( 'exf_pages_auto_created' );
		if ( $auto ) {
			delete_transient( 'exf_pages_auto_created' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				/* translators: %s: page titles */
				sprintf( __( 'The Ex Files update added these pages: %s.', 'exfiles' ), implode( ', ', (array) $auto ) )
			) . '</p></div>';
		}

		$screen = get_current_screen();
		$here   = $screen && ( false !== strpos( (string) $screen->id, 'exf-' ) || 'edit-ex_file' === $screen->id || 'dashboard' === $screen->id );
		if ( ! $here ) {
			return;
		}

		if ( isset( $_GET['exf_created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$n = absint( $_GET['exf_created'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				$n
					/* translators: %d: number of pages */
					? sprintf( _n( 'Created %d page.', 'Created %d pages.', $n, 'exfiles' ), $n )
					: __( 'All the pages already exist.', 'exfiles' )
			) . '</p></div>';
		}

		$missing = exf_missing_pages();
		if ( ! $missing ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=exf_create_pages' ), 'exf_create_pages' );
		echo '<div class="notice notice-warning"><p>' . esc_html(
			/* translators: %s: page titles */
			sprintf( __( 'These Ex Files pages don\'t exist yet: %s.', 'exfiles' ), implode( ', ', $missing ) )
		) . ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Create missing pages', 'exfiles' ) . '</a></p></div>';
	}
);

/*
 * One-off update: point the header menu's "Open Your Ex File" link at the
 * registration page instead of the homepage contact form (#contact). Runs once,
 * after the registration page exists. Other menu items are left alone.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'exf_menu_register_link_done' ) || ! exf_page_id_by_template( 'page-register.php' ) ) {
			return;
		}
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_menu_item_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '#contact', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $items as $item ) {
			$title = strtolower( trim( $item->post_title ) );
			if ( 'open your ex file' === $title ) {
				update_post_meta( $item->ID, '_menu_item_url', '/open-your-ex-file/' );
			}
		}
		update_option( 'exf_menu_register_link_done', 1, false );
	},
	30
);
