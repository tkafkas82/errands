<?php
/**
 * Single blog post (the Notes section).
 *
 * @package Errands
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="project-hero">
				<?php the_post_thumbnail( 'errands-hero', array( 'sizes' => '100vw' ) ); ?>
			</div>
		<?php endif; ?>

		<header class="page-head">
			<div class="wrap">
				<p class="label"><?php echo esc_html( get_the_date() ); ?></p>
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
		</header>

		<div class="page-body">
			<div class="wrap">
				<div class="prose prose--lead"><?php the_content(); ?></div>
			</div>
		</div>

		<?php
		$errands_images = errands_gallery();
		if ( $errands_images ) :
			?>
			<div class="wrap">
				<div class="gal js-gal">
					<?php
					foreach ( $errands_images as $errands_image ) {
						errands_figure( $errands_image );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
