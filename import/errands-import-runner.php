<?php
/**
 * Plugin Name: ERRANDS import runner
 * Description: One-off, admin-only runner for import.php on a host with no shell. Delete once the import is done.
 * Version: 1.0.0
 *
 * TEMPORARY. Upload to wp-content/mu-plugins/, run it from Tools -> ERRANDS
 * import, then delete it along with wp-content/errands-import/.
 *
 * Why this exists: import.php is written for wp-cli, and the work it does —
 * 197 attachments, each resized into seven sizes, some from 6MB originals — is
 * well past a shared host's max_execution_time for one web request. So the run
 * is split in two:
 *
 *   1. Content. Projects, pages, menu and attachment records, with sub-size
 *      generation switched off. Seconds rather than minutes, because no image
 *      is decoded or rewritten.
 *   2. Sizes. The derivatives, a few attachments per request, resumable, with
 *      each finished attachment marked so a restart never repeats work.
 *
 * Step 2 is the one that would otherwise time out, and it is the one that is
 * safe to interrupt: stop halfway and the next run picks up where it stopped.
 *
 * @package Errands
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where import.php and import.json were uploaded. */
if ( ! defined( 'ERRANDS_IMPORT_DIR' ) ) {
	define( 'ERRANDS_IMPORT_DIR', WP_CONTENT_DIR . '/errands-import' );
}

/** Attachments per request in the sizes pass. Lower it if the host is slow. */
if ( ! defined( 'ERRANDS_SIZE_BATCH' ) ) {
	define( 'ERRANDS_SIZE_BATCH', 5 );
}

const ERRANDS_SLUG = 'errands-import';
const ERRANDS_DONE = '_errands_sizes_done';

/* =========================================================================
 * Logging shim
 *
 * import.php speaks WP_CLI::log(). Supplying the class is enough; the WP_CLI
 * *constant* stays undefined, so nothing else in the request mistakes itself
 * for a CLI run.
 * ====================================================================== */

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		/** @var string[] */
		public static $lines = array();

		public static function log( $message ) {
			self::$lines[] = (string) $message;
		}

		public static function line( $message = '' ) {
			self::$lines[] = (string) $message;
		}

		public static function success( $message ) {
			self::$lines[] = 'Success: ' . $message;
		}

		public static function warning( $message ) {
			self::$lines[] = 'Warning: ' . $message;
		}

		public static function error( $message, $exit = true ) {
			self::$lines[] = 'Error: ' . $message;
			throw new RuntimeException( (string) $message );
		}
	}
}

/* =========================================================================
 * Admin page
 * ====================================================================== */

add_action( 'admin_menu', function () {
	add_management_page(
		'ERRANDS import',
		'ERRANDS import',
		'manage_options',
		ERRANDS_SLUG,
		'errands_runner_page'
	);
} );

/** Read the content manifest, or null if it was not uploaded. */
function errands_runner_data() {
	static $data = null;

	if ( null === $data ) {
		$file = ERRANDS_IMPORT_DIR . '/import.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : false;
	}

	return $data ? $data : null;
}

/** Attachments still waiting for their derivatives. */
function errands_runner_pending_sizes( $limit = -1 ) {
	return get_posts( array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => ERRANDS_DONE,
				'compare' => 'NOT EXISTS',
			),
		),
	) );
}

/**
 * Per-project comparison of what the manifest expects against what is in the
 * database. This is what catches a content pass that died halfway: the project
 * exists, so a re-run skips it, but it is short of images.
 */
function errands_runner_audit() {
	$data = errands_runner_data();
	$rows = array();

	if ( ! $data ) {
		return $rows;
	}

	$uploads = wp_get_upload_dir()['basedir'];

	foreach ( $data['projects'] as $p ) {
		$found = get_posts( array(
			'post_type'      => 'project',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_errands_legacy_id',
			'meta_value'     => (string) $p['legacy_id'],
		) );

		$expected = count( $p['images'] ) + count( isset( $p['docs'] ) ? $p['docs'] : array() );
		$post     = $found ? $found[0] : null;

		$actual = $post
			? count( get_children( array(
				'post_parent'    => $post->ID,
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			) ) )
			: 0;

		$absent = 0;
		foreach ( array_merge( $p['images'], isset( $p['docs'] ) ? $p['docs'] : array() ) as $rel ) {
			if ( ! file_exists( $uploads . '/' . $rel ) ) {
				$absent++;
			}
		}

		$rows[] = array(
			'title'    => $p['title'],
			'id'       => $post ? $post->ID : 0,
			'expected' => $expected,
			'actual'   => $actual,
			'absent'   => $absent,
			'ok'       => $post && $actual === $expected,
		);
	}

	return $rows;
}

/* =========================================================================
 * Actions
 * ====================================================================== */

/** Step 1: content, with image derivatives deferred to step 2. */
function errands_runner_import() {
	$file = ERRANDS_IMPORT_DIR . '/import.php';

	if ( ! is_readable( $file ) ) {
		return array( false, array( 'Not readable: ' . $file ) );
	}

	@set_time_limit( 0 );
	ignore_user_abort( true );
	wp_raise_memory_limit( 'image' );

	// No sub-sizes, and no -scaled rewrite of the big originals: both belong to
	// step 2, and together they are the whole reason a single request dies.
	$none = function () {
		return array();
	};
	add_filter( 'intermediate_image_sizes_advanced', $none, 99 );
	add_filter( 'big_image_size_threshold', '__return_false', 99 );

	if ( ! defined( 'ERRANDS_RUNNER' ) ) {
		define( 'ERRANDS_RUNNER', true );
	}

	WP_CLI::$lines = array();
	$ok            = true;

	try {
		require $file;
	} catch ( Throwable $e ) {
		$ok              = false;
		WP_CLI::$lines[] = 'Stopped: ' . $e->getMessage();
	}

	remove_filter( 'intermediate_image_sizes_advanced', $none, 99 );
	remove_filter( 'big_image_size_threshold', '__return_false', 99 );

	return array( $ok, WP_CLI::$lines );
}

/** Step 2: one batch of derivatives. Returns [done, remaining, log]. */
function errands_runner_sizes() {
	@set_time_limit( 0 );
	ignore_user_abort( true );
	wp_raise_memory_limit( 'image' );

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$batch = errands_runner_pending_sizes( ERRANDS_SIZE_BATCH );
	$log   = array();

	foreach ( $batch as $id ) {
		$path = get_attached_file( $id );

		if ( ! $path || ! file_exists( $path ) ) {
			$log[] = sprintf( '#%d skipped — file missing', $id );
			update_post_meta( $id, ERRANDS_DONE, 'missing' );
			continue;
		}

		$meta = wp_generate_attachment_metadata( $id, $path );

		if ( $meta ) {
			wp_update_attachment_metadata( $id, $meta );
			$log[] = sprintf(
				'#%-6d %-44s %d sizes',
				$id,
				wp_basename( $path ),
				count( isset( $meta['sizes'] ) ? $meta['sizes'] : array() )
			);
		} else {
			$log[] = sprintf( '#%d produced no metadata', $id );
		}

		update_post_meta( $id, ERRANDS_DONE, 1 );
	}

	return array( count( $batch ), count( errands_runner_pending_sizes() ), $log );
}

/**
 * Delete the projects the audit found incomplete, so the content pass rebuilds
 * them. Files on disk are untouched; only the database records go.
 */
function errands_runner_reset_incomplete() {
	$log = array();

	foreach ( errands_runner_audit() as $row ) {
		if ( $row['ok'] || ! $row['id'] ) {
			continue;
		}

		foreach ( get_children( array(
			'post_parent'    => $row['id'],
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		) ) as $child ) {
			wp_delete_post( $child->ID, true );
		}

		wp_delete_post( $row['id'], true );
		$log[] = sprintf( 'reset %s (#%d, had %d of %d)', $row['title'], $row['id'], $row['actual'], $row['expected'] );
	}

	return $log ? $log : array( 'Nothing to reset — every project is complete.' );
}

/* =========================================================================
 * Rendering
 * ====================================================================== */

function errands_runner_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}

	$action = isset( $_REQUEST['errands_action'] ) ? sanitize_key( $_REQUEST['errands_action'] ) : '';
	$log    = array();
	$notice = '';
	$next   = '';

	if ( $action ) {
		check_admin_referer( 'errands_' . $action );

		switch ( $action ) {
			case 'import':
				list( $ok, $log ) = errands_runner_import();
				$notice           = $ok
					? 'Content pass finished. Now run the sizes pass.'
					: 'Content pass stopped — see the log.';
				break;

			case 'sizes':
				list( $did, $left, $log ) = errands_runner_sizes();
				$notice                   = $left
					? sprintf( '%d done this batch, %d to go — continuing.', $did, $left )
					: 'All derivatives generated.';
				if ( $left ) {
					// Self-continue, so the whole queue drains without babysitting.
					$next = wp_nonce_url(
						admin_url( 'tools.php?page=' . ERRANDS_SLUG . '&errands_action=sizes' ),
						'errands_sizes'
					);
				}
				break;

			case 'reset':
				$log    = errands_runner_reset_incomplete();
				$notice = 'Incomplete projects cleared. Run the content pass again.';
				break;
		}
	}

	$data    = errands_runner_data();
	$pending = count( errands_runner_pending_sizes() );

	// The audit is 21 project lookups plus their children. Worth skipping while
	// the sizes pass is looping through forty-odd self-continuing requests.
	$audit = $next ? array() : errands_runner_audit();
	$bad   = array_filter( $audit, function ( $r ) {
		return ! $r['ok'];
	} );

	$button = function ( $act, $label, $primary = false ) {
		printf(
			'<a href="%s" class="button %s">%s</a> ',
			esc_url( wp_nonce_url(
				admin_url( 'tools.php?page=' . ERRANDS_SLUG . '&errands_action=' . $act ),
				'errands_' . $act
			) ),
			$primary ? 'button-primary' : '',
			esc_html( $label )
		);
	};

	echo '<div class="wrap"><h1>ERRANDS import</h1>';

	if ( $next ) {
		// Scripted rather than <meta refresh>, which is only valid in <head> and
		// this is rendered in the body. The link is the fallback if JS is off.
		printf(
			'<p><a href="%1$s">Continuing…</a> — leave this tab open.</p>' .
			'<script>setTimeout(function(){location.href=%2$s;},1000);</script>',
			esc_url( $next ),
			wp_json_encode( $next )
		);
	}

	if ( $notice ) {
		printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $notice ) );
	}

	if ( ! $data ) {
		printf(
			'<div class="notice notice-error"><p>No <code>import.json</code> in <code>%s</code>. Upload <code>import.php</code> and <code>import.json</code> there first.</p></div></div>',
			esc_html( ERRANDS_IMPORT_DIR )
		);
		return;
	}

	$theme = wp_get_theme();
	if ( 'errands' !== $theme->get_stylesheet() && 'errands' !== $theme->get_template() ) {
		printf(
			'<div class="notice notice-error"><p><strong>The ERRANDS theme is not active</strong> (current: %s). The <code>project</code> post type is registered by the theme, so importing now would create 21 posts of a type WordPress does not know about. Activate it first.</p></div>',
			esc_html( $theme->get( 'Name' ) )
		);
	}

	echo '<h2>1. Content</h2><p>Projects, pages, menu and attachment records. Image derivatives are deferred to step 2. Safe to re-run — anything already imported is skipped.</p><p>';
	$button( 'import', 'Run the content pass', true );
	echo '</p>';

	echo '<h2>2. Derivatives</h2>';
	printf(
		'<p>%s</p><p>',
		$pending
			? sprintf( '<strong>%d</strong> attachments still need their sizes. Runs %d at a time and continues on its own.', (int) $pending, (int) ERRANDS_SIZE_BATCH )
			: 'Nothing pending.'
	);
	if ( $pending ) {
		$button( 'sizes', 'Generate derivatives', true );
	}
	echo '</p>';

	echo '<h2>3. Audit</h2>';
	echo '<table class="widefat striped" style="max-width:52em"><thead><tr><th>Project</th><th>Attachments</th><th>Missing files</th></tr></thead><tbody>';
	foreach ( $audit as $row ) {
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
			esc_html( $row['title'] ),
			$row['ok']
				? sprintf( '%d', (int) $row['actual'] )
				: sprintf( '<strong style="color:#b32d2e">%d of %d</strong>', (int) $row['actual'], (int) $row['expected'] ),
			$row['absent'] ? sprintf( '<strong style="color:#b32d2e">%d not on disk</strong>', (int) $row['absent'] ) : '&mdash;'
		);
	}
	echo '</tbody></table>';

	if ( $bad ) {
		echo '<p>Projects short of attachments are usually a content pass that was interrupted. Clearing them lets the content pass rebuild them from scratch.</p><p>';
		$button( 'reset', sprintf( 'Clear %d incomplete project(s)', count( $bad ) ) );
		echo '</p>';
	} else {
		echo '<p><strong>Every project matches the manifest.</strong> Delete this plugin and <code>' . esc_html( ERRANDS_IMPORT_DIR ) . '</code>, then re-save Settings &rarr; Permalinks.</p>';
	}

	if ( $log ) {
		echo '<h2>Log</h2><pre style="background:#fff;border:1px solid #c3c4c7;padding:1em;max-height:26em;overflow:auto">';
		echo esc_html( implode( "\n", $log ) );
		echo '</pre>';
	}

	echo '</div>';
}
