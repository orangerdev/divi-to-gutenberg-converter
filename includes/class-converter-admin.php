<?php
/**
 * Admin page for the converter.
 *
 * @package DTG_Converter
 */

defined( 'ABSPATH' ) || die( '-1' );

class DTG_Converter_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var DTG_Converter_Admin|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton instance.
	 *
	 * @return DTG_Converter_Admin
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_dtg-converter' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );

		wp_enqueue_style(
			'dtg-converter-admin',
			$plugin_url . 'assets/css/admin.css',
			[],
			DTG_Converter::VERSION
		);

		wp_enqueue_script(
			'dtg-converter-admin',
			$plugin_url . 'assets/js/admin.js',
			[ 'jquery' ],
			DTG_Converter::VERSION,
			true
		);

		wp_localize_script( 'dtg-converter-admin', 'dtgConverter', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dtg_converter_nonce' ),
			'i18n'    => [
				'scanning'    => __( 'Scanning posts...', 'dtg-converter' ),
				'converting'  => __( 'Converting...', 'dtg-converter' ),
				'rolling_back' => __( 'Rolling back...', 'dtg-converter' ),
				'done'        => __( 'Done!', 'dtg-converter' ),
				'confirm_convert' => __( 'Are you sure you want to convert all posts? Make sure you have a database backup.', 'dtg-converter' ),
				'confirm_rollback' => __( 'Are you sure you want to rollback ALL converted posts?', 'dtg-converter' ),
			],
		] );
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		?>
		<div class="wrap dtg-converter-wrap">
			<h1><?php esc_html_e( 'Shortcode to Gutenberg Converter', 'dtg-converter' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Convert WPBakery (vc_*) and Jupiter Donut (mk_*) shortcodes to Gutenberg blocks.', 'dtg-converter' ); ?>
			</p>

			<!-- Scan Section -->
			<div class="dtg-section" id="dtg-scan-section">
				<h2><?php esc_html_e( 'Step 1: Scan Posts', 'dtg-converter' ); ?></h2>
				<p><?php esc_html_e( 'Scan your site for posts containing WPBakery/Jupiter shortcodes.', 'dtg-converter' ); ?></p>
				<button type="button" class="button button-primary" id="dtg-scan-btn">
					<?php esc_html_e( 'Scan Posts', 'dtg-converter' ); ?>
				</button>
				<span class="spinner" id="dtg-scan-spinner"></span>

				<div id="dtg-scan-results" style="display:none;">
					<h3><?php esc_html_e( 'Scan Results', 'dtg-converter' ); ?></h3>
					<table class="widefat dtg-results-table">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Total posts with shortcodes', 'dtg-converter' ); ?></strong></td>
								<td id="dtg-total-posts">-</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Posts with WPBakery (vc_*)', 'dtg-converter' ); ?></strong></td>
								<td id="dtg-vc-posts">-</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Posts with Jupiter (mk_*)', 'dtg-converter' ); ?></strong></td>
								<td id="dtg-mk-posts">-</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Already converted', 'dtg-converter' ); ?></strong></td>
								<td id="dtg-converted-posts">-</td>
							</tr>
						</tbody>
					</table>

					<!-- Shortcode Inventory -->
					<h3><?php esc_html_e( 'Shortcode Inventory', 'dtg-converter' ); ?></h3>
					<div id="dtg-shortcode-inventory"></div>

					<!-- Post List -->
					<h3><?php esc_html_e( 'Posts Found', 'dtg-converter' ); ?></h3>
					<div id="dtg-post-list"></div>
				</div>
			</div>

			<!-- Preview Section -->
			<div class="dtg-section" id="dtg-preview-section">
				<h2><?php esc_html_e( 'Step 2: Preview Conversion', 'dtg-converter' ); ?></h2>
				<p><?php esc_html_e( 'Select a post from the scan results and preview its conversion.', 'dtg-converter' ); ?></p>

				<div class="dtg-preview-controls">
					<label for="dtg-preview-post-id">
						<?php esc_html_e( 'Post ID:', 'dtg-converter' ); ?>
					</label>
					<input type="number" id="dtg-preview-post-id" min="1" placeholder="123" />
					<button type="button" class="button" id="dtg-preview-btn">
						<?php esc_html_e( 'Preview', 'dtg-converter' ); ?>
					</button>
					<span class="spinner" id="dtg-preview-spinner"></span>
				</div>

				<div id="dtg-preview-results" style="display:none;">
					<h3 id="dtg-preview-title"></h3>
					<div class="dtg-preview-columns">
						<div class="dtg-preview-column">
							<h4><?php esc_html_e( 'Original (Shortcodes)', 'dtg-converter' ); ?></h4>
							<pre id="dtg-preview-original" class="dtg-code-block"></pre>
						</div>
						<div class="dtg-preview-column">
							<h4><?php esc_html_e( 'Converted (Gutenberg)', 'dtg-converter' ); ?></h4>
							<pre id="dtg-preview-converted" class="dtg-code-block"></pre>
						</div>
					</div>
					<div id="dtg-preview-css-wrap" style="display:none;">
						<h4><?php esc_html_e( 'Generated CSS', 'dtg-converter' ); ?></h4>
						<pre id="dtg-preview-css" class="dtg-code-block dtg-css-block"></pre>
					</div>
				</div>
			</div>

			<!-- Convert Section -->
			<div class="dtg-section" id="dtg-convert-section">
				<h2><?php esc_html_e( 'Step 3: Run Conversion', 'dtg-converter' ); ?></h2>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Warning:', 'dtg-converter' ); ?></strong>
						<?php esc_html_e( 'This will modify post content in your database. A backup of original content is saved to post meta, but a full database backup is strongly recommended.', 'dtg-converter' ); ?>
					</p>
				</div>
				<button type="button" class="button button-primary" id="dtg-convert-btn">
					<?php esc_html_e( 'Start Conversion', 'dtg-converter' ); ?>
				</button>
				<span class="spinner" id="dtg-convert-spinner"></span>

				<div id="dtg-progress" style="display:none;">
					<div class="dtg-progress-bar">
						<div class="dtg-progress-fill" id="dtg-progress-fill"></div>
					</div>
					<p id="dtg-progress-text"></p>
				</div>

				<div id="dtg-convert-log" style="display:none;">
					<h3><?php esc_html_e( 'Conversion Log', 'dtg-converter' ); ?></h3>
					<div id="dtg-log-entries"></div>
				</div>
			</div>

			<!-- Rollback Section -->
			<div class="dtg-section" id="dtg-rollback-section">
				<h2><?php esc_html_e( 'Rollback', 'dtg-converter' ); ?></h2>
				<p><?php esc_html_e( 'Restore posts to their original content.', 'dtg-converter' ); ?></p>

				<div class="dtg-rollback-controls">
					<label for="dtg-rollback-post-id">
						<?php esc_html_e( 'Post ID (single rollback):', 'dtg-converter' ); ?>
					</label>
					<input type="number" id="dtg-rollback-post-id" min="1" placeholder="123" />
					<button type="button" class="button" id="dtg-rollback-single-btn">
						<?php esc_html_e( 'Rollback Post', 'dtg-converter' ); ?>
					</button>
				</div>

				<p style="margin-top:15px;">
					<button type="button" class="button button-link-delete" id="dtg-rollback-all-btn">
						<?php esc_html_e( 'Rollback ALL Converted Posts', 'dtg-converter' ); ?>
					</button>
					<span class="spinner" id="dtg-rollback-spinner"></span>
				</p>

				<div id="dtg-rollback-results" style="display:none;"></div>
			</div>

			<!-- CSS Management Section -->
			<div class="dtg-section" id="dtg-css-section">
				<h2><?php esc_html_e( 'CSS Management', 'dtg-converter' ); ?></h2>
				<p><?php esc_html_e( 'Manage the generated CSS file for converted posts.', 'dtg-converter' ); ?></p>

				<?php
				$css_url = DTG_Batch_Processor::get_css_file_url();
				if ( $css_url ) :
					?>
					<div class="notice notice-success inline">
						<p>
							<?php esc_html_e( 'CSS file is active:', 'dtg-converter' ); ?>
							<code><?php echo esc_html( $css_url ); ?></code>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'No CSS file generated yet. Run a conversion first or click Regenerate CSS.', 'dtg-converter' ); ?></p>
					</div>
				<?php endif; ?>

				<button type="button" class="button" id="dtg-regenerate-css-btn">
					<?php esc_html_e( 'Regenerate CSS', 'dtg-converter' ); ?>
				</button>
				<span class="spinner" id="dtg-css-spinner"></span>
				<div id="dtg-css-results" style="display:none;"></div>
			</div>
		</div>
		<?php
	}
}
