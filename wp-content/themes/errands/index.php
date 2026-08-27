<?php
/**
 * Fallback archive / blog index.
 *
 * @package Errands
 */

get_header();
?>

<header class="page-head">
	<div class="wrap">
		<p class="label"><?php esc_html_e( 'Journal', 'errands' ); ?></p>
		<h1 class="page-title">
			<?php
			if ( is_home() ) {
				esc_html_e( 'Notes', 'errands' );
			} else {
				the_archive_title();
			}
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
							<p class="label"><?php echo esc_html( get_the_date() ); ?></p>
							<h2 class="results__title"><?php the_title(); ?></h2>
							<?php if ( has_excerpt() || get_the_content() ) : ?>
								<p class="results__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
							<?php endif; ?>
						</a>
					</li>
				<?php endwhile; ?>
			</ul>

			<div style="padding-top:2.5rem">
				<?php
				the_posts_pagination( array(
					'mid_size'  => 1,
					'prev_text' => esc_html__( '← Newer', 'errands' ),
					'next_text' => esc_html__( 'Older →', 'errands' ),
				) );
				?>
			</div>
		<?php else : ?>
			<div class="empty">
				<p class="empty__code">000</p>
				<p><?php esc_html_e( 'Nothing here yet.', 'errands' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
