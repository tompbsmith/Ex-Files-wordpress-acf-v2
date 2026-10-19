<?php
/**
 * Appearance → Ex Files Setup: one-click import of the original site's content.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_theme_page(
			__( 'Ex Files Setup', 'exfiles' ),
			__( 'Ex Files Setup', 'exfiles' ),
			'edit_theme_options',
			'exf-setup',
			'exf_setup_page'
		);
	}
);

add_action( 'admin_post_exf_import', 'exf_handle_import' );

/**
 * Default homepage sections, matching the original static site.
 *
 * @param array $media Attachment IDs keyed by asset.
 */
function exf_default_sections( $media ) {
	return array(
		array(
			'acf_fc_layout'  => 'hero',
			'eyebrow'        => 'Matrimonial Finance Specialists',
			'heading'        => "Making\nseparation\nless alien.",
			'questions'      => array(
				array( 'text' => 'Is an Ex File right for you?' ),
				array( 'text' => 'Are you currently navigating a divorce on your own?' ),
				array( 'text' => 'Do you need expert guidance on how your financial assets should be divided?' ),
				array( 'text' => 'Are you looking for a way to stay in control of your negotiations without a solicitor managing every detail?' ),
				array( 'text' => 'If you answered "yes" to these, starting your Ex File is the professional path for you.' ),
			),
			'buttons'        => array(
				array(
					'link'  => array( 'title' => 'Create Your Ex File', 'url' => '#contact', 'target' => '' ),
					'style' => 'solid',
				),
			),
			'video_desktop'  => $media['video_desktop'],
			'video_mobile'   => $media['video_mobile'],
			'poster_desktop' => '',
			'poster_mobile'  => '',
			'section_id'     => '',
			'hide_section'   => 0,
		),
		array(
			'acf_fc_layout' => 'guide',
			'heading'       => 'Get Your Free Divorce Guide',
			'text'          => 'Receive tailored guidance as to your next steps and how we can assist you.',
			'card_heading'  => 'Free Divorce Guide',
			'card_text'     => 'Complete a short, anonymous form and receive a downloadable document outlining key steps to take now, other professionals who may be able to help, and which of our services is likely the best fit for you at this stage.',
			'card_button'   => array( 'title' => 'Get Your Free Divorce Guide', 'url' => '#contact', 'target' => '' ),
			'section_id'    => '',
			'hide_section'  => 0,
		),
		array(
			'acf_fc_layout' => 'services',
			'heading'       => 'Our Services',
			'intro'         => 'Expert guidance tailored to your specific financial situation and goals.',
			'cards'         => array(
				array(
					'title'  => 'Know Your Position',
					'text'   => 'Gain clarity on your financial claims with a 1-hour virtual consultation and the option to receive a written advisory report.',
					'button' => array( 'title' => 'Book Assessment', 'url' => '#contact', 'target' => '' ),
				),
				array(
					'title'  => 'Check Your Deal',
					'text'   => 'Review your existing agreement to ensure it is a fair and legally sound resolution for your specific circumstances.',
					'button' => array( 'title' => 'Review Agreement', 'url' => '#contact', 'target' => '' ),
				),
				array(
					'title'  => 'Make it Legal',
					'text'   => 'Transform your agreement into a legally binding financial consent order.',
					'button' => array( 'title' => 'Make it Legal', 'url' => '#contact', 'target' => '' ),
				),
			),
			'section_id'    => 'services',
			'hide_section'  => 0,
		),
		array(
			'acf_fc_layout' => 'steps',
			'heading'       => 'How It Works',
			'intro'         => 'A clear, structured path to getting the right support for your financial settlement.',
			'steps'         => array(
				array(
					'title' => 'Select Your Service',
					'text'  => 'Choose the service that best matches where you are in your financial journey.',
				),
				array(
					'title' => 'Fixed Payment & Account Creation',
					'text'  => 'Make a fixed payment and create your account. From your account, you will be able to upload documents where required, manage any upcoming appointments, view the progress of your chosen service, access any written reports and instruct new services as and when required.',
				),
				array(
					'title' => 'Create Your Ex File',
					'text'  => 'Complete a questionnaire to build your personal profile before receiving your chosen service. This allows us to assess your financial landscape and tailor our support accordingly.',
				),
				array(
					'title' => 'Receive Your Chosen Service',
					'text'  => 'Once your Ex File is complete, you will receive your selected service in line with our agreed scope and fixed fee.',
				),
			),
			'section_id'    => 'how-it-works',
			'hide_section'  => 1, // Hidden on the original site too.
		),
		array(
			'acf_fc_layout' => 'trust',
			'heading'       => 'Certainty in your position, certainty in the price.',
			'items'         => array(
				array(
					'icon'       => 'shield',
					'icon_image' => '',
					'title'      => 'Independent Expertise',
					'text'       => 'We provide authoritative financial advice without the traditional solicitor model, ensuring you maintain full control over your settlement process.',
				),
				array(
					'icon'       => 'report',
					'icon_image' => '',
					'title'      => 'Transparent Process',
					'text'       => 'Our advisory reports are clear, concise, and tailored to your specific circumstances, helping you understand your position before any agreement is made.',
				),
				array(
					'icon'       => 'document',
					'icon_image' => '',
					'title'      => 'Legal Compliance',
					'text'       => 'We prepare legally binding consent orders and D81 statements to ensure your agreement is formally incorporated and protected by the court.',
				),
			),
			'section_id'    => 'the-brand',
			'hide_section'  => 0,
		),
		array(
			'acf_fc_layout'   => 'contact',
			'heading'         => 'Open Your Ex File',
			'lead'            => "Tell us a little about your situation and we'll come back to you to discuss the right next step.",
			'show_email'      => 0,
			'email'           => 'info@theexfiles.co.uk',
			'show_phone'      => 0,
			'button_label'    => 'Send Message',
			'success_message' => "Thanks — we've received your message and will be in touch shortly.",
			'section_id'      => 'contact',
			'hide_section'    => 0,
		),
	);
}

/**
 * Default Site Settings values.
 */
function exf_default_options() {
	return array(
		'form_recipient'     => 'info@theexfiles.co.uk',
		'footer_tagline'     => 'Making separation feel less alien',
		'contact_email'      => 'info@theexfiles.co.uk',
		'instagram_url'      => 'https://www.instagram.com/theexfiles.co.uk',
		'linkedin_url'       => 'https://www.linkedin.com/company/the-ex-files/',
		'facebook_url'       => 'https://www.facebook.com/share/1DfwbRHsop/',
		'show_footer_bottom' => 0,
		'copyright'          => '© {year} The Ex Files.',
		'meta_description'   => 'The Ex Files — matrimonial finance specialists. Making separation less alien with expert, fixed-fee guidance on dividing financial assets.',
	);
}

/**
 * Copy a bundled theme file into the Media Library (once).
 *
 * @param string $relative Path inside the theme.
 * @return int Attachment ID or 0.
 */
function exf_import_media( $relative ) {
	$map = (array) get_option( 'exf_imported_media', array() );
	if ( ! empty( $map[ $relative ] ) && get_post( $map[ $relative ] ) ) {
		return (int) $map[ $relative ];
	}

	$source = EXF_DIR . '/' . $relative;
	if ( ! file_exists( $source ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( basename( $source ) );
	if ( ! $tmp || ! copy( $source, $tmp ) ) {
		return 0;
	}

	$id = media_handle_sideload(
		array(
			'name'     => basename( $source ),
			'tmp_name' => $tmp,
		),
		0
	);

	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}

	$map[ $relative ] = (int) $id;
	update_option( 'exf_imported_media', $map, false );
	return (int) $id;
}

/**
 * Run the import.
 *
 * @param bool $overwrite Replace existing sections.
 * @return array{status:string,page:int} Result.
 */
function exf_run_import( $overwrite = false ) {
	if ( ! exf_acf_ready() ) {
		return array( 'status' => 'no-acf', 'page' => 0 );
	}

	// 1. Front page.
	$page_id = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
	if ( ! $page_id || ! get_post( $page_id ) ) {
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Home',
			)
		);
		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return array( 'status' => 'error', 'page' => 0 );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
	}

	$existing = get_field( 'field_exf_sections', $page_id, false );
	if ( ! empty( $existing ) && ! $overwrite ) {
		return array( 'status' => 'exists', 'page' => $page_id );
	}

	// 2. Media.
	$media = array(
		'video_desktop' => exf_import_media( 'assets/defaults/olive-hero-desktop.mp4' ),
		'video_mobile'  => exf_import_media( 'assets/defaults/olive-hero-mobile.mp4' ),
	);
	if ( ! get_theme_mod( 'custom_logo' ) ) {
		$logo = exf_import_media( 'assets/img/logo.png' );
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
		}
	}

	// 3. Sections.
	update_field( 'field_exf_sections', exf_default_sections( $media ), $page_id );

	// 4. Site Settings (only fill empty ones).
	foreach ( exf_default_options() as $name => $value ) {
		// Read the stored value directly: get_field() would return the field's default.
		$current = get_option( 'options_' . $name, null );
		if ( null === $current || '' === $current || $overwrite ) {
			update_field( 'field_exf_opt_' . $name, $value, 'option' );
		}
	}

	// 5. Primary menu, if none is assigned.
	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		$menu_id = wp_create_nav_menu( 'Primary' );
		if ( is_wp_error( $menu_id ) ) {
			$menu    = wp_get_nav_menu_object( 'Primary' );
			$menu_id = $menu ? $menu->term_id : 0;
		}
		if ( $menu_id && ! wp_get_nav_menu_items( $menu_id ) ) {
			foreach ( array( 'Home' => '#top', 'Our Services' => '#services', 'Open Your Ex File' => '#contact' ) as $title => $url ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => $title,
						'menu-item-url'    => $url,
						'menu-item-type'   => 'custom',
						'menu-item-status' => 'publish',
					)
				);
			}
		}
		if ( $menu_id ) {
			$locations['primary'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	return array( 'status' => 'done', 'page' => $page_id );
}

/**
 * Handle the import form post.
 */
function exf_handle_import() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'exfiles' ) );
	}
	check_admin_referer( 'exf_import' );

	$result = exf_run_import( ! empty( $_POST['overwrite'] ) );
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'   => 'exf-setup',
				'result' => $result['status'],
			),
			admin_url( 'themes.php' )
		)
	);
	exit;
}

/**
 * Render the setup screen.
 */
function exf_setup_page() {
	$result   = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$messages = array(
		'done'   => array( 'success', __( 'Default content imported. Your homepage is ready to edit.', 'exfiles' ) ),
		'exists' => array( 'warning', __( 'The homepage already has sections, so nothing was changed. Tick "Replace existing sections" to overwrite them.', 'exfiles' ) ),
		'no-acf' => array( 'error', __( 'Please activate ACF Pro or Secure Custom Fields first.', 'exfiles' ) ),
		'error'  => array( 'error', __( 'Something went wrong creating the homepage.', 'exfiles' ) ),
	);
	$front    = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ex Files Setup', 'exfiles' ); ?></h1>
		<?php if ( isset( $messages[ $result ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $result ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $result ][1] ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Import the content from the original Ex Files site: the homepage sections, the hero videos, the logo, footer settings and the main menu.', 'exfiles' ); ?></p>
		<p><?php esc_html_e( 'If no static homepage is set, a "Home" page is created and set as the homepage.', 'exfiles' ); ?></p>

		<?php if ( ! exf_acf_ready() ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Activate ACF Pro or Secure Custom Fields before importing.', 'exfiles' ); ?></p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="exf_import">
				<?php wp_nonce_field( 'exf_import' ); ?>
				<p><label><input type="checkbox" name="overwrite" value="1"> <?php esc_html_e( 'Replace existing sections and settings', 'exfiles' ); ?></label></p>
				<?php submit_button( __( 'Import default content', 'exfiles' ), 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<?php if ( $front ) : ?>
			<p style="margin-top:2em">
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $front ) ); ?>"><?php esc_html_e( 'Edit homepage sections', 'exfiles' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=exf-site-settings' ) ); ?>"><?php esc_html_e( 'Site Settings', 'exfiles' ); ?></a>
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View site', 'exfiles' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
