<?php
/**
 * Plugin Name: ERRANDS legacy redirects
 * Description: 301s the 2011 site's ?p=<id> permalinks onto the rebuilt projects.
 * Version: 1.0.0
 *
 * The old site's permalinks were https://errands.gr/?p=875. Those URLs are in
 * print, in biennial catalogues and on other people's pages, and WordPress will
 * not resolve them any more: the posts were recreated as a new post type with
 * new IDs. The importer recorded every old id in _errands_legacy_id, so the
 * mapping is exact rather than guessed.
 *
 * Keep this one. Unlike the import runner, it is not temporary.
 *
 * @package Errands
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', function () {
	if ( ! is_404() || empty( $_GET['p'] ) ) {
		return;
	}

	$legacy = (int) $_GET['p'];
	if ( $legacy < 1 ) {
		return;
	}

	$found = get_posts( array(
		'post_type'      => array( 'project', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_errands_legacy_id',
		'meta_value'     => (string) $legacy,
	) );

	if ( $found ) {
		wp_safe_redirect( get_permalink( $found[0] ), 301 );
		exit;
	}
} );
