<?php
if ( ! defined('ABSPATH') ) { exit; }

class AWB_Woo {
    public static function init(){
        add_action('woocommerce_before_calculate_totals', array(__CLASS__,'override_price'), 20);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__,'add_awb_order_item_meta'), 10, 4);
        
        // Use cropped image as thumbnail in cart and checkout
        add_filter('woocommerce_cart_item_thumbnail', array(__CLASS__, 'custom_cart_item_thumbnail'), 10, 3);
        add_filter('woocommerce_order_item_thumbnail', array(__CLASS__, 'custom_order_item_thumbnail'), 10, 2);
        
        // Restore AWB data from session
        add_filter('woocommerce_get_cart_item_from_session', array(__CLASS__, 'restore_awb_from_session'), 10, 3);
        
        // Update cropped image filename when order is created
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'update_cropped_image_filename'), 10, 1);
        
        // Debug logging removed - thumbnail conflict resolved
    }

    public static function override_price($cart){
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( empty( $item['awb'] ) ) {
                continue;
            }

            $product = $item['data'];
            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            
            // For variations, use the parent product ID. For simple products, use the product ID.
            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            $use_sqm_price = get_post_meta( $product_id, '_awb_use_sqm_price', true );
            
            // Only run sqm recalculation if:
            // 1) sqm pricing is enabled
            // 2) dimensions are present and > 0
            if ( ! $use_sqm_price || 
                 ! isset($item['awb']['width'], $item['awb']['height']) ||
                 $item['awb']['width'] <= 0 || 
                 $item['awb']['height'] <= 0 ) {
                continue;
            }

            $w = floatval( $item['awb']['width'] );
            $h = floatval( $item['awb']['height'] );
            // Convert centimetres to metres and compute area. Ensure a minimal non‑zero area
            // to prevent free products on extremely small inputs.
            $sqm = max( 0.0001, ( $w / 100.0 ) * ( $h / 100.0 ) );
            // Use the current product price as the price per square metre. When the square
            // metre pricing flag is enabled, the product price is treated as €/m².
            $price_per_sqm = floatval( $product->get_price() );
            $total         = $price_per_sqm * $sqm;
            if ( $total > 0 ) {
                $product->set_price( $total );
            }
        }
    }

    public static function add_awb_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty($values['awb']['cropped_image_url']) ) {
            $item->add_meta_data('_awb_cropped_image_url', $values['awb']['cropped_image_url'], true);
        }
        if ( ! empty($values['awb']['cropped_image_file']) ) {
            $item->add_meta_data('_awb_cropped_image_file', $values['awb']['cropped_image_file'], true);
        }

        // Add crop ratio and position for reference
        if ( ! empty( $values['awb']['ratio'] ) ) {
            $item->add_meta_data( 'Crop Ratio', floatval( $values['awb']['ratio'] ), true );
        }
        if ( ! empty( $values['awb']['crop_x'] ) ) {
            $item->add_meta_data( 'Crop Position X', floatval( $values['awb']['crop_x'] ), true );
        }

        // Include the width and height in centimetres as meta data so that they appear
        // on the order details screen. These values are stored in the cart item under
        // the 'awb' key.
        if ( ! empty( $values['awb']['width'] ) && ! empty( $values['awb']['height'] ) ) {
            $w = intval( $values['awb']['width'] );
            $h = intval( $values['awb']['height'] );
            $item->add_meta_data( __( 'Breite (cm)', 'ai-wallpaper-builder' ), $w );
            $item->add_meta_data( __( 'Höhe (cm)', 'ai-wallpaper-builder' ), $h );
        }

        // Add any dynamic custom fields defined in the builder. The fields are stored
        // as key/value pairs in the cart item under 'awb' => 'fields'. We loop
        // through them and add each as its own order item meta. Use ucfirst
        // and replace underscores with spaces to create a human readable label.
        if ( ! empty( $values['awb']['fields'] ) && is_array( $values['awb']['fields'] ) ) {
            foreach ( $values['awb']['fields'] as $key => $val ) {
                // Only show field if it has a value (not empty)
                if (!empty(trim($val))) {
                    $label = ucwords( str_replace( '_', ' ', $key ) );
                    $item->add_meta_data( $label, wc_clean( $val ) );
                }
            }
        }
    }
    
    /**
     * Replace cart item thumbnail with cropped image if available
     */
    public static function custom_cart_item_thumbnail( $thumbnail, $cart_item, $key ) {
        $url = $cart_item['awb']['cropped_image_url'] ?? '';
        if (! $url) return $thumbnail;
        
        $product_name = $cart_item['data']->get_name();
        return '<img src="'.esc_url($url).'" alt="'.esc_attr($product_name).'" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" style="width:64px;height:64px;object-fit:cover;" />';
    }
    
    /**
     * Restore AWB data from session
     */
    public static function restore_awb_from_session( $cart_item, $session_values, $key ) {
        if ( isset($session_values['awb']) && is_array($session_values['awb']) ) {
            $cart_item['awb'] = $session_values['awb'];
        }
        return $cart_item;
    }
    
    /**
     * Replace order item thumbnail with cropped image if available
     */
    public static function custom_order_item_thumbnail($thumbnail, $item) {
        // Get item meta data - check both old and new meta keys for backward compatibility
        $cropped_image = $item->get_meta('_awb_cropped_image_url');
        if (empty($cropped_image)) {
            $cropped_image = $item->get_meta('Cropped Image');
        }
        
        if (!empty($cropped_image)) {
            $product_name = $item->get_name();
            $thumbnail = '<img src="' . esc_url($cropped_image) . '" alt="' . esc_attr($product_name) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" style="width:64px;height:64px;object-fit:cover;" />';
        }
        
        return $thumbnail;
    }
    
    /**
     * Update cropped image filename when order is created
     */
    public static function update_cropped_image_filename($order_id) {
        if (!$order_id) return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        foreach ($order->get_items() as $item_id => $item) {
            $cropped_image_file = $item->get_meta('Cropped Image File');
            $cropped_image_url = $item->get_meta('Cropped Image');
            
            if (!empty($cropped_image_file) && !empty($cropped_image_url)) {
                // Check if filename doesn't already start with order ID
                if (strpos($cropped_image_file, $order_id . '_') !== 0) {
                    // Get upload directory
                    $upload_dir = wp_upload_dir();
                    $old_path = $upload_dir['path'] . '/' . $cropped_image_file;
                    
                    // Get size from item meta
                    $awb_data = $item->get_meta('awb');
                    $width = isset($awb_data['width']) ? intval($awb_data['width']) : 0;
                    $height = isset($awb_data['height']) ? intval($awb_data['height']) : 0;
                    $size_part = $width && $height ? $width . 'x' . $height . 'cm' : 'unknown_size';
                    
                    // Generate new filename
                    $new_filename = $order_id . '_awb_crop_' . $size_part . '_' . wp_generate_password(6, false) . '.jpg';
                    $new_path = $upload_dir['path'] . '/' . $new_filename;
                    $new_url = $upload_dir['url'] . '/' . $new_filename;
                    
                    // Rename file if it exists
                    if (file_exists($old_path)) {
                        if (rename($old_path, $new_path)) {
                            // Update item meta with new filename and URL
                            $item->update_meta_data('Cropped Image File', $new_filename);
                            $item->update_meta_data('Cropped Image', $new_url);
                            $item->save();
                            
                            error_log("AWB: Renamed cropped image from $cropped_image_file to $new_filename for order $order_id");
                        }
                    }
                }
            }
        }
    }
} 