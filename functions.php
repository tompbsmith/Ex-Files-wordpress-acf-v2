<?php
/**
 * The Ex Files theme bootstrap.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

define( 'EXF_VERSION', '1.3.0' );
define( 'EXF_DIR', get_template_directory() );
define( 'EXF_URI', get_template_directory_uri() );

require EXF_DIR . '/inc/setup.php';
require EXF_DIR . '/inc/helpers.php';
require EXF_DIR . '/inc/acf.php';
require EXF_DIR . '/inc/contact.php';
require EXF_DIR . '/inc/importer.php';
require EXF_DIR . '/inc/core/bootstrap.php';
