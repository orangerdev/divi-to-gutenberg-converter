<?php
/**
 * Plugin Name: MK Font Icons Standalone
 * Description: Standalone replacement for Jupiter Donut mk_font_icons shortcode.
 *              Renders SVG icons with optional color, gradient, circle, and link support.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_font_icons' ) ) {
		add_shortcode( 'mk_font_icons', 'sol_render_mk_font_icons' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'mk_font_icons' ) ) {
		add_action( 'wp_head', 'sol_mk_font_icons_base_css' );
	}
} );

/* ================================================================
 * [mk_font_icons]  –  SVG icon element
 *
 * Jupiter Donut output:
 *   <div class="mk-font-icons icon-align-{align} {el_class}" id="mk-font-icons-{id}"
 *        style="margin:{v}px {h}px;">
 *     <i class="font-icon mk-size-{size}">
 *       <svg class="mk-svg-icon" ...>...</svg>
 *     </i>
 *   </div>
 * ============================================================== */
function sol_render_mk_font_icons( $atts ) {
	$atts = shortcode_atts( array(
		'icon'                     => '',
		'size'                     => 'small',
		'color_style'              => 'single_color',
		'color'                    => '',
		'grandient_color_from'     => '',
		'grandient_color_to'       => '',
		'grandient_color_angle'    => 'vertical',
		'grandient_color_style'    => 'linear',
		'grandient_color_fallback' => '',
		'circle'                   => 'false',
		'circle_color'             => '',
		'circle_border_color'      => '',
		'circle_border_style'      => 'solid',
		'circle_border_width'      => '1',
		'margin_horizental'        => '4',
		'margin_vertical'          => '4',
		'align'                    => 'none',
		'link'                     => '',
		'target'                   => '_self',
		'visibility'               => '',
		'el_class'                 => '',
	), $atts, 'mk_font_icons' );

	$icon = $atts['icon'];
	if ( empty( $icon ) ) {
		return '';
	}

	// Ensure icon name has mk- prefix.
	if ( false === strpos( $icon, 'mk-' ) ) {
		$icon = 'mk-' . $icon;
	}

	// Generate unique ID per instance.
	static $instance = 0;
	$instance++;
	$id = 'mk-font-icons-' . $instance . '-' . substr( md5( $icon . $instance ), 0, 6 );

	// --- Resolve SVG icon ---
	$svg = sol_mk_fi_get_svg( $icon );

	if ( empty( $svg ) ) {
		return '';
	}

	// Size map.
	$sizes = array(
		'small'     => 16,
		'medium'    => 32,
		'large'     => 48,
		'x-large'   => 64,
		'xx-large'  => 128,
		'xxx-large' => 256,
	);
	$size    = isset( $sizes[ $atts['size'] ] ) ? $atts['size'] : 'small';
	$size_px = $sizes[ $size ];

	// --- Apply SVG attributes ---
	$svg_id    = 'icon-' . $id;
	$svg_style = '';

	if ( 'gradient_color' === $atts['color_style']
		&& ! empty( $atts['grandient_color_from'] )
		&& ! empty( $atts['grandient_color_to'] )
	) {
		// Add gradient defs.
		$gradient_type = $atts['grandient_color_style'];
		$cords         = sol_mk_fi_gradient_cords( $atts['grandient_color_angle'] );

		if ( 'radial' === $gradient_type ) {
			$defs = '<defs><radialGradient id="gradient-' . esc_attr( $svg_id ) . '">'
				. '<stop offset="0%" stop-color="' . esc_attr( $atts['grandient_color_from'] ) . '"/>'
				. '<stop offset="100%" stop-color="' . esc_attr( $atts['grandient_color_to'] ) . '"/>'
				. '</radialGradient></defs>';
		} else {
			$defs = '<defs><linearGradient id="gradient-' . esc_attr( $svg_id ) . '" ' . $cords . '>'
				. '<stop offset="0%" stop-color="' . esc_attr( $atts['grandient_color_from'] ) . '"/>'
				. '<stop offset="100%" stop-color="' . esc_attr( $atts['grandient_color_to'] ) . '"/>'
				. '</linearGradient></defs>';
		}

		// Insert defs after first >.
		$svg = preg_replace( '/>/', '>' . $defs, $svg, 1 );

		// Set fill on path.
		$svg = preg_replace( '/<path/', '<path fill="url(#gradient-' . esc_attr( $svg_id ) . ')"', $svg, 1 );
	} elseif ( ! empty( $atts['color'] ) ) {
		$svg_style .= 'fill:' . esc_attr( $atts['color'] ) . ';';
	}

	// Add class and data attributes to SVG.
	$svg_attrs = ' class="mk-svg-icon" data-name="' . esc_attr( $icon ) . '" data-cacheid="' . esc_attr( $svg_id ) . '"';
	if ( ! empty( $svg_style ) ) {
		$svg_attrs .= ' style="' . $svg_style . '"';
	}
	$svg = preg_replace( '/<svg/', '<svg' . $svg_attrs, $svg, 1 );

	// --- Circle styling ---
	$circle_classes = '';
	$circle_css     = '';
	if ( 'true' === $atts['circle'] ) {
		$circle_classes = ' circle-enabled center-icon';
		$circle_css  = 'background-color:' . esc_attr( $atts['circle_color'] ) . ';';
		$circle_css .= 'border-width:' . absint( $atts['circle_border_width'] ) . 'px;';
		$circle_css .= 'border-color:' . esc_attr( $atts['circle_border_color'] ) . ';';
		$circle_css .= 'border-style:' . esc_attr( $atts['circle_border_style'] ) . ';';
	}

	// --- Build icon element ---
	$icon_style = ! empty( $circle_css ) ? ' style="' . $circle_css . '"' : '';
	$icon_html  = '<i class="font-icon mk-size-' . esc_attr( $size ) . $circle_classes . '"' . $icon_style . '>' . $svg . '</i>';

	// --- Wrap with link ---
	if ( ! empty( $atts['link'] ) ) {
		$icon_html = '<a href="' . esc_url( $atts['link'] ) . '" target="' . esc_attr( $atts['target'] ) . '">' . $icon_html . '</a>';
	}

	// --- Container classes ---
	$container_classes = array( 'mk-font-icons', 'icon-align-' . esc_attr( $atts['align'] ) );
	if ( ! empty( $atts['visibility'] ) ) {
		$container_classes[] = 'jupiter-donut-' . esc_attr( $atts['visibility'] );
	}
	if ( ! empty( $atts['el_class'] ) ) {
		$container_classes[] = $atts['el_class'];
	}

	// --- Margins ---
	$margin_v = absint( $atts['margin_vertical'] );
	$margin_h = absint( $atts['margin_horizental'] );

	$output  = '<div class="' . esc_attr( trim( implode( ' ', $container_classes ) ) ) . '"';
	$output .= ' id="' . esc_attr( $id ) . '"';
	$output .= ' style="margin:' . $margin_v . 'px ' . $margin_h . 'px;"';
	$output .= '>';
	$output .= $icon_html;
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * Helper: load SVG icon from jupiter-donut assets
 * ============================================================== */
function sol_mk_fi_get_svg( $icon_name ) {
	// Font family mapping (same as Jupiter Donut).
	$families = array(
		'mk-icon'         => 'awesome-icons',
		'mk-moon'         => 'icomoon',
		'mk-li'           => 'pe-line-icons',
		'mk-jupiter-icon' => 'theme-icons',
	);

	// Determine font family from icon prefix.
	$family = '';
	foreach ( $families as $prefix => $fam ) {
		if ( 0 === strpos( $icon_name, $prefix ) ) {
			$family = $fam;
			break;
		}
	}

	if ( empty( $family ) ) {
		return '';
	}

	// Try to find jupiter-donut assets directory.
	$base_dir = WP_PLUGIN_DIR . '/jupiter-donut/assets/icons/' . $family;
	if ( ! is_dir( $base_dir ) ) {
		return '';
	}

	// Load map.json to get unicode.
	$map_file = $base_dir . '/map.json';
	if ( ! file_exists( $map_file ) ) {
		return '';
	}

	static $maps = array();
	if ( ! isset( $maps[ $family ] ) ) {
		$maps[ $family ] = json_decode( file_get_contents( $map_file ), true );
	}

	if ( ! isset( $maps[ $family ][ $icon_name ] ) ) {
		return '';
	}

	$unicode  = $maps[ $family ][ $icon_name ];
	$svg_file = $base_dir . '/svg/' . $unicode . '.svg';

	if ( ! file_exists( $svg_file ) ) {
		return '';
	}

	return file_get_contents( $svg_file );
}

/* ================================================================
 * Helper: gradient coordinate strings for SVG linearGradient
 * ============================================================== */
function sol_mk_fi_gradient_cords( $direction ) {
	switch ( $direction ) {
		case 'horizontal':
			return 'x1="0%" y1="0%" x2="100%" y2="0%"';
		case 'diagonal_left_bottom':
			return 'x1="0%" y1="100%" x2="100%" y2="0%"';
		case 'diagonal_left_top':
			return 'x1="0%" y1="0%" x2="100%" y2="100%"';
		case 'vertical':
		default:
			return 'x1="0%" y1="100%" x2="0%" y2="0%"';
	}
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_mk_font_icons_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-font-icons-base-css">
		.mk-font-icons { display: inline-block; }
		.mk-font-icons.icon-align-right { float: right; }
		.mk-font-icons.icon-align-center { display: block; text-align: center; }
		.mk-font-icons.icon-align-left { float: left; }

		.mk-font-icons .font-icon { display: inline-block; line-height: normal; }
		.mk-font-icons .font-icon.circle-enabled { text-align: center; border-radius: 256px; }
		.mk-font-icons .circle-enabled .mk-svg-icon { margin: 0 auto; }

		/* Size: small (16px) */
		.mk-font-icons .font-icon.mk-size-small { font-size: 16px; }
		.mk-font-icons .font-icon.mk-size-small svg { height: 16px; width: 16px; }
		.mk-font-icons .font-icon.mk-size-small.circle-enabled { padding: 3px; width: 16px; height: 16px; font-size: 12px; }
		.mk-font-icons .font-icon.mk-size-small.circle-enabled svg { height: 12px; width: 12px; }

		/* Size: medium (32px) */
		.mk-font-icons .font-icon.mk-size-medium { font-size: 32px; }
		.mk-font-icons .font-icon.mk-size-medium svg { height: 32px; width: 32px; }
		.mk-font-icons .font-icon.mk-size-medium.circle-enabled { padding: 3px; width: 32px; height: 32px; font-size: 16px; }
		.mk-font-icons .font-icon.mk-size-medium.circle-enabled svg { height: 16px; width: 16px; }

		/* Size: large (48px) */
		.mk-font-icons .font-icon.mk-size-large { font-size: 48px; }
		.mk-font-icons .font-icon.mk-size-large svg { height: 48px; width: 48px; }
		.mk-font-icons .font-icon.mk-size-large.circle-enabled { width: 48px; height: 48px; font-size: 24px; }
		.mk-font-icons .font-icon.mk-size-large.circle-enabled svg { height: 24px; width: 24px; }

		/* Size: x-large (64px) */
		.mk-font-icons .font-icon.mk-size-x-large { font-size: 64px; }
		.mk-font-icons .font-icon.mk-size-x-large svg { height: 64px; width: 64px; }
		.mk-font-icons .font-icon.mk-size-x-large.circle-enabled { width: 64px; height: 64px; font-size: 32px; }
		.mk-font-icons .font-icon.mk-size-x-large.circle-enabled svg { height: 32px; width: 32px; }

		/* Size: xx-large (128px) */
		.mk-font-icons .font-icon.mk-size-xx-large { font-size: 128px; }
		.mk-font-icons .font-icon.mk-size-xx-large svg { height: 128px; width: 128px; }
		.mk-font-icons .font-icon.mk-size-xx-large.circle-enabled { width: 128px; height: 128px; font-size: 48px; }
		.mk-font-icons .font-icon.mk-size-xx-large.circle-enabled svg { height: 48px; width: 48px; }

		/* Size: xxx-large (256px) */
		.mk-font-icons .font-icon.mk-size-xxx-large { font-size: 256px; }
		.mk-font-icons .font-icon.mk-size-xxx-large svg { height: 256px; width: 256px; }
		.mk-font-icons .font-icon.mk-size-xxx-large.circle-enabled { width: 256px; height: 256px; font-size: 64px; }
		.mk-font-icons .font-icon.mk-size-xxx-large.circle-enabled svg { height: 64px; width: 64px; }
	</style>
	<?php
}
