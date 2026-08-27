<?php
/**
 * Single project: hero, prose, gallery.
 *
 * @package Errands
 */

get_header();

while ( have_posts() ) :
	the_post();

	$errands_cover  = errands_cover_id();
	$errands_images = errands_gallery();
	$errands_docs   = errands_documents();
	$errands_series = get_the_terms( get_the_ID(), 'project_series' );
	$errands_count  = errands_image_count();
	?>

	<article>

		<div class="project-hero <?php echo $errands_cover ? '' : 'project-hero--drawn'; ?>">
			<?php
			if ( $errands_cover ) {
				echo wp_get_attachment_image( $errands_cover, 'errands-hero', false, array(
					'alt'           => get_the_title(),
					'fetchpriority' => 'high',
					'decoding'      => 'async',
					'sizes'         => '100vw',
				) );
			} else {
				errands_the_placeholder( get_the_ID(), 'hero' );
			}
			?>
		</div>

		<header class="project-head">
			<div class="wrap">
				<div class="project-head__meta label">
					<span><?php echo esc_html( get_the_date( 'F Y' ) ); ?></span>
					<?php if ( ! is_wp_error( $errands_series ) && $errands_series ) : ?>
						<span class="dot" aria-hidden="true"></span>
						<span>
							<?php
							$errands_links = array();
							foreach ( $errands_series as $errands_term ) {
								$errands_links[] = '<a href="' . esc_url( get_term_link( $errands_term ) ) . '">' . esc_html( $errands_term->name ) . '</a>';
							}
							echo wp_kses_post( implode( ', ', $errands_links ) );
							?>
						</span>
					<?php endif; ?>
					<?php if ( $errands_count ) : ?>
						<span class="dot" aria-hidden="true"></span>
						<span><?php echo esc_html( sprintf( /* translators: %d: image count. */ _n( '%d image', '%d images', $errands_count, 'errands' ), $errands_count ) ); ?></span>
					<?php endif; ?>
				</div>

				<h1 class="project-title"><?php the_title(); ?></h1>
			</div>
		</header>

		<div class="wrap project-body">
			<div class="prose prose--lead">
				<?php
				the_content();

				if ( ! get_the_content() ) {
					echo '<p class="label">' . esc_html__( 'No description recorded for this project.', 'errands' ) . '</p>';
				}
				?>
			</div>

			<aside class="aside">
				<dl class="facts">
					<div class="facts__row">
						<dt><?php esc_html_e( 'Year', 'errands' ); ?></dt>
						<dd><?php echo esc_html( get_the_date( 'Y' ) ); ?></dd>
					</div>
					<?php if ( ! is_wp_error( $errands_series ) && $errands_series ) : ?>
						<div class="facts__row">
							<dt><?php esc_html_e( 'Series', 'errands' ); ?></dt>
							<dd><?php echo wp_kses_post( implode( ', ', $errands_links ) ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $errands_count ) : ?>
						<div class="facts__row">
							<dt><?php esc_html_e( 'Images', 'errands' ); ?></dt>
							<dd><?php echo esc_html( $errands_count ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( $errands_docs ) : ?>
					<div class="docs">
						<p class="label" style="margin-bottom:0.7rem"><?php esc_html_e( 'Documents', 'errands' ); ?></p>
						<?php foreach ( $errands_docs as $errands_doc ) : ?>
							<a class="doc-link" href="<?php echo esc_url( wp_get_attachment_url( $errands_doc->ID ) ); ?>" target="_blank" rel="noopener">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M14 3v5h5M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/></svg>
								<span><?php echo esc_html( get_the_title( $errands_doc->ID ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php if ( $errands_images ) : ?>
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

		<?php
		$errands_prev = get_previous_post();
		$errands_next = get_next_post();
		?>
		<nav class="pn" aria-label="<?php esc_attr_e( 'Project navigation', 'errands' ); ?>">
			<?php if ( $errands_prev ) : ?>
				<a class="pn__link" href="<?php echo esc_url( get_permalink( $errands_prev ) ); ?>">
					<span class="label"><?php esc_html_e( '← Previous', 'errands' ); ?></span>
					<strong><?php echo esc_html( get_the_title( $errands_prev ) ); ?></strong>
				</a>
			<?php else : ?>
				<span class="pn__link pn__empty"></span>
			<?php endif; ?>

			<?php if ( $errands_next ) : ?>
				<a class="pn__link pn__link--next" href="<?php echo esc_url( get_permalink( $errands_next ) ); ?>">
					<span class="label"><?php esc_html_e( 'Next →', 'errands' ); ?></span>
					<strong><?php echo esc_html( get_the_title( $errands_next ) ); ?></strong>
				</a>
			<?php else : ?>
				<span class="pn__link pn__empty"></span>
			<?php endif; ?>
		</nav>

	</article>

	<?php
endwhile;

get_footer();
