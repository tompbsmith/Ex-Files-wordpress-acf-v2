<?php
/**
 * Back office for the portal: the "Work" panel on each Ex File (orders,
 * questionnaires, documents) and the dashboard widget.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'add_meta_boxes_ex_file',
	function () {
		add_meta_box( 'exf_work', __( 'Orders, questionnaires and documents', 'exfiles' ), 'exf_work_box', 'ex_file', 'normal', 'default' );
	}
);

/**
 * Everything bought and shared for an Ex File.
 *
 * @param WP_Post $post Ex File.
 */
function exf_work_box( $post ) {
	$client = (int) $post->post_author;
	$orders = exf_client_orders( $client, $post->ID );
	$qs     = exf_client_questionnaires( $client, $post->ID );
	$docs   = exf_client_documents( $client, $post->ID );
	$add    = admin_url( 'post-new.php?post_type=client_document&ex_file=' . $post->ID );
	?>
	<h3 style="margin:8px 0"><?php esc_html_e( 'Orders', 'exfiles' ); ?></h3>
	<?php if ( $orders ) : ?>
		<table class="widefat striped"><tbody>
			<?php foreach ( $orders as $o ) : ?>
				<?php
				$start = (int) get_post_meta( $o, 'booking_start', true );
				$req   = (int) get_post_meta( $o, 'change_requested_at', true );
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $o ) ); ?>"><?php echo esc_html( (string) get_post_meta( $o, 'service_name', true ) ); ?></a></td>
					<td><?php echo esc_html( exf_order_status_label( exf_order_status( $o ) ) ); ?></td>
					<td>
						<?php echo esc_html( $start ? exf_booking_label( $start ) : '—' ); ?>
						<?php if ( $req && $start > time() ) : ?>
							<br><strong style="color:#b32d2e"><?php echo 'cancel' === get_post_meta( $o, 'change_request_kind', true ) ? esc_html__( 'Client asked to cancel', 'exfiles' ) : esc_html__( 'Client asked to move it', 'exfiles' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing ordered yet.', 'exfiles' ); ?></p>
	<?php endif; ?>

	<h3 style="margin:18px 0 8px"><?php esc_html_e( 'Questionnaires', 'exfiles' ); ?></h3>
	<?php if ( $qs ) : ?>
		<table class="widefat striped"><tbody>
			<?php foreach ( $qs as $q ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $q ) ); ?>"><?php echo esc_html( get_the_title( $q ) ); ?></a></td>
					<td><?php echo esc_html( exf_questionnaire_status_label( (string) get_post_meta( $q, 'q_status', true ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php else : ?>
		<p><?php esc_html_e( 'None.', 'exfiles' ); ?></p>
	<?php endif; ?>

	<h3 style="margin:18px 0 8px"><?php esc_html_e( 'Documents', 'exfiles' ); ?></h3>
	<?php if ( $docs ) : ?>
		<table class="widefat striped"><tbody>
			<?php foreach ( $docs as $d ) : ?>
				<?php $from_client = 'client' === get_post_meta( $d, 'uploaded_by', true ); ?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $d ) ); ?>"><?php echo esc_html( get_the_title( $d ) ); ?></a></td>
					<td><?php echo $from_client ? '<strong>' . esc_html__( 'From client', 'exfiles' ) . '</strong>' : esc_html__( 'Shared with client', 'exfiles' ); ?></td>
					<td><?php echo esc_html( get_the_date( 'j M Y', $d ) ); ?></td>
					<td><a href="<?php echo esc_url( exf_document_url( $d ) ); ?>"><?php esc_html_e( 'Download', 'exfiles' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php else : ?>
		<p><?php esc_html_e( 'None yet.', 'exfiles' ); ?></p>
	<?php endif; ?>
	<p style="margin-top:12px"><a class="button button-primary" href="<?php echo esc_url( $add ); ?>"><?php esc_html_e( 'Upload a document for this client', 'exfiles' ); ?></a></p>
	<?php
}

/* -------------------------------------------------------------------------
 * Dashboard widget
 * ---------------------------------------------------------------------- */

add_action(
	'wp_dashboard_setup',
	function () {
		if ( exf_user_can_manage() ) {
			wp_add_dashboard_widget( 'exf_dashboard', __( 'The Ex Files', 'exfiles' ), 'exf_dashboard_widget' );
		}
	}
);

// The dashboard shows only The Ex Files box: remove WordPress's own boxes
// (At a Glance, Activity, Quick Draft, Events and News, Site Health) and any added by plugins.
add_action(
	'wp_dashboard_setup',
	function () {
		global $wp_meta_boxes;
		if ( empty( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}
		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( (array) $priorities as $priority => $boxes ) {
				foreach ( array_keys( (array) $boxes ) as $id ) {
					if ( 'exf_dashboard' !== $id ) {
						remove_meta_box( $id, 'dashboard', $context );
					}
				}
			}
		}
	},
	999
);
remove_action( 'welcome_panel', 'wp_welcome_panel' );
// The dashboard home page is styled like the website (fonts, colours, cards).
// Only index.php: every other admin screen stays standard WordPress.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'exf-fonts', 'https://fonts.googleapis.com/css2?family=Petrona:wght@400;500;600&family=Jost:wght@300;400;500;600&display=swap', array(), null );
		$css = EXF_DIR . '/assets/css/admin-dashboard.css';
		wp_enqueue_style( 'exf-admin-dashboard', EXF_URI . '/assets/css/admin-dashboard.css', array( 'exf-fonts' ), file_exists( $css ) ? (string) filemtime( $css ) : EXF_VERSION );
	}
);

// Welcome header at the top of the dashboard home page.
add_action(
	'all_admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id || ! exf_user_can_manage() ) {
			return;
		}
		$user  = wp_get_current_user();
		$hour  = (int) wp_date( 'G' );
		$greet = $hour < 12 ? __( 'Good morning', 'exfiles' ) : ( $hour < 18 ? __( 'Good afternoon', 'exfiles' ) : __( 'Good evening', 'exfiles' ) );
		$name  = $user->first_name ? $user->first_name : $user->display_name;
		$links = array(
			array( __( 'Ex Files', 'exfiles' ), admin_url( 'edit.php?post_type=ex_file' ) ),
			array( __( 'Orders', 'exfiles' ), admin_url( 'edit.php?post_type=exf_order' ) ),
			array( __( 'Questionnaires', 'exfiles' ), admin_url( 'edit.php?post_type=fs_questionnaire' ) ),
			array( __( 'View website', 'exfiles' ), home_url( '/' ) ),
		);
		?>
		<div class="exf-dash-hero">
			<div class="exf-dash-hero-text">
				<p class="exf-dash-eyebrow"><?php echo esc_html( wp_date( 'l j F Y' ) ); ?></p>
				<p class="exf-dash-title"><?php echo esc_html( $greet . ( $name ? ', ' . $name : '' ) ); ?></p>
				<p class="exf-dash-sub"><?php esc_html_e( "Here's what needs your attention.", 'exfiles' ); ?></p>
			</div>
			<nav class="exf-dash-links" aria-label="<?php esc_attr_e( 'Shortcuts', 'exfiles' ); ?>">
				<?php foreach ( $links as $l ) : ?>
					<a class="exf-dash-btn" href="<?php echo esc_url( $l[1] ); ?>"><?php echo esc_html( $l[0] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
);

/**
 * Upcoming appointments across all clients.
 *
 * @param int $days How far ahead.
 * @return int[] Orders, soonest first.
 */
function exf_upcoming_appointments( $days = 7 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_key'       => 'booking_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'booking_start',
					'value'   => array( time() - HOUR_IN_SECONDS, time() + $days * DAY_IN_SECONDS ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	return array_values( array_filter( array_map( 'intval', $ids ), 'exf_order_is_live' ) );
}

/**
 * Dashboard widget: what needs attention.
 */
function exf_dashboard_widget() {
	$reviews = exf_review_count();
	$appts   = exf_upcoming_appointments( 7 );
	$uploads = get_posts(
		array(
			'post_type'      => 'client_document',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => '14 days ago' ) ),
			'meta_key'       => 'uploaded_by', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'client', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$requests = get_posts(
		array(
			'post_type'      => 'exf_order',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'change_requested_at',
					'value'   => time() - 14 * DAY_IN_SECONDS,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => 'booking_start',
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	?>
	<p>
		<?php if ( $reviews ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ex_file&conflict=review' ) ); ?>"><strong>
				<?php
				/* translators: %d: count */
				echo esc_html( sprintf( _n( '%d conflict check needs review', '%d conflict checks need review', $reviews, 'exfiles' ), $reviews ) );
				?>
			</strong></a>
		<?php else : ?>
			<?php esc_html_e( 'No conflict checks waiting.', 'exfiles' ); ?>
		<?php endif; ?>
	</p>

	<?php
	$todo    = function_exists( 'exf_orders_to_do' ) ? count( exf_orders_to_do() ) : 0;
	$waiting = function_exists( 'exf_questionnaires_waiting' ) ? exf_questionnaires_waiting() : array();
	$expired = (int) get_transient( 'exf_retention_due' );
	?>
	<?php if ( $waiting ) : ?>
		<h3><strong><?php esc_html_e( 'Questionnaires waiting for you', 'exfiles' ); ?></strong></h3>
		<ul>
			<?php foreach ( $waiting as $q ) : ?>
				<?php $u = get_userdata( (int) get_post_field( 'post_author', $q ) ); ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $q ) ); ?>"><?php echo esc_html( ( $u ? $u->display_name : '' ) . ' – ' . exf_q_service_name( $q ) ); ?></a> (<?php echo esc_html( wp_date( 'j M', (int) get_post_meta( $q, 'q_submitted_at', true ) ) ); ?>)</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=exf_order&exf_todo=1' ) ); ?>">
			<?php
			/* translators: %d: count */
			echo esc_html( sprintf( _n( '%d service to deliver', '%d services to deliver', $todo, 'exfiles' ), $todo ) );
			?>
		</a>
		<?php if ( $expired ) : ?>
			&nbsp;·&nbsp; <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ex_file&page=exf-retention' ) ); ?>">
				<?php
				/* translators: %d: count */
				echo esc_html( sprintf( _n( '%d matter past retention', '%d matters past retention', $expired, 'exfiles' ), $expired ) );
				?>
			</a>
		<?php endif; ?>
	</p>

	<?php $overdue = function_exists( 'exf_orders_q_overdue' ) ? exf_orders_q_overdue() : array(); ?>
	<?php if ( $overdue ) : ?>
		<h3><strong style="color:#b32d2e"><?php esc_html_e( 'Questionnaires not received in time', 'exfiles' ); ?></strong></h3>
		<ul>
			<?php foreach ( $overdue as $o ) : ?>
				<?php $u = get_userdata( (int) get_post_meta( $o, 'client_id', true ) ); ?>
				<li><?php echo esc_html( ( $u ? $u->display_name : '' ) . ' – ' . get_post_meta( $o, 'service_name', true ) . ', ' . exf_booking_label( (int) get_post_meta( $o, 'booking_start', true ) ) ); ?> · <a href="<?php echo esc_url( exf_appointment_admin_url( $o ) ); ?>"><?php esc_html_e( 'Change or cancel', 'exfiles' ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $requests ) : ?>
		<h3><strong><?php esc_html_e( 'Appointment requests', 'exfiles' ); ?></strong></h3>
		<ul>
			<?php foreach ( $requests as $o ) : ?>
				<?php $u = get_userdata( (int) get_post_meta( $o, 'client_id', true ) ); ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $o ) ); ?>"><?php echo esc_html( ( $u ? $u->display_name : '' ) . ' – ' . ( 'cancel' === get_post_meta( $o, 'change_request_kind', true ) ? __( 'cancel', 'exfiles' ) : __( 'move', 'exfiles' ) ) . ' ' . exf_booking_label( (int) get_post_meta( $o, 'booking_start', true ) ) ); ?></a> · <a href="<?php echo esc_url( exf_appointment_admin_url( $o ) ); ?>"><?php esc_html_e( 'Change or cancel', 'exfiles' ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h3><strong><?php esc_html_e( 'Appointments in the next 7 days', 'exfiles' ); ?></strong></h3>
	<?php if ( $appts ) : ?>
		<ul>
			<?php foreach ( $appts as $o ) : ?>
				<?php $u = get_userdata( (int) get_post_meta( $o, 'client_id', true ) ); ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $o ) ); ?>"><?php echo esc_html( exf_booking_label( (int) get_post_meta( $o, 'booking_start', true ) ) ); ?></a> – <?php echo esc_html( ( $u ? $u->display_name : '' ) . ', ' . get_post_meta( $o, 'service_name', true ) ); ?>
					<?php if ( (int) get_post_meta( $o, 'booking_start', true ) > time() ) : ?>
						· <a href="<?php echo esc_url( exf_appointment_admin_url( $o ) ); ?>"><?php esc_html_e( 'Change or cancel', 'exfiles' ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php esc_html_e( 'None.', 'exfiles' ); ?></p>
	<?php endif; ?>

	<?php $reports = function_exists( 'exf_reports_due' ) ? array_slice( exf_reports_due(), 0, 10, true ) : array(); ?>
	<?php if ( $reports ) : ?>
		<h3><strong><?php esc_html_e( 'Written reports due', 'exfiles' ); ?></strong></h3>
		<ul>
			<?php foreach ( $reports as $o => $due ) : ?>
				<?php $u = get_userdata( (int) get_post_meta( $o, 'client_id', true ) ); ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $o ) ); ?>"><?php echo esc_html( wp_date( 'D j M', $due ) ); ?></a> – <?php echo esc_html( ( $u ? $u->display_name : '' ) . ', ' . get_post_meta( $o, 'service_name', true ) ); ?>
					<?php if ( $due < time() ) : ?>
						<strong style="color:#b32d2e"><?php esc_html_e( 'overdue', 'exfiles' ); ?></strong>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h3><strong><?php esc_html_e( 'Recent uploads from clients', 'exfiles' ); ?></strong></h3>
	<?php if ( $uploads ) : ?>
		<ul>
			<?php foreach ( $uploads as $d ) : ?>
				<?php $u = get_userdata( (int) get_post_meta( $d, 'client_id', true ) ); ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $d ) ); ?>"><?php echo esc_html( get_the_title( $d ) ); ?></a> – <?php echo esc_html( ( $u ? $u->display_name : '' ) . ', ' . get_the_date( 'j M', $d ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php esc_html_e( 'None in the last 14 days.', 'exfiles' ); ?></p>
	<?php endif; ?>
	<?php
}
