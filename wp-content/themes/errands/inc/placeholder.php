<?php
/**
 * Generated cover artwork for projects with no photograph.
 *
 * Rather than a "missing image" tile, an imageless project gets a composed
 * drawing: a blueprint grid, an improvised skyline of stacked and cantilevered
 * volumes, a halftone field and one red element. It reads as an intentional
 * cover, which suits a group whose subject is improvised and leftover
 * structures.
 *
 * The composition is derived from the project slug, so a given project always
 * gets the same drawing, but no two projects get the same one.
 *
 * Colours come from CSS custom properties, so the artwork follows light/dark
 * like everything else.
 *
 * @package Errands
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canvas dimensions per usage.
 *
 * @param string $variant card | wide | hero | tile.
 * @return array{0:float,1:float}
 */
function errands_placeholder_box( $variant ) {
	switch ( $variant ) {
		case 'wide':
			return array( 1200.0, 750.0 );
		case 'hero':
			return array( 2400.0, 1100.0 );
		case 'tile':
			return array( 600.0, 600.0 );
		case 'card':
		default:
			return array( 1200.0, 900.0 );
	}
}

/**
 * Build the generated cover as inline SVG.
 *
 * @param int    $post_id Project ID.
 * @param string $variant Canvas variant.
 * @return string SVG markup.
 */
function errands_placeholder_svg( $post_id = null, $variant = 'card' ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$slug = get_post_field( 'post_name', $post_id );
	if ( ! $slug ) {
		$slug = (string) $post_id;
	}

	// Deterministic sequence seeded by the slug: same project, same drawing.
	$seed = crc32( $slug );
	$rnd  = static function () use ( &$seed ) {
		$seed = ( $seed * 1103515245 + 12345 ) & 0x7FFFFFFF;
		return $seed / 0x7FFFFFFF;
	};
	// Random float in a range.
	$between = static function ( $lo, $hi ) use ( $rnd ) {
		return $lo + ( $hi - $lo ) * $rnd();
	};

	list( $w, $h ) = errands_placeholder_box( $variant );

	$ground = $h * 0.80;
	$svg    = array();

	/* ---------------------------------------------------------------
	 * Ground tone
	 * -------------------------------------------------------------- */

	$svg[] = sprintf( '<rect class="ph__bg" width="%s" height="%s"/>', $w, $h );

	/* ---------------------------------------------------------------
	 * Blueprint grid
	 * -------------------------------------------------------------- */

	$step = $w / 24;
	$grid = array();
	for ( $x = $step; $x < $w; $x += $step ) {
		$grid[] = sprintf( 'M%.1f 0V%.1f', $x, $h );
	}
	for ( $y = $step; $y < $h; $y += $step ) {
		$grid[] = sprintf( 'M0 %.1fH%.1f', $y, $w );
	}
	$svg[] = sprintf( '<path class="ph__grid" d="%s"/>', implode( '', $grid ) );

	/* ---------------------------------------------------------------
	 * Improvised skyline
	 * -------------------------------------------------------------- */

	$count  = 5 + (int) floor( $rnd() * 4 ); // 5–8 volumes.
	$margin = $w * 0.08;
	$span   = $w - ( $margin * 2 );

	// Random-ish column widths that still fill the span exactly.
	$weights = array();
	$sum     = 0.0;
	for ( $i = 0; $i < $count; $i++ ) {
		$weights[ $i ] = $between( 0.6, 1.6 );
		$sum          += $weights[ $i ];
	}

	$accent_col = (int) floor( $rnd() * $count );
	$x          = $margin;
	$blocks     = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$cw = ( $weights[ $i ] / $sum ) * $span;
		$ch = $between( $h * 0.16, $h * 0.56 );
		$y  = $ground - $ch;

		$blocks[] = array( $x, $y, $cw, $ch, $i === $accent_col );
		$x       += $cw;
	}

	foreach ( $blocks as $b ) {
		list( $bx, $by, $bw, $bh, $is_accent ) = $b;

		// Inset so adjacent volumes read as separate structures.
		$inset = min( 6.0, $bw * 0.06 );
		$rx    = $bx + $inset;
		$rw    = $bw - ( $inset * 2 );

		if ( $rw <= 2 ) {
			continue;
		}

		if ( $is_accent ) {
			$svg[] = sprintf(
				'<rect class="ph__accent" x="%.1f" y="%.1f" width="%.1f" height="%.1f"/>',
				$rx, $by, $rw, $bh
			);
			continue;
		}

		$svg[] = sprintf(
			'<rect class="ph__line" x="%.1f" y="%.1f" width="%.1f" height="%.1f"/>',
			$rx, $by, $rw, $bh
		);

		// Floor lines — the facade.
		$floor_gap = max( 18.0, $h * 0.045 );
		$floors    = array();
		for ( $fy = $by + $floor_gap; $fy < $ground - 2; $fy += $floor_gap ) {
			$floors[] = sprintf( 'M%.1f %.1fh%.1f', $rx, $fy, $rw );
		}
		if ( $floors ) {
			$svg[] = sprintf( '<path class="ph__floor" d="%s"/>', implode( '', $floors ) );
		}

		// A cantilevered add-on on some volumes: the improvised part.
		if ( $rnd() > 0.45 ) {
			$aw = $between( $rw * 0.28, $rw * 0.62 );
			$ah = $between( $h * 0.04, $h * 0.09 );
			$ax = $rnd() > 0.5 ? $rx + $rw - $aw * 0.35 : $rx - $aw * 0.65;
			$ay = $by + $between( $bh * 0.12, $bh * 0.55 );

			$svg[] = sprintf(
				'<rect class="ph__solid" x="%.1f" y="%.1f" width="%.1f" height="%.1f"/>',
				$ax, $ay, $aw, $ah
			);
		}
	}

	/* ---------------------------------------------------------------
	 * Ground line
	 * -------------------------------------------------------------- */

	$svg[] = sprintf(
		'<path class="ph__ground" d="M0 %.1fH%.1f"/>',
		$ground,
		$w
	);

	/* ---------------------------------------------------------------
	 * Halftone field
	 * -------------------------------------------------------------- */

	$cols = 9;
	$rows = 4;
	$dgap = $w / 34;
	$dx0  = $w - ( $cols * $dgap ) - $margin * 0.5;
	$dy0  = $h * 0.09;
	$dots = array();

	for ( $c = 0; $c < $cols; $c++ ) {
		for ( $r = 0; $r < $rows; $r++ ) {
			// Fade out toward the left so it reads as a gradient of ink.
			$density = ( $c + 1 ) / $cols;
			if ( $rnd() > $density ) {
				continue;
			}
			$dots[] = sprintf(
				'<circle cx="%.1f" cy="%.1f" r="%.1f"/>',
				$dx0 + $c * $dgap,
				$dy0 + $r * $dgap,
				$between( 1.5, $dgap * 0.3 )
			);
		}
	}
	if ( $dots ) {
		$svg[] = '<g class="ph__dots">' . implode( '', $dots ) . '</g>';
	}

	/* ---------------------------------------------------------------
	 * A single strut across the composition
	 * -------------------------------------------------------------- */

	$svg[] = sprintf(
		'<path class="ph__strut" d="M%.1f %.1fL%.1f %.1f"/>',
		$margin * 0.5,
		$between( $h * 0.30, $h * 0.55 ),
		$w - $margin * 0.5,
		$between( $h * 0.16, $h * 0.40 )
	);

	return sprintf(
		'<svg class="ph ph--%s" viewBox="0 0 %s %s" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $variant ),
		$w,
		$h,
		implode( '', $svg )
	);
}

/**
 * Echo the generated cover.
 *
 * @param int    $post_id Project ID.
 * @param string $variant Canvas variant.
 */
function errands_the_placeholder( $post_id = null, $variant = 'card' ) {
	// Built entirely from numbers and fixed class names above.
	echo errands_placeholder_svg( $post_id, $variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * The ERRANDS mark.
 *
 * An ordered square frame with one solid volume displaced out of its corner —
 * the group's own subject: the thing that will not sit inside the system.
 * Drawn on a 32-unit grid with chunky strokes so it survives a 16px browser tab.
 *
 * Inline in the document, so it inherits the current palette.
 *
 * @return string SVG markup.
 */
function errands_mark_svg() {
	return '<svg class="mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
		. '<rect class="mark__frame" x="4.5" y="11.5" width="17" height="16" />'
		. '<rect class="mark__block" x="18" y="4" width="10" height="10" />'
		. '</svg>';
}
