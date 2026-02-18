<?php
/**
 * Abstract base class for shortcode converters.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

abstract class DTG_Converter_Base {

	/** @var DTG_Gutenberg_Builder|null */
	protected $builder;

	public function set_builder( $builder ) {
		$this->builder = $builder;
	}

	abstract public function can_convert( $tag );
	abstract public function convert( $node );

	protected function convert_children( $children ) {
		if ( empty( $children ) || ! $this->builder ) {
			return '';
		}
		return $this->builder->build_from_nodes( $children );
	}

	protected function get_attr( $attrs, $key, $default = '' ) {
		return isset( $attrs[ $key ] ) ? $attrs[ $key ] : $default;
	}

	/**
	 * Get next unique CSS class name from builder.
	 */
	protected function next_class() {
		if ( $this->builder ) {
			return $this->builder->next_class();
		}
		return 'dtg-0-0';
	}

	/**
	 * Add CSS rules for a class to the builder's CSS collection.
	 */
	protected function add_css( $class_name, $declarations ) {
		if ( $this->builder && ! empty( $declarations ) ) {
			$this->builder->add_css( '.' . $class_name, $declarations );
		}
	}

	/**
	 * Add CSS with hover state.
	 */
	protected function add_css_hover( $class_name, $hover_declarations ) {
		if ( $this->builder && ! empty( $hover_declarations ) ) {
			$this->builder->add_css( '.' . $class_name . ':hover', $hover_declarations );
		}
	}

	/**
	 * Add a responsive CSS rule.
	 */
	protected function add_css_responsive( $class_name, $declarations, $max_width = '767px' ) {
		if ( $this->builder && ! empty( $declarations ) ) {
			$this->builder->add_css( '@media (max-width: ' . $max_width . ') { .' . $class_name, $declarations );
		}
	}

	/**
	 * Register a Google Font.
	 */
	protected function add_google_font( $family, $weight = '400', $style = 'normal' ) {
		if ( $this->builder && ! empty( $family ) ) {
			$this->builder->add_google_font( $family, $weight, $style );
		}
	}

	/**
	 * Parse WPBakery css attribute.
	 * Format: ".vc_custom_xxx{margin-bottom: 0px !important;}"
	 *
	 * @return array CSS declarations.
	 */
	protected function parse_vc_css( $css_attr ) {
		if ( empty( $css_attr ) ) {
			return [];
		}

		if ( preg_match( '/\{([^}]+)\}/', $css_attr, $m ) ) {
			$declarations = [];
			$parts        = explode( ';', $m[1] );

			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( empty( $part ) ) {
					continue;
				}

				$kv = explode( ':', $part, 2 );
				if ( count( $kv ) === 2 ) {
					$prop  = trim( $kv[0] );
					$value = trim( str_replace( '!important', '', $kv[1] ) );
					$value = trim( $value );

					if ( '' !== $value ) {
						$declarations[ $prop ] = $value;
					}
				}
			}

			return $declarations;
		}

		return [];
	}

	/**
	 * Parse WPBakery font_container attribute.
	 * Format: "tag:h1|font_size:70|text_align:center|color:%23ffffff"
	 */
	protected function parse_font_container( $font_container ) {
		$result = [
			'tag'        => 'h2',
			'font_size'  => '',
			'text_align' => '',
			'color'      => '',
			'line_height' => '',
		];

		if ( empty( $font_container ) ) {
			return $result;
		}

		$parts = explode( '|', $font_container );
		foreach ( $parts as $part ) {
			$pair = explode( ':', $part, 2 );
			if ( count( $pair ) === 2 ) {
				$key   = trim( $pair[0] );
				$value = urldecode( trim( $pair[1] ) );
				if ( array_key_exists( $key, $result ) ) {
					$result[ $key ] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Parse WPBakery google_fonts attribute.
	 * Format: "font_family:Rubik%20One%3A400|font_style:400%20regular%3A400%3Anormal"
	 */
	protected function parse_google_fonts( $google_fonts_attr ) {
		$result = [
			'family' => '',
			'weight' => '400',
			'style'  => 'normal',
		];

		if ( empty( $google_fonts_attr ) ) {
			return $result;
		}

		$parts = explode( '|', $google_fonts_attr );
		foreach ( $parts as $part ) {
			$pair = explode( ':', $part, 2 );
			if ( count( $pair ) === 2 ) {
				$key   = trim( $pair[0] );
				$value = urldecode( trim( $pair[1] ) );

				if ( 'font_family' === $key ) {
					$family_parts    = explode( ':', $value );
					$result['family'] = trim( $family_parts[0] );
				}

				if ( 'font_style' === $key ) {
					$style_parts = explode( ':', $value );
					if ( isset( $style_parts[1] ) ) {
						$result['weight'] = $style_parts[1];
					}
					if ( isset( $style_parts[2] ) ) {
						$result['style'] = $style_parts[2];
					}
				}
			}
		}

		return $result;
	}

	protected function parse_vc_link( $link_string ) {
		$result = [
			'url'    => '',
			'title'  => '',
			'target' => '',
			'rel'    => '',
		];

		if ( empty( $link_string ) ) {
			return $result;
		}

		$parts = explode( '|', $link_string );
		foreach ( $parts as $part ) {
			$pair = explode( ':', $part, 2 );
			if ( count( $pair ) === 2 ) {
				$key   = trim( $pair[0] );
				$value = urldecode( trim( $pair[1] ) );
				if ( array_key_exists( $key, $result ) ) {
					$result[ $key ] = $value;
				}
			}
		}

		return $result;
	}

	protected function width_to_percentage( $width ) {
		$map = [
			'1/1'   => '100%',
			'1/2'   => '50%',
			'1/3'   => '33.33%',
			'2/3'   => '66.66%',
			'1/4'   => '25%',
			'3/4'   => '75%',
			'1/6'   => '16.66%',
			'5/6'   => '83.33%',
			'1/12'  => '8.33%',
			'5/12'  => '41.66%',
			'7/12'  => '58.33%',
			'11/12' => '91.66%',
		];

		if ( isset( $map[ $width ] ) ) {
			return $map[ $width ];
		}

		if ( strpos( $width, '/' ) !== false ) {
			$parts = explode( '/', $width );
			if ( count( $parts ) === 2 && intval( $parts[1] ) > 0 ) {
				$percentage = round( ( intval( $parts[0] ) / intval( $parts[1] ) ) * 100, 2 );
				return $percentage . '%';
			}
		}

		return '';
	}

	protected function esc_block_text( $text ) {
		return wp_kses_post( $text );
	}

	protected function json_attrs( $attrs ) {
		$attrs = array_filter( $attrs, function( $v ) {
			return '' !== $v && null !== $v;
		});

		if ( empty( $attrs ) ) {
			return '';
		}

		return ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Ensure a numeric value has px unit.
	 */
	protected function ensure_px( $value ) {
		if ( is_numeric( $value ) ) {
			return $value . 'px';
		}
		return $value;
	}
}
