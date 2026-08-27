<?php
/**
 * ERRANDS theme functions.
 *
 * Editorial, gallery-first theme for the ERRANDS collective.
 *
 * @package Errands
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ERRANDS_VERSION', '1.2.0' );

require_once get_template_directory() . '/inc/placeholder.php';

/* -------------------------------------------------------------------------
 * Theme setup
 * ---------------------------------------------------------------------- */

function errands_setup() {
	load_theme_textdomain( 'errands', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 70,
		'width'       => 461,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );

	// Card images: soft-cropped so nothing important gets sliced off.
	add_image_size( 'errands-card', 1200, 900, false );
	add_image_size( 'errands-hero', 2200, 1400, false );
	// Square tiles for the index rail only, where a hard crop is intentional.
	add_image_size( 'errands-tile', 600, 600, true );

	register_nav_menus( array(
		'primary' => __( 'Primary menu', 'errands' ),
		'footer'  => __( 'Footer menu', 'errands' ),
	) );
}
add_action( 'after_setup_theme', 'errands_setup' );

function errands_content_width() {
	$GLOBALS['content_width'] = 1120;
}
add_action( 'after_setup_theme', 'errands_content_width', 0 );

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

function errands_assets() {
	// Inter covers Greek, which the archive titles need.
	wp_enqueue_style(
		'errands-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'errands', get_stylesheet_uri(), array( 'errands-fonts' ), ERRANDS_VERSION );

	wp_enqueue_script( 'errands', get_template_directory_uri() . '/assets/js/main.js', array(), ERRANDS_VERSION, true );
	wp_localize_script( 'errands', 'ERRANDS_I18N', array(
		'close'   => __( 'Close', 'errands' ),
		'prev'    => __( 'Previous image', 'errands' ),
		'next'    => __( 'Next image', 'errands' ),
		'of'      => __( 'of', 'errands' ),
		'noHits'  => __( 'Nothing matches that.', 'errands' ),
		'hint'    => __( 'Start typing to search', 'errands' ),
	) );

	// Search runs in the browser off this index, so it keeps working in the
	// static export where there is no PHP to answer /?s=. The whole site is
	// ~24 entries, so the index is a few KB.
	wp_localize_script( 'errands', 'ERRANDS_INDEX', errands_search_index() );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'errands_assets' );

/**
 * Icons.
 *
 * Only emitted when WordPress has no Site Icon set, so anything chosen in
 * Settings → General still wins.
 */
function errands_icons() {
	if ( get_option( 'site_icon' ) ) {
		return;
	}

	$base = get_template_directory_uri() . '/assets';
	$ver  = ERRANDS_VERSION;

	printf( '<link rel="icon" href="%s/favicon.svg?v=%s" type="image/svg+xml">' . "\n", esc_url( $base ), esc_attr( $ver ) );
	printf( '<link rel="icon" href="%s/favicon-32.png?v=%s" sizes="32x32" type="image/png">' . "\n", esc_url( $base ), esc_attr( $ver ) );
	printf( '<link rel="icon" href="%s/favicon-192.png?v=%s" sizes="192x192" type="image/png">' . "\n", esc_url( $base ), esc_attr( $ver ) );
	printf( '<link rel="apple-touch-icon" href="%s/apple-touch-icon.png?v=%s">' . "\n", esc_url( $base ), esc_attr( $ver ) );
	printf( '<meta name="theme-color" content="%s">' . "\n", '#ff3706' );
}
add_action( 'wp_head', 'errands_icons', 2 );

/** The 2011 site loaded the emoji polyfill on every page. Not needed. */
function errands_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'errands_trim_head' );

/* -------------------------------------------------------------------------
 * Content model: projects + series
 * ---------------------------------------------------------------------- */

function errands_register_content() {
	register_post_type( 'project', array(
		'labels'        => array(
			'name'               => __( 'Projects', 'errands' ),
			'singular_name'      => __( 'Project', 'errands' ),
			'add_new_item'       => __( 'Add new project', 'errands' ),
			'edit_item'          => __( 'Edit project', 'errands' ),
			'all_items'          => __( 'All projects', 'errands' ),
			'not_found'          => __( 'No projects yet.', 'errands' ),
			'menu_name'          => __( 'Projects', 'errands' ),
		),
		'public'        => true,
		'has_archive'   => 'projects',
		'rewrite'       => array( 'slug' => 'projects', 'with_front' => false ),
		'menu_icon'     => 'dashicons-camera-alt',
		'menu_position' => 5,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ),
		'show_in_rest'  => true,
		'taxonomies'    => array( 'project_series' ),
	) );

	register_taxonomy( 'project_series', array( 'project' ), array(
		'labels'            => array(
			'name'          => __( 'Series', 'errands' ),
			'singular_name' => __( 'Series', 'errands' ),
			'add_new_item'  => __( 'Add new series', 'errands' ),
			'menu_name'     => __( 'Series', 'errands' ),
		),
		'public'            => true,
		'hierarchical'      => false,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'series', 'with_front' => false ),
	) );
}
add_action( 'init', 'errands_register_content' );

/** Show every project on the archive; the grid is the point, pagination is not. */
function errands_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_post_type_archive( 'project' ) || $query->is_tax( 'project_series' ) ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'errands_archive_query' );

/* -------------------------------------------------------------------------
 * Gallery helpers
 * ---------------------------------------------------------------------- */

/**
 * Image attachments belonging to a post, in editor order.
 *
 * @param int  $post_id         Post ID.
 * @param bool $skip_thumbnail  Exclude the featured image (it runs as the hero).
 * @return array<int, WP_Post>
 */
function errands_gallery( $post_id = null, $skip_thumbnail = true ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$images = get_posts( array(
		'post_parent'    => $post_id,
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
	) );

	if ( $skip_thumbnail ) {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$images   = array_values( array_filter( $images, function ( $img ) use ( $thumb_id ) {
			return (int) $img->ID !== $thumb_id;
		} ) );
	}

	return $images;
}

/** Non-image attachments (the site has one PDF), surfaced as downloads. */
function errands_documents( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	return get_posts( array(
		'post_parent'    => $post_id,
		'post_type'      => 'attachment',
		'posts_per_page' => -1,
		'post_mime_type' => array( 'application/pdf' ),
		'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
	) );
}

/**
 * Render one lightbox-enabled gallery figure.
 *
 * @param WP_Post $image Attachment.
 * @param string  $size  Registered image size.
 */
function errands_figure( $image, $size = 'errands-card' ) {
	$full    = wp_get_attachment_image_url( $image->ID, 'full' );
	$caption = wp_get_attachment_caption( $image->ID );
	$alt     = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );

	if ( ! $alt ) {
		$alt = $caption ? $caption : get_the_title( $image->ID );
	}

	$meta   = wp_get_attachment_metadata( $image->ID );
	$ratio  = ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) )
		? round( $meta['width'] / max( 1, $meta['height'] ), 4 )
		: 1.5;
	// Tall images get a wider grid gutter so they do not tower over the row.
	$shape = $ratio >= 1.7 ? 'wide' : ( $ratio <= 0.85 ? 'tall' : 'std' );

	printf(
		'<figure class="gal__item gal__item--%1$s">
			<button class="gal__open" type="button" data-full="%2$s" data-caption="%3$s" aria-label="%4$s">
				%5$s
			</button>%6$s
		</figure>',
		esc_attr( $shape ),
		esc_url( $full ),
		esc_attr( $caption ),
		/* translators: %s: image title. */
		esc_attr( sprintf( __( 'Enlarge image: %s', 'errands' ), $alt ) ),
		wp_get_attachment_image( $image->ID, $size, false, array(
			'alt'      => $alt,
			'loading'  => 'lazy',
			'decoding' => 'async',
		) ),
		$caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : ''
	);
}

/* -------------------------------------------------------------------------
 * Presentation helpers
 * ---------------------------------------------------------------------- */

/** Series names for a project, as a comma-joined string. */
function errands_series_list( $post_id = null, $sep = ', ' ) {
	$terms = get_the_terms( $post_id ? $post_id : get_the_ID(), 'project_series' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}
	return implode( $sep, wp_list_pluck( $terms, 'name' ) );
}

/** Fallback cover: featured image, else the first attached image. */
function errands_cover_id( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$id      = (int) get_post_thumbnail_id( $post_id );

	if ( $id ) {
		return $id;
	}

	$first = errands_gallery( $post_id, false );
	return $first ? (int) $first[0]->ID : 0;
}

/**
 * Editorial grid rhythm: vary card widths so the archive does not read as a
 * uniform table of thumbnails.
 *
 * The grid is 6 columns. A "wide" card spans 3 (two per row), a "std" card
 * spans 2 (three per row). The pattern below therefore tiles into complete
 * rows every 5 cards: [wide wide] [std std std].
 *
 * @param int $index Zero-based card index.
 * @param int $total Total cards, so a trailing orphan can fill its row.
 * @return string One of 'wide', 'std', 'full'.
 */
function errands_card_span( $index, $total = 0 ) {
	$pattern = array( 'wide', 'wide', 'std', 'std', 'std' );
	$span    = $pattern[ $index % count( $pattern ) ];

	// A lone card opening a two-up row looks stranded; let it run full width.
	if ( $total && $index === $total - 1 && 0 === $index % 5 ) {
		return 'full';
	}

	return $span;
}

/** Count of images in a project, used as a card meta label. */
function errands_image_count( $post_id = null ) {
	return count( errands_gallery( $post_id, false ) );
}

/** Excerpt tail: "…" rather than "[...]" which the old theme printed. */
function errands_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'errands_excerpt_more' );

function errands_excerpt_length() {
	return 32;
}
add_filter( 'excerpt_length', 'errands_excerpt_length' );

/** Body classes describing the current view, for CSS hooks. */
function errands_body_class( $classes ) {
	if ( is_singular( 'project' ) ) {
		$classes[] = 'is-project';
	}
	if ( is_post_type_archive( 'project' ) || is_tax( 'project_series' ) ) {
		$classes[] = 'is-projects-archive';
	}
	return $classes;
}
add_filter( 'body_class', 'errands_body_class' );

/* -------------------------------------------------------------------------
 * Search index for the in-browser search
 * ---------------------------------------------------------------------- */

/**
 * Every public entry, flattened for client-side matching.
 *
 * Kept deliberately small: short keys, one trimmed excerpt each. Rebuilt on
 * each request, which is fine at this size and means the static export always
 * ships a current index.
 *
 * @return array<int, array<string, string>>
 */
function errands_search_index() {
	$items = get_posts( array(
		'post_type'      => array( 'project', 'page', 'post' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$index = array();

	foreach ( $items as $item ) {
		$text = $item->post_excerpt ? $item->post_excerpt : wp_strip_all_tags( $item->post_content );
		$type = get_post_type_object( $item->post_type );

		$index[] = array(
			't' => get_the_title( $item ),
			'u' => get_permalink( $item ),
			'y' => get_the_date( 'Y', $item ),
			's' => errands_series_list( $item->ID ),
			'k' => $type ? $type->labels->singular_name : $item->post_type,
			// Enough to match against and to show as a result line.
			'e' => wp_trim_words( $text, 28, '…' ),
		);
	}

	return $index;
}

/* -------------------------------------------------------------------------
 * Search: include projects
 * ---------------------------------------------------------------------- */

function errands_search_types( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() && ! $query->get( 'post_type' ) ) {
		$query->set( 'post_type', array( 'project', 'post', 'page' ) );
	}
}
add_action( 'pre_get_posts', 'errands_search_types' );

/* -------------------------------------------------------------------------
 * Customizer: the one line of copy that belongs to the front page
 * ---------------------------------------------------------------------- */

function errands_customize( $wp_customize ) {
	$wp_customize->add_section( 'errands_home', array(
		'title'    => __( 'ERRANDS front page', 'errands' ),
		'priority' => 30,
	) );

	$fields = array(
		'errands_statement' => array(
			'default' => 'Re-animating failed utopias.',
			'label'   => __( 'Hero statement', 'errands' ),
			'type'    => 'textarea',
		),
		'errands_standfirst' => array(
			'default' => 'A group of architects, visual artists, sociologists and graphic designers, working since 2007 on the spaces, objects and visions the system left behind.',
			'label'   => __( 'Hero standfirst', 'errands' ),
			'type'    => 'textarea',
		),
		'errands_locus' => array(
			'default' => 'Athens · since 2007',
			'label'   => __( 'Hero meta line', 'errands' ),
			'type'    => 'text',
		),
	);

	foreach ( $fields as $id => $args ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $args['default'],
			'sanitize_callback' => 'wp_kses_post',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $id, array(
			'section' => 'errands_home',
			'label'   => $args['label'],
			'type'    => $args['type'],
		) );
	}
}
add_action( 'customize_register', 'errands_customize' );

/** Theme mod with the customizer default applied. */
function errands_mod( $key, $fallback = '' ) {
	$defaults = array(
		'errands_statement'  => 'Re-animating failed utopias.',
		'errands_standfirst' => 'A group of architects, visual artists, sociologists and graphic designers, working since 2007 on the spaces, objects and visions the system left behind.',
		'errands_locus'      => 'Athens · since 2007',
	);
	$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : $fallback;

	return get_theme_mod( $key, $default );
}
