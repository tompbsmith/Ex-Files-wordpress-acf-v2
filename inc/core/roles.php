<?php
/**
 * Capabilities and the Ex Files Manager role.
 *
 * Everything client-related (Ex Files, questionnaires, documents, guide requests,
 * the conflict register) is gated by one capability, so the solicitor can work
 * without being a full administrator.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_CAP           = 'manage_ex_files';
const EXF_ROLES_VERSION = 1;

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
			'read'         => true,
			'upload_files' => true,
			EXF_CAP        => true,
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
