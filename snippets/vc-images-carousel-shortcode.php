<?php
/**
 * Plugin Name: VC Images Carousel Standalone
 * Description: Standalone replacement for WPBakery vc_images_carousel shortcode.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! shortcode_exists( 'vc_images_carousel' ) ) {
		add_shortcode( 'vc_images_carousel', 'sol_render_vc_images_carousel' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output base CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vc_images_carousel' ) ) {
		add_action( 'wp_head', 'sol_vc_images_carousel_base_css' );
	}
} );

/* ---- Per-instance CSS buffer ---- */
function sol_vc_images_carousel_collect_instance_css( $css = null ) {
	static $buffer = '';
	if ( null !== $css ) {
		$buffer .= $css . "\n";
	}
	return $buffer;
}
function sol_vc_images_carousel_output_instance_css() {
	$css = sol_vc_images_carousel_collect_instance_css();
	if ( ! empty( $css ) ) {
		printf( '<style id="vc-images-carousel-instance-css">%s</style>', $css );
	}
}

function sol_render_vc_images_carousel( $atts ) {
	$atts = shortcode_atts( array(
		'title'                   => '',
		'images'                  => '',
		'img_size'                => 'thumbnail',
		'onclick'                 => 'link_no',
		'custom_links'            => '',
		'custom_links_target'     => '_self',
		'autoplay'                => 'no',
		'speed'                   => 5000,
		'slides_per_view'         => 1,
		'hide_pagination_control' => 'no',
		'hide_prev_next_buttons'  => 'no',
		'wrap'                    => 'no',
		'css_animation'           => '',
		'el_class'                => '',
	), $atts, 'vc_images_carousel' );

	// CSS + JS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_vc_images_carousel_base_css', 1 );
	add_action( 'wp_footer', 'sol_vc_images_carousel_output_instance_css', 2 );

	// Parse image IDs.
	$image_ids = array_filter( array_map( 'intval', explode( ',', $atts['images'] ) ) );
	if ( empty( $image_ids ) ) {
		return '';
	}

	// Decode custom links (WPBakery vc_value_from_safe format).
	$links = array();
	if ( 'custom_link' === $atts['onclick'] && ! empty( $atts['custom_links'] ) ) {
		$raw = $atts['custom_links'];
		if ( preg_match( '/^#E\-8_/', $raw ) ) {
			$raw = rawurldecode( base64_decode( preg_replace( '/^#E\-8_/', '', $raw ) ) );
		}
		$raw   = str_replace( array( '`{`', '`}`', '``' ), array( '[', ']', '"' ), $raw );
		$links = explode( ',', $raw );
	}

	$id              = wp_unique_id( 'vc_ic_' );
	$slides_per_view = max( 1, intval( $atts['slides_per_view'] ) );
	$speed           = intval( $atts['speed'] );
	$is_autoplay     = ( 'yes' === $atts['autoplay'] );
	$is_wrap         = ( 'yes' === $atts['wrap'] );
	$show_dots       = ( 'yes' !== $atts['hide_pagination_control'] );
	$show_arrows     = ( 'yes' !== $atts['hide_prev_next_buttons'] );
	$css_animation   = esc_attr( $atts['css_animation'] );
	$el_class        = esc_attr( $atts['el_class'] );
	$link_target     = esc_attr( $atts['custom_links_target'] );
	$image_count     = count( $image_ids );

	ob_start();

	// Per-instance CSS → footer.
	ob_start();
	sol_vc_images_carousel_instance_css( $id, $atts );
	sol_vc_images_carousel_collect_instance_css( ob_get_clean() );

	// Optional heading title.
	if ( ! empty( $atts['title'] ) ) {
		printf( '<h2 class="wpb_heading">%s</h2>', esc_html( $atts['title'] ) );
	}

	?>
	<div class="wpb_images_carousel <?php echo $css_animation; ?> <?php echo $el_class; ?>"
		 id="vc_ic_<?php echo esc_attr( $id ); ?>">
		<div class="vc-carousel-wrap"
			 data-autoplay="<?php echo $is_autoplay ? 'true' : 'false'; ?>"
			 data-speed="<?php echo $speed; ?>"
			 data-wrap="<?php echo $is_wrap ? 'true' : 'false'; ?>"
			 data-per-view="<?php echo $slides_per_view; ?>">

			<div class="vc-carousel-viewport">
				<div class="vc-carousel-track">
					<?php
					$i = 0;
					foreach ( $image_ids as $image_id ) :
						$img = wp_get_attachment_image( $image_id, $atts['img_size'], false, array( 'class' => 'vc-carousel-img' ) );
						if ( ! $img ) {
							$i++;
							continue;
						}

						$link_open  = '';
						$link_close = '';

						if ( 'custom_link' === $atts['onclick'] && isset( $links[ $i ] ) && '' !== trim( $links[ $i ] ) ) {
							$link_open  = '<a href="' . esc_url( trim( $links[ $i ] ) ) . '" target="' . $link_target . '">';
							$link_close = '</a>';
						} elseif ( 'link_image' === $atts['onclick'] ) {
							$full_url   = wp_get_attachment_url( $image_id );
							$link_open  = '<a href="' . esc_url( $full_url ) . '">';
							$link_close = '</a>';
						}
						?>
						<div class="vc-carousel-slide">
							<?php echo $link_open . $img . $link_close; ?>
						</div>
						<?php
						$i++;
					endforeach;
					?>
				</div>
			</div>

			<?php if ( $show_dots && $image_count > 1 ) : ?>
				<ol class="vc-carousel-dots">
					<?php for ( $d = 0; $d < $image_count; $d++ ) : ?>
						<li data-slide-to="<?php echo $d; ?>"<?php echo 0 === $d ? ' class="active"' : ''; ?>></li>
					<?php endfor; ?>
				</ol>
			<?php endif; ?>

			<?php if ( $show_arrows && $image_count > 1 ) : ?>
				<button class="vc-carousel-prev" aria-label="Previous slide">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M13.25 3L6.75 10l6.5 7 1.5-1.6L9.75 10l5-5.4z"/></svg>
				</button>
				<button class="vc-carousel-next" aria-label="Next slide">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M6.75 3l6.5 7-6.5 7-1.5-1.6 5-5.4-5-5.4z"/></svg>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php

	// Slider JS → footer.
	add_action( 'wp_footer', 'sol_vc_images_carousel_slider_js', 20 );

	return ob_get_clean();
}

/**
 * Output base CSS once per page load.
 */
function sol_vc_images_carousel_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="vc-images-carousel-base-css">
		/* ---- Container ---- */
		.wpb_images_carousel {
			position: relative;
			margin-bottom: 35px;
		}
		.wpb_images_carousel .wpb_heading {
			margin-bottom: 15px;
		}

		/* ---- Carousel wrap ---- */
		.vc-carousel-wrap {
			position: relative;
			overflow: hidden;
		}

		/* ---- Viewport & track ---- */
		.vc-carousel-viewport {
			overflow: hidden;
		}
		.vc-carousel-track {
			display: flex;
			transition: transform 0.6s ease;
			will-change: transform;
		}
		.vc-carousel-slide {
			flex: 0 0 100%;
			max-width: 100%;
			box-sizing: border-box;
		}
		.vc-carousel-slide a {
			display: block;
		}
		.vc-carousel-slide img {
			display: block;
			width: 100%;
			height: auto;
		}

		/* ---- Dots ---- */
		.vc-carousel-dots {
			list-style: none;
			display: flex;
			justify-content: center;
			gap: 6px;
			margin: 12px 0 0;
			padding: 0;
		}
		.vc-carousel-dots li {
			width: 10px;
			height: 10px;
			border-radius: 50%;
			background: #ccc;
			border: 1px solid #ccc;
			cursor: pointer;
			transition: background 0.3s ease;
		}
		.vc-carousel-dots li.active {
			background: transparent;
			border-color: #888;
		}

		/* ---- Arrows ---- */
		.vc-carousel-prev,
		.vc-carousel-next {
			position: absolute;
			top: 50%;
			transform: translateY(-50%);
			z-index: 2;
			background: rgba(255, 255, 255, 0.8);
			border: none;
			border-radius: 50%;
			width: 40px;
			height: 40px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			opacity: 0.6;
			transition: opacity 0.3s ease;
			padding: 0;
			color: #333;
		}
		.vc-carousel-prev:hover,
		.vc-carousel-next:hover {
			opacity: 1;
		}
		.vc-carousel-prev {
			left: 10px;
		}
		.vc-carousel-next {
			right: 10px;
		}

		/* ---- CSS Animation ---- */
		@keyframes vcFadeIn {
			from { opacity: 0; }
			to   { opacity: 1; }
		}
		.wpb_images_carousel.fadeIn {
			animation: vcFadeIn 1s ease;
		}

		/* ---- Responsive ---- */
		@media (max-width: 767px) {
			.vc-carousel-slide {
				flex: 0 0 100% !important;
				max-width: 100% !important;
			}
			.vc-carousel-prev,
			.vc-carousel-next {
				width: 32px;
				height: 32px;
			}
		}
	</style>
	<?php
}

/**
 * Output per-instance dynamic CSS.
 */
function sol_vc_images_carousel_instance_css( $id, $atts ) {
	$eid             = esc_attr( $id );
	$slides_per_view = max( 1, intval( $atts['slides_per_view'] ) );

	if ( $slides_per_view <= 1 ) {
		return;
	}

	$slide_width = 100 / $slides_per_view;
	?>
	<style>
		#vc_ic_<?php echo $eid; ?> .vc-carousel-slide {
			flex: 0 0 <?php echo $slide_width; ?>%;
			max-width: <?php echo $slide_width; ?>%;
			padding: 0 5px;
		}
	</style>
	<?php
}

/**
 * Output slider JavaScript once per page load.
 */
function sol_vc_images_carousel_slider_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function() {
		function initVcCarousels() {
			var carousels = document.querySelectorAll('.wpb_images_carousel');

			carousels.forEach(function(el) {
				var wrap = el.querySelector('.vc-carousel-wrap');
				if (!wrap) return;

				var track    = el.querySelector('.vc-carousel-track');
				var slides   = el.querySelectorAll('.vc-carousel-slide');
				var dots     = el.querySelectorAll('.vc-carousel-dots li');
				var prevBtn  = el.querySelector('.vc-carousel-prev');
				var nextBtn  = el.querySelector('.vc-carousel-next');

				if (!track || slides.length < 2) return;

				var autoplay = wrap.getAttribute('data-autoplay') === 'true';
				var speed    = parseInt(wrap.getAttribute('data-speed')) || 5000;
				var wrapMode = wrap.getAttribute('data-wrap') === 'true';
				var perView  = parseInt(wrap.getAttribute('data-per-view')) || 1;
				var total    = slides.length;
				var current  = 0;
				var timer    = null;
				var touchStartX = 0;
				var touchEndX   = 0;

				function getPerView() {
					return window.innerWidth <= 767 ? 1 : perView;
				}

				function maxIndex() {
					return Math.max(0, total - getPerView());
				}

				function goTo(idx) {
					var max = maxIndex();
					if (wrapMode) {
						if (idx > max) idx = 0;
						if (idx < 0) idx = max;
					} else {
						idx = Math.max(0, Math.min(idx, max));
					}
					current = idx;

					var pv = getPerView();
					var offset = current * (100 / pv);
					track.style.transform = 'translateX(-' + offset + '%)';

					dots.forEach(function(dot, i) {
						dot.classList.toggle('active', i === current);
					});
				}

				function startAuto() {
					stopAuto();
					if (autoplay && speed > 0) {
						timer = setInterval(function() {
							goTo(current + 1);
						}, speed);
					}
				}

				function stopAuto() {
					if (timer) {
						clearInterval(timer);
						timer = null;
					}
				}

				// Arrows.
				if (prevBtn) {
					prevBtn.addEventListener('click', function() {
						stopAuto();
						goTo(current - 1);
						startAuto();
					});
				}
				if (nextBtn) {
					nextBtn.addEventListener('click', function() {
						stopAuto();
						goTo(current + 1);
						startAuto();
					});
				}

				// Dots.
				dots.forEach(function(dot) {
					dot.addEventListener('click', function() {
						stopAuto();
						goTo(parseInt(dot.getAttribute('data-slide-to')) || 0);
						startAuto();
					});
				});

				// Pause on hover.
				el.addEventListener('mouseenter', stopAuto);
				el.addEventListener('mouseleave', startAuto);

				// Touch / swipe.
				wrap.addEventListener('touchstart', function(e) {
					touchStartX = e.changedTouches[0].screenX;
				}, { passive: true });

				wrap.addEventListener('touchend', function(e) {
					touchEndX = e.changedTouches[0].screenX;
					var diff = touchStartX - touchEndX;
					if (Math.abs(diff) > 50) {
						stopAuto();
						goTo(current + (diff > 0 ? 1 : -1));
						startAuto();
					}
				}, { passive: true });

				// Responsive recalc.
				window.addEventListener('resize', function() {
					goTo(current);
				});

				// Init.
				goTo(0);
				startAuto();
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initVcCarousels);
		} else {
			initVcCarousels();
		}
	})();
	</script>
	<?php
}
