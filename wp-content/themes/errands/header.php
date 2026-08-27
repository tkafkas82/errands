<?php
/**
 * Site header.
 *
 * @package Errands
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
	// Apply the stored theme choice before first paint to avoid a flash.
	?>
	<script>
		(function () {
			try {
				var t = localStorage.getItem('errands-theme');
				if (t === 'dark' || t === 'light') {
					document.documentElement.setAttribute('data-theme', t);
				}
			} catch (e) {}
		})();
	</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'errands' ); ?></a>

<header class="site-header">
	<div class="wrap site-header__inner">

		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				echo errands_mark_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<span class="brand__word">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
			}
			?>
		</a>

		<nav class="nav" aria-label="<?php esc_attr_e( 'Primary', 'errands' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav__list',
					'depth'          => 1,
					'fallback_cb'    => false,
				) );
			} else {
				// Sensible default before a menu is assigned in Appearance → Menus.
				$archive = get_post_type_archive_link( 'project' );
				$about   = get_page_by_path( 'about' );
				$index   = get_page_by_path( 'index' );
				$exhib   = get_page_by_path( 'exhibitions' );
				?>
				<ul class="nav__list">
					<li><a class="<?php echo is_post_type_archive( 'project' ) || is_tax( 'project_series' ) ? 'is-current' : ''; ?>" href="<?php echo esc_url( $archive ); ?>"><?php esc_html_e( 'Projects', 'errands' ); ?></a></li>
					<?php if ( $exhib ) : ?>
						<li><a class="<?php echo is_page( $exhib->ID ) ? 'is-current' : ''; ?>" href="<?php echo esc_url( get_permalink( $exhib ) ); ?>"><?php esc_html_e( 'Exhibitions', 'errands' ); ?></a></li>
					<?php endif; ?>
					<?php if ( $index ) : ?>
						<li><a class="<?php echo is_page( $index->ID ) ? 'is-current' : ''; ?>" href="<?php echo esc_url( get_permalink( $index ) ); ?>"><?php esc_html_e( 'Index', 'errands' ); ?></a></li>
					<?php endif; ?>
					<?php if ( $about ) : ?>
						<li><a class="<?php echo is_page( $about->ID ) ? 'is-current' : ''; ?>" href="<?php echo esc_url( get_permalink( $about ) ); ?>"><?php esc_html_e( 'About', 'errands' ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php
			}
			?>

			<button class="icon-btn js-search-open" type="button" aria-label="<?php esc_attr_e( 'Search', 'errands' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			</button>

			<button class="icon-btn theme-toggle js-theme" type="button" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'errands' ); ?>">
				<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
				<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
			</button>

			<button class="icon-btn nav-toggle js-nav" type="button" aria-expanded="false" aria-label="<?php esc_attr_e( 'Menu', 'errands' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
			</button>
		</nav>
	</div>
</header>

<div class="search-overlay js-search-overlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'errands' ); ?>">
	<?php get_search_form(); ?>
	<p class="label"><?php esc_html_e( 'Press Esc to close', 'errands' ); ?></p>
</div>

<main id="main">
