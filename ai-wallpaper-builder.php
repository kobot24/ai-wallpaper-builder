<?php
/**
 * Plugin Name: AI Wallpaper Builder
 * Plugin URI:  
 * Description: KI-generierte personalisierte Tapeten für WooCommerce
 * Version:     5.9.124-stable-2.2
 * Author:      
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AWB_VER', '5.9.124-stable-2.2' );
define('AWB_DIR', plugin_dir_path(__FILE__));
define('AWB_URL', plugin_dir_url(__FILE__));

require_once AWB_DIR . 'includes/class-awb-settings.php';
require_once AWB_DIR . 'includes/class-awb-admin.php';
require_once AWB_DIR . 'includes/class-awb-openai.php';
require_once AWB_DIR . 'includes/class-awb-frontend.php';
require_once AWB_DIR . 'includes/class-awb-ajax.php';
require_once AWB_DIR . 'includes/class-awb-woo.php';
// Load the order bridge functionality. This class implements the logic to save the generated
// image on the server when the user clicks "Bild verwenden" and attaches the link to the
// WooCommerce cart and order. Placing it here keeps all functionality in one plugin while
// avoiding duplicate class definitions if the old bridge plugin is still installed.
require_once AWB_DIR . 'includes/class-awb-order-bridge.php';

add_action('plugins_loaded', function(){
    AWB_Settings::init();
    AWB_Admin::init();
    AWB_Frontend::init();
    new AWB_Ajax(); // Initialize new AJAX handler
    AWB_Woo::init();
    // Instantiate the order bridge so that it hooks into WooCommerce and the front end.
    // The constructor registers all necessary actions and filters.
    if (class_exists('AWB_Order_Bridge')) {
        new AWB_Order_Bridge();
    }
});
