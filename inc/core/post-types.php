<?php
/**
 * Private post types for client data.
 *
 * None of these are public: no front-end URLs, archives, search results, REST
 * routes or sitemap entries. The client portal reads them through its own code.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'exf_register_post_types' );

/**
 * Register the Ex Files post types under one "Ex Files" admin menu.
 */
function exf_register_post_types() {
	$private = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_rest'        => false,
		'show_in_nav_menus'   => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'map_meta_cap'        => false,
		'capabilities'        => exf_post_type_caps(),
	);
	$menu    = 'edit.php?post_type=ex_file';

	// One separation: the ex's details and the conflict check. Owned by the client (post author).
	register_post_type(
		'ex_file',
		array_merge(
			$private,
			array(
				'labels'        => exf_post_type_labels( __( 'Ex File', 'exfiles' ), __( 'Ex Files', 'exfiles' ), __( 'Ex Files', 'exfiles' ) ),
				'menu_icon'     => 'dashicons-portfolio',
				'menu_position' => 26,
				'supports'      => array( 'title', 'author' ),
			)
		)
	);

	register_post_type(
		'fs_questionnaire',
		array_merge(
			$private,
			array(
				'labels'       => exf_post_type_labels( __( 'Questionnaire', 'exfiles' ), __( 'Questionnaires', 'exfiles' ) ),
				'show_in_menu' => $menu,
				'supports'     => array( 'title', 'author' ),
				'capabilities' => exf_post_type_caps( false ),
			)
		)
	);

	register_post_type(
		'client_document',
		array_merge(
			$private,
			array(
				'labels'       => exf_post_type_labels( __( 'Client Document', 'exfiles' ), __( 'Client Documents', 'exfiles' ) ),
				'show_in_menu' => $menu,
				'supports'     => array( 'title' ),
			)
		)
	);

	// Written by the guide form only, so no "Add New".
	register_post_type(
		'guide_request',
		array_merge(
			$private,
			array(
				'labels'       => exf_post_type_labels( __( 'Guide Request', 'exfiles' ), __( 'Guide Requests', 'exfiles' ) ),
				'show_in_menu' => $menu,
				'supports'     => array( 'title' ),
				'capabilities' => exf_post_type_caps( false ),
			)
		)
	);

	register_post_type(
		'conflict_record',
		array_merge(
			$private,
			array(
				'labels'       => exf_post_type_labels( __( 'Conflict Record', 'exfiles' ), __( 'Conflict Register', 'exfiles' ) ),
				'show_in_menu' => $menu,
				'supports'     => array( 'title' ),
			)
		)
	);
}

/**
 * Admin labels for a post type.
 *
 * @param string $singular Singular name.
 * @param string $plural   Plural name.
 * @param string $menu     Menu name (defaults to plural).
 */
function exf_post_type_labels( $singular, $plural, $menu = '' ) {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'menu_name'          => $menu ? $menu : $plural,
		'all_items'          => $plural,
		/* translators: %s: item name */
		'add_new_item'       => sprintf( __( 'Add %s', 'exfiles' ), $singular ),
		'add_new'            => __( 'Add New', 'exfiles' ),
		/* translators: %s: item name */
		'edit_item'          => sprintf( __( 'Edit %s', 'exfiles' ), $singular ),
		/* translators: %s: item name */
		'view_item'          => sprintf( __( 'View %s', 'exfiles' ), $singular ),
		/* translators: %s: items name */
		'search_items'       => sprintf( __( 'Search %s', 'exfiles' ), $plural ),
		/* translators: %s: items name */
		'not_found'          => sprintf( __( 'No %s found.', 'exfiles' ), strtolower( $plural ) ),
		/* translators: %s: items name */
		'not_found_in_trash' => sprintf( __( 'No %s found in Bin.', 'exfiles' ), strtolower( $plural ) ),
	);
}
