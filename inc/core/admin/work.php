<?php
/**
 * The solicitor's to-do: services that are paid for but not yet delivered.
 *
 * An order is "to do" once it's paid (or booked, if free) until she marks it
 * completed. Questionnaires submitted for an order that isn't completed are
 * waiting for her.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether an order has been marked completed.
 *
 * @param int $order Order.
 */
function exf_order_completed( $order ) {
	return (bool) get_post_meta( $order, 'completed_at', true );
}

/**
 * Live orders not yet completed, oldest first.
 *
 * @return int[]
 */
function exf_orders_to_do() {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'order_status',
					'value'   => array( 'paid', 'confirmed' ),
					'compare' => 'IN',
				),
				array(
					'key'     => 'completed_at',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * Submitted questionnaires whose service isn't completed.
 *
 * @return int[]
 */
function exf_questionnaires_waiting() {
	$out = array();
	foreach ( exf_orders_to_do() as $o ) {
		$q = (int) get_post_meta( $o, 'questionnaire_id', true );
		if ( $q && 'submitted' === exf_q_status( $q ) ) {
			$out[ $q ] = $q;
		}
	}
	return array_values( $out );
}

add_action(
	'add_meta_boxes_exf_order',
	function () {
		add_meta_box( 'exf_order_work', __( 'Delivery', 'exfiles' ), 'exf_order_work_box', 'exf_order', 'side', 'high' );
	}
);

/**
 * "Mark as completed" box on an order.
 *
 * @param WP_Post $post Order.
 */
function exf_order_work_box( $post ) {
	$id   = $post->ID;
	$done = (int) get_post_meta( $id, 'completed_at', true );
	$q    = (int) get_post_meta( $id, 'questionnaire_id', true );
	if ( ! exf_order_is_live( $id ) && ! $done ) {
		echo '<p>' . esc_html__( 'Nothing to deliver: this order isn’t paid.', 'exfiles' ) . '</p>';
		return;
	}
	if ( $q ) {
		echo '<p>' . esc_html__( 'Questionnaire:', 'exfiles' ) . ' <a href="' . esc_url( get_edit_post_link( $q ) ) . '">' . esc_html( exf_questionnaire_status_label( exf_q_status( $q ) ) ) . '</a></p>';
	}
	if ( $done ) {
		/* translators: %s: date */
		echo '<p><strong>' . esc_html( sprintf( __( 'Completed %s', 'exfiles' ), wp_date( 'j M Y', $done ) ) ) . '</strong></p>';
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_order_complete&undo=1&order=' . $id ), 'exf_order_complete_' . $id ) ) . '">' . esc_html__( 'Mark as not completed', 'exfiles' ) . '</a></p>';
	} else {
		echo '<p>' . esc_html__( 'When you have delivered this service (held the appointment, sent the report or document), mark it completed. It then leaves your to-do list.', 'exfiles' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exf_order_complete&order=' . $id ), 'exf_order_complete_' . $id ) ) . '">' . esc_html__( 'Mark as completed', 'exfiles' ) . '</a></p>';
	}
}

add_action(
	'admin_post_exf_order_complete',
	function () {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'exf_order_complete_' . $id );
		if ( ! exf_user_can_manage() || 'exf_order' !== get_post_type( $id ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		if ( ! empty( $_GET['undo'] ) ) {
			delete_post_meta( $id, 'completed_at' );
			exf_order_log( $id, 'Marked as not completed' );
		} else {
			update_post_meta( $id, 'completed_at', time() );
			exf_order_log( $id, 'Marked as completed' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : get_edit_post_link( $id, 'url' ) );
		exit;
	}
);

// Orders list: a "To do" view and a column.
add_filter(
	'views_edit-exf_order',
	function ( $views ) {
		$current        = isset( $_GET['exf_todo'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views['todo']  = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( admin_url( 'edit.php?post_type=exf_order&exf_todo=1' ) ), $current ? ' class="current"' : '', esc_html__( 'To do', 'exfiles' ), count( exf_orders_to_do() ) );
		return $views;
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() && $query->is_main_query() && 'exf_order' === $query->get( 'post_type' ) && isset( $_GET['exf_todo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ids = exf_orders_to_do();
			$query->set( 'post__in', $ids ? $ids : array( 0 ) );
		}
	}
);

add_action(
	'manage_exf_order_posts_custom_column',
	function ( $column, $id ) {
		if ( 'exf_status' === $column && exf_order_completed( $id ) ) {
			echo '<br><span style="color:#3d6b3f">' . esc_html__( 'Completed', 'exfiles' ) . '</span>';
		}
	},
	20,
	2
);

// The client sees "Completed" too.
add_filter(
	'exf_client_order_label',
	function ( $label, $order ) {
		return exf_order_completed( $order ) ? __( 'Completed', 'exfiles' ) : $label;
	},
	10,
	2
);
