<?php
/**
 * Plugin Name: MK Testimonials Standalone
 * Description: Standalone replacement for Jupiter Donut mk_testimonials shortcode (avantgarde slideshow style).
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_testimonials' ) ) {
		add_shortcode( 'mk_testimonials', 'sol_render_mk_testimonials' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output base CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'mk_testimonials' ) ) {
		add_action( 'wp_head', 'sol_mk_testimonials_base_css' );
	}
} );

/* ---- Per-instance CSS buffer ---- */
function sol_mk_testimonials_collect_instance_css( $css = null ) {
	static $buffer = '';
	if ( null !== $css ) {
		$buffer .= $css . "\n";
	}
	return $buffer;
}
function sol_mk_testimonials_output_instance_css() {
	$css = sol_mk_testimonials_collect_instance_css();
	if ( ! empty( $css ) ) {
		printf( '<style id="mk-testimonials-instance-css">%s</style>', $css );
	}
}

function sol_render_mk_testimonials( $atts ) {
	$atts = shortcode_atts( array(
		'title'           => '',
		'show_as'         => 'slideshow',
		'style'           => 'avantgarde',
		'count'           => 10,
		'orderby'         => 'date',
		'order'           => 'ASC',
		'testimonials'    => '',
		'categories'      => '',
		'animation_speed' => 500,
		'slideshow_speed' => 7000,
		'skin'            => 'dark',
		'text_color'      => '#777777',
		'author_color'    => '#444444',
		'skill_color'     => '#777777',
		'font_size'       => '18',
		'font_style'      => 'italic',
		'font_weight'     => 'bold',
		'text_transform'  => 'initial',
		'letter_spacing'  => '0',
		'el_class'        => '',
	), $atts, 'mk_testimonials' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_mk_testimonials_base_css', 1 );
	add_action( 'wp_footer', 'sol_mk_testimonials_output_instance_css', 2 );

	// Build WP_Query args.
	$query_args = array(
		'post_type'      => 'testimonial',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => $atts['orderby'],
		'order'          => $atts['order'],
	);

	if ( ! empty( $atts['testimonials'] ) ) {
		$query_args['post__in'] = array_map( 'intval', explode( ',', $atts['testimonials'] ) );
	}

	if ( ! empty( $atts['categories'] ) ) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'testimonial_category',
				'field'    => 'slug',
				'terms'    => explode( ',', $atts['categories'] ),
			),
		);
	}

	$query = new WP_Query( $query_args );

	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	$id          = wp_unique_id( 'mkt_' );
	$skin_class  = esc_attr( $atts['skin'] ) . '-version';
	$el_class    = esc_attr( $atts['el_class'] );
	$anim_speed  = intval( $atts['animation_speed'] );
	$slide_speed = intval( $atts['slideshow_speed'] );
	$post_count  = $query->post_count;

	// Per-instance CSS → footer.
	ob_start();
	sol_mk_testimonials_instance_css( $id, $atts );
	sol_mk_testimonials_collect_instance_css( ob_get_clean() );

	ob_start();

	// Optional heading title.
	if ( ! empty( $atts['title'] ) ) {
		printf(
			'<h3 class="title-line-style %s"><span>%s</span></h3>',
			esc_attr( $skin_class ),
			esc_html( $atts['title'] )
		);
	}

	?>
	<div class="mk-testimonial avantgarde-style <?php echo $skin_class; ?> <?php echo $el_class; ?>"
		 id="testimonial_<?php echo esc_attr( $id ); ?>"
		 data-animation-speed="<?php echo $anim_speed; ?>"
		 data-slideshow-speed="<?php echo $slide_speed; ?>">

		<ul class="mk-flex-slides">
			<?php
			$i = 0;
			while ( $query->have_posts() ) :
				$query->the_post();
				$post_id = get_the_ID();

				$desc    = get_post_meta( $post_id, '_desc', true );
				$author  = strip_tags( get_post_meta( $post_id, '_author', true ) );
				$company = strip_tags( get_post_meta( $post_id, '_company', true ) );
				$url     = get_post_meta( $post_id, '_url', true );

				$has_thumb = has_post_thumbnail( $post_id );
				$thumb_url = '';
				if ( $has_thumb ) {
					$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'thumbnail' );
					if ( $img_src ) {
						$thumb_url = $img_src[0];
					}
				}

				$active_class = ( 0 === $i ) ? ' active' : '';
				?>
				<li class="testimonial-item<?php echo $active_class; ?>">
					<div class="mk-testimonial-content">
						<p class="mk-testimonial-quote"><?php echo wp_kses_post( $desc ); ?></p>
					</div>

					<?php if ( $has_thumb && $thumb_url ) : ?>
						<div class="mk-testimonial-image">
							<img width="95" height="95"
								 src="<?php echo esc_url( $thumb_url ); ?>"
								 alt="<?php echo esc_attr( $author ); ?>" />
						</div>
					<?php endif; ?>

					<span class="mk-testimonial-author"><?php echo esc_html( $author ); ?></span>

					<?php if ( ! empty( $company ) ) : ?>
						<?php if ( ! empty( $url ) ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
						<?php endif; ?>
						<span class="mk-testimonial-company"><?php echo esc_html( $company ); ?></span>
						<?php if ( ! empty( $url ) ) : ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</li>
				<?php
				$i++;
			endwhile;
			wp_reset_postdata();
			?>
		</ul>

		<?php if ( $post_count > 1 ) : ?>
			<nav class="mk-testimonial-nav">
				<button class="mk-testimonial-prev" aria-label="Previous testimonial">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M10.6 2.4L5 8l5.6 5.6 1.4-1.4L7.8 8l4.2-4.2z"/></svg>
				</button>
				<button class="mk-testimonial-next" aria-label="Next testimonial">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M5.4 2.4L4 3.8 8.2 8 4 12.2l1.4 1.4L11 8z"/></svg>
				</button>
			</nav>
		<?php endif; ?>

		<div class="clearboth"></div>
	</div>
	<?php

	// Slider JS → footer.
	add_action( 'wp_footer', 'sol_mk_testimonials_slider_js', 20 );

	return ob_get_clean();
}

/**
 * Output base CSS once per page load.
 */
function sol_mk_testimonials_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-testimonials-base-css">
		/* ---- Base testimonial styles ---- */
		.mk-testimonial {
			margin: 3px 3px 40px;
			position: relative;
		}
		.mk-testimonial .mk-testimonial-content {
			position: relative;
			margin-bottom: 30px;
			margin-top: 10px;
			padding: 20px 20px 0;
		}
		.mk-testimonial .mk-testimonial-quote {
			line-height: 1.8em;
		}
		.mk-testimonial .mk-testimonial-content p {
			margin-bottom: 20px;
		}
		.mk-testimonial .mk-testimonial-author {
			display: block;
			margin-bottom: 5px;
			font-weight: bold;
			font-size: 13px;
			line-height: 11px;
		}
		.mk-testimonial .mk-testimonial-company {
			font-size: 12px;
			line-height: 14px;
			opacity: .8;
		}

		/* ---- Avantgarde style ---- */
		.mk-testimonial.avantgarde-style {
			padding: 0 50px;
			text-align: center;
		}
		.mk-testimonial.avantgarde-style.mk-testimonial {
			margin: 3px 3px 0 !important;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-image {
			display: block;
			text-align: center;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-image img {
			margin: 10px auto;
			width: 95px !important;
			height: 95px !important;
			border-radius: 100%;
			object-fit: cover;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-author {
			font-size: 14px;
			text-transform: uppercase;
			letter-spacing: 1px;
			margin: 25px 0 10px 0;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-company {
			font-size: 12px;
			font-style: italic;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-content {
			margin-bottom: 0;
			padding: 0 20% 20px 20%;
		}
		.mk-testimonial.avantgarde-style .mk-testimonial-content p {
			text-align: center;
			font-size: inherit;
		}

		/* ---- Light skin overrides ---- */
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-content,
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-content p,
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-company,
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-author {
			color: #ffffff !important;
		}
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-nav button {
			border-color: #ffffff;
			color: #ffffff;
		}
		.mk-testimonial.avantgarde-style.light-version .mk-testimonial-nav svg {
			fill: #ffffff !important;
		}

		/* ---- Slider mechanics ---- */
		.mk-testimonial .mk-flex-slides {
			list-style: none;
			margin: 0;
			padding: 0;
			position: relative;
		}
		.mk-testimonial .testimonial-item {
			display: none;
			opacity: 0;
			transition: opacity 0.5s ease;
		}
		.mk-testimonial .testimonial-item.active {
			display: block;
			opacity: 1;
		}

		/* ---- Navigation (avantgarde circular buttons) ---- */
		.mk-testimonial-nav {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			pointer-events: none;
		}
		.mk-testimonial-nav button {
			pointer-events: auto;
			position: absolute;
			width: 70px;
			height: 70px;
			line-height: 76px;
			text-align: center;
			border: 1px solid #878787;
			border-radius: 50%;
			background: transparent;
			cursor: pointer;
			opacity: 0.4;
			transition: opacity 0.3s ease;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0;
		}
		.mk-testimonial-nav button:hover {
			opacity: 1;
		}
		.mk-testimonial-nav button svg {
			fill: #878787;
			height: 16px;
			width: 16px;
		}
		.mk-testimonial-nav .mk-testimonial-prev {
			top: 50%;
			transform: translateY(-50%);
			left: 0;
		}
		.mk-testimonial-nav .mk-testimonial-next {
			top: 50%;
			transform: translateY(-50%);
			right: 0;
			margin: 0;
		}

		/* ---- Heading title ---- */
		.title-line-style {
			text-align: center;
			position: relative;
			padding: 30px 0;
			letter-spacing: 3px;
			text-transform: uppercase;
		}
		.title-line-style.light-version {
			color: #ffffff !important;
		}
		.title-line-style::after {
			display: block;
			content: '';
			position: absolute;
			width: 30px;
			height: 3px;
			bottom: 0;
			left: 50%;
			margin-left: -15px;
		}
		.title-line-style.light-version::after {
			background-color: #ffffff;
		}
		.title-line-style.dark-version::after {
			background-color: #878787;
		}

		/* ---- Clearfix ---- */
		.mk-testimonial .clearboth {
			clear: both;
		}

		/* ---- Responsive ---- */
		@media handheld, only screen and (max-width: 767px) {
			.mk-testimonial.avantgarde-style {
				padding: 60px 0 0 0;
			}
			.mk-testimonial.avantgarde-style .mk-testimonial-content {
				padding: 0 !important;
			}
			.mk-testimonial-nav .mk-testimonial-next {
				top: 0;
				transform: translate(110%, -50%);
				right: 50% !important;
				left: auto;
			}
			.mk-testimonial-nav .mk-testimonial-prev {
				top: 0;
				transform: translate(-110%, -50%);
				left: 50% !important;
			}
		}
	</style>
	<?php
}

/**
 * Output per-instance dynamic CSS.
 */
function sol_mk_testimonials_instance_css( $id, $atts ) {
	$eid            = esc_attr( $id );
	$text_color     = esc_attr( $atts['text_color'] );
	$font_size      = intval( $atts['font_size'] );
	$font_style     = esc_attr( $atts['font_style'] );
	$font_weight    = esc_attr( $atts['font_weight'] );
	$letter_spacing = intval( $atts['letter_spacing'] );
	$text_transform = esc_attr( $atts['text_transform'] );
	$author_color   = esc_attr( $atts['author_color'] );
	$skill_color    = esc_attr( $atts['skill_color'] );
	$anim_speed     = intval( $atts['animation_speed'] );
	?>
	<style>
		#testimonial_<?php echo $eid; ?> .mk-testimonial-quote,
		#testimonial_<?php echo $eid; ?> .mk-testimonial-quote p {
			color: <?php echo $text_color; ?>;
		}
		#testimonial_<?php echo $eid; ?> .mk-testimonial-quote {
			font-size: <?php echo $font_size; ?>px;
			font-style: <?php echo $font_style; ?>;
			font-weight: <?php echo $font_weight; ?>;
			letter-spacing: <?php echo $letter_spacing; ?>px;
			text-transform: <?php echo $text_transform; ?>;
		}
		#testimonial_<?php echo $eid; ?> .mk-testimonial-quote * {
			font-style: <?php echo $font_style; ?> !important;
			font-weight: <?php echo $font_weight; ?> !important;
		}
		#testimonial_<?php echo $eid; ?> .mk-testimonial-author {
			color: <?php echo $author_color; ?>;
		}
		#testimonial_<?php echo $eid; ?> .mk-testimonial-company {
			color: <?php echo $skill_color; ?>;
		}
		#testimonial_<?php echo $eid; ?> .testimonial-item {
			transition-duration: <?php echo $anim_speed; ?>ms;
		}
	</style>
	<?php
}

/**
 * Output slider JavaScript once per page load.
 */
function sol_mk_testimonials_slider_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function() {
		function initMkTestimonialSliders() {
			var sliders = document.querySelectorAll('.mk-testimonial.avantgarde-style');

			sliders.forEach(function(slider) {
				var items = slider.querySelectorAll('.testimonial-item');
				if (items.length < 2) return;

				var current   = 0;
				var animSpeed = parseInt(slider.getAttribute('data-animation-speed')) || 500;
				var slideSpeed = parseInt(slider.getAttribute('data-slideshow-speed')) || 7000;
				var timer     = null;
				var animating = false;

				function showSlide(index) {
					if (animating) return;
					animating = true;

					var prev = current;
					current = ((index % items.length) + items.length) % items.length;

					if (prev === current) {
						animating = false;
						return;
					}

					// Fade out current.
					items[prev].style.opacity = '0';

					setTimeout(function() {
						items[prev].classList.remove('active');
						items[prev].style.display = 'none';

						// Fade in next.
						items[current].style.display = 'block';
						// Force reflow.
						void items[current].offsetWidth;
						items[current].classList.add('active');
						items[current].style.opacity = '1';

						animating = false;
					}, animSpeed);
				}

				function startAutoplay() {
					stopAutoplay();
					timer = setInterval(function() {
						showSlide(current + 1);
					}, slideSpeed);
				}

				function stopAutoplay() {
					if (timer) {
						clearInterval(timer);
						timer = null;
					}
				}

				// Navigation buttons.
				var prevBtn = slider.querySelector('.mk-testimonial-prev');
				var nextBtn = slider.querySelector('.mk-testimonial-next');

				if (prevBtn) {
					prevBtn.addEventListener('click', function() {
						stopAutoplay();
						showSlide(current - 1);
						startAutoplay();
					});
				}

				if (nextBtn) {
					nextBtn.addEventListener('click', function() {
						stopAutoplay();
						showSlide(current + 1);
						startAutoplay();
					});
				}

				// Pause on hover.
				slider.addEventListener('mouseenter', stopAutoplay);
				slider.addEventListener('mouseleave', startAutoplay);

				// Start.
				startAutoplay();
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initMkTestimonialSliders);
		} else {
			initMkTestimonialSliders();
		}
	})();
	</script>
	<?php
}
