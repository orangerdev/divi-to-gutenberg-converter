<?php
/**
 * Plugin Name: MK Imagebox Standalone
 * Description: Standalone replacement for Jupiter Donut mk_imagebox / mk_imagebox_item shortcodes.
 *              Mirrors the original column-style rendering with per-item styling.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcodes
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_imagebox' ) ) {
		add_shortcode( 'mk_imagebox', 'sol_render_mk_imagebox' );
	}
	if ( ! shortcode_exists( 'mk_imagebox_item' ) ) {
		add_shortcode( 'mk_imagebox_item', 'sol_render_mk_imagebox_item' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output base CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return;
	}
	$content = $post->post_content;
	if ( has_shortcode( $content, 'mk_imagebox' ) || has_shortcode( $content, 'mk_imagebox_item' ) ) {
		add_action( 'wp_head', 'sol_mk_imagebox_base_css' );
	}
} );

/* ---- Per-instance CSS buffer ---- */
function sol_mk_imagebox_collect_instance_css( $css = null ) {
	static $buffer = '';
	if ( null !== $css ) {
		$buffer .= $css . "\n";
	}
	return $buffer;
}
function sol_mk_imagebox_output_instance_css() {
	$css = sol_mk_imagebox_collect_instance_css();
	if ( ! empty( $css ) ) {
		printf( '<style id="mk-imagebox-instance-css">%s</style>', $css );
	}
}

/* ================================================================
 * [mk_imagebox]  –  Parent container (column layout)
 *
 * Jupiter Donut output (column mode):
 *   <div id="mk-imagebox-{id}" class="mk-imagebox column-style {el_class}">
 *     <div class="{column_class}">
 *       {child items}
 *     </div>
 *     <div class="clearboth"></div>
 *   </div>
 * ============================================================== */
function sol_render_mk_imagebox( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'show_as'         => 'column',
		'column'          => 3,
		'padding'         => 20,
		'scroll_nav'      => 'true',
		'per_view'        => 4,
		'animation_speed' => 700,
		'slideshow_speed' => 7000,
		'visibility'      => '',
		'el_class'        => '',
	), $atts, 'mk_imagebox' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_mk_imagebox_base_css', 1 );
	add_action( 'wp_footer', 'sol_mk_imagebox_output_instance_css', 2 );

	$column = absint( $atts['column'] );
	if ( $column < 1 || $column > 6 ) {
		$column = 3;
	}

	$column_map = array(
		1 => 'one-column',
		2 => 'two-column',
		3 => 'three-column',
		4 => 'four-column',
		5 => 'five-column',
		6 => 'six-column',
	);
	$column_class = isset( $column_map[ $column ] ) ? $column_map[ $column ] : 'three-column';

	$id      = wp_unique_id( 'mk-imagebox-' );
	$padding = absint( $atts['padding'] );

	// Per-instance padding CSS → footer.
	sol_mk_imagebox_collect_instance_css(
		sprintf( '#%s .item-holder { margin: 0 %dpx; }', esc_attr( $id ), $padding )
	);

	ob_start();

	if ( ! empty( $atts['visibility'] ) ) {
		printf( '<div class="jupiter-donut-%s">', esc_attr( $atts['visibility'] ) );
	}

	printf(
		'<div id="%s" class="mk-imagebox column-style %s">',
		esc_attr( $id ),
		esc_attr( $atts['el_class'] )
	);

	printf( '<div class="%s">', esc_attr( $column_class ) );
	echo do_shortcode( $content );
	echo '</div>';
	echo '<div class="clearboth"></div>';
	echo '</div>';

	if ( ! empty( $atts['visibility'] ) ) {
		echo '</div>';
	}

	return ob_get_clean();
}

/* ================================================================
 * [mk_imagebox_item]  –  Individual image box card
 *
 * Jupiter Donut output:
 *   <div class="">
 *     <div id="imagebox-item-{id}" class="mk-imagebox-item {el_class} image-type">
 *       <div class="item-holder">
 *         <div class="item-wrapper">
 *           <div class="item-image padding-{true|false}">
 *             <img src="{item_image}" alt="{item_title}" />
 *           </div>
 *           <div class="item-title"><h5>{item_title}</h5></div>
 *           <div class="item-content"><span>{content}</span></div>
 *           <div class="item-button"><a href="{btn_url}">{btn_text}</a></div>
 *         </div>
 *       </div>
 *     </div>
 *   </div>
 * ============================================================== */
function sol_render_mk_imagebox_item( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'icon_type'                  => 'image',
		'item_image'                 => '',
		'image_padding'              => 'true',
		'background_color'           => '#eaeaea',
		'item_title'                 => '',
		'title_text_size'            => 16,
		'title_color'                => '',
		'title_font_weight'          => 'inherit',
		'text_color'                 => '',
		'btn_text'                   => '',
		'btn_text_color'             => '',
		'btn_background_color'       => '',
		'btn_hover_background_color' => '',
		'btn_url'                    => '',
		'el_class'                   => '',
	), $atts, 'mk_imagebox_item' );

	$id = wp_unique_id( 'imagebox-item-' );

	// Build per-instance CSS.
	$instance_css = '';

	if ( ! empty( $atts['background_color'] ) ) {
		$instance_css .= sprintf(
			'#%s .item-wrapper { background-color: %s; }',
			esc_attr( $id ),
			esc_attr( $atts['background_color'] )
		);
	}

	$title_styles = '';
	if ( ! empty( $atts['title_color'] ) ) {
		$title_styles .= 'color:' . esc_attr( $atts['title_color'] ) . ';';
	}
	if ( ! empty( $atts['title_font_weight'] ) && 'inherit' !== $atts['title_font_weight'] ) {
		$title_styles .= 'font-weight:' . esc_attr( $atts['title_font_weight'] ) . ';';
	}
	if ( ! empty( $atts['title_text_size'] ) && 16 != $atts['title_text_size'] ) {
		$title_styles .= 'font-size:' . absint( $atts['title_text_size'] ) . 'px;';
	}
	if ( $title_styles ) {
		$instance_css .= sprintf( '#%s .item-title h5 { %s }', esc_attr( $id ), $title_styles );
	}

	if ( ! empty( $atts['text_color'] ) ) {
		$instance_css .= sprintf(
			'#%1$s .item-content, #%1$s .item-content p { color: %2$s; }',
			esc_attr( $id ),
			esc_attr( $atts['text_color'] )
		);
	}

	if ( ! empty( $atts['btn_background_color'] ) || ! empty( $atts['btn_text_color'] ) ) {
		$btn_styles = '';
		if ( ! empty( $atts['btn_background_color'] ) ) {
			$btn_styles .= 'background-color:' . esc_attr( $atts['btn_background_color'] ) . ';';
		}
		if ( ! empty( $atts['btn_text_color'] ) ) {
			$btn_styles .= 'color:' . esc_attr( $atts['btn_text_color'] ) . ';';
		}
		$instance_css .= sprintf( '#%s .item-button a { %s }', esc_attr( $id ), $btn_styles );
	}

	if ( ! empty( $atts['btn_hover_background_color'] ) ) {
		$instance_css .= sprintf(
			'#%s .item-button a:hover { background-color: %s; }',
			esc_attr( $id ),
			esc_attr( $atts['btn_hover_background_color'] )
		);
	}

	// Per-instance CSS → footer.
	if ( $instance_css ) {
		sol_mk_imagebox_collect_instance_css( $instance_css );
	}

	ob_start();

	$icon_type = esc_attr( $atts['icon_type'] );
	?>
	<div class="">
		<div id="<?php echo esc_attr( $id ); ?>" class="mk-imagebox-item <?php echo esc_attr( $atts['el_class'] ); ?> <?php echo $icon_type; ?>-type">
			<div class="item-holder">
				<div class="item-wrapper">

					<?php if ( 'image' === $atts['icon_type'] && ! empty( $atts['item_image'] ) ) : ?>
						<div class="item-image padding-<?php echo esc_attr( $atts['image_padding'] ); ?>">
							<img src="<?php echo esc_url( $atts['item_image'] ); ?>"
								 alt="<?php echo esc_attr( $atts['item_title'] ); ?>"
								 loading="lazy" />
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $atts['item_title'] ) ) : ?>
						<div class="item-title">
							<h5><?php echo esc_html( $atts['item_title'] ); ?></h5>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $content ) ) : ?>
						<div class="item-content">
							<span><?php echo wp_kses_post( trim( $content ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $atts['btn_url'] ) ) : ?>
						<div class="item-button">
							<a href="<?php echo esc_url( $atts['btn_url'] ); ?>">
								<?php echo esc_html( $atts['btn_text'] ); ?>
							</a>
						</div>
					<?php endif; ?>

				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_mk_imagebox_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-imagebox-base-css">
		/* ---- Container ---- */
		.mk-imagebox { position: relative; }
		.mk-imagebox-item { margin-bottom: 40px; }
		.mk-imagebox .clearboth { clear: both; }

		/* ---- Column widths ---- */
		.mk-imagebox .one-column .mk-imagebox-item { width: 100%; }
		.mk-imagebox .two-column .mk-imagebox-item { width: 49.96%; }
		.mk-imagebox .three-column .mk-imagebox-item { width: 33.31%; }
		.mk-imagebox .four-column .mk-imagebox-item { width: 24.96%; }
		.mk-imagebox .five-column .mk-imagebox-item { width: 19.96%; }
		.mk-imagebox .six-column .mk-imagebox-item { width: 16.6%; }

		/* ---- Row clearing ---- */
		.mk-imagebox .two-column div:nth-of-type(2n+3) .mk-imagebox-item,
		.mk-imagebox .three-column div:nth-of-type(3n+4) .mk-imagebox-item,
		.mk-imagebox .four-column div:nth-of-type(4n+5) .mk-imagebox-item,
		.mk-imagebox .five-column div:nth-of-type(5n+6) .mk-imagebox-item,
		.mk-imagebox .six-column div:nth-of-type(6n+7) .mk-imagebox-item { clear: both; }

		/* ---- Item layout ---- */
		.mk-imagebox-item { float: left; }
		.mk-imagebox-item .item-holder {
			position: relative;
			overflow: hidden;
		}
		.mk-imagebox-item .item-image {
			text-align: center;
			padding: 0;
		}
		.mk-imagebox-item .item-image img {
			width: 100%;
			display: block;
		}
		.mk-imagebox-item .item-image.padding-true {
			padding: 5% 5% 0;
		}
		.mk-imagebox-item .item-title {
			line-height: 1.66em;
			padding: 10% 10% 7%;
			letter-spacing: 1px;
		}
		.mk-imagebox-item .item-title h5 {
			margin: 0;
			text-align: center;
		}
		.mk-imagebox-item .item-content {
			padding: 0% 13% 7%;
		}
		.mk-imagebox-item .item-wrapper p {
			text-align: center;
		}
		.mk-imagebox-item .item-button a {
			display: block;
			font-size: 14px;
			letter-spacing: 1px;
			padding: 20px;
			text-align: center;
			transition: all 0.2s ease-out;
			text-decoration: none;
		}

		/* ---- Responsive ---- */
		@media handheld, only screen and (max-width: 960px) {
			.mk-imagebox .two-column .mk-imagebox-item,
			.mk-imagebox .three-column .mk-imagebox-item,
			.mk-imagebox .four-column .mk-imagebox-item,
			.mk-imagebox .five-column .mk-imagebox-item,
			.mk-imagebox .six-column .mk-imagebox-item {
				width: 50%;
				margin-bottom: 20px;
			}
			.mk-imagebox .two-column div:nth-of-type(2n+3) .mk-imagebox-item,
			.mk-imagebox .three-column div:nth-of-type(3n+4) .mk-imagebox-item,
			.mk-imagebox .four-column div:nth-of-type(4n+5) .mk-imagebox-item,
			.mk-imagebox .five-column div:nth-of-type(5n+6) .mk-imagebox-item,
			.mk-imagebox .six-column div:nth-of-type(6n+7) .mk-imagebox-item { clear: none; }
			.mk-imagebox .three-column div:nth-of-type(2n+3) .mk-imagebox-item,
			.mk-imagebox .four-column div:nth-of-type(2n+3) .mk-imagebox-item,
			.mk-imagebox .five-column div:nth-of-type(2n+3) .mk-imagebox-item,
			.mk-imagebox .six-column div:nth-of-type(2n+3) .mk-imagebox-item { clear: both; }
			.mk-imagebox .two-column img,
			.mk-imagebox .three-column img,
			.mk-imagebox .four-column img,
			.mk-imagebox .five-column img,
			.mk-imagebox .six-column img { width: 100%; }
		}
		@media handheld, only screen and (max-width: 540px) {
			.mk-imagebox .mk-imagebox-item { width: 100% !important; }
			.mk-imagebox .mk-imagebox-item .item-holder { margin: 0 !important; }
		}
	</style>
	<?php
}
