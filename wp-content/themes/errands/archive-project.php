<?php
/**
 * Projects archive: every project, filterable by series and year.
 *
 * Filtering is client-side because the whole archive is loaded at once
 * (see errands_archive_query) — no round trip needed for 21 cards.
 *
 * @package Errands
 */

get_header();

$errands_total = (int) $GLOBALS['wp_query']->post_count;

// Build the facet counts from the posts actually on the page.
$errands_series_counts = array();
$errands_series_names  = array();
$errands_year_counts   = array();

foreach ( $GLOBALS['wp_query']->posts as $errands_post ) {
	$errands_year = get_the_date( 'Y', $errands_post );
	$errands_year_counts[ $errands_year ] = ( $errands_year_counts[ $errands_year ] ?? 0 ) + 1;

	$errands_terms = get_the_terms( $errands_post, 'project_series' );
	if ( ! is_wp_error( $errands_terms ) && $errands_terms ) {
		foreach ( $errands_terms as $errands_term ) {
			$errands_series_counts[ $errands_term->slug ] = ( $errands_series_counts[ $errands_term->slug ] ?? 0 ) + 1;
			$errands_series_names[ $errands_term->slug ]  = $errands_term->name;
		}
	}
}

krsort( $errands_year_counts );
arsort( $errands_series_counts );
?>

<section class="page-head">
	<div class="wrap">
		<p class="label"><?php esc_html_e( 'Archive', 'errands' ); ?></p>
		<h1 class="page-title">
			<?php
			if ( is_tax( 'project_series' ) ) {
				single_term_title();
			} else {
				esc_html_e( 'Projects', 'errands' );
			}
			?>
		</h1>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap">

		<?php if ( $errands_series_counts || count( $errands_year_counts ) > 1 ) : ?>
			<div class="filters js-filters" role="group" aria-label="<?php esc_attr_e( 'Filter projects', 'errands' ); ?>">
				<button class="chip" type="button" data-filter="all" aria-pressed="true">
					<?php esc_html_e( 'All', 'errands' ); ?><span class="n"><?php echo esc_html( $errands_total ); ?></span>
				</button>

				<?php foreach ( $errands_series_counts as $errands_slug => $errands_count ) : ?>
					<button class="chip" type="button" data-filter="series" data-value="<?php echo esc_attr( $errands_slug ); ?>" aria-pressed="false">
						<?php echo esc_html( $errands_series_names[ $errands_slug ] ); ?><span class="n"><?php echo esc_html( $errands_count ); ?></span>
					</button>
				<?php endforeach; ?>

				<?php if ( $errands_series_counts && count( $errands_year_counts ) > 1 ) : ?>
					<span class="filters__sep" aria-hidden="true"></span>
				<?php endif; ?>

				<?php foreach ( $errands_year_counts as $errands_year => $errands_count ) : ?>
					<button class="chip" type="button" data-filter="year" data-value="<?php echo esc_attr( $errands_year ); ?>" aria-pressed="false">
						<?php echo esc_html( $errands_year ); ?><span class="n"><?php echo esc_html( $errands_count ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="grid js-grid">
				<?php
				$errands_i = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card', null, array(
						'index'        => $errands_i,
						'total'        => $errands_total,
						'show_excerpt' => false,
					) );
					$errands_i++;
				endwhile;
				?>
			</div>
			<p class="label js-filter-empty" style="display:none;padding-block:3rem">
				<?php esc_html_e( 'Nothing matches that filter.', 'errands' ); ?>
			</p>
		<?php else : ?>
			<div class="empty">
				<p class="empty__code">000</p>
				<p><?php esc_html_e( 'No projects here yet.', 'errands' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
