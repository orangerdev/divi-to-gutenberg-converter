<?php
/**
 * Plugin Name: VC TTA Tabs Standalone
 * Description: Standalone replacement for WPBakery vc_tta_tabs shortcode.
 *              Mirrors the original WPBakery rendering (tab navigation + content panels).
 *              Requires vc-tta-accordion-shortcode.php for vc_tta_section support.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_tta_tabs' ) ) {
		add_shortcode( 'vc_tta_tabs', 'sol_render_vc_tta_tabs' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vc_tta_tabs' ) ) {
		add_action( 'wp_head', 'sol_vc_tta_tabs_base_css' );
	}
} );

/* ================================================================
 * [vc_tta_tabs]  –  Tabs container shortcode
 *
 * WPBakery output:
 *   <div class="vc_tta-container" data-vc-action="collapse">
 *     <div class="vc_general vc_tta vc_tta-tabs {style} {color} {shape}">
 *       <div class="vc_tta-tabs-container">
 *         <ul class="vc_tta-tabs-list">
 *           <li class="vc_tta-tab {vc_active}"><a href="#{tab_id}" data-vc-tabs>…</a></li>
 *         </ul>
 *       </div>
 *       <div class="vc_tta-panels-container">
 *         <div class="vc_tta-panels">
 *           …panels from vc_tta_section…
 *         </div>
 *       </div>
 *     </div>
 *   </div>
 * ============================================================== */
function sol_render_vc_tta_tabs( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'title'          => '',
		'style'          => 'classic',
		'shape'          => 'rounded',
		'color'          => 'grey',
		'no_fill'        => '',
		'spacing'        => '',
		'gap'            => '',
		'tab_position'   => 'top',
		'alignment'      => 'left',
		'active_section' => '1',
		'autoplay'       => '',
		'el_id'          => '',
		'el_class'       => '',
		'css_animation'  => '',
		'css'            => '',
	), $atts, 'vc_tta_tabs' );

	// ---- Extract section data from raw content for tab navigation ----
	$sections = sol_vc_tta_tabs_extract_sections( $content );

	// ---- Push state so child vc_tta_section can read settings ----
	if ( class_exists( 'Sol_Vc_Tta_Accordion_State' ) ) {
		Sol_Vc_Tta_Accordion_State::$current_accordion = $atts;
		Sol_Vc_Tta_Accordion_State::$section_index      = 0;
	}

	// Process child shortcodes (vc_tta_section).
	$panels_html = do_shortcode( shortcode_unautop( trim( $content ) ) );

	// Pop state.
	if ( class_exists( 'Sol_Vc_Tta_Accordion_State' ) ) {
		Sol_Vc_Tta_Accordion_State::$current_accordion = null;
	}

	// ---- Build wrapper classes ----
	$general_classes   = array( 'vc_general', 'vc_tta', 'vc_tta-tabs' );
	$general_classes[] = 'vc_tta-color-' . esc_attr( $atts['color'] );
	$general_classes[] = 'vc_tta-style-' . esc_attr( $atts['style'] );
	$general_classes[] = 'vc_tta-shape-' . esc_attr( $atts['shape'] );

	if ( empty( $atts['spacing'] ) ) {
		$general_classes[] = 'vc_tta-o-shape-group';
	} else {
		$general_classes[] = 'vc_tta-spacing-' . esc_attr( $atts['spacing'] );
	}
	if ( ! empty( $atts['gap'] ) ) {
		$general_classes[] = 'vc_tta-gap-' . esc_attr( $atts['gap'] );
	}
	if ( ! empty( $atts['alignment'] ) ) {
		$general_classes[] = 'vc_tta-controls-align-' . esc_attr( $atts['alignment'] );
	}
	if ( ! empty( $atts['tab_position'] ) ) {
		$general_classes[] = 'vc_tta-tabs-position-' . esc_attr( $atts['tab_position'] );
	}
	if ( 'true' === $atts['no_fill'] ) {
		$general_classes[] = 'vc_tta-o-no-fill';
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$general_classes[] = esc_attr( $atts['el_class'] );
	}

	$id_attr = '';
	if ( ! empty( $atts['el_id'] ) ) {
		$id_attr = ' id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Optional widget title.
	$title_html = '';
	if ( '' !== trim( $atts['title'] ) ) {
		$title_html = '<h2 class="wpb_heading">' . esc_html( $atts['title'] ) . '</h2>';
	}

	// Autoplay data attribute.
	$autoplay_attr = '';
	if ( ! empty( $atts['autoplay'] ) && 'none' !== $atts['autoplay'] ) {
		$delay = absint( $atts['autoplay'] ) * 1000;
		if ( $delay > 0 ) {
			$autoplay_attr = ' data-vc-tta-autoplay=\'{"delay":' . $delay . '}\'';
		}
	}

	// ---- Build tab navigation ----
	$active_section = intval( $atts['active_section'] );
	$tabs_html      = sol_vc_tta_tabs_build_navigation( $sections, $active_section );

	// CSS + JS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_tta_tabs_base_css', 1 );
	add_action( 'wp_footer', 'sol_vc_tta_tabs_js', 20 );

	ob_start();
	?>
	<div class="vc_tta-container" data-vc-action="collapse"<?php echo $id_attr; ?><?php echo $autoplay_attr; ?>>
		<?php echo $title_html; ?>
		<div class="<?php echo esc_attr( implode( ' ', array_filter( $general_classes ) ) ); ?>">
			<?php if ( 'bottom' !== $atts['tab_position'] ) : ?>
				<?php echo $tabs_html; ?>
			<?php endif; ?>
			<div class="vc_tta-panels-container">
				<div class="vc_tta-panels">
					<?php echo $panels_html; ?>
				</div>
			</div>
			<?php if ( 'bottom' === $atts['tab_position'] ) : ?>
				<?php echo $tabs_html; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/* ================================================================
 * Extract vc_tta_section attributes from raw shortcode content
 * (before do_shortcode processes them) so we can build tab nav.
 * ============================================================== */
function sol_vc_tta_tabs_extract_sections( $content ) {
	$sections = array();

	if ( ! preg_match_all( '/\[vc_tta_section([^\]]*)\]/s', $content, $matches ) ) {
		return $sections;
	}

	foreach ( $matches[1] as $attrs_string ) {
		$parsed = shortcode_parse_atts( $attrs_string );
		if ( ! is_array( $parsed ) ) {
			$parsed = array();
		}

		$sections[] = array(
			'title'              => isset( $parsed['title'] ) ? $parsed['title'] : '',
			'tab_id'             => isset( $parsed['tab_id'] ) ? $parsed['tab_id'] : '',
			'add_icon'           => isset( $parsed['add_icon'] ) ? $parsed['add_icon'] : '',
			'i_icon_fontawesome' => isset( $parsed['i_icon_fontawesome'] ) ? $parsed['i_icon_fontawesome'] : '',
			'i_position'         => isset( $parsed['i_position'] ) ? $parsed['i_position'] : 'left',
		);
	}

	return $sections;
}

/* ================================================================
 * Build tab navigation HTML
 * ============================================================== */
function sol_vc_tta_tabs_build_navigation( $sections, $active_section ) {
	if ( empty( $sections ) ) {
		return '';
	}

	$output  = '<div class="vc_tta-tabs-container">';
	$output .= '<ul class="vc_tta-tabs-list" role="tablist">';

	foreach ( $sections as $index => $section ) {
		$section_num = $index + 1;
		$is_active   = ( $section_num === $active_section );
		$tab_id      = ! empty( $section['tab_id'] ) ? $section['tab_id'] : 'tab-' . $section_num;

		$li_class = 'vc_tta-tab';
		if ( $is_active ) {
			$li_class .= ' vc_active';
		}

		// Icon markup.
		$icon_html = '';
		if ( 'true' === $section['add_icon'] && ! empty( $section['i_icon_fontawesome'] ) ) {
			$icon_html = '<i class="vc_tta-icon ' . esc_attr( $section['i_icon_fontawesome'] ) . '"></i>';
		}

		$icon_left  = ( $icon_html && 'left' === $section['i_position'] ) ? $icon_html : '';
		$icon_right = ( $icon_html && 'right' === $section['i_position'] ) ? $icon_html : '';

		$output .= '<li class="' . esc_attr( $li_class ) . '" data-vc-tab role="presentation">';
		$output .= '<a href="#' . esc_attr( $tab_id ) . '" data-vc-tabs data-vc-container=".vc_tta" role="tab">';
		$output .= $icon_left;
		$output .= '<span class="vc_tta-title-text">' . wp_kses_post( $section['title'] ) . '</span>';
		$output .= $icon_right;
		$output .= '</a>';
		$output .= '</li>';
	}

	$output .= '</ul>';
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * Base CSS  –  output once per page
 *
 * Tabs-specific styles that build on top of the accordion base
 * styles (which provide .vc_tta-panel, .vc_tta-panel-heading, etc.)
 * ============================================================== */
function sol_vc_tta_tabs_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-tta-tabs-base-css">
		/* ---- Tabs container ---- */
		.vc_tta.vc_tta-tabs .vc_tta-tabs-container {
			display: none; /* Hidden on mobile, shown at breakpoint */
		}

		/* ---- Tabs list ---- */
		.vc_tta-tabs-list {
			display: flex;
			flex-wrap: wrap;
			list-style: none;
			margin: 0;
			padding: 0;
		}

		/* ---- Tab item ---- */
		.vc_tta-tab {
			display: inline-block;
			margin: 0;
			padding: 0;
		}
		.vc_tta-tab > a {
			display: block;
			padding: 14px 20px;
			text-decoration: none;
			color: inherit;
			white-space: nowrap;
			transition: background .2s ease-in-out, color .2s ease-in-out;
			border: solid transparent;
			cursor: pointer;
			position: relative;
		}
		.vc_tta-tab > a:focus,
		.vc_tta-tab > a:hover {
			text-decoration: none;
			outline: none;
			box-shadow: none;
		}

		/* ---- Tab alignment ---- */
		.vc_tta-controls-align-left .vc_tta-tabs-list { justify-content: flex-start; }
		.vc_tta-controls-align-center .vc_tta-tabs-list { justify-content: center; }
		.vc_tta-controls-align-right .vc_tta-tabs-list { justify-content: flex-end; }

		/* ---- Tab shape ---- */
		.vc_tta.vc_tta-shape-square .vc_tta-tab > a { border-radius: 0; }
		.vc_tta.vc_tta-shape-rounded .vc_tta-tab > a { border-radius: 5px 5px 0 0; }
		.vc_tta.vc_tta-shape-round .vc_tta-tab > a { border-radius: 24px 24px 0 0; }

		/* ---- Bottom tabs: reverse border-radius ---- */
		.vc_tta.vc_tta-tabs-position-bottom .vc_tta-tabs-container { order: 2; }
		.vc_tta.vc_tta-tabs-position-bottom .vc_tta-panels-container { order: 1; }
		.vc_tta.vc_tta-tabs-position-bottom.vc_tta-shape-rounded .vc_tta-tab > a { border-radius: 0 0 5px 5px; }
		.vc_tta.vc_tta-tabs-position-bottom.vc_tta-shape-round .vc_tta-tab > a { border-radius: 0 0 24px 24px; }

		/* ---- Tab gap ---- */
		.vc_tta-gap-1 .vc_tta-tab { margin-right: 1px; }
		.vc_tta-gap-2 .vc_tta-tab { margin-right: 2px; }
		.vc_tta-gap-3 .vc_tta-tab { margin-right: 3px; }
		.vc_tta-gap-5 .vc_tta-tab { margin-right: 5px; }
		.vc_tta-gap-10 .vc_tta-tab { margin-right: 10px; }
		.vc_tta-gap-15 .vc_tta-tab { margin-right: 15px; }
		.vc_tta-gap-20 .vc_tta-tab { margin-right: 20px; }
		.vc_tta-gap-25 .vc_tta-tab { margin-right: 25px; }
		.vc_tta-gap-30 .vc_tta-tab { margin-right: 30px; }
		.vc_tta-gap-35 .vc_tta-tab { margin-right: 35px; }
		.vc_tta-gap-1 .vc_tta-tab:last-child,
		.vc_tta-gap-2 .vc_tta-tab:last-child,
		.vc_tta-gap-3 .vc_tta-tab:last-child,
		.vc_tta-gap-5 .vc_tta-tab:last-child,
		.vc_tta-gap-10 .vc_tta-tab:last-child,
		.vc_tta-gap-15 .vc_tta-tab:last-child,
		.vc_tta-gap-20 .vc_tta-tab:last-child,
		.vc_tta-gap-25 .vc_tta-tab:last-child,
		.vc_tta-gap-30 .vc_tta-tab:last-child,
		.vc_tta-gap-35 .vc_tta-tab:last-child { margin-right: 0; }

		/* ---- Style: Classic ---- */
		.vc_tta.vc_tta-tabs.vc_tta-style-classic .vc_tta-tab > a { border-width: 1px; border-bottom-color: transparent; }
		.vc_tta.vc_tta-tabs.vc_tta-style-classic .vc_tta-tab:not(.vc_active) > a { border-bottom-width: 1px; }
		.vc_tta.vc_tta-tabs.vc_tta-style-classic .vc_tta-tab.vc_active > a { border-bottom-color: transparent; margin-bottom: -1px; z-index: 1; position: relative; }

		/* ---- Style: Modern ---- */
		.vc_tta.vc_tta-tabs.vc_tta-style-modern .vc_tta-tab > a {
			border-width: 1px;
			border-bottom-color: transparent;
			background-image: linear-gradient(rgba(255,255,255,.2), rgba(255,255,255,.01));
		}
		.vc_tta.vc_tta-tabs.vc_tta-style-modern .vc_tta-tab.vc_active > a { border-bottom-color: transparent; margin-bottom: -1px; z-index: 1; position: relative; }

		/* ---- Style: Flat ---- */
		.vc_tta.vc_tta-tabs.vc_tta-style-flat .vc_tta-tab > a { border-width: 0; }

		/* ---- Style: Outline ---- */
		.vc_tta.vc_tta-tabs.vc_tta-style-outline .vc_tta-tab > a { border-width: 2px; border-bottom-color: transparent; }
		.vc_tta.vc_tta-tabs.vc_tta-style-outline .vc_tta-tab.vc_active > a { border-bottom-color: transparent; margin-bottom: -2px; z-index: 1; position: relative; }

		/* ---- Tabs panel body: force panels to be block inside vc_tta-tabs (active) ---- */
		.vc_tta.vc_tta-tabs .vc_tta-panel .vc_tta-panel-body { display: none; }
		.vc_tta.vc_tta-tabs .vc_tta-panel.vc_active .vc_tta-panel-body { display: block; }

		/* ================================================================
		 * COLOR SCHEMES – Tabs
		 * ============================================================ */
		<?php
		$colors = array(
			'grey'        => array( 'main' => '#f8f8f8', 'border' => '#f0f0f0', 'text' => '#666', 'active_bg' => '#f8f8f8' ),
			'blue'        => array( 'main' => '#5472d2', 'border' => '#4e6bc3', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'turquoise'   => array( 'main' => '#00c1cf', 'border' => '#00b1be', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'pink'        => array( 'main' => '#fe6c61', 'border' => '#f95e52', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'violet'      => array( 'main' => '#8d6dc4', 'border' => '#8160ba', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'peacoc'      => array( 'main' => '#4cadc9', 'border' => '#44a0bb', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'chino'       => array( 'main' => '#cec2ab', 'border' => '#c5b89d', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'mulled-wine' => array( 'main' => '#50485b', 'border' => '#493f55', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'vista-blue'  => array( 'main' => '#75d69c', 'border' => '#66ce90', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'orange'      => array( 'main' => '#f7be68', 'border' => '#f5b54e', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'sky'         => array( 'main' => '#5aa1e3', 'border' => '#4b96dd', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'green'       => array( 'main' => '#6dab3c', 'border' => '#619b33', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'juicy-pink'  => array( 'main' => '#f4524d', 'border' => '#f24843', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'sandy-brown' => array( 'main' => '#f79468', 'border' => '#f58453', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'purple'      => array( 'main' => '#b97ebb', 'border' => '#ae6eb0', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'black'       => array( 'main' => '#2a2a2a', 'border' => '#1e1e1e', 'text' => '#fff', 'active_bg' => '#f8f8f8' ),
			'white'       => array( 'main' => '#ffffff', 'border' => '#f0f0f0', 'text' => '#666', 'active_bg' => '#f8f8f8' ),
		);
		foreach ( $colors as $name => $c ) :
			$active_text = '#666';
		?>
		/* <?php echo $name; ?> – classic & modern tabs */
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-classic .vc_tta-tab > a,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-modern .vc_tta-tab > a {
			border-color: <?php echo $c['border']; ?>;
			background-color: <?php echo $c['main']; ?>;
			color: <?php echo $c['text']; ?>;
		}
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-classic .vc_tta-tab.vc_active > a,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-modern .vc_tta-tab.vc_active > a {
			border-color: #f0f0f0;
			background-color: <?php echo $c['active_bg']; ?>;
			color: <?php echo $active_text; ?>;
		}
		/* <?php echo $name; ?> – flat tabs */
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-flat .vc_tta-tab > a {
			background-color: <?php echo $c['main']; ?>;
			color: <?php echo $c['text']; ?>;
		}
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-flat .vc_tta-tab.vc_active > a {
			background-color: <?php echo $c['active_bg']; ?>;
			color: <?php echo $active_text; ?>;
		}
		/* <?php echo $name; ?> – outline tabs */
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-outline .vc_tta-tab > a {
			border-color: <?php echo $c['border']; ?>;
			background-color: transparent;
			color: <?php echo $c['main']; ?>;
		}
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-outline .vc_tta-tab > a:hover {
			background-color: <?php echo $c['main']; ?>;
			color: <?php echo $c['text']; ?>;
		}
		.vc_tta-color-<?php echo $name; ?>.vc_tta-tabs.vc_tta-style-outline .vc_tta-tab.vc_active > a {
			border-color: #f0f0f0;
			background-color: transparent;
			color: <?php echo $active_text; ?>;
		}
		<?php endforeach; ?>

		/* ================================================================
		 * RESPONSIVE: Desktop (≥ 768px)
		 * On desktop: show tab navigation, hide panel headings
		 * On mobile: hide tab navigation, show panel headings (accordion)
		 * ============================================================ */
		@media (min-width: 768px) {
			.vc_tta.vc_tta-tabs .vc_tta-tabs-container {
				display: block;
			}
			.vc_tta.vc_tta-tabs .vc_tta-panel-heading {
				display: none;
			}

			/* Flex ordering for bottom position */
			.vc_tta.vc_tta-tabs.vc_tta-tabs-position-bottom {
				display: flex;
				flex-direction: column;
			}
		}

		/* Mobile: accordion behaviour */
		@media (max-width: 767px) {
			.vc_tta.vc_tta-tabs .vc_tta-tabs-container {
				display: none !important;
			}
			.vc_tta.vc_tta-tabs .vc_tta-panel-heading {
				display: block;
			}
		}
	</style>
	<?php
}

/* ================================================================
 * JavaScript tab switching behaviour  –  output once per page
 * ============================================================== */
function sol_vc_tta_tabs_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		function initVcTabs(){
			var containers = document.querySelectorAll('.vc_tta.vc_tta-tabs');
			containers.forEach(function(tta){
				var tabLinks = tta.querySelectorAll('.vc_tta-tabs-list [data-vc-tabs]');
				var panels   = tta.querySelectorAll('.vc_tta-panels > .vc_tta-panel');

				// Also handle accordion heading clicks (for mobile).
				var panelLinks = tta.querySelectorAll('.vc_tta-panel-heading [data-vc-accordion]');

				tabLinks.forEach(function(link){
					link.addEventListener('click', function(e){
						e.preventDefault();
						var href = link.getAttribute('href');
						if(!href) return;
						var targetId = href.replace('#', '');
						activateTab(tta, tabLinks, panels, targetId);
					});
				});

				panelLinks.forEach(function(link){
					link.addEventListener('click', function(e){
						e.preventDefault();
						var href = link.getAttribute('href');
						if(!href) return;
						var targetId = href.replace('#', '');
						activateTab(tta, tabLinks, panels, targetId);
					});
				});

				// Autoplay.
				var wrapper = tta.closest('.vc_tta-container');
				if(wrapper && wrapper.hasAttribute('data-vc-tta-autoplay')){
					try {
						var config = JSON.parse(wrapper.getAttribute('data-vc-tta-autoplay'));
						var delay  = config.delay || 5000;
						var currentIndex = 0;

						// Find active panel index.
						panels.forEach(function(p, i){
							if(p.classList.contains('vc_active')) currentIndex = i;
						});

						setInterval(function(){
							currentIndex = (currentIndex + 1) % panels.length;
							var panel = panels[currentIndex];
							if(panel){
								var panelId = panel.getAttribute('id');
								if(panelId) activateTab(tta, tabLinks, panels, panelId);
							}
						}, delay);
					} catch(ex){}
				}
			});
		}

		function activateTab(tta, tabLinks, panels, targetId){
			// Deactivate all tabs.
			tabLinks.forEach(function(l){
				l.closest('.vc_tta-tab').classList.remove('vc_active');
			});

			// Deactivate all panels.
			panels.forEach(function(p){
				p.classList.remove('vc_active');
			});

			// Activate target tab.
			tabLinks.forEach(function(l){
				var href = l.getAttribute('href');
				if(href && href.replace('#', '') === targetId){
					l.closest('.vc_tta-tab').classList.add('vc_active');
				}
			});

			// Activate target panel.
			var targetPanel = tta.querySelector('#' + CSS.escape(targetId));
			if(targetPanel){
				targetPanel.classList.add('vc_active');
			}
		}

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', initVcTabs);
		} else {
			initVcTabs();
		}
	})();
	</script>
	<?php
}
