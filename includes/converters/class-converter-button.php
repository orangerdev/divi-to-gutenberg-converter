<?php
/**
 * Converter for button shortcodes.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Button extends DTG_Converter_Base {

	private $tags = [
		'vc_btn',
		'vc_button',
		'vc_button2',
		'mk_button',
		'mk_button_gradient',
	];

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

	public function convert( $node ) {
		switch ( $node['tag'] ) {
			case 'vc_btn':
				return $this->convert_vc_btn( $node );

			case 'vc_button':
			case 'vc_button2':
				return $this->convert_vc_button_legacy( $node );

			case 'mk_button':
			case 'mk_button_gradient':
				return $this->convert_mk_button( $node );

			default:
				return '';
		}
	}

	private function convert_vc_btn( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$title     = $this->get_attr( $attrs, 'title', 'Button' );
		$link      = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );
		$align     = $this->get_attr( $attrs, 'align', '' );

		return $this->build_button_block( $title, $link_data, $align );
	}

	private function convert_vc_button_legacy( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$title = $this->get_attr( $attrs, 'title', '' );
		if ( empty( $title ) ) {
			$title = isset( $node['content'] ) ? trim( strip_tags( $node['content'] ) ) : 'Button';
		}

		$url    = $this->get_attr( $attrs, 'href', '' );
		$target = $this->get_attr( $attrs, 'target', '' );

		$link_data = [
			'url'    => $url,
			'title'  => $title,
			'target' => $target,
			'rel'    => '',
		];

		return $this->build_button_block( $title, $link_data );
	}

	private function convert_mk_button( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$text = isset( $node['content'] ) ? trim( strip_tags( $node['content'] ) ) : '';
		if ( empty( $text ) ) {
			$text = $this->get_attr( $attrs, 'text', 'Button' );
		}

		$url    = $this->get_attr( $attrs, 'url', '' );
		$target = $this->get_attr( $attrs, 'target', '_self' );
		$align  = $this->get_attr( $attrs, 'align', '' );

		$link_data = [
			'url'    => $url,
			'title'  => $text,
			'target' => $target,
			'rel'    => '',
		];

		// Collect CSS for the button.
		$css_declarations   = [];
		$hover_declarations = [];

		$bg_color = $this->get_attr( $attrs, 'bg_color', '' );
		if ( $bg_color ) {
			$css_declarations['background-color'] = $bg_color;
		}

		$txt_color = $this->get_attr( $attrs, 'txt_color', '' );
		if ( $txt_color ) {
			$css_declarations['color'] = $txt_color;
		}

		$btn_hover_bg = $this->get_attr( $attrs, 'btn_hover_bg', '' );
		if ( $btn_hover_bg ) {
			$hover_declarations['background-color'] = $btn_hover_bg;
		}

		$btn_hover_txt = $this->get_attr( $attrs, 'btn_hover_txt_color', '' );
		if ( $btn_hover_txt ) {
			$hover_declarations['color'] = $btn_hover_txt;
		}

		$corner_style = $this->get_attr( $attrs, 'corner_style', '' );
		if ( 'full_rounded' === $corner_style ) {
			$css_declarations['border-radius'] = '99px';
		} elseif ( 'rounded' === $corner_style ) {
			$css_declarations['border-radius'] = '4px';
		}

		$dimension = $this->get_attr( $attrs, 'dimension', '' );
		if ( 'flat' === $dimension ) {
			$css_declarations['border'] = 'none';
		} elseif ( 'outline' === $dimension ) {
			$border_color = $bg_color ? $bg_color : '#333';
			$css_declarations['border']           = '2px solid ' . $border_color;
			$css_declarations['background-color']  = 'transparent';
			$css_declarations['color']             = $border_color;
		}

		$margin_bottom = $this->get_attr( $attrs, 'margin_bottom', '' );
		if ( $margin_bottom && '0' !== $margin_bottom ) {
			$css_declarations['margin-bottom'] = $this->ensure_px( $margin_bottom );
		}

		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );
			if ( ! empty( $hover_declarations ) ) {
				$this->add_css_hover( $css_class, $hover_declarations );
			}
		}

		return $this->build_button_block( $text, $link_data, $align, $css_class );
	}

	private function build_button_block( $text, $link_data = [], $align = '', $css_class = '' ) {
		$text = $this->esc_block_text( $text );
		if ( empty( $text ) ) {
			$text = 'Button';
		}

		$buttons_attrs = [];
		$layout_map    = [
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		];

		if ( $align && isset( $layout_map[ $align ] ) ) {
			$buttons_attrs['layout'] = [
				'type'           => 'flex',
				'justifyContent' => $layout_map[ $align ],
			];
		}

		$button_attrs = [];
		if ( ! empty( $link_data['url'] ) ) {
			$button_attrs['url'] = $link_data['url'];

			if ( '_blank' === ( $link_data['target'] ?? '' ) ) {
				$button_attrs['linkTarget'] = '_blank';
			}

			if ( ! empty( $link_data['rel'] ) ) {
				$button_attrs['rel'] = $link_data['rel'];
			}
		}

		if ( $css_class ) {
			$button_attrs['className'] = $css_class;
		}

		$href_attr = '';
		if ( ! empty( $link_data['url'] ) ) {
			$target_attr = ( '_blank' === ( $link_data['target'] ?? '' ) ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$href_attr   = ' href="' . esc_url( $link_data['url'] ) . '"' . $target_attr;
		}

		$btn_class = 'wp-block-button__link wp-element-button';
		if ( $css_class ) {
			$btn_class .= ' ' . esc_attr( $css_class );
		}

		$output  = '<!-- wp:buttons' . $this->json_attrs( $buttons_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-buttons">';

		$output .= '<!-- wp:button' . $this->json_attrs( $button_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-button">';
		$output .= '<a class="' . $btn_class . '"' . $href_attr . '>' . $text . '</a>';
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:button -->';

		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:buttons -->' . "\n\n";

		return $output;
	}
}
