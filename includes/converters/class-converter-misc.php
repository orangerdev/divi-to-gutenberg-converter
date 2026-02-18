<?php
/**
 * Converter for miscellaneous shortcodes.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Misc extends DTG_Converter_Base {

	private $tags = [
		'vc_raw_html',
		'vc_raw_js',
		'vc_cta',
		'vc_cta_button',
		'vc_cta_button2',
		'vc_message',
		'vc_icon',
		'vc_copyright',
		'mk_custom_box',
	];

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

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

			case 'mk_custom_box':
				return $this->convert_custom_box( $node );

			default:
				return '';
		}
	}

	private function convert_raw_html( $node ) {
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		$decoded = rawurldecode( base64_decode( $content ) );
		if ( empty( $decoded ) ) {
			$decoded = $content;
		}

		$output  = '<!-- wp:html -->' . "\n";
		$output .= $decoded . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	private function convert_raw_js( $node ) {
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';

		if ( empty( $content ) ) {
			return '';
		}

		$decoded = rawurldecode( base64_decode( $content ) );
		if ( empty( $decoded ) ) {
			$decoded = $content;
		}

		if ( false === stripos( $decoded, '<script' ) ) {
			$decoded = '<script>' . $decoded . '</script>';
		}

		$output  = '<!-- wp:html -->' . "\n";
		$output .= $decoded . "\n";
		$output .= '<!-- /wp:html -->' . "\n\n";

		return $output;
	}

	private function convert_cta( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$heading     = $this->get_attr( $attrs, 'h2', '' );
		$sub_heading = $this->get_attr( $attrs, 'h4', '' );
		$content     = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		$btn_title   = $this->get_attr( $attrs, 'btn_title', '' );
		$btn_link    = $this->get_attr( $attrs, 'btn_link', '' );

		$output  = '<!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n";
		$output .= '<div class="wp-block-group">';

		if ( $heading ) {
			$output .= '<!-- wp:heading -->' . "\n";
			$output .= '<h2 class="wp-block-heading">' . $this->esc_block_text( $heading ) . '</h2>' . "\n";
			$output .= '<!-- /wp:heading -->' . "\n";
		}

		if ( $sub_heading ) {
			$output .= '<!-- wp:heading {"level":4} -->' . "\n";
			$output .= '<h4 class="wp-block-heading">' . $this->esc_block_text( $sub_heading ) . '</h4>' . "\n";
			$output .= '<!-- /wp:heading -->' . "\n";
		}

		if ( $content ) {
			$output .= '<!-- wp:freeform -->' . "\n";
			$output .= $content . "\n";
			$output .= '<!-- /wp:freeform -->' . "\n";
		}

		if ( $btn_title ) {
			$link_data = $this->parse_vc_link( $btn_link );
			$href_attr = '';
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

	private function convert_message( $node ) {
		$attrs   = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$content = isset( $node['content'] ) ? trim( $node['content'] ) : '';
		$color   = $this->get_attr( $attrs, 'message_box_color', 'info' );

		if ( empty( $content ) ) {
			return '';
		}

		$output  = '<!-- wp:freeform -->' . "\n";
		$output .= '<div class="dtg-message dtg-message-' . esc_attr( $color ) . '">' . $content . '</div>' . "\n";
		$output .= '<!-- /wp:freeform -->' . "\n\n";

		return $output;
	}

	private function convert_icon( $node ) {
		return '';
	}

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

	/**
	 * Convert mk_custom_box to wp:group with gradient/rounded styling.
	 */
	private function convert_custom_box( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$css_declarations = [];

		$corner_radius = $this->get_attr( $attrs, 'corner_radius', '' );
		if ( $corner_radius ) {
			$css_declarations['border-radius'] = $this->ensure_px( $corner_radius );
		}

		$padding_vertical = $this->get_attr( $attrs, 'padding_vertical', '' );
		if ( $padding_vertical ) {
			$css_declarations['padding-top']    = $this->ensure_px( $padding_vertical );
			$css_declarations['padding-bottom'] = $this->ensure_px( $padding_vertical );
		}

		$min_height = $this->get_attr( $attrs, 'min_height', '' );
		if ( $min_height ) {
			$css_declarations['min-height'] = $this->ensure_px( $min_height );
		}

		$bg_color = $this->get_attr( $attrs, 'bg_color', '' );
		$bg_style = $this->get_attr( $attrs, 'background_style', '' );

		if ( 'gradient_color' === $bg_style ) {
			$from = $this->get_attr( $attrs, 'bg_grandient_color_from', '#000000' );
			$to   = $this->get_attr( $attrs, 'bg_grandient_color_to', '#000000' );
			$css_declarations['background'] = 'linear-gradient(to bottom, ' . $from . ', ' . $to . ')';
		} elseif ( $bg_color ) {
			$css_declarations['background-color'] = $bg_color;
		}

		$css_declarations['padding-left']  = '20px';
		$css_declarations['padding-right'] = '20px';
		$css_declarations['overflow']      = 'hidden';

		$css_class = $this->next_class();
		$this->add_css( $css_class, $css_declarations );

		// Hover gradient.
		$hover_style = $this->get_attr( $attrs, 'background_hov_color_style', '' );
		if ( 'gradient_color' === $hover_style ) {
			$hover_from = $this->get_attr( $attrs, 'bg_hov_grandient_color_from', '' );
			$hover_to   = $this->get_attr( $attrs, 'bg_hov_grandient_color_to', '' );
			if ( $hover_from && $hover_to ) {
				$this->add_css_hover( $css_class, [
					'background' => 'linear-gradient(to bottom, ' . $hover_from . ', ' . $hover_to . ')',
				] );
			}
		}

		$block_attrs = [
			'className' => $css_class,
			'layout'    => [ 'type' => 'constrained' ],
		];

		$inner_content = $this->convert_children( isset( $node['children'] ) ? $node['children'] : [] );

		$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-group ' . esc_attr( $css_class ) . '">';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:group -->' . "\n\n";

		return $output;
	}
}
