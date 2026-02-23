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
			$css_declarations['border-top-style'] = $border_style;
		}

		$sep_color    = $this->get_attr( $attrs, 'color', '' );
		$named_colors = [
			'white'       => '#ffffff',
			'grey'        => '#ebebeb',
			'black'       => '#000000',
			'blue'        => '#5472d2',
			'turquoise'   => '#00c1cf',
			'pink'        => '#fe6c61',
			'violet'      => '#8d6dc4',
			'peacoc'      => '#4cadc9',
			'chino'       => '#cec2ab',
			'mulled_wine' => '#50485b',
			'vista_blue'  => '#75d69c',
			'orange'      => '#f7be68',
			'sky'         => '#5aa1e3',
			'green'       => '#6dab3c',
			'juicy_pink'  => '#f4524d',
			'sandy_brown' => '#f79468',
			'purple'      => '#b97ebb',
			'red'         => '#ff0000',
		];
		if ( $sep_color && 'grey' !== $sep_color ) {
			$resolved = isset( $named_colors[ $sep_color ] ) ? $named_colors[ $sep_color ] : $sep_color;
			$css_declarations['border-top-color'] = $resolved;
		}

		// vc_separator css attribute.
		$css_attr = $this->get_attr( $attrs, 'css', '' );
		$vc_css   = $this->parse_vc_css( $css_attr );
		$vc_class = $this->extract_vc_class( $css_attr );
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
		$extra_class = trim( $css_class . ( $vc_class ? ' ' . $vc_class : '' ) );
		if ( $extra_class ) {
			$existing = isset( $block_attrs['className'] ) ? $block_attrs['className'] . ' ' : '';
			$block_attrs['className'] = $existing . $extra_class;
		}

		$hr_class = 'wp-block-separator has-alpha-channel-opacity ' . esc_attr( $style_class );
		if ( $extra_class ) {
			$hr_class .= ' ' . esc_attr( $extra_class );
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
		$vc_class  = $this->extract_vc_class( $css_attr );
		$css_class = '';

		if ( ! empty( $vc_css ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $vc_css );
		}

		$class_list = trim( $css_class . ( $vc_class ? ' ' . $vc_class : '' ) );

		$block_attrs = [ 'height' => $height ];
		if ( $class_list ) {
			$block_attrs['className'] = $class_list;
		}

		$div_class = 'wp-block-spacer';
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$output  = '<!-- wp:spacer' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="' . $div_class . '"></div>' . "\n";
		$output .= '<!-- /wp:spacer -->' . "\n\n";

		return $output;
	}
}
