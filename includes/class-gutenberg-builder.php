<?php
/**
 * Gutenberg Builder: traverses AST and assembles Gutenberg block markup.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Gutenberg_Builder {

	/** @var DTG_Converter_Base[] */
	private $converters = [];

	/** @var DTG_Shortcode_Parser */
	private $parser;

	/** @var array Collected CSS rules during conversion. */
	private $css_rules = [];

	/** @var array Collected Google Font families. */
	private $google_fonts = [];

	/** @var int Element counter for unique class names. */
	private $element_counter = 0;

	/** @var int Current post ID. */
	private $post_id = 0;

	public function __construct() {
		$this->parser = new DTG_Shortcode_Parser();

		$this->register_converter( new DTG_Converter_Layout() );
		$this->register_converter( new DTG_Converter_Text() );
		$this->register_converter( new DTG_Converter_Media() );
		$this->register_converter( new DTG_Converter_Button() );
		$this->register_converter( new DTG_Converter_Separator() );
		$this->register_converter( new DTG_Converter_Misc() );
	}

	private function register_converter( DTG_Converter_Base $converter ) {
		$converter->set_builder( $this );
		$this->converters[] = $converter;
	}

	/**
	 * Set current post ID and reset CSS state.
	 */
	public function set_post_id( $post_id ) {
		$this->post_id         = (int) $post_id;
		$this->css_rules       = [];
		$this->google_fonts    = [];
		$this->element_counter = 0;
	}

	/**
	 * Get next unique CSS class name.
	 */
	public function next_class() {
		$this->element_counter++;
		return 'dtg-' . $this->post_id . '-' . $this->element_counter;
	}

	/**
	 * Add a CSS rule to the collection.
	 */
	public function add_css( $selector, $declarations ) {
		if ( ! empty( $declarations ) ) {
			$this->css_rules[] = [
				'selector'     => $selector,
				'declarations' => $declarations,
			];
		}
	}

	/**
	 * Register a Google Font family.
	 */
	public function add_google_font( $font_family, $weight = '400', $style = 'normal' ) {
		$font_family = trim( $font_family );
		if ( empty( $font_family ) ) {
			return;
		}

		if ( ! isset( $this->google_fonts[ $font_family ] ) ) {
			$this->google_fonts[ $font_family ] = [];
		}

		$variant = $weight;
		if ( 'italic' === $style ) {
			$variant .= 'italic';
		}

		if ( ! in_array( $variant, $this->google_fonts[ $font_family ], true ) ) {
			$this->google_fonts[ $font_family ][] = $variant;
		}
	}

	/**
	 * Get all collected CSS as string.
	 */
	public function get_collected_css() {
		if ( empty( $this->css_rules ) ) {
			return '';
		}

		$css = '/* Post ID: ' . $this->post_id . " */\n";
		foreach ( $this->css_rules as $rule ) {
			$css .= $rule['selector'] . " {\n";
			foreach ( $rule['declarations'] as $prop => $value ) {
				$css .= '  ' . $prop . ': ' . $value . ";\n";
			}
			$css .= "}\n";
		}

		return $css;
	}

	/**
	 * Get Google Fonts @import CSS.
	 */
	public function get_google_fonts_css() {
		if ( empty( $this->google_fonts ) ) {
			return '';
		}

		$families = [];
		foreach ( $this->google_fonts as $family => $variants ) {
			$families[] = str_replace( ' ', '+', $family ) . ':' . implode( ',', $variants );
		}

		return '@import url("https://fonts.googleapis.com/css?family=' . implode( '|', $families ) . '&display=swap");' . "\n";
	}

	/**
	 * Get raw Google Fonts data.
	 */
	public function get_google_fonts() {
		return $this->google_fonts;
	}

	/**
	 * Convert post content from shortcodes to Gutenberg blocks.
	 */
	public function convert( $content ) {
		$nodes = $this->parser->parse( $content );

		if ( empty( $nodes ) ) {
			return $content;
		}

		return $this->build_from_nodes( $nodes );
	}

	/**
	 * Build Gutenberg markup from AST nodes.
	 */
	public function build_from_nodes( $nodes ) {
		$output = '';

		foreach ( $nodes as $node ) {
			$output .= $this->convert_node( $node );
		}

		return $output;
	}

	private function convert_node( $node ) {
		if ( 'text' === $node['type'] ) {
			return $this->convert_text_node( $node );
		}

		$tag = $node['tag'];

		foreach ( $this->converters as $converter ) {
			if ( $converter->can_convert( $tag ) ) {
				return $converter->convert( $node );
			}
		}

		return $this->wrap_as_shortcode_block( $node );
	}

	private function convert_text_node( $node ) {
		$content = trim( $node['content'] );

		if ( '' === $content ) {
			return '';
		}

		if ( preg_match( '/<(?:div|table|ul|ol|h[1-6]|blockquote|figure|form|section|article|header|footer|nav|aside|p)\b/i', $content ) ) {
			$output  = '<!-- wp:freeform -->' . "\n";
			$output .= $content . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n\n";
			return $output;
		}

		$output  = '<!-- wp:paragraph -->' . "\n";
		$output .= '<p>' . wp_kses_post( $content ) . '</p>' . "\n";
		$output .= '<!-- /wp:paragraph -->' . "\n\n";

		return $output;
	}

	private function wrap_as_shortcode_block( $node ) {
		$raw = isset( $node['raw'] ) ? $node['raw'] : '';

		if ( empty( $raw ) ) {
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

		$content = isset( $node['content'] ) ? $node['content'] : '';
		if ( '' !== $content ) {
			$shortcode .= $content . '[/' . $tag . ']';
		}

		return $shortcode;
	}

	public function get_parser() {
		return $this->parser;
	}

	public function analyze_shortcodes( $content ) {
		$nodes   = $this->parser->parse( $content );
		$results = [];

		$this->count_shortcodes( $nodes, $results );

		arsort( $results );
		return $results;
	}

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
