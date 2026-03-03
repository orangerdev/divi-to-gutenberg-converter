<?php
/**
 * Plugin Name: VC Hoverbox Standalone
 * Description: Standalone replacement for WPBakery vc_hoverbox shortcode (animated flip box).
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_hoverbox' ) ) {
		add_shortcode( 'vc_hoverbox', 'sol_render_vc_hoverbox' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vc_hoverbox' ) ) {
		add_action( 'wp_head', 'sol_vc_hoverbox_base_css' );
	}
} );

function sol_render_vc_hoverbox( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'image'                  => '',
		'primary_title'          => '',
		'hover_title'            => '',
		'shape'                  => 'rounded',
		'hover_background_color' => 'grey',
		'hover_custom_background' => '#EBEBEB',
		'el_width'               => '100',
		'align'                  => 'center',
		'primary_align'          => 'center',
		'hover_align'            => 'center',
		'reverse'                => '',
		'css_animation'          => '',
		'el_id'                  => '',
		'el_class'               => '',
		'custom_text'            => '',
		'style'                  => '',
	), $atts, 'vc_hoverbox' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_hoverbox_base_css', 1 );

	// Image.
	$image_src = '';
	if ( ! empty( $atts['image'] ) ) {
		$image_data = wp_get_attachment_image_src( intval( $atts['image'] ), 'large' );
		if ( $image_data ) {
			$image_src = esc_url( $image_data[0] );
		}
	}
	if ( empty( $image_src ) ) {
		$image_src = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect fill="#ccc" width="600" height="400"/></svg>' );
	}

	// CSS classes.
	$shape_class   = 'vc-hoverbox-shape--' . esc_attr( $atts['shape'] );
	$align_class   = 'vc-hoverbox-align--' . esc_attr( $atts['align'] );
	$width_class   = 'vc-hoverbox-width--' . esc_attr( $atts['el_width'] );
	$reverse_class = ! empty( $atts['reverse'] ) ? 'vc-hoverbox-direction--reverse' : 'vc-hoverbox-direction--default';
	$el_class      = esc_attr( $atts['el_class'] );
	$css_animation = esc_attr( $atts['css_animation'] );

	$id_attr = '';
	if ( ! empty( $atts['el_id'] ) ) {
		$id_attr = 'id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Hover background color.
	$color_map = array(
		'blue'         => '#5472d2',
		'turquoise'    => '#00c1cf',
		'pink'         => '#fe6c61',
		'violet'       => '#8d6dc4',
		'peacoc'       => '#4cadc9',
		'chino'        => '#cec2ab',
		'mulled-wine'  => '#50485b',
		'vista-blue'   => '#75d69c',
		'black'        => '#2a2a2a',
		'grey'         => '#ebebeb',
		'orange'       => '#f7be68',
		'sky'          => '#5aa1e3',
		'green'        => '#6dab3c',
		'juicy-pink'   => '#f4524d',
		'sandy-brown'  => '#f79468',
		'purple'       => '#b97ebb',
		'white'        => '#ffffff',
	);

	if ( 'custom' === $atts['hover_background_color'] ) {
		$hover_bg = esc_attr( $atts['hover_custom_background'] );
	} elseif ( isset( $color_map[ $atts['hover_background_color'] ] ) ) {
		$hover_bg = $color_map[ $atts['hover_background_color'] ];
	} else {
		$hover_bg = '#ebebeb';
	}

	// Titles.
	$primary_title_html = '';
	if ( '' !== trim( $atts['primary_title'] ) ) {
		$primary_title_html = '<h2 style="text-align:' . esc_attr( $atts['primary_align'] ) . '">' . esc_html( $atts['primary_title'] ) . '</h2>';
	}

	$hover_title_html = '';
	if ( '' !== trim( $atts['hover_title'] ) ) {
		$hover_title_html = '<h2 style="text-align:' . esc_attr( $atts['hover_align'] ) . '">' . esc_html( $atts['hover_title'] ) . '</h2>';
	}

	// Content.
	$content = do_shortcode( shortcode_unautop( wpautop( trim( $content ) ) ) );

	ob_start();
	?>
	<div class="vc-hoverbox-wrapper <?php echo $shape_class; ?> <?php echo $align_class; ?> <?php echo $reverse_class; ?> <?php echo $width_class; ?> <?php echo $el_class; ?> <?php echo $css_animation; ?>" <?php echo $id_attr; ?> ontouchstart="">
		<div class="vc-hoverbox">
			<div class="vc-hoverbox-inner">
				<div class="vc-hoverbox-block vc-hoverbox-front" style="background-image: url(<?php echo $image_src; ?>);">
					<div class="vc-hoverbox-block-inner vc-hoverbox-front-inner">
						<?php echo $primary_title_html; ?>
					</div>
				</div>
				<div class="vc-hoverbox-block vc-hoverbox-back" style="background-color: <?php echo $hover_bg; ?>;">
					<div class="vc-hoverbox-block-inner vc-hoverbox-back-inner">
						<?php echo $hover_title_html; ?>
						<?php echo $content; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Output base CSS once per page load.
 */
function sol_vc_hoverbox_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-hoverbox-base-css">
		.vc-hoverbox-wrapper,
		.vc-hoverbox-wrapper * {
			box-sizing: border-box;
		}
		.vc-hoverbox-wrapper.vc-hoverbox-shape--rounded .vc-hoverbox-front,
		.vc-hoverbox-wrapper.vc-hoverbox-shape--rounded .vc-hoverbox-back {
			border-radius: 10px;
		}
		.vc-hoverbox-wrapper.vc-hoverbox-shape--round .vc-hoverbox-front,
		.vc-hoverbox-wrapper.vc-hoverbox-shape--round .vc-hoverbox-back {
			border-radius: 50px;
		}
		.vc-hoverbox-wrapper.vc-hoverbox-align--center { text-align: center; }
		.vc-hoverbox-wrapper.vc-hoverbox-align--left   { text-align: left; }
		.vc-hoverbox-wrapper.vc-hoverbox-align--right  { text-align: right; }

		.vc-hoverbox-wrapper .vc-hoverbox {
			position: relative;
			display: inline-block;
			text-align: center;
			width: 100%;
		}
		.vc-hoverbox-wrapper.vc-hoverbox-width--100 .vc-hoverbox { width: 100%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--90  .vc-hoverbox { width: 90%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--80  .vc-hoverbox { width: 80%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--70  .vc-hoverbox { width: 70%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--60  .vc-hoverbox { width: 60%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--50  .vc-hoverbox { width: 50%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--40  .vc-hoverbox { width: 40%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--30  .vc-hoverbox { width: 30%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--20  .vc-hoverbox { width: 20%; }
		.vc-hoverbox-wrapper.vc-hoverbox-width--10  .vc-hoverbox { width: 10%; }

		.vc-hoverbox-inner {
			width: 100%;
			display: inline-block;
			min-height: 250px;
		}
		.vc-hoverbox-inner :last-child {
			margin-bottom: 0;
		}
		.vc-hoverbox-block {
			height: 100%;
			width: 100%;
			-webkit-backface-visibility: hidden;
			backface-visibility: hidden;
			position: absolute;
			top: 0;
			left: 0;
			display: flex;
			flex-direction: column;
			justify-content: center;
			transition: transform .5s ease-in-out;
			transform-style: preserve-3d;
			background-size: cover;
			background-position: center;
		}
		.vc-hoverbox-block-inner {
			flex-shrink: 0;
			padding: 20px;
		}
		.vc-hoverbox-front {
			transform: rotateY(0deg);
		}
		.vc-hoverbox-back {
			transform: rotateY(180deg);
		}
		.vc-hoverbox:hover .vc-hoverbox-front {
			transform: rotateY(-180deg);
		}
		.vc-hoverbox:hover .vc-hoverbox-back {
			transform: rotateY(0deg);
		}

		/* Reverse direction */
		.vc-hoverbox-direction--reverse .vc-hoverbox-front {
			transform: rotateY(180deg);
		}
		.vc-hoverbox-direction--reverse .vc-hoverbox-back {
			transform: rotateY(0deg);
			z-index: 2;
		}
		.vc-hoverbox-direction--reverse .vc-hoverbox:hover .vc-hoverbox-front {
			transform: rotateY(0deg);
		}
		.vc-hoverbox-direction--reverse .vc-hoverbox:hover .vc-hoverbox-back {
			transform: rotateY(-180deg);
		}
	</style>
	<?php
}
