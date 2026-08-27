<?php
/**
 * Static page.
 *
 * @package Errands
 */

get_header();

while ( have_posts() ) :
	the_post();

	$errands_cover = (int) get_post_thumbnail_id();
	?>

	<article>
		<?php if ( $errands_cover ) : ?>
			<div class="project-hero">
				<?php
				echo wp_get_attachment_image( $errands_cover, 'errands-hero', false, array(
					'alt'           => get_the_title(),
					'fetchpriority' => 'high',
					'sizes'         => '100vw',
				) );
				?>
			</div>
		<?php endif; ?>

		<header class="page-head">
			<div class="wrap">
				<p class="label"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
		</header>

		<div class="page-body">
			<div class="wrap">
				<div class="prose prose--lead">
					<?php the_content(); ?>
				</div>
			</div>
		</div>

		<?php
		// A page may still carry attached images (the About page does).
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
