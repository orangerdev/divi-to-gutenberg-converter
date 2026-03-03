<?php
/**
 * Plugin Name: MK Steps (Process Builder) Standalone
 * Description: Standalone replacement for Jupiter Donut mk_steps shortcode.
 *              Renders process steps with circular SVG icons and dashed connector line.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------
 * Register shortcode
 * -------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'mk_steps' ) ) {
		add_shortcode( 'mk_steps', 'sol_render_mk_steps' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output base CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'mk_steps' ) ) {
		add_action( 'wp_head', 'sol_mk_steps_base_css' );
	}
} );

/* ---- Per-instance CSS buffer ---- */
function sol_mk_steps_collect_instance_css( $css = null ) {
	static $buffer = '';
	if ( null !== $css ) {
		$buffer .= $css . "\n";
	}
	return $buffer;
}
function sol_mk_steps_output_instance_css() {
	$css = sol_mk_steps_collect_instance_css();
	if ( ! empty( $css ) ) {
		printf( '<style id="mk-steps-instance-css">%s</style>', $css );
	}
}

/* ================================================================
 * [mk_steps]  –  Process steps with icons
 *
 * Jupiter Donut output:
 *   <div id="mk-process-{id}" class="mk-process-steps process-steps-{N} {el_class}">
 *     <ul>
 *       <li>
 *         <span class="mk-process-icon">{SVG}</span>
 *         <div class="mk-process-detail">
 *           <h3>{title}</h3>
 *           <div class="clearboth"></div>
 *           <p>{desc}</p>
 *         </div>
 *       </li>
 *       …
 *     </ul>
 *   </div>
 * ============================================================== */
function sol_render_mk_steps( $atts ) {
	$atts = shortcode_atts( array(
		'title'       => '',
		'step'        => 4,
		'hover_color' => '#41c4d5',
		'icon_1'      => '',
		'title_1'     => '',
		'desc_1'      => '',
		'url_1'       => '',
		'icon_2'      => '',
		'title_2'     => '',
		'desc_2'      => '',
		'url_2'       => '',
		'icon_3'      => '',
		'title_3'     => '',
		'desc_3'      => '',
		'url_3'       => '',
		'icon_4'      => '',
		'title_4'     => '',
		'desc_4'      => '',
		'url_4'       => '',
		'icon_5'      => '',
		'title_5'     => '',
		'desc_5'      => '',
		'url_5'       => '',
		'visibility'  => '',
		'el_class'    => '',
	), $atts, 'mk_steps' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_mk_steps_base_css', 1 );
	add_action( 'wp_footer', 'sol_mk_steps_output_instance_css', 2 );

	$step_count  = max( 2, min( 5, absint( $atts['step'] ) ) );
	$id          = wp_unique_id( 'mk-process-' );
	$hover_color = $atts['hover_color'] ? $atts['hover_color'] : '#41c4d5';

	// Per-instance hover CSS → footer.
	sol_mk_steps_collect_instance_css(
		sprintf(
			'#%s ul li:hover .mk-process-icon { background-color: %s; box-shadow: 0 0 0 6px rgba(0,0,0,0.1); }',
			esc_attr( $id ),
			esc_attr( $hover_color )
		)
	);

	ob_start();
	?>

	<div id="<?php echo esc_attr( $id ); ?>"
		 class="mk-process-steps process-steps-<?php echo $step_count; ?> <?php echo esc_attr( $atts['el_class'] ); ?>">

		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h3 class="mk-steps-heading"><span><?php echo esc_html( $atts['title'] ); ?></span></h3>
		<?php endif; ?>

		<ul>
			<?php for ( $i = 1; $i <= $step_count; $i++ ) :
				$icon_class = $atts[ 'icon_' . $i ];
				$title      = $atts[ 'title_' . $i ];
				$desc       = $atts[ 'desc_' . $i ];
				$url        = $atts[ 'url_' . $i ];

				if ( empty( $icon_class ) ) {
					continue;
				}

				// Normalise icon class: ensure mk- prefix.
				if ( strpos( $icon_class, 'mk-' ) !== 0 ) {
					$icon_class = 'mk-' . $icon_class;
				}

				$svg = sol_mk_steps_get_icon_svg( $icon_class );
			?>
				<li>
					<?php if ( ! empty( $url ) ) : ?>
						<a href="<?php echo esc_url( $url ); ?>">
					<?php endif; ?>

					<span class="mk-process-icon"><?php echo $svg; ?></span>

					<?php if ( ! empty( $url ) ) : ?>
						</a>
					<?php endif; ?>

					<div class="mk-process-detail">
						<?php if ( ! empty( $url ) ) : ?>
							<a href="<?php echo esc_url( $url ); ?>">
						<?php endif; ?>

						<h3><?php echo esc_html( $title ); ?></h3>

						<?php if ( ! empty( $url ) ) : ?>
							</a>
						<?php endif; ?>

						<div class="clearboth"></div>

						<?php if ( ! empty( $desc ) ) : ?>
							<p><?php echo wp_kses_post( $desc ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endfor; ?>

			<div class="clearboth"></div>
		</ul>
	</div>

	<?php
	return ob_get_clean();
}

/* ================================================================
 * Icon SVG resolver
 *
 * Maps icon class names to inline SVGs. Supports the Jupiter icon
 * families: icomoon (mk-moon-*), awesome-icons (mk-icon-*),
 * pe-line-icons (mk-li-*).
 *
 * Falls back to loading SVG files from the jupiter-donut plugin
 * assets when available, otherwise uses a built-in map for the
 * most commonly used icons.
 * ============================================================== */
function sol_mk_steps_get_icon_svg( $icon_class ) {
	// Determine font family and icon name from class.
	$family  = '';
	$icon_id = '';

	if ( strpos( $icon_class, 'mk-moon-' ) === 0 ) {
		$family  = 'icomoon';
		$icon_id = $icon_class;
	} elseif ( strpos( $icon_class, 'mk-icon-' ) === 0 ) {
		$family  = 'awesome-icons';
		$icon_id = $icon_class;
	} elseif ( strpos( $icon_class, 'mk-li-' ) === 0 ) {
		$family  = 'pe-line-icons';
		$icon_id = $icon_class;
	}

	// Try to load from jupiter-donut map.json + svg files.
	$donut_path = WP_PLUGIN_DIR . '/jupiter-donut/assets/icons/' . $family;
	$map_file   = $donut_path . '/map.json';

	if ( $family && file_exists( $map_file ) ) {
		$map = json_decode( file_get_contents( $map_file ), true );
		if ( is_array( $map ) && isset( $map[ $icon_class ] ) ) {
			$unicode  = $map[ $icon_class ];
			$svg_file = $donut_path . '/svg/' . $unicode . '.svg';
			if ( file_exists( $svg_file ) ) {
				return file_get_contents( $svg_file );
			}
		}
	}

	// Built-in fallback map for common icons.
	$builtin = sol_mk_steps_builtin_icons();
	if ( isset( $builtin[ $icon_class ] ) ) {
		return $builtin[ $icon_class ];
	}

	// Last resort: empty placeholder.
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><circle cx="256" cy="256" r="8" fill="currentColor"/></svg>';
}

/* ================================================================
 * Built-in SVG icon fallbacks
 * ============================================================== */
function sol_mk_steps_builtin_icons() {
	static $icons = null;
	if ( null !== $icons ) {
		return $icons;
	}

	$icons = array(
		// icomoon: bubble-star
		'mk-moon-bubble-star' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M464 0h-416c-26.4 0-48 21.6-48 48v288c0 26.4 21.6 48 48 48h80v128l153.6-128h182.4c26.4 0 48-21.6 48-48v-288c0-26.4-21.6-48-48-48zm-108 342l-100-82.098-100 82.098 36.372-117.326-96.372-64.799h120.676l39.324-125.027 39.324 125.027h120.676l-96.372 64.799 36.372 117.326z"/></svg>',

		// awesome-icons: lightbulb-o
		'mk-icon-lightbulb-o' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1792"><path d="M736 576q0 13-9.5 22.5t-22.5 9.5-22.5-9.5-9.5-22.5q0-46-54-71t-106-25q-13 0-22.5-9.5t-9.5-22.5 9.5-22.5 22.5-9.5q50 0 99.5 16t87 54 37.5 90zm160 0q0-72-34.5-134t-90-101.5-123-62-136.5-22.5-136.5 22.5-123 62-90 101.5-34.5 134q0 101 68 180 10 11 30.5 33t30.5 33q128 153 141 298h228q13-145 141-298 10-11 30.5-33t30.5-33q68-79 68-180zm128 0q0 155-103 268-45 49-74.5 87t-59.5 95.5-34 107.5q47 28 47 82 0 37-25 64 25 27 25 64 0 52-45 81 13 23 13 47 0 46-31.5 71t-77.5 25q-20 44-60 70t-87 26-87-26-60-70q-46 0-77.5-25t-31.5-71q0-24 13-47-45-29-45-81 0-37 25-64-25-27-25-64 0-54 47-82-4-50-34-107.5t-59.5-95.5-74.5-87q-103-113-103-268 0-99 44.5-184.5t117-142 164-89 186.5-32.5 186.5 32.5 164 89 117 142 44.5 184.5z"/></svg>',

		// icomoon: trophy-star
		'mk-moon-trophy-star' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M416 96v-64h-320v64h-96v64c0 53.019 42.979 96 96 96 10.038 0 19.715-1.543 28.81-4.401 23.087 33.004 58.304 56.898 99.19 65.198v99.203h-32c-35.347 0-64 28.653-64 64h256c0-35.347-28.653-64-64-64h-32v-99.203c40.886-8.3 76.103-32.193 99.19-65.198 9.095 2.858 18.772 4.401 28.81 4.401 53.021 0 96-42.981 96-96v-64h-96zm-320 122c-31.981 0-58-26.019-58-58v-32h58v32c0 20.093 3.715 39.316 10.477 57.034-3.401.623-6.899.966-10.477.966zm208.707-32.403l30.51 93.208-79.217-57.821-79.216 57.821 30.509-93.208-79.468-57.472 98.072.214 30.103-93.339 30.104 93.339 98.071-.214-79.468 57.472zm169.293-25.597c0 31.981-26.019 58-58 58-3.578 0-7.076-.343-10.477-.966 6.762-17.718 10.477-36.941 10.477-57.034v-32h58v32z"/></svg>',

		// pe-line-icons: tshirt
		'mk-li-tshirt' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M503.383 154.341l-95.604-95.52c-8.576-9.028-20.669-14.684-34.095-14.684h-40.817c-7.265 35.82-38.931 62.781-76.897 62.781s-69.632-26.96-76.898-62.781h-40.816c-13.412 0-25.49 5.641-34.066 14.638h-.007l-95.566 95.566c-5.035 5.027-5.027 13.197 0 18.224l82.554 82.553v14.147h-.031v175.055c0 12.997 10.537 23.543 23.543 23.543h282.514c13.005 0 23.543-10.546 23.543-23.543v-75.763h.031v-113.377l82.614-82.614c5.026-5.028 5.034-13.197-.002-18.225zm-82.614 56.436v-33.214h-.031v-23.559c0-8.66-7.028-15.695-15.695-15.695-8.668 0-15.695 7.035-15.695 15.695v26.486l.031.307v88.469h-.031v167.206h-266.818v-67.915h.031v-214.553c0-8.66-7.028-15.695-15.695-15.695s-15.695 7.035-15.695 15.695v56.712l-47.278-47.27 83.029-83.012c.177-.184.352-.368.537-.536l.629-.629.023.092c2.744-2.345 6.261-3.832 10.147-3.832h18.446c17.864 37.537 56.252 62.781 99.268 62.781s81.403-25.244 99.267-62.781h18.447c4.031 0 7.679 1.579 10.461 4.093l.023-.108 6.783 6.775.007.016 77.151 77.142-47.342 47.33z"/></svg>',
	);

	return $icons;
}

/* ================================================================
 * Base CSS  –  output once per page
 * ============================================================== */
function sol_mk_steps_base_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="mk-steps-base-css">
		/* ---- Container ---- */
		.mk-process-steps {
			margin-bottom: 30px;
			text-align: center;
		}
		.mk-process-steps ul {
			position: relative;
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.mk-process-steps ul:before {
			position: absolute;
			left: 80px;
			display: block;
			width: 85%;
			height: 0;
			border-top: 1px dashed #bbbbbb;
			content: "";
		}
		.mk-process-steps ul li {
			display: inline-block;
			float: left;
			margin: 0;
			padding-top: 6px;
			text-align: center;
		}
		.mk-process-steps ul li:hover svg {
			fill: #ffffff;
			animation: solToRightFromLeft 0.3s forwards;
		}
		.mk-process-steps ul li:hover .mk-process-icon {
			box-shadow: 0 0 0 6px rgba(0,0,0,0.1);
		}
		.mk-process-steps ul li h3 {
			position: relative;
			display: inline-block;
			margin: 35px auto 20px;
			font-size: 16px !important;
		}
		.mk-process-steps ul li p {
			position: relative;
			display: inline-block;
			margin-bottom: 0;
			text-align: center;
		}
		.mk-process-steps ul li a {
			text-decoration: none;
			color: inherit;
		}
		.mk-process-steps .clearboth { clear: both; }
		.mk-process-steps .mk-process-detail .clearboth { margin: 0; }

		/* ---- Icon circle ---- */
		.mk-process-icon {
			position: relative;
			display: inline-block;
			overflow: hidden;
			background-color: #ffffff;
			transition: background 0.3s, color 0.3s, box-shadow 0.3s;
			border-radius: 50%;
			box-shadow: 0 0 0 2px #bbbbbb;
		}
		.mk-process-icon svg {
			fill: #bbbbbb;
			fill: rgba(0,0,0,0.24);
			position: absolute;
			left: 50%;
			top: 50%;
			transform: translate(-50%, -50%);
		}

		/* ---- Hover animation ---- */
		@keyframes solToRightFromLeft {
			49% { transform: translateX(100%) translateY(-50%); }
			50% { opacity: 0; transform: translateX(-100%) translateY(-50%); }
			51% { opacity: 1; }
		}

		/* ---- Heading ---- */
		.mk-steps-heading {
			text-align: center;
			margin-bottom: 20px;
			font-size: 20px;
			letter-spacing: 1px;
		}

		/* ---- 2 steps ---- */
		.mk-process-steps.process-steps-2 ul:before { top: 200px; left: 100px; width: 70%; }
		.mk-process-steps.process-steps-2 li { width: 50%; }
		.mk-process-steps.process-steps-2 li h3,
		.mk-process-steps.process-steps-2 li p { padding: 0 20px; }
		.mk-process-steps.process-steps-2 .mk-process-icon { width: 400px; height: 400px; text-align: center; }
		.mk-process-steps.process-steps-2 .mk-process-icon svg { height: 128px; }

		/* ---- 3 steps ---- */
		.mk-process-steps.process-steps-3 ul:before { top: 115px; }
		.mk-process-steps.process-steps-3 li { width: 33.3%; }
		.mk-process-steps.process-steps-3 li h3,
		.mk-process-steps.process-steps-3 li p { padding: 0 20px; }
		.mk-process-steps.process-steps-3 .mk-process-icon { width: 230px; height: 230px; text-align: center; }
		.mk-process-steps.process-steps-3 .mk-process-icon svg { height: 80px; }

		/* ---- 4 steps ---- */
		.mk-process-steps.process-steps-4 ul:before { top: 90px; }
		.mk-process-steps.process-steps-4 li { width: 25%; }
		.mk-process-steps.process-steps-4 li h3,
		.mk-process-steps.process-steps-4 li p { padding: 0 20px; }
		.mk-process-steps.process-steps-4 .mk-process-icon { width: 180px; height: 180px; text-align: center; }
		.mk-process-steps.process-steps-4 .mk-process-icon svg { height: 70px; }

		/* ---- 5 steps ---- */
		.mk-process-steps.process-steps-5 ul:before { top: 70px; }
		.mk-process-steps.process-steps-5 li { width: 20%; }
		.mk-process-steps.process-steps-5 li h3,
		.mk-process-steps.process-steps-5 li p { padding: 0 15px; }
		.mk-process-steps.process-steps-5 .mk-process-icon { width: 140px; height: 140px; text-align: center; }
		.mk-process-steps.process-steps-5 .mk-process-icon svg { height: 60px; }

		/* ---- Responsive ---- */
		@media handheld, only screen and (max-width: 767px) {
			.mk-process-steps ul:before { display: none; }
			.mk-process-steps ul li {
				float: none;
				width: 100% !important;
				margin-bottom: 30px;
			}
			.mk-process-steps.process-steps-2 .mk-process-icon,
			.mk-process-steps.process-steps-3 .mk-process-icon,
			.mk-process-steps.process-steps-4 .mk-process-icon,
			.mk-process-steps.process-steps-5 .mk-process-icon {
				width: 120px;
				height: 120px;
			}
			.mk-process-steps .mk-process-icon svg { height: 48px !important; }
		}
	</style>
	<?php
}
