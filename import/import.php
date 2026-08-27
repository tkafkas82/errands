<?php
/**
 * Import the cleaned errands.gr content into WordPress.
 *
 * Run with:
 *   docker compose run --rm cli eval-file /import/import.php
 *
 * Safe to re-run: every created object records its origin in post meta
 * (_errands_legacy_id / _errands_src) and is skipped if already present.
 * Pass ERRANDS_FORCE=1 in the environment to delete and rebuild instead.
 *
 * @package Errands
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this through wp-cli.\n" );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$force = (bool) getenv( 'ERRANDS_FORCE' );

$data = json_decode( file_get_contents( '/import/import.json' ), true );
if ( ! $data ) {
	WP_CLI::error( 'Could not read /import/import.json — run `node clean.js` first.' );
}

/**
 * Absolute path to the uploads folder.
 *
 * Resolved through a function rather than a top-level variable: `wp eval-file`
 * includes this script inside a function scope, so top-level variables here are
 * NOT globals and `global $x` in a helper would silently read null.
 */
function errands_uploads_dir() {
	static $dir = null;
	if ( null === $dir ) {
		$dir = wp_get_upload_dir()['basedir'];
	}
	return $dir;
}

/* =========================================================================
 * Helpers
 * ====================================================================== */

/** Find an existing object previously created by this importer. */
function errands_find_by_legacy( $legacy_id, $types = array( 'project', 'page', 'post' ) ) {
	$found = get_posts( array(
		'post_type'        => $types,
		'post_status'      => 'any',
		'posts_per_page'   => 1,
		'meta_key'         => '_errands_legacy_id',
		'meta_value'       => (string) $legacy_id,
		'suppress_filters' => false,
	) );

	return $found ? $found[0] : null;
}

/**
 * Register an already-on-disk file as an attachment, without copying it.
 *
 * The media was downloaded straight into wp-content/uploads at its original
 * YYYY/MM path, so all we need is the database record plus derived sizes.
 *
 * @return int|WP_Error Attachment ID.
 */
function errands_attach_existing( $rel, $parent_id, $order, $alt ) {
	$abs = errands_uploads_dir() . '/' . $rel;
	if ( ! file_exists( $abs ) ) {
		return new WP_Error( 'missing', "not on disk: $rel" );
	}

	// Already imported? Re-parent if it was orphaned, otherwise reuse.
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'meta_key'       => '_errands_src',
		'meta_value'     => $rel,
	) );

	if ( $existing ) {
		$id = (int) $existing[0]->ID;
		wp_update_post( array(
			'ID'          => $id,
			'post_parent' => $parent_id,
			'menu_order'  => $order,
		) );
		return $id;
	}

	$type = wp_check_filetype( basename( $abs ), null );

	// Humanise the original file name for the media library listing.
	$name  = pathinfo( $abs, PATHINFO_FILENAME );
	$title = trim( preg_replace( '/[-_]+/', ' ', $name ) );

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'] ? $type['type'] : 'application/octet-stream',
			'post_title'     => $title,
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $parent_id,
			'menu_order'     => $order,
		),
		$abs,
		$parent_id
	);

	if ( is_wp_error( $id ) || ! $id ) {
		return is_wp_error( $id ) ? $id : new WP_Error( 'attach_failed', $rel );
	}

	$meta = wp_generate_attachment_metadata( $id, $abs );
	if ( $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}

	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	update_post_meta( $id, '_errands_src', $rel );

	return (int) $id;
}

/** Attach a whole ordered list of media to a post and set the first as cover. */
function errands_attach_all( $post_id, array $images, array $docs, $project_title ) {
	$order      = 0;
	$first      = 0;
	$attached   = 0;
	$problems   = array();

	foreach ( $images as $i => $rel ) {
		$alt = sprintf(
			/* translators: %1$s: project title, %2$d: image number. */
			'%1$s — image %2$d',
			$project_title,
			$i + 1
		);

		$id = errands_attach_existing( $rel, $post_id, $order++, $alt );

		if ( is_wp_error( $id ) ) {
			$problems[] = $id->get_error_message();
			continue;
		}

		$attached++;
		if ( ! $first ) {
			$first = $id;
		}
	}

	foreach ( $docs as $rel ) {
		$id = errands_attach_existing( $rel, $post_id, $order++, '' );
		if ( is_wp_error( $id ) ) {
			$problems[] = $id->get_error_message();
		} else {
			$attached++;
		}
	}

	if ( $first ) {
		set_post_thumbnail( $post_id, $first );
	}

	return array( $attached, $problems );
}

/* =========================================================================
 * Series terms
 * ====================================================================== */

$series_names = array();
foreach ( $data['projects'] as $p ) {
	foreach ( $p['series'] as $s ) {
		$series_names[ $s ] = true;
	}
}

foreach ( array_keys( $series_names ) as $name ) {
	if ( ! term_exists( $name, 'project_series' ) ) {
		wp_insert_term( $name, 'project_series' );
		WP_CLI::log( "series: created '$name'" );
	}
}

/* =========================================================================
 * Projects
 * ====================================================================== */

$created  = 0;
$skipped  = 0;
$imgs     = 0;
$warnings = array();

foreach ( $data['projects'] as $p ) {
	$existing = errands_find_by_legacy( $p['legacy_id'] );

	if ( $existing && ! $force ) {
		WP_CLI::log( sprintf( 'skip    %-46s (already imported as #%d)', $p['slug'], $existing->ID ) );
		$skipped++;
		continue;
	}

	if ( $existing && $force ) {
		// Remove the old post and its attachment records (files stay on disk).
		foreach ( get_children( array( 'post_parent' => $existing->ID, 'post_type' => 'attachment' ) ) as $child ) {
			wp_delete_post( $child->ID, true );
		}
		wp_delete_post( $existing->ID, true );
	}

	$post_id = wp_insert_post( array(
		'post_type'      => 'project',
		'post_status'    => 'publish',
		'post_title'     => $p['title'],
		'post_name'      => $p['slug'],
		'post_content'   => $p['prose'],
		'post_excerpt'   => $p['excerpt'],
		'post_date'      => $p['date'],
		'post_date_gmt'  => $p['date'],
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	), true );

	if ( is_wp_error( $post_id ) ) {
		$warnings[] = $p['slug'] . ': ' . $post_id->get_error_message();
		continue;
	}

	update_post_meta( $post_id, '_errands_legacy_id', (string) $p['legacy_id'] );

	if ( $p['series'] ) {
		wp_set_object_terms( $post_id, $p['series'], 'project_series', false );
	}

	list( $n, $problems ) = errands_attach_all( $post_id, $p['images'], $p['docs'], $p['title'] );
	$imgs += $n;
	foreach ( $problems as $problem ) {
		$warnings[] = $p['slug'] . ': ' . $problem;
	}

	WP_CLI::log( sprintf( 'project %-46s #%-5d %2d media', $p['slug'], $post_id, $n ) );
	$created++;
}

/* =========================================================================
 * About page (the collective's own statement)
 * ====================================================================== */

$about_id = 0;
if ( ! empty( $data['about'] ) ) {
	$a        = $data['about'];
	$existing = errands_find_by_legacy( $a['legacy_id'] );

	if ( $existing && ! $force ) {
		$about_id = $existing->ID;
		WP_CLI::log( "skip    about                                          (already imported as #$about_id)" );
	} else {
		if ( $existing && $force ) {
			foreach ( get_children( array( 'post_parent' => $existing->ID, 'post_type' => 'attachment' ) ) as $child ) {
				wp_delete_post( $child->ID, true );
			}
			wp_delete_post( $existing->ID, true );
		}

		$about_id = wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'About',
			'post_name'      => 'about',
			'post_content'   => $a['prose'],
			'post_excerpt'   => $a['excerpt'],
			'comment_status' => 'closed',
		), true );

		if ( is_wp_error( $about_id ) ) {
			$warnings[] = 'about: ' . $about_id->get_error_message();
			$about_id   = 0;
		} else {
			update_post_meta( $about_id, '_errands_legacy_id', (string) $a['legacy_id'] );
			list( $n ) = errands_attach_all( $about_id, $a['images'], $a['docs'], 'ERRANDS' );
			$imgs     += $n;
			WP_CLI::log( sprintf( 'page    %-46s #%-5d %2d media', 'about', $about_id, $n ) );
		}
	}
}

/* =========================================================================
 * Exhibitions + Index pages
 * ====================================================================== */

/**
 * Create or fetch a page by slug.
 *
 * @return int Page ID.
 */
function errands_page( $slug, $title, $content, $template = '' ) {
	$existing = get_page_by_path( $slug );

	if ( $existing ) {
		return (int) $existing->ID;
	}

	$id = wp_insert_post( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_title'     => $title,
		'post_name'      => $slug,
		'post_content'   => $content,
		'comment_status' => 'closed',
	), true );

	if ( is_wp_error( $id ) ) {
		return 0;
	}

	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}

	return (int) $id;
}

// The exhibition record the collective states in its own text.
$exhibitions = array(
	array( '2007', '7th São Paulo Biennial of Architecture', 'São Paulo, Brazil', '' ),
	array( '2009', '2nd Athens Biennale', 'Athens, Greece', 'http://athensbiennale.org/ab2/' ),
	array( '2012', '1st Istanbul Design Biennial', 'Istanbul, Turkey', 'http://www.biennialfoundation.org/2011/07/first-istanbul-design-biennial-october-13-december-16-2012/' ),
	array( '2013', 'Chi controlla i controllori', 'Galleria Clou, Ragusa, Italy', 'http://www.galleriaclou.it/eventi_dettaglio.php?tipo=1&id_mostra=19' ),
);

$rows = '';
foreach ( $exhibitions as $e ) {
	list( $year, $name, $where, $url ) = $e;
	$label = $url
		? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ) . '</a>'
		: esc_html( $name );

	$rows .= sprintf(
		"\t<li><span class=\"exhibits__year\">%s</span><span><span class=\"exhibits__name\">%s</span><span class=\"exhibits__where\">%s</span></span></li>\n",
		esc_html( $year ),
		$label,
		esc_html( $where )
	);
}

$exhib_content = "<p>Selected exhibitions and biennials the group has taken part in.</p>\n\n<ul class=\"exhibits\">\n" . $rows . '</ul>';

$exhib_id = errands_page( 'exhibitions', 'Exhibitions', $exhib_content );
$index_id = errands_page( 'index', 'Index', '', 'template-index.php' );

WP_CLI::log( "page    exhibitions                                    #$exhib_id" );
WP_CLI::log( "page    index                                          #$index_id" );

/* =========================================================================
 * Site settings
 * ====================================================================== */

update_option( 'blogname', 'ERRANDS' );
update_option( 'blogdescription', 'Re-animating failed utopias' );
update_option( 'timezone_string', 'Europe/Athens' );
update_option( 'date_format', 'j F Y' );
update_option( 'start_of_week', 1 );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
update_option( 'comment_registration', 1 );
update_option( 'thumbnail_crop', 0 );
update_option( 'permalink_structure', '/%postname%/' );

// Drop the WordPress starter content if it is still untouched.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
	$victim = get_page_by_path( $slug, OBJECT, $type );
	if ( $victim && ! get_post_meta( $victim->ID, '_errands_legacy_id', true ) ) {
		wp_delete_post( $victim->ID, true );
		WP_CLI::log( "removed default $type '$slug'" );
	}
}

/* =========================================================================
 * Primary menu
 * ====================================================================== */

$menu_name = 'Primary';
$menu      = wp_get_nav_menu_object( $menu_name );

if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( $menu_name );
} else {
	$menu_id = (int) $menu->term_id;
	// Rebuild from scratch so re-runs do not stack duplicates.
	foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
		wp_delete_post( $item->ID, true );
	}
}

if ( ! is_wp_error( $menu_id ) ) {
	$position = 1;

	wp_update_nav_menu_item( $menu_id, 0, array(
		'menu-item-title'     => 'Projects',
		'menu-item-type'      => 'post_type_archive',
		'menu-item-object'    => 'project',
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $position++,
	) );

	foreach ( array( $exhib_id => 'Exhibitions', $index_id => 'Index', $about_id => 'About' ) as $pid => $label ) {
		if ( ! $pid ) {
			continue;
		}
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'    => $label,
			'menu-item-type'     => 'post_type',
			'menu-item-object'   => 'page',
			'menu-item-object-id' => $pid,
			'menu-item-status'   => 'publish',
			'menu-item-position' => $position++,
		) );
	}

	$locations            = get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );

	WP_CLI::log( "menu    Primary                                        #$menu_id" );
}

/* =========================================================================
 * Done
 * ====================================================================== */

flush_rewrite_rules( true );

WP_CLI::log( '' );
WP_CLI::log( str_repeat( '-', 62 ) );
WP_CLI::log( sprintf( 'projects created : %d', $created ) );
WP_CLI::log( sprintf( 'projects skipped : %d', $skipped ) );
WP_CLI::log( sprintf( 'media attached   : %d', $imgs ) );

if ( $warnings ) {
	WP_CLI::log( '' );
	WP_CLI::warning( count( $warnings ) . ' problem(s):' );
	foreach ( $warnings as $w ) {
		WP_CLI::log( '  ' . $w );
	}
} else {
	WP_CLI::log( 'no problems' );
}
