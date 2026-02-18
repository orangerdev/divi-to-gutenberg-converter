<?php
/**
 * Converter for media shortcodes: vc_single_image, mk_image, vc_video.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Media extends DTG_Converter_Base {

	private $tags = [
		'vc_single_image',
		'mk_image',
		'vc_video',
	];

	public function can_convert( $tag ) {
		return in_array( $tag, $this->tags, true );
	}

	public function convert( $node ) {
		switch ( $node['tag'] ) {
			case 'vc_single_image':
				return $this->convert_vc_image( $node );

			case 'mk_image':
				return $this->convert_mk_image( $node );

			case 'vc_video':
				return $this->convert_video( $node );

			default:
				return '';
		}
	}

	private function convert_vc_image( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$image_id  = $this->get_attr( $attrs, 'image', '' );
		$img_size  = $this->get_attr( $attrs, 'img_size', 'full' );
		$alignment = $this->get_attr( $attrs, 'alignment', '' );
		$caption   = $this->get_attr( $attrs, 'caption', '' );
		$css_attr  = $this->get_attr( $attrs, 'css', '' );

		$source       = $this->get_attr( $attrs, 'source', 'media_library' );
		$external_url = $this->get_attr( $attrs, 'external_img_url', '' );

		// Parse CSS.
		$vc_css    = $this->parse_vc_css( $css_attr );
		$css_class = '';
		if ( ! empty( $vc_css ) ) {
			$css_class = $this->next_class();
			$this->add_css( $css_class, $vc_css );
		}

		if ( 'external_link' === $source && $external_url ) {
			return $this->build_image_block( 0, $external_url, '', $alignment, $caption, [], $css_class );
		}

		if ( ! $image_id ) {
			return '';
		}

		$image_url = wp_get_attachment_url( (int) $image_id );
		$image_alt = get_post_meta( (int) $image_id, '_wp_attachment_image_alt', true );

		if ( ! $image_url ) {
			return '';
		}

		$onclick   = $this->get_attr( $attrs, 'onclick', '' );
		$link      = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );

		return $this->build_image_block( (int) $image_id, $image_url, $image_alt, $alignment, $caption, $link_data, $css_class );
	}

	private function convert_mk_image( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$image_src   = $this->get_attr( $attrs, 'src', '' );
		$alignment   = $this->get_attr( $attrs, 'align', '' );
		$caption     = $this->get_attr( $attrs, 'caption', '' );
		$link        = $this->get_attr( $attrs, 'link', '' );

		if ( is_numeric( $image_src ) ) {
			$url = wp_get_attachment_url( (int) $image_src );
			$alt = get_post_meta( (int) $image_src, '_wp_attachment_image_alt', true );

			if ( ! $url ) {
				return '';
			}

			$link_data = [ 'url' => $link, 'title' => '', 'target' => '', 'rel' => '' ];
			return $this->build_image_block( (int) $image_src, $url, $alt, $alignment, $caption, $link_data );
		}

		// If src is a URL string.
		if ( $image_src && ! is_numeric( $image_src ) ) {
			return $this->build_image_block( 0, $image_src, '', $alignment, $caption );
		}

		$image_url = $this->get_attr( $attrs, 'image_url', '' );
		if ( $image_url ) {
			return $this->build_image_block( 0, $image_url, '', $alignment, $caption );
		}

		return '';
	}

	private function build_image_block( $id, $url, $alt = '', $alignment = '', $caption = '', $link_data = [], $css_class = '' ) {
		$block_attrs = [];

		if ( $id ) {
			$block_attrs['id'] = $id;
		}

		$align_map = [
			'left'   => 'left',
			'center' => 'center',
			'right'  => 'right',
		];

		if ( $alignment && isset( $align_map[ $alignment ] ) ) {
			$block_attrs['align'] = $align_map[ $alignment ];
		}

		if ( $css_class ) {
			$block_attrs['className'] = $css_class;
		}

		$img_tag = '<img src="' . esc_url( $url ) . '"';
		if ( $alt ) {
			$img_tag .= ' alt="' . esc_attr( $alt ) . '"';
		}
		if ( $id ) {
			$img_tag .= ' class="wp-image-' . $id . '"';
		}
		$img_tag .= '/>';

		if ( ! empty( $link_data['url'] ) ) {
			$target   = ! empty( $link_data['target'] ) ? ' target="' . esc_attr( $link_data['target'] ) . '"' : '';
			$rel      = ! empty( $link_data['rel'] ) ? ' rel="' . esc_attr( $link_data['rel'] ) . '"' : '';
			$img_tag  = '<a href="' . esc_url( $link_data['url'] ) . '"' . $target . $rel . '>' . $img_tag . '</a>';
		}

		$figure_class = 'wp-block-image size-full';
		if ( $css_class ) {
			$figure_class .= ' ' . esc_attr( $css_class );
		}

		$output  = '<!-- wp:image' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<figure class="' . $figure_class . '">' . $img_tag;

		if ( $caption ) {
			$output .= '<figcaption class="wp-element-caption">' . $this->esc_block_text( $caption ) . '</figcaption>';
		}

		$output .= '</figure>' . "\n";
		$output .= '<!-- /wp:image -->' . "\n\n";

		return $output;
	}

	private function convert_video( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$link  = $this->get_attr( $attrs, 'link', '' );

		if ( empty( $link ) ) {
			return '';
		}

		$provider_slug = 'youtube';
		if ( false !== strpos( $link, 'vimeo.com' ) ) {
			$provider_slug = 'vimeo';
		}

		$block_attrs = [
			'url'              => $link,
			'type'             => 'video',
			'providerNameSlug' => $provider_slug,
			'responsive'       => true,
		];

		$output  = '<!-- wp:embed' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider_slug ) . ' wp-block-embed-' . esc_attr( $provider_slug ) . '">';
		$output .= '<div class="wp-block-embed__wrapper">' . "\n";
		$output .= esc_url( $link ) . "\n";
		$output .= '</div>';
		$output .= '</figure>' . "\n";
		$output .= '<!-- /wp:embed -->' . "\n\n";

		return $output;
	}
}
