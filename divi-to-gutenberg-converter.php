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
			add_action( 'wp_ajax_dtg_rollback_post', [ $this, 'ajax_rollback_post' ] );
			add_action( 'wp_ajax_dtg_rollback_all', [ $this, 'ajax_rollback_all' ] );
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

			wp_send_json_success( $result );
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
