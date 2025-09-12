<?php
if ( ! defined('ABSPATH') ) { exit; }

class AWB_Admin {

    public static function init(){
        add_filter('woocommerce_product_data_tabs', array(__CLASS__,'product_tab'));
        add_action('woocommerce_product_data_panels', array(__CLASS__,'product_panel'));
        add_action('save_post_product', array(__CLASS__,'save_product_meta'));
        add_action('admin_enqueue_scripts', array(__CLASS__,'assets'));
    }

    public static function assets($hook){
        global $typenow;
        if ( ($hook === 'post.php' || $hook == 'post-new.php') && $typenow === 'product'){
            wp_enqueue_style('awb-admin-fields', AWB_URL.'assets/css/admin-fields.css', array(), AWB_VER);
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_media();
            wp_enqueue_script('awb-admin-fields', AWB_URL.'assets/js/admin-fields.js', array('jquery','jquery-ui-sortable'), AWB_VER, true);
        }
    }

    public static function product_tab($tabs){
        $tabs['ai_wallpaper'] = array(
            'label'    => __('AI Wallpaper','ai-wallpaper-builder'),
            'target'   => 'awb_product_panel',
            'priority' => 70,
            'class'    => array(),
        );
        return $tabs;
    }

    public static function product_panel(){
        global $post;
        $enabled       = (bool) get_post_meta($post->ID, '_awb_enabled', true);
        $width         = intval(get_post_meta($post->ID, '_awb_width', true));  if(!$width) $width = 200;
        $height        = intval(get_post_meta($post->ID, '_awb_height', true)); if(!$height) $height= 200;
        $fields        = get_post_meta($post->ID, '_awb_fields', true);
        if (!is_array($fields)) $fields = array();
        // Fetch default image and OpenAI toggle for this product. These values
        // are used on the front end to determine whether to show a generated
        // image or a pre‑selected standard image.
        $aiw_default_image = get_post_meta( $post->ID, '_aiw_default_image', true );
        $aiw_use_openai    = (bool) get_post_meta( $post->ID, '_aiw_use_openai', true );
        $aiw_image_ratio   = get_post_meta( $post->ID, '_aiw_image_ratio', true ); 
        if ( empty( $aiw_image_ratio ) ) $aiw_image_ratio = '16:9'; // Default
        $aiw_master_prompt = get_post_meta( $post->ID, '_aiw_master_prompt', true );
        if ( empty( $aiw_master_prompt ) ) $aiw_master_prompt = "Graffiti Schriftzug '#Name' an Mauer, realistisch, scharf. Hinweise: #Beschreibung. Stil: #Stil."; // Default
        $aiw_reference_image = get_post_meta( $post->ID, '_aiw_reference_image', true );
        if (!is_array($fields)) $fields = array();
        ?>
        <div id="awb_product_panel" class="panel woocommerce_options_panel">
            <!-- Row for enabling the builder. We place the checkbox and label explicitly to avoid theme interference. -->
            <p class="awb-enable-toggle">
                <input type="checkbox" id="awb_enable_checkbox" name="_awb_enabled" value="1" <?php checked($enabled); ?> />
                <label for="awb_enable_checkbox" style="font-weight:600; margin-left:6px; color:#333;">
                    <?php esc_html_e( 'AI Wallpaper Builder aktivieren', 'ai-wallpaper-builder' ); ?>
                </label>
                <span class="description" style="display:block; margin-left:26px; color:#555; font-size:12px;">
                    <?php esc_html_e( 'Zeigt den AI-Builder und die Felder im Frontend an.', 'ai-wallpaper-builder' ); ?>
                </span>
            </p>
            <?php
            // Load preview button setting. When true, the preview button is shown on the
            // product page; when false, the button is hidden. Default: show preview.
            $preview_enabled = (bool) get_post_meta( $post->ID, '_awb_show_preview_btn', true );
            ?>
            <p class="awb-preview-toggle">
                <input type="checkbox" id="awb_preview_checkbox" name="_awb_show_preview_btn" value="1" <?php checked( $preview_enabled ); ?> />
                <label for="awb_preview_checkbox" style="font-weight:600; margin-left:6px; color:#333;">
                    <?php esc_html_e( 'Vorschau-Button anzeigen', 'ai-wallpaper-builder' ); ?>
                </label>
                <span class="description" style="display:block; margin-left:26px; color:#555; font-size:12px;">
                    <?php esc_html_e( 'Schaltet den Button „Vorschau erzeugen“ im Frontend ein oder aus.', 'ai-wallpaper-builder' ); ?>
                </span>
            </p>
            <div class="awb-grid">
                <p>
                    <label for="awb_width_input"><?php esc_html_e('Standard Breite (cm)','ai-wallpaper-builder'); ?></label>
                    <input id="awb_width_input" type="number" name="_awb_width" value="<?php echo esc_attr($width); ?>" min="1" />
                    <span class="description" style="font-size:12px; color:#555;">
                        <?php esc_html_e('Vorgabewert für die Breite der Tapete (in cm).', 'ai-wallpaper-builder'); ?>
                    </span>
                </p>
                <p>
                    <label for="awb_height_input"><?php esc_html_e('Standard Höhe (cm)','ai-wallpaper-builder'); ?></label>
                    <input id="awb_height_input" type="number" name="_awb_height" value="<?php echo esc_attr($height); ?>" min="1" />
                    <span class="description" style="font-size:12px; color:#555;">
                        <?php esc_html_e('Vorgabewert für die Höhe der Tapete (in cm).', 'ai-wallpaper-builder'); ?>
                    </span>
                </p>
                <!-- Standard image and OpenAI toggle -->
                <p>
                    <label for="aiw_default_image" style="font-weight:600;">
                        <?php esc_html_e( 'Standardbild', 'ai-wallpaper-builder' ); ?>
                    </label>
                    <input type="text" id="aiw_default_image" name="_aiw_default_image" value="<?php echo esc_attr( $aiw_default_image ); ?>" style="width:60%;" />
                    <button type="button" class="button aiw-upload-button"><?php esc_html_e( 'Bild wählen', 'ai-wallpaper-builder' ); ?></button>
                    <span class="description" style="display:block; font-size:12px; color:#555;">
                        <?php esc_html_e( 'Legt das Standardbild für die Tapete fest. Dieses Bild wird verwendet, wenn OpenAI deaktiviert ist.', 'ai-wallpaper-builder' ); ?>
                    </span>
                </p>
                <p>
                    <label for="aiw_image_ratio" style="font-weight:600;">
                        <?php esc_html_e( 'Bildverhältnis', 'ai-wallpaper-builder' ); ?>
                    </label>
                    <select id="aiw_image_ratio" name="_aiw_image_ratio" style="width:200px;">
                        <option value="16:9" <?php selected( $aiw_image_ratio, '16:9' ); ?>>16:9 (Widescreen)</option>
                        <option value="3:2" <?php selected( $aiw_image_ratio, '3:2' ); ?>>3:2 (Klassisch)</option>
                        <option value="4:3" <?php selected( $aiw_image_ratio, '4:3' ); ?>>4:3 (Standard)</option>
                        <option value="1:1" <?php selected( $aiw_image_ratio, '1:1' ); ?>>1:1 (Quadratisch)</option>
                        <option value="auto" <?php selected( $aiw_image_ratio, 'auto' ); ?>>Auto (Original)</option>
                    </select>
                    <span class="description" style="display:block; font-size:12px; color:#555;">
                        <?php esc_html_e( 'Stellt sicher, dass das Vorschaubild im korrekten Verhältnis angezeigt wird und nicht verzerrt wird.', 'ai-wallpaper-builder' ); ?>
                    </span>
                </p>
                <p>
                    <label for="aiw_reference_image" style="font-weight:600;">
                        <?php esc_html_e( 'Referenzbild für OpenAI', 'ai-wallpaper-builder' ); ?>
                    </label>
                    <input type="text" id="aiw_reference_image" name="_aiw_reference_image" value="<?php echo esc_attr( $aiw_reference_image ); ?>" style="width:60%;" />
                    <button type="button" class="button aiw-upload-button-ref"><?php esc_html_e( 'Referenzbild wählen', 'ai-wallpaper-builder' ); ?></button>
                    <span class="description" style="display:block; font-size:12px; color:#555;">
                        <?php esc_html_e( 'Ein Referenzbild, das an OpenAI gesendet wird, um den Stil und die Komposition zu beeinflussen.', 'ai-wallpaper-builder' ); ?>
                    </span>
                </p>
                <p>
                    <input type="checkbox" id="aiw_use_openai" name="_aiw_use_openai" value="1" <?php checked( $aiw_use_openai ); ?> />
                    <label for="aiw_use_openai" style="font-weight:600; margin-left:6px; color:#333;">
                        <?php esc_html_e( 'Open AI aktivieren', 'ai-wallpaper-builder' ); ?>
                    </label>
                    <span class="description" style="display:block; margin-left:26px; color:#555; font-size:12px;">
                        <?php esc_html_e( 'Wenn aktiv, wird das Vorschau‑Bild mit OpenAI generiert. Sonst wird das Standardbild verwendet.', 'ai-wallpaper-builder' ); ?>
                    </span>
                </p>
                <p>
                    <label for="aiw_master_prompt" style="font-weight:600;">
                        <?php esc_html_e( 'Master-Prompt', 'ai-wallpaper-builder' ); ?>
                    </label>
                    <textarea id="aiw_master_prompt" name="_aiw_master_prompt" rows="3" style="width:100%;" placeholder="Graffiti Schriftzug '#Name' an Mauer, realistisch, scharf. Hinweise: #Beschreibung. Stil: #Stil."><?php echo esc_textarea( $aiw_master_prompt ); ?></textarea>
                    <span class="description" style="display:block; font-size:12px; color:#555; margin-bottom:10px;">
                        <?php esc_html_e( 'Der Master-Prompt für OpenAI Bildgenerierung mit folgenden Platzhaltern:', 'ai-wallpaper-builder' ); ?>
                    </span>
                    <div style="background:#f5f5f5; padding:12px; border:1px solid #ddd; border-radius:4px; font-size:11px; margin-bottom:8px;">
                        <strong style="color:#333;">📝 Standard-Platzhalter:</strong><br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">#Name</code> - Name/Text vom Nutzer<br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">#Beschreibung</code> - Beschreibung vom Nutzer<br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">#Stil</code> - Stil-Auswahl vom Nutzer<br><br>
                        
                        <strong style="color:#333;">🏷️ Meta-Field Platzhalter:</strong><br>
                        <span style="color:#666;">Format: <code style="background:#fff; padding:2px 4px;">&lt;#feldname#&gt;</code> für alle unten erstellten Felder</span><br>
                        <span style="color:#666;">Beispiele:</span><br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">&lt;#farbe#&gt;</code> - Wert vom Feld "farbe"<br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">&lt;#material#&gt;</code> - Wert vom Feld "material"<br>
                        <code style="background:#fff; padding:2px 4px; margin:2px;">&lt;#groesse#&gt;</code> - Wert vom Feld "groesse"<br><br>
                        
                        <strong style="color:#333;">💡 Beispiel:</strong><br>
                        <code style="background:#fff; padding:4px 6px; display:block; margin-top:4px;">
                            Graffiti Schriftzug "#Name" an &lt;#material#&gt; Wand, Farbe: &lt;#farbe#&gt;, Stil: #Stil
                        </code>
                    </div>
                </p>
                
                <!-- Debug Log URL für Support -->
                <p style="background:#f0f8ff; padding:15px; border:1px solid #0073aa; border-radius:4px; margin:20px 0;">
                    <strong style="color:#0073aa;">🔍 Debug-Logs für Support:</strong><br>
                    <span style="font-size:12px; color:#666; margin:8px 0; display:block;">
                        Kopieren Sie diese URLs und senden Sie sie an den Support:
                    </span>
                    <strong>WordPress Debug Log:</strong><br>
                    <code style="background:#fff; padding:4px 6px; display:block; margin:4px 0; font-size:11px; word-break:break-all;">
                        <?php echo esc_url(home_url('/wp-content/debug.log')); ?>
                    </code>
                    <strong>Plugin Debug Log:</strong><br>
                    <code style="background:#fff; padding:4px 6px; display:block; margin:4px 0; font-size:11px; word-break:break-all;">
                        <?php echo esc_url(home_url('/wp-content/awb-debug.log')); ?>
                    </code>
                    <span style="font-size:11px; color:#666; margin-top:8px; display:block;">
                        💡 Diese Logs zeigen alle API-Calls und Fehler für die Fehlersuche.
                    </span>
                </p>
            </div>
            <div id="awb_field_builder" data-fields='<?php echo wp_json_encode($fields); ?>'></div>
            <input type="hidden" id="awb-fields-json" name="_awb_fields_json" value='<?php echo esc_attr( wp_json_encode($fields) ); ?>'>
        </div>
        <?php
    }

    public static function save_product_meta($post_id){
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        update_post_meta($post_id, '_awb_enabled', !empty($_POST['_awb_enabled']) ? 1 : 0);
        update_post_meta($post_id, '_awb_show_preview_btn', !empty($_POST['_awb_show_preview_btn']) ? 1 : 0);
        if (isset($_POST['_awb_width']))  update_post_meta($post_id, '_awb_width', intval($_POST['_awb_width']));
        if (isset($_POST['_awb_height'])) update_post_meta($post_id, '_awb_height', intval($_POST['_awb_height']));
        if (isset($_POST['_aiw_image_ratio'])) update_post_meta($post_id, '_aiw_image_ratio', sanitize_text_field($_POST['_aiw_image_ratio']));

        if ( isset($_POST['_awb_fields_json']) ){
            $raw = wp_unslash($_POST['_awb_fields_json']);
            $arr = json_decode($raw, true);
            if (!is_array($arr)) $arr = array();
            $out = array();
            foreach ( $arr as $f ) {
                $type  = sanitize_key( isset( $f['type'] ) ? $f['type'] : 'text' );
                $label = sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' );
                $key   = sanitize_title( isset( $f['key'] ) ? $f['key'] : '' );
                if ( ! $key && $label ) {
                    $key = sanitize_title( $label );
                }
                $row = array(
                    'id'       => sanitize_key( isset( $f['id'] ) ? $f['id'] : ( 'fld_' . wp_generate_uuid4() ) ),
                    'type'     => $type,
                    'label'    => $label,
                    'key'      => $key,
                    'required' => ! empty( $f['required'] ) ? 1 : 0,
                );
                if ( $type === 'text' ) {
                    $row['placeholder'] = sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' );
                    $row['maxlength']   = intval( isset( $f['maxlength'] ) ? $f['maxlength'] : 0 );
                    // New: minimum length for text fields
                    $row['minlength']   = intval( isset( $f['minlength'] ) ? $f['minlength'] : 0 );
                } elseif ( $type === 'textarea' ) {
                    $row['placeholder'] = sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' );
                    $row['rows']        = max( 1, intval( isset( $f['rows'] ) ? $f['rows'] : 3 ) );
                } elseif ( $type === 'cards' ) {
                    $opts  = isset( $f['options'] ) ? (array) $f['options'] : array();
                    $opts  = array_map( 'esc_url_raw', $opts );
                    $row['options'] = array_values( array_filter( $opts ) );
                } elseif ( $type === 'square_meter' ) {
                    // Square meter specific: whether to use the base price as €/m²
                    $row['sqm_price'] = ! empty( $f['sqm_price'] ) ? 1 : 0;
                }
                $out[] = $row;
            }
            // Persist fields meta
            update_post_meta( $post_id, '_awb_fields', $out );
            // Determine whether this product should use square meter pricing. If any field
            // of type "square_meter" has the sqm_price flag set, enable use_sqm_price.
            $use_sqm_price = false;
            foreach ( $out as $f ) {
                if ( $f['type'] === 'square_meter' && ! empty( $f['sqm_price'] ) ) {
                    $use_sqm_price = true;
                    break;
                }
            }
            update_post_meta( $post_id, '_awb_use_sqm_price', $use_sqm_price ? 1 : 0 );
        }

        // Save AI Wallpaper additional meta fields: default image and OpenAI toggle.
        // The default image is a string (URL) entered via the admin UI. Sanitize as URL.
        if ( isset($_POST['_aiw_default_image']) ) {
            $def = trim( stripslashes( $_POST['_aiw_default_image'] ) );
            $def = esc_url_raw( $def );
            update_post_meta( $post_id, '_aiw_default_image', $def );
        }
        // Save OpenAI activation flag. Checkboxes are present only when checked.
        update_post_meta( $post_id, '_aiw_use_openai', ! empty( $_POST['_aiw_use_openai'] ) ? 1 : 0 );
        // Save master prompt
        if ( isset($_POST['_aiw_master_prompt']) ) {
            $prompt = trim( stripslashes( $_POST['_aiw_master_prompt'] ) );
            update_post_meta( $post_id, '_aiw_master_prompt', sanitize_textarea_field( $prompt ) );
        }
        // Save reference image
        if ( isset($_POST['_aiw_reference_image']) ) {
            $ref_img = trim( stripslashes( $_POST['_aiw_reference_image'] ) );
            $ref_img = esc_url_raw( $ref_img );
            update_post_meta( $post_id, '_aiw_reference_image', $ref_img );
        }
    }
}
