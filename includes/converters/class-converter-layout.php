<?php
/**
 * Converter for layout shortcodes: vc_row, vc_column, vc_section, mk_page_section.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Layout extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_row',
		'vc_row_inner',
		'vc_column',
		'vc_column_inner',
		'vc_section',
		'mk_page_section',
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
		$tag = $node['tag'];

		switch ( $tag ) {
			case 'vc_row':
			case 'vc_row_inner':
				return $this->convert_row( $node );

			case 'vc_column':
			case 'vc_column_inner':
				return $this->convert_column( $node );

			case 'vc_section':
			case 'mk_page_section':
				return $this->convert_section( $node );

			default:
				return '';
		}
	}

	/**
	 * Convert vc_row / vc_row_inner to wp:columns or wp:group.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_row( $node ) {
		$children = isset( $node['children'] ) ? $node['children'] : [];

		// Count actual column children.
		$column_children = array_filter( $children, function( $child ) {
			return 'shortcode' === $child['type']
				&& in_array( $child['tag'], [ 'vc_column', 'vc_column_inner' ], true );
		});

		$inner_content = $this->convert_children( $children );

		// Single column or no columns → wp:group.
		if ( count( $column_children ) <= 1 ) {
			$attrs = [ 'layout' => [ 'type' => 'constrained' ] ];

			$output  = '<!-- wp:group' . $this->json_attrs( $attrs ) . ' -->' . "\n";
			$output .= '<div class="wp-block-group">';
			$output .= $inner_content;
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:group -->' . "\n\n";

			return $output;
		}

		// Multiple columns → wp:columns.
		$output  = '<!-- wp:columns -->' . "\n";
		$output .= '<div class="wp-block-columns">';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:columns -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_column / vc_column_inner to wp:column.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_column( $node ) {
		$attrs  = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$width  = $this->get_attr( $attrs, 'width', '' );

		$block_attrs = [];
		$style_attr  = '';

		if ( $width ) {
			$percentage = $this->width_to_percentage( $width );
			if ( $percentage ) {
				$block_attrs['width'] = $percentage;
				$style_attr = ' style="flex-basis:' . esc_attr( $percentage ) . '"';
			}
		}

		$inner_content = $this->convert_children( isset( $node['children'] ) ? $node['children'] : [] );

		$output  = '<!-- wp:column' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-column"' . $style_attr . '>';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:column -->' . "\n";

		return $output;
	}

	/**
	 * Convert vc_section / mk_page_section to wp:group.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_section( $node ) {
		$attrs       = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$block_attrs = [ 'layout' => [ 'type' => 'constrained' ] ];

		// Check for full-width.
		$full_width = $this->get_attr( $attrs, 'full_width', '' );
		if ( 'stretch_row' === $full_width || 'true' === $this->get_attr( $attrs, 'full_width', '' ) ) {
			$block_attrs['align'] = 'full';
		}

		$inner_content = $this->convert_children( isset( $node['children'] ) ? $node['children'] : [] );

		$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-group">';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:group -->' . "\n\n";

		return $output;
	}
}
