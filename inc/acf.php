<?php
/**
 * ACF integration: local JSON, options page, editor tweaks.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

// Field groups live in /acf-json so they're version-controlled with the theme.
add_filter(
	'acf/settings/save_json',
	function () {
		return EXF_DIR . '/acf-json';
	}
);
add_filter(
	'acf/settings/load_json',
	function ( $paths ) {
		$paths[] = EXF_DIR . '/acf-json';
		return array_unique( $paths );
	}
);

/*
 * Auto-sync: when a field group JSON file in the theme is newer than its copy in
 * the database (e.g. after a Git deploy, or an edit to the JSON), import it so the
 * admin matches the repo. New field groups with no database copy are imported too.
 * The repo is the source of truth.
 */
add_action( 'admin_init', 'exf_auto_sync_field_groups' );

/**
 * Import newer field group JSON from the theme into the database.
 */
function exf_auto_sync_field_groups() {
	if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'acf_get_field_group_post' ) || ! function_exists( 'acf_import_field_group' ) ) {
		return;
	}

	$files = glob( EXF_DIR . '/acf-json/group_*.json' );
	if ( ! $files ) {
		return;
	}

	// Skip the work entirely unless a JSON file has changed since the last check.
	$signature = md5(
		implode(
			'|',
			array_map(
				function ( $file ) {
					return $file . ':' . filemtime( $file ) . ':' . filesize( $file );
				},
				$files
			)
		)
	);
	if ( get_option( 'exf_acf_json_signature' ) === $signature ) {
		return;
	}

	$synced = array();
	$seen   = (array) get_option( 'exf_acf_json_seen', array() );
	foreach ( $files as $file ) {
		$group = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $group ) || empty( $group['key'] ) || empty( $group['modified'] ) ) {
			continue;
		}

		$post          = acf_get_field_group_post( $group['key'] );
		$json_modified = (int) $group['modified'];

		if ( $post ) {
			// Compare against the JSON version this site last saved or synced. Fall back to
			// the database timestamp the first time.
			$baseline = isset( $seen[ $group['key'] ] ) ? (int) $seen[ $group['key'] ] : (int) get_post_modified_time( 'U', true, $post );
			if ( $json_modified <= $baseline ) {
				continue; // Already up to date.
			}
			$group['ID'] = $post->ID;
		}
		// No database copy yet (a new field group): import it so it shows in the admin.

		// Same as ACF's own "Sync" action, without rewriting the JSON file.
		$json_enabled = acf_get_setting( 'json' );
		acf_update_setting( 'json', false );
		acf_import_field_group( $group );
		acf_update_setting( 'json', $json_enabled );

		$seen[ $group['key'] ] = $json_modified;
		$synced[]              = $group['title'];
	}

	update_option( 'exf_acf_json_seen', $seen, false );
	update_option( 'exf_acf_json_signature', $signature, false );

	if ( $synced ) {
		set_transient( 'exf_acf_synced_' . get_current_user_id(), $synced, MINUTE_IN_SECONDS );
	}
}

/*
 * When a field group is saved in the admin (e.g. on Local), ACF rewrites its JSON file.
 * Record that version as already seen, so the auto-sync doesn't re-import it.
 * Runs after ACF's own JSON writer (priority 10).
 */
add_action(
	'acf/update_field_group',
	function ( $field_group ) {
		if ( empty( $field_group['key'] ) ) {
			return;
		}
		$file = EXF_DIR . '/acf-json/' . $field_group['key'] . '.json';
		if ( ! file_exists( $file ) ) {
			return;
		}
		clearstatcache( true, $file );
		$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! empty( $json['modified'] ) ) {
			$seen                         = (array) get_option( 'exf_acf_json_seen', array() );
			$seen[ $field_group['key'] ] = (int) $json['modified'];
			update_option( 'exf_acf_json_seen', $seen, false );
		}
	},
	20
);

add_action(
	'admin_notices',
	function () {
		$key    = 'exf_acf_synced_' . get_current_user_id();
		$synced = get_transient( $key );
		if ( $synced ) {
			delete_transient( $key );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(
				/* translators: %s: field group titles */
				sprintf( __( 'Field groups updated from the theme: %s', 'exfiles' ), implode( ', ', (array) $synced ) )
			) . '</p></div>';
		}
	}
);

// Site Settings options page (footer, socials, contact recipient).
add_action(
	'acf/init',
	function () {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page(
				array(
					'page_title' => __( 'Site Settings', 'exfiles' ),
					'menu_title' => __( 'Site Settings', 'exfiles' ),
					'menu_slug'  => 'exf-site-settings',
					'capability' => 'edit_theme_options',
					'icon_url'   => 'dashicons-admin-generic',
					'position'   => 59,
					'redirect'   => false,
				)
			);
		}
	}
);

// Pages built from sections use the classic screen so the sections are front and centre.
add_filter(
	'use_block_editor_for_post',
	function ( $use, $post ) {
		return ( $post && exf_is_sections_page( $post->ID ) ) ? false : $use;
	},
	10,
	2
);

// Show each section's heading in the collapsed layout bar.
add_filter(
	'acf/fields/flexible_content/layout_title/name=sections',
	function ( $title ) {
		$heading = get_sub_field( 'heading' );
		if ( $heading ) {
			$title .= ': <span style="font-weight:400">' . esc_html( wp_trim_words( preg_replace( '/\s+/', ' ', $heading ), 8 ) ) . '</span>';
		}
		if ( get_sub_field( 'hide_section' ) ) {
			$title .= ' <em style="opacity:.7">(' . esc_html__( 'hidden', 'exfiles' ) . ')</em>';
		}
		return $title;
	}
);

add_action(
	'admin_notices',
	function () {
		if ( exf_acf_ready() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . wp_kses_post(
			__( '<strong>The Ex Files theme</strong> needs <strong>ACF Pro</strong> or the free <strong>Secure Custom Fields</strong> plugin (both include Flexible Content) to edit the homepage sections.', 'exfiles' )
		) . '</p></div>';
	}
);
