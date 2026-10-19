<?php
/**
 * Protected file storage for client PDFs and documents.
 *
 * Files go in wp-content/uploads/exfiles-private/, which is closed to direct web
 * access (.htaccess for Apache, an index.php for directory listing). Nginx ignores
 * .htaccess, so on Nginx hosts also add a "deny all" location rule for that path.
 * Files are only ever served through PHP after a permission check.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Absolute path of the private directory (created and locked on first use).
 *
 * @param string $sub Optional sub-directory, e.g. "guides".
 * @return string|false Path with no trailing slash, or false if it can't be created.
 */
function exf_private_dir( $sub = '' ) {
	$uploads = wp_get_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return false;
	}
	$base = trailingslashit( $uploads['basedir'] ) . 'exfiles-private';

	if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
		return false;
	}
	exf_lock_dir( $base );

	if ( '' === $sub ) {
		return $base;
	}
	$dir = $base . '/' . sanitize_file_name( $sub );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	exf_lock_dir( $dir );
	return $dir;
}

/**
 * Write the files that stop a directory being browsed or served.
 *
 * @param string $dir Directory.
 */
function exf_lock_dir( $dir ) {
	$files = array(
		'.htaccess' => "# The Ex Files: private client files. Served only through WordPress.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	);
	foreach ( $files as $name => $contents ) {
		if ( ! file_exists( $dir . '/' . $name ) ) {
			file_put_contents( $dir . '/' . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}

/**
 * Save bytes to the private directory under an unguessable name.
 *
 * @param string $sub      Sub-directory.
 * @param string $basename Readable part of the file name.
 * @param string $bytes    File contents.
 * @return string|false Path relative to the private directory, or false.
 */
function exf_private_save( $sub, $basename, $bytes ) {
	$dir = exf_private_dir( $sub );
	if ( ! $dir ) {
		return false;
	}
	$name = sanitize_file_name( $basename );
	$ext  = pathinfo( $name, PATHINFO_EXTENSION );
	$stem = pathinfo( $name, PATHINFO_FILENAME );
	$file = $stem . '-' . wp_generate_password( 20, false, false ) . ( $ext ? '.' . $ext : '' );

	if ( false === file_put_contents( $dir . '/' . $file, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false;
	}
	return sanitize_file_name( $sub ) . '/' . $file;
}

/**
 * Absolute path for a stored relative path, refusing anything outside the private directory.
 *
 * @param string $relative Path from exf_private_save().
 * @return string|false
 */
function exf_private_path( $relative ) {
	$base = exf_private_dir();
	if ( ! $base || ! $relative ) {
		return false;
	}
	$path = realpath( $base . '/' . ltrim( (string) $relative, '/' ) );
	if ( ! $path || 0 !== strpos( $path, realpath( $base ) . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
		return false;
	}
	return $path;
}

/**
 * Delete a stored file.
 *
 * @param string $relative Path from exf_private_save().
 */
function exf_private_delete( $relative ) {
	$path = exf_private_path( $relative );
	if ( $path ) {
		wp_delete_file( $path );
	}
}

/**
 * Send a stored file to the browser and stop.
 *
 * @param string $relative      Path from exf_private_save().
 * @param string $download_name File name the visitor sees.
 * @param bool   $inline        Open in the browser rather than download.
 */
function exf_private_stream( $relative, $download_name, $inline = false ) {
	$path = exf_private_path( $relative );
	if ( ! $path ) {
		wp_die( esc_html__( 'Sorry, that file is no longer available.', 'exfiles' ), '', array( 'response' => 404 ) );
	}

	$type = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $path ) );
	header( sprintf( 'Content-Disposition: %s; filename="%s"', $inline ? 'inline' : 'attachment', str_replace( '"', '', $download_name ) ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	while ( ob_get_level() ) {
		ob_end_clean();
	}
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
