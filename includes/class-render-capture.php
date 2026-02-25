<?php
/**
 * Server-side render capture engine.
 *
 * Uses do_shortcode() while WPBakery/JupiterDonut are active to capture
 * the exact rendered HTML output for complex shortcode elements.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Render_Capture {

	/**
	 * CSS extractor instance for collecting class names.
	 *
	 * @var DTG_CSS_Extractor|null
	 */
	private $css_extractor;

	/**
	 * All CSS class names found across captured HTML blocks.
	 *
	 * @var array
	 */
	private $all_captured_classes = [];

	/**
	 * Set the CSS extractor instance.
	 *
	 * @param DTG_CSS_Extractor $extractor CSS extractor.
	 */
	public function set_css_extractor( $extractor ) {
		$this->css_extractor = $extractor;
	}

	/**
	 * Capture the rendered HTML for an AST node.
	 *
	 * @param array  $node           AST node from DTG_Shortcode_Parser.
	 * @param string $classification 'capture' or 'dynamic'.
	 * @return string Rendered HTML wrapped in <!-- wp:html --> block.
	 */
	public function capture_node( $node, $classification = 'capture' ) {
		$shortcode_string = $this->get_shortcode_string( $node );

		if ( empty( $shortcode_string ) ) {
			return '';
		}

		// Render via WordPress shortcode engine.
		$rendered_html = do_shortcode( $shortcode_string );

		if ( empty( trim( $rendered_html ) ) ) {
			return '';
		}

		// Collect CSS class names from rendered HTML.
		$classes = $this->extract_css_classes( $rendered_html );
		$this->all_captured_classes = array_unique(
			array_merge( $this->all_captured_classes, $classes )
		);

		// Notify CSS extractor.
		if ( $this->css_extractor ) {
			$this->css_extractor->register_captured_classes( $classes );
		}

		// Post-process for dynamic elements.
		if ( 'dynamic' === $classification ) {
			$rendered_html = $this->post_process_dynamic( $rendered_html, $node );
		}

		// Clean render artifacts.
		$rendered_html = $this->clean_rendered_html( $rendered_html );

		return $this->wrap_as_html_block( $rendered_html );
	}

	/**
	 * Get the full shortcode string from an AST node.
	 *
	 * Prefers the `raw` field (complete original shortcode including nested content).
	 * Falls back to reconstruction from node data.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function get_shortcode_string( $node ) {
		// The parser stores full match in 'raw' — includes all nested content.
		if ( ! empty( $node['raw'] ) ) {
			return $node['raw'];
		}

		return $this->reconstruct_shortcode( $node );
	}

	/**
	 * Reconstruct a shortcode string from AST node data.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function reconstruct_shortcode( $node ) {
		$tag   = $node['tag'] ?? '';
		$attrs = $node['attrs'] ?? [];

		if ( empty( $tag ) ) {
			return '';
		}

		$shortcode = '[' . $tag;

		foreach ( $attrs as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$shortcode .= ' ' . $value;
			} else {
				$shortcode .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
		}

		$shortcode .= ']';

		$content = $node['content'] ?? '';
		if ( '' !== $content ) {
			$shortcode .= $content . '[/' . $tag . ']';
		}

		return $shortcode;
	}

	/**
	 * Post-process rendered HTML for dynamic elements.
	 *
	 * Ensures WooCommerce data attributes and popup trigger classes are preserved.
	 *
	 * @param string $html Rendered HTML.
	 * @param array  $node AST node.
	 * @return string
	 */
	private function post_process_dynamic( $html, $node ) {
		$tag   = $node['tag'] ?? '';
		$attrs = $node['attrs'] ?? [];

		// mk_button with product_id: ensure WooCommerce AJAX attributes exist.
		if ( ( 'mk_button' === $tag || 'mk_button_gradient' === $tag ) ) {
			$product_id = $attrs['product_id'] ?? '';
			if ( $product_id && false === strpos( $html, 'data-product_id' ) ) {
				// Inject WooCommerce data attributes if missing from render.
				$html = preg_replace(
					'/class="([^"]*mk-button[^"]*)"/',
					'data-quantity="1" data-product_id="' . esc_attr( $product_id ) . '" class="add_to_cart_button ajax_add_to_cart $1"',
					$html,
					1
				);
			}
		}

		return $html;
	}

	/**
	 * Clean WPBakery/JupiterDonut render artifacts from captured HTML.
	 *
	 * @param string $html Raw rendered HTML.
	 * @return string Cleaned HTML.
	 */
	private function clean_rendered_html( $html ) {
		// Remove WPBakery full-width clearfix divs.
		$html = preg_replace(
			'/<div\s+class="vc_row-full-width\s+vc_clearfix"\s*><\/div>/',
			'',
			$html
		);

		// Remove data-vc-* attributes (Visual Composer internals).
		$html = preg_replace( '/\s+data-vc-[a-z_-]+="[^"]*"/', '', $html );

		// Remove empty data-vc attributes.
		$html = preg_replace( '/\s+data-vc-[a-z_-]+(?=[>\s])/', '', $html );

		// Remove vc_row-no-padding placeholder divs.
		$html = preg_replace(
			'/<div\s+class="vc_row-no-padding"\s*><\/div>/',
			'',
			$html
		);

		// Normalize multiple whitespace/newlines.
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		return trim( $html );
	}

	/**
	 * Wrap rendered HTML in a Gutenberg HTML block.
	 *
	 * @param string $html Rendered HTML.
	 * @return string Gutenberg block markup.
	 */
	private function wrap_as_html_block( $html ) {
		$output  = '<!-- wp:html -->' . "\n";
		$output .= $html . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	/**
	 * Extract all CSS class names from an HTML string.
	 *
	 * @param string $html HTML content.
	 * @return array Unique class names.
	 */
	private function extract_css_classes( $html ) {
		$classes = [];

		if ( preg_match_all( '/class="([^"]*)"/', $html, $matches ) ) {
			foreach ( $matches[1] as $class_string ) {
				$parts   = preg_split( '/\s+/', $class_string );
				$classes = array_merge( $classes, $parts );
			}
		}

		// Also check single-quoted class attributes.
		if ( preg_match_all( "/class='([^']*)'/", $html, $matches ) ) {
			foreach ( $matches[1] as $class_string ) {
				$parts   = preg_split( '/\s+/', $class_string );
				$classes = array_merge( $classes, $parts );
			}
		}

		return array_unique( array_filter( $classes ) );
	}

	/**
	 * Setup frontend rendering context for AJAX execution.
	 *
	 * Some shortcodes depend on being in a frontend request context
	 * with $post global set and specific hooks fired.
	 *
	 * @param int $post_id Post ID to set up context for.
	 */
	public function setup_frontend_context( $post_id ) {
		global $post;

		$post = get_post( $post_id );
		if ( $post ) {
			setup_postdata( $post );
		}

		// Ensure WPBakery shortcodes are registered.
		if ( class_exists( 'WPBMap' ) ) {
			WPBMap::addAllMappedShortcodes();
		}

		// Trigger Jupiter Donut shortcode registration if not already done.
		if ( class_exists( 'Jupiter_Donut' ) && ! did_action( 'vc_mapper_init_before' ) ) {
			do_action( 'vc_mapper_init_before' );
		}
	}

	/**
	 * Teardown the frontend rendering context.
	 */
	public function teardown_frontend_context() {
		wp_reset_postdata();
	}

	/**
	 * Get all CSS class names found across all captured blocks.
	 *
	 * @return array
	 */
	public function get_all_captured_classes() {
		return $this->all_captured_classes;
	}

	/**
	 * Reset the captured classes (e.g., between posts).
	 */
	public function reset() {
		$this->all_captured_classes = [];
	}
}
