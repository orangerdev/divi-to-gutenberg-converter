<?php
/**
 * Abstract base class for shortcode converters.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

abstract class DTG_Converter_Base {

	/**
	 * Reference to the Gutenberg builder (for recursive child conversion).
	 *
	 * @var DTG_Gutenberg_Builder|null
	 */
	protected $builder;

	/**
	 * Set the builder reference.
	 *
	 * @param DTG_Gutenberg_Builder $builder Builder instance.
	 */
	public function set_builder( $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Check if this converter can handle the given tag.
	 *
	 * @param string $tag Shortcode tag.
	 * @return bool
	 */
	abstract public function can_convert( $tag );

	/**
	 * Convert an AST node to Gutenberg block markup.
	 *
	 * @param array $node AST node.
	 * @return string Gutenberg block markup.
	 */
	abstract public function convert( $node );

	/**
	 * Convert children nodes to Gutenberg markup via builder.
	 *
	 * @param array $children Array of child AST nodes.
	 * @return string Combined Gutenberg markup.
	 */
	protected function convert_children( $children ) {
		if ( empty( $children ) || ! $this->builder ) {
			return '';
		}
		return $this->builder->build_from_nodes( $children );
	}

	/**
	 * Get attribute value with default.
	 *
	 * @param array  $attrs   Attributes array.
	 * @param string $key     Attribute key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_attr( $attrs, $key, $default = '' ) {
		return isset( $attrs[ $key ] ) ? $attrs[ $key ] : $default;
	}

	/**
	 * Parse WPBakery link attribute format.
	 *
	 * Format: url:https%3A%2F%2Fexample.com|title:Click%20Here|target:_blank|rel:nofollow
	 *
	 * @param string $link_string Raw link attribute value.
	 * @return array Parsed link parts (url, title, target, rel).
	 */
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

	/**
	 * Convert WPBakery column width string to percentage.
	 *
	 * @param string $width Width string (e.g., '1/2', '1/3', '2/3').
	 * @return string Percentage string (e.g., '50%').
	 */
	protected function width_to_percentage( $width ) {
		$map = [
			'1/1'  => '100%',
			'1/2'  => '50%',
			'1/3'  => '33.33%',
			'2/3'  => '66.66%',
			'1/4'  => '25%',
			'3/4'  => '75%',
			'1/6'  => '16.66%',
			'5/6'  => '83.33%',
			'1/12' => '8.33%',
			'5/12' => '41.66%',
			'7/12' => '58.33%',
			'11/12' => '91.66%',
		];

		if ( isset( $map[ $width ] ) ) {
			return $map[ $width ];
		}

		// Try to calculate from fraction.
		if ( strpos( $width, '/' ) !== false ) {
			$parts = explode( '/', $width );
			if ( count( $parts ) === 2 && intval( $parts[1] ) > 0 ) {
				$percentage = round( ( intval( $parts[0] ) / intval( $parts[1] ) ) * 100, 2 );
				return $percentage . '%';
			}
		}

		return '';
	}

	/**
	 * Escape text for use inside Gutenberg block HTML.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	protected function esc_block_text( $text ) {
		return wp_kses_post( $text );
	}

	/**
	 * Encode JSON attributes for Gutenberg block comment.
	 *
	 * @param array $attrs Attributes to encode.
	 * @return string JSON string or empty.
	 */
	protected function json_attrs( $attrs ) {
		// Remove empty values.
		$attrs = array_filter( $attrs, function( $v ) {
			return '' !== $v && null !== $v;
		});

		if ( empty( $attrs ) ) {
			return '';
		}

		return ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
