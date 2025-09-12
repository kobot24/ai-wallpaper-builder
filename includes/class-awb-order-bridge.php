<?php
/**
 * Class AWB_Order_Bridge
 *
 * This class provides the functionality formerly supplied by the separate
 * "AI Wallpaper – Order Bridge" add‑on. It saves the generated image on
 * the server when the customer clicks the "Bild verwenden" button, stores
 * the resulting URL in the session, and attaches the link to both the cart
 * item and the final order. It also optionally redirects the user to the
 * cart after adding to cart. The assets (JS/CSS) are enqueued only on
 * single product pages and use the core plugin constants to locate the
 * files within the merged plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AWB_Order_Bridge {
    /**
     * A version string for cache busting. We re‑use AWB_VER so the
     * entire plugin shares the same version number. When bumping the main
     * plugin version, this value will automatically update.
     */
    const VER = AWB_VER;

    /**
     * Session key used to temporarily hold the last generated image URL.
     */
    const SESSION_KEY = 'awb_last_image_url';

    /**
     * Nonce key used for the AJAX request that saves the image. Changing
     * this will require updating the JS localisation as well.
     */
    const NONCE = 'awb_bridge_nonce';

    /**
     * Constructor. Hooks into WordPress and WooCommerce actions/filters.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
        add_action( 'wp_ajax_awb_bridge_save_image', array( $this, 'ajax_save_image' ) );
        add_action( 'wp_ajax_nopriv_awb_bridge_save_image', array( $this, 'ajax_save_image' ) );

        // Attach the saved image URL to the cart item and order item.
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

        // Allow optional redirect to the cart after adding to cart.
        add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'maybe_redirect_to_cart' ) );

        // Thumbnail hooks moved to class-awb-woo.php to avoid conflicts
        // The main AWB plugin now handles thumbnail display for cropped images
    }

    /**
     * Enqueue the bridge assets on single product pages. These files live in
     * the main plugin's assets directory. We rely on the AWB_URL constant to
     * resolve the correct URL, which points to the root of this merged plugin.
     */
    public function enqueue() {
        if ( ! is_product() ) {
            return;
        }
        // Enqueue the bridge script. The handle intentionally differs from
        // the core plugin handles to avoid conflicts.
        wp_enqueue_script(
            'awb-bridge',
            AWB_URL . 'assets/js/awb-bridge.js',
            array( 'jquery' ),
            self::VER,
            true
        );
        // Enqueue the bridge styles.
        wp_enqueue_style(
            'awb-bridge',
            AWB_URL . 'assets/css/awb-bridge.css',
            array(),
            self::VER
        );
        // Localise the script with runtime data.
        wp_localize_script(
            'awb-bridge',
            'AWB_BRIDGE',
            array(
                'ajax'       => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( self::NONCE ),
                'redir'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
                'sessionKey' => self::SESSION_KEY,
            )
        );
    }

    /**
     * Ensure a directory exists. Wrapper around wp_mkdir_p().
     *
     * @param string $path Absolute path to create.
     */
    private function ensure_dir( $path ) {
        if ( ! file_exists( $path ) ) {
            wp_mkdir_p( $path );
        }
    }

    /**
     * AJAX handler: Save an image (data URI or external URL) to the uploads
     * directory. The image is stored under ai-wallpaper/<session>/ and
     * inserted into the Media Library. On success, returns the public URL
     * and the attachment ID.
     */
    public function ajax_save_image() {
        check_ajax_referer( self::NONCE, 'nonce' );

        $img_url = isset( $_POST['img'] ) ? esc_url_raw( $_POST['img'] ) : '';
        if ( ! $img_url ) {
            wp_send_json_error( array( 'msg' => __( 'Kein Bild übergeben.', 'ai-wallpaper-builder' ) ) );
        }

        // Determine image bytes (either data URI or remote HTTP(S) URL).
        $bytes = '';
        if ( strpos( $img_url, 'data:image/' ) === 0 ) {
            $parts = explode( ',', $img_url, 2 );
            $bytes = base64_decode( isset( $parts[1] ) ? $parts[1] : '' );
        } else {
            // Only allow http(s) URLs.
            if ( ! preg_match( '#^https?://#i', $img_url ) ) {
                wp_send_json_error( array( 'msg' => __( 'Ungültige Bild‑URL.', 'ai-wallpaper-builder' ) ) );
            }
            // Fetch the remote image.
            $resp = wp_remote_get( $img_url, array( 'timeout' => 30 ) );
            if ( is_wp_error( $resp ) ) {
                wp_send_json_error( array( 'msg' => $resp->get_error_message() ) );
            }
            $bytes = wp_remote_retrieve_body( $resp );
        }

        if ( ! $bytes ) {
            wp_send_json_error( array( 'msg' => __( 'Bild konnte nicht gelesen werden.', 'ai-wallpaper-builder' ) ) );
        }

        // Build a unique upload path: uploads/ai-wallpaper/<session>/<file>.
        $up  = wp_get_upload_dir();
        $sid = substr( wp_hash( session_id() . '-' . time() ), 0, 12 );
        $dir = trailingslashit( $up['basedir'] ) . 'ai-wallpaper/' . $sid . '/';
        $this->ensure_dir( $dir );

        $filename  = 'aiw-' . date( 'Ymd-His' ) . '-' . wp_generate_uuid4() . '.png';
        $full_path = $dir . $filename;
        $ok        = file_put_contents( $full_path, $bytes );
        if ( ! $ok ) {
            wp_send_json_error( array( 'msg' => __( 'Speichern fehlgeschlagen.', 'ai-wallpaper-builder' ) ) );
        }

        $url = trailingslashit( $up['baseurl'] ) . 'ai-wallpaper/' . $sid . '/' . $filename;

        // Store the URL in the WooCommerce session for the next add‑to‑cart action.
        if ( function_exists( 'WC' ) ) {
            WC()->session->set( self::SESSION_KEY, $url );
        } else {
            // Fallback: store in a transient keyed by user ID.
            set_transient( self::SESSION_KEY . '_' . get_current_user_id(), $url, 60 * 60 );
        }

        // Insert into the Media Library so that the admin can access the file.
        $attachment_id = 0;
        $filetype      = wp_check_filetype( $filename, null );
        $attachment    = array(
            'guid'           => $url,
            'post_mime_type' => $filetype['type'] ?: 'image/png',
            'post_title'     => sanitize_file_name( $filename ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $attachment_id = wp_insert_attachment( $attachment, $full_path );
        if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata( $attachment_id, $full_path );
            wp_update_attachment_metadata( $attachment_id, $attach_data );
        }

        wp_send_json_success( array( 'url' => esc_url_raw( $url ), 'attachment_id' => intval( $attachment_id ) ) );
    }

    /**
     * Copy the stored image URL from the session to the cart item data. This is
     * called when a product is added to the cart. After reading the URL, it
     * clears the session key so subsequent adds do not re‑use the old URL.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id     The product ID being added.
     * @param int   $variation_id   The variation ID being added.
     * @return array Modified cart item data.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        // Only act if WooCommerce session is available.
        if ( function_exists( 'WC' ) ) {
            $url = WC()->session->get( self::SESSION_KEY );
            if ( $url ) {
                $cart_item_data['awb_image_url'] = esc_url_raw( $url );
                // Clear the session value for future add‑to‑cart events.
                WC()->session->set( self::SESSION_KEY, null );
            }
        }
        return $cart_item_data;
    }

    /**
     * Add the image URL as a meta field on the order item. This will display
     * a clickable link in the admin order screen. If the builder plugin also
     * stores its own image meta, both will be present; we do not interfere
     * with the builder’s behaviour.
     *
     * @param WC_Order_Item_Product $item The order item object.
     * @param string                $cart_item_key The cart item key.
     * @param array                 $values Additional cart item values.
     * @param WC_Order              $order The order object.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['awb_image_url'] ) ) {
            $url = esc_url_raw( $values['awb_image_url'] );
            // Add as a meta with a descriptive label. Using UTF‑8 hyphen for consistency.
            $item->add_meta_data( 'AI‑Wallpaper Bild', $url, true );
        }

        // Copy additional AI Wallpaper meta values from the cart to the order. These keys
        // are set when the user uploads their own image or generates via OpenAI with a
        // custom prompt. The values are attached as separate meta entries on the order
        // item so that they can be inspected in the admin order screen.
        if ( ! empty( $values['aiwallpaper'] ) && is_array( $values['aiwallpaper'] ) ) {
            $aiw = $values['aiwallpaper'];
            if ( ! empty( $aiw['user_image_url'] ) ) {
                $item->add_meta_data( 'AI‑Wallpaper Benutzerbild', esc_url_raw( $aiw['user_image_url'] ), true );
            }
            if ( ! empty( $aiw['openai_prompt'] ) ) {
                $item->add_meta_data( 'AI‑Wallpaper Prompt', sanitize_textarea_field( $aiw['openai_prompt'] ), true );
            }
            if ( isset( $aiw['ratio'] ) ) {
                $item->add_meta_data( 'AI‑Wallpaper Seitenverhältnis', sanitize_text_field( $aiw['ratio'] ), true );
            }
            if ( isset( $aiw['crop_x'] ) ) {
                $item->add_meta_data( 'AI‑Wallpaper Crop‑Offset', sanitize_text_field( $aiw['crop_x'] ), true );
            }
            if ( isset( $aiw['bahnen_aktiv'] ) ) {
                $item->add_meta_data( 'AI‑Wallpaper Bahnen Aktiv', sanitize_text_field( $aiw['bahnen_aktiv'] ), true );
            }
        }
    }

    /**
     * Optionally redirect to the cart after adding an item to the cart. This
     * is controlled by a hidden field named "awb_to_cart" in the product form
     * and is set by the bridge JavaScript when the user clicks "Bild verwenden".
     *
     * @param string $url The URL to redirect to after adding to cart.
     * @return string The (possibly modified) redirect URL.
     */
    public function maybe_redirect_to_cart( $url ) {
        if ( isset( $_REQUEST['awb_to_cart'] ) && $_REQUEST['awb_to_cart'] === '1' ) {
            return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $url;
        }
        return $url;
    }

    // cart_item_thumbnail method removed - now handled by class-awb-woo.php
}