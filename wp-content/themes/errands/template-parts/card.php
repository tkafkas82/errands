<?php
/**
 * One project card.
 *
 * @package Errands
 *
 * Expects $args:
 *   'index' => int   Zero-based position in the grid.
 *   'total' => int   Total cards in the grid.
 *   'show_excerpt' => bool
 */

$errands_i     = isset( $args['index'] ) ? (int) $args['index'] : 0;
$errands_total = isset( $args['total'] ) ? (int) $args['total'] : 0;
$errands_span  = errands_card_span( $errands_i, $errands_total );
$errands_cover = errands_cover_id();
$errands_count = errands_image_count();
$errands_terms = get_the_terms( get_the_ID(), 'project_series' );
$errands_slugs = ( ! is_wp_error( $errands_terms ) && $errands_terms )
	? implode( ' ', wp_list_pluck( $errands_terms, 'slug' ) )
	: '';
?>
<article
	class="card card--<?php echo esc_attr( $errands_span ); ?>"
	data-series="<?php echo esc_attr( $errands_slugs ); ?>"
	data-year="<?php echo esc_attr( get_the_date( 'Y' ) ); ?>"
>
	<?php
	$errands_ph_variant = ( 'wide' === $errands_span || 'full' === $errands_span ) ? 'wide' : 'card';
	?>
	<a class="card__media <?php echo $errands_cover ? 'card__media--photo' : 'card__media--drawn'; ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		// The drawn cover is always in the markup. With no photograph it simply
		// shows; with one it sits behind as a fallback, revealed by JS if the
		// image fails to load. It has to be inline rather than fetched on
		// demand, because the usual reason an image fails is that the server
		// has gone away — at which point fetching a replacement would fail too.
		errands_the_placeholder( get_the_ID(), $errands_ph_variant );

		if ( $errands_cover ) {
			echo wp_get_attachment_image( $errands_cover, 'errands-card', false, array(
				'alt'      => '',
				'loading'  => $errands_i < 4 ? 'eager' : 'lazy',
				'decoding' => 'async',
				'sizes'    => '(max-width: 620px) 100vw, (max-width: 1040px) 50vw, 33vw',
			) );
		}

		if ( $errands_count > 1 ) {
			printf(
				'<span class="card__count">%s</span>',
				esc_html( sprintf( /* translators: %d: number of images. */ _n( '%d image', '%d images', $errands_count, 'errands' ), $errands_count ) )
			);
		}
		?>
	</a>

	<div class="card__body">
		<p class="card__meta label">
			<span><?php echo esc_html( get_the_date( 'Y' ) ); ?></span>
			<?php if ( errands_series_list() ) : ?>
				<span class="dot" aria-hidden="true"></span>
				<span><?php echo esc_html( errands_series_list() ); ?></span>
			<?php endif; ?>
		</p>

		<h3 class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

		<?php if ( ! empty( $args['show_excerpt'] ) && has_excerpt() ) : ?>
			<p class="card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>
	</div>
</article>
