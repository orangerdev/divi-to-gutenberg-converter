<?php
/**
 * Plugin Name: VC Row Inner / Column Inner / Column Text Standalone
 * Description: Standalone replacement for WPBakery vc_row_inner, vc_column_inner, and vc_column_text shortcodes.
 *              Mirrors the original WPBakery rendering (grid, CSS attribute, equal_height, etc.).
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcodes
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_row_inner' ) ) {
		add_shortcode( 'vc_row_inner', 'sol_render_vc_row_inner' );
	}
	if ( ! shortcode_exists( 'vc_column_inner' ) ) {
		add_shortcode( 'vc_column_inner', 'sol_render_vc_column_inner' );
	}
	if ( ! shortcode_exists( 'vc_column_text' ) ) {
		add_shortcode( 'vc_column_text', 'sol_render_vc_column_text' );
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
	if ( has_shortcode( $content, 'vc_row_inner' ) || has_shortcode( $content, 'vc_column_inner' ) || has_shortcode( $content, 'vc_column_text' ) ) {
		add_action( 'wp_head', 'sol_vc_row_inner_base_css' );
	}
} );

/* ================================================================
 * Helper: parse WPBakery css attribute
 * Format: .vc_custom_1590078986237{margin-bottom: 0px !important;}
 * Returns [ 'class' => 'vc_custom_…', 'style' => 'margin-bottom: 0px !important;' ]
 * ============================================================== */
function sol_vc_parse_css_attribute( $css_attr ) {
	$result = [ 'class' => '', 'style' => '' ];
	if ( empty( $css_attr ) ) {
		return $result;
	}

	// Extract class name.
	if ( preg_match( '/\.([^\{]+)\s*\{/', $css_attr, $m ) ) {
		$result['class'] = trim( $m[1] );
	}

	// Extract style declarations.
	if ( preg_match( '/\{\s*([^\}]+)\s*\}/', $css_attr, $m ) ) {
		$result['style'] = trim( $m[1] );
	}

	return $result;
}

/* ================================================================
 * Helper: translate WPBakery column width fraction to grid span
 * "1/3" → 4, "1/2" → 6, "2/3" → 8, etc. (12-column grid)
 * ============================================================== */
function sol_vc_width_to_span( $width ) {
	if ( empty( $width ) ) {
		return 12;
	}

	if ( preg_match( '/(\d+)\/(\d+)/', $width, $m ) ) {
		$part_x = (int) $m[1];
		$part_y = (int) $m[2];
		if ( $part_x > 0 && $part_y > 0 ) {
			$value = (int) ceil( $part_x / $part_y * 12 );
			if ( $value > 0 && $value <= 12 ) {
				return $value;
			}
		}
	}

	return 12;
}

/* ================================================================
 * [vc_row_inner]  –  Inner row container
 *
 * WPBakery output:
 *   <div class="vc_row wpb_row vc_inner vc_row-fluid {css_class} {el_class}">
 *     …columns…
 *   </div>
 * ============================================================== */
function sol_render_vc_row_inner( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'el_class'          => '',
		'el_id'             => '',
		'css'               => '',
		'equal_height'      => '',
		'content_placement' => '',
		'gap'               => '',
		'rtl_reverse'       => '',
		'disable_element'   => '',
	), $atts, 'vc_row_inner' );

	if ( 'yes' === $atts['disable_element'] ) {
		return '';
	}

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_row_inner_base_css', 1 );

	$css_parsed = sol_vc_parse_css_attribute( $atts['css'] );

	$css_classes = array(
		'vc_row',
		'wpb_row',
		'vc_inner',
		'vc_row-fluid',
	);

	if ( $css_parsed['class'] ) {
		$css_classes[] = $css_parsed['class'];
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$css_classes[] = $atts['el_class'];
	}

	// Has fill (border/background).
	if ( ! empty( $css_parsed['style'] ) && preg_match( '/border|background/', $css_parsed['style'] ) ) {
		$css_classes[] = 'vc_row-has-fill';
	}

	// Column gap.
	if ( ! empty( $atts['gap'] ) ) {
		$css_classes[] = 'vc_column-gap-' . esc_attr( $atts['gap'] );
	}

	$flex_row = false;

	// Equal height.
	if ( ! empty( $atts['equal_height'] ) ) {
		$flex_row      = true;
		$css_classes[] = 'vc_row-o-equal-height';
	}

	// RTL reverse.
	if ( ! empty( $atts['rtl_reverse'] ) ) {
		$css_classes[] = 'vc_rtl-columns-reverse';
	}

	// Content placement.
	if ( ! empty( $atts['content_placement'] ) ) {
		$flex_row      = true;
		$css_classes[] = 'vc_row-o-content-' . esc_attr( $atts['content_placement'] );
	}

	if ( $flex_row ) {
		$css_classes[] = 'vc_row-flex';
	}

	$wrapper_attrs = array();
	if ( ! empty( $atts['el_id'] ) ) {
		$wrapper_attrs[] = 'id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	$class_string    = esc_attr( implode( ' ', array_filter( array_unique( $css_classes ) ) ) );
	$wrapper_attrs[] = 'class="' . $class_string . '"';

	// Inline style from css attribute.
	$style_attr = '';
	if ( $css_parsed['style'] ) {
		$style_attr = ' style="' . esc_attr( $css_parsed['style'] ) . '"';
	}

	$inner_content = do_shortcode( shortcode_unautop( trim( $content ) ) );

	$output  = '<div ' . implode( ' ', $wrapper_attrs ) . $style_attr . '>';
	$output .= $inner_content;
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * [vc_column_inner]  –  Inner column
 *
 * WPBakery output:
 *   <div class="wpb_column vc_column_container vc_col-sm-{n} {el_class}">
 *     <div class="vc_column-inner {css_class}">
 *       <div class="wpb_wrapper">
 *         …content…
 *       </div>
 *     </div>
 *   </div>
 * ============================================================== */
function sol_render_vc_column_inner( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'width'    => '',
		'el_class' => '',
		'el_id'    => '',
		'css'      => '',
		'offset'   => '',
	), $atts, 'vc_column_inner' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_row_inner_base_css', 1 );

	$css_parsed = sol_vc_parse_css_attribute( $atts['css'] );
	$span       = sol_vc_width_to_span( $atts['width'] );
	$span_class = 'vc_col-sm-' . $span;

	// Merge offset responsive classes.
	if ( ! empty( $atts['offset'] ) ) {
		// offset can be like "vc_col-md-6 vc_col-xs-12"
		$span_class .= ' ' . $atts['offset'];
	}

	$outer_classes = array(
		'wpb_column',
		'vc_column_container',
		$span_class,
	);
	if ( ! empty( $atts['el_class'] ) ) {
		$outer_classes[] = $atts['el_class'];
	}

	// Has fill.
	if ( ! empty( $css_parsed['style'] ) && preg_match( '/border|background/', $css_parsed['style'] ) ) {
		$outer_classes[] = 'vc_col-has-fill';
	}

	$wrapper_attrs = array();
	$wrapper_attrs[] = 'class="' . esc_attr( implode( ' ', array_filter( $outer_classes ) ) ) . '"';
	if ( ! empty( $atts['el_id'] ) ) {
		$wrapper_attrs[] = 'id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Inner column class + custom css class.
	$inner_class = 'vc_column-inner';
	if ( $css_parsed['class'] ) {
		$inner_class .= ' ' . $css_parsed['class'];
	}

	// Inline style from css attribute.
	$style_attr = '';
	if ( $css_parsed['style'] ) {
		$style_attr = ' style="' . esc_attr( $css_parsed['style'] ) . '"';
	}

	$inner_content = do_shortcode( shortcode_unautop( trim( $content ) ) );

	$output  = '<div ' . implode( ' ', $wrapper_attrs ) . '>';
	$output .= '<div class="' . esc_attr( trim( $inner_class ) ) . '"' . $style_attr . '>';
	$output .= '<div class="wpb_wrapper">';
	$output .= $inner_content;
	$output .= '</div>';
	$output .= '</div>';
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * [vc_column_text]  –  Text block
 *
 * WPBakery output:
 *   <div class="wpb_text_column wpb_content_element {css_class} {el_class}">
 *     <div class="wpb_wrapper">
 *       {content with wpautop}
 *     </div>
 *   </div>
 * ============================================================== */
function sol_render_vc_column_text( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'el_class'      => '',
		'el_id'         => '',
		'css'           => '',
		'css_animation' => '',
	), $atts, 'vc_column_text' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_row_inner_base_css', 1 );

	$css_parsed = sol_vc_parse_css_attribute( $atts['css'] );

	$css_classes = array( 'wpb_text_column', 'wpb_content_element' );
	if ( $css_parsed['class'] ) {
		$css_classes[] = $css_parsed['class'];
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$css_classes[] = $atts['el_class'];
	}

	$wrapper_attrs = array();
	if ( ! empty( $atts['el_id'] ) ) {
		$wrapper_attrs[] = 'id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Inline style from css attribute.
	$style_attr = '';
	if ( $css_parsed['style'] ) {
		$style_attr = ' style="' . esc_attr( $css_parsed['style'] ) . '"';
	}

	// Apply wpautop to content (matches WPBakery behavior with $autop = true).
	$processed_content = wpautop( preg_replace( '/<\/?p\>/', "\n", $content ) . "\n" );
	$processed_content = do_shortcode( shortcode_unautop( $processed_content ) );

	$output  = '<div class="' . esc_attr( implode( ' ', array_filter( $css_classes ) ) ) . '"';
	$output .= ' ' . implode( ' ', $wrapper_attrs );
	$output .= $style_attr . '>';
	$output .= '<div class="wpb_wrapper">';
	$output .= $processed_content;
	$output .= '</div>';
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * Base CSS  –  output once per page
 *
 * Includes WPBakery grid system (vc_col-sm-*), row styles,
 * column-inner wrapper, and text column base styles.
 * ============================================================== */
function sol_vc_row_inner_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-row-inner-base-css">
		/* ---- Reset box-sizing ---- */
		.vc_row *,
		.vc_row *::before,
		.vc_row *::after { box-sizing: border-box; }

		/* ---- Row ---- */
		.vc_row {
			margin-left: -15px;
			margin-right: -15px;
		}
		.vc_row::before,
		.vc_row::after {
			content: " ";
			display: table;
		}
		.vc_row::after { clear: both; }

		.vc_inner { margin-bottom: 0; }

		/* Row has fill – reset negative margins when bg/border applied */
		.vc_row-has-fill > .vc_column_container > .vc_column-inner { padding-top: 35px; }

		/* Flex row */
		.vc_row-flex { display: flex; flex-wrap: wrap; }
		.vc_row-flex > .vc_column_container { display: flex; }
		.vc_row-flex > .vc_column_container > .vc_column-inner {
			flex-grow: 1;
			display: flex;
			flex-direction: column;
		}
		.vc_row-o-equal-height > .vc_column_container { align-self: stretch; }
		.vc_row-o-content-top > .vc_column_container > .vc_column-inner { justify-content: flex-start; }
		.vc_row-o-content-middle > .vc_column_container > .vc_column-inner { justify-content: center; }
		.vc_row-o-content-bottom > .vc_column_container > .vc_column-inner { justify-content: flex-end; }

		/* ---- Column container ---- */
		.vc_column_container { width: 100%; }
		.vc_column_container > .vc_column-inner { width: 100%; }

		/* ---- Grid columns: common styles ---- */
		.vc_col-xs-1, .vc_col-sm-1, .vc_col-md-1, .vc_col-lg-1,
		.vc_col-xs-2, .vc_col-sm-2, .vc_col-md-2, .vc_col-lg-2,
		.vc_col-xs-3, .vc_col-sm-3, .vc_col-md-3, .vc_col-lg-3,
		.vc_col-xs-4, .vc_col-sm-4, .vc_col-md-4, .vc_col-lg-4,
		.vc_col-xs-5, .vc_col-sm-5, .vc_col-md-5, .vc_col-lg-5,
		.vc_col-xs-6, .vc_col-sm-6, .vc_col-md-6, .vc_col-lg-6,
		.vc_col-xs-7, .vc_col-sm-7, .vc_col-md-7, .vc_col-lg-7,
		.vc_col-xs-8, .vc_col-sm-8, .vc_col-md-8, .vc_col-lg-8,
		.vc_col-xs-9, .vc_col-sm-9, .vc_col-md-9, .vc_col-lg-9,
		.vc_col-xs-10, .vc_col-sm-10, .vc_col-md-10, .vc_col-lg-10,
		.vc_col-xs-11, .vc_col-sm-11, .vc_col-md-11, .vc_col-lg-11,
		.vc_col-xs-12, .vc_col-sm-12, .vc_col-md-12, .vc_col-lg-12 {
			position: relative;
			min-height: 1px;
			padding-left: 15px;
			padding-right: 15px;
			box-sizing: border-box;
		}

		/* ---- XS grid (always active) ---- */
		.vc_col-xs-1, .vc_col-xs-2, .vc_col-xs-3, .vc_col-xs-4,
		.vc_col-xs-5, .vc_col-xs-6, .vc_col-xs-7, .vc_col-xs-8,
		.vc_col-xs-9, .vc_col-xs-10, .vc_col-xs-11, .vc_col-xs-12 { float: left; }
		.vc_col-xs-1  { width: 8.33333333%; }
		.vc_col-xs-2  { width: 16.66666667%; }
		.vc_col-xs-3  { width: 25%; }
		.vc_col-xs-4  { width: 33.33333333%; }
		.vc_col-xs-5  { width: 41.66666667%; }
		.vc_col-xs-6  { width: 50%; }
		.vc_col-xs-7  { width: 58.33333333%; }
		.vc_col-xs-8  { width: 66.66666667%; }
		.vc_col-xs-9  { width: 75%; }
		.vc_col-xs-10 { width: 83.33333333%; }
		.vc_col-xs-11 { width: 91.66666667%; }
		.vc_col-xs-12 { width: 100%; }

		/* ---- SM grid (≥ 768px) ---- */
		@media (min-width: 768px) {
			.vc_col-sm-1, .vc_col-sm-2, .vc_col-sm-3, .vc_col-sm-4,
			.vc_col-sm-5, .vc_col-sm-6, .vc_col-sm-7, .vc_col-sm-8,
			.vc_col-sm-9, .vc_col-sm-10, .vc_col-sm-11, .vc_col-sm-12 { float: left; }
			.vc_col-sm-1  { width: 8.33333333%; }
			.vc_col-sm-2  { width: 16.66666667%; }
			.vc_col-sm-3  { width: 25%; }
			.vc_col-sm-4  { width: 33.33333333%; }
			.vc_col-sm-5  { width: 41.66666667%; }
			.vc_col-sm-6  { width: 50%; }
			.vc_col-sm-7  { width: 58.33333333%; }
			.vc_col-sm-8  { width: 66.66666667%; }
			.vc_col-sm-9  { width: 75%; }
			.vc_col-sm-10 { width: 83.33333333%; }
			.vc_col-sm-11 { width: 91.66666667%; }
			.vc_col-sm-12 { width: 100%; }
		}

		/* ---- MD grid (≥ 992px) ---- */
		@media (min-width: 992px) {
			.vc_col-md-1, .vc_col-md-2, .vc_col-md-3, .vc_col-md-4,
			.vc_col-md-5, .vc_col-md-6, .vc_col-md-7, .vc_col-md-8,
			.vc_col-md-9, .vc_col-md-10, .vc_col-md-11, .vc_col-md-12 { float: left; }
			.vc_col-md-1  { width: 8.33333333%; }
			.vc_col-md-2  { width: 16.66666667%; }
			.vc_col-md-3  { width: 25%; }
			.vc_col-md-4  { width: 33.33333333%; }
			.vc_col-md-5  { width: 41.66666667%; }
			.vc_col-md-6  { width: 50%; }
			.vc_col-md-7  { width: 58.33333333%; }
			.vc_col-md-8  { width: 66.66666667%; }
			.vc_col-md-9  { width: 75%; }
			.vc_col-md-10 { width: 83.33333333%; }
			.vc_col-md-11 { width: 91.66666667%; }
			.vc_col-md-12 { width: 100%; }
		}

		/* ---- LG grid (≥ 1200px) ---- */
		@media (min-width: 1200px) {
			.vc_col-lg-1, .vc_col-lg-2, .vc_col-lg-3, .vc_col-lg-4,
			.vc_col-lg-5, .vc_col-lg-6, .vc_col-lg-7, .vc_col-lg-8,
			.vc_col-lg-9, .vc_col-lg-10, .vc_col-lg-11, .vc_col-lg-12 { float: left; }
			.vc_col-lg-1  { width: 8.33333333%; }
			.vc_col-lg-2  { width: 16.66666667%; }
			.vc_col-lg-3  { width: 25%; }
			.vc_col-lg-4  { width: 33.33333333%; }
			.vc_col-lg-5  { width: 41.66666667%; }
			.vc_col-lg-6  { width: 50%; }
			.vc_col-lg-7  { width: 58.33333333%; }
			.vc_col-lg-8  { width: 66.66666667%; }
			.vc_col-lg-9  { width: 75%; }
			.vc_col-lg-10 { width: 83.33333333%; }
			.vc_col-lg-11 { width: 91.66666667%; }
			.vc_col-lg-12 { width: 100%; }
		}

		/* ---- Column gap variants ---- */
		.vc_column-gap-1 > .vc_column_container { padding-left: 1px; padding-right: 1px; }
		.vc_column-gap-2 > .vc_column_container { padding-left: 2px; padding-right: 2px; }
		.vc_column-gap-3 > .vc_column_container { padding-left: 3px; padding-right: 3px; }
		.vc_column-gap-5 > .vc_column_container { padding-left: 5px; padding-right: 5px; }
		.vc_column-gap-10 > .vc_column_container { padding-left: 10px; padding-right: 10px; }
		.vc_column-gap-15 > .vc_column_container { padding-left: 15px; padding-right: 15px; }
		.vc_column-gap-20 > .vc_column_container { padding-left: 20px; padding-right: 20px; }
		.vc_column-gap-25 > .vc_column_container { padding-left: 25px; padding-right: 25px; }
		.vc_column-gap-30 > .vc_column_container { padding-left: 30px; padding-right: 30px; }
		.vc_column-gap-35 > .vc_column_container { padding-left: 35px; padding-right: 35px; }

		/* ---- Column inner ---- */
		.vc_column-inner {
			padding-top: 0;
			padding-bottom: 0;
		}
		.vc_column-inner::before,
		.vc_column-inner::after {
			content: " ";
			display: table;
		}
		.vc_column-inner::after { clear: both; }

		/* ---- Wrapper ---- */
		.wpb_wrapper::before,
		.wpb_wrapper::after {
			content: " ";
			display: table;
		}
		.wpb_wrapper::after { clear: both; }

		/* ---- Text column ---- */
		.wpb_text_column { margin-bottom: 35px; }
		.wpb_text_column:last-child { margin-bottom: 0; }
		.wpb_text_column p:last-child { margin-bottom: 0; }

		/* ---- Content element ---- */
		.wpb_content_element { margin-bottom: 35px; }
		.wpb_content_element:last-child { margin-bottom: 0; }

		/* ---- Responsive: stack columns on mobile ---- */
		@media (max-width: 767px) {
			.vc_col-sm-1, .vc_col-sm-2, .vc_col-sm-3, .vc_col-sm-4,
			.vc_col-sm-5, .vc_col-sm-6, .vc_col-sm-7, .vc_col-sm-8,
			.vc_col-sm-9, .vc_col-sm-10, .vc_col-sm-11 {
				width: 100%;
				float: none;
			}
		}
	</style>
	<?php
}
