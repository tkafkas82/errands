<?php
/**
 * Rasterise the ERRANDS mark into PNG icons.
 *
 * SVG favicons are not honoured everywhere (Safari, older Android, and the
 * apple-touch-icon slot all want a bitmap), so the same geometry is drawn with
 * GD at the sizes browsers actually ask for.
 *
 * Run with:
 *   docker compose run --rm cli eval-file /import/make-icon.php
 *
 * Writes into the theme's assets/ folder, which is bind-mounted to Windows.
 *
 * @package Errands
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this through wp-cli.\n" );
}

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	WP_CLI::error( 'GD is not available in this container.' );
}

$out_dir = '/var/www/html/wp-content/themes/errands/assets';

if ( ! is_dir( $out_dir ) ) {
	WP_CLI::error( "Missing theme assets folder: $out_dir" );
}

/**
 * Draw the mark at a given pixel size.
 *
 * Geometry matches assets/favicon.svg on a 32-unit grid:
 *   frame  x 4.5 y 11.5 w 17 h 16, stroke 3 centred on the path
 *   block  x 18  y 4    w 10 h 10, solid, drawn over the frame
 *
 * @param int  $size        Output edge length in pixels.
 * @param bool $transparent Transparent ground instead of paper.
 * @return resource|GdImage
 */
function errands_draw_mark( $size, $transparent = false ) {
	$img = imagecreatetruecolor( $size, $size );
	imagealphablending( $img, false );
	imagesavealpha( $img, true );

	$s = $size / 32.0;
	$p = static function ( $u ) use ( $s ) {
		return (int) round( $u * $s );
	};

	$paper = $transparent
		? imagecolorallocatealpha( $img, 246, 244, 241, 127 )
		: imagecolorallocate( $img, 246, 244, 241 );
	$ink   = imagecolorallocate( $img, 22, 17, 15 );
	$red   = imagecolorallocate( $img, 255, 55, 6 );

	imagefilledrectangle( $img, 0, 0, $size - 1, $size - 1, $paper );

	imagealphablending( $img, true );

	// Frame drawn as a solid block with the middle punched back out, so the
	// stroke width is exact rather than at the mercy of imagesetthickness.
	imagefilledrectangle( $img, $p( 3.0 ), $p( 10.0 ), $p( 23.0 ), $p( 29.0 ), $ink );
	imagealphablending( $img, false );
	imagefilledrectangle( $img, $p( 6.0 ), $p( 13.0 ), $p( 20.0 ), $p( 26.0 ), $paper );
	imagealphablending( $img, true );

	// The displaced volume, over the frame's corner.
	imagefilledrectangle( $img, $p( 18.0 ), $p( 4.0 ), $p( 28.0 ), $p( 14.0 ), $red );

	return $img;
}

$targets = array(
	'favicon-32.png'        => array( 32, false ),
	'favicon-192.png'       => array( 192, false ),
	'apple-touch-icon.png'  => array( 180, false ),
	'icon-512.png'          => array( 512, false ),
);

foreach ( $targets as $name => $spec ) {
	list( $size, $transparent ) = $spec;

	$img  = errands_draw_mark( $size, $transparent );
	$path = $out_dir . '/' . $name;

	if ( imagepng( $img, $path, 9 ) ) {
		WP_CLI::log( sprintf( '%-24s %dx%d  %s bytes', $name, $size, $size, number_format( filesize( $path ) ) ) );
	} else {
		WP_CLI::warning( "failed to write $name" );
	}

	imagedestroy( $img );
}

WP_CLI::success( 'Icons written to theme assets/.' );
