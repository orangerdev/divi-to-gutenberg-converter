<?php
/**
 * Plugin Name: Divi to Gutenberg Converter
 * Plugin URI: https://github.com/orangerdev/divi-to-gutenberg-converter
 * Description: Convert WPBakery Page Builder (vc_*) and Jupiter Donut (mk_*) shortcodes to Gutenberg blocks.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://github.com/orangerdev
 * Text Domain: dtg-converter
 * License: GPL-3.0-or-later
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'DTG_Converter' ) ) {

	/**
	 * Main plugin class.
	 */
	class DTG_Converter {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * Singleton instance.
		 *
		 * @var DTG_Converter|null
		 */
		private static $instance = null;

		/**
		 * Plugin directory path.
		 *
		 * @var string
		 */
		private $plugin_dir;

		/**
		 * Plugin URL.
		 *
		 * @var string
		 */
		private $plugin_url;

		/**
		 * Returns singleton instance.
		 *
		 * @return DTG_Converter
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			$this->plugin_dir = plugin_dir_path( __FILE__ );
			$this->plugin_url = plugin_dir_url( __FILE__ );

			$this->load_dependencies();

			add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
			add_action( 'wp_ajax_dtg_scan_posts', [ $this, 'ajax_scan_posts' ] );
			add_action( 'wp_ajax_dtg_preview_conversion', [ $this, 'ajax_preview_conversion' ] );
			add_action( 'wp_ajax_dtg_run_batch', [ $this, 'ajax_run_batch' ] );
			add_action( 'wp_ajax_dtg_run_hybrid_batch', [ $this, 'ajax_run_hybrid_batch' ] );
			add_action( 'wp_ajax_dtg_check_hybrid_requirements', [ $this, 'ajax_check_hybrid_requirements' ] );
			add_action( 'wp_ajax_dtg_convert_single', [ $this, 'ajax_convert_single' ] );
			add_action( 'wp_ajax_dtg_convert_selected_batch', [ $this, 'ajax_convert_selected_batch' ] );
			add_action( 'wp_ajax_dtg_rollback_post', [ $this, 'ajax_rollback_post' ] );
			add_action( 'wp_ajax_dtg_rollback_all', [ $this, 'ajax_rollback_all' ] );
			add_action( 'wp_ajax_dtg_regenerate_css', [ $this, 'ajax_regenerate_css' ] );

			// Frontend CSS enqueue.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_css' ] );

			// Block editor CSS enqueue (so styles are visible while editing).
			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_css' ] );
		}

		/**
		 * Load required files.
		 */
		private function load_dependencies() {
			$includes = $this->plugin_dir . 'includes/';

			require_once $includes . 'class-shortcode-parser.php';
			require_once $includes . 'class-gutenberg-builder.php';
			require_once $includes . 'class-batch-processor.php';
			require_once $includes . 'class-converter-admin.php';

			// Hybrid mode components.
			require_once $includes . 'class-shortcode-classifier.php';
			require_once $includes . 'class-render-capture.php';
			require_once $includes . 'class-css-extractor.php';

			// Converters.
			require_once $includes . 'converters/class-converter-base.php';
			require_once $includes . 'converters/class-converter-layout.php';
			require_once $includes . 'converters/class-converter-text.php';
			require_once $includes . 'converters/class-converter-media.php';
			require_once $includes . 'converters/class-converter-button.php';
			require_once $includes . 'converters/class-converter-separator.php';
			require_once $includes . 'converters/class-converter-misc.php';
		}

		/**
		 * Register admin menu under Tools.
		 */
		public function register_admin_menu() {
			add_management_page(
				__( 'Shortcode to Gutenberg', 'dtg-converter' ),
				__( 'Shortcode to Gutenberg', 'dtg-converter' ),
				'manage_options',
				'dtg-converter',
				[ DTG_Converter_Admin::get_instance(), 'render_page' ]
			);
		}

		/**
		 * AJAX: Scan posts for shortcodes.
		 */
		public function ajax_scan_posts() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$processor = new DTG_Batch_Processor();
			$results   = $processor->scan_posts();

			wp_send_json_success( $results );
		}

		/**
		 * AJAX: Preview conversion for a single post.
		 */
		public function ajax_preview_conversion() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

			if ( ! $post_id ) {
				wp_send_json_error( 'Invalid post ID' );
			}

			$processor = new DTG_Batch_Processor();
			$result    = $processor->preview_conversion( $post_id );

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Run batch conversion.
		 */
		public function ajax_run_batch() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
			$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;

			$processor = new DTG_Batch_Processor();
			$result    = $processor->process_batch( $offset, $limit );

			// Regenerate CSS file when all batches are done.
			if ( ! $result['has_more'] ) {
				$css_result         = $processor->regenerate_css_file();
				$result['css_file'] = $css_result;
			}

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Run hybrid batch conversion (render capture for complex elements).
		 */
		public function ajax_run_hybrid_batch() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
			$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;

			$processor           = new DTG_Batch_Processor();
			$failed_requirements = null;

			// Enable hybrid mode (validates requirements internally).
			if ( ! $processor->enable_hybrid_mode( $failed_requirements ) ) {
				wp_send_json_error( [
					'message'      => 'Hybrid mode requirements not met.',
					'requirements' => $failed_requirements,
				] );
			}

			$result = $processor->process_batch( $offset, $limit );

			// When all batches are done, finalize CSS extraction.
			if ( ! $result['has_more'] ) {
				$css_result             = $processor->regenerate_css_file();
				$result['css_file']     = $css_result;
				$result['captured_css'] = $processor->finalize_hybrid_css();
			}

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Check hybrid mode requirements without running conversion.
		 */
		public function ajax_check_hybrid_requirements() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$processor    = new DTG_Batch_Processor();
			$requirements = $processor->check_hybrid_requirements();

			$ready = true;
			foreach ( $requirements as $req ) {
				if ( 'missing' === $req['status'] ) {
					$ready = false;
					break;
				}
			}

			wp_send_json_success( [
				'ready'        => $ready,
				'requirements' => $requirements,
			] );
		}

		/**
		 * AJAX: Convert a single post (hybrid mode).
		 */
		public function ajax_convert_single() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$mode    = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'hybrid';

			if ( ! $post_id ) {
				wp_send_json_error( 'Invalid post ID' );
			}

			$processor = new DTG_Batch_Processor();

			if ( 'hybrid' === $mode ) {
				$failed_requirements = null;
				if ( ! $processor->enable_hybrid_mode( $failed_requirements ) ) {
					wp_send_json_error( [
						'message'      => 'Hybrid mode requirements not met.',
						'requirements' => $failed_requirements,
					] );
				}
			}

			$result = $processor->convert_single_post( $post_id );

			// Regenerate CSS after conversion.
			if ( 'success' === $result['status'] ) {
				$processor->regenerate_css_file();

				if ( 'hybrid' === $mode ) {
					$result['captured_css'] = $processor->finalize_hybrid_css();
				}
			}

			if ( 'failed' === $result['status'] ) {
				wp_send_json_error( $result['message'] );
			}

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Convert a batch of selected posts with a specific mode.
		 */
		public function ajax_convert_selected_batch() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : [];
			$mode     = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'hybrid';

			if ( empty( $post_ids ) ) {
				wp_send_json_error( 'No posts selected' );
			}

			$processor = new DTG_Batch_Processor();

			if ( 'hybrid' === $mode ) {
				$failed_requirements = null;
				if ( ! $processor->enable_hybrid_mode( $failed_requirements ) ) {
					wp_send_json_error( [
						'message'      => 'Hybrid mode requirements not met.',
						'requirements' => $failed_requirements,
					] );
				}
			}

			$result = $processor->convert_selected_posts( $post_ids );

			// Regenerate CSS.
			$css_result         = $processor->regenerate_css_file();
			$result['css_file'] = $css_result;

			if ( 'hybrid' === $mode ) {
				$result['captured_css'] = $processor->finalize_hybrid_css();
			}

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Rollback a single post.
		 */
		public function ajax_rollback_post() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

			if ( ! $post_id ) {
				wp_send_json_error( 'Invalid post ID' );
			}

			$processor = new DTG_Batch_Processor();
			$result    = $processor->rollback_post( $post_id );

			if ( $result ) {
				wp_send_json_success( 'Post rolled back successfully' );
			} else {
				wp_send_json_error( 'No backup found for this post' );
			}
		}

		/**
		 * AJAX: Rollback all converted posts.
		 */
		public function ajax_rollback_all() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$processor = new DTG_Batch_Processor();
			$result    = $processor->rollback_all();

			// Regenerate CSS file after rollback.
			$processor->regenerate_css_file();

			wp_send_json_success( $result );
		}

		/**
		 * AJAX: Regenerate the aggregated CSS file.
		 */
		public function ajax_regenerate_css() {
			check_ajax_referer( 'dtg_converter_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$processor = new DTG_Batch_Processor();
			$result    = $processor->regenerate_css_file();

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result['message'] );
			}
		}

		/**
		 * Enqueue the generated CSS files on the frontend.
		 */
		public function enqueue_frontend_css() {
			// 1. Per-post converter CSS (native Gutenberg blocks).
			$css_url = DTG_Batch_Processor::get_css_file_url();
			if ( $css_url ) {
				$version = get_option( 'dtg_css_version', self::VERSION );
				wp_enqueue_style( 'dtg-converter-custom', $css_url, [], $version );
			}

			// 2. Captured plugin/theme CSS (hybrid render capture).
			$captured_url = DTG_CSS_Extractor::get_captured_css_url();
			if ( $captured_url ) {
				$captured_version = get_option( 'dtg_captured_css_version', self::VERSION );
				wp_enqueue_style( 'dtg-captured-plugin-styles', $captured_url, [], $captured_version );
			}
		}

		/**
		 * Enqueue CSS inside the Gutenberg block editor so converted
		 * blocks render with the correct styles while editing.
		 */
		public function enqueue_editor_css() {
			// 1. Enqueue the global aggregated CSS file.
			$css_url = DTG_Batch_Processor::get_css_file_url();
			if ( $css_url ) {
				$version = get_option( 'dtg_css_version', self::VERSION );
				wp_enqueue_style( 'dtg-converter-custom', $css_url, [], $version );
			}

			// 1b. Enqueue captured plugin/theme CSS.
			$captured_url = DTG_CSS_Extractor::get_captured_css_url();
			if ( $captured_url ) {
				$captured_version = get_option( 'dtg_captured_css_version', self::VERSION );
				wp_enqueue_style( 'dtg-captured-plugin-styles', $captured_url, [], $captured_version );
			}

			// 2. Add per-post inline CSS (covers posts not yet aggregated).
			$post_id = get_the_ID();
			if ( $post_id ) {
				$post_css = get_post_meta( $post_id, '_dtg_custom_css', true );
				if ( $post_css ) {
					// Ensure handle exists before adding inline style.
					if ( ! wp_style_is( 'dtg-converter-custom', 'enqueued' ) ) {
						wp_register_style( 'dtg-converter-custom', false );
						wp_enqueue_style( 'dtg-converter-custom' );
					}
					wp_add_inline_style( 'dtg-converter-custom', $post_css );
				}
			}
		}

		/**
		 * Get plugin directory path.
		 *
		 * @return string
		 */
		public function plugin_dir() {
			return $this->plugin_dir;
		}

		/**
		 * Get plugin URL.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return $this->plugin_url;
		}
	}
}

/**
 * Initialize the plugin.
 */
function dtg_converter_init() {
	return DTG_Converter::get_instance();
}
add_action( 'plugins_loaded', 'dtg_converter_init' );
