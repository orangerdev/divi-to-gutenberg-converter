<?php
/**
 * Plugin Name: VC Single Image Standalone
 * Description: Standalone replacement for WPBakery vc_single_image shortcode.
 *              Mirrors the original WPBakery rendering including onclick="link_image" lightbox behavior.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_single_image' ) ) {
		add_shortcode( 'vc_single_image', 'sol_render_vc_single_image' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vc_single_image' ) ) {
		add_action( 'wp_head', 'sol_vc_single_image_base_css' );
	}
} );

/* ================================================================
 * [vc_single_image]  –  Single image element
 *
 * WPBakery output:
 *   <div class="wpb_single_image wpb_content_element vc_align_{alignment} {css_class} {el_class}">
 *     <figure class="wpb_wrapper vc_figure">
 *       <a href="{link}" class="vc_single_image-wrapper {style} {border}" data-lightbox="…">
 *         <img src="…" class="vc_single_image-img" />
 *       </a>
 *     </figure>
 *   </div>
 * ============================================================== */
function sol_render_vc_single_image( $atts, $content = '' ) {
	$atts = shortcode_atts( array(
		'title'             => '',
		'source'            => 'media_library',
		'image'             => '',
		'custom_src'        => '',
		'external_img_size' => '',
		'onclick'           => '',
		'img_size'          => 'full',
		'img_link_large'    => '',
		'link'              => '',
		'img_link_target'   => '_self',
		'alignment'         => '',
		'el_class'          => '',
		'el_id'             => '',
		'css_animation'     => '',
		'style'             => '',
		'external_style'    => '',
		'border_color'      => '',
		'external_border_color' => '',
		'css'               => '',
		'add_caption'       => '',
		'caption'           => '',
	), $atts, 'vc_single_image' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_single_image_base_css', 1 );

	// Backward compatibility: img_link_large → onclick.
	if ( empty( $atts['onclick'] ) && 'yes' === $atts['img_link_large'] ) {
		$atts['onclick'] = 'img_link_large';
	} elseif ( empty( $atts['onclick'] ) && 'yes' !== $atts['img_link_large'] ) {
		$atts['onclick'] = 'custom_link';
	}

	if ( 'external_link' === $atts['source'] ) {
		$atts['style']        = $atts['external_style'];
		$atts['border_color'] = $atts['external_border_color'];
	}

	$border_color = ( '' !== $atts['border_color'] ) ? ' vc_box_border_' . esc_attr( $atts['border_color'] ) : '';

	// --- Resolve image ---
	$img_html = '';
	$img_id   = 0;

	switch ( $atts['source'] ) {
		case 'media_library':
		default:
			$img_id = absint( preg_replace( '/[^\d]/', '', $atts['image'] ) );
			if ( ! $img_id ) {
				return '';
			}

			$img_size = $atts['img_size'] ? $atts['img_size'] : 'medium';
			$img_arr  = wp_get_attachment_image_src( $img_id, $img_size );
			if ( ! $img_arr ) {
				return '';
			}

			$alt      = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
			$img_html = '<img src="' . esc_url( $img_arr[0] ) . '"';
			if ( $img_arr[1] ) {
				$img_html .= ' width="' . esc_attr( $img_arr[1] ) . '"';
			}
			if ( $img_arr[2] ) {
				$img_html .= ' height="' . esc_attr( $img_arr[2] ) . '"';
			}
			$img_html .= ' alt="' . esc_attr( $alt ) . '"';
			$img_html .= ' class="vc_single_image-img"';
			$img_html .= ' loading="lazy" />';
			break;

		case 'external_link':
			$src = $atts['custom_src'] ? $atts['custom_src'] : '';
			if ( empty( $src ) ) {
				return '';
			}

			$hwstring = '';
			if ( ! empty( $atts['external_img_size'] ) && preg_match( '/(\d+)x(\d+)/', $atts['external_img_size'], $dim ) ) {
				$hwstring = ' width="' . esc_attr( $dim[1] ) . '" height="' . esc_attr( $dim[2] ) . '"';
			}

			$img_html = '<img src="' . esc_url( $src ) . '"' . $hwstring . ' class="vc_single_image-img" loading="lazy" />';
			break;

		case 'featured_image':
			$post_id = get_the_ID();
			if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
				return '';
			}
			$img_id   = get_post_thumbnail_id( $post_id );
			$img_size = $atts['img_size'] ? $atts['img_size'] : 'medium';
			$img_arr  = wp_get_attachment_image_src( $img_id, $img_size );
			if ( ! $img_arr ) {
				return '';
			}
			$alt      = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
			$img_html = '<img src="' . esc_url( $img_arr[0] ) . '" width="' . esc_attr( $img_arr[1] ) . '" height="' . esc_attr( $img_arr[2] ) . '" alt="' . esc_attr( $alt ) . '" class="vc_single_image-img" loading="lazy" />';
			break;
	}

	if ( empty( $img_html ) ) {
		return '';
	}

	// --- Resolve link ---
	$link     = $atts['link'];
	$a_attrs  = array();
	$onclick  = $atts['onclick'];

	switch ( $onclick ) {
		case 'img_link_large':
			if ( 'external_link' === $atts['source'] ) {
				$link = $atts['custom_src'];
			} elseif ( $img_id ) {
				$large = wp_get_attachment_image_src( $img_id, 'large' );
				$link  = isset( $large[0] ) ? $large[0] : '';
			}
			break;

		case 'link_image':
			// Lightbox CSS + JS → footer.
			add_action( 'wp_footer', 'sol_vc_single_image_lightbox_css', 1 );
			add_action( 'wp_footer', 'sol_vc_single_image_lightbox_js', 20 );

			$a_attrs['data-lightbox'] = 'lightbox[rel-' . get_the_ID() . '-' . wp_rand() . ']';

			if ( 'external_link' === $atts['source'] ) {
				$link = $atts['custom_src'];
			} elseif ( $img_id ) {
				$large = wp_get_attachment_image_src( $img_id, 'large' );
				$link  = isset( $large[0] ) ? $large[0] : '';
			}
			break;

		case 'custom_link':
			// $link is already set from attributes.
			break;

		default:
			$link = '';
			break;
	}

	// Backward compatibility: remove prettyphoto from el_class.
	$el_class = $atts['el_class'];
	if ( false !== strpos( $el_class, 'prettyphoto' ) ) {
		$el_class = trim( str_replace( 'prettyphoto', '', $el_class ) );
	}

	// --- Build image wrapper ---
	$wrapper_class = 'vc_single_image-wrapper ' . esc_attr( $atts['style'] ) . $border_color;

	if ( $link ) {
		$a_attrs['href']   = esc_url( $link );
		$a_attrs['target'] = $atts['img_link_target'];

		$a_attrs_str = '';
		foreach ( $a_attrs as $key => $val ) {
			$a_attrs_str .= ' ' . $key . '="' . esc_attr( $val ) . '"';
		}

		$html = '<a' . $a_attrs_str . ' class="' . esc_attr( trim( $wrapper_class ) ) . '">' . $img_html . '</a>';
	} else {
		$html = '<div class="' . esc_attr( trim( $wrapper_class ) ) . '">' . $img_html . '</div>';
	}

	// --- Caption ---
	$caption_html = '';
	if ( in_array( $atts['source'], array( 'media_library', 'featured_image' ), true ) && 'yes' === $atts['add_caption'] && $img_id ) {
		$caption_text = wp_get_attachment_caption( $img_id );
		if ( $caption_text ) {
			$caption_html = '<figcaption class="vc_figure-caption">' . wp_kses_post( $caption_text ) . '</figcaption>';
		}
	} elseif ( 'external_link' === $atts['source'] && ! empty( $atts['caption'] ) ) {
		$caption_html = '<figcaption class="vc_figure-caption">' . wp_kses_post( $atts['caption'] ) . '</figcaption>';
	}

	// --- CSS attribute ---
	$css_parsed = sol_vc_si_parse_css_attribute( $atts['css'] );

	// --- Build outer wrapper ---
	$outer_classes = array(
		'wpb_single_image',
		'wpb_content_element',
	);
	if ( ! empty( $atts['alignment'] ) ) {
		$outer_classes[] = 'vc_align_' . esc_attr( $atts['alignment'] );
	}
	if ( $css_parsed['class'] ) {
		$outer_classes[] = $css_parsed['class'];
	}
	if ( ! empty( $el_class ) ) {
		$outer_classes[] = $el_class;
	}

	$wrapper_attributes = array();
	if ( ! empty( $atts['el_id'] ) ) {
		$wrapper_attributes[] = 'id="' . esc_attr( $atts['el_id'] ) . '"';
	}

	// Inline style from css attribute.
	$style_attr = '';
	if ( $css_parsed['style'] ) {
		$style_attr = ' style="' . esc_attr( $css_parsed['style'] ) . '"';
	}

	// Title.
	$title_html = '';
	if ( ! empty( $atts['title'] ) ) {
		$title_html = '<h2 class="wpb_heading wpb_singleimage_heading">' . esc_html( $atts['title'] ) . '</h2>';
	}

	$output  = '<div ' . implode( ' ', $wrapper_attributes ) . ' class="' . esc_attr( trim( implode( ' ', array_filter( $outer_classes ) ) ) ) . '"' . $style_attr . '>';
	$output .= $title_html;
	$output .= '<figure class="wpb_wrapper vc_figure">';
	$output .= $html;
	$output .= $caption_html;
	$output .= '</figure>';
	$output .= '</div>';

	return $output;
}

/* ================================================================
 * Helper: parse WPBakery css attribute
 * ============================================================== */
function sol_vc_si_parse_css_attribute( $css_attr ) {
	$result = array( 'class' => '', 'style' => '' );
	if ( empty( $css_attr ) ) {
		return $result;
	}
	if ( preg_match( '/\.([^\{]+)\s*\{/', $css_attr, $m ) ) {
		$result['class'] = trim( $m[1] );
	}
	if ( preg_match( '/\{\s*([^\}]+)\s*\}/', $css_attr, $m ) ) {
		$result['style'] = trim( $m[1] );
	}
	return $result;
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_vc_single_image_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-single-image-base-css">
		/* ---- Container ---- */
		.wpb_single_image { margin-bottom: 35px; }
		.wpb_single_image:last-child { margin-bottom: 0; }

		/* ---- Alignment ---- */
		.wpb_single_image.vc_align_left { text-align: left; }
		.wpb_single_image.vc_align_center { text-align: center; }
		.wpb_single_image.vc_align_right { text-align: right; }

		/* ---- Figure ---- */
		.wpb_single_image .vc_figure {
			display: inline-block;
			vertical-align: top;
			margin: 0;
			padding: 0;
			max-width: 100%;
		}

		/* ---- Image wrapper ---- */
		.wpb_single_image .vc_single_image-wrapper {
			display: inline-block;
			vertical-align: top;
			max-width: 100%;
		}
		.wpb_single_image .vc_single_image-wrapper img {
			display: block;
			max-width: 100%;
			height: auto;
		}

		/* ---- Link wrapper ---- */
		.wpb_single_image a.vc_single_image-wrapper {
			text-decoration: none;
			border: none;
			box-shadow: none;
		}
		.wpb_single_image a.vc_single_image-wrapper:hover {
			opacity: 0.85;
			transition: opacity .2s ease;
		}

		/* ---- Border styles ---- */
		.vc_box_border_grey { border: 1px solid #ebebeb; }
		.vc_box_border_black { border: 1px solid #2a2a2a; }
		.vc_box_border_blue { border: 1px solid #5472d2; }
		.vc_box_border_green { border: 1px solid #6dab3c; }
		.vc_box_border_orange { border: 1px solid #f7be68; }
		.vc_box_border_pink { border: 1px solid #fe6c61; }

		/* ---- Image styles ---- */
		.vc_box_rounded .vc_single_image-img { border-radius: 4px; }
		.vc_box_rounded_less .vc_single_image-img { border-radius: 2px; }
		.vc_box_circle .vc_single_image-img { border-radius: 50%; }
		.vc_box_outline .vc_single_image-img { border: 3px solid transparent; }
		.vc_box_outline_circle .vc_single_image-img { border-radius: 50%; border: 3px solid transparent; }

		.vc_box_shadow .vc_single_image-img { box-shadow: 0 0 5px rgba(0,0,0,.1); }
		.vc_box_shadow_border .vc_single_image-img { box-shadow: 0 0 5px rgba(0,0,0,.1); border: 1px solid #ebebeb; }
		.vc_box_shadow_3d .vc_single_image-img { box-shadow: 0 3px 8px rgba(0,0,0,.2); }
		.vc_box_shadow_circle .vc_single_image-img { border-radius: 50%; box-shadow: 0 0 5px rgba(0,0,0,.1); }

		/* ---- Caption ---- */
		.wpb_single_image .vc_figure-caption {
			margin-top: 8px;
			font-size: 0.85em;
			color: #888;
			text-align: center;
		}

		/* ---- Heading ---- */
		.wpb_singleimage_heading {
			margin-bottom: 10px;
			font-size: 1em;
		}
	</style>
	<?php
}

/* ================================================================
 * Lightweight lightbox JS  –  output once per page
 *
 * Provides a simple lightbox for onclick="link_image" without
 * requiring prettyphoto or any external library.
 * ============================================================== */
function sol_vc_single_image_lightbox_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-single-image-lightbox-css">
		.sol-lightbox-overlay {
			position: fixed;
			top: 0; left: 0; right: 0; bottom: 0;
			background: rgba(0,0,0,.85);
			z-index: 999999;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			opacity: 0;
			transition: opacity .3s ease;
		}
		.sol-lightbox-overlay.sol-active { opacity: 1; }
		.sol-lightbox-overlay img {
			max-width: 90vw;
			max-height: 90vh;
			object-fit: contain;
			border-radius: 2px;
			box-shadow: 0 5px 30px rgba(0,0,0,.5);
		}
		.sol-lightbox-close {
			position: absolute;
			top: 20px;
			right: 25px;
			color: #fff;
			font-size: 36px;
			font-weight: 300;
			cursor: pointer;
			line-height: 1;
			z-index: 1000000;
			text-shadow: 0 1px 3px rgba(0,0,0,.5);
		}
		.sol-lightbox-close:hover { opacity: .7; }
	</style>
	<?php
}

/**
 * Lightweight lightbox JS  –  output once per page
 */
function sol_vc_single_image_lightbox_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		function initLightbox(){
			document.addEventListener('click', function(e){
				var link = e.target.closest('a[data-lightbox]');
				if(!link) return;
				e.preventDefault();

				var src = link.getAttribute('href');
				if(!src) return;

				var overlay = document.createElement('div');
				overlay.className = 'sol-lightbox-overlay';
				overlay.innerHTML = '<span class="sol-lightbox-close">&times;</span><img src="' + src + '" alt="" />';
				document.body.appendChild(overlay);

				// Trigger reflow then animate in.
				overlay.offsetHeight;
				overlay.classList.add('sol-active');

				// Prevent body scroll.
				document.body.style.overflow = 'hidden';

				function close(){
					overlay.classList.remove('sol-active');
					setTimeout(function(){
						overlay.remove();
						document.body.style.overflow = '';
					}, 300);
				}

				overlay.addEventListener('click', function(ev){
					if(ev.target === overlay || ev.target.classList.contains('sol-lightbox-close')){
						close();
					}
				});

				document.addEventListener('keydown', function handler(ev){
					if(ev.key === 'Escape'){
						document.removeEventListener('keydown', handler);
						close();
					}
				});
			});
		}

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', initLightbox);
		} else {
			initLightbox();
		}
	})();
	</script>
	<?php
}
