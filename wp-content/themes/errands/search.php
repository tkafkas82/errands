<?php
/**
 * Search results.
 *
 * @package Errands
 */

get_header();
?>

<header class="page-head">
	<div class="wrap">
		<p class="label">
			<?php
			printf(
				/* translators: %d: number of results. */
				esc_html( _n( '%d result', '%d results', (int) $GLOBALS['wp_query']->found_posts, 'errands' ) ),
				esc_html( (int) $GLOBALS['wp_query']->found_posts )
			);
			?>
		</p>
		<h1 class="page-title">
			<?php
			/* translators: %s: search query. */
			printf( esc_html__( 'Search: %s', 'errands' ), '<span style="color:var(--accent)">' . esc_html( get_search_query() ) . '</span>' );
			?>
		</h1>
	</div>
</header>

<div class="page-body">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<ul class="results">
				<?php while ( have_posts() ) : the_post(); ?>
					<li>
						<a href="<?php the_permalink(); ?>">
							<p class="label">
								<?php
								$errands_obj = get_post_type_object( get_post_type() );
								echo esc_html( $errands_obj ? $errands_obj->labels->singular_name : get_post_type() );
								?>
								· <?php echo esc_html( get_the_date( 'Y' ) ); ?>
							</p>
							<h2 class="results__title"><?php the_title(); ?></h2>
							<p class="results__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
						</a>
					</li>
				<?php endwhile; ?>
			</ul>

			<div style="padding-top:2.5rem">
				<?php
				the_posts_pagination( array(
					'mid_size'  => 1,
					'prev_text' => esc_html__( '← Previous', 'errands' ),
					'next_text' => esc_html__( 'Next →', 'errands' ),
				) );
				?>
			</div>
		<?php else : ?>
			<div class="empty">
				<p class="empty__code">000</p>
				<p><?php esc_html_e( 'No matches. Try a shorter term.', 'errands' ); ?></p>
				<div style="max-width:420px;margin:2rem auto 0"><?php get_search_form(); ?></div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
