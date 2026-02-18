<?php
/**
 * Converter for separator/spacer shortcodes.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Separator extends DTG_Converter_Base {

	private $tags = [
		'vc_separator',
		'vc_text_separator',
		'vc_empty_space',
		'mk_divider',
		'mk_padding_divider',
	];

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

	public function convert( $node ) {
		switch ( $node['tag'] ) {
			case 'vc_separator':
			case 'vc_text_separator':
			case 'mk_divider':
				return $this->convert_separator( $node );

			case 'vc_empty_space':
			case 'mk_padding_divider':
				return $this->convert_spacer( $node );

			default:
				return '';
		}
	}

	private function convert_separator( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$style_map = [
			'solid'  => 'is-style-default',
			'dashed' => 'is-style-default',
			'dotted' => 'is-style-dots',
			'double' => 'is-style-default',
		];

		$border_style = $this->get_attr( $attrs, 'style', 'solid' );
		$style_class  = isset( $style_map[ $border_style ] ) ? $style_map[ $border_style ] : 'is-style-default';

		// CSS from mk_divider attributes.
		$css_declarations = [];
		$margin_bottom    = $this->get_attr( $attrs, 'margin_bottom', '' );
		if ( $margin_bottom ) {
			$css_declarations['margin-bottom'] = $this->ensure_px( $margin_bottom );
		}

		// vc_separator border_width and color.
		$border_width = $this->get_attr( $attrs, 'border_width', '' );
		if ( $border_width ) {
			$css_declarations['border-top-width'] = $this->ensure_px( $border_width );
		}

		$sep_color = $this->get_attr( $attrs, 'color', '' );
		if ( $sep_color && 'grey' !== $sep_color ) {
			$css_declarations['border-top-color'] = $sep_color;
		}

		// vc_separator css attribute.
		$css_attr = $this->get_attr( $attrs, 'css', '' );
		$vc_css   = $this->parse_vc_css( $css_attr );
		if ( ! empty( $vc_css ) ) {
			$css_declarations = array_merge( $css_declarations, $vc_css );
		}

		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );
		}

		$block_attrs = [];
		if ( 'is-style-dots' === $style_class ) {
			$block_attrs['className'] = 'is-style-dots';
		}
		if ( $css_class ) {
			$existing = isset( $block_attrs['className'] ) ? $block_attrs['className'] . ' ' : '';
			$block_attrs['className'] = $existing . $css_class;
		}

		$hr_class = 'wp-block-separator has-alpha-channel-opacity ' . esc_attr( $style_class );
		if ( $css_class ) {
			$hr_class .= ' ' . esc_attr( $css_class );
		}

		$output  = '<!-- wp:separator' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<hr class="' . $hr_class . '"/>' . "\n";
		$output .= '<!-- /wp:separator -->' . "\n\n";

		return $output;
	}

	private function convert_spacer( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$height = $this->get_attr( $attrs, 'height', '' );

		if ( empty( $height ) ) {
			$height = $this->get_attr( $attrs, 'size', '' );
		}

		if ( empty( $height ) ) {
			$height = '32px';
		}

		if ( is_numeric( $height ) ) {
			$height .= 'px';
		}

		// vc_empty_space css attribute.
		$css_attr  = $this->get_attr( $attrs, 'css', '' );
		$vc_css    = $this->parse_vc_css( $css_attr );
		$css_class = '';

		if ( ! empty( $vc_css ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $vc_css );
		}

		$block_attrs = [ 'height' => $height ];
		if ( $css_class ) {
			$block_attrs['className'] = $css_class;
		}

		$div_class = 'wp-block-spacer';
		if ( $css_class ) {
			$div_class .= ' ' . esc_attr( $css_class );
		}

		$output  = '<!-- wp:spacer' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="' . $div_class . '"></div>' . "\n";
		$output .= '<!-- /wp:spacer -->' . "\n\n";

		return $output;
	}
}
