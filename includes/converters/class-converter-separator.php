<?php
/**
 * Converter for separator/spacer shortcodes.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Separator extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_separator',
		'vc_text_separator',
		'vc_empty_space',
		'mk_divider',
		'mk_padding_divider',
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

	/**
	 * Convert separator shortcodes to wp:separator.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
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

		$block_attrs = [];
		if ( 'is-style-dots' === $style_class ) {
			$block_attrs['className'] = 'is-style-dots';
		}

		$output  = '<!-- wp:separator' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<hr class="wp-block-separator has-alpha-channel-opacity ' . esc_attr( $style_class ) . '"/>' . "\n";
		$output .= '<!-- /wp:separator -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert spacer shortcodes to wp:spacer.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_spacer( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		// vc_empty_space uses 'height', mk_padding_divider uses 'size'.
		$height = $this->get_attr( $attrs, 'height', '' );

		if ( empty( $height ) ) {
			$height = $this->get_attr( $attrs, 'size', '' );
		}

		// Default height.
		if ( empty( $height ) ) {
			$height = '32px';
		}

		// Ensure unit.
		if ( is_numeric( $height ) ) {
			$height .= 'px';
		}

		$block_attrs = [ 'height' => $height ];

		$output  = '<!-- wp:spacer' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>' . "\n";
		$output .= '<!-- /wp:spacer -->' . "\n\n";

		return $output;
	}
}
