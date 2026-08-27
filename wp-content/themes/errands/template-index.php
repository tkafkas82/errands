<?php
/**
 * Template Name: Project index by year
 * Template Post Type: page
 *
 * A dense, scannable list of everything, grouped by year.
 *
 * @package Errands
 */

get_header();

$errands_projects = get_posts( array(
	'post_type'      => 'project',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

// Group by year.
$errands_by_year = array();
foreach ( $errands_projects as $errands_project ) {
	$errands_by_year[ get_the_date( 'Y', $errands_project ) ][] = $errands_project;
}

the_post();
?>

<header class="page-head">
	<div class="wrap">
		<p class="label">
			<?php
			printf(
				/* translators: %d: total projects. */
				esc_html( _n( '%d project', '%d projects', count( $errands_projects ), 'errands' ) ),
				esc_html( count( $errands_projects ) )
			);
			?>
		</p>
		<h1 class="page-title"><?php the_title(); ?></h1>
	</div>
</header>

<?php if ( get_the_content() ) : ?>
	<div class="wrap" style="padding-bottom:clamp(1rem,3vw,2rem)">
		<div class="prose" style="max-width:var(--prose)"><?php the_content(); ?></div>
	</div>
<?php endif; ?>

<div class="page-body">
	<div class="wrap">
		<?php foreach ( $errands_by_year as $errands_year => $errands_group ) : ?>
			<section class="year-group">
				<div class="year-group__head">
					<h2 class="year-group__year"><?php echo esc_html( $errands_year ); ?></h2>
					<p class="label">
						<?php
						printf(
							/* translators: %d: projects in this year. */
							esc_html( _n( '%d project', '%d projects', count( $errands_group ), 'errands' ) ),
							esc_html( count( $errands_group ) )
						);
						?>
					</p>
				</div>

				<ul class="idx">
					<?php foreach ( $errands_group as $errands_project ) : ?>
						<?php
						$errands_cover = errands_cover_id( $errands_project->ID );
						$errands_n     = errands_image_count( $errands_project->ID );
						?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $errands_project ) ); ?>">
								<?php
								if ( $errands_cover ) {
									echo wp_get_attachment_image( $errands_cover, 'errands-tile', false, array(
										'class'   => 'idx__thumb',
										'alt'     => '',
										'loading' => 'lazy',
									) );
								} else {
									echo '<span class="idx__thumb idx__thumb--drawn">';
									errands_the_placeholder( $errands_project->ID, 'tile' );
									echo '</span>';
								}
								?>
								<span>
									<span class="idx__title"><?php echo esc_html( get_the_title( $errands_project ) ); ?></span>
									<?php if ( errands_series_list( $errands_project->ID ) ) : ?>
										<span class="label" style="display:block;margin-top:0.2rem"><?php echo esc_html( errands_series_list( $errands_project->ID ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="idx__n"><?php echo esc_html( $errands_n ? sprintf( '%02d', $errands_n ) : '—' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
</div>

<?php get_footer(); ?>
