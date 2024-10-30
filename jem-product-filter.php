<?php
/*
 Plugin Name: JEM WooCommerce Product filter
 Plugin URI: http://www.jem-products.com
 Description: WooCommerce AJAX Product Filter.
 Version: 1.1
 Author: JEM Plugins
 Author URI: http://www.jem-products.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Some constants

define ( 'JEM_PFLITE_PLUGIN_PATH' , plugin_dir_path( __FILE__ ) );
define ( 'JEM_PFLITE_PLUGIN_URL' , plugin_dir_url( __FILE__ ) );

define('JEM_PFLITE_DOMAIN', 'jem-product-filter-lite');

/**
 * JEM Product Filter main class
 */
if (!class_exists('JEMProductFilter')) {
	class JEMProductFilter
	{
		public function __construct(){
			
			$this->init();
			
		}
		
		//Handles the initialisation
		public function init(){
			
			//Add some woo version checking here
			
			//Inlcude the right files
			$this->add_includes();
			
			//load our settings/defaults
			$this->settings = get_option('jempflite_settings');
			//Set the defaults for our settings
			$defaults = array(
					'custom_loop_container'  => '.jempflite-before-shop',
					'shop_loop_container'  => '.products'
			);
				
			
			//now set any defaults
			$this->settings = wp_parse_args($this->settings, $defaults);
				
			
			//Setup the hooks to add stuff into the html
			$this->add_hooks();
			
			add_action('wp_enqueue_scripts', array($this, 'mainJscripts'));
		}
		
		public function add_includes(){
			require_once(JEM_PFLITE_PLUGIN_PATH . 'includes/jem-pflite-widget.php');
		}
		
		public function mainJscripts(){
			$script = JEM_PFLITE_PLUGIN_URL . 'includes/main.js';
			wp_register_script('jem-pflite-main', $script);
			wp_register_style('jem-pflite-css', JEM_PFLITE_PLUGIN_URL . 'includes/css/jempflite.css');
				
			wp_register_style('jem-jquery-ui', JEM_PFLITE_PLUGIN_URL . 'includes/css/jquery-ui.css');
			wp_register_style('jem-font-awesome', JEM_PFLITE_PLUGIN_URL . 'includes/css/font-awesome.min.css');

			//select2
			$script = JEM_PFLITE_PLUGIN_URL . 'includes/select2.js';
			wp_register_script('jem-pflite-select2', $script);
			wp_register_style('jem-select2-css', JEM_PFLITE_PLUGIN_URL . 'includes/css/select2.css');
				
			//faking settings for now!
			//$this->settings['']
			//load our vars into the jscript
			wp_localize_script('jem-pflite-main', 'jempflite_vars', $this->settings);
				
		}
		
		/**
		 * Sets up the hooks to put our html around woo
		 */
		public function add_hooks(){
			add_action('woocommerce_before_shop_loop', array($this, 'beforeShopLoop'), 0);
			add_action('woocommerce_after_shop_loop', array($this, 'afterShopLoop'), 200);

		}

		/**
		 * Wrap the container - could do it jscript but I think more robust this way...
		 */
		public function beforeShopLoop(){
			//remove the first . from the setting
			$class = ltrim($this->settings['custom_loop_container'], '.');
			
			echo "<div class='" . $class . "' style='overflow: hidden;'>";
		}
		
		public function afterShopLoop(){
			echo '</div>';
		}
		

		
		/**
		 * Takes a list of product ids's and gets the price range for them
		 * @param unknown $products
		 */
		public function getPriceRangeForProducts($products){
			$price_range = array();
			
			foreach ($products as $id) {
				$meta_value = get_post_meta($id, '_price', true);
			
				if ($meta_value) {
					$price_range[] = $meta_value;
				}
			
				// for child posts
				$product_variation = get_children(
						array(
								'post_type'   => 'product_variation',
								'post_parent' => $id,
								'numberposts' => -1
						)
				);
			
				if (sizeof($product_variation) > 0) {
					foreach ($product_variation as $variation) {
						$meta_value = get_post_meta($variation->ID, '_price', true);
						if ($meta_value) {
							$price_range[] = $meta_value;
						}
					}
				}
			}
			
			$price_range = array_unique($price_range);
			
			return $price_range;
			
		}
		
	}
	
}


/**
 * Instantiate main plugin class - GLOBAL
 */
global $jem_pflite;
$jem_pflite = new JEMProductFilter();


?>