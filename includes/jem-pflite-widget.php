<?php
/*
 Plugin Name: KEM WooCommerce Product Filter
 Plugin URI: http://mydomain.com
 Description: Widget for Product Filter
 Author: JEM Products
 Version: 1.0
 Author URI: http://www.jem-products.com
 */

// Block direct requests
if ( !defined('ABSPATH') )
	die('-1');

if ( ! defined('JEM_PFLITE_DOMAIN') ) {
	define('JEM_PFLITE_DOMAIN', 'jem-product-filter-lite');
}

if ( ! defined('JEM_PFLITE_PLUGIN_PATH') ) {
	define('JEM_PFLITE_PLUGIN_PATH', JEM_PFLITE_PLUGIN_PATH);
}


add_action( 'widgets_init', function(){
	register_widget( 'JEM_PFLITE_WIDGET' );
});

/**
 * Adds My_Widget widget.
	*/
	class JEM_PFLITE_WIDGET extends WP_Widget {
		/**
		 * Register widget with WordPress.
		 */
		function __construct() {
			parent::__construct(
					'JEM_PFLITE_WIDGET', // Base ID
					__('JEM AJAX Product Filter', JEM_PFLITE_DOMAIN), // Name
					array( 'description' => __( 'Add AJAX Product filters to your WooCommerce store', JEM_PFLITE_DOMAIN ), ) // Args
			);
			
			//add some classes to the widget
			$this->widget_options['classname'] .= ' woocommerce';
			$this->init();
		}
		
		/**
		 * Performs initialisation stuff
		 */
		function init(){
			

			
			//Set the defaults for our settings
			$defaults = array(
					'shop_loop_container'  => '.products',
					'no_products_found' => '.woocommerce-info',
					'custom_loop_container'  => '.jempflite-before-shop',					
			);
			
			$this->params = get_option('jempflite_settings');
				
			//now set any defaults
			$this->params = wp_parse_args($this->params, $defaults);
				
			//This is the plugin URL - we use it in jscript
			$this->params['plugin_url'] = plugin_dir_url( __FILE__ );
			
			//lets get the currency symbol and alignment
			$this->params['ccy_symbol']  = get_woocommerce_currency_symbol();
			$this->params['ccy_format'] = get_option( 'woocommerce_currency_pos' );
			
			//now lets convert the price format into somethign we can use in javascript
			//always have a default!
			$this->params['ccy_space'] = "";
			$this->params['ccy_side'] = "left";
			
			switch ( $this->params['ccy_format']  ) {
				case 'left' :
					$this->params['ccy_space'] = "";
					$this->params['ccy_side'] = "left";
					break;
				case 'right' :
					$this->params['ccy_space'] = "";
					$this->params['ccy_side'] = "right";
					break;
				case 'left_space' :
					$this->params['ccy_space'] = " ";
					$this->params['ccy_side'] = "left";
					break;
				case 'right_space' :
					$this->params['ccy_space'] = " ";
					$this->params['ccy_side'] = "right";
					break;
			}
			
			// get the price range - we also store this in a transient
			$transient_name = 'jempflite_price_range';
			
			//keep track of min/max
			$min=false;
			$max=false;
			
			if (false === ($price_range = get_transient($transient_name))) {

				//get all products
				$args = array(
						'post_type'   => 'product',
						'post_status' => 'publish',
						'numberposts' => -1,
						'fields'      => 'ids'
				);
				
				$product_ids = get_posts($args);

				
				
				foreach ($product_ids as $id) {
					
					
					//$pdct = $_pf->get_product($id);
					$pdct = new WC_product($id);
						
					$price= $pdct->get_price_including_tax();
					
					
				
					if ($price) {
						if ($min === false || $min > (int)$price) {
							$min = floor($price);
						}
							
						if ($max === false || $max < (int)$price) {
							$max = ceil($price);
						}
						
					}
				
					// for child posts
					$product_variation = get_children(
							array(
									'post_type'   => 'product_variation',
									'post_parent' => $id,
									'numberposts' => -1
							)
					);
				
					if (sizeof($product_variation) >0) {
						foreach ($product_variation as $variation) {
							$pdct = new WC_product($variation->ID);
								
							$price= $pdct->get_price();
								
							if ($price) {
								if ($min === false || $min > (int)$price) {
									$min = floor($price);
								}
									
								if ($max === false || $max < (int)$price) {
									$max = ceil($price);
								}
								
							}
						}
					}
				}
				
				//$price_range = array_unique($price_range);

				$price_range= array($min, $max);				
				set_transient($transient_name, $price_range, 3600);  //1 hour
				
			} else {
				//use the transient
				$min = $price_range[0];
				$max = $price_range[1];
			}
				
			
			$this->params['min_price'] = $min;
			$this->params['max_price'] = $max;
			
			
							
			//lets get in on the product action
			add_action('woocommerce_product_query', array($this, 'productQuery'));
		}
		
		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget( $args, $instance ) {
			
			//get outta here if we are not on the right page
			if (!is_post_type_archive('product') && !is_tax(get_object_taxonomies('product'))) {
				return;
			}

			
			//Include the various JS, CSS we need
			// frontend sctipts
			wp_enqueue_script('jem-pflite-main');
			wp_enqueue_script('jquery-ui-slider');
			wp_enqueue_style('jem-jquery-ui');
			wp_enqueue_style('jem-pflite-css');
			wp_enqueue_style('jem-font-awesome');
			wp_enqueue_script('jem-pflite-select2');
			wp_enqueue_style('jem-select2-css');
				
			//localize our vars
			wp_localize_script('jem-pflite-main', 'jempflite_params', $this->params);
			
			
			//OK lets get the options for this widget
			$this->show_price_filter  = ( ! empty( $instance['show_price_filter'] ) ) ? $instance['show_price_filter']  : false;
			$this->show_cat_filter = ( ! empty( $instance['show_cat_filter'] ) ) ? $instance['show_cat_filter']  : false;
			$this->show_cat_count = ( ! empty( $instance['show_cat_count'] ) ) ? $instance['show_cat_count']  : false;
			$this->hide_cat_empty = ( ! empty( $instance['hide_cat_empty'] ) ) ? $instance['hide_cat_empty']  : false;
			$this->attribute_to_show = ( ! empty( $instance['attribute_to_show'] ) ) ? $instance['attribute_to_show']  : false;
			$this->attribute_title= ( ! empty( $instance['attribute_title'] ) ) ? $instance['attribute_title']  : false;
				
			echo $args['before_widget'];
			if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
			}
			
			//Price filter?
			if($this->show_price_filter){
				echo '<label for="jempflite-amount">Price range:</label><BR>';
				echo '<div id="jempflite-price-slider"></div>';
				echo '<div id="jempflite-amount"></div>';
			}
			
			
			//Category filter?
			if($this->show_cat_filter){
				$cats = $this->category_tree(0);
				echo '<div id="jempflite-categories">';
				if( count($cats) > 0){
				
					//get the list of cats that are on the url into an array
					$cat = isset($_GET['pcat']) ? $_GET['pcat'] : "";
					$this->cats_in_url = explode(',', $cat);
				
					echo '<ul>';
					$this->display_children($cats);
					echo '</ul>';
				}
				echo '</div>';
				
			}	
						
			
			//Show the attribute filter?
			if($this->attribute_to_show != "JEM_NO_ATTR"){
				//let load all attribute values for this attribute
				
				//Get the woo name
				$woo_taxonomy_name = wc_attribute_taxonomy_name( $instance['attribute_to_show'] );
				$orderby = wc_attribute_orderby( $woo_taxonomy_name );
				
				//now lets get all the attributes
				$args = array( 'hide_empty' => '1' );
				
				switch ( $orderby ) {
					case 'name' :
						$args['orderby']    = 'name';
						$args['menu_order'] = false;
						break;
					case 'id' :
						$args['orderby']    = 'id';
						$args['order']      = 'ASC';
						$args['menu_order'] = false;
						break;
					case 'menu_order' :
						$args['menu_order'] = 'ASC';
						break;
				}
				
				$terms = get_terms( $woo_taxonomy_name, $args );

				
				//do we have a title
				if($this->attribute_title){
					$title_html = '<BR><label for="jempflite-amount">' . $this->attribute_title . '</label><BR>';
						
				} else {
					$title_html = "";
				}
				
				$title_html .= "<select id='jempflite-attribute-select' multiple='multiple' class='jempflite-{$instance['attribute_to_show']}'>";
				
				if(count($terms) > 0){
					
					echo $title_html;
					$title_html = "";
					
					foreach ($terms as $term){
						// Get count based on current view - uses transients
						$transient_name = 'wc_ln_count_' . md5( sanitize_key( $woo_taxonomy_name ) . sanitize_key( $term->term_taxonomy_id ) );
						
						if ( false === ( $_products_in_term = get_transient( $transient_name ) ) ) {
						
							$_products_in_term = get_objects_in_term( $term->term_id, $woo_taxonomy_name );
						
							set_transient( $transient_name, $_products_in_term );
						}
						
						
						//now add them in
						//need to cater if the item is already selected! - MAYBE
						
						$html = "<option value='{$term->term_id}'>{$term->name}</option> ";
						echo $html;
						
					}

						
				}

				if(count($terms) > 0){
					echo "</select>";
				}
				
			}
			
		}
		
		
		/**
		 * Intercepts the product query and add's our values onto it
		 * Inspired by http://www.kathyisawesome.com/woocommerce-modifying-product-query/
		 * @param unknown $q
		 */
		public function productQuery($q){
				
			//TODO add check to make sure we only do this on the right pages
			
			
			//Lets see if we have any prices!
			$min = (isset($_GET['min-price'])) ?  $_GET['min-price'] : null;
			$max = (isset($_GET['max-price'])) ?  $_GET['max-price'] : null;
			$meta_query = $q->get( 'meta_query' );

			//do we have one?
			if($min or $max){
				//lets set based on which prices are set
				if($min && $max) {
					$meta_query[] = array(
							'key'     => '_price',
							'value'   => array(
									$min ,
									$max
							),
							'compare' => 'BETWEEN',
							'type'=> 'NUMERIC'
					);
					
				} elseif ($min){
					$meta_query[] = array(
							'key'     => '_price',
							'value'   => $min,
							'compare' => '>=',
							'type'=> 'NUMERIC'
					);
					
				} else {
					
					$meta_query[] = array(
							'key'     => '_price',
							'value'   => $max,
							'compare' => '<=',
							'type'=> 'NUMERIC'
					);
				}
			
			}
				
			
			
			//now see if we have any product categories
			$tax_query = $q->get( 'tax_query' );
			$cats = (isset($_GET['pcat'])) ?  $_GET['pcat'] : null;
			
			if($cats){
				
				$cats_array = explode(',', $cats);
					
				$tax_query[]  = array(
						'taxonomy' => 'product_cat',
						'field' => 'id',
						'terms' => $cats_array,
						'operator' => 'IN'
				);
				
				
			}	

			
			//OK any ATTRIBUTE filters?
			$filter_values = (isset($_GET['filter-value'])) ?  $_GET['filter-value'] : null;
			$attribute_to_filter = (isset($_GET['attr-query'])) ?  $_GET['attr-query'] : null;
			
			if($attribute_to_filter && $filter_values){
				//hooray we have an atrribute filter

				//need to stick the pa_ on the front
				$attribute_to_filter = 'pa_' . $attribute_to_filter;
				//this will hold all the products that match this attribute 
				$product_list = array();
				
				// get the values we want to filter
				$atts_array = explode(',', $filter_values);
				
				//CONTINUE HERE
				foreach ($atts_array as $value) {
					$posts = get_posts(
							array(
									'post_type'     => 'product',
									'numberposts'   => -1,
									'post_status'   => 'publish',
									'fields'        => 'ids',
									'no_found_rows' => true,
									'tax_query'     => array(
											array(
													'taxonomy' => $attribute_to_filter,
													'terms'    => $value,
													'field'    => 'term_id'
											)
									)
							)
					);
				
					
					if (!is_wp_error($posts)) {
						if (sizeof($product_list) > 0 ) {
							$product_list = array_merge($posts, $product_list);
						} else {
							$product_list = $posts;
						}
				
					}
				}
				
				//ok if we have some results lets add them into the query
				if(sizeof($product_list > 0)){
					$q->set('post__in', $product_list);
						
				}
				
			}
				
			
			$q->set( 'meta_query', $meta_query );
			$q->set( 'tax_query', $tax_query);
		}
		
		
		
		
		/**
		 * Back-end widget form.
		 *
		 * @see WP_Widget::form()
		 *
		 * @param array $instance Previously saved values from database.
		 */
		public function form( $instance ) {
			
			
			//Lets get the list of attributes, borrowed from Woo
			$attribute_array      = array();
			$attribute_taxonomies = wc_get_attribute_taxonomies();

			$sel = (!empty($instance['attribute_to_show'])) ? $instance['attribute_to_show'] : "";
			$html = "<option class='widefat' value='JEM_NO_ATTR' " . selected($sel, 'JEM_NO_ATTR', false) . ">" . __('None', JEM_PFLITE_DOMAIN) .  "</option>";
			
			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $tax ) {
					if ( taxonomy_exists( wc_attribute_taxonomy_name( $tax->attribute_name ) ) ) {
						$attribute_array[ $tax->attribute_name ] = $tax->attribute_name;
						$sel = (!empty($instance['attribute_to_show'])) ? $instance['attribute_to_show'] : "";
						$html .= "<option class='widefat' value='{$tax->attribute_name}' " . selected($sel, $tax->attribute_name, false) . ">{$tax->attribute_name}</option>";
					}
				}
			}
			
			//Widet title
			if ( isset( $instance[ 'title' ] ) ) {
				$title = $instance[ 'title' ];
			}
			else {
				$title = __( 'New title', JEM_PFLITE_DOMAIN );
			}

			//Atribute filter title
			if ( isset( $instance[ 'attribute_title' ] ) ) {
				$attribute_title = $instance[ 'attribute_title' ];
			}
			else {
				$attribute_title = __( 'Attribute Filter Title', JEM_PFLITE_DOMAIN );
			}			
			
			?>
<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
	<input class="widefat"
		id="<?php echo $this->get_field_id( 'title' ); ?>"
		name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
		value="<?php echo esc_attr( $title ); ?>">
</p>
<p>
	<input id="<?php echo $this->get_field_id('show_price_filter'); ?>"
		name="<?php echo $this->get_field_name('show_price_filter'); ?>"
		type="checkbox" value="1"
		<?php echo (!empty($instance['show_price_filter']) && $instance['show_price_filter'] == true) ? 'checked="checked"' : ''; ?>>
	<label for="<?php echo $this->get_field_id('show_price_filter'); ?>"><?php printf(__('Show Price Filter', JEM_PFLITE_DOMAIN)); ?></label>
</p>
<p>
	<input id="<?php echo $this->get_field_id('show_cat_filter'); ?>"
		name="<?php echo $this->get_field_name('show_cat_filter'); ?>"
		type="checkbox" value="1"
		<?php echo (!empty($instance['show_cat_filter']) && $instance['show_cat_filter'] == true) ? 'checked="checked"' : ''; ?>>
	<label for="<?php echo $this->get_field_id('show_cat_filter'); ?>"><?php printf(__('Show Category Filter', JEM_PFLITE_DOMAIN)); ?></label>
</p>
<p>
	<input id="<?php echo $this->get_field_id('show_cat_count'); ?>"
		name="<?php echo $this->get_field_name('show_cat_count'); ?>"
		type="checkbox" value="1"
		<?php echo (!empty($instance['show_cat_count']) && $instance['show_cat_count'] == true) ? 'checked="checked"' : ''; ?>>
	<label for="<?php echo $this->get_field_id('show_cat_count'); ?>"><?php printf(__('Show Count of Products in Category', JEM_PFLITE_DOMAIN)); ?></label>
</p>
<p>
	<input id="<?php echo $this->get_field_id('hide_cat_empty'); ?>"
		name="<?php echo $this->get_field_name('hide_cat_empty'); ?>"
		type="checkbox" value="1"
		<?php echo (!empty($instance['hide_cat_empty']) && $instance['hide_cat_empty'] == true) ? 'checked="checked"' : ''; ?>>
	<label for="<?php echo $this->get_field_id('hide_cat_empty'); ?>"><?php printf(__('Hide Empty Categories', JEM_PFLITE_DOMAIN)); ?></label>
</p>
<p>
	<b><?php printf( __('Attribute Filter', JEM_PFLITE_DOMAIN)); ?></b><br>
	<label for="<?php echo $this->get_field_id('attribute_to_show'); ?>"><?php printf(__('Which Attribute to Filter', JEM_PFLITE_DOMAIN)); ?></label>
	<select class="widefat"
		id="<?php echo $this->get_field_id('attribute_to_show'); ?>"
		name="<?php echo $this->get_field_name('attribute_to_show'); ?>">
		<?php echo $html; ?>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id('attribute_title'); ?>"><?php printf(__('Ttile for Attribute Filter', JEM_PFLITE_DOMAIN)); ?></label>
	<input class="widefat"
		name="<?php echo $this->get_field_name( 'attribute_title' ); ?>"
		type="text" value="<?php echo esc_attr( $attribute_title ); ?>">
</p>
<?php
	}
	
	
	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['show_price_filter'] = ( ! empty( $new_instance['show_price_filter'] ) ) ? strip_tags( $new_instance['show_price_filter'] ) : '';
		$instance['show_cat_filter'] = ( ! empty( $new_instance['show_cat_filter'] ) ) ? strip_tags( $new_instance['show_cat_filter'] ) : '';
		$instance['show_cat_count'] = ( ! empty( $new_instance['show_cat_count'] ) ) ? strip_tags( $new_instance['show_cat_count'] ) : '';
		$instance['hide_cat_empty'] = ( ! empty( $new_instance['hide_cat_empty'] ) ) ? strip_tags( $new_instance['hide_cat_empty'] ) : '';
		$instance['attribute_to_show'] = ( ! empty( $new_instance['attribute_to_show'] ) ) ? strip_tags( $new_instance['attribute_to_show'] ) : '';
		$instance['attribute_title'] = ( ! empty( $new_instance['attribute_title'] ) ) ? strip_tags( $new_instance['attribute_title'] ) : '';
		return $instance;
	}
	
	
	

	//Builds the hierarchical category tree
	//Calles recursively
	public function category_tree($category) {

	
		$args = array(
				'taxonomy'     => 'product_cat',
				'orderby'      => 'name',
				'show_count'   => 1,
				'pad_counts'   => 0,
				'hierarchical' => 1,
				'title_li'     => '',
				'hide_empty'   => 0,
				'parent'	   => $category
		);		
		
		$next = get_categories ( $args );
		$temp = array();
		
		if ($next){
			
			//go thru each one and recursively call
			foreach ( $next as $cat ){
				$temp[$cat->term_id] = array(
						"title" =>  $cat->name,
						"count" => $cat->count,
						"children" => $this->category_tree($cat->term_id)
						); 
			}
			
		    
		}
		
		return $temp;

	}
	
	//prints the hierarchical tree
	//called recursively
	public function display_children($items, $class = ""){
		
		
		echo '<ul class="' . $class . '">';
		
		foreach ($items as $id => $cat){

			//If this category is empty and our flag is set, don't show!
			
			if( $this->hide_cat_empty && ( $cat['count'] == 0 )  ){
				continue;
			}
			
			//Is this category in the url??
			if( in_array($id, $this->cats_in_url) ){
				$checked = 'class="jempflite-checked"';
			} else {
				$checked = '';
			}

			//Do we show the count
			$show_count = ( $this->show_cat_count ) ? ('<span class="count">[' . $cat['count'] . ']</span>') : "";
				
		echo '<li ' . $checked . '><a href="#" jempflite-key="pcat" jempflite-value="' . $id . '">' . $cat['title'] . '</a> ' . $show_count . '</li>';
		
			if( count( $cat['children'] ) > 0){
				$this->display_children($cat['children'], 'jempflite-child');
			}
		}
		
		
		echo '</ul>';	
	}
	
	
	
	
} // class JEM_PFLITE_WIDGET