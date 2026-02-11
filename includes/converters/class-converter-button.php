<?php
/**
 * Converter for button shortcodes: vc_btn, mk_button, mk_button_gradient.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Button extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_btn',
		'vc_button',
		'vc_button2',
		'mk_button',
		'mk_button_gradient',
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

	/**
	 * Convert vc_btn to wp:buttons > wp:button.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_vc_btn( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$title     = $this->get_attr( $attrs, 'title', 'Button' );
		$link      = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );
		$align     = $this->get_attr( $attrs, 'align', '' );

		return $this->build_button_block( $title, $link_data, $align );
	}

	/**
	 * Convert legacy vc_button / vc_button2 to wp:buttons.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
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

	/**
	 * Convert mk_button / mk_button_gradient to wp:buttons > wp:button.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_mk_button( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$text   = isset( $node['content'] ) ? trim( strip_tags( $node['content'] ) ) : '';
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

		return $this->build_button_block( $text, $link_data, $align );
	}

	/**
	 * Build wp:buttons > wp:button block markup.
	 *
	 * @param string $text      Button text.
	 * @param array  $link_data Link data (url, title, target, rel).
	 * @param string $align     Alignment (left, center, right).
	 * @return string
	 */
	private function build_button_block( $text, $link_data = [], $align = '' ) {
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
				'type'            => 'flex',
				'justifyContent'  => $layout_map[ $align ],
			];
		}

		// Build inner button.
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

		$href_attr = '';
		if ( ! empty( $link_data['url'] ) ) {
			$target_attr = ( '_blank' === ( $link_data['target'] ?? '' ) ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$href_attr   = ' href="' . esc_url( $link_data['url'] ) . '"' . $target_attr;
		}

		$output  = '<!-- wp:buttons' . $this->json_attrs( $buttons_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-buttons">';

		$output .= '<!-- wp:button' . $this->json_attrs( $button_attrs ) . ' -->' . "\n";
		$output .= '<div class="wp-block-button">';
		$output .= '<a class="wp-block-button__link wp-element-button"' . $href_attr . '>' . $text . '</a>';
		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:button -->';

		$output .= '</div>' . "\n";
		$output .= '<!-- /wp:buttons -->' . "\n\n";

		return $output;
	}
}
