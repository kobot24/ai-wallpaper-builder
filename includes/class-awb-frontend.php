<?php
if ( ! defined('ABSPATH') ) { exit; }

class AWB_Frontend {

    public static function init(){
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render' ), 31 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_meta' ), 10, 2 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_display' ), 10, 2 );
    }

    public static function assets(){
        // Check if WooCommerce is loaded and we're on a product page
        if ( ! function_exists( 'WC' ) || ! is_product() ) { 
            error_log( 'AWB Debug Frontend: WooCommerce not loaded or not on product page' );
            return; 
        }
        
        // Wait for WooCommerce to be fully loaded
        if ( ! did_action( 'woocommerce_init' ) ) {
            error_log( 'AWB Debug Frontend: WooCommerce not yet initialized' );
            return;
        }
        wp_enqueue_style( 'awb-fonts', AWB_URL . 'assets/css/fonts.css', array(), AWB_VER );
        wp_enqueue_style( 'awb-ui-framework', AWB_URL . 'assets/css/ui-framework.css', array('awb-fonts'), AWB_VER );
        wp_enqueue_style( 'awb-frontend', AWB_URL . 'assets/css/frontend.css', array('awb-fonts', 'awb-ui-framework'), AWB_VER );
        wp_enqueue_style( 'aiw-modal', AWB_URL . 'assets/css/aiw-modal.css', array('awb-fonts', 'awb-ui-framework'), AWB_VER );

        // Load our simple canvas crop solution
        wp_enqueue_script( 'awb-canvas-crop', AWB_URL . 'assets/js/canvas-crop.js', array(), AWB_VER, false );
        wp_enqueue_script( 'aiw-modal', AWB_URL . 'assets/js/aiw-modal.js', array('awb-canvas-crop'), AWB_VER, false );

        wp_enqueue_script( 'awb-frontend', AWB_URL . 'assets/js/frontend.js', array( 'jquery' ), AWB_VER, true );
        
        // Make AJAX available for modal script
        wp_localize_script( 'aiw-modal', 'awb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('awb_frontend_nonce')
        ) );
        wp_enqueue_script( 'awb-variation-price', AWB_URL . 'assets/js/variation-price.js', array( 'jquery' ), AWB_VER, true );

        $price_per_sqm           = 0;
        $price_per_sqm_formatted = '';
        $base_price              = 0;
        $base_price_formatted    = '';
        $use_sqm_price           = true;
        $decimals        = wc_get_price_decimals();
        $currency_symbol = get_woocommerce_currency_symbol();

        global $product;
        if ( $product instanceof WC_Product ) {
            $base_price = (float) wc_get_price_to_display( $product );
            $base_price_formatted = $base_price ? wc_price( $base_price ) : '';
            if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
                $var_min = (float) $product->get_variation_price( 'min', true );
                if ( $var_min <= 0 ) { $var_min = (float) $product->get_variation_price( 'min', false ); }
                $price_per_sqm = $var_min;
            } else {
                $price_per_sqm = (float) $product->get_price();
                if ( $price_per_sqm <= 0 ) { $price_per_sqm = $base_price; }
            }
            if ( $price_per_sqm > 0 ) { $price_per_sqm_formatted = wc_price( $price_per_sqm ) . ' / m²'; }
        }

        $settings   = class_exists( 'AWB_Settings' ) ? AWB_Settings::get() : array();
        $blacklist_raw = isset($settings['blacklist']) ? $settings['blacklist'] : '';
        $blacklist      = array();
        if ( $blacklist_raw ) {
            $parts = preg_split('/[,\n]+/', $blacklist_raw);
            foreach ( $parts as $part ) { $word = trim( $part ); if ( $word !== '' ) $blacklist[] = $word; }
        }
        $tax_rate = 0;
        if ( function_exists( 'WC_Tax' ) ) {
            $rates = WC_Tax::get_base_tax_rates( $product ? $product->get_tax_class() : '' );
            if ( ! empty( $rates ) ) { $first = array_shift( $rates ); if ( isset( $first['rate'] ) ) { $tax_rate = floatval( $first['rate'] ); } }
        }
        $aiw_default_image = '';
        $aiw_use_openai    = false;
        $pid = 0; // Initialize pid variable
        
        // ENHANCED DEBUGGING - Log all product variables
        error_log( 'AWB Debug Frontend [' . date('Y-m-d H:i:s') . ']: Product Debug Info:' );
        error_log( 'AWB Debug Frontend: $product instanceof WC_Product: ' . ($product instanceof WC_Product ? 'YES' : 'NO') );
        
        if ( $product instanceof WC_Product ) {
            $pid_current = $product->get_id();
            $pid_parent = $product->get_parent_id();
            // Set pid to parent ID if exists, otherwise use current product ID
            $pid = $pid_parent ? $pid_parent : $pid_current;
            
            error_log( 'AWB Debug Frontend: $pid_current: ' . $pid_current );
            error_log( 'AWB Debug Frontend: $pid_parent: ' . $pid_parent );
            error_log( 'AWB Debug Frontend: Final $pid: ' . $pid );
            
            $raw_default_image = get_post_meta( $pid_current, '_aiw_default_image', true );
            if ( empty( $raw_default_image ) ) { if ( $pid_parent ) { $raw_default_image = get_post_meta( $pid_parent, '_aiw_default_image', true ); } }
            if ( is_string( $raw_default_image ) ) { $aiw_default_image = trim( str_replace( array( "\r", "\n" ), '', $raw_default_image ) ); }
            $aiw_use_openai = (bool) get_post_meta( $pid_current, '_aiw_use_openai', true );
            if ( ! $aiw_use_openai && $pid_parent ) { $aiw_use_openai = (bool) get_post_meta( $pid_parent, '_aiw_use_openai', true ); }
        } else {
            error_log( 'AWB Debug Frontend: WARNING: $product is NOT instanceof WC_Product!' );
            error_log( 'AWB Debug Frontend: $product type: ' . gettype($product) );
            error_log( 'AWB Debug Frontend: $product value: ' . print_r($product, true) );
            
            // ALTERNATIVE METHOD: Try to get product ID from URL or query
            if ( is_product() ) {
                $queried_object = get_queried_object();
                if ( $queried_object && isset($queried_object->ID) ) {
                    $pid = $queried_object->ID;
                    error_log( 'AWB Debug Frontend: Using queried_object->ID: ' . $pid );
                } else {
                    // Try to get from URL
                    $product_id = url_to_postid( $_SERVER['REQUEST_URI'] );
                    if ( $product_id ) {
                        $pid = $product_id;
                        error_log( 'AWB Debug Frontend: Using URL to post ID: ' . $pid );
                    } else {
                        error_log( 'AWB Debug Frontend: Could not determine product ID from any method!' );
                    }
                }
            }
        }
        // Debug log for product_id - FORCE WRITE WITH TIMESTAMP
        error_log( 'AWB Debug Frontend [' . date('Y-m-d H:i:s') . ']: product_id being sent to JavaScript: ' . $pid );
        // Also write to custom log file as backup
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', date('Y-m-d H:i:s') . ' - Frontend product_id: ' . $pid . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        wp_localize_script( 'awb-frontend', 'AWB', array(
            'ajax'                    => admin_url( 'admin-ajax.php' ),
            'nonce'                   => wp_create_nonce( 'awb_ajax' ),
            'product_id'              => $pid,
            'i18n'                    => array(
                'preview'  => __( 'Vorschau anzeigen', 'ai-wallpaper-builder' ),
                'apply'    => __( 'Bild verwenden', 'ai-wallpaper-builder' ),
                'revise'   => __( 'Korrektur einreichen', 'ai-wallpaper-builder' ),
                'send'     => __( 'Korrektur senden', 'ai-wallpaper-builder' ),
                'close'    => __( 'Schließen', 'ai-wallpaper-builder' ),
                'area'     => __( 'Fläche', 'ai-wallpaper-builder' ),
                'total'    => __( 'Gesamt', 'ai-wallpaper-builder' ),
                'original' => __( 'Original', 'ai-wallpaper-builder' ),
                'new'      => __( 'Neu', 'ai-wallpaper-builder' ),
            ),
            'use_sqm_price'           => $use_sqm_price,
            'price_per_sqm'           => (float) $price_per_sqm,
            'price_per_sqm_formatted' => $price_per_sqm_formatted,
            'base_price'              => (float) $base_price,
            'base_price_formatted'    => $base_price_formatted,
            'decimals'                => $decimals,
            'currency_symbol'         => $currency_symbol,
            'tax_rate'               => (float) $tax_rate,
            'shipping_text'         => __('zzgl. Versand', 'ai-wallpaper-builder'),
            'delivery_text'         => __('3 bis 5 Tage*', 'ai-wallpaper-builder'),
            'blacklist'               => $blacklist,
            'aiw_default_image'      => $aiw_default_image ? esc_url( $aiw_default_image ) : '',
            'aiw_use_openai'         => (bool) $aiw_use_openai,
        ) );
    }

    public static function enabled($pid){
        $flag = get_post_meta($pid, '_awb_enabled', true);
        if ($flag === '' || $flag === null) {
            $fields = get_post_meta($pid, '_awb_fields', true);
            if (is_array($fields) && !empty($fields)) {
                return true;
            }
        }
        return (bool) $flag;
    }

    public static function render(){
        if ( ! is_product() ) return;
        global $product;
        $pid = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        if ( ! self::enabled( $pid ) ) {
            return;
        }

        $w = intval(get_post_meta( $pid, '_awb_width', true ));  if ( ! $w ) { $w = 200; }
        $h = intval(get_post_meta( $pid, '_awb_height', true )); if ( ! $h ) { $h = 200; }
        $fields = get_post_meta( $pid, '_awb_fields', true ); if ( ! is_array( $fields ) ) { $fields = array(); }

        echo '<div class="awb-box">';
        echo '<div class="row size-row">'.'<label>'.esc_html__('Größe','ai-wallpaper-builder').'</label>'.'<div class="size-inputs">'.'<div class="awb-input-group">'.'<img class="icon" src="'.AWB_URL.'assets/img/icon-width.png'.'" alt="" aria-hidden="true">'.'<input type="number" name="awb_width" class="awb-width" value="'.esc_attr($w).'" min="1" placeholder="'.esc_attr__('Breite','ai-wallpaper-builder').'">'.'<span class="unit">cm</span>'.'</div>'.'<span class="size-sep">×</span>'.'<div class="awb-input-group">'.'<img class="icon" src="'.AWB_URL.'assets/img/icon-height.png'.'" alt="" aria-hidden="true">'.'<input type="number" name="awb_height" class="awb-height" value="'.esc_attr($h).'" min="1" placeholder="'.esc_attr__('Höhe','ai-wallpaper-builder').'">'.'<span class="unit">cm</span>'.'</div>'.'</div>'.'</div>';
        foreach($fields as $f){
            $key   = $f['key'];
            $label = $f['label'];
            $req   = ! empty( $f['required'] );
            if ($f['type'] === 'text'){
                $max   = ! empty( $f['maxlength'] ) ? intval( $f['maxlength'] ) : 0;
                $min   = ! empty( $f['minlength'] ) ? intval( $f['minlength'] ) : 0;
                printf(
                    '<div class="row"><label>%s%s</label><input type="text" name="awb_%s" placeholder="%s" %s %s %s></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    esc_attr( $f['placeholder'] ?? '' ),
                    $req ? 'required' : '',
                    $max ? 'maxlength="'. $max .'"' : '',
                    $min ? 'minlength="'. $min .'"' : ''
                );
            } elseif ($f['type'] === 'textarea'){
                $rows = max( 1, intval( $f['rows'] ?? 3 ) );
                printf(
                    '<div class="row"><label>%s%s</label><textarea name="awb_%s" rows="%d" placeholder="%s" %s></textarea></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    $rows,
                    esc_attr( $f['placeholder'] ?? '' ),
                    $req ? 'required' : ''
                );
            } elseif ($f['type'] === 'cards'){
                echo '<div class="row"><label>'. esc_html( $label ) . ( $req ? ' *' : '' ) .'</label><div class="cards">';
                $i = 0;
                foreach( (array) ( $f['options'] ?? array() ) as $url ){
                    $i++;
                    $checked = $i === 1 ? 'checked' : '';
                    echo '<label class="card"><input type="radio" name="awb_'. esc_attr( $key ) .'" value="'. esc_url( $url ) .'" '. $checked .'><img src="'. esc_url( $url ) .'" alt=""></label>';
                }
                echo '</div></div>';
            }
        }

        echo '<div class="awb-price">';
        echo '<div class="total-price"></div>';
        echo '<div class="sqm-price"></div>';
        echo '</div>';
        $show_preview_btn = (bool) get_post_meta( $pid, '_awb_show_preview_btn', true );
        if ( $show_preview_btn ) {
            echo '<div class="actions"><button type="button" class="button awb-preview">'. esc_html__( 'Vorschau anzeigen', 'ai-wallpaper-builder' ) .'</button></div>';
        }
        echo '<input type="hidden" name="aiwallpaper_user_image_url" class="aiw-hidden-meta aiw-user-image-url" value="">';
        echo '<input type="hidden" name="aiwallpaper_openai_prompt" class="aiw-hidden-meta aiw-openai-prompt" value="">';
        echo '<input type="hidden" name="aiwallpaper_ratio" class="aiw-hidden-meta aiw-ratio" value="">';
        echo '<input type="hidden" name="aiwallpaper_crop_x" class="aiw-hidden-meta aiw-crop-x" value="">';
        echo '<input type="hidden" name="aiwallpaper_bahnen_aktiv" class="aiw-hidden-meta aiw-bahnen" value="">';
        echo '</div>';

        $aiw_default_image_render = '';
        $aiw_use_openai_render    = false;
        $aiw_raw_image = get_post_meta( $pid, '_aiw_default_image', true );
        if ( is_array( $aiw_raw_image ) ) {
            $aiw_raw_image = isset( $aiw_raw_image[0] ) ? $aiw_raw_image[0] : '';
        }
        $aiw_default_image_render = trim( is_string( $aiw_raw_image ) ? str_replace( array( "\r", "\n" ), '', $aiw_raw_image ) : '' );
        $aiw_use_openai_render = (bool) get_post_meta( $pid, '_aiw_use_openai', true );
        echo '<input type="hidden" class="aiw-default-image-url" value="' . esc_attr( $aiw_default_image_render ) . '">';
        echo '<input type="hidden" class="aiw-use-openai" value="' . ( $aiw_use_openai_render ? '1' : '0' ) . '">';

        echo '<div id="awb-modal" class="awb-modal" style="display:none">';
        if ( ! wp_script_is( 'awb-canvas-crop', 'enqueued' ) && ! wp_script_is( 'awb-canvas-crop', 'done' ) ) {
            echo '<script id="awb-canvas-crop-fallback" src="'. esc_url( AWB_URL . 'assets/js/canvas-crop.js' ) .'"></script>';
        }
        echo '  <div class="awb-inner">';
        echo '    <div class="awb-top-bar">';
        echo '      <button type="button" class="awb-close awb-close-x" aria-label="'. esc_attr__( 'Schließen', 'ai-wallpaper-builder' ) .'">&times;</button>';
        echo '    </div>';
        echo '    <div class="awb-content">';
        echo '      <div class="awb-preview-col">';
        echo '        <div class="awb-result">';
        echo '          <div class="progress-overlay"><div class="spinner"></div><span class="pct">0%</span></div>';
        echo '          <div class="awb-preview-images"></div>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="awb-form-col">';
        echo '        <div class="awb-modal-fields">';
        echo '          ' . '<div class="row size-row">'.'<label>'.esc_html__('Größe','ai-wallpaper-builder').'</label>'.'<div class="size-inputs">'.'<div class="awb-input-group">'.'<img class="icon" src="'.AWB_URL.'assets/img/icon-width.png'.'" alt="" aria-hidden="true">'.'<input type="number" name="awb_width_modal" class="awb-width" value="'.esc_attr($w).'" min="1" placeholder="'.esc_attr__('Breite','ai-wallpaper-builder').'">'.'<span class="unit">cm</span>'.'</div>'.'<span class="size-sep">×</span>'.'<div class="awb-input-group">'.'<img class="icon" src="'.AWB_URL.'assets/img/icon-height.png'.'" alt="" aria-hidden="true">'.'<input type="number" name="awb_height_modal" class="awb-height" value="'.esc_attr($h).'" min="1" placeholder="'.esc_attr__('Höhe','ai-wallpaper-builder').'">'.'<span class="unit">cm</span>'.'</div>'.'</div>'.'</div>' . "
";        foreach($fields as $f){
            $key   = $f['key'];
            $label = $f['label'];
            $req   = ! empty( $f['required'] );
            if ($f['type'] === 'text'){
                $max   = ! empty( $f['maxlength'] ) ? intval( $f['maxlength'] ) : 0;
                $min   = ! empty( $f['minlength'] ) ? intval( $f['minlength'] ) : 0;
                $placeholder = isset($f['placeholder']) ? $f['placeholder'] : '';
                printf(
                    '<div class="row"><label>%s%s</label><input type="text" name="awb_%s_modal" placeholder="%s" %s %s %s></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    esc_attr( $placeholder ),
                    $req ? 'required' : '',
                    $max ? 'maxlength="'. $max .'"' : '',
                    $min ? 'minlength="'. $min .'"' : ''
                );
            } elseif ($f['type'] === 'textarea'){
                $rows = max( 1, intval( $f['rows'] ?? 3 ) );
                $placeholder = isset($f['placeholder']) ? $f['placeholder'] : '';
                printf(
                    '<div class="row"><label>%s%s</label><textarea name="awb_%s_modal" rows="%d" placeholder="%s" %s></textarea></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    $rows,
                    esc_attr( $placeholder ),
                    $req ? 'required' : ''
                );
            } elseif ($f['type'] === 'cards'){
                echo '<div class="row"><label>'. esc_html( $label ) . ( $req ? ' *' : '' ) .'</label><div class="cards">';
                $i = 0;
                foreach( (array) ( $f['options'] ?? array() ) as $url ){
                    $i++;
                    $checked = $i === 1 ? 'checked' : '';
                    echo '<label class="card"><input type="radio" name="awb_'. esc_attr( $key ) .'_modal" value="'. esc_url( $url ) .'" '. $checked .'><img src="'. esc_url( $url ) .'" alt=""></label>';
                }
                echo '</div></div>';
            }
        }
        echo '        </div>';
        echo '        <div class="awb-modal-revision" style="display:none;">';
        echo '          <label>'. esc_html__( 'Korrekturtext','ai-wallpaper-builder' ) .'</label>';
        echo '          <textarea class="revise-text" rows="3" placeholder="'. esc_attr__( 'Korrekturwunsch beschreiben…', 'ai-wallpaper-builder' ) .'"></textarea>';
        echo '          <div class="revise-actions"><button type="button" class="button button-primary awb-send-revision">'. esc_html__( 'Korrektur senden', 'ai-wallpaper-builder' ) .'</button></div>';
        echo '        </div>';
        $correction_button = $aiw_use_openai_render ? '<button type="button" class="button awb-revise">'. esc_html__( 'Korrektur einreichen','ai-wallpaper-builder' ) .'</button>' : '';
        echo '        <div class="awb-modal-actions"><button type="button" class="button button-primary awb-apply" style="margin-left: auto;">'. esc_html__( 'Bild verwenden','ai-wallpaper-builder' ) .'</button>'. $correction_button .'</div>';
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        $aspect = ($h > 0) ? ($w / $h) : 1;
        echo '  <div class="aiw-modal__dialog">';
        echo '    <div class="aiw-modal__header">';
        echo '      <div class="aiw-tabs">';
        echo '        <button type="button" class="aiw-tab is-active" data-aiw-tab="preview">'. esc_html__( 'Vorschau', 'ai-wallpaper-builder' ) .'</button>';
        echo '        <button type="button" class="aiw-tab aiw-tab-revision" data-aiw-tab="revision" style="display:none">'. esc_html__( 'Korrektur', 'ai-wallpaper-builder' ) .'</button>';
        echo '      </div>';
        echo '      <div class="aiw-controls">';
        echo '        <label class="aiw-select"><span class="aiw-select__label">'. esc_html__( 'Bahnen', 'ai-wallpaper-builder' ) .'</span><select id="aiwLaneSelect"><option value="0" selected>Keine Bahnen</option><option value="50">50cm Bahnen</option><option value="70">70cm Bahnen</option></select></label>';        echo '      </div>';
        echo '      <button type="button" class="awb-close aiw-close" aria-label="'. esc_attr__( 'Schließen', 'ai-wallpaper-builder' ) .'">&times;</button>';
        echo '    </div>';
        echo '    <div class="aiw-modal__body">';
        echo '      <div class="aiw-preview">';
        echo '        <div class="aiw-preview__stage" data-aspect="'. esc_attr( $aspect ) .'">';
        echo '          <div class="awb-preview-images"></div>';
        echo '          <div class="aiw-preview__lanes"></div>';
        echo '          <div class="progress-overlay"><div class="spinner"></div><span class="pct">0%</span></div>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="aiw-sidebar">';
        // Generate material options dynamically from WooCommerce product variations
        $material_options = '';
        if ( $product && $product->is_type( 'variable' ) ) {
            $attributes = $product->get_variation_attributes();
            // Look for material attribute
            $material_attribute = null;
            foreach ( $attributes as $attribute_name => $options ) {
                if ( strpos( $attribute_name, 'material' ) !== false || strpos( $attribute_name, 'pa_material' ) !== false ) {
                    $material_attribute = $attribute_name;
                    break;
                }
            }
            
            if ( $material_attribute && ! empty( $attributes[$material_attribute] ) ) {
                foreach ( $attributes[$material_attribute] as $option ) {
                    $material_options .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
                }
            }
        }
        
        // Fallback to hardcoded options if no dynamic options found
        if ( empty( $material_options ) ) {
            $material_options = '<option value="vlies_struktur">'. esc_html__( 'Vlies Struktur', 'ai-wallpaper-builder' ) .'</option><option value="vlies_glatt">'. esc_html__( 'Vlies Glatt', 'ai-wallpaper-builder' ) .'</option>';
        }
        
        echo '        <div class="aiw-field"><label for="aiwMaterial">'. esc_html__( 'Material', 'ai-wallpaper-builder' ) .'</label><select id="aiwMaterial" name="awb_material_modal">' . $material_options . '</select></div>';
        echo '        <div class="aiw-field aiw-field-size"><label>'. esc_html__( 'Größe', 'ai-wallpaper-builder' ) .'</label>';
        echo '          <div class="size-inputs">';
        echo '            <div class="awb-input-group">';
        echo '              <img class="icon" src="'. AWB_URL . 'assets/img/icon-width.png'.'" alt="" aria-hidden="true">';
        echo '              <input type="number" id="aiwWidthCm" name="awb_width_modal" class="awb-width" value="'. esc_attr( $w ) .'" min="1" placeholder="'. esc_attr__( 'Breite', 'ai-wallpaper-builder' ) .'">';
        echo '              <span class="unit">cm</span>';
        echo '            </div>';
        echo '            <span class="size-sep">×</span>';
        echo '            <div class="awb-input-group">';
        echo '              <img class="icon" src="'. AWB_URL . 'assets/img/icon-height.png'.'" alt="" aria-hidden="true">';
        echo '              <input type="number" id="aiwHeightCm" name="awb_height_modal" class="awb-height" value="'. esc_attr( $h ) .'" min="1" placeholder="'. esc_attr__( 'Höhe', 'ai-wallpaper-builder' ) .'">';
        echo '              <span class="unit">cm</span>';
        echo '            </div>';
        echo '          </div></div>';
        foreach($fields as $f){
            $key   = $f['key'];
            $label = $f['label'];
            $req   = ! empty( $f['required'] );
            if ($f['type'] === 'text'){
                $max   = ! empty( $f['maxlength'] ) ? intval( $f['maxlength'] ) : 0;
                $min   = ! empty( $f['minlength'] ) ? intval( $f['minlength'] ) : 0;
                $placeholder = isset($f['placeholder']) ? $f['placeholder'] : '';
                printf(
                    '<div class="aiw-field"><label>%s%s</label><input type="text" name="awb_%s_modal" placeholder="%s" %s %s %s></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    esc_attr( $placeholder ),
                    $req ? 'required' : '',
                    $max ? 'maxlength="'. $max .'"' : '',
                    $min ? 'minlength="'. $min .'"' : ''
                );
            } elseif ($f['type'] === 'textarea'){
                $rows = max( 1, intval( $f['rows'] ?? 3 ) );
                $placeholder = isset($f['placeholder']) ? $f['placeholder'] : '';
                printf(
                    '<div class="aiw-field"><label>%s%s</label><textarea name="awb_%s_modal" rows="%d" placeholder="%s" %s></textarea></div>',
                    esc_html( $label ),
                    $req ? ' *' : '',
                    esc_attr( $key ),
                    $rows,
                    esc_attr( $placeholder ),
                    $req ? 'required' : ''
                );
            } elseif ($f['type'] === 'cards'){
                echo '<div class="aiw-field"><label>'. esc_html( $label ) . ( $req ? ' *' : '' ) .'</label><div class="cards">';
                $i = 0;
                foreach( (array) ( $f['options'] ?? array() ) as $url ){
                    $i++;
                    $checked = $i === 1 ? 'checked' : '';
                    echo '<label class="card"><input type="radio" name="awb_'. esc_attr( $key ) .'_modal" value="'. esc_url( $url ) .'" '. $checked .'><img src="'. esc_url( $url ) .'" alt=""></label>';
                }
                echo '</div></div>';
            }
        }
        echo '        <div class="aiw-field awb-modal-revision" style="display:none;"><label>'. esc_html__( 'Korrekturtext', 'ai-wallpaper-builder' ) .'</label><textarea class="revise-text" rows="3" placeholder="'. esc_attr__( 'Korrekturwunsch beschreiben…', 'ai-wallpaper-builder' ) .'"></textarea><div class="revise-actions"><button type="button" class="button button-primary awb-send-revision">'. esc_html__( 'Korrektur senden', 'ai-wallpaper-builder' ) .'</button></div></div>';
        $correction_button_new = $aiw_use_openai_render ? '<button type="button" class="awb-btn awb-btn-secondary awb-revise">'. esc_html__( 'Korrektur einreichen','ai-wallpaper-builder' ) .'</button>' : '';
        echo '        <div class="aiw-buttons awb-modal-actions awb-fixed-bottom-right"><button type="button" class="awb-btn awb-btn-primary awb-apply">'. esc_html__( 'Bild verwenden', 'ai-wallpaper-builder' ) .'</button>'. $correction_button_new .'</div>';
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    public static function cart_meta($cart_item_data, $product_id){
        if ( ! self::enabled($product_id) ) return $cart_item_data;
        
        $aiw = array();
        // Map width/height from POST (ints > 0). Use isset() not empty() so "0" isn't coerced.
        $aiw['width'] = max(0, (int)($_POST['awb_width'] ?? 0));
        $aiw['height'] = max(0, (int)($_POST['awb_height'] ?? 0));
        $aiw['width_cm'] = $aiw['width'];
        $aiw['height_cm'] = $aiw['height'];
        
        $aiw['fields'] = array();
        $aiw['image'] = esc_url_raw($_POST['awb_image'] ?? '');
        
        $fields = get_post_meta($product_id, '_awb_fields', true);
        if (is_array($fields)){
            foreach($fields as $f){
                $k = 'awb_'.$f['key'];
                if (isset($_POST[$k])){
                    $aiw['fields'][$f['key']] = sanitize_text_field($_POST[$k]);
                }
            }
        }
        if (!empty($_POST['aiwallpaper_user_image_url'])){
            $aiw['user_image_url'] = esc_url_raw($_POST['aiwallpaper_user_image_url']);
        }
        if (!empty($_POST['aiwallpaper_openai_prompt'])){
            $aiw['openai_prompt'] = sanitize_textarea_field($_POST['aiwallpaper_openai_prompt']);
        }
        if (!empty($_POST['aiwallpaper_ratio'])){
            $aiw['ratio'] = sanitize_text_field($_POST['aiwallpaper_ratio']);
        }
        if (!empty($_POST['aiwallpaper_crop_x'])){
            $aiw['crop_x'] = sanitize_text_field($_POST['aiwallpaper_crop_x']);
        }
        // Cropped URL (http/https ODER data:image)
        if ( ! empty($_POST['aiwallpaper_cropped_image_url']) ) {
            $raw = trim((string) $_POST['aiwallpaper_cropped_image_url']);

            if (strpos($raw, 'data:image') === 0) {
                // 1) Base64 in Datei schreiben
                $parts = explode(',', $raw, 2);
                $b64   = isset($parts[1]) ? $parts[1] : '';
                $bin   = base64_decode($b64);

                if ($bin !== false) {
                    $uploads  = wp_upload_dir();
                    $fname    = 'awb-crop-' . time() . '-' . wp_generate_password(6, false) . '.png';
                    $fpath    = trailingslashit($uploads['path']) . $fname;
                    $furl     = trailingslashit($uploads['url'])  . $fname;

                    // schreibt Datei
                    file_put_contents($fpath, $bin);

                    // 2) Nur die URL in den Warenkorb legen
                    $aiw['cropped_image_url'] = esc_url_raw($furl);
                }
            } else {
                $aiw['cropped_image_url'] = esc_url_raw($raw);
            }
        }
        if ( ! empty($_POST['aiwallpaper_cropped_image_file']) ) {
            $aiw['cropped_image_file'] = sanitize_file_name($_POST['aiwallpaper_cropped_image_file']);
        }
        if (isset($_POST['aiwallpaper_bahnen_aktiv'])){
            $aiw['bahnen_aktiv'] = sanitize_text_field($_POST['aiwallpaper_bahnen_aktiv']);
        }
        if (!empty($aiw)){
            $cart_item_data['aiwallpaper'] = $aiw;
        }
        
        // MERGE, don't overwrite:
        $cart_item_data['awb'] = (isset($cart_item_data['awb']) && is_array($cart_item_data['awb']))
            ? array_merge($cart_item_data['awb'], $aiw)
            : $aiw;
        return $cart_item_data;
    }

    public static function cart_item_display($item_data, $cart_item){
        if (empty($cart_item['awb'])) return $item_data;
        $d = $cart_item['awb'];
        $item_data[] = array('name'=>__('Breite × Höhe (cm)','ai-wallpaper-builder'), 'value'=>intval($d['width']).' × '.intval($d['height']));
        foreach( (array)$d['fields'] as $k=>$v ){
            // Only show field if it has a value (not empty)
            if (!empty(trim($v))) {
                $item_data[] = array('name'=>ucfirst(str_replace('_',' ',$k)), 'value'=>wc_clean($v));
            }
        }
        if (!empty($d['image'])){
            $item_data[] = array('name'=>__('Bild','ai-wallpaper-builder'), 'value'=>'<a href="'.esc_url($d['image']).'" target="_blank">'.esc_html__('Download','ai-wallpaper-builder').'</a>');
        }
        return $item_data;
    }
}
