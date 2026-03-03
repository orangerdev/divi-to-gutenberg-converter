<?php
/**
 * Plugin Name: MK Padding Divider Standalone
 * Description: Standalone replacement for Jupiter Donut mk_padding_divider shortcode.
 *              Renders a vertical spacer div with configurable height.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_padding_divider' ) ) {
		add_shortcode( 'mk_padding_divider', 'sol_render_mk_padding_divider' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'mk_padding_divider' ) ) {
		add_action( 'wp_head', 'sol_mk_padding_divider_visibility_css' );
	}
} );

/* ================================================================
 * [mk_padding_divider]  –  Vertical spacer
 *
 * Jupiter Donut output:
 *   <div class="mk-padding-divider" style="height:{size}px;"></div>
 * ============================================================== */
function sol_render_mk_padding_divider( $atts ) {
	$atts = shortcode_atts( array(
		'size'       => '40',
		'visibility' => '',
	), $atts, 'mk_padding_divider' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_mk_padding_divider_visibility_css', 1 );

	$height = absint( $atts['size'] );
	if ( $height <= 0 ) {
		$height = 40;
	}

	$classes = 'mk-padding-divider';
	if ( ! empty( $atts['visibility'] ) ) {
		$classes .= ' ' . esc_attr( $atts['visibility'] );
	}

	return '<div class="' . esc_attr( trim( $classes ) ) . '" style="height:' . $height . 'px;"></div>';
}

/* ================================================================
 * Visibility CSS  –  output once per page
 * ============================================================== */
function sol_mk_padding_divider_visibility_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-padding-divider-visibility-css">
		@media (max-width: 767px) {
			.mk-padding-divider.hidden-sm { display: none; }
		}
		@media (min-width: 768px) and (max-width: 1023px) {
			.mk-padding-divider.hidden-md { display: none; }
		}
		@media (min-width: 1024px) {
			.mk-padding-divider.hidden-lg { display: none; }
		}
	</style>
	<?php
}
