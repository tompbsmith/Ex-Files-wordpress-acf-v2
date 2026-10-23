<?php
/**
 * Capabilities and the Ex Files Manager role (client work, plus editing the site's pages).
 *
 * Everything client-related (Ex Files, questionnaires, documents, guide requests,
 * the conflict register) is gated by one capability, so the solicitor can work
 * without being a full administrator.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_CAP           = 'manage_ex_files';
const EXF_ROLES_VERSION = 2;

add_action( 'init', 'exf_maybe_install_roles', 5 );

/**
 * Create the role and grant the capability to administrators, once per version.
 */
function exf_maybe_install_roles() {
	if ( (int) get_option( 'exf_roles_version' ) === EXF_ROLES_VERSION ) {
		return;
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( EXF_CAP );
	}

	remove_role( 'ex_files_manager' );
	add_role(
		'ex_files_manager',
		__( 'Ex Files Manager', 'exfiles' ),
		array(
			'read'                   => true,
			'upload_files'           => true,
			EXF_CAP                  => true,
			// Pages: so she can edit the website's pages and their sections.
			'edit_pages'             => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'edit_private_pages'     => true,
			'read_private_pages'     => true,
			'publish_pages'          => true,
			'delete_pages'           => true,
			'delete_others_pages'    => true,
			'delete_published_pages' => true,
			'delete_private_pages'   => true,
		)
	);

	update_option( 'exf_roles_version', EXF_ROLES_VERSION );
}

/**
 * Whether the current user can work with client data.
 */
function exf_user_can_manage() {
	return current_user_can( EXF_CAP );
}

/**
 * Capability map for the private post types: everything needs EXF_CAP.
 *
 * @param bool $allow_create Whether "Add New" is allowed (false for machine-written records).
 */
function exf_post_type_caps( $allow_create = true ) {
	return array(
		'edit_post'              => EXF_CAP,
		'read_post'              => EXF_CAP,
		'delete_post'            => EXF_CAP,
		'edit_posts'             => EXF_CAP,
		'edit_others_posts'      => EXF_CAP,
		'delete_posts'           => EXF_CAP,
		'publish_posts'          => EXF_CAP,
		'read_private_posts'     => EXF_CAP,
		'delete_private_posts'   => EXF_CAP,
		'delete_published_posts' => EXF_CAP,
		'delete_others_posts'    => EXF_CAP,
		'edit_private_posts'     => EXF_CAP,
		'edit_published_posts'   => EXF_CAP,
		'create_posts'           => $allow_create ? EXF_CAP : 'do_not_allow',
	);
}

// Plain "saved" messages instead of WordPress's "Post published." on the Ex Files record screens.
add_filter(
	'post_updated_messages',
	function ( $messages ) {
		$names = array(
			'ex_file'          => __( 'Ex File', 'exfiles' ),
			'client_document'  => __( 'Document', 'exfiles' ),
			'conflict_record'  => __( 'Register entry', 'exfiles' ),
			'fs_questionnaire' => __( 'Questionnaire', 'exfiles' ),
			'exf_order'        => __( 'Order', 'exfiles' ),
			'guide_request'    => __( 'Guide request', 'exfiles' ),
		);
		foreach ( $names as $type => $name ) {
			/* translators: %s: record type, e.g. Document */
			$saved             = sprintf( __( '%s saved.', 'exfiles' ), $name );
			$messages[ $type ] = array(
				0  => '',
				1  => $saved,
				4  => $saved,
				6  => $saved,
				7  => $saved,
				8  => $saved,
				10 => $saved,
			);
		}
		return $messages;
	}
);
