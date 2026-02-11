<?php
/**
 * Converter for miscellaneous shortcodes: vc_raw_html, vc_raw_js, vc_cta, etc.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Misc extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_raw_html',
		'vc_raw_js',
		'vc_cta',
		'vc_cta_button',
		'vc_cta_button2',
		'vc_message',
		'vc_icon',
		'vc_copyright',
	];

	/**
	 * {@inheritdoc}
	 */
	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function convert( $node ) {
		switch ( $node['tag'] ) {
			case 'vc_raw_html':
				return $this->convert_raw_html( $node );

			case 'vc_raw_js':
				return $this->convert_raw_js( $node );

			case 'vc_cta':
			case 'vc_cta_button':
			case 'vc_cta_button2':
				return $this->convert_cta( $node );

			case 'vc_message':
				return $this->convert_message( $node );

			case 'vc_icon':
				return $this->convert_icon( $node );

			case 'vc_copyright':
				return $this->convert_copyright( $node );

			default:
				return '';
		}
	}

	/**
	 * Convert vc_raw_html to wp:html.
	 * WPBakery stores raw HTML content as base64-encoded.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_raw_html( $node ) {
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		// WPBakery base64-encodes raw HTML content.
		$decoded = rawurldecode( base64_decode( $content ) );
		if ( empty( $decoded ) ) {
			$decoded = $content; // Fallback if not base64-encoded.
		}

		$output  = '<!-- wp:html -->' . "\n";
		$output .= $decoded . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_raw_js to wp:html.
	 * WPBakery stores raw JS content as base64-encoded.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_raw_js( $node ) {
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		// WPBakery base64-encodes raw JS content.
		$decoded = rawurldecode( base64_decode( $content ) );
		if ( empty( $decoded ) ) {
			$decoded = $content;
		}

		// Wrap in script tag if not already wrapped.
		if ( false === stripos( $decoded, '<script' ) ) {
			$decoded = '<script>' . $decoded . '</script>';
		}

		$output  = '<!-- wp:html -->' . "\n";
		$output .= $decoded . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_cta to wp:group containing heading + paragraph + button.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_cta( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$heading      = $this->get_attr( $attrs, 'h2', '' );
		$sub_heading  = $this->get_attr( $attrs, 'h4', '' );
		$content      = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		$btn_title    = $this->get_attr( $attrs, 'btn_title', '' );
		$btn_link     = $this->get_attr( $attrs, 'btn_link', '' );

		$output  = '<!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n";
		$output .= '<div class="wp-block-group">';

		// Heading.
		if ( $heading ) {
			$output .= '<!-- wp:heading -->' . "\n";
			$output .= '<h2 class="wp-block-heading">' . $this->esc_block_text( $heading ) . '</h2>' . "\n";
			$output .= '<!-- /wp:heading -->' . "\n";
		}

		// Sub-heading.
		if ( $sub_heading ) {
			$output .= '<!-- wp:heading {"level":4} -->' . "\n";
			$output .= '<h4 class="wp-block-heading">' . $this->esc_block_text( $sub_heading ) . '</h4>' . "\n";
			$output .= '<!-- /wp:heading -->' . "\n";
		}

		// Content.
		if ( $content ) {
			$output .= '<!-- wp:freeform -->' . "\n";
			$output .= $content . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n";
		}

		// Button.
		if ( $btn_title ) {
			$link_data  = $this->parse_vc_link( $btn_link );
			$href_attr  = '';
			if ( ! empty( $link_data['url'] ) ) {
				$href_attr = ' href="' . esc_url( $link_data['url'] ) . '"';
			}

			$output .= '<!-- wp:buttons -->' . "\n";
			$output .= '<div class="wp-block-buttons">';
			$output .= '<!-- wp:button -->' . "\n";
			$output .= '<div class="wp-block-button">';
			$output .= '<a class="wp-block-button__link wp-element-button"' . $href_attr . '>' . $this->esc_block_text( $btn_title ) . '</a>';
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:button -->';
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:buttons -->' . "\n";
		}

		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:group -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_message to wp:freeform with notice-style markup.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_message( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		$type    = $this->get_attr( $attrs, 'message_box_style', 'standard' );
		$color   = $this->get_attr( $attrs, 'message_box_color', 'info' );

		if ( empty( $content ) ) {
			return '';
		}

		$output  = '<!-- wp:freeform -->' . "\n";
		$output .= '<div class="dtg-message dtg-message-' . esc_attr( $color ) . '">' . $content . '</div>' . "\n";
		$output .= '<!-- /wp:freeform -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_icon — just skip it or convert to simple HTML.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_icon( $node ) {
		// Icons are purely visual, difficult to convert.
		// Leave as empty or a placeholder.
		return '';
	}

	/**
	 * Convert vc_copyright to wp:paragraph.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_copyright( $node ) {
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		$output  = '<!-- wp:paragraph -->' . "\n";
		$output .= '<p>' . $this->esc_block_text( $content ) . '</p>' . "\n";
		$output .= '<!-- /wp:paragraph -->' . "\n\n";

		return $output;
	}
}
