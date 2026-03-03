<?php
/**
 * Plugin Name: MK Header Standalone
 * Description: Standalone replacement for Jupiter mk_header shortcode.
 *              Renders a navigation header bar with customizable colors, hover styles,
 *              and menu location — without requiring the Jupiter theme or WPBakery.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_header' ) ) {
		add_shortcode( 'mk_header', 'sol_render_mk_header' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: output base CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'mk_header' ) ) {
		add_action( 'wp_head', 'sol_mk_header_base_css' );
	}
} );

/* ---- Per-instance CSS buffer ---- */
function sol_mk_header_collect_instance_css( $css = null ) {
	static $buffer = '';
	if ( null !== $css ) {
		$buffer .= $css . "\n";
	}
	return $buffer;
}
function sol_mk_header_output_instance_css() {
	$css = sol_mk_header_collect_instance_css();
	if ( ! empty( $css ) ) {
		printf( '<style id="mk-header-instance-css">%s</style>', $css );
	}
}

/* ================================================================
 * [mk_header]  –  Header navigation bar shortcode
 *
 * Jupiter output:
 *   <header id="mk-header-{id}" class="mk-header header-style-1 header-align-{align} ... mk-header-shortcode">
 *     <div class="mk-header-holder">
 *       <div class="mk-header-inner">
 *         <div class="mk-header-bg"></div>
 *         <div class="mk-header-nav-container menu-hover-style-{hover_styles}">
 *           <nav class="mk-main-navigation">
 *             <ul class="main-navigation-ul">…</ul>
 *           </nav>
 *         </div>
 *       </div>
 *     </div>
 *   </header>
 * ============================================================== */
function sol_render_mk_header( $atts ) {
	$atts = shortcode_atts( array(
		'style'           => 1,
		'align'           => 'left',
		'logo'            => 'true',
		'burger_icon'     => 'true',
		'woo_cart'        => 'true',
		'search_icon'     => 'true',
		'hover_styles'    => 1,
		'menu_location'   => 'primary-menu',
		'bg_color'        => '',
		'border_color'    => '',
		'text_color'      => '',
		'text_hover_skin' => '',
		'visibility'      => '',
		'el_class'        => '',
	), $atts, 'mk_header' );

	$id            = wp_unique_id( 'mk-hdr-' );
	$style         = absint( $atts['style'] );
	$align         = esc_attr( $atts['align'] );
	$hover_styles  = absint( $atts['hover_styles'] );
	$menu_location = esc_attr( $atts['menu_location'] );
	$bg_color      = sanitize_hex_color( $atts['bg_color'] );
	$border_color  = sanitize_hex_color( $atts['border_color'] );
	$text_color    = sanitize_hex_color( $atts['text_color'] );
	$hover_skin    = sanitize_hex_color( $atts['text_hover_skin'] );
	$el_class      = esc_attr( $atts['el_class'] );
	$visibility    = esc_attr( $atts['visibility'] );

	$show_logo    = ( 'false' !== $atts['logo'] );
	$show_burger  = ( 'false' !== $atts['burger_icon'] );
	$show_search  = ( 'false' !== $atts['search_icon'] );
	$show_cart    = ( 'false' !== $atts['woo_cart'] );

	// Enqueue CSS + JS.
	add_action( 'wp_footer', 'sol_mk_header_base_css', 1 );
	add_action( 'wp_footer', 'sol_mk_header_output_instance_css', 2 );
	add_action( 'wp_footer', 'sol_mk_header_js', 20 );

	// ---- Build per-instance CSS ----
	$sel = ".mk-header-shortcode#mk-header-{$id}";
	$instance_css = '';

	// Base: remove bottom border.
	$instance_css .= "{$sel}, {$sel} .mk-header-bg { border-bottom: none !important; }\n";

	// Border color.
	if ( $border_color ) {
		$instance_css .= "{$sel} { border-top: 1px solid {$border_color}; border-bottom: 1px solid {$border_color}; }\n";
	}

	// Background color.
	if ( $bg_color ) {
		$instance_css .= "{$sel} .mk-header-bg { background-color: {$bg_color} !important; }\n";
	}

	// Text color (menu links, search, cart, burger).
	if ( $text_color ) {
		$instance_css .= <<<CSS
{$sel} .main-navigation-ul > li.menu-item > a.menu-item-link,
{$sel} .mk-search-trigger,
{$sel} .mk-header-cart-count,
{$sel} .mk-header-start-tour { color: {$text_color}; }
{$sel} .mk-shoping-cart-link svg,
{$sel} .mk-header-social svg { fill: {$text_color}; }
{$sel} .mk-css-icon-close div,
{$sel} .mk-css-icon-menu div { background-color: {$text_color}; }
CSS;
	}

	// Hover skin (search, submenu arrows).
	if ( $hover_skin ) {
		$instance_css .= <<<CSS
{$sel} .mk-search-trigger:hover,
{$sel} .mk-header-start-tour:hover { color: {$hover_skin} !important; }
{$sel} .main-navigation-ul > li.no-mega-menu ul.sub-menu:after,
{$sel} .main-navigation-ul > li.has-mega-menu > ul.sub-menu:after { background-color: {$hover_skin} !important; }
CSS;
	}

	// ---- Hover style specific CSS ----
	if ( $hover_skin ) {
		$hs = ".menu-hover-style-{$hover_styles}";

		if ( 1 === $hover_styles ) {
			$instance_css .= <<<CSS
{$sel} {$hs} .main-navigation-ul li.menu-item > a.menu-item-link:hover,
{$sel} {$hs} .main-navigation-ul li.menu-item:hover > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul li.current-menu-item > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul li.current-menu-ancestor > a.menu-item-link { color: {$hover_skin} !important; }
{$sel} {$hs} .main-navigation-ul > li.dropdownOpen > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.active > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.open > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.menu-item > a:hover,
{$sel} {$hs} .main-navigation-ul > li.current-menu-item > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.current-menu-ancestor > a.menu-item-link { border-top-color: {$hover_skin} !important; }
CSS;
		}

		if ( 2 === $hover_styles ) {
			$instance_css .= <<<CSS
{$sel} {$hs} .main-navigation-ul > li.menu-item > a.menu-item-link:hover,
{$sel} {$hs} .main-navigation-ul > li.menu-item:hover > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.current-menu-item > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.current-menu-ancestor > a.menu-item-link { color: {$hover_skin} !important; }
CSS;
		}

		if ( 3 === $hover_styles ) {
			$instance_css .= <<<CSS
{$sel} {$hs} .main-navigation-ul > li.menu-item > a.menu-item-link:hover,
{$sel} {$hs} .main-navigation-ul > li.menu-item:hover > a.menu-item-link { border: 2px solid {$hover_skin} !important; }
{$sel} {$hs} .main-navigation-ul > li.current-menu-item > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul > li.current-menu-ancestor > a.menu-item-link { border: 2px solid {$hover_skin} !important; background-color: {$hover_skin} !important; color: #fff !important; }
CSS;
		}

		if ( 4 === $hover_styles ) {
			$instance_css .= <<<CSS
{$sel} {$hs} .main-navigation-ul li.menu-item > a.menu-item-link:hover,
{$sel} {$hs} .main-navigation-ul li.menu-item:hover > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul li.current-menu-item > a.menu-item-link,
{$sel} {$hs} .main-navigation-ul li.current-menu-ancestor > a.menu-item-link { background-color: {$hover_skin} !important; }
CSS;
		}

		if ( 5 === $hover_styles ) {
			$instance_css .= <<<CSS
{$sel} {$hs} .main-navigation-ul > li.menu-item > a.menu-item-link:after { background-color: {$hover_skin} !important; }
CSS;
		}
	}

	sol_mk_header_collect_instance_css( $instance_css );

	// ---- Build header classes ----
	$classes   = array();
	$classes[] = 'mk-header';
	$classes[] = 'header-style-' . $style;
	$classes[] = 'header-align-' . $align;
	$classes[] = 'menu-hover-' . $hover_styles;
	$classes[] = 'js-header-shortcode';
	$classes[] = 'mk-header-shortcode';
	if ( $visibility ) {
		$classes[] = 'jupiter-donut-' . $visibility;
	}
	if ( $el_class ) {
		$classes[] = $el_class;
	}

	// ---- Render menu ----
	$menu_html = wp_nav_menu( array(
		'theme_location'  => $menu_location,
		'container'       => 'nav',
		'container_class' => 'mk-main-navigation js-main-nav',
		'menu_class'      => 'main-navigation-ul',
		'echo'            => false,
		'fallback_cb'     => false,
		'link_before'     => '',
		'link_after'      => '',
		'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
		'walker'          => class_exists( 'mk_main_menu' ) ? new mk_main_menu() : null,
	) );

	// If the menu location doesn't exist or is empty, try by slug.
	if ( empty( $menu_html ) ) {
		$menu_html = wp_nav_menu( array(
			'menu'            => $menu_location,
			'container'       => 'nav',
			'container_class' => 'mk-main-navigation js-main-nav',
			'menu_class'      => 'main-navigation-ul',
			'echo'            => false,
			'fallback_cb'     => false,
			'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
		) );
	}

	ob_start();
	?>
	<header id="mk-header-<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<div class="mk-header-holder">
			<div class="mk-header-inner">

				<div class="mk-header-bg"></div>

				<div class="mk-header-nav-container one-row-style menu-hover-style-<?php echo esc_attr( $hover_styles ); ?>">
					<?php
					// Main navigation.
					if ( $menu_html ) {
						echo $menu_html;
					}
					?>
				</div>

				<?php if ( $show_logo ) : ?>
					<div class="header-logo">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<?php
							if ( has_custom_logo() ) {
								the_custom_logo();
							} else {
								echo '<span class="site-title">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
							}
							?>
						</a>
					</div>
				<?php endif; ?>

			</div>

			<div class="mk-responsive-wrap">
				<button class="mk-nav-responsive-link" aria-label="<?php esc_attr_e( 'Toggle Menu', 'mk-header' ); ?>">
					<div class="mk-css-icon-menu">
						<div class="mk-css-icon-menu-line-1"></div>
						<div class="mk-css-icon-menu-line-2"></div>
						<div class="mk-css-icon-menu-line-3"></div>
					</div>
				</button>
				<?php
				if ( $menu_html ) {
					// Responsive menu: reuse the same menu.
					wp_nav_menu( array(
						'theme_location'  => $menu_location,
						'container'       => 'nav',
						'container_class' => 'mk-responsive-nav-container',
						'menu_class'      => 'mk-responsive-nav',
						'echo'            => true,
						'fallback_cb'     => false,
						'depth'           => 3,
						'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
					) );
				}
				?>
			</div>

		</div>
	</header>
	<?php

	return ob_get_clean();
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_mk_header_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-header-base-css">
		/* ---- Header reset ---- */
		.mk-header-shortcode {
			background: transparent !important;
			position: relative;
			z-index: 999;
			width: 100%;
		}
		.mk-header-shortcode *,
		.mk-header-shortcode *::before,
		.mk-header-shortcode *::after {
			box-sizing: border-box;
		}

		/* ---- Header holder ---- */
		.mk-header-shortcode .mk-header-holder {
			position: relative;
		}

		/* ---- Header inner ---- */
		.mk-header-shortcode .mk-header-inner {
			position: relative;
			display: flex;
			align-items: center;
			min-height: 60px;
		}

		/* ---- Header background overlay ---- */
		.mk-header-shortcode .mk-header-bg {
			position: absolute;
			top: 0; left: 0; right: 0; bottom: 0;
			z-index: 0;
		}

		/* ---- Nav container ---- */
		.mk-header-shortcode .mk-header-nav-container {
			position: relative;
			z-index: 1;
			flex: 1;
			display: flex;
			align-items: center;
		}

		/* ---- Alignment ---- */
		.mk-header-shortcode.header-align-left .mk-header-nav-container { justify-content: flex-start; }
		.mk-header-shortcode.header-align-center .mk-header-nav-container { justify-content: center; }
		.mk-header-shortcode.header-align-right .mk-header-nav-container { justify-content: flex-end; }

		/* ---- Navigation ---- */
		.mk-header-shortcode .mk-main-navigation {
			position: relative;
			z-index: 1;
		}
		.mk-header-shortcode .main-navigation-ul {
			list-style: none;
			margin: 0;
			padding: 0;
			display: flex;
			align-items: center;
		}
		.mk-header-shortcode .main-navigation-ul > li.menu-item {
			position: relative;
			margin: 0;
			padding: 0;
		}
		.mk-header-shortcode .main-navigation-ul > li.menu-item > a.menu-item-link,
		.mk-header-shortcode .main-navigation-ul > li.menu-item > a {
			display: block;
			padding: 20px 16px;
			text-decoration: none;
			font-size: 13px;
			font-weight: 600;
			letter-spacing: 1px;
			text-transform: uppercase;
			transition: all 0.3s ease;
			white-space: nowrap;
			color: inherit;
		}
		.mk-header-shortcode .main-navigation-ul > li.menu-item > a:hover {
			text-decoration: none;
		}

		/* ---- Sub-menus (dropdowns) ---- */
		.mk-header-shortcode .main-navigation-ul li.menu-item ul.sub-menu {
			display: none;
			position: absolute;
			top: 100%;
			left: 0;
			min-width: 200px;
			background: #fff;
			box-shadow: 0 4px 12px rgba(0,0,0,0.12);
			list-style: none;
			margin: 0;
			padding: 8px 0;
			z-index: 9999;
		}
		.mk-header-shortcode .main-navigation-ul li.menu-item:hover > ul.sub-menu {
			display: block;
		}
		.mk-header-shortcode .main-navigation-ul li.menu-item ul.sub-menu li {
			margin: 0;
			padding: 0;
		}
		.mk-header-shortcode .main-navigation-ul li.menu-item ul.sub-menu li a {
			display: block;
			padding: 8px 20px;
			font-size: 13px;
			color: #333;
			text-decoration: none;
			transition: background 0.2s ease, color 0.2s ease;
		}
		.mk-header-shortcode .main-navigation-ul li.menu-item ul.sub-menu li a:hover {
			background: #f5f5f5;
		}
		/* Nested sub-menus */
		.mk-header-shortcode .main-navigation-ul li.menu-item ul.sub-menu ul.sub-menu {
			top: 0;
			left: 100%;
		}

		/* ---- Hover Style 1: Top border underline ---- */
		.mk-header-shortcode .menu-hover-style-1 .main-navigation-ul > li.menu-item > a {
			border-top: 3px solid transparent;
		}

		/* ---- Hover Style 2: Simple color change (handled by instance CSS) ---- */

		/* ---- Hover Style 3: Border box ---- */
		.mk-header-shortcode .menu-hover-style-3 .main-navigation-ul > li.menu-item > a {
			border: 2px solid transparent;
			transition: all 0.2s ease;
		}

		/* ---- Hover Style 4: Full background ---- */
		.mk-header-shortcode .menu-hover-style-4 .main-navigation-ul > li.menu-item > a {
			transition: background-color 0.3s ease;
		}

		/* ---- Hover Style 5: Underline via ::after ---- */
		.mk-header-shortcode .menu-hover-style-5 .main-navigation-ul > li.menu-item > a.menu-item-link {
			position: relative;
		}
		.mk-header-shortcode .menu-hover-style-5 .main-navigation-ul > li.menu-item > a.menu-item-link:after {
			content: '';
			position: absolute;
			bottom: 0;
			left: 50%;
			width: 0;
			height: 2px;
			background-color: transparent;
			transition: width 0.3s ease, left 0.3s ease;
		}
		.mk-header-shortcode .menu-hover-style-5 .main-navigation-ul > li.menu-item > a.menu-item-link:hover:after,
		.mk-header-shortcode .menu-hover-style-5 .main-navigation-ul > li.current-menu-item > a.menu-item-link:after {
			width: 100%;
			left: 0;
		}

		/* ---- Logo ---- */
		.mk-header-shortcode .header-logo {
			position: relative;
			z-index: 1;
			padding: 10px 20px;
		}
		.mk-header-shortcode .header-logo a {
			display: inline-block;
			text-decoration: none;
		}
		.mk-header-shortcode .header-logo img {
			max-height: 40px;
			width: auto;
		}
		.mk-header-shortcode .header-logo .site-title {
			font-size: 20px;
			font-weight: 700;
			color: inherit;
		}

		/* ---- Responsive toggle ---- */
		.mk-header-shortcode .mk-responsive-wrap {
			display: none;
		}
		.mk-header-shortcode .mk-nav-responsive-link {
			display: flex;
			align-items: center;
			justify-content: center;
			background: transparent;
			border: none;
			cursor: pointer;
			padding: 10px 15px;
			position: relative;
			z-index: 1;
		}
		.mk-header-shortcode .mk-css-icon-menu {
			width: 22px;
			height: 16px;
			position: relative;
		}
		.mk-header-shortcode .mk-css-icon-menu div {
			position: absolute;
			left: 0;
			width: 100%;
			height: 2px;
			background-color: #fff;
			transition: all 0.3s ease;
		}
		.mk-header-shortcode .mk-css-icon-menu-line-1 { top: 0; }
		.mk-header-shortcode .mk-css-icon-menu-line-2 { top: 7px; }
		.mk-header-shortcode .mk-css-icon-menu-line-3 { top: 14px; }

		/* Responsive nav */
		.mk-header-shortcode .mk-responsive-nav-container {
			display: none;
			width: 100%;
		}
		.mk-header-shortcode .mk-responsive-nav-container.is-open {
			display: block;
		}
		.mk-header-shortcode .mk-responsive-nav {
			list-style: none;
			margin: 0;
			padding: 10px 0;
		}
		.mk-header-shortcode .mk-responsive-nav li {
			margin: 0;
		}
		.mk-header-shortcode .mk-responsive-nav li a {
			display: block;
			padding: 10px 20px;
			color: inherit;
			text-decoration: none;
			font-size: 14px;
		}
		.mk-header-shortcode .mk-responsive-nav li a:hover {
			opacity: 0.7;
		}
		.mk-header-shortcode .mk-responsive-nav .sub-menu {
			list-style: none;
			margin: 0;
			padding: 0 0 0 20px;
		}

		/* ---- Responsive breakpoint ---- */
		@media (max-width: 1024px) {
			.mk-header-shortcode .mk-header-nav-container {
				display: none;
			}
			.mk-header-shortcode .mk-responsive-wrap {
				display: block;
				position: relative;
				z-index: 1;
			}
		}

		/* ---- Page section integration ---- */
		.mk-page-section .mk-header-shortcode {
			position: absolute;
			left: 0;
			bottom: 0;
		}
		.mk-page-section .mk-header-shortcode .mk-header-holder {
			position: relative !important;
		}
		.mk-page-section .mk-header-shortcode .mk-header-padding-wrapper {
			display: none !important;
		}
	</style>
	<?php
}

/* ================================================================
 * JavaScript  –  output once per page, in footer
 * ============================================================== */
function sol_mk_header_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script id="mk-header-js">
	(function(){
		function initMkHeader(){
			var headers = document.querySelectorAll('.js-header-shortcode');
			headers.forEach(function(header){

				/* ---- Page-section integration (move header to bottom of section) ---- */
				var pageSection = header.closest('.mk-page-section');
				if(pageSection){
					pageSection.appendChild(header);
					pageSection.style.overflow = 'visible';
					var parentRow = header.closest('.js-master-row');
					if(parentRow) parentRow.style.overflow = 'visible';
				}
				header.parentElement.style.zIndex = '99999';

				/* ---- Responsive menu toggle ---- */
				var toggler = header.querySelector('.mk-nav-responsive-link');
				var respNav = header.querySelector('.mk-responsive-nav-container');
				if(toggler && respNav){
					toggler.addEventListener('click', function(e){
						e.preventDefault();
						respNav.classList.toggle('is-open');
					});
				}

				/* ---- Add menu-item-link class to top-level links if missing ---- */
				var topLinks = header.querySelectorAll('.main-navigation-ul > li.menu-item > a');
				topLinks.forEach(function(a){
					if(!a.classList.contains('menu-item-link')){
						a.classList.add('menu-item-link');
					}
				});
			});
		}

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', initMkHeader);
		} else {
			initMkHeader();
		}
	})();
	</script>
	<?php
}
