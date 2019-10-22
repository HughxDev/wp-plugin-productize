<?php
/**
 * Plugin Name: Productize
 * Plugin URI: http://hughguiney.com/2015/blah
 * Description: List your products.
 * Version: 0.1
 * Author: Hugh Guiney
 * Author URI: http://hughguiney.com/
 * License: Public Domain
 */
global $wpdb;

global $productize_db_version;
$productize_db_version = '0.1';

global $productize_table_name;
$productize_table_name = $wpdb->prefix . 'productize_customers';

// global $productize_dir;
$productize_dir = plugin_dir_path( __FILE__ );

require_once 'vendor/autoload.php';

require_once 'vendor/PrintfulAPI-php/PrintfulClient.php';

define( 'PRINTFUL_API_KEY', 'pmh9685l-u7s9-jdz4:lfm6-1xy1eer2axe8' );

$printful = new PrintfulClient( PRINTFUL_API_KEY );

$stripeTest = array(
  "secret_key"      => "sk_test_F9moYIhNuB83bMBErafBtOym",
  "publishable_key" => "pk_test_8cdnJd80ozfHftchrYBV5mgg"
);

$stripeLive = array(
  "secret_key"      => "sk_live_ROpwAHHzHbFCWkc8qMD8f4fY",
  "publishable_key" => "pk_live_6B5N2RfmCNyHdrlzdxRQzuqn"
);

// \Stripe\Stripe::setApiKey( $stripeTest['secret_key'] );
\Stripe\Stripe::setApiKey( $stripeLive['secret_key'] );

// echo $wpdb->get_var( "show tables like '$productize_table_name'" );

// https://php-built.com/making-an-api-endpoint-in-wordpress-using-add_rewrite_rule/
function productize_api() {
  // http://wordpress.stackexchange.com/a/6895/37816
  global $wp_rewrite;
  
  $regex = 'productize-api/([^/]*)/?';
  $location = 'index.php?_productize_api_endpoint=$matches[1]';
  $priority = 'top';

  add_rewrite_rule( $regex, $location, $priority );

  $wp_rewrite->flush_rules( true );  // This should really be done in a plugin activation
}

function productize_query_vars( $vars ) {
  array_push( $vars, '_productize_api_endpoint' );

  return $vars;
}

function productize_api_template( $template ) {
  $endpoint = get_query_var( '_productize_api_endpoint', null );
  
  if ( $endpoint ) {
    $template = __DIR__ . '/api/' . $endpoint . '.php';
  }

  return $template;
}

function productize_install() {
  global $wpdb;
  global $productize_db_version;
  global $productize_table_name;
  
  $charset_collate = $wpdb->get_charset_collate();

  if ( $wpdb->get_var( "SHOW TABLES LIKE '$productize_table_name'" ) != $productize_table_name ) {
    $sql = "CREATE TABLE $productize_table_name (
      email varchar(254) DEFAULT '' NOT NULL,
      stripe_id varchar(255) DEFAULT NULL,
      stripe_token varchar(255) DEFAULT NULL,
      UNIQUE KEY email (email (191))
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    // $dbDelta = dbDelta( $sql );

    // var_dump( $dbDelta );

    $query = $wpdb->query( $sql );

    // exit( var_dump( $wpdb->last_query ) );

    // var_dump( $query )

    add_option( 'productize_db_version', $productize_db_version );

    // die('fuck you');
  }
}

function product_post_type() {  
  register_post_type(
    'product',
    array(
      'labels' => array(
        'name' => _x('Products', 'post type general name'),
        'singular_name' => _x('Product', 'post type singular name'),
        'add_new' => _x('Add New', 'Product'),
        'add_new_item' => __('Add New Product'),
        'edit_item' => __('Edit Product'),
        'new_item' => __('New Product'),
        'all_items' => __('All Products'),
        'view_item' => __('View Product'),
        'search_items' => __('Search Products'),
        'not_found' =>  __('No Products found'),
        'not_found_in_trash' => __('No Products found in Trash'), 
        'parent_item_colon' => '',
        'menu_name' => __('Products')
      ),
      'public' => true,
      'menu_position' => 10,
      'rewrite' => array('slug' => 'products', 'with_front' => false),
      'supports' => array('title', 'thumbnail', 'editor', 'excerpt'),
      'has_archive' => true //'products'
    )
  );
}

function set_product_icon() {
  if ( is_plugin_active( 'post-type-icons/post-type-icons.php' ) ) {
    pti_set_post_type_icon( 'product', 'money' );
    //add_filter( 'pti_plugin_show_admin_menu', '__return_false' );
  }
}

function product_types() {
  register_taxonomy(
    'product_type',
    'product',
    array(
      'hierarchical' => true,
      'label' => 'Product Types',
      'query_var' => true,
      'rewrite' => array(
        'slug' => 'types',
        'with_front' => false
      )
    )
  );
}

function products_post_class( $classes ) {
  if ( get_post_type() === 'product' ) {
    $classes = array_diff( $classes, array( 'hentry' ) );

    if ( !in_array( 'entry', $classes ) ) {
      $classes[] = 'entry';
    }
  }

  return $classes;
}

function productize_scripts() {
  wp_enqueue_script( 'stripe', 'https://checkout.stripe.com/checkout.js', array(), null, false );
  // wp_enqueue_script( 'stripe-button', plugin_dir_url( __FILE__ ) . '/script/stripe-button.js', array( 'stripe' ), null, true );
}

function productize_metabox_styles() {
  wp_enqueue_style( 'productize-wpalchemy-metabox', plugin_dir_url( __FILE__ ) . '/style/product_meta.css' );
}

function productize_settings_html() {
  echo '<p>test</p>';
}

function productize_add_submenu() {
  add_submenu_page( 'edit.php?post_type=product', 'Settings', 'Productize Settings', 'manage_options', 'productize-settings', 'productize_settings_html' );
}

function getPrintfulVariantFromSpecs( $productID, $size, $color ) {
  global $printful;
  
  $variants = $printful->get( 'products/' . $productID )['variants'];

  $size = strtolower( $size );

  $color = strtolower( $color );

  if ( count( $variants ) ) {
    foreach ( $variants as $variantIndex => $variant ) {
      if (
        ( strtolower( $variant['size'] ) == $size ) &&
        ( ( strtolower( $variant['color'] ) == $color ) || ( strtolower( $variant['color_code'] ) == $color ) )
      ) {
        return $variant;
      }
    }
  }

  return false;
}

function getPrintfulShippingRates( $countryCode = 'US', $stateCode = NULL, $zipCode = NULL, array $items ) {
  global $printful;

  $recipient = array();

  if ( !isset( $countryCode ) || empty( $countryCode ) ) {
    $recipient['country_code'] = 'US';
  } else {
    $recipient['country_code'] = $countryCode;
  }

  if ( isset( $stateCode ) ) {
    $recipient['state_code'] = $stateCode;
  }

  if ( isset( $zipCode ) ) {
    $recipient['zip'] = $zipCode;
  }

  // var_dump( $recipient );

  // var_dump( $items );

  $rates = $printful->post( 'shipping/rates',
    array(
      'recipient' => $recipient,
      'items' => $items
      /*array(
        array(
          'variant_id' => 1118,
          'quantity' => 1
        )
      )*/
    )
  );
  /*array(
      'expedited' => '1'
    )*/

  return $rates;
}

// if ( is_plugin_active( WP_CONTENT_DIR . '/wpalchemy/MetaBox.php' ) ) {
  $product_integrations = new WPAlchemy_MetaBox(array(
    'id' => '_product_integrations',
    'title' => 'Integrations',
    'types' => array( 'product' ), // added only for pages and to custom post type "events"
    'context' => 'normal', // same as above, defaults to "normal"
    'priority' => 'high', // same as above, defaults to "high"
    'template' => get_stylesheet_directory() . '/metaboxes/product-integrations.php',
    'mode' => WPALCHEMY_MODE_EXTRACT,
    'prefix' => '_productize_'
  ));

  $product_inventory = new WPAlchemy_MetaBox(array(
    'id' => '_product_inventory',
    'title' => 'Inventory Management',
    'types' => array( 'product' ), // added only for pages and to custom post type "events"
    'context' => 'normal', // same as above, defaults to "normal"
    'priority' => 'high', // same as above, defaults to "high"
    'template' => get_stylesheet_directory() . '/metaboxes/product-inventory.php',
    'mode' => WPALCHEMY_MODE_EXTRACT,
    'prefix' => '_productize_'
  ));

  $product_shipping = new WPAlchemy_MetaBox(array(
    'id' => '_product_shipping',
    'title' => 'Shipping Information',
    'types' => array( 'product' ), // added only for pages and to custom post type "events"
    'context' => 'normal', // same as above, defaults to "normal"
    'priority' => 'high', // same as above, defaults to "high"
    'template' => get_stylesheet_directory() . '/metaboxes/product-shipping.php',
    'mode' => WPALCHEMY_MODE_EXTRACT,
    'prefix' => '_productize_'
  ));
// }

// Activation
register_activation_hook( __FILE__, 'productize_install' );

// Actions
add_action( 'init', 'product_post_type' );
add_action( 'init', 'product_types' );
add_action( 'admin_init', 'productize_api' );
add_action( 'admin_init', 'set_product_icon' );
add_action( 'wp_enqueue_scripts', 'productize_scripts' );
add_action( 'admin_enqueue_scripts', 'productize_metabox_styles' );
// add_term_meta( 'product_type' );
// add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
add_action( 'admin_menu', 'productize_add_submenu' );

// Filters
add_filter( 'query_vars', 'productize_query_vars' );
add_filter( 'template_include', 'productize_api_template', 99 );