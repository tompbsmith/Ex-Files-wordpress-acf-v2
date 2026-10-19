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
 * The functional pages the site needs: key => [template, default title, default slug, parent key].
 */
function exf_required_pages() {
	return array(
		'guide_landing' => array( 'page-sections.php', __( 'Free Divorce Guide', 'exfiles' ), 'free-divorce-guide', '' ),
		'guide_form'    => array( 'page-guide-form.php', __( 'Get your free guide', 'exfiles' ), 'start', 'guide_landing' ),
		'guide_sent'    => array( 'page-guide-sent.php', __( 'Your guide is on its way', 'exfiles' ), 'sent', 'guide_landing' ),
		'register'      => array( 'page-register.php', __( 'Open your Ex File', 'exfiles' ), 'open-your-ex-file', '' ),
		'account'       => array( 'page-account.php', __( 'My Ex File', 'exfiles' ), 'my-ex-file', '' ),
	);
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
 * URL of a functional page.
 *
 * @param string $key Key from exf_required_pages().
 */
function exf_page_url( $key ) {
	$pages = exf_required_pages();
	if ( 'guide_landing' === $key ) {
		$id = (int) get_option( 'exf_page_guide_landing' );
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/free-divorce-guide/' );
	}
	$id = isset( $pages[ $key ] ) ? exf_page_id_by_template( $pages[ $key ][0] ) : 0;
	return $id ? get_permalink( $id ) : home_url( '/' );
}

/**
 * Create any functional pages that are missing. Existing pages are left alone.
 *
 * @return string[] Titles of the pages created.
 */
function exf_create_missing_pages() {
	$created     = array();
	$ids         = array();
	$new_landing = 0;

	foreach ( exf_required_pages() as $key => $page ) {
		list( $template, $title, $slug, $parent_key ) = $page;

		if ( 'guide_landing' === $key ) {
			// The landing page is an ordinary Sections page, so remember it by ID.
			$existing = (int) get_option( 'exf_page_guide_landing' );
			if ( $existing && get_post( $existing ) ) {
				$ids[ $key ] = $existing;
				continue;
			}
			$by_path = get_page_by_path( $slug );
			if ( $by_path ) {
				$ids[ $key ] = $by_path->ID;
				update_option( 'exf_page_guide_landing', $by_path->ID, false );
				continue;
			}
		} else {
			$existing = exf_page_id_by_template( $template );
			if ( $existing ) {
				$ids[ $key ] = $existing;
				continue;
			}
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => ( $parent_key && ! empty( $ids[ $parent_key ] ) ) ? $ids[ $parent_key ] : 0,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_wp_page_template', $template );
		if ( 'guide_landing' === $key ) {
			update_option( 'exf_page_guide_landing', $id, false );
			$new_landing = $id;
		}
		$ids[ $key ] = $id;
		$created[]   = $title;
		wp_cache_delete( 'tpl_' . $template, 'exf_pages' );
	}

	// After the loop, so the card button can link to the form page.
	if ( $new_landing ) {
		exf_seed_guide_landing_sections( $new_landing );
	}

	return $created;
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
				'card_heading'  => __( 'It takes about two minutes', 'exfiles' ),
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

add_action(
	'admin_notices',
	function () {
		if ( ! exf_user_can_manage() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'exf-' ) && 'edit-ex_file' !== $screen->id ) {
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

		$missing = array();
		foreach ( exf_required_pages() as $key => $page ) {
			$id = 'guide_landing' === $key ? (int) get_option( 'exf_page_guide_landing' ) : exf_page_id_by_template( $page[0] );
			if ( ! $id || ! get_post( $id ) ) {
				$missing[] = $page[1];
			}
		}
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
