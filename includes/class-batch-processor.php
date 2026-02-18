<?php
/**
 * Batch processor for converting posts/pages.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Batch_Processor {

	/**
	 * Meta key for storing backup of original content.
	 *
	 * @var string
	 */
	const BACKUP_META_KEY = '_dtg_pre_gutenberg_content';

	/**
	 * Meta key to mark a post as converted.
	 *
	 * @var string
	 */
	const CONVERTED_META_KEY = '_dtg_converted';

	/**
	 * Meta key for storing WPBakery custom CSS backup.
	 *
	 * @var string
	 */
	const CSS_BACKUP_META_KEY = '_dtg_wpb_custom_css_backup';

	/**
	 * Meta key for per-post CSS.
	 *
	 * @var string
	 */
	const CSS_META_KEY = '_dtg_custom_css';

	/**
	 * Meta key for per-post Google Fonts.
	 *
	 * @var string
	 */
	const FONTS_META_KEY = '_dtg_google_fonts';

	/**
	 * Upload sub-directory name.
	 *
	 * @var string
	 */
	const UPLOAD_DIR = 'dtg-converter';

	/**
	 * Gutenberg builder instance.
	 *
	 * @var DTG_Gutenberg_Builder
	 */
	private $builder;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->builder = new DTG_Gutenberg_Builder();
	}

	/**
	 * Scan all posts/pages for shortcodes and return report.
	 *
	 * @return array Scan results.
	 */
	public function scan_posts() {
		global $wpdb;

		$results = [
			'total_posts'        => 0,
			'posts_with_vc'      => 0,
			'posts_with_mk'      => 0,
			'already_converted'  => 0,
			'posts'              => [],
			'shortcode_inventory' => [],
		];

		// Find all posts/pages with shortcodes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_status, post_content
			FROM {$wpdb->posts}
			WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[mk_%')
			AND post_type IN ('post', 'page', 'product')
			AND post_status IN ('publish', 'draft', 'private', 'pending')
			ORDER BY post_type ASC, post_title ASC"
		);

		if ( ! $posts ) {
			return $results;
		}

		$results['total_posts'] = count( $posts );
		$all_shortcodes         = [];

		foreach ( $posts as $post ) {
			$has_vc = ( false !== strpos( $post->post_content, '[vc_' ) );
			$has_mk = ( false !== strpos( $post->post_content, '[mk_' ) );

			if ( $has_vc ) {
				$results['posts_with_vc']++;
			}
			if ( $has_mk ) {
				$results['posts_with_mk']++;
			}

			$is_converted = get_post_meta( $post->ID, self::CONVERTED_META_KEY, true );
			if ( $is_converted ) {
				$results['already_converted']++;
			}

			// Analyze shortcodes in this post.
			$shortcodes = $this->builder->analyze_shortcodes( $post->post_content );

			foreach ( $shortcodes as $tag => $count ) {
				if ( ! isset( $all_shortcodes[ $tag ] ) ) {
					$all_shortcodes[ $tag ] = 0;
				}
				$all_shortcodes[ $tag ] += $count;
			}

			$results['posts'][] = [
				'ID'          => $post->ID,
				'post_title'  => $post->post_title,
				'post_type'   => $post->post_type,
				'post_status' => $post->post_status,
				'has_vc'      => $has_vc,
				'has_mk'      => $has_mk,
				'converted'   => (bool) $is_converted,
				'shortcodes'  => $shortcodes,
			];
		}

		arsort( $all_shortcodes );
		$results['shortcode_inventory'] = $all_shortcodes;

		return $results;
	}

	/**
	 * Preview conversion for a single post (dry run).
	 *
	 * @param int $post_id Post ID.
	 * @return array Preview data with before/after content.
	 */
	public function preview_conversion( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => 'Post not found' ];
		}

		$original = $post->post_content;

		$this->builder->set_post_id( $post_id );
		$converted = $this->builder->convert( $original );
		$css       = $this->builder->get_google_fonts_css() . $this->builder->get_collected_css();

		return [
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'original'   => $original,
			'converted'  => $converted,
			'css'        => $css,
			'shortcodes' => $this->builder->analyze_shortcodes( $original ),
		];
	}

	/**
	 * Process a batch of posts.
	 *
	 * @param int $offset Starting offset.
	 * @param int $limit  Number of posts to process.
	 * @return array Batch results.
	 */
	public function process_batch( $offset = 0, $limit = 10 ) {
		global $wpdb;

		$results = [
			'processed' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'details'   => [],
			'has_more'  => false,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_content
				FROM {$wpdb->posts}
				WHERE (post_content LIKE '%%[vc_%%' OR post_content LIKE '%%[mk_%%')
				AND post_type IN ('post', 'page', 'product')
				AND post_status IN ('publish', 'draft', 'private', 'pending')
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				$limit + 1, // Fetch one extra to check if there are more.
				$offset
			)
		);

		if ( ! $posts ) {
			return $results;
		}

		// Check if there are more posts.
		if ( count( $posts ) > $limit ) {
			$results['has_more'] = true;
			array_pop( $posts ); // Remove the extra.
		}

		foreach ( $posts as $post ) {
			$detail = [
				'ID'         => $post->ID,
				'post_title' => $post->post_title,
				'status'     => 'success',
				'message'    => '',
			];

			// Skip already converted posts.
			$is_converted = get_post_meta( $post->ID, self::CONVERTED_META_KEY, true );
			if ( $is_converted ) {
				$detail['status']  = 'skipped';
				$detail['message'] = 'Already converted';
				$results['skipped']++;
				$results['details'][] = $detail;
				continue;
			}

			try {
				$this->convert_post( $post );
				$results['processed']++;
				$detail['message'] = 'Converted successfully';
			} catch ( Exception $e ) {
				$detail['status']  = 'failed';
				$detail['message'] = $e->getMessage();
				$results['failed']++;
			}

			$results['details'][] = $detail;
		}

		return $results;
	}

	/**
	 * Convert a single post.
	 *
	 * @param WP_Post|object $post Post object.
	 * @throws Exception If conversion fails.
	 */
	private function convert_post( $post ) {
		$original = $post->post_content;

		// 1. Backup original content.
		update_post_meta( $post->ID, self::BACKUP_META_KEY, $original );

		// 2. Backup WPBakery custom CSS if present.
		$wpb_css = get_post_meta( $post->ID, '_wpb_shortcodes_custom_css', true );
		if ( $wpb_css ) {
			update_post_meta( $post->ID, self::CSS_BACKUP_META_KEY, $wpb_css );
		}

		// 3. Convert content with CSS collection.
		$this->builder->set_post_id( $post->ID );
		$converted = $this->builder->convert( $original );

		// 4. Save collected CSS and Google Fonts to post meta.
		$post_css = $this->builder->get_collected_css();
		if ( ! empty( $post_css ) ) {
			update_post_meta( $post->ID, self::CSS_META_KEY, $post_css );
		}

		$google_fonts = $this->builder->get_google_fonts();
		if ( ! empty( $google_fonts ) ) {
			update_post_meta( $post->ID, self::FONTS_META_KEY, $google_fonts );
		}

		// 5. Update post content.
		$result = wp_update_post(
			[
				'ID'           => $post->ID,
				'post_content' => $converted,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			// Restore backup on failure.
			wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $original,
				]
			);
			delete_post_meta( $post->ID, self::BACKUP_META_KEY );
			delete_post_meta( $post->ID, self::CSS_BACKUP_META_KEY );
			delete_post_meta( $post->ID, self::CSS_META_KEY );
			delete_post_meta( $post->ID, self::FONTS_META_KEY );

			throw new Exception( 'Failed to update post: ' . $result->get_error_message() );
		}

		// 6. Clean up WPBakery meta.
		delete_post_meta( $post->ID, '_wpb_vc_js_status' );
		delete_post_meta( $post->ID, '_wpb_shortcodes_custom_css' );
		delete_post_meta( $post->ID, '_wpb_shortcodes_default_css' );

		// 7. Mark as converted.
		update_post_meta( $post->ID, self::CONVERTED_META_KEY, current_time( 'mysql' ) );
	}

	/**
	 * Rollback a single post to its original content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success, false if no backup.
	 */
	public function rollback_post( $post_id ) {
		$backup = get_post_meta( $post_id, self::BACKUP_META_KEY, true );

		if ( empty( $backup ) ) {
			return false;
		}

		// Restore content.
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $backup,
			]
		);

		// Restore WPBakery CSS.
		$css_backup = get_post_meta( $post_id, self::CSS_BACKUP_META_KEY, true );
		if ( $css_backup ) {
			update_post_meta( $post_id, '_wpb_shortcodes_custom_css', $css_backup );
		}

		// Restore WPBakery status.
		update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );

		// Clean up converter meta.
		delete_post_meta( $post_id, self::BACKUP_META_KEY );
		delete_post_meta( $post_id, self::CSS_BACKUP_META_KEY );
		delete_post_meta( $post_id, self::CONVERTED_META_KEY );
		delete_post_meta( $post_id, self::CSS_META_KEY );
		delete_post_meta( $post_id, self::FONTS_META_KEY );

		return true;
	}

	/**
	 * Rollback all converted posts.
	 *
	 * @return array Results with count of restored posts.
	 */
	public function rollback_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::CONVERTED_META_KEY
			)
		);

		$results = [
			'total'    => count( $post_ids ),
			'restored' => 0,
			'failed'   => 0,
		];

		foreach ( $post_ids as $post_id ) {
			if ( $this->rollback_post( (int) $post_id ) ) {
				$results['restored']++;
			} else {
				$results['failed']++;
			}
		}

		return $results;
	}

	/**
	 * Get total count of posts with shortcodes.
	 *
	 * @return int
	 */
	public function get_total_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE (post_content LIKE '%[vc_%' OR post_content LIKE '%[mk_%')
			AND post_type IN ('post', 'page', 'product')
			AND post_status IN ('publish', 'draft', 'private', 'pending')"
		);
	}

	/**
	 * Regenerate the aggregated CSS file from all converted posts.
	 *
	 * @return array Result with file path and size.
	 */
	public function regenerate_css_file() {
		global $wpdb;

		// Collect all per-post CSS.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$css_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_status IN ('publish', 'draft', 'private', 'pending')
				ORDER BY pm.post_id ASC",
				self::CSS_META_KEY
			)
		);

		// Collect all Google Fonts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$font_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::FONTS_META_KEY
			)
		);

		$all_fonts = [];
		if ( $font_rows ) {
			foreach ( $font_rows as $row ) {
				$fonts = maybe_unserialize( $row->meta_value );
				if ( is_array( $fonts ) ) {
					foreach ( $fonts as $family => $variants ) {
						if ( ! isset( $all_fonts[ $family ] ) ) {
							$all_fonts[ $family ] = [];
						}
						$all_fonts[ $family ] = array_unique( array_merge( $all_fonts[ $family ], (array) $variants ) );
					}
				}
			}
		}

		// Build CSS file content.
		$css = "/* DTG Converter - Auto-generated CSS */\n";
		$css .= "/* Generated: " . current_time( 'mysql' ) . " */\n\n";

		// Google Fonts @import.
		if ( ! empty( $all_fonts ) ) {
			$families = [];
			foreach ( $all_fonts as $family => $variants ) {
				$families[] = str_replace( ' ', '+', $family ) . ':' . implode( ',', $variants );
			}
			$css .= '@import url("https://fonts.googleapis.com/css?family=' . implode( '|', $families ) . '&display=swap");' . "\n\n";
		}

		// Per-post CSS.
		if ( $css_rows ) {
			foreach ( $css_rows as $row ) {
				$css .= $row->meta_value . "\n";
			}
		}

		// Write file.
		$upload_dir = $this->get_upload_dir();
		if ( ! $upload_dir ) {
			return [
				'success' => false,
				'message' => 'Failed to create upload directory',
			];
		}

		$file_path = $upload_dir . '/custom-styles.css';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $file_path, $css );

		if ( false === $written ) {
			return [
				'success' => false,
				'message' => 'Failed to write CSS file',
			];
		}

		// Store file version for cache busting.
		update_option( 'dtg_css_version', time() );

		return [
			'success'   => true,
			'file_path' => $file_path,
			'file_size' => size_format( strlen( $css ) ),
			'post_count' => $css_rows ? count( $css_rows ) : 0,
		];
	}

	/**
	 * Get the upload directory path, creating it if needed.
	 *
	 * @return string|false Directory path or false on failure.
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
	 * Get the URL to the generated CSS file.
	 *
	 * @return string|false CSS file URL or false if not generated.
	 */
	public static function get_css_file_url() {
		$upload = wp_upload_dir();
		$file   = $upload['basedir'] . '/' . self::UPLOAD_DIR . '/custom-styles.css';

		if ( ! file_exists( $file ) ) {
			return false;
		}

		return $upload['baseurl'] . '/' . self::UPLOAD_DIR . '/custom-styles.css';
	}
}
