<?php
/**
 * Converter for text shortcodes.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Text extends DTG_Converter_Base {

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

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

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

	private function convert_column_text( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = isset( $node['content'] ) ? $node['content'] : '';

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

		// Parse CSS and alignment.
		$css_attr  = $this->get_attr( $attrs, 'css', '' );
		$vc_css    = $this->parse_vc_css( $css_attr );
		$align     = $this->get_attr( $attrs, 'align', '' );
		$el_class  = $this->get_attr( $attrs, 'el_class', '' );

		$css_declarations = [];
		if ( ! empty( $vc_css ) ) {
			$css_declarations = array_merge( $css_declarations, $vc_css );
		}
		if ( $align ) {
			$css_declarations['text-align'] = $align;
		}

		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );
		}

		$class_list = $css_class;
		if ( $el_class ) {
			$class_list = trim( $class_list . ' ' . $el_class );
		}

		if ( $class_list ) {
			$output  = '<!-- wp:freeform -->' . "\n";
			$output .= '<div class="' . esc_attr( $class_list ) . '">' . "\n" . $content . "\n" . '</div>' . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n\n";
		} else {
			$output  = '<!-- wp:freeform -->' . "\n";
			$output .= $content . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n\n";
		}

		return $output;
	}

	private function convert_custom_heading( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$text = $this->get_attr( $attrs, 'text', '' );
		if ( empty( $text ) ) {
			$text = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $text ) ) {
			return '';
		}

		// Parse font_container.
		$font_container = $this->get_attr( $attrs, 'font_container', '' );
		$fc             = $this->parse_font_container( $font_container );

		$level      = 2;
		$tag_name   = $fc['tag'];
		$tag_match  = preg_replace( '/[^0-9]/', '', $tag_name );
		if ( $tag_match >= 1 && $tag_match <= 6 ) {
			$level = (int) $tag_match;
		}

		// Determine if this is a heading or paragraph tag.
		$is_paragraph = ( 'p' === $tag_name || 'div' === $tag_name || 'span' === $tag_name );

		$text_align = $fc['text_align'];

		// CSS declarations.
		$css_declarations = [];

		if ( $fc['font_size'] ) {
			$css_declarations['font-size'] = $this->ensure_px( $fc['font_size'] );
		}

		if ( $fc['color'] ) {
			$css_declarations['color'] = $fc['color'];
		}

		if ( $fc['line_height'] ) {
			$css_declarations['line-height'] = $fc['line_height'];
		}

		// Parse google_fonts.
		$google_fonts_attr = $this->get_attr( $attrs, 'google_fonts', '' );
		$gf                = $this->parse_google_fonts( $google_fonts_attr );

		if ( $gf['family'] ) {
			$css_declarations['font-family'] = '"' . $gf['family'] . '", sans-serif';
			$css_declarations['font-weight'] = $gf['weight'];
			if ( 'normal' !== $gf['style'] ) {
				$css_declarations['font-style'] = $gf['style'];
			}
			$this->add_google_font( $gf['family'], $gf['weight'], $gf['style'] );
		}

		// Parse vc_custom_heading css attribute.
		$css_attr = $this->get_attr( $attrs, 'css', '' );
		$vc_css   = $this->parse_vc_css( $css_attr );
		if ( ! empty( $vc_css ) ) {
			$css_declarations = array_merge( $css_declarations, $vc_css );
		}

		// Generate CSS class.
		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );
		}

		$text = $this->esc_block_text( $text );

		// Link.
		$link      = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );
		if ( ! empty( $link_data['url'] ) ) {
			$text = '<a href="' . esc_url( $link_data['url'] ) . '">' . $text . '</a>';
		}

		// Output as paragraph block for p tags.
		if ( $is_paragraph ) {
			$block_attrs = [];
			if ( $text_align ) {
				$block_attrs['align'] = $text_align;
			}
			if ( $css_class ) {
				$block_attrs['className'] = $css_class;
			}

			$p_class = '';
			if ( $text_align ) {
				$p_class .= 'has-text-align-' . esc_attr( $text_align );
			}
			if ( $css_class ) {
				$p_class .= ( $p_class ? ' ' : '' ) . esc_attr( $css_class );
			}

			$class_attr = $p_class ? ' class="' . $p_class . '"' : '';

			$output  = '<!-- wp:paragraph' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
			$output .= '<p' . $class_attr . '>' . $text . '</p>' . "\n";
			$output .= '<!-- /wp:paragraph -->' . "\n\n";

			return $output;
		}

		// Output as heading block.
		$block_attrs = [];
		if ( 2 !== $level ) {
			$block_attrs['level'] = $level;
		}
		if ( $text_align ) {
			$block_attrs['textAlign'] = $text_align;
		}
		if ( $css_class ) {
			$block_attrs['className'] = $css_class;
		}

		$tag         = 'h' . $level;
		$align_class = $text_align ? ' has-text-align-' . esc_attr( $text_align ) : '';
		$extra_class = $css_class ? ' ' . esc_attr( $css_class ) : '';

		$output  = '<!-- wp:heading' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<' . $tag . ' class="wp-block-heading' . $align_class . $extra_class . '">' . $text . '</' . $tag . '>' . "\n";
		$output .= '<!-- /wp:heading -->' . "\n\n";

		return $output;
	}

	private function convert_mk_heading( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$content = $this->get_attr( $attrs, 'content', '' );
		if ( empty( $content ) ) {
			$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $content ) ) {
			return '';
		}

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

		// Collect CSS declarations from mk_fancy_title attributes.
		$css_declarations = [];
		$responsive_css   = [];

		$color = $this->get_attr( $attrs, 'color', '' );
		if ( $color ) {
			$css_declarations['color'] = $color;
		}

		$size = $this->get_attr( $attrs, 'size', '' );
		if ( $size ) {
			$css_declarations['font-size'] = $this->ensure_px( $size );
		}

		if ( $text_align ) {
			$css_declarations['text-align'] = $text_align;
		}

		$font_weight = $this->get_attr( $attrs, 'font_weight', '' );
		if ( $font_weight && 'inherit' !== $font_weight ) {
			$css_declarations['font-weight'] = $font_weight;
		}

		$txt_transform = $this->get_attr( $attrs, 'txt_transform', '' );
		if ( $txt_transform && 'initial' !== $txt_transform && 'none' !== $txt_transform ) {
			$css_declarations['text-transform'] = $txt_transform;
		}

		$letter_spacing = $this->get_attr( $attrs, 'letter_spacing', '' );
		if ( $letter_spacing && '0' !== $letter_spacing ) {
			$css_declarations['letter-spacing'] = $this->ensure_px( $letter_spacing );
		}

		$margin_top = $this->get_attr( $attrs, 'margin_top', '' );
		if ( $margin_top && '0' !== $margin_top ) {
			$css_declarations['margin-top'] = $this->ensure_px( $margin_top );
		}

		$margin_bottom = $this->get_attr( $attrs, 'margin_bottom', '' );
		if ( '' !== $margin_bottom ) {
			$css_declarations['padding-bottom'] = $this->ensure_px( $margin_bottom );
		}

		// Font family.
		$font_family = $this->get_attr( $attrs, 'font_family', 'none' );
		$font_type   = $this->get_attr( $attrs, 'font_type', '' );

		if ( 'none' !== $font_family && ! empty( $font_family ) ) {
			$css_declarations['font-family'] = '"' . $font_family . '", sans-serif';
			if ( 'google' === $font_type ) {
				$weight = $font_weight ? $font_weight : '400';
				$this->add_google_font( $font_family, $weight );
			}
		}

		// Responsive font size.
		$size_phone = $this->get_attr( $attrs, 'size_phone', '' );
		if ( $size_phone ) {
			$responsive_css['font-size'] = $this->ensure_px( $size_phone );
		}

		// Generate CSS class.
		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );

			if ( ! empty( $responsive_css ) ) {
				$this->builder->add_css(
					'@media (max-width: 767px) { .' . $css_class . ' }',
					$responsive_css
				);
			}
		}

		$block_attrs = [];
		if ( 2 !== $level ) {
			$block_attrs['level'] = $level;
		}
		if ( $text_align && 'left' !== $text_align ) {
			$block_attrs['textAlign'] = $text_align;
		}
		if ( $css_class ) {
			$block_attrs['className'] = $css_class;
		}

		$tag         = 'h' . $level;
		$content     = $this->esc_block_text( $content );
		$align_class = ( $text_align && 'left' !== $text_align ) ? ' has-text-align-' . esc_attr( $text_align ) : '';
		$extra_class = $css_class ? ' ' . esc_attr( $css_class ) : '';

		$output  = '<!-- wp:heading' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<' . $tag . ' class="wp-block-heading' . $align_class . $extra_class . '">' . $content . '</' . $tag . '>' . "\n";
		$output .= '<!-- /wp:heading -->' . "\n\n";

		return $output;
	}

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

	private function convert_custom_list( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = $this->get_attr( $attrs, 'content', '' );

		if ( empty( $content ) ) {
			$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		}

		if ( empty( $content ) ) {
			return '';
		}

		// Convert to <li> tags if not already.
		if ( false === strpos( $content, '<li>' ) ) {
			$items   = preg_split( '/\r?\n/', $content );
			$items   = array_filter( array_map( 'trim', $items ) );
			$content = '';
			foreach ( $items as $item ) {
				$content .= '<li>' . $this->esc_block_text( $item ) . '</li>';
			}
		}

		// CSS for list styling.
		$css_declarations = [];
		$icon_color       = $this->get_attr( $attrs, 'icon_color', '' );
		$margin_bottom    = $this->get_attr( $attrs, 'margin_bottom', '' );
		$align            = $this->get_attr( $attrs, 'align', '' );
		$el_class         = $this->get_attr( $attrs, 'el_class', '' );

		if ( $icon_color ) {
			$css_declarations['list-style'] = 'none';
		}

		if ( $margin_bottom && '30' !== $margin_bottom ) {
			$css_declarations['margin-bottom'] = $this->ensure_px( $margin_bottom );
		}

		if ( $align ) {
			$css_declarations['text-align'] = $align;
		}

		$css_class       = '';
		$marker_css      = [];

		if ( ! empty( $css_declarations ) || $icon_color ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );

			if ( $icon_color ) {
				$this->builder->add_css( '.' . $css_class . ' li::before', [
					'content'      => '"\\2022"',
					'color'        => $icon_color,
					'font-weight'  => 'bold',
					'margin-right' => '0.5em',
				] );
			}
		}

		$class_list = 'wp-block-list';
		if ( $css_class ) {
			$class_list .= ' ' . $css_class;
		}
		if ( $el_class ) {
			$class_list .= ' ' . $el_class;
		}

		$block_attrs = [];
		$extra = trim( ( $css_class ? $css_class : '' ) . ' ' . $el_class );
		if ( $extra ) {
			$block_attrs['className'] = $extra;
		}

		$output  = '<!-- wp:list' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<ul class="' . esc_attr( $class_list ) . '">' . $content . '</ul>' . "\n";
		$output .= '<!-- /wp:list -->' . "\n\n";

		return $output;
	}

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
			$content = '<span class="has-drop-cap">' . $content . '</span>';
		}

		$output  = '<!-- wp:freeform -->' . "\n";
		$output .= '<p>' . $content . '</p>' . "\n";
		$output .= '<!-- /wp:freeform -->' . "\n\n";

		return $output;
	}
}
