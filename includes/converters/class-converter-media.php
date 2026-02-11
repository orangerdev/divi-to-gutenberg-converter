<?php
/**
 * Converter for media shortcodes: vc_single_image, mk_image, vc_video.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Media extends DTG_Converter_Base {

	/**
	 * Tags handled by this converter.
	 *
	 * @var array
	 */
	private $tags = [
		'vc_single_image',
		'mk_image',
		'vc_video',
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

	/**
	 * Convert vc_single_image to wp:image.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_vc_image( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$image_id  = $this->get_attr( $attrs, 'image', '' );
		$img_size  = $this->get_attr( $attrs, 'img_size', 'full' );
		$alignment = $this->get_attr( $attrs, 'alignment', '' );
		$caption   = $this->get_attr( $attrs, 'caption', '' );

		// External image URL.
		$source       = $this->get_attr( $attrs, 'source', 'media_library' );
		$external_url = $this->get_attr( $attrs, 'external_img_url', '' );

		if ( 'external_link' === $source && $external_url ) {
			return $this->build_image_block( 0, $external_url, '', $alignment, $caption );
		}

		if ( ! $image_id ) {
			return '';
		}

		$image_url = wp_get_attachment_url( (int) $image_id );
		$image_alt = get_post_meta( (int) $image_id, '_wp_attachment_image_alt', true );

		if ( ! $image_url ) {
			return '';
		}

		// Link.
		$onclick   = $this->get_attr( $attrs, 'onclick', '' );
		$link      = $this->get_attr( $attrs, 'link', '' );
		$link_data = $this->parse_vc_link( $link );

		return $this->build_image_block( (int) $image_id, $image_url, $image_alt, $alignment, $caption, $link_data );
	}

	/**
	 * Convert mk_image to wp:image.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_mk_image( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];

		$image_id    = $this->get_attr( $attrs, 'src', '' );
		$image_url   = $this->get_attr( $attrs, 'image_url', '' );
		$alignment   = $this->get_attr( $attrs, 'align', '' );
		$caption     = $this->get_attr( $attrs, 'caption', '' );
		$link        = $this->get_attr( $attrs, 'link', '' );

		// If src is a numeric ID.
		if ( is_numeric( $image_id ) ) {
			$url = wp_get_attachment_url( (int) $image_id );
			$alt = get_post_meta( (int) $image_id, '_wp_attachment_image_alt', true );

			if ( ! $url ) {
				return '';
			}

			$link_data = [ 'url' => $link, 'title' => '', 'target' => '', 'rel' => '' ];
			return $this->build_image_block( (int) $image_id, $url, $alt, $alignment, $caption, $link_data );
		}

		// If src is a URL string.
		if ( $image_url ) {
			return $this->build_image_block( 0, $image_url, '', $alignment, $caption );
		}

		return '';
	}

	/**
	 * Build wp:image block markup.
	 *
	 * @param int    $id        Attachment ID (0 for external).
	 * @param string $url       Image URL.
	 * @param string $alt       Alt text.
	 * @param string $alignment Alignment (left, center, right).
	 * @param string $caption   Caption text.
	 * @param array  $link_data Optional link data.
	 * @return string
	 */
	private function build_image_block( $id, $url, $alt = '', $alignment = '', $caption = '', $link_data = [] ) {
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

		$img_tag = '<img src="' . esc_url( $url ) . '"';
		if ( $alt ) {
			$img_tag .= ' alt="' . esc_attr( $alt ) . '"';
		}
		if ( $id ) {
			$img_tag .= ' class="wp-image-' . $id . '"';
		}
		$img_tag .= '/>';

		// Wrap in link if provided.
		if ( ! empty( $link_data['url'] ) ) {
			$target   = ! empty( $link_data['target'] ) ? ' target="' . esc_attr( $link_data['target'] ) . '"' : '';
			$rel      = ! empty( $link_data['rel'] ) ? ' rel="' . esc_attr( $link_data['rel'] ) . '"' : '';
			$img_tag  = '<a href="' . esc_url( $link_data['url'] ) . '"' . $target . $rel . '>' . $img_tag . '</a>';
		}

		$output  = '<!-- wp:image' . $this->json_attrs( $block_attrs ) . ' -->' . "\n";
		$output .= '<figure class="wp-block-image size-full">' . $img_tag;

		if ( $caption ) {
			$output .= '<figcaption class="wp-element-caption">' . $this->esc_block_text( $caption ) . '</figcaption>';
		}

		$output .= '</figure>' . "\n";
		$output .= '<!-- /wp:image -->' . "\n\n";

		return $output;
	}

	/**
	 * Convert vc_video to wp:embed.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function convert_video( $node ) {
		$attrs = isset( $node['attrs'] ) ? $node['attrs'] : [];
		$link  = $this->get_attr( $attrs, 'link', '' );

		if ( empty( $link ) ) {
			return '';
		}

		// Determine provider.
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
