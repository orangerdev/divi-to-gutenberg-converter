<?php
/**
 * Plugin Name: VC TTA Accordion Standalone
 * Description: Standalone replacement for WPBakery vc_tta_accordion + vc_tta_section shortcodes.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Global state: tracks parent accordion settings so child sections
 * can read them at render time.
 * -------------------------------------------------------------- */
class Sol_Vc_Tta_Accordion_State {
	/** @var array|null Current parent accordion atts while content is being processed. */
	public static $current_accordion = null;
	/** @var int 1-indexed counter that increments for every vc_tta_section rendered inside current accordion. */
	public static $section_index = 0;
}

/* ----------------------------------------------------------------
 * Register shortcodes
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_tta_accordion' ) ) {
		add_shortcode( 'vc_tta_accordion', 'sol_render_vc_tta_accordion' );
	}
	if ( ! shortcode_exists( 'vc_tta_section' ) ) {
		add_shortcode( 'vc_tta_section', 'sol_render_vc_tta_section' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return;
	}
	$content = $post->post_content;
	if ( has_shortcode( $content, 'vc_tta_accordion' ) || has_shortcode( $content, 'vc_tta_section' ) ) {
		add_action( 'wp_head', 'sol_vc_tta_accordion_base_css' );
	}
} );

/* ================================================================
 * [vc_tta_accordion]  –  Container shortcode
 * ============================================================== */
function sol_render_vc_tta_accordion( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'title'            => '',
		'style'            => 'classic',   // classic | modern | flat | outline
		'shape'            => 'rounded',   // rounded | square | round
		'color'            => 'grey',
		'no_fill'          => '',
		'spacing'          => '',
		'gap'              => '',
		'c_align'          => '',
		'c_icon'           => 'chevron',   // chevron | plus | triangle
		'c_position'       => 'left',      // left | right
		'active_section'   => '1',
		'collapsible_all'  => '',
		'autoplay'         => '',
		'el_id'            => '',
		'el_class'         => '',
		'css_animation'    => '',
		'css'              => '',
	), $atts, 'vc_tta_accordion' );

	// Push state so child sections can read accordion settings.
	Sol_Vc_Tta_Accordion_State::$current_accordion = $atts;
	Sol_Vc_Tta_Accordion_State::$section_index      = 0;

	// Process child shortcodes (vc_tta_section).
	$panels_html = do_shortcode( shortcode_unautop( trim( $content ) ) );

	// Pop state.
	Sol_Vc_Tta_Accordion_State::$current_accordion = null;

	// Build wrapper classes.
	$general_classes   = array( 'vc_general', 'vc_tta', 'vc_tta-accordion' );
	$general_classes[] = 'vc_tta-color-' . esc_attr( $atts['color'] );
	$general_classes[] = 'vc_tta-style-' . esc_attr( $atts['style'] );
	$general_classes[] = 'vc_tta-shape-' . esc_attr( $atts['shape'] );

	// When no spacing is set, WPBakery adds the shape-group class.
	if ( empty( $atts['spacing'] ) ) {
		$general_classes[] = 'vc_tta-o-shape-group';
	} else {
		$general_classes[] = 'vc_tta-spacing-' . esc_attr( $atts['spacing'] );
	}
	if ( ! empty( $atts['gap'] ) ) {
		$general_classes[] = 'vc_tta-gap-' . esc_attr( $atts['gap'] );
	}
	if ( ! empty( $atts['c_align'] ) ) {
		$general_classes[] = 'vc_tta-controls-align-' . esc_attr( $atts['c_align'] );
	}
	if ( 'true' === $atts['no_fill'] ) {
		$general_classes[] = 'vc_tta-o-no-fill';
	}
	if ( 'true' === $atts['collapsible_all'] ) {
		$general_classes[] = 'vc_tta-o-all-clickable';
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$general_classes[] = esc_attr( $atts['el_class'] );
	}

	$vc_action = ( 'true' === $atts['collapsible_all'] ) ? 'collapseAll' : 'collapse';

	$id_attr = '';
	if ( ! empty( $atts['el_id'] ) ) {
		$id_attr = ' id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Optional widget title.
	$title_html = '';
	if ( '' !== trim( $atts['title'] ) ) {
		$title_html = '<h2 class="wpb_heading">' . esc_html( $atts['title'] ) . '</h2>';
	}

	// CSS + JS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_tta_accordion_base_css', 1 );
	add_action( 'wp_footer', 'sol_vc_tta_accordion_js', 20 );

	ob_start();
	?>
	<div class="vc_tta-container" data-vc-action="<?php echo esc_attr( $vc_action ); ?>"<?php echo $id_attr; ?>>
		<?php echo $title_html; ?>
		<div class="<?php echo esc_attr( implode( ' ', array_filter( $general_classes ) ) ); ?>">
			<div class="vc_tta-panels-container">
				<div class="vc_tta-panels">
					<?php echo $panels_html; ?>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/* ================================================================
 * [vc_tta_section]  –  Individual panel shortcode
 * ============================================================== */
function sol_render_vc_tta_section( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'title'    => '',
		'tab_id'   => '',
		'add_icon' => '',
		'i_type'   => 'fontawesome',
		'i_icon_fontawesome' => '',
		'i_position' => 'left',
		'el_class' => '',
	), $atts, 'vc_tta_section' );

	// Increment section counter.
	Sol_Vc_Tta_Accordion_State::$section_index++;
	$index = Sol_Vc_Tta_Accordion_State::$section_index;

	// Read parent accordion settings.
	$acc = Sol_Vc_Tta_Accordion_State::$current_accordion;

	// Determine if this section is active.
	$is_active = false;
	if ( $acc ) {
		$active_section = intval( $acc['active_section'] );
		$is_active      = ( $index === $active_section );
	}

	$panel_classes = array( 'vc_tta-panel' );
	if ( $is_active ) {
		$panel_classes[] = 'vc_active';
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$panel_classes[] = esc_attr( $atts['el_class'] );
	}

	// Control icon type & position from parent.
	$c_icon     = $acc ? $acc['c_icon'] : 'chevron';
	$c_position = $acc ? $acc['c_position'] : 'left';
	if ( 'default' === $c_position ) {
		$c_position = is_rtl() ? 'right' : 'left';
	}

	$heading_classes = array( 'vc_tta-panel-title' );
	if ( ! empty( $c_icon ) ) {
		$heading_classes[] = 'vc_tta-controls-icon-position-' . esc_attr( $c_position );
	}

	$tab_id = ! empty( $atts['tab_id'] ) ? $atts['tab_id'] : sanitize_title( $atts['title'] ) . '-' . $index;

	// Optional section icon.
	$icon_html = '';
	if ( 'true' === $atts['add_icon'] && ! empty( $atts['i_icon_fontawesome'] ) ) {
		$icon_html = '<i class="vc_tta-icon ' . esc_attr( $atts['i_icon_fontawesome'] ) . '"></i>';
	}
	$icon_left  = ( $icon_html && 'left' === $atts['i_position'] ) ? $icon_html : '';
	$icon_right = ( $icon_html && 'right' === $atts['i_position'] ) ? $icon_html : '';

	// Control icon markup.
	$control_icon = '';
	if ( ! empty( $c_icon ) ) {
		$control_icon = '<i class="vc_tta-controls-icon vc_tta-controls-icon-' . esc_attr( $c_icon ) . '"></i>';
	}

	// Section content.
	$section_content = do_shortcode( shortcode_unautop( wpautop( trim( $content ) ) ) );

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $panel_classes ) ); ?>" id="<?php echo esc_attr( $tab_id ); ?>" data-vc-content=".vc_tta-panel-body">
		<div class="vc_tta-panel-heading">
			<h4 class="<?php echo esc_attr( implode( ' ', $heading_classes ) ); ?>">
				<a href="#<?php echo esc_attr( $tab_id ); ?>" data-vc-accordion data-vc-container=".vc_tta-container"><?php
					echo $icon_left;
					?><span class="vc_tta-title-text"><?php echo wp_kses_post( $atts['title'] ); ?></span><?php
					echo $icon_right;
					echo $control_icon;
				?></a>
			</h4>
		</div>
		<div class="vc_tta-panel-body">
			<?php echo $section_content; ?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_vc_tta_accordion_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-tta-accordion-base-css">
		/* ---- Container ---- */
		.vc_tta-container { margin-bottom: 35px; }
		.vc_tta-container *,
		.vc_tta-container *::before,
		.vc_tta-container *::after { box-sizing: border-box; }

		/* ---- General ---- */
		.vc_tta.vc_general { font-size: 1em; }
		.vc_tta .vc_tta-panels-container,
		.vc_tta .vc_tta-panels { position: relative; }
		.vc_tta .vc_tta-panel { display: block; }

		/* ---- Panel heading ---- */
		.vc_tta .vc_tta-panel-heading {
			border: solid transparent;
			transition: background .2s ease-in-out;
		}
		.vc_tta .vc_tta-panel-title {
			margin: 0;
			line-height: 1;
		}
		.vc_tta .vc_tta-panel-title > a {
			background: transparent;
			display: block;
			padding: 14px 20px;
			text-decoration: none;
			color: inherit;
			position: relative;
			transition: color .2s ease-in-out;
			border: none;
			box-shadow: none;
		}
		.vc_tta .vc_tta-panel-title > a:focus,
		.vc_tta .vc_tta-panel-title > a:hover {
			text-decoration: none;
			outline: none;
			box-shadow: none;
		}

		/* ---- Panel body ---- */
		.vc_tta .vc_tta-panel-body {
			border: solid transparent;
			box-sizing: content-box;
			padding: 14px 20px;
			display: none;
			overflow: hidden;
			transform: translate3d(0,0,0);
			transition: padding .2s ease-in-out;
		}
		.vc_tta .vc_tta-panel-body > :last-child { margin-bottom: 0; }

		/* ---- Active panel ---- */
		.vc_tta .vc_tta-panel.vc_active .vc_tta-panel-body { display: block; }
		.vc_tta .vc_tta-panel.vc_active .vc_tta-panel-title > a:hover { cursor: default; }
		.vc_tta.vc_tta-o-all-clickable .vc_tta-panel .vc_tta-panel-title > a:hover { cursor: pointer; }

		/* ---- Animating panel ---- */
		.vc_tta .vc_tta-panel.vc_animating .vc_tta-panel-body { display: block; min-height: 0; }

		/* ---- Title text + icon spacing ---- */
		.vc_tta .vc_tta-icon { font-size: 1.15em; line-height: 0; display: inline; }
		.vc_tta .vc_tta-title-text:not(:empty):not(:first-child),
		.vc_tta .vc_tta-title-text:not(:empty) ~ * { margin-left: 14px; }

		/* ================================================================
		 * SHAPES
		 * ============================================================ */
		/* Square */
		.vc_tta.vc_tta-shape-square .vc_tta-panel-body,
		.vc_tta.vc_tta-shape-square .vc_tta-panel-heading { border-radius: 0; }
		/* Rounded */
		.vc_tta.vc_tta-shape-rounded .vc_tta-panel-body { min-height: 10px; }
		.vc_tta.vc_tta-shape-rounded .vc_tta-panel-body,
		.vc_tta.vc_tta-shape-rounded .vc_tta-panel-heading { border-radius: 5px; }
		/* Round */
		.vc_tta.vc_tta-shape-round .vc_tta-panel-body { min-height: 4em; }
		.vc_tta.vc_tta-shape-round .vc_tta-panel-body,
		.vc_tta.vc_tta-shape-round .vc_tta-panel-heading { border-radius: 2em; }

		/* Active panel shape fix: remove inner border-radius */
		.vc_tta-shape-rounded:not(.vc_tta-o-no-fill) .vc_tta-panel.vc_active .vc_tta-panel-heading { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
		.vc_tta-shape-rounded:not(.vc_tta-o-no-fill) .vc_tta-panel.vc_active .vc_tta-panel-body   { border-top-left-radius: 0; border-top-right-radius: 0; }
		.vc_tta-shape-round:not(.vc_tta-o-no-fill) .vc_tta-panel.vc_active .vc_tta-panel-heading   { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
		.vc_tta-shape-round:not(.vc_tta-o-no-fill) .vc_tta-panel.vc_active .vc_tta-panel-body     { border-top-left-radius: 0; border-top-right-radius: 0; }

		/* Shape group (no spacing): flatten adjacent panels */
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:not(:first-child):not(:last-child) .vc_tta-panel-heading,
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:not(:first-child):not(:last-child) .vc_tta-panel-body { border-radius: 0; }
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:first-child:not(:last-child) .vc_tta-panel-heading,
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:first-child:not(:last-child) .vc_tta-panel-body { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:last-child:not(:first-child) .vc_tta-panel-heading,
		.vc_tta-shape-rounded.vc_tta-o-shape-group:not(.vc_tta-o-no-fill) .vc_tta-panel:last-child:not(:first-child) .vc_tta-panel-body { border-top-left-radius: 0; border-top-right-radius: 0; }

		/* ================================================================
		 * STYLES
		 * ============================================================ */
		/* Classic */
		.vc_tta.vc_tta-style-classic .vc_tta-panel-body,
		.vc_tta.vc_tta-style-classic .vc_tta-panel-heading { border-width: 1px; }
		.vc_tta.vc_tta-style-classic .vc_tta-panel:not(:first-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-classic .vc_tta-panel.vc_active + .vc_tta-panel .vc_tta-panel-heading { margin-top: -1px; }
		.vc_tta.vc_tta-style-classic .vc_tta-panel:not(:last-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-classic .vc_tta-panel.vc_active .vc_tta-panel-heading { margin-bottom: -1px; }

		/* Modern */
		.vc_tta.vc_tta-style-modern .vc_tta-panel-body,
		.vc_tta.vc_tta-style-modern .vc_tta-panel-heading {
			border-width: 1px;
			background-image: linear-gradient(rgba(255,255,255,.2), rgba(255,255,255,.01));
		}
		.vc_tta.vc_tta-style-modern .vc_tta-panel:not(:first-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-modern .vc_tta-panel.vc_active + .vc_tta-panel .vc_tta-panel-heading { margin-top: -1px; }
		.vc_tta.vc_tta-style-modern .vc_tta-panel:not(:last-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-modern .vc_tta-panel.vc_active .vc_tta-panel-heading { margin-bottom: -1px; }

		/* Flat */
		.vc_tta.vc_tta-style-flat .vc_tta-panel-body,
		.vc_tta.vc_tta-style-flat .vc_tta-panel-heading { border-width: 0; }

		/* Outline */
		.vc_tta.vc_tta-style-outline .vc_tta-panel-body,
		.vc_tta.vc_tta-style-outline .vc_tta-panel-heading { border-width: 2px; }
		.vc_tta.vc_tta-style-outline .vc_tta-panel:not(:first-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-outline .vc_tta-panel.vc_active + .vc_tta-panel .vc_tta-panel-heading { margin-top: -2px; }
		.vc_tta.vc_tta-style-outline .vc_tta-panel:not(:last-child) .vc_tta-panel-heading,
		.vc_tta.vc_tta-style-outline .vc_tta-panel.vc_active .vc_tta-panel-heading { margin-bottom: -2px; }

		/* No-fill option */
		.vc_tta.vc_tta-o-no-fill .vc_tta-panel-body {
			border-color: transparent;
			background-color: transparent;
		}

		/* ================================================================
		 * CONTROL ICONS  (chevron / plus / triangle)
		 * ============================================================ */
		.vc_tta .vc_tta-controls-icon {
			display: inline-block;
			vertical-align: middle;
			height: 12px;
			width: 12px;
			position: relative;
			font-size: inherit;
			margin: 0;
		}
		.vc_tta .vc_tta-controls-icon::before,
		.vc_tta .vc_tta-controls-icon::after {
			transition: all .2s ease-in-out;
		}
		.vc_tta .vc_tta-title-text:not(:empty) ~ .vc_tta-controls-icon { margin-left: 0; }

		/* Plus */
		.vc_tta .vc_tta-controls-icon-plus::before {
			content: '';
			display: block;
			position: absolute;
			left: 0; right: 0; top: 50%;
			transform: translateY(-50%);
			border-style: solid;
			border-width: 2px 0 0 0;
		}
		.vc_tta .vc_tta-controls-icon-plus::after {
			content: '';
			display: block;
			position: absolute;
			left: 50%; top: 0; bottom: 0;
			transform: translateX(-50%);
			border-style: solid;
			border-width: 0 0 0 2px;
		}
		.vc_tta .vc_active .vc_tta-controls-icon-plus::after { display: none; }

		/* Chevron */
		.vc_tta .vc_tta-controls-icon-chevron::before {
			content: '';
			display: block;
			position: absolute;
			left: 2px; right: 2px; top: 2px; bottom: 2px;
			border-style: solid;
			border-width: 0 2px 2px 0;
			transform: rotate(45deg) translate(-25%, -25%);
		}
		.vc_tta .vc_active .vc_tta-controls-icon-chevron::before {
			transform: rotate(225deg) translate(-25%, -25%);
		}

		/* Triangle */
		.vc_tta .vc_tta-controls-icon-triangle::before {
			content: '';
			display: block;
			position: absolute;
			left: 0; right: 0; top: 0; bottom: 0;
			border-style: solid;
			border-width: 6px;
			border-bottom-color: transparent !important;
			border-right-color: transparent !important;
			border-left-color: transparent !important;
			transform: translateY(25%);
		}
		.vc_tta .vc_active .vc_tta-controls-icon-triangle::before {
			transform: rotate(180deg) translateY(25%);
		}

		/* Icon position: left */
		.vc_tta.vc_tta-accordion .vc_tta-controls-icon-position-left > a {
			padding-left: calc(20px + 14px + 12px);
		}
		.vc_tta.vc_tta-accordion .vc_tta-controls-icon-position-left .vc_tta-controls-icon {
			position: absolute;
			top: 50%;
			transform: translateY(-50%);
			left: 20px;
		}

		/* Icon position: right */
		.vc_tta.vc_tta-accordion .vc_tta-controls-icon-position-right > a {
			padding-right: calc(30px + 12px);
		}
		.vc_tta.vc_tta-accordion .vc_tta-controls-icon-position-right .vc_tta-controls-icon {
			position: absolute;
			top: 50%;
			transform: translateY(-50%);
			right: 20px;
		}

		/* ================================================================
		 * COLORS  –  Grey (default)
		 * ============================================================ */
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-heading,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-heading {
			border-color: #f0f0f0;
			background-color: #f8f8f8;
		}
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-heading:hover,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-heading:hover {
			background-color: #f2f2f2;
		}
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-title > a,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-title > a { color: #666; }
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel.vc_active .vc_tta-panel-heading,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel.vc_active .vc_tta-panel-heading {
			border-color: #f0f0f0;
			background-color: #f8f8f8;
		}
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel.vc_active .vc_tta-panel-title > a,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel.vc_active .vc_tta-panel-title > a { color: #666; }
		.vc_tta-color-grey.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-body,
		.vc_tta-color-grey.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-body {
			border-color: #f0f0f0;
			background-color: #f8f8f8;
		}
		.vc_tta-color-grey .vc_tta-controls-icon::before,
		.vc_tta-color-grey .vc_tta-controls-icon::after { border-color: #666; }
		.vc_tta-color-grey .vc_active .vc_tta-controls-icon::before,
		.vc_tta-color-grey .vc_active .vc_tta-controls-icon::after { border-color: #666; }

		/* Flat grey */
		.vc_tta-color-grey.vc_tta-style-flat .vc_tta-panel .vc_tta-panel-heading { background-color: #f8f8f8; }
		.vc_tta-color-grey.vc_tta-style-flat .vc_tta-panel.vc_active .vc_tta-panel-heading { background-color: #f8f8f8; }
		.vc_tta-color-grey.vc_tta-style-flat .vc_tta-panel .vc_tta-panel-body { background-color: #f8f8f8; }
		.vc_tta-color-grey.vc_tta-style-flat .vc_tta-panel .vc_tta-panel-title > a { color: #666; }

		/* Outline grey */
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel .vc_tta-panel-heading {
			border-color: #ebebeb;
			background-color: transparent;
		}
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel .vc_tta-panel-heading:hover { background-color: #ebebeb; }
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel .vc_tta-panel-title > a { color: #ebebeb; }
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel .vc_tta-panel-heading:hover .vc_tta-panel-title > a { color: #666; }
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel.vc_active .vc_tta-panel-heading {
			border-color: #ebebeb;
			background-color: transparent;
		}
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel.vc_active .vc_tta-panel-title > a { color: #ebebeb; }
		.vc_tta-color-grey.vc_tta-style-outline .vc_tta-panel .vc_tta-panel-body {
			border-color: #ebebeb;
			background-color: transparent;
		}

		/* ================================================================
		 * COLORS  –  Other common colors
		 * Generated from WPBakery variables.
		 * ============================================================ */
		<?php
		$colors = array(
			'blue'        => array( 'main' => '#5472d2', 'border' => '#4e6bc3' ),
			'turquoise'   => array( 'main' => '#00c1cf', 'border' => '#00b1be' ),
			'pink'        => array( 'main' => '#fe6c61', 'border' => '#f95e52' ),
			'violet'      => array( 'main' => '#8d6dc4', 'border' => '#8160ba' ),
			'peacoc'      => array( 'main' => '#4cadc9', 'border' => '#44a0bb' ),
			'chino'       => array( 'main' => '#cec2ab', 'border' => '#c5b89d' ),
			'mulled-wine' => array( 'main' => '#50485b', 'border' => '#493f55' ),
			'vista-blue'  => array( 'main' => '#75d69c', 'border' => '#66ce90' ),
			'orange'      => array( 'main' => '#f7be68', 'border' => '#f5b54e' ),
			'sky'         => array( 'main' => '#5aa1e3', 'border' => '#4b96dd' ),
			'green'       => array( 'main' => '#6dab3c', 'border' => '#619b33' ),
			'juicy-pink'  => array( 'main' => '#f4524d', 'border' => '#f24843' ),
			'sandy-brown' => array( 'main' => '#f79468', 'border' => '#f58453' ),
			'purple'      => array( 'main' => '#b97ebb', 'border' => '#ae6eb0' ),
			'black'       => array( 'main' => '#2a2a2a', 'border' => '#1e1e1e' ),
			'white'       => array( 'main' => '#ffffff', 'border' => '#f0f0f0' ),
		);
		$bg        = '#f8f8f8';
		$bg_border = '#f0f0f0';
		$text      = '#666';
		foreach ( $colors as $name => $c ) :
			$contrast = in_array( $name, array( 'grey', 'white' ), true ) ? '#666' : '#fff';
		?>
		/* <?php echo $name; ?> – classic & modern */
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-heading,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-heading { border-color: <?php echo $c['border']; ?>; background-color: <?php echo $c['main']; ?>; }
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-title > a,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-title > a { color: <?php echo $contrast; ?>; }
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-classic .vc_tta-panel.vc_active .vc_tta-panel-heading,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-modern .vc_tta-panel.vc_active .vc_tta-panel-heading { border-color: <?php echo $bg_border; ?>; background-color: <?php echo $bg; ?>; }
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-classic .vc_tta-panel.vc_active .vc_tta-panel-title > a,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-modern .vc_tta-panel.vc_active .vc_tta-panel-title > a { color: <?php echo $text; ?>; }
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-classic .vc_tta-panel .vc_tta-panel-body,
		.vc_tta-color-<?php echo $name; ?>.vc_tta-style-modern .vc_tta-panel .vc_tta-panel-body { border-color: <?php echo $bg_border; ?>; background-color: <?php echo $bg; ?>; }
		.vc_tta-color-<?php echo $name; ?> .vc_tta-controls-icon::before,
		.vc_tta-color-<?php echo $name; ?> .vc_tta-controls-icon::after { border-color: <?php echo $contrast; ?>; }
		.vc_tta-color-<?php echo $name; ?> .vc_active .vc_tta-controls-icon::before,
		.vc_tta-color-<?php echo $name; ?> .vc_active .vc_tta-controls-icon::after { border-color: <?php echo $text; ?>; }
		<?php endforeach; ?>
	</style>
	<?php
}

/* ================================================================
 * JavaScript accordion behaviour  –  output once per page
 * ============================================================== */
function sol_vc_tta_accordion_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		function initVcAccordions(){
			var containers = document.querySelectorAll('.vc_tta-container');
			containers.forEach(function(container){
				var action   = container.getAttribute('data-vc-action') || 'collapse';
				var links    = container.querySelectorAll('[data-vc-accordion]');
				var animating = false;

				links.forEach(function(link){
					link.addEventListener('click', function(e){
						e.preventDefault();
						if(animating) return;

						var panel = link.closest('.vc_tta-panel');
						if(!panel) return;

						var isActive = panel.classList.contains('vc_active');

						if(action === 'collapse'){
							// Close all other panels in this container.
							var siblings = container.querySelectorAll('.vc_tta-panel.vc_active');
							siblings.forEach(function(sib){
								if(sib !== panel) closePanel(sib);
							});
							if(!isActive){
								openPanel(panel);
							}
						} else {
							// collapseAll — toggle individually.
							if(isActive){
								closePanel(panel);
							} else {
								openPanel(panel);
							}
						}
					});
				});

				function openPanel(panel){
					var body = panel.querySelector('.vc_tta-panel-body');
					if(!body) return;

					panel.classList.add('vc_animating');
					body.style.display   = 'block';
					body.style.overflow  = 'hidden';
					var h = body.scrollHeight;
					body.style.height    = '0px';
					body.style.padding   = '0 20px';
					body.offsetHeight; // force reflow
					body.style.transition = 'height .3s ease, padding .3s ease';
					body.style.height     = h + 'px';
					body.style.padding    = '14px 20px';

					body.addEventListener('transitionend', function handler(){
						body.removeEventListener('transitionend', handler);
						body.style.height     = '';
						body.style.overflow   = '';
						body.style.transition = '';
						panel.classList.remove('vc_animating');
						panel.classList.add('vc_active');
					});
				}

				function closePanel(panel){
					var body = panel.querySelector('.vc_tta-panel-body');
					if(!body) return;

					panel.classList.add('vc_animating');
					var h = body.scrollHeight;
					body.style.height    = h + 'px';
					body.style.overflow  = 'hidden';
					body.offsetHeight; // force reflow
					body.style.transition = 'height .3s ease, padding .3s ease';
					body.style.height     = '0px';
					body.style.padding    = '0 20px';

					body.addEventListener('transitionend', function handler(){
						body.removeEventListener('transitionend', handler);
						body.style.height     = '';
						body.style.display    = '';
						body.style.overflow   = '';
						body.style.padding    = '';
						body.style.transition = '';
						panel.classList.remove('vc_animating');
						panel.classList.remove('vc_active');
					});
				}
			});
		}

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', initVcAccordions);
		} else {
			initVcAccordions();
		}
	})();
	</script>
	<?php
}
