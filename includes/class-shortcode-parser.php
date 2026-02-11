<?php
/**
 * Recursive shortcode parser that produces an AST.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Shortcode_Parser {

	/**
	 * All known shortcode tags (vc_* and mk_*).
	 *
	 * @var array
	 */
	private $known_tags = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->known_tags = $this->get_known_tags();
	}

	/**
	 * Parse post content into an AST.
	 *
	 * Each node is an associative array:
	 *   'type'     => 'shortcode' | 'text'
	 *   'tag'      => shortcode tag (only for type=shortcode)
	 *   'attrs'    => parsed attributes array
	 *   'content'  => raw inner content string
	 *   'children' => array of child nodes (recursive)
	 *   'raw'      => original full shortcode string (for Tier 2 fallback)
	 *
	 * @param string $content The post content containing shortcodes.
	 * @return array Array of AST nodes.
	 */
	public function parse( $content ) {
		if ( empty( $content ) ) {
			return [];
		}

		$nodes   = [];
		$pattern = $this->get_shortcode_regex();

		// Split content by shortcodes, keeping track of positions.
		$offset = 0;

		while ( preg_match( '/' . $pattern . '/s', $content, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$full_match     = $match[0][0];
			$match_position = $match[0][1];

			// Capture text before this shortcode.
			if ( $match_position > $offset ) {
				$text_before = substr( $content, $offset, $match_position - $offset );
				$trimmed     = trim( $text_before );
				if ( '' !== $trimmed ) {
					$nodes[] = [
						'type'    => 'text',
						'content' => $text_before,
					];
				}
			}

			// Parse the shortcode match.
			// Match groups from get_shortcode_regex():
			// 1 - Extra opening bracket for escaping.
			// 2 - Shortcode tag name.
			// 3 - Attributes string.
			// 4 - Self-closing slash.
			// 5 - Inner content (between opening and closing tags).
			// 6 - Extra closing bracket for escaping.
			$tag           = $match[2][0];
			$attrs_string  = isset( $match[3][0] ) ? $match[3][0] : '';
			$inner_content = isset( $match[5][0] ) ? $match[5][0] : '';
			$self_closing  = ! empty( $match[4][0] );

			$attrs = $this->parse_attributes( $attrs_string );

			// Recursively parse inner content for child shortcodes.
			$children = [];
			if ( ! $self_closing && '' !== $inner_content ) {
				if ( $this->contains_shortcode( $inner_content ) ) {
					$children = $this->parse( $inner_content );
				} else {
					// Inner content is plain text/HTML, no child shortcodes.
					$trimmed_inner = trim( $inner_content );
					if ( '' !== $trimmed_inner ) {
						$children = [
							[
								'type'    => 'text',
								'content' => $inner_content,
							],
						];
					}
				}
			}

			$nodes[] = [
				'type'     => 'shortcode',
				'tag'      => $tag,
				'attrs'    => $attrs,
				'content'  => $inner_content,
				'children' => $children,
				'raw'      => $full_match,
			];

			$offset = $match_position + strlen( $full_match );
		}

		// Capture trailing text after last shortcode.
		if ( $offset < strlen( $content ) ) {
			$trailing = substr( $content, $offset );
			$trimmed  = trim( $trailing );
			if ( '' !== $trimmed ) {
				$nodes[] = [
					'type'    => 'text',
					'content' => $trailing,
				];
			}
		}

		return $nodes;
	}

	/**
	 * Check if content contains any known shortcodes.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	public function contains_shortcode( $content ) {
		if ( false === strpos( $content, '[' ) ) {
			return false;
		}

		foreach ( $this->known_tags as $tag ) {
			if ( false !== strpos( $content, '[' . $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse shortcode attributes string into an associative array.
	 *
	 * @param string $attrs_string Raw attributes string from shortcode.
	 * @return array Parsed attributes.
	 */
	private function parse_attributes( $attrs_string ) {
		if ( empty( trim( $attrs_string ) ) ) {
			return [];
		}

		$attrs = shortcode_parse_atts( $attrs_string );

		if ( ! is_array( $attrs ) ) {
			return [];
		}

		return $attrs;
	}

	/**
	 * Build regex pattern that matches all known shortcode tags.
	 *
	 * Based on WordPress get_shortcode_regex() but with our known tags.
	 *
	 * @return string Regex pattern (without delimiters).
	 */
	private function get_shortcode_regex() {
		$tagnames = $this->known_tags;
		$tagregexp = implode( '|', array_map( 'preg_quote', $tagnames ) );

		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound
		return '\\['                              // Opening bracket.
			. '(\\[?)'                            // 1: Optional second bracket for escaping.
			. "($tagregexp)"                      // 2: Shortcode name.
			. '(?![\\w-])'                        // Not followed by word character or hyphen.
			. '('                                 // 3: Unroll the loop: Inside the opening shortcode tag.
			.     '[^\\]\\/]*'                    //     Not a closing bracket or forward slash.
			.     '(?:'
			.         '\\/(?!\\])'                //     A forward slash not followed by a closing bracket.
			.         '[^\\]\\/]*'                //     Not a closing bracket or forward slash.
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\\/)'                         // 4: Self closing tag ...
			.     '\\]'                           //     ... and target closing bracket.
			. '|'
			.     '\\]'                           // Closing bracket.
			.     '(?:'
			.         '('                         // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags.
			.             '[^\\[]*+'              //     Not an opening bracket.
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])'  //     An opening bracket not followed by the closing shortcode tag.
			.                 '[^\\[]*+'          //     Not an opening bracket.
			.             ')*+'
			.         ')'
			.         '\\[\\/\\2\\]'              // Closing shortcode tag.
			.     ')?'
			. ')'
			. '(\\]?)';                           // 6: Optional second closing bracket for escaping.
		// phpcs:enable
	}

	/**
	 * Get all known vc_* and mk_* shortcode tags.
	 *
	 * @return array
	 */
	private function get_known_tags() {
		return [
			// WPBakery layout.
			'vc_row',
			'vc_row_inner',
			'vc_column',
			'vc_column_inner',
			'vc_section',

			// WPBakery content.
			'vc_column_text',
			'vc_icon',
			'vc_separator',
			'vc_zigzag',
			'vc_text_separator',
			'vc_message',
			'vc_hoverbox',
			'vc_copyright',
			'vc_toggle',
			'vc_single_image',
			'vc_gallery',
			'vc_images_carousel',
			'vc_custom_heading',
			'vc_btn',
			'vc_cta',
			'vc_pricing_table',
			'vc_video',
			'vc_goo_maps',
			'vc_raw_html',
			'vc_raw_js',
			'vc_flickr',
			'vc_progress_bar',
			'vc_pie',
			'vc_round_chart',
			'vc_line_chart',
			'vc_empty_space',
			'vc_posts_slider',

			// WPBakery TTA.
			'vc_tta_tabs',
			'vc_tta_tour',
			'vc_tta_accordion',
			'vc_tta_pageable',
			'vc_tta_toggle',
			'vc_tta_section',
			'vc_tta_toggle_section',

			// WPBakery grids.
			'vc_basic_grid',
			'vc_media_grid',
			'vc_masonry_grid',
			'vc_masonry_media_grid',

			// WPBakery social.
			'vc_facebook',
			'vc_tweetmeme',
			'vc_googleplus',
			'vc_pinterest',

			// WPBakery WP widgets.
			'vc_wp_search',
			'vc_wp_meta',
			'vc_wp_recentcomments',
			'vc_wp_calendar',
			'vc_wp_pages',
			'vc_wp_tagcloud',
			'vc_wp_custommenu',
			'vc_wp_text',
			'vc_wp_posts',
			'vc_wp_links',
			'vc_wp_categories',
			'vc_wp_archives',
			'vc_wp_rss',

			// WPBakery structure.
			'vc_widget_sidebar',

			// WPBakery deprecated.
			'vc_tabs',
			'vc_tour',
			'vc_tab',
			'vc_accordion',
			'vc_accordion_tab',
			'vc_button',
			'vc_button2',
			'vc_cta_button',
			'vc_cta_button2',
			'vc_gmaps',

			// Jupiter Donut (mk_*).
			'mk_advanced_gmaps',
			'mk_animated_columns',
			'mk_audio',
			'mk_banner_builder',
			'mk_blockquote',
			'mk_blog',
			'mk_blog_carousel',
			'mk_blog_showcase',
			'mk_blog_teaser',
			'mk_button',
			'mk_button_gradient',
			'mk_category',
			'mk_chart',
			'mk_circle_image',
			'mk_clients',
			'mk_contact_form',
			'mk_contact_info',
			'mk_content_box',
			'mk_countdown',
			'mk_custom_box',
			'mk_custom_list',
			'mk_custom_sidebar',
			'mk_divider',
			'mk_dropcaps',
			'mk_edge_one_pager',
			'mk_edge_slider',
			'mk_employees',
			'mk_fancy_title',
			'mk_faq',
			'mk_flexslider',
			'mk_flickr',
			'mk_flipbox',
			'mk_font_icons',
			'mk_fullwidth_slideshow',
			'mk_gallery',
			'mk_highlight',
			'mk_icon_box',
			'mk_icon_box_gradient',
			'mk_icon_box2',
			'mk_image',
			'mk_image_slideshow',
			'mk_image_switch',
			'mk_imagebox',
			'mk_imagebox_item',
			'mk_laptop_slideshow',
			'mk_layerslider',
			'mk_lcd_slideshow',
			'mk_message_box',
			'mk_milestone',
			'mk_mini_callout',
			'mk_moving_image',
			'mk_news',
			'mk_news_tab',
			'mk_ornamental_title',
			'mk_padding_divider',
			'mk_page_section',
			'mk_page_title_box',
			'mk_photo_album',
			'mk_photo_roller',
			'mk_portfolio',
			'mk_portfolio_carousel',
			'mk_pricing_table',
			'mk_pricing_table_2',
			'mk_revslider',
			'mk_skill_meter',
			'mk_skill_meter_chart',
			'mk_slideshow_box',
			'mk_social_networks',
			'mk_steps',
			'mk_subscribe',
			'mk_swipe_slideshow',
			'mk_tab_slider',
			'mk_table',
			'mk_testimonials',
			'mk_theatre_slider',
			'mk_title_box',
			'mk_toggle',
			'mk_tooltip',
			'mk_woocommerce_recent_carousel',
		];
	}

	/**
	 * Get the list of known tags (public accessor).
	 *
	 * @return array
	 */
	public function get_tags() {
		return $this->known_tags;
	}
}
