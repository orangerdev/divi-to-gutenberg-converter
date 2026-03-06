<?php
/**
 * Plugin Name: VC WP Search Standalone
 * Description: Standalone replacement for WPBakery vc_wp_search shortcode.
 *              Renders the WordPress search widget inside a wrapper div.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_wp_search' ) ) {
		add_shortcode( 'vc_wp_search', 'sol_render_vc_wp_search' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vc_wp_search' ) ) {
		add_action( 'wp_head', 'sol_vc_wp_search_base_css' );
	}
} );

/* ================================================================
 * [vc_wp_search]  –  WordPress Search Widget
 *
 * WPBakery output:
 *   <div id="{el_id}" class="vc_wp_search wpb_content_element {el_class}">
 *     <div class="widget widget_search">
 *       <h2 class="widgettitle">{title}</h2>
 *       <form role="search" method="get" class="search-form" action="...">
 *         <label>
 *           <span class="screen-reader-text">Search for:</span>
 *           <input type="search" class="search-field" placeholder="Search &hellip;" value="" name="s" />
 *         </label>
 *         <input type="submit" class="search-submit" value="Search" />
 *       </form>
 *     </div>
 *   </div>
 * ============================================================== */
function sol_render_vc_wp_search( $atts ) {
	$atts = shortcode_atts( array(
		'title'    => '',
		'el_id'    => '',
		'el_class' => '',
	), $atts, 'vc_wp_search' );

	// Build outer wrapper attributes.
	$outer_classes = array( 'vc_wp_search', 'wpb_content_element' );
	if ( ! empty( $atts['el_class'] ) ) {
		$outer_classes[] = $atts['el_class'];
	}

	$id_attr = '';
	if ( ! empty( $atts['el_id'] ) ) {
		$id_attr = ' id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Render using WP_Widget_Search via the_widget() – mirrors original WPBakery behavior.
	$widget_output = '';
	global $wp_widget_factory;

	if ( is_object( $wp_widget_factory )
		&& isset( $wp_widget_factory->widgets, $wp_widget_factory->widgets['WP_Widget_Search'] )
	) {
		ob_start();
		the_widget(
			'WP_Widget_Search',
			array( 'title' => $atts['title'] ),
			array(
				'before_widget' => '<div class="widget widget_search">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="widgettitle">',
				'after_title'   => '</h2>',
			)
		);
		$widget_output = ob_get_clean();
	} else {
		// Fallback: render search form manually if widget factory unavailable.
		$widget_output  = '<div class="widget widget_search">';
		if ( ! empty( $atts['title'] ) ) {
			$widget_output .= '<h2 class="widgettitle">' . esc_html( $atts['title'] ) . '</h2>';
		}
		$widget_output .= get_search_form( false );
		$widget_output .= '</div>';
	}

	$output  = '<div' . $id_attr . ' class="' . esc_attr( trim( implode( ' ', $outer_classes ) ) ) . '">';
	$output .= $widget_output;
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_vc_wp_search_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-wp-search-base-css">
		/* ---- Container ---- */
		.vc_wp_search { margin-bottom: 35px; }
		.vc_wp_search:last-child { margin-bottom: 0; }

		/* ---- Widget title ---- */
		.vc_wp_search .widgettitle {
			font-size: 1em;
			margin-bottom: 10px;
		}

		/* ---- Search form ---- */
		.vc_wp_search .search-form {
			display: flex;
			align-items: stretch;
		}
		.vc_wp_search .search-form label {
			flex: 1 1 auto;
			margin: 0;
		}
		.vc_wp_search .search-field {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #ddd;
			border-radius: 3px 0 0 3px;
			font-size: 14px;
			line-height: 1.5;
			box-sizing: border-box;
			outline: none;
			transition: border-color .2s ease;
		}
		.vc_wp_search .search-field:focus {
			border-color: #0073aa;
		}
		.vc_wp_search .search-submit {
			padding: 8px 16px;
			border: 1px solid #ddd;
			border-left: none;
			border-radius: 0 3px 3px 0;
			background: #f7f7f7;
			font-size: 14px;
			line-height: 1.5;
			cursor: pointer;
			transition: background .2s ease;
		}
		.vc_wp_search .search-submit:hover {
			background: #eee;
		}
	</style>
	<?php
}
