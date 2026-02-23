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
		$vc_class     = $this->extract_vc_class( $css_attr );
		$fullwidth    = $this->get_attr( $attrs, 'fullwidth', '' );
		$el_class     = $this->get_attr( $attrs, 'el_class', '' );
		$row_id       = $this->get_attr( $attrs, 'id', '' );
		$visibility   = $this->get_attr( $attrs, 'visibility', '' );
		$css_class    = '';
		$extra_styles = [];

		// Merge vc_css into styles.
		if ( ! empty( $vc_css ) ) {
			$extra_styles = array_merge( $extra_styles, $vc_css );
		}

		// Sanitize excessive horizontal values (WPBakery fullwidth hack).
		if ( 'true' === $fullwidth ) {
			$horizontal_props = [ 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left-width', 'border-right-width' ];
			foreach ( $horizontal_props as $prop ) {
				if ( isset( $extra_styles[ $prop ] ) ) {
					$val = (int) $extra_styles[ $prop ];
					if ( abs( $val ) > 100 ) {
						unset( $extra_styles[ $prop ] );
					}
				}
			}

			// Add content constraint for fullwidth_content="false".
			$fullwidth_content = $this->get_attr( $attrs, 'fullwidth_content', '' );
			if ( 'false' === $fullwidth_content ) {
				$extra_styles['max-width']     = '1140px';
				$extra_styles['margin-left']   = 'auto';
				$extra_styles['margin-right']  = 'auto';
			}
		}

		// Column gap from column_padding.
		$column_padding = $this->get_attr( $attrs, 'column_padding', '' );
		if ( $column_padding && '0' !== $column_padding ) {
			$extra_styles['gap'] = $this->ensure_px( $column_padding );
		}

		// Generate CSS class if needed.
		if ( ! empty( $extra_styles ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $extra_styles );
		}

		// Visibility handling (responsive show/hide).
		if ( $visibility ) {
			if ( empty( $css_class ) ) {
				$css_class = $this->next_class();
			}
			$this->apply_visibility( $css_class, $visibility );
		}

		$class_list = $css_class;
		if ( $vc_class ) {
			$class_list = trim( $class_list . ' ' . $vc_class );
		}
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
			if ( $row_id ) {
				$block_attrs['anchor'] = $row_id;
			}

			$div_class = 'wp-block-group';
			if ( $class_list ) {
				$div_class .= ' ' . esc_attr( $class_list );
			}

			$id_attr = $row_id ? ' id="' . esc_attr( $row_id ) . '"' : '';

			$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
			$output .= '<div class="' . $div_class . '"' . $id_attr . '>';
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
		if ( $row_id ) {
			$block_attrs['anchor'] = $row_id;
		}

		$div_class = 'wp-block-columns';
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$id_attr = $row_id ? ' id="' . esc_attr( $row_id ) . '"' : '';

		$output  = '<!-- wp:columns' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="' . $div_class . '"' . $id_attr . '>';
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
		$vc_class  = $this->extract_vc_class( $css_attr );
		$css_class = '';

		if ( ! empty( $vc_css ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $vc_css );
		}

		$class_list = $css_class;
		if ( $vc_class ) {
			$class_list = trim( $class_list . ' ' . $vc_class );
		}
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

		// Section ID for anchor links.
		$section_id = $this->get_attr( $attrs, 'section_id', '' );
		if ( $section_id ) {
			$block_attrs['anchor'] = $section_id;
		}

		// Collect CSS for section.
		$css_declarations = [];
		$el_class         = $this->get_attr( $attrs, 'el_class', '' );
		$visibility       = $this->get_attr( $attrs, 'visibility', '' );

		$bg_color = $this->get_attr( $attrs, 'bg_color', '' );
		if ( $bg_color ) {
			$css_declarations['background-color'] = $bg_color;
		}

		$bg_image = $this->get_attr( $attrs, 'bg_image', '' );
		if ( $bg_image ) {
			$css_declarations['background-image']    = 'url(' . $bg_image . ')';
			$css_declarations['position']            = 'relative';
			$css_declarations['overflow']             = 'hidden';
		}

		$bg_position = $this->get_attr( $attrs, 'bg_position', '' );
		if ( $bg_position ) {
			$css_declarations['background-position'] = $bg_position;
		} elseif ( $bg_image ) {
			// Default background-position when bg_image is set.
			$css_declarations['background-position'] = 'center center';
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
			$pb_val = (int) $padding_bottom;
			if ( $pb_val < 0 ) {
				// Negative padding is invalid CSS — convert to margin-bottom overlap.
				$css_declarations['margin-bottom'] = $pb_val . 'px';
			} else {
				$css_declarations['padding-bottom'] = $this->ensure_px( $padding_bottom );
			}
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
				$this->add_css( $css_class, [ 'position' => 'relative' ] );
			}
		}

		// Responsive background image for portrait/mobile.
		$bg_image_portrait = $this->get_attr( $attrs, 'bg_image_portrait', '' );
		if ( $bg_image_portrait ) {
			if ( empty( $css_class ) ) {
				$css_class = $this->next_class();
				$this->add_css( $css_class, $css_declarations );
			}
			$this->builder->add_css(
				'@media (max-width: 767px) { .' . $css_class,
				[ 'background-image' => 'url(' . $bg_image_portrait . ') !important' ]
			);
		}

		// Visibility handling (responsive show/hide).
		if ( $visibility ) {
			if ( empty( $css_class ) ) {
				$css_class = $this->next_class();
			}
			$this->apply_visibility( $css_class, $visibility );
		}

		$class_list = $css_class;
		if ( $el_class ) {
			$class_list = trim( $class_list . ' ' . $el_class );
		}
		if ( $class_list ) {
			$block_attrs['className'] = $class_list;
		}

		$children = isset( $node['children'] ) ? $node['children'] : [];

		// Auto-wrap multiple vc_column children in wp:columns.
		$column_children = array_filter( $children, function( $child ) {
			return 'shortcode' === $child['type']
				&& in_array( $child['tag'], [ 'vc_column', 'vc_column_inner' ], true );
		});

		if ( count( $column_children ) > 1 ) {
			$inner_content = $this->wrap_columns_in_section( $children );
		} else {
			$inner_content = $this->convert_children( $children );
		}

		$div_class = 'wp-block-group';
		if ( ! empty( $block_attrs['align'] ) ) {
			$div_class .= ' alignfull';
		}
		if ( $class_list ) {
			$div_class .= ' ' . esc_attr( $class_list );
		}

		$id_attr = $section_id ? ' id="' . esc_attr( $section_id ) . '"' : '';

		$output  = '<!-- wp:group' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<div class="' . $div_class . '"' . $id_attr . '>';
		$output .= $inner_content;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:group -->' . "\n\n";

		return $output;
	}

	/**
	 * Wrap consecutive vc_column children in wp:columns blocks.
	 * Non-column children are rendered as standalone blocks between column groups.
	 */
	private function wrap_columns_in_section( $children ) {
		$output        = '';
		$column_buffer = [];

		foreach ( $children as $child ) {
			$is_column = ( 'shortcode' === $child['type']
				&& in_array( $child['tag'], [ 'vc_column', 'vc_column_inner' ], true ) );

			if ( $is_column ) {
				$column_buffer[] = $child;
			} else {
				// Flush any buffered columns as a wp:columns block.
				if ( ! empty( $column_buffer ) ) {
					$output       .= $this->build_columns_block( $column_buffer );
					$column_buffer = [];
				}
				// Render non-column child normally.
				$output .= $this->convert_children( [ $child ] );
			}
		}

		// Flush remaining columns.
		if ( ! empty( $column_buffer ) ) {
			$output .= $this->build_columns_block( $column_buffer );
		}

		return $output;
	}

	/**
	 * Build a wp:columns block from an array of column child nodes.
	 */
	private function build_columns_block( $column_nodes ) {
		$inner = '';
		foreach ( $column_nodes as $col ) {
			$inner .= $this->convert_children( [ $col ] );
		}

		$output  = '<!-- wp:columns -->' . "\n";
		$output .= '<div class="wp-block-columns">';
		$output .= $inner;
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:columns -->' . "\n\n";

		return $output;
	}
}
