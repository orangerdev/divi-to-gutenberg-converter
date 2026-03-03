<?php
/**
 * Plugin Name: SOL Screen Print Tips Page
 * Description: Standalone replacement for Jupiter child theme "Screen Print Tips" page template.
 *              Registers CPT, taxonomy, shortcode, search, and widgets.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ================================================================
 * 1. Register Custom Post Type: screen-print-tip
 *
 * Original CPT was registered by Jupiter/MK core plugin.
 * ============================================================== */
add_action( 'init', function () {
	if ( post_type_exists( 'screen-print-tip' ) ) {
		return;
	}

	register_post_type( 'screen-print-tip', array(
		'labels' => array(
			'name'               => 'Screen Print Tips',
			'singular_name'      => 'Screen Print Tip',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Screen Print Tip',
			'edit_item'          => 'Edit Screen Print Tip',
			'new_item'           => 'New Screen Print Tip',
			'view_item'          => 'View Screen Print Tip',
			'search_items'       => 'Search Screen Print Tips',
			'not_found'          => 'No screen print tips found',
			'not_found_in_trash' => 'No screen print tips found in Trash',
		),
		'public'       => true,
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => 'screen-print-tip' ),
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'menu_icon'    => 'dashicons-printer',
		'show_in_rest' => true,
	) );
} );

/* ================================================================
 * 2. Register Taxonomy: screen-print-tip-category
 * ============================================================== */
add_action( 'init', function () {
	if ( taxonomy_exists( 'screen-print-tip-category' ) ) {
		return;
	}

	register_taxonomy( 'screen-print-tip-category', 'screen-print-tip', array(
		'labels' => array(
			'name'          => 'Tip Categories',
			'singular_name' => 'Tip Category',
			'search_items'  => 'Search Tip Categories',
			'all_items'     => 'All Tip Categories',
			'edit_item'     => 'Edit Tip Category',
			'add_new_item'  => 'Add New Tip Category',
		),
		'hierarchical' => true,
		'public'       => true,
		'rewrite'      => array( 'slug' => 'screen-print-tip-category' ),
		'show_in_rest' => true,
	) );
} );

/* ================================================================
 * 3. Shortcode: [sol_screen_print_tips]
 *
 * Replaces the Jupiter page template + view. Drop this shortcode
 * into any page (Gutenberg, Elementor, etc.) to render the same
 * list of screen-print-tip posts with search and pagination.
 *
 * Attributes:
 *   posts_per_page  – number of tips per page (default 20)
 *   search_param    – GET parameter name for search (default "sq")
 *   show_search     – 1|0 whether to render the built-in search form (default 1)
 * ============================================================== */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'sol_screen_print_tips' ) ) {
		add_shortcode( 'sol_screen_print_tips', 'sol_render_screen_print_tips' );
	}
} );

/* ----------------------------------------------------------------
 * Early CSS: use has_shortcode() to output CSS in <head> when possible
 * -------------------------------------------------------------- */
add_action( 'wp', function () {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'sol_screen_print_tips' ) ) {
		add_action( 'wp_head', 'sol_screen_print_tips_css' );
	}
} );

function sol_render_screen_print_tips( $atts ) {
	$atts = shortcode_atts( array(
		'posts_per_page' => 20,
		'search_param'   => 'sq',
		'show_search'    => 1,
	), $atts, 'sol_screen_print_tips' );

	// CSS → footer (only when shortcode is used).
	add_action( 'wp_footer', 'sol_screen_print_tips_css', 1 );

	$paged = max( 1, absint( get_query_var( 'paged' ) ) );

	$args = array(
		'post_type'      => 'screen-print-tip',
		'posts_per_page' => absint( $atts['posts_per_page'] ),
		'paged'          => $paged,
		'post_status'    => 'publish',
	);

	// Apply search filter from GET parameter.
	$search_param = sanitize_key( $atts['search_param'] );
	if ( ! empty( $_GET[ $search_param ] ) ) {
		$args['s'] = sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) );
	}

	$query = new WP_Query( $args );

	ob_start();

	// Search form.
	if ( $atts['show_search'] ) {
		$current_search = isset( $_GET[ $search_param ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) ) ) : '';
		?>
		<div class="sol-spt-search-wrapper">
			<form method="get" action="">
				<input type="text"
				       name="<?php echo esc_attr( $search_param ); ?>"
				       value="<?php echo $current_search; ?>"
				       placeholder="Search screen print tips&hellip;" />
				<button type="submit">Search</button>
			</form>
		</div>
		<?php
	}

	if ( $query->have_posts() ) :
		?>
		<div class="sol-spt-list">
			<ul class="sol-spt-links">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<li>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</li>
				<?php endwhile; ?>
			</ul>
		</div>

		<?php
		// Pagination.
		$GLOBALS['wp_query']->max_num_pages = $query->max_num_pages;
		?>
		<div class="sol-spt-pagination">
			<?php
			the_posts_pagination( array(
				'mid_size'  => 6,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			) );
			?>
		</div>
		<?php
		wp_reset_postdata();
	else :
		?>
		<p class="sol-spt-empty">No screen print tips found.</p>
		<?php
	endif;

	return ob_get_clean();
}

/* ================================================================
 * 4. Base CSS (output once)
 * ============================================================== */
function sol_screen_print_tips_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="sol-screen-print-tips-css">
		/* Search */
		.sol-spt-search-wrapper {
			margin-bottom: 30px;
		}
		.sol-spt-search-wrapper form {
			display: flex;
			gap: 8px;
			max-width: 500px;
		}
		.sol-spt-search-wrapper input[type="text"] {
			flex: 1;
			padding: 8px 12px;
			border: 1px solid #ccc;
			border-radius: 4px;
			font-size: 14px;
		}
		.sol-spt-search-wrapper button {
			padding: 8px 20px;
			background: #333;
			color: #fff;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
		}
		.sol-spt-search-wrapper button:hover {
			background: #555;
		}

		/* List */
		.sol-spt-list {
			margin-bottom: 30px;
		}
		.sol-spt-links {
			list-style: none;
			margin: 0;
			padding: 0;
		}
		.sol-spt-links li {
			padding: 10px 0;
			border-bottom: 1px solid #eee;
		}
		.sol-spt-links li:last-child {
			border-bottom: none;
		}
		.sol-spt-links a {
			text-decoration: none;
			color: #333;
			font-size: 16px;
		}
		.sol-spt-links a:hover {
			color: #0073aa;
		}

		/* Pagination */
		.sol-spt-pagination {
			margin-top: 20px;
		}
		.sol-spt-pagination .nav-links {
			display: flex;
			gap: 6px;
			flex-wrap: wrap;
		}
		.sol-spt-pagination .page-numbers {
			display: inline-block;
			padding: 6px 12px;
			border: 1px solid #ddd;
			border-radius: 3px;
			text-decoration: none;
			color: #333;
		}
		.sol-spt-pagination .page-numbers.current {
			background: #333;
			color: #fff;
			border-color: #333;
		}
		.sol-spt-pagination .page-numbers:hover:not(.current) {
			background: #f5f5f5;
		}

		/* Empty state */
		.sol-spt-empty {
			padding: 40px 0;
			text-align: center;
			color: #999;
		}
	</style>
	<?php
}

/* ================================================================
 * 5. Widget: Recent Screen Print Tips
 *
 * Replaces Artbees_Widget_Recent_Screen_Print_Tips from child theme.
 * ============================================================== */
class SOL_Widget_Recent_Screen_Print_Tips extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'sol_recent_screen_print_tips',
			'Recent Screen Print Tips',
			array(
				'classname'   => 'sol-widget-recent-spt',
				'description' => 'Displays recent Screen Print Tips posts.',
			)
		);
	}

	public function widget( $args, $instance ) {
		$title        = apply_filters( 'widget_title', empty( $instance['title'] ) ? 'Recent Screen Print Tips' : $instance['title'], $instance, $this->id_base );
		$posts_number = max( 1, min( 15, absint( $instance['posts_number'] ?? 10 ) ) );
		$show_date    = ! empty( $instance['show_date'] );

		$query_args = array(
			'post_type'      => 'screen-print-tip',
			'posts_per_page' => $posts_number,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $instance['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'screen-print-tip-category',
					'field'    => 'term_id',
					'terms'    => absint( $instance['category'] ),
				),
			);
		}

		$recent = new WP_Query( $query_args );

		if ( ! $recent->have_posts() ) {
			return;
		}

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		echo '<ul class="sol-recent-spt-list">';

		while ( $recent->have_posts() ) :
			$recent->the_post();
			?>
			<li>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" class="sol-spt-thumb">
						<?php the_post_thumbnail( 'thumbnail' ); ?>
					</a>
				<?php endif; ?>
				<div class="sol-spt-info">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<?php if ( $show_date ) : ?>
						<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php endif; ?>
				</div>
			</li>
			<?php
		endwhile;

		echo '</ul>';

		wp_reset_postdata();

		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'        => sanitize_text_field( $new_instance['title'] ),
			'posts_number' => absint( $new_instance['posts_number'] ),
			'show_date'    => ! empty( $new_instance['show_date'] ) ? 1 : 0,
			'category'     => absint( $new_instance['category'] ?? 0 ),
		);
	}

	public function form( $instance ) {
		$title        = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$posts_number = isset( $instance['posts_number'] ) ? absint( $instance['posts_number'] ) : 10;
		$show_date    = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;
		$category     = isset( $instance['category'] ) ? absint( $instance['category'] ) : 0;

		$categories = get_terms( array(
			'taxonomy'   => 'screen-print-tip-category',
			'hide_empty' => false,
		) );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       type="text" value="<?php echo $title; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'posts_number' ); ?>">Number of posts:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'posts_number' ); ?>"
			       name="<?php echo $this->get_field_name( 'posts_number' ); ?>"
			       type="number" min="1" max="15" value="<?php echo $posts_number; ?>" />
		</p>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'show_date' ); ?>"
			       name="<?php echo $this->get_field_name( 'show_date' ); ?>" <?php checked( $show_date ); ?> />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>">Show Date</label>
		</p>
		<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
			<p>
				<label for="<?php echo $this->get_field_id( 'category' ); ?>">Category:</label>
				<select class="widefat" id="<?php echo $this->get_field_id( 'category' ); ?>"
				        name="<?php echo $this->get_field_name( 'category' ); ?>">
					<option value="0">All Categories</option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $category, $cat->term_id ); ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>
		<?php
	}
}

/* ================================================================
 * 6. Widget: Screen Print Tip Categories
 *
 * Replaces WP_Widget_Screen_Print_Tip_Categories from child theme.
 * ============================================================== */
class SOL_Widget_Screen_Print_Tip_Categories extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'sol_screen_print_tip_categories',
			'Screen Print Tip Categories',
			array(
				'classname'   => 'sol-widget-spt-categories',
				'description' => 'A list or dropdown of Screen Print Tip categories.',
			)
		);
	}

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? 'Categories' : $instance['title'], $instance, $this->id_base );
		$c     = ! empty( $instance['count'] );
		$h     = ! empty( $instance['hierarchical'] );
		$d     = ! empty( $instance['dropdown'] );

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		$cat_args = array(
			'orderby'      => 'name',
			'show_count'   => $c,
			'hierarchical' => $h,
			'taxonomy'     => 'screen-print-tip-category',
		);

		if ( $d ) {
			$dropdown_id = 'sol-spt-cat-dropdown-' . $this->number;

			echo '<form action="' . esc_url( home_url( '/' ) ) . '" method="get">';
			echo '<label class="screen-reader-text" for="' . esc_attr( $dropdown_id ) . '">' . esc_html( $title ) . '</label>';

			$cat_args['show_option_none'] = 'Select Category';
			$cat_args['id']               = $dropdown_id;
			wp_dropdown_categories( $cat_args );

			echo '</form>';

			// JS → footer.
			$dd_id = $dropdown_id;
			add_action( 'wp_footer', function () use ( $dd_id ) {
				?>
				<script>
				(function(){
					var dd = document.getElementById("<?php echo esc_js( $dd_id ); ?>");
					if (dd) {
						dd.onchange = function() {
							if (dd.options[dd.selectedIndex].value > 0) {
								dd.parentNode.submit();
							}
						};
					}
				})();
				</script>
				<?php
			}, 20 );
		} else {
			$cat_args['title_li'] = '';
			echo '<ul>';
			wp_list_categories( $cat_args );
			echo '</ul>';
		}

		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'        => sanitize_text_field( $new_instance['title'] ),
			'count'        => ! empty( $new_instance['count'] ) ? 1 : 0,
			'hierarchical' => ! empty( $new_instance['hierarchical'] ) ? 1 : 0,
			'dropdown'     => ! empty( $new_instance['dropdown'] ) ? 1 : 0,
		);
	}

	public function form( $instance ) {
		$title        = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$count        = isset( $instance['count'] ) ? (bool) $instance['count'] : false;
		$hierarchical = isset( $instance['hierarchical'] ) ? (bool) $instance['hierarchical'] : false;
		$dropdown     = isset( $instance['dropdown'] ) ? (bool) $instance['dropdown'] : false;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       type="text" value="<?php echo $title; ?>" />
		</p>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'dropdown' ); ?>"
			       name="<?php echo $this->get_field_name( 'dropdown' ); ?>" <?php checked( $dropdown ); ?> />
			<label for="<?php echo $this->get_field_id( 'dropdown' ); ?>">Display as dropdown</label>
		</p>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'count' ); ?>"
			       name="<?php echo $this->get_field_name( 'count' ); ?>" <?php checked( $count ); ?> />
			<label for="<?php echo $this->get_field_id( 'count' ); ?>">Show post counts</label>
		</p>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'hierarchical' ); ?>"
			       name="<?php echo $this->get_field_name( 'hierarchical' ); ?>" <?php checked( $hierarchical ); ?> />
			<label for="<?php echo $this->get_field_id( 'hierarchical' ); ?>">Show hierarchy</label>
		</p>
		<?php
	}
}

/* ================================================================
 * 7. Register Widgets
 * ============================================================== */
add_action( 'widgets_init', function () {
	register_widget( 'SOL_Widget_Recent_Screen_Print_Tips' );
	register_widget( 'SOL_Widget_Screen_Print_Tip_Categories' );
} );
