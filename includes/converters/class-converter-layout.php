<?php
/**
 * Converter for layout shortcodes: vc_row, vc_column, vc_section, mk_page_section.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Layout extends DTG_Converter_Base {

	private $tags = [
		'vc_row',
		'vc_row_inner',
		'vc_column',
		'vc_column_inner',
		'vc_section',
		'mk_page_section',
	];

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

	public function convert( $node ) {
		switch ( $node['tag'] ) {
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

	private function convert_row( $node ) {
		$attrs    = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$children = isset( $node['children'] ) ? $node['children'] : [];

		// Count column children.
		$column_children = array_filter( $children, function( $child ) {
			return 'shortcode' === $child['type']
				&& in_array( $child['tag'], [ 'vc_column', 'vc_column_inner' ], true );
		});

		$inner_content = $this->convert_children( $children );

		// CSS from vc_row css attribute.
		$css_attr     = $this->get_attr( $attrs, 'css', '' );
		$vc_css       = $this->parse_vc_css( $css_attr );
		$fullwidth    = $this->get_attr( $attrs, 'fullwidth', '' );
		$el_class     = $this->get_attr( $attrs, 'el_class', '' );
		$css_class    = '';
		$extra_styles = [];

		// Merge vc_css into styles.
		if ( ! empty( $vc_css ) ) {
			$extra_styles = array_merge( $extra_styles, $vc_css );
		}

		// Generate CSS class if needed.
		if ( ! empty( $extra_styles ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $extra_styles );
		}

		$class_list = $css_class;
		if ( $el_class ) {
			$class_list = trim( $class_list . ' ' . $el_class );
		}

		// Single column or no columns → wp:group.
		if ( count( $column_children ) <= 1 ) {
			$block_attrs = [ 'layout' => [ 'type' => 'constrained' ] ];

			if ( 'true' === $fullwidth ) {
				$block_attrs['align'] = 'full';
			}
			if ( $class_list ) {
				$block_attrs['className'] = $class_list;
			}

			$div_class = 'wp-block-group';
			if ( $class_list ) {
				$div_class .= ' ' . esc_attr( $class_list );
			}

			$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
			$output .= '<div class="' . $div_class . '">';
			$output .= $inner_content;
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:group -->' . "\n\n";

			return $output;
		}

		// Multiple columns → wp:columns.
		$block_attrs = [];
		if ( $class_list ) {
			$block_attrs['className'] = $class_list;
		}

		$div_class = 'wp-block-columns';
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$output  = '<!-- wp:columns' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="' . $div_class . '">';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:columns -->' . "\n\n";

		return $output;
	}

	private function convert_column( $node ) {
		$attrs     = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$width     = $this->get_attr( $attrs, 'width', '' );
		$css_attr  = $this->get_attr( $attrs, 'css', '' );
		$el_class  = $this->get_attr( $attrs, 'el_class', '' );

		$block_attrs = [];
		$style_attr  = '';

		if ( $width ) {
			$percentage = $this->width_to_percentage( $width );
			if ( $percentage ) {
				$block_attrs['width'] = $percentage;
				$style_attr = ' style="flex-basis:' . esc_attr( $percentage ) . '"';
			}
		}

		// CSS from vc_column css attribute.
		$vc_css    = $this->parse_vc_css( $css_attr );
		$css_class = '';

		if ( ! empty( $vc_css ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $vc_css );
		}

		$class_list = $css_class;
		if ( $el_class ) {
			$class_list = trim( $class_list . ' ' . $el_class );
		}
		if ( $class_list ) {
			$block_attrs['className'] = $class_list;
		}

		$inner_content = $this->convert_children( isset( $node['children'] ) ? $node['children'] : [] );

		$div_class = 'wp-block-column';
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$output  = '<!-- wp:column' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="' . $div_class . '"' . $style_attr . '>';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:column -->' . "\n";

		return $output;
	}

	private function convert_section( $node ) {
		$attrs       = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$block_attrs = [ 'layout' => [ 'type' => 'constrained' ] ];

		$full_width = $this->get_attr( $attrs, 'full_width', '' );
		if ( 'stretch_row' === $full_width || 'true' === $full_width ) {
			$block_attrs['align'] = 'full';
		}

		// For mk_page_section, always full width.
		if ( 'mk_page_section' === $node['tag'] ) {
			$block_attrs['align'] = 'full';
		}

		// Collect CSS for section.
		$css_declarations = [];
		$el_class         = $this->get_attr( $attrs, 'el_class', '' );

		$bg_color = $this->get_attr( $attrs, 'bg_color', '' );
		if ( $bg_color ) {
			$css_declarations['background-color'] = $bg_color;
		}

		$bg_image = $this->get_attr( $attrs, 'bg_image', '' );
		if ( $bg_image ) {
			$css_declarations['background-image'] = 'url(' . $bg_image . ')';
		}

		$bg_position = $this->get_attr( $attrs, 'bg_position', '' );
		if ( $bg_position ) {
			$css_declarations['background-position'] = $bg_position;
		}

		$bg_repeat = $this->get_attr( $attrs, 'bg_repeat', '' );
		if ( $bg_repeat ) {
			$css_declarations['background-repeat'] = $bg_repeat;
		}

		$bg_stretch = $this->get_attr( $attrs, 'bg_stretch', '' );
		if ( 'true' === $bg_stretch ) {
			$css_declarations['background-size'] = 'cover';
		}

		$min_height = $this->get_attr( $attrs, 'min_height', '' );
		if ( $min_height && '0' !== $min_height ) {
			$css_declarations['min-height'] = $this->ensure_px( $min_height );
		}

		$padding_top = $this->get_attr( $attrs, 'padding_top', '' );
		if ( '' !== $padding_top ) {
			$css_declarations['padding-top'] = $this->ensure_px( $padding_top );
		}

		$padding_bottom = $this->get_attr( $attrs, 'padding_bottom', '' );
		if ( '' !== $padding_bottom ) {
			$css_declarations['padding-bottom'] = $this->ensure_px( $padding_bottom );
		}

		$vertical_align = $this->get_attr( $attrs, 'vertical_align', '' );
		if ( 'center' === $vertical_align ) {
			$css_declarations['display']     = 'flex';
			$css_declarations['align-items'] = 'center';
		}

		// Video overlay.
		$video_color_mask = $this->get_attr( $attrs, 'video_color_mask', '' );
		$video_opacity    = $this->get_attr( $attrs, 'video_opacity', '' );

		$css_class = '';
		if ( ! empty( $css_declarations ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $css_declarations );

			// Add overlay pseudo-element if needed.
			if ( $video_color_mask && $video_opacity && '0' !== $video_opacity ) {
				$this->builder->add_css( '.' . $css_class . '::before', [
					'content'          => '""',
					'position'         => 'absolute',
					'top'              => '0',
					'left'             => '0',
					'right'            => '0',
					'bottom'           => '0',
					'background-color' => $video_color_mask,
					'opacity'          => $video_opacity,
					'pointer-events'   => 'none',
				] );
				// Ensure section is positioned for the overlay.
				$css_declarations['position'] = 'relative';
				// Update the rule.
				$this->add_css( $css_class, [ 'position' => 'relative' ] );
			}
		}

		$class_list = $css_class;
		if ( $el_class ) {
			$class_list = trim( $class_list . ' ' . $el_class );
		}
		if ( $class_list ) {
			$block_attrs['className'] = $class_list;
		}

		$inner_content = $this->convert_children( isset( $node['children'] ) ? $node['children'] : [] );

		$div_class = 'wp-block-group';
		if ( ! empty( $block_attrs['align'] ) ) {
			$div_class .= ' alignfull';
		}
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="' . $div_class . '">';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:group -->' . "\n\n";

		return $output;
	}
}
