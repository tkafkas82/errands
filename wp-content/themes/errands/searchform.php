<?php
/**
 * Search form.
 *
 * @package Errands
 */
?>
<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="errands-s"><?php esc_html_e( 'Search', 'errands' ); ?></label>
	<input
		id="errands-s"
		type="search"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search projects…', 'errands' ); ?>"
		autocomplete="off"
	>
	<button class="icon-btn" type="submit" aria-label="<?php esc_attr_e( 'Submit search', 'errands' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
	</button>
</form>
