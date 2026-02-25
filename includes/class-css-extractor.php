<?php
/**
 * CSS Extractor: discovers, extracts, and bundles CSS from
 * WPBakery/JupiterDonut/Jupiter theme stylesheets.
 *
 * Ensures styles survive plugin/theme deactivation by bundling
 * the full CSS from source plugins/theme into a standalone file.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_CSS_Extractor {

	/**
	 * CSS class names found in rendered HTML.
	 *
	 * @var array
	 */
	private $captured_classes = [];

	/**
	 * Source stylesheet file paths to scan.
	 *
	 * @var array handle => file_path
	 */
	private $source_stylesheets = [];

	/**
	 * Dynamic CSS collected during rendering (inline styles, post meta CSS).
	 *
	 * @var string
	 */
	private $dynamic_css = '';

	/**
	 * Extracted CSS output.
	 *
	 * @var string
	 */
	private $extracted_css = '';

	/**
	 * Upload sub-directory name (same as batch processor).
	 */
	const UPLOAD_DIR = 'dtg-converter';

	/**
	 * Sub-directory for copied font/asset files.
	 */
	const ASSETS_DIR = 'dtg-converter/assets';

	/**
	 * Font file extensions to copy.
	 */
	const FONT_EXTENSIONS = [ 'woff', 'woff2', 'ttf', 'eot', 'svg' ];

	/**
	 * Discover all CSS source files from plugins and theme.
	 *
	 * Must be called while WPBakery/JupiterDonut are still active.
	 *
	 * @return array Discovered stylesheets (handle => path).
	 */
	public function discover_stylesheets() {
		$this->source_stylesheets = [];

		// 1. JupiterDonut plugin CSS (primary shortcode styles).
		$jd_css_dir = WP_PLUGIN_DIR . '/jupiter-donut/assets/css/';
		$jd_files   = [
			'jd-shortcodes' => [ 'shortcodes-styles.min.css', 'shortcodes-styles.css' ],
			'jd-styles'     => [ 'styles.min.css', 'styles.css' ],
		];
		foreach ( $jd_files as $handle => $candidates ) {
			foreach ( $candidates as $file ) {
				$path = $jd_css_dir . $file;
				if ( file_exists( $path ) ) {
					$this->source_stylesheets[ $handle ] = $path;
					break;
				}
			}
		}

		// 2. Jupiter theme core styles.
		$theme_dir    = get_template_directory();
		$theme_styles = $theme_dir . '/assets/stylesheet/';
		if ( is_dir( $theme_styles ) ) {
			// Find the latest core-styles version.
			$core_files = glob( $theme_styles . 'core-styles.*.css' );
			if ( $core_files ) {
				// Sort descending and take the latest version.
				rsort( $core_files );
				$this->source_stylesheets['jupiter-core'] = $core_files[0];
			}
		}

		// 3. Jupiter theme critical path CSS.
		$critical_css = $theme_dir . '/assets/stylesheet/critical-path.css';
		if ( file_exists( $critical_css ) ) {
			$this->source_stylesheets['jupiter-critical'] = $critical_css;
		}

		// 4. Per-shortcode CSS files in JupiterDonut.
		$sc_css_dir = WP_PLUGIN_DIR . '/jupiter-donut/includes/wpbakery/shortcodes/';
		if ( is_dir( $sc_css_dir ) ) {
			$sc_css_files = glob( $sc_css_dir . '*/vc_front.css' );
			if ( $sc_css_files ) {
				foreach ( $sc_css_files as $file ) {
					$shortcode_name = basename( dirname( $file ) );
					$this->source_stylesheets[ 'jd-sc-' . $shortcode_name ] = $file;
				}
			}
		}

		// 5. WPBakery CSS (Modified Version — js_composer_theme).
		$wpb_dirs = [
			WP_PLUGIN_DIR . '/js_composer_theme/',
			WP_PLUGIN_DIR . '/js_composer/',
		];
		foreach ( $wpb_dirs as $wpb_dir ) {
			if ( is_dir( $wpb_dir ) ) {
				$wpb_files = [
					'wpb-front' => $wpb_dir . 'assets/css/js_composer.min.css',
				];
				foreach ( $wpb_files as $handle => $path ) {
					if ( file_exists( $path ) && ! isset( $this->source_stylesheets[ $handle ] ) ) {
						$this->source_stylesheets[ $handle ] = $path;
					}
				}
				break; // Use whichever directory exists first.
			}
		}

		// 6. Enqueued stylesheets from wp_styles (catches anything we missed).
		global $wp_styles;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( $wp_styles->registered as $handle => $style ) {
				if ( empty( $style->src ) ) {
					continue;
				}

				$file_path = $this->url_to_path( $style->src );
				if ( $file_path && file_exists( $file_path ) && ! in_array( $file_path, $this->source_stylesheets, true ) ) {
					// Only include stylesheets from jupiter/wpbakery/jupiter-donut.
					if ( $this->is_relevant_stylesheet( $file_path ) ) {
						$this->source_stylesheets[ 'enqueued-' . $handle ] = $file_path;
					}
				}
			}
		}

		return $this->source_stylesheets;
	}

	/**
	 * Register CSS class names found in captured HTML.
	 *
	 * @param array $classes Array of class names.
	 */
	public function register_captured_classes( $classes ) {
		$this->captured_classes = array_unique(
			array_merge( $this->captured_classes, $classes )
		);
	}

	/**
	 * Capture dynamic/inline CSS for a specific post.
	 *
	 * Collects WPBakery custom CSS and post custom CSS from post meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function capture_post_dynamic_css( $post_id ) {
		// WPBakery shortcode custom CSS.
		$wpb_css = get_post_meta( $post_id, '_wpb_shortcodes_custom_css', true );
		if ( $wpb_css ) {
			$this->dynamic_css .= "/* WPBakery Shortcode CSS - Post {$post_id} */\n" . $wpb_css . "\n\n";
		}

		// WPBakery post custom CSS (user-authored).
		$wpb_post_css = get_post_meta( $post_id, '_wpb_post_custom_css', true );
		if ( $wpb_post_css ) {
			$this->dynamic_css .= "/* WPBakery Post Custom CSS - Post {$post_id} */\n" . $wpb_post_css . "\n\n";
		}
	}

	/**
	 * Bundle full CSS from all discovered source stylesheets.
	 *
	 * Concatenates entire CSS files (not selective extraction) to ensure
	 * no styles are lost when plugins/theme are deactivated.
	 * Font files referenced in CSS are copied to the uploads directory.
	 *
	 * @return string Full CSS bundle.
	 */
	public function bundle_full_css() {
		if ( empty( $this->source_stylesheets ) && empty( $this->dynamic_css ) ) {
			return '';
		}

		$css_output  = "/* ==========================================================================\n";
		$css_output .= "   DTG Converter - Full CSS Bundle\n";
		$css_output .= "   Bundled from WPBakery, JupiterDonut, and Jupiter theme.\n";
		$css_output .= "   Generated: " . current_time( 'mysql' ) . "\n";
		$css_output .= "   Sources: " . count( $this->source_stylesheets ) . " stylesheets\n";
		$css_output .= "   ========================================================================== */\n\n";

		// Ensure assets directory exists for font copying.
		$this->ensure_assets_dir();

		foreach ( $this->source_stylesheets as $handle => $file_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$css_content = file_get_contents( $file_path );
			if ( empty( $css_content ) ) {
				continue;
			}

			// Rewrite relative URLs (fonts, images) to work from new location.
			$css_content = $this->rewrite_asset_urls( $css_content, $file_path );

			$css_output .= "/* === Source: " . basename( $file_path ) . " ({$handle}) === */\n";
			$css_output .= $css_content . "\n\n";
		}

		// Append dynamic/inline CSS from post meta.
		if ( ! empty( $this->dynamic_css ) ) {
			$css_output .= "/* === Dynamic/Inline CSS === */\n";
			$css_output .= $this->dynamic_css . "\n";
		}

		$this->extracted_css = $css_output;
		return $css_output;
	}

	/**
	 * Rewrite relative URL references in CSS to absolute paths.
	 *
	 * For font files: copies them to the uploads assets directory and
	 * rewrites the URL to point there. For other assets: converts to
	 * absolute URL based on the source file's original location.
	 *
	 * @param string $css         CSS content.
	 * @param string $source_path Absolute path of the source CSS file.
	 * @return string CSS with rewritten URLs.
	 */
	private function rewrite_asset_urls( $css, $source_path ) {
		$source_dir = dirname( $source_path );

		// Match url(...) references — handles quoted and unquoted values.
		return preg_replace_callback(
			'/url\(\s*[\'"]?\s*([^\'")]+?)\s*[\'"]?\s*\)/',
			function ( $matches ) use ( $source_dir ) {
				$url = $matches[1];

				// Skip data URIs and absolute URLs.
				if ( preg_match( '/^(data:|https?:|\/\/)/', $url ) ) {
					return $matches[0];
				}

				// Skip fragment-only references (e.g., url(#svg-id)).
				if ( 0 === strpos( $url, '#' ) ) {
					return $matches[0];
				}

				// Strip query string / fragment for file resolution.
				$clean_url = preg_replace( '/[?#].*$/', '', $url );

				// Resolve relative path to absolute.
				$absolute_path = realpath( $source_dir . '/' . $clean_url );

				if ( ! $absolute_path || ! file_exists( $absolute_path ) ) {
					// Can't resolve — convert to absolute URL based on source dir.
					$absolute_url = $this->path_to_url( $source_dir . '/' . $clean_url );
					if ( $absolute_url ) {
						return 'url("' . $absolute_url . '")';
					}
					return $matches[0];
				}

				// Check if this is a font file that should be copied.
				$extension = strtolower( pathinfo( $absolute_path, PATHINFO_EXTENSION ) );
				if ( in_array( $extension, self::FONT_EXTENSIONS, true ) ) {
					$new_url = $this->copy_asset_to_uploads( $absolute_path );
					if ( $new_url ) {
						return 'url("' . $new_url . '")';
					}
				}

				// For non-font files, convert to absolute URL.
				$absolute_url = $this->path_to_url( $absolute_path );
				if ( $absolute_url ) {
					return 'url("' . $absolute_url . '")';
				}

				return $matches[0];
			},
			$css
		);
	}

	/**
	 * Copy an asset file to the uploads directory and return its URL.
	 *
	 * @param string $source_path Absolute path of the source file.
	 * @return string|false URL of the copied file, or false on failure.
	 */
	private function copy_asset_to_uploads( $source_path ) {
		$assets_dir = $this->get_assets_dir();
		if ( ! $assets_dir ) {
			return false;
		}

		// Preserve a minimal directory structure to avoid name collisions.
		// E.g., /plugins/jupiter-donut/assets/fonts/icon.woff
		//     → /uploads/dtg-converter/assets/jupiter-donut/fonts/icon.woff
		$relative = $this->get_short_relative_path( $source_path );
		$dest_dir = $assets_dir . '/' . dirname( $relative );

		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$dest_path = $assets_dir . '/' . $relative;

		// Only copy if not already there (or source is newer).
		if ( ! file_exists( $dest_path ) || filemtime( $source_path ) > filemtime( $dest_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			if ( ! copy( $source_path, $dest_path ) ) {
				return false;
			}
		}

		// Build URL.
		$upload = wp_upload_dir();
		return $upload['baseurl'] . '/' . self::ASSETS_DIR . '/' . $relative;
	}

	/**
	 * Get a short relative path for an asset file, scoped by plugin/theme name.
	 *
	 * @param string $absolute_path Absolute file path.
	 * @return string Short relative path like "jupiter-donut/fonts/icon.woff".
	 */
	private function get_short_relative_path( $absolute_path ) {
		// Try to extract relative to plugins directory.
		$plugins_dir = WP_PLUGIN_DIR . '/';
		if ( 0 === strpos( $absolute_path, $plugins_dir ) ) {
			return substr( $absolute_path, strlen( $plugins_dir ) );
		}

		// Try relative to themes directory.
		$themes_dir = get_theme_root() . '/';
		if ( 0 === strpos( $absolute_path, $themes_dir ) ) {
			return substr( $absolute_path, strlen( $themes_dir ) );
		}

		// Fallback: just the filename with a hash prefix for uniqueness.
		return md5( dirname( $absolute_path ) ) . '/' . basename( $absolute_path );
	}

	/**
	 * Convert a local file path to a URL.
	 *
	 * @param string $path Absolute file path.
	 * @return string|false URL or false.
	 */
	private function path_to_url( $path ) {
		// Normalize the path.
		$path = wp_normalize_path( $path );

		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 === strpos( $path, $content_dir ) ) {
			$relative = substr( $path, strlen( $content_dir ) );
			return content_url( $relative );
		}

		$abspath = wp_normalize_path( ABSPATH );
		if ( 0 === strpos( $path, $abspath ) ) {
			$relative = substr( $path, strlen( $abspath ) );
			return site_url( '/' . $relative );
		}

		return false;
	}

	/**
	 * Extract CSS rules matching captured class names from all source stylesheets.
	 *
	 * @return string Extracted CSS.
	 */
	public function extract_matching_css() {
		if ( empty( $this->captured_classes ) && empty( $this->dynamic_css ) ) {
			return '';
		}

		$css_output  = "/* ==========================================================================\n";
		$css_output .= "   DTG Converter - Captured Plugin/Theme CSS\n";
		$css_output .= "   Extracted from WPBakery, JupiterDonut, and Jupiter theme.\n";
		$css_output .= "   Generated: " . current_time( 'mysql' ) . "\n";
		$css_output .= "   ========================================================================== */\n\n";

		// Extract from each source stylesheet.
		foreach ( $this->source_stylesheets as $handle => $file_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$css_content = file_get_contents( $file_path );
			if ( empty( $css_content ) ) {
				continue;
			}

			$matched_rules = $this->extract_rules_for_classes( $css_content );
			if ( ! empty( $matched_rules ) ) {
				$css_output .= "/* Source: " . basename( $file_path ) . " ({$handle}) */\n";
				$css_output .= $matched_rules . "\n";
			}
		}

		// Append dynamic CSS.
		if ( ! empty( $this->dynamic_css ) ) {
			$css_output .= "/* Dynamic/Inline CSS */\n";
			$css_output .= $this->dynamic_css . "\n";
		}

		$this->extracted_css = $css_output;
		return $css_output;
	}

	/**
	 * Write the extracted CSS to the bundle file.
	 *
	 * @return array Result with file path and size.
	 */
	public function write_bundle() {
		if ( empty( $this->extracted_css ) ) {
			return [
				'success' => false,
				'message' => 'No CSS to write',
			];
		}

		$upload_dir = $this->get_upload_dir();
		if ( ! $upload_dir ) {
			return [
				'success' => false,
				'message' => 'Failed to create upload directory',
			];
		}

		$file_path = $upload_dir . '/captured-plugin-styles.css';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $file_path, $this->extracted_css );

		if ( false === $written ) {
			return [
				'success' => false,
				'message' => 'Failed to write CSS file',
			];
		}

		// Store version for cache busting.
		update_option( 'dtg_captured_css_version', time() );

		return [
			'success'           => true,
			'file_path'         => $file_path,
			'file_size'         => size_format( strlen( $this->extracted_css ) ),
			'sources_count'     => count( $this->source_stylesheets ),
			'classes_count'     => count( $this->captured_classes ),
		];
	}

	/**
	 * Get URL to the captured plugin styles CSS file.
	 *
	 * @return string|false CSS file URL or false if not generated.
	 */
	public static function get_captured_css_url() {
		$upload = wp_upload_dir();
		$file   = $upload['basedir'] . '/' . self::UPLOAD_DIR . '/captured-plugin-styles.css';

		if ( ! file_exists( $file ) ) {
			return false;
		}

		return $upload['baseurl'] . '/' . self::UPLOAD_DIR . '/captured-plugin-styles.css';
	}

	/**
	 * Parse CSS content and extract rules matching captured class names.
	 *
	 * Handles: regular rules, @media blocks, @keyframes, @font-face.
	 *
	 * @param string $css Raw CSS content.
	 * @return string Matched CSS rules.
	 */
	private function extract_rules_for_classes( $css ) {
		if ( empty( $this->captured_classes ) ) {
			return '';
		}

		// Remove CSS comments.
		$css = preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Build regex pattern to match any captured class name in a selector.
		$escaped_classes = array_map( function ( $c ) {
			return preg_quote( $c, '/' );
		}, $this->captured_classes );

		// Match .classname followed by space, comma, colon, bracket, dot, or end.
		$class_pattern = '\.(' . implode( '|', $escaped_classes ) . ')(?=[\s,:>\+\~\.\[{)\]]|$)';

		$matched = '';
		$keyframes_to_include = [];

		// Tokenize and process.
		$tokens = $this->tokenize_css( $css );

		foreach ( $tokens as $token ) {
			switch ( $token['type'] ) {
				case 'rule':
					if ( preg_match( '/' . $class_pattern . '/', $token['selector'] ) ) {
						$matched .= $token['selector'] . ' {' . "\n";
						$matched .= '  ' . $token['declarations'] . "\n";
						$matched .= '}' . "\n";

						// Check for animation-name references.
						if ( preg_match( '/animation(?:-name)?\s*:\s*([a-zA-Z_][\w-]*)/', $token['declarations'], $anim_m ) ) {
							$keyframes_to_include[] = $anim_m[1];
						}
					}
					break;

				case 'media':
					$inner_matched = '';
					foreach ( $token['rules'] as $inner_rule ) {
						if ( preg_match( '/' . $class_pattern . '/', $inner_rule['selector'] ) ) {
							$inner_matched .= '  ' . $inner_rule['selector'] . ' {' . "\n";
							$inner_matched .= '    ' . $inner_rule['declarations'] . "\n";
							$inner_matched .= '  }' . "\n";

							if ( preg_match( '/animation(?:-name)?\s*:\s*([a-zA-Z_][\w-]*)/', $inner_rule['declarations'], $anim_m ) ) {
								$keyframes_to_include[] = $anim_m[1];
							}
						}
					}
					if ( $inner_matched ) {
						$matched .= $token['query'] . ' {' . "\n";
						$matched .= $inner_matched;
						$matched .= '}' . "\n";
					}
					break;

				case 'keyframes':
					// Include if referenced by matched rules.
					foreach ( $keyframes_to_include as $name ) {
						if ( false !== strpos( $token['full_rule'], $name ) ) {
							$matched .= $token['full_rule'] . "\n";
							break;
						}
					}
					break;

				case 'font-face':
					// Include @font-face rules — they may be needed.
					$matched .= $token['full_rule'] . "\n";
					break;
			}
		}

		return $matched;
	}

	/**
	 * Tokenize CSS into structured blocks.
	 *
	 * Handles @media (1 level deep), @keyframes, @font-face, and regular rules.
	 *
	 * @param string $css Raw CSS (comments already removed).
	 * @return array Tokens.
	 */
	private function tokenize_css( $css ) {
		$tokens = [];
		$pos    = 0;
		$len    = strlen( $css );

		while ( $pos < $len ) {
			// Skip whitespace.
			while ( $pos < $len && ctype_space( $css[ $pos ] ) ) {
				$pos++;
			}
			if ( $pos >= $len ) {
				break;
			}

			// @media rule.
			if ( substr( $css, $pos, 6 ) === '@media' ) {
				$brace_pos = strpos( $css, '{', $pos );
				if ( false === $brace_pos ) {
					break;
				}

				$query       = trim( substr( $css, $pos, $brace_pos - $pos ) );
				$inner_start = $brace_pos + 1;
				$inner_end   = $this->find_matching_brace( $css, $brace_pos );

				if ( false === $inner_end ) {
					break;
				}

				$inner_css   = substr( $css, $inner_start, $inner_end - $inner_start );
				$inner_rules = $this->parse_rule_block( $inner_css );

				$tokens[] = [
					'type'  => 'media',
					'query' => $query,
					'rules' => $inner_rules,
				];

				$pos = $inner_end + 1;
				continue;
			}

			// @keyframes rule.
			if ( preg_match( '/^@(?:-webkit-)?keyframes\b/', substr( $css, $pos, 30 ) ) ) {
				$brace_pos = strpos( $css, '{', $pos );
				if ( false === $brace_pos ) {
					break;
				}

				$end_pos = $this->find_matching_brace( $css, $brace_pos );
				if ( false === $end_pos ) {
					break;
				}

				$tokens[] = [
					'type'      => 'keyframes',
					'full_rule' => substr( $css, $pos, $end_pos - $pos + 1 ),
				];

				$pos = $end_pos + 1;
				continue;
			}

			// @font-face rule.
			if ( substr( $css, $pos, 10 ) === '@font-face' ) {
				$brace_pos = strpos( $css, '{', $pos );
				if ( false === $brace_pos ) {
					break;
				}

				$end_pos = $this->find_matching_brace( $css, $brace_pos );
				if ( false === $end_pos ) {
					break;
				}

				$tokens[] = [
					'type'      => 'font-face',
					'full_rule' => substr( $css, $pos, $end_pos - $pos + 1 ),
				];

				$pos = $end_pos + 1;
				continue;
			}

			// Skip other @-rules (e.g., @charset, @import, @supports).
			if ( '@' === $css[ $pos ] ) {
				$brace_pos    = strpos( $css, '{', $pos );
				$semicolon_pos = strpos( $css, ';', $pos );

				// Single-line @-rule (like @import, @charset).
				if ( false !== $semicolon_pos && ( false === $brace_pos || $semicolon_pos < $brace_pos ) ) {
					$pos = $semicolon_pos + 1;
					continue;
				}

				// Block @-rule — skip it.
				if ( false !== $brace_pos ) {
					$end_pos = $this->find_matching_brace( $css, $brace_pos );
					if ( false === $end_pos ) {
						break;
					}
					$pos = $end_pos + 1;
					continue;
				}

				break;
			}

			// Regular rule.
			$brace_pos = strpos( $css, '{', $pos );
			if ( false === $brace_pos ) {
				break;
			}

			$selector = trim( substr( $css, $pos, $brace_pos - $pos ) );

			$close_pos = strpos( $css, '}', $brace_pos );
			if ( false === $close_pos ) {
				break;
			}

			$declarations = trim( substr( $css, $brace_pos + 1, $close_pos - $brace_pos - 1 ) );

			if ( ! empty( $selector ) ) {
				$tokens[] = [
					'type'         => 'rule',
					'selector'     => $selector,
					'declarations' => $declarations,
				];
			}

			$pos = $close_pos + 1;
		}

		return $tokens;
	}

	/**
	 * Parse a CSS block (inside @media) into rule arrays.
	 *
	 * @param string $css CSS content inside a @media block.
	 * @return array Array of ['selector' => ..., 'declarations' => ...].
	 */
	private function parse_rule_block( $css ) {
		$rules = [];
		$pos   = 0;
		$len   = strlen( $css );

		while ( $pos < $len ) {
			while ( $pos < $len && ctype_space( $css[ $pos ] ) ) {
				$pos++;
			}
			if ( $pos >= $len ) {
				break;
			}

			$brace_pos = strpos( $css, '{', $pos );
			if ( false === $brace_pos ) {
				break;
			}

			$selector = trim( substr( $css, $pos, $brace_pos - $pos ) );

			$close_pos = strpos( $css, '}', $brace_pos );
			if ( false === $close_pos ) {
				break;
			}

			$declarations = trim( substr( $css, $brace_pos + 1, $close_pos - $brace_pos - 1 ) );

			if ( ! empty( $selector ) ) {
				$rules[] = [
					'selector'     => $selector,
					'declarations' => $declarations,
				];
			}

			$pos = $close_pos + 1;
		}

		return $rules;
	}

	/**
	 * Find the position of the matching closing brace.
	 *
	 * @param string $css   CSS string.
	 * @param int    $start Position of the opening brace.
	 * @return int|false Position of matching closing brace, or false.
	 */
	private function find_matching_brace( $css, $start ) {
		$len   = strlen( $css );
		$depth = 1;
		$pos   = $start + 1;

		while ( $pos < $len && $depth > 0 ) {
			if ( '{' === $css[ $pos ] ) {
				$depth++;
			} elseif ( '}' === $css[ $pos ] ) {
				$depth--;
			}
			if ( $depth > 0 ) {
				$pos++;
			}
		}

		return ( 0 === $depth ) ? $pos : false;
	}

	/**
	 * Check if a stylesheet file is from Jupiter, JupiterDonut, or WPBakery.
	 *
	 * @param string $file_path Absolute file path.
	 * @return bool
	 */
	private function is_relevant_stylesheet( $file_path ) {
		$relevant_paths = [
			'/jupiter-donut/',
			'/jupiter/',
			'/jupiter-core/',
			'/js_composer_theme/',
			'/js_composer/',
			'/wpbakery/',
		];

		foreach ( $relevant_paths as $path ) {
			if ( false !== strpos( $file_path, $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert a URL to a local file path.
	 *
	 * @param string $url Stylesheet URL.
	 * @return string|false Local file path or false.
	 */
	private function url_to_path( $url ) {
		// Handle protocol-relative URLs.
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$site_url = set_url_scheme( site_url(), 'https' );
		$url      = set_url_scheme( $url, 'https' );

		if ( 0 === strpos( $url, $site_url ) ) {
			$relative = str_replace( $site_url, '', $url );
			return ABSPATH . ltrim( $relative, '/' );
		}

		$content_url = set_url_scheme( content_url(), 'https' );
		if ( 0 === strpos( $url, $content_url ) ) {
			$relative = str_replace( $content_url, '', $url );
			return WP_CONTENT_DIR . $relative;
		}

		return false;
	}

	/**
	 * Get the upload directory path, creating it if needed.
	 *
	 * @return string|false
	 */
	private function get_upload_dir() {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/' . self::UPLOAD_DIR;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		return $dir;
	}

	/**
	 * Get the assets sub-directory for copied font/image files.
	 *
	 * @return string|false
	 */
	private function get_assets_dir() {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/' . self::ASSETS_DIR;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		return $dir;
	}

	/**
	 * Ensure the assets directory exists.
	 */
	private function ensure_assets_dir() {
		$this->get_assets_dir();
	}

	/**
	 * Get the extracted CSS.
	 *
	 * @return string
	 */
	public function get_extracted_css() {
		return $this->extracted_css;
	}

	/**
	 * Get captured class names.
	 *
	 * @return array
	 */
	public function get_captured_classes() {
		return $this->captured_classes;
	}

	/**
	 * Get discovered source stylesheets.
	 *
	 * @return array handle => file_path.
	 */
	public function get_source_stylesheets() {
		return $this->source_stylesheets;
	}

	/**
	 * Reset state (for fresh extraction).
	 */
	public function reset() {
		$this->captured_classes = [];
		$this->dynamic_css     = '';
		$this->extracted_css   = '';
	}
}
