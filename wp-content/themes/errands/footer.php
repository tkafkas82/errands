<?php
/**
 * Site footer.
 *
 * @package Errands
 */

$errands_series = get_terms( array(
	'taxonomy'   => 'project_series',
	'hide_empty' => true,
	'number'     => 6,
) );
?>
</main>

<footer class="site-footer">
	<div class="wrap">
		<div class="site-footer__grid">

			<div>
				<p class="site-footer__statement"><?php echo wp_kses_post( errands_mod( 'errands_statement' ) ); ?></p>
			</div>

			<div>
				<h2><?php esc_html_e( 'Series', 'errands' ); ?></h2>
				<ul>
					<?php if ( ! is_wp_error( $errands_series ) && $errands_series ) : ?>
						<?php foreach ( $errands_series as $errands_term ) : ?>
							<li><a href="<?php echo esc_url( get_term_link( $errands_term ) ); ?>"><?php echo esc_html( $errands_term->name ); ?></a></li>
						<?php endforeach; ?>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'project' ) ); ?>"><?php esc_html_e( 'All projects', 'errands' ); ?></a></li>
				</ul>
			</div>

			<div>
				<h2><?php esc_html_e( 'Elsewhere', 'errands' ); ?></h2>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					) );
				} else {
					?>
					<ul>
						<li><a href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'RSS feed', 'errands' ); ?></a></li>
					</ul>
					<?php
				}
				?>
			</div>
		</div>

		<div class="site-footer__bar">
			<p class="label">
				<?php
				printf(
					/* translators: %1$s: year, %2$s: site name. */
					esc_html__( '© %1$s %2$s. All rights reserved.', 'errands' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
			<p class="label"><?php echo esc_html( errands_mod( 'errands_locus' ) ); ?></p>
		</div>
	</div>
</footer>

<?php
// Lightbox shell. Populated by assets/js/main.js from the gallery figures.
?>
<div class="lb js-lb" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'errands' ); ?>">
	<div class="lb__bar">
		<p class="label js-lb-counter"></p>
		<button class="lb__close js-lb-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'errands' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
		</button>
	</div>
	<div class="lb__stage">
		<button class="lb__nav lb__nav--prev js-lb-prev" type="button" aria-label="<?php esc_attr_e( 'Previous image', 'errands' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
		</button>
		<img class="js-lb-img" src="" alt="">
		<button class="lb__nav lb__nav--next js-lb-next" type="button" aria-label="<?php esc_attr_e( 'Next image', 'errands' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
		</button>
	</div>
	<p class="lb__foot js-lb-caption"></p>
</div>

<?php wp_footer(); ?>
</body>
</html>
