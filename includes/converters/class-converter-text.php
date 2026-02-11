<?php
/**
 * Converter for text shortcodes: vc_column_text, vc_custom_heading, mk_fancy_title, mk_ornamental_title.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Text extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_column_text',
		'vc_custom_heading',
		'mk_fancy_title',
		'mk_ornamental_title',
		'mk_title_box',
		'mk_blockquote',
		'mk_custom_list',
		'mk_highlight',
		'mk_dropcaps',
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
			case 'vc_column_text':
				return $this->convert_column_text( $node );

			case 'vc_custom_heading':
				return $this->convert_custom_heading( $node );

			case 'mk_fancy_title':
			case 'mk_ornamental_title':
			case 'mk_title_box':
				return $this->convert_mk_heading( $node );

			case 'mk_blockquote':
				return $this->convert_blockquote( $node );

			case 'mk_custom_list':
				return $this->convert_custom_list( $node );

			case 'mk_highlight':
			case 'mk_dropcaps':
				return $this->convert_inline_text( $node );

			default:
				return '';
		}
	}

	/**
	 * Convert vc_column_text to wp:freeform (Classic block).
	 *
	 * The Classic block preserves HTML content and makes it editable via TinyMCE.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_column_text( $node ) {
		$content = isset( $node['content'] ) ? $node['content'] : '';

		// If children are just text nodes, combine their content.
		if ( ! empty( $node['children'] ) ) {
			$content = '';
			foreach ( $node['children'] as $child ) {
				if ( 'text' === $child['type'] ) {
					$content .= $child['content'];
				}
			}
		}

		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		// wp:freeform is the Classic block — renders HTML as-is, editable via TinyMCE.
		$output  = '<!-- wp:freeform -->' . "\n";
		$output .= $content . "\n";
		$output .= '<!-- /wp:freeform -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_custom_heading to wp:heading.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_custom_heading( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		// Extract text.
		$text = $this->get_attr( $attrs, 'text', '' );
		if ( empty( $text ) ) {
			$text = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $text ) ) {
			return '';
		}

		// Extract heading level from font_container.
		// Format: "tag:h2|font_size:24|text_align:center|color:#333"
		$font_container = $this->get_attr( $attrs, 'font_container', '' );
		$level          = 2; // Default h2.
		$text_align     = '';

		if ( $font_container ) {
			$parts = explode( '|', $font_container );
			foreach ( $parts as $part ) {
				$pair = explode( ':', $part, 2 );
				if ( count( $pair ) === 2 ) {
					if ( 'tag' === $pair[0] ) {
						$tag_match = preg_replace( '/[^0-9]/', '', $pair[1] );
						if ( $tag_match >= 1 && $tag_match <= 6 ) {
							$level = (int) $tag_match;
						}
					}
					if ( 'text_align' === $pair[0] ) {
						$text_align = $pair[1];
					}
				}
			}
		}

		// Check for link.
		$link = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );

		$block_attrs = [];
		if ( 2 !== $level ) {
			$block_attrs['level'] = $level;
		}
		if ( $text_align ) {
			$block_attrs['textAlign'] = $text_align;
		}

		$tag  = 'h' . $level;
		$text = $this->esc_block_text( $text );

		if ( ! empty( $link_data['url'] ) ) {
			$text = '<a href="' . esc_url( $link_data['url'] ) . '">' . $text . '</a>';
		}

		$align_class = $text_align ? ' has-text-align-' . esc_attr( $text_align ) : '';

		$output  = '<!-- wp:heading' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<' . $tag . ' class="wp-block-heading' . $align_class . '">' . $text . '</' . $tag . '>' . "\n";
		$output .= '<!-- /wp:heading -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert mk_fancy_title / mk_ornamental_title / mk_title_box to wp:heading.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_mk_heading( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$content = $this->get_attr( $attrs, 'content', '' );
		if ( empty( $content ) ) {
			$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $content ) ) {
			return '';
		}

		// mk_fancy_title uses tag_name, mk_title_box uses title.
		$tag_name = $this->get_attr( $attrs, 'tag_name', 'h2' );
		$title    = $this->get_attr( $attrs, 'title', '' );

		if ( ! empty( $title ) && empty( $content ) ) {
			$content = $title;
		}

		$level = (int) preg_replace( '/[^0-9]/', '', $tag_name );
		if ( $level < 1 || $level > 6 ) {
			$level = 2;
		}

		$text_align = $this->get_attr( $attrs, 'align', '' );

		$block_attrs = [];
		if ( 2 !== $level ) {
			$block_attrs['level'] = $level;
		}
		if ( $text_align && 'left' !== $text_align ) {
			$block_attrs['textAlign'] = $text_align;
		}

		$tag         = 'h' . $level;
		$content     = $this->esc_block_text( $content );
		$align_class = ( $text_align && 'left' !== $text_align ) ? ' has-text-align-' . esc_attr( $text_align ) : '';

		$output  = '<!-- wp:heading' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<' . $tag . ' class="wp-block-heading' . $align_class . '">' . $content . '</' . $tag . '>' . "\n";
		$output .= '<!-- /wp:heading -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert mk_blockquote to wp:quote.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_blockquote( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = $this->get_attr( $attrs, 'content', '' );

		if ( empty( $content ) ) {
			$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $content ) ) {
			return '';
		}

		$content = $this->esc_block_text( $content );

		$output  = '<!-- wp:quote -->' . "\n";
		$output .= '<blockquote class="wp-block-quote"><p>' . $content . '</p></blockquote>' . "\n";
		$output .= '<!-- /wp:quote -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert mk_custom_list to wp:list.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_custom_list( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = $this->get_attr( $attrs, 'content', '' );

		if ( empty( $content ) ) {
			$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $content ) ) {
			return '';
		}

		// Content may already contain <li> tags or be line-separated.
		if ( false === strpos( $content, '<li>' ) ) {
			$items   = preg_split( '/\r?\n/', $content );
			$items   = array_filter( array_map( 'trim', $items ) );
			$content = '';
			foreach ( $items as $item ) {
				$content .= '<li>' . $this->esc_block_text( $item ) . '</li>';
			}
		}

		$output  = '<!-- wp:list -->' . "\n";
		$output .= '<ul class="wp-block-list">' . $content . '</ul>' . "\n";
		$output .= '<!-- /wp:list -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert mk_highlight / mk_dropcaps to wp:freeform with inline styling.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_inline_text( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		$content = $this->esc_block_text( $content );

		if ( 'mk_highlight' === $node['tag'] ) {
			$bg_color = $this->get_attr( $attrs, 'bg_color', '#fff000' );
			$content  = '<mark style="background-color:' . esc_attr( $bg_color ) . '">' . $content . '</mark>';
		}

		if ( 'mk_dropcaps' === $node['tag'] ) {
			$style = $this->get_attr( $attrs, 'style', 'fancy-style' );
			$content = '<span class="has-drop-cap">' . $content . '</span>';
		}

		$output  = '<!-- wp:freeform -->' . "\n";
		$output .= '<p>' . $content . '</p>' . "\n";
		$output .= '<!-- /wp:freeform -->' . "\n\n";

		return $output;
	}
}
