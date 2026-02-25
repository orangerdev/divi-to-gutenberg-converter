<?php
/**
 * Classifies AST nodes into native/capture/dynamic tiers
 * for hybrid conversion.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Shortcode_Classifier {

	/**
	 * Tier 1: Simple elements → convert to native Gutenberg blocks.
	 */
	const NATIVE_TAGS = [
		'vc_custom_heading',
		'vc_column_text',
		'vc_single_image',
		'mk_image',
		'vc_separator',
		'vc_text_separator',
		'vc_empty_space',
		'mk_padding_divider',
		'mk_divider',
		'mk_fancy_title',
		'mk_ornamental_title',
		'mk_title_box',
		'mk_blockquote',
		'mk_highlight',
		'mk_dropcaps',
		'vc_video',
		'vc_raw_html',
		'vc_raw_js',
		'vc_copyright',
	];

	/**
	 * Tier 2: Complex visual elements → render capture to HTML.
	 */
	const CAPTURE_TAGS = [
		'mk_page_section',
		'mk_custom_box',
		'mk_header',
		'mk_testimonials',
		'mk_custom_list',
		'mk_blog',
		'mk_blog_carousel',
		'mk_blog_showcase',
		'mk_blog_teaser',
		'mk_clients',
		'mk_contact_form',
		'mk_contact_info',
		'mk_gallery',
		'mk_icon_box',
		'mk_icon_box_gradient',
		'mk_icon_box2',
		'mk_milestone',
		'mk_portfolio',
		'mk_portfolio_carousel',
		'mk_pricing_table',
		'mk_pricing_table_2',
		'mk_skill_meter',
		'mk_skill_meter_chart',
		'mk_steps',
		'mk_toggle',
		'mk_employees',
		'mk_edge_slider',
		'mk_flexslider',
		'mk_image_slideshow',
		'mk_swipe_slideshow',
		'mk_lcd_slideshow',
		'mk_laptop_slideshow',
		'mk_theatre_slider',
		'mk_fullwidth_slideshow',
		'mk_flipbox',
		'mk_tab_slider',
		'mk_photo_album',
		'mk_photo_roller',
		'mk_countdown',
		'mk_chart',
		'mk_circle_image',
		'mk_moving_image',
		'mk_image_switch',
		'mk_faq',
		'mk_subscribe',
		'mk_social_networks',
		'mk_font_icons',
		'mk_banner_builder',
		'mk_mini_callout',
		'mk_imagebox',
		'mk_imagebox_item',
		'mk_content_box',
		'mk_message_box',
		'mk_news',
		'mk_news_tab',
		'mk_category',
		'mk_custom_sidebar',
		'mk_table',
		'mk_woocommerce_recent_carousel',
		'mk_revslider',
		'mk_layerslider',
		'mk_edge_one_pager',
		'mk_advanced_gmaps',
		'mk_animated_columns',
		'mk_audio',
		'mk_slideshow_box',
		'mk_tooltip',
		'mk_page_title_box',
		'vc_tta_tabs',
		'vc_tta_tour',
		'vc_tta_accordion',
		'vc_tta_pageable',
		'vc_tta_toggle',
		'vc_tabs',
		'vc_tab',
		'vc_accordion',
		'vc_accordion_tab',
		'vc_gallery',
		'vc_images_carousel',
		'vc_basic_grid',
		'vc_media_grid',
		'vc_masonry_grid',
		'vc_masonry_media_grid',
		'vc_goo_maps',
		'vc_gmaps',
		'vc_flickr',
		'vc_progress_bar',
		'vc_pie',
		'vc_round_chart',
		'vc_line_chart',
		'vc_pricing_table',
		'vc_hoverbox',
		'vc_posts_slider',
	];

	/**
	 * Layout tags whose classification depends on children and attributes.
	 */
	const LAYOUT_TAGS = [
		'vc_row',
		'vc_row_inner',
		'vc_column',
		'vc_column_inner',
		'vc_section',
	];

	/**
	 * Classify an AST node.
	 *
	 * @param array $node AST node from DTG_Shortcode_Parser.
	 * @return string 'native' | 'capture' | 'dynamic'
	 */
	public function classify( $node ) {
		if ( 'shortcode' !== ( $node['type'] ?? '' ) ) {
			return 'native';
		}

		$tag = $node['tag'] ?? '';

		// 1. Always-capture tags.
		if ( in_array( $tag, self::CAPTURE_TAGS, true ) ) {
			return 'capture';
		}

		// 2. mk_button: dynamic if product_id or popup trigger, else native.
		if ( 'mk_button' === $tag || 'mk_button_gradient' === $tag ) {
			return $this->classify_button( $node );
		}

		// 3. Always-native tags.
		if ( in_array( $tag, self::NATIVE_TAGS, true ) ) {
			return $this->classify_native_with_popup_check( $node );
		}

		// 4. Layout tags: classification depends on children + attributes.
		if ( in_array( $tag, self::LAYOUT_TAGS, true ) ) {
			return $this->classify_layout( $node );
		}

		// 5. Unknown tags: capture as fallback (safe).
		return 'capture';
	}

	/**
	 * Classify mk_button / mk_button_gradient.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function classify_button( $node ) {
		$attrs      = $node['attrs'] ?? [];
		$product_id = $attrs['product_id'] ?? '';
		$el_class   = $attrs['el_class'] ?? '';

		// Buttons with WooCommerce product_id need render capture.
		if ( ! empty( $product_id ) ) {
			return 'dynamic';
		}

		// Buttons that trigger popups need render capture.
		if ( preg_match( '/popmake-\d+/', $el_class ) ) {
			return 'dynamic';
		}

		// Buttons with JS-driven el_class (WooCommerce bundle triggers).
		$woo_classes = [
			'emeraldwithinkoption',
			'nxtemeraldwithinkoption',
			'advancedbundle',
			'elitebundle',
			'masterbundle',
		];
		foreach ( $woo_classes as $woo_class ) {
			if ( false !== strpos( $el_class, $woo_class ) ) {
				return 'dynamic';
			}
		}

		return 'native';
	}

	/**
	 * Check if a nominally-native tag has popup trigger class.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function classify_native_with_popup_check( $node ) {
		$el_class = $node['attrs']['el_class'] ?? '';

		if ( preg_match( '/popmake-\d+/', $el_class ) ) {
			return 'dynamic';
		}

		return 'native';
	}

	/**
	 * Classify a layout container (vc_row, vc_column, etc.).
	 *
	 * If the layout has complex attributes OR any descendant requires
	 * capture/dynamic, the entire subtree gets captured.
	 *
	 * @param array $node AST node.
	 * @return string
	 */
	private function classify_layout( $node ) {
		// Check if this layout node has complex attributes.
		if ( $this->has_complex_layout_attrs( $node ) ) {
			return 'capture';
		}

		// Check all descendants recursively.
		if ( $this->has_capture_descendants( $node ) ) {
			return 'capture';
		}

		return 'native';
	}

	/**
	 * Check if a layout node has attributes too complex for Gutenberg native.
	 *
	 * @param array $node AST node.
	 * @return bool
	 */
	private function has_complex_layout_attrs( $node ) {
		$attrs = $node['attrs'] ?? [];

		// Background image on row.
		if ( ! empty( $attrs['bg_image'] ) ) {
			return true;
		}

		// WPBakery fullwidth hack with extreme margins (>100px).
		$css_attr = $attrs['css'] ?? '';
		if ( $css_attr && preg_match( '/margin-(?:left|right)\s*:\s*(\d+)px/', $css_attr, $m ) ) {
			if ( (int) $m[1] > 100 ) {
				return true;
			}
		}

		// Background color on row — this is common and important for visual fidelity.
		if ( $css_attr && preg_match( '/background-color\s*:\s*(?!transparent)/', $css_attr ) ) {
			$tag = $node['tag'] ?? '';
			// Only flag top-level rows with background, not inner containers.
			if ( 'vc_row' === $tag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check if any descendant requires capture or dynamic treatment.
	 *
	 * @param array $node AST node.
	 * @return bool
	 */
	private function has_capture_descendants( $node ) {
		$children = $node['children'] ?? [];

		foreach ( $children as $child ) {
			if ( 'shortcode' !== ( $child['type'] ?? '' ) ) {
				continue;
			}

			$classification = $this->classify( $child );
			if ( 'capture' === $classification || 'dynamic' === $classification ) {
				return true;
			}
		}

		return false;
	}
}
