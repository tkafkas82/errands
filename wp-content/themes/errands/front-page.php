<?php
/**
 * Front page: statement, then the work.
 *
 * @package Errands
 */

get_header();

$errands_featured = new WP_Query( array(
	'post_type'           => 'project',
	'posts_per_page'      => 10,
	'ignore_sticky_posts' => true,
) );

$errands_all = wp_count_posts( 'project' );
$errands_n   = isset( $errands_all->publish ) ? (int) $errands_all->publish : 0;
?>

<section class="hero">
	<div class="wrap">
		<h1 class="hero__statement"><?php echo wp_kses_post( errands_mod( 'errands_statement' ) ); ?></h1>
		<p class="hero__standfirst"><?php echo wp_kses_post( errands_mod( 'errands_standfirst' ) ); ?></p>
		<div class="hero__meta">
			<span class="label"><?php echo esc_html( errands_mod( 'errands_locus' ) ); ?></span>
			<span class="label">
				<?php
				printf(
					/* translators: %d: number of projects. */
					esc_html( _n( '%d project', '%d projects', $errands_n, 'errands' ) ),
					esc_html( $errands_n )
				);
				?>
			</span>
		</div>
	</div>
</section>

<?php if ( $errands_featured->have_posts() ) : ?>
	<section class="section">
		<div class="wrap">
			<div class="section__head">
				<h2 class="section__title">
					<?php esc_html_e( 'Selected work', 'errands' ); ?>
					<span class="count"><?php echo esc_html( str_pad( (string) $errands_n, 3, '0', STR_PAD_LEFT ) ); ?></span>
				</h2>
				<a class="more-link" href="<?php echo esc_url( get_post_type_archive_link( 'project' ) ); ?>">
					<?php esc_html_e( 'All projects', 'errands' ); ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
			</div>

			<div class="grid">
				<?php
				$errands_i     = 0;
				$errands_total = $errands_featured->post_count;
				while ( $errands_featured->have_posts() ) :
					$errands_featured->the_post();
					get_template_part( 'template-parts/card', null, array(
						'index'        => $errands_i,
						'total'        => $errands_total,
						'show_excerpt' => true,
					) );
					$errands_i++;
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php else : ?>
	<section class="wrap empty">
		<p class="empty__code">000</p>
		<p><?php esc_html_e( 'No projects yet. Add one under Projects in the dashboard.', 'errands' ); ?></p>
	</section>
<?php endif; ?>

<?php get_footer(); ?>
