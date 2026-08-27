<?php
/**
 * 404.
 *
 * @package Errands
 */

get_header();
?>

<div class="wrap empty">
	<p class="empty__code">404</p>
	<p><?php esc_html_e( 'That page has been abandoned, forgotten, or never existed.', 'errands' ); ?></p>
	<p style="margin-top:2rem">
		<a class="more-link" href="<?php echo esc_url( get_post_type_archive_link( 'project' ) ); ?>">
			<?php esc_html_e( 'Browse the projects', 'errands' ); ?>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
		</a>
	</p>
</div>

<?php get_footer(); ?>
