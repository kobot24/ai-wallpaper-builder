<?php
if ( ! defined('ABSPATH') ) { exit; }

class AWB_Settings {
    public static function init(){
        add_action('admin_menu', array(__CLASS__,'menu'));
        add_action('admin_init', array(__CLASS__,'register'));
        add_action('wp_ajax_awb_api_test', array(__CLASS__,'ajax_api_test'));
    }

    public static function defaults(){
        return array(
            'api_key' => '',
            'model'   => 'gpt-image-1',
            'size'    => '1024x1024',
            // Default image quality. Supported values as of 2024‑04: 'low','medium','high','auto'.
            // Use 'medium' to match the previous default of 'standard'.
            'quality' => 'medium',
            'timeout' => 120,

            'limit_user_day' => 10,
            'limit_total_day'=> 0,
            // A comma- or newline-separated list of blocked words. If a user's description
            // contains any of these words, generation will be rejected with an error.
            'blacklist' => '',
        );
    }

    public static function get(){
        $opt = get_option('awb_settings', array());
        return wp_parse_args($opt, self::defaults());
    }

    public static function menu(){
        // Register a dedicated top‑level menu for AI Wallpaper. Without a custom top‑level
        // menu the settings page could be hard to discover. We use a unique slug
        // (`awb-settings`) so that existing links remain compatible. The icon and
        // position can be adjusted as needed (currently uses the built‑in art icon).
        add_menu_page(
            __('AI Wallpaper','ai-wallpaper-builder'),
            __('AI Wallpaper','ai-wallpaper-builder'),
            'manage_options',
            'awb-settings',
            array(__CLASS__,'render'),
            'dashicons-art',
            58
        );
        // Also register a submenu pointing to the same slug. WordPress requires
        // at least one submenu for top‑level pages to display properly.
        add_submenu_page(
            'awb-settings',
            __('Einstellungen','ai-wallpaper-builder'),
            __('Einstellungen','ai-wallpaper-builder'),
            'manage_options',
            'awb-settings',
            array(__CLASS__,'render')
        );
    }

    public static function register(){
        register_setting('awb_settings_group', 'awb_settings');
        add_settings_section('awb_main', '', '__return_false', 'awb-settings');
        $fields = array(
            'api_key' => __('OpenAI API Key','ai-wallpaper-builder'),
            'model'   => __('Modell','ai-wallpaper-builder'),
            'size'    => __('Bildgröße','ai-wallpaper-builder'),
            'quality' => __('Qualität','ai-wallpaper-builder'),
            'timeout' => __('Timeout (Sek.)','ai-wallpaper-builder'),
            'limit_user_day' => __('Limit pro Nutzer/Tag','ai-wallpaper-builder'),
            'limit_total_day'=> __('Gesamtlimit/Tag (0 = aus)','ai-wallpaper-builder'),
            'blacklist' => __('Blacklist (verbotene Begriffe)','ai-wallpaper-builder'),
        );
        foreach($fields as $k=>$label){
            add_settings_field($k, $label, array(__CLASS__,'field'), 'awb-settings', 'awb_main', array('key'=>$k));
        }
    }

    public static function field($args){
        $o = self::get();
        $k = $args['key'];
        switch($k){
            case 'api_key':
                printf('<input type="password" style="width:420px" name="awb_settings[api_key]" value="%s" /> <button type="button" class="button" id="awb-test">%s</button> <span id="awb-test-result"></span>',
                    esc_attr($o['api_key']), esc_html__('API testen','ai-wallpaper-builder'));
                break;
            case 'model':
                $models = array('gpt-image-1'=>'gpt-image-1','dall-e-3'=>'dall-e-3');
                echo '<select name="awb_settings[model]">';
                foreach($models as $v=>$lab){
                    printf('<option value="%s"%s>%s</option>', esc_attr($v), selected($o['model'],$v,false), esc_html($lab));
                }
                echo '</select>';
                break;
            case 'size':
                $sizes = array('1024x1024','1536x1024','1024x1536');
                echo '<select name="awb_settings[size]">';
                foreach($sizes as $s){
                    printf('<option %s>%s</option>', selected($o['size'],$s,false), esc_html($s));
                }
                echo '</select>';
                break;
            case 'quality':
                // Updated quality choices to reflect OpenAI API v1 as of 2024‑04.
                // The API accepts: low, medium, high and auto. We also allow empty to not send the parameter.
                $qs = array('' => __('(nicht senden)','ai-wallpaper-builder'),
                            'low'    => 'low',
                            'medium' => 'medium',
                            'high'   => 'high',
                            'auto'   => 'auto');
                echo '<select name="awb_settings[quality]">';
                foreach($qs as $v=>$lab){
                    printf('<option value="%s"%s>%s</option>', esc_attr($v), selected($o['quality'],$v,false), esc_html($lab));
                }
                echo '</select>';
                break;
            case 'timeout':
                printf('<input type="number" min="30" max="300" name="awb_settings[timeout]" value="%d" />', intval($o['timeout']));
                break;
            case 'prompt':
                printf('<textarea name="awb_settings[prompt]" rows="3" style="width:520px">%s</textarea>', esc_textarea($o['prompt']));
                break;
            case 'limit_user_day':
                printf('<input type="number" min="0" name="awb_settings[limit_user_day]" value="%d" />', intval($o['limit_user_day']));
                break;
            case 'limit_total_day':
                printf('<input type="number" min="0" name="awb_settings[limit_total_day]" value="%d" />', intval($o['limit_total_day']));
                break;
            case 'blacklist':
                // Display a textarea for entering a comma or newline separated list of blocked keywords. We
                // trim each entry at runtime. When any of these words appear in the user's
                // description, image generation will be blocked with an error. Note: this list is
                // case-insensitive.
                printf('<textarea name="awb_settings[blacklist]" rows="3" style="width:520px" placeholder="%s">%s</textarea>',
                    esc_attr__( 'z.B. Disney, Pixar, Marvel …', 'ai-wallpaper-builder' ),
                    esc_textarea( $o['blacklist'] ) );
                echo '<p class="description">'. esc_html__( 'Verbotene Begriffe mit Komma oder Zeilenumbruch trennen. Enthält die Beschreibung einen dieser Begriffe, wird die Generierung abgelehnt.', 'ai-wallpaper-builder' ) .'</p>';
                break;
        }
    }

    public static function render(){
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Wallpaper – Einstellungen','ai-wallpaper-builder');?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('awb_settings_group'); do_settings_sections('awb-settings'); submit_button(); ?>
            </form>
            <hr/>
            <h2><?php esc_html_e('Fehlerprotokoll','ai-wallpaper-builder');?></h2>
            <pre style="max-height:260px;overflow:auto;background:#111;color:#8f8;padding:10px;border-radius:6px"><?php echo esc_html( AWB_OpenAI::read_log(200) ); ?></pre>
        </div>
        <script>
        (function($){
            $('#awb-test').on('click', function(){
                var $r = $('#awb-test-result').text('…');
                $.post(ajaxurl, { action:'awb_api_test', _wpnonce: '<?php echo esc_js( wp_create_nonce("awb_api_test") ); ?>' }, function(res){
                    if (res && res.success){ $r.text('OK'); $r.css('color','#15803d'); }
                    else { $r.text((res && res.data) ? res.data : 'Fehler'); $r.css('color','#b91c1c'); }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function ajax_api_test(){
        check_ajax_referer('awb_api_test');
        $o = self::get();
        if ( empty($o['api_key']) ){
            wp_send_json_error('Kein API-Key gespeichert.');
        }
        $r = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array('Authorization'=>'Bearer '.$o['api_key']),
            'timeout' => 20,
        ));
        if ( is_wp_error($r) ){
            wp_send_json_error( $r->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code($r);
        if ($code>=200 && $code<300){
            wp_send_json_success('OK');
        }
        wp_send_json_error('HTTP '.$code.': '.substr(wp_remote_retrieve_body($r),0,200));
    }
}
