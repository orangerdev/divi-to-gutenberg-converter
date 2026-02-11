<?php
/**
 * Gutenberg Builder: traverses AST and assembles Gutenberg block markup.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Gutenberg_Builder {

	/**
	 * Registered converters.
	 *
	 * @var DTG_Converter_Base[]
	 */
	private $converters = [];

	/**
	 * Shortcode parser instance.
	 *
	 * @var DTG_Shortcode_Parser
	 */
	private $parser;

	/**
	 * Constructor — register all converters.
	 */
	public function __construct() {
		$this->parser = new DTG_Shortcode_Parser();

		$this->register_converter( new DTG_Converter_Layout() );
		$this->register_converter( new DTG_Converter_Text() );
		$this->register_converter( new DTG_Converter_Media() );
		$this->register_converter( new DTG_Converter_Button() );
		$this->register_converter( new DTG_Converter_Separator() );
		$this->register_converter( new DTG_Converter_Misc() );
	}

	/**
	 * Register a converter and inject builder reference.
	 *
	 * @param DTG_Converter_Base $converter Converter instance.
	 */
	private function register_converter( DTG_Converter_Base $converter ) {
		$converter->set_builder( $this );
		$this->converters[] = $converter;
	}

	/**
	 * Convert post content from shortcodes to Gutenberg blocks.
	 *
	 * @param string $content Raw post content with shortcodes.
	 * @return string Gutenberg block markup.
	 */
	public function convert( $content ) {
		$nodes = $this->parser->parse( $content );

		if ( empty( $nodes ) ) {
			return $content;
		}

		return $this->build_from_nodes( $nodes );
	}

	/**
	 * Build Gutenberg markup from an array of AST nodes.
	 *
	 * Called by converters for recursive child processing.
	 *
	 * @param array $nodes Array of AST nodes.
	 * @return string Combined Gutenberg block markup.
	 */
	public function build_from_nodes( $nodes ) {
		$output = '';

		foreach ( $nodes as $node ) {
			$output .= $this->convert_node( $node );
		}

		return $output;
	}

	/**
	 * Convert a single AST node to Gutenberg markup.
	 *
	 * @param array $node AST node.
	 * @return string Gutenberg block markup.
	 */
	private function convert_node( $node ) {
		// Text nodes — wrap in paragraph or pass through.
		if ( 'text' === $node['type'] ) {
			return $this->convert_text_node( $node );
		}

		// Shortcode nodes — try Tier 1 converters.
		$tag = $node['tag'];

		foreach ( $this->converters as $converter ) {
			if ( $converter->can_convert( $tag ) ) {
				return $converter->convert( $node );
			}
		}

		// Tier 2 — leave shortcode as-is, wrapped in wp:html block.
		return $this->wrap_as_shortcode_block( $node );
	}

	/**
	 * Convert a text node.
	 *
	 * If the text contains HTML structure, wrap in wp:freeform.
	 * If it's plain text, wrap in wp:paragraph.
	 *
	 * @param array $node Text node.
	 * @return string
	 */
	private function convert_text_node( $node ) {
		$content = trim( $node['content'] );

		if ( '' === $content ) {
			return '';
		}

		// If content has HTML block-level tags, use freeform (Classic block).
		if ( preg_match( '/<(?:div|table|ul|ol|h[1-6]|blockquote|figure|form|section|article|header|footer|nav|aside|p)\b/i', $content ) ) {
			$output  = '<!-- wp:freeform -->' . "\n";
			$output .= $content . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n\n";
			return $output;
		}

		// Simple inline text — wrap in paragraph.
		$output  = '<!-- wp:paragraph -->' . "\n";
		$output .= '<p>' . wp_kses_post( $content ) . '</p>' . "\n";
		$output .= '<!-- /wp:paragraph -->' . "\n\n";

		return $output;
	}

	/**
	 * Wrap a Tier 2 shortcode node — leave the shortcode text as-is inside wp:html.
	 *
	 * @param array $node Shortcode AST node.
	 * @return string
	 */
	private function wrap_as_shortcode_block( $node ) {
		$raw = isset( $node['raw'] ) ? $node['raw'] : '';

		if ( empty( $raw ) ) {
			// Reconstruct from node data.
			$raw = $this->reconstruct_shortcode( $node );
		}

		if ( empty( $raw ) ) {
			return '';
		}

		$output  = '<!-- wp:html -->' . "\n";
		$output .= $raw . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	/**
	 * Reconstruct shortcode string from node data.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function reconstruct_shortcode( $node ) {
		$tag   = $node['tag'];
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$shortcode = '[' . $tag;

		foreach ( $attrs as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$shortcode .= ' ' . $value;
			} else {
				$shortcode .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
		}

		$shortcode .= ']';

		// Add inner content.
		$content = isset( $node['content'] ) ? $node['content'] : '';
		if ( '' !== $content ) {
			$shortcode .= $content . '[/' . $tag . ']';
		}

		return $shortcode;
	}

	/**
	 * Get the parser instance.
	 *
	 * @return DTG_Shortcode_Parser
	 */
	public function get_parser() {
		return $this->parser;
	}

	/**
	 * Analyze content and return a report of shortcodes found.
	 *
	 * @param string $content Post content.
	 * @return array Associative array: tag => count.
	 */
	public function analyze_shortcodes( $content ) {
		$nodes   = $this->parser->parse( $content );
		$results = [];

		$this->count_shortcodes( $nodes, $results );

		arsort( $results );
		return $results;
	}

	/**
	 * Recursively count shortcodes in AST nodes.
	 *
	 * @param array $nodes   AST nodes.
	 * @param array &$counts Running count by tag.
	 */
	private function count_shortcodes( $nodes, &$counts ) {
		foreach ( $nodes as $node ) {
			if ( 'shortcode' === $node['type'] ) {
				$tag = $node['tag'];
				if ( ! isset( $counts[ $tag ] ) ) {
					$counts[ $tag ] = 0;
				}
				$counts[ $tag ]++;

				if ( ! empty( $node['children'] ) ) {
					$this->count_shortcodes( $node['children'], $counts );
				}
			}
		}
	}
}
