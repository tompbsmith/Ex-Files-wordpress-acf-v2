<?php
/**
 * Admin screens for the guide: the Guide Requests log and the content page tools.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Guide Requests list
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_guide_request_posts_columns',
	function () {
		return array(
			'cb'             => '<input type="checkbox" />',
			'title'          => __( 'Request', 'exfiles' ),
			'exf_route'      => __( 'Route', 'exfiles' ),
			'exf_paragraphs' => __( 'Paragraphs', 'exfiles' ),
			'exf_services'   => __( 'Interested in', 'exfiles' ),
			'exf_sent'       => __( 'Emailed', 'exfiles' ),
		);
	}
);

add_action(
	'manage_guide_request_posts_custom_column',
	function ( $column, $post_id ) {
		switch ( $column ) {
			case 'exf_route':
				echo esc_html( (string) get_post_meta( $post_id, '_exf_route', true ) );
				break;
			case 'exf_paragraphs':
				echo esc_html( implode( ', ', (array) get_post_meta( $post_id, '_exf_paragraphs', true ) ) );
				break;
			case 'exf_services':
				$names = array_intersect_key( exf_guide_services(), array_flip( (array) get_post_meta( $post_id, '_exf_services', true ) ) );
				echo esc_html( $names ? implode( ', ', $names ) : '—' );
				if ( get_post_meta( $post_id, '_exf_marketing', true ) ) {
					echo '<br><small>' . esc_html__( 'Marketing consent given', 'exfiles' ) . '</small>';
				}
				break;
			case 'exf_sent':
				echo get_post_meta( $post_id, '_exf_email_sent', true ) ? esc_html__( 'Yes', 'exfiles' ) : '<strong style="color:#b32d2e">' . esc_html__( 'Failed', 'exfiles' ) . '</strong>';
				break;
		}
	},
	10,
	2
);

// No quick edit or "view" link: these records are read-only.
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'guide_request' === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'], $actions['view'] );
			$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $post->ID ) ), esc_html__( 'View', 'exfiles' ) );
		}
		return $actions;
	},
	10,
	2
);

// "Export CSV" button above the list.
add_action(
	'manage_posts_extra_tablenav',
	function ( $which ) {
		$screen = get_current_screen();
		if ( 'top' !== $which || ! $screen || 'edit-guide_request' !== $screen->id ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=exf_guide_export' ), 'exf_guide_export' );
		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'exfiles' ) . '</a></div>';
	}
);

add_action(
	'admin_post_exf_guide_export',
	function () {
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'exf_guide_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="guide-requests-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM so Excel reads UTF-8.
		fputcsv( $out, array( 'Date', 'Email', 'Route', 'Paragraphs', 'Interested in', 'Marketing consent', 'Emailed' ) );

		$services = exf_guide_services();
		$paged    = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'guide_request',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			foreach ( $query->posts as $p ) {
				fputcsv(
					$out,
					array(
						get_post_time( 'Y-m-d H:i', false, $p ),
						get_post_meta( $p->ID, '_exf_email', true ),
						get_post_meta( $p->ID, '_exf_route', true ),
						implode( ', ', (array) get_post_meta( $p->ID, '_exf_paragraphs', true ) ),
						implode( ', ', array_intersect_key( $services, array_flip( (array) get_post_meta( $p->ID, '_exf_services', true ) ) ) ),
						get_post_meta( $p->ID, '_exf_marketing', true ) ? 'Yes' : 'No',
						get_post_meta( $p->ID, '_exf_email_sent', true ) ? 'Yes' : 'No',
					)
				);
			}
			++$paged;
		} while ( $paged <= $query->max_num_pages );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Single guide request (read-only)
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_guide_request',
	function () {
		remove_meta_box( 'submitdiv', 'guide_request', 'side' );
		remove_meta_box( 'slugdiv', 'guide_request', 'normal' );
		add_meta_box( 'exf_guide_request', __( 'Guide sent', 'exfiles' ), 'exf_guide_request_box', 'guide_request', 'normal', 'high' );
		add_meta_box( 'exf_guide_request_side', __( 'Record', 'exfiles' ), 'exf_guide_request_side_box', 'guide_request', 'side', 'high' );
	}
);

// The title is generated; don't let it be edited.
add_action(
	'admin_head-post.php',
	function () {
		$screen = get_current_screen();
		if ( $screen && 'guide_request' === $screen->post_type ) {
			remove_post_type_support( 'guide_request', 'title' );
		}
	}
);

/**
 * Main box: answers and paragraphs.
 *
 * @param WP_Post $post Request.
 */
function exf_guide_request_box( $post ) {
	$answers    = (array) get_post_meta( $post->ID, '_exf_answers', true );
	$ids        = (array) get_post_meta( $post->ID, '_exf_paragraphs', true );
	$paragraphs = exf_guide_paragraphs();
	?>
	<h3 style="margin-top:0"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
	<p><strong><?php esc_html_e( 'Email:', 'exfiles' ); ?></strong> <?php echo esc_html( (string) get_post_meta( $post->ID, '_exf_email', true ) ); ?></p>
	<h4><?php esc_html_e( 'Answers (Annex A)', 'exfiles' ); ?></h4>
	<table class="widefat striped">
		<tbody>
		<?php foreach ( exf_guide_annex_rows( $answers ) as $row ) : ?>
			<tr><td style="width:60%"><?php echo esc_html( $row['question'] ); ?></td><td><strong><?php echo esc_html( $row['answer'] ); ?></strong></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<h4><?php esc_html_e( 'Paragraphs included', 'exfiles' ); ?></h4>
	<ul style="list-style:disc;padding-left:20px">
		<?php foreach ( $ids as $id ) : ?>
			<li><?php echo esc_html( $id . ( isset( $paragraphs[ $id ] ) ? ' — ' . $paragraphs[ $id ]['heading'] : '' ) ); ?></li>
		<?php endforeach; ?>
	</ul>
	<p class="description"><?php esc_html_e( 'Headings show the current wording. The PDF is the exact copy that was sent.', 'exfiles' ); ?></p>
	<?php
}

/**
 * Side box: PDF, email status, consent.
 *
 * @param WP_Post $post Request.
 */
function exf_guide_request_side_box( $post ) {
	$url      = wp_nonce_url( admin_url( 'admin-post.php?action=exf_admin_guide_pdf&id=' . $post->ID ), 'exf_admin_guide_pdf_' . $post->ID );
	$services = array_intersect_key( exf_guide_services(), array_flip( (array) get_post_meta( $post->ID, '_exf_services', true ) ) );
	?>
	<p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Download PDF', 'exfiles' ); ?></a></p>
	<p><?php echo get_post_meta( $post->ID, '_exf_email_sent', true ) ? esc_html__( 'Emailed successfully.', 'exfiles' ) : '<strong style="color:#b32d2e">' . esc_html__( 'The email failed to send.', 'exfiles' ) . '</strong>'; ?></p>
	<p><strong><?php esc_html_e( 'Interested in:', 'exfiles' ); ?></strong> <?php echo esc_html( $services ? implode( ', ', $services ) : __( 'None', 'exfiles' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Marketing consent:', 'exfiles' ); ?></strong> <?php echo get_post_meta( $post->ID, '_exf_marketing', true ) ? esc_html__( 'Yes', 'exfiles' ) : esc_html__( 'No', 'exfiles' ); ?></p>
	<p><a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url( get_delete_post_link( $post->ID, '', true ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this record and its PDF?', 'exfiles' ) ); ?>');"><?php esc_html_e( 'Delete permanently', 'exfiles' ); ?></a></p>
	<?php
}

add_action(
	'admin_post_exf_admin_guide_pdf',
	function () {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		check_admin_referer( 'exf_admin_guide_pdf_' . $id );
		if ( ! exf_user_can_manage() || 'guide_request' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		exf_private_stream( get_post_meta( $id, '_exf_file', true ), 'Divorce guide ' . get_the_date( 'Y-m-d', $id ) . '.pdf' );
	}
);

// Deleting a request deletes its PDF.
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( 'guide_request' === get_post_type( $post_id ) ) {
			exf_private_delete( get_post_meta( $post_id, '_exf_file', true ) );
		}
	}
);

/* -------------------------------------------------------------------------
 * Divorce Guide Content page: "Load the default wording" button
 * ---------------------------------------------------------------------- */

add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'exf-guide-content' ) || ! exf_user_can_manage() ) {
			return;
		}
		if ( isset( $_GET['exf_defaults'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The default wording has been loaded into any empty fields below.', 'exfiles' ) . '</p></div>';
		}
		$empty = ! function_exists( 'get_field' ) || ! get_field( 'guide_paragraphs', EXF_GUIDE_ID );
		$url   = wp_nonce_url( admin_url( 'admin-post.php?action=exf_guide_defaults' ), 'exf_guide_defaults' );
		echo '<div class="notice notice-info"><p>';
		if ( $empty ) {
			esc_html_e( 'The guide is currently using the wording from the precedent document. Load it into the fields below to edit it.', 'exfiles' );
		} else {
			esc_html_e( 'Empty fields fall back to the wording from the precedent document. Loading the defaults only fills empty fields; nothing you have edited is replaced.', 'exfiles' );
		}
		echo ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Load the default wording', 'exfiles' ) . '</a></p></div>';
	}
);

add_action(
	'admin_post_exf_guide_defaults',
	function () {
		if ( ! exf_user_can_manage() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'exf_guide_defaults' );
		exf_guide_load_defaults();
		wp_safe_redirect( admin_url( 'admin.php?page=exf-guide-content&exf_defaults=1' ) );
		exit;
	}
);

// Warn about paragraph IDs in the mapping that don't exist.
add_filter(
	'acf/validate_value/key=field_exf_gm_paragraphs',
	function ( $valid, $value ) {
		if ( true !== $valid ) {
			return $valid;
		}
		$bad = array_filter(
			preg_split( '/[\s,;]+/', strtoupper( (string) $value ) ),
			function ( $id ) {
				return '' !== $id && ! preg_match( '/^P\d{1,3}$/', $id );
			}
		);
		/* translators: %s: invalid IDs */
		return $bad ? sprintf( __( 'Use paragraph IDs like P01, P02. Not recognised: %s', 'exfiles' ), implode( ', ', $bad ) ) : true;
	},
	10,
	2
);
