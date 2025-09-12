<?php
if ( ! defined('ABSPATH') ) { exit; }

class AWB_OpenAI {
    public static function log_file(){
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']).'ai-wallpaper';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        return $dir.'/aiw.log';
    }
    public static function log($msg){
        $line = '['.date('Y-m-d H:i:s').'] '.(is_string($msg)?$msg:wp_json_encode($msg)).PHP_EOL;
        file_put_contents(self::log_file(), $line, FILE_APPEND);
    }
    public static function read_log($lines=200){
        $f = self::log_file();
        if (!file_exists($f)) return '';
        $arr = file($f, FILE_IGNORE_NEW_LINES);
        return implode("\n", array_slice($arr, -absint($lines)));
    }

    public static function session_id(){
        if (is_user_logged_in()) return 'u'.get_current_user_id();
        if (!headers_sent() && empty($_COOKIE['awb_session'])){
            $sid = wp_generate_password(10,false,false);
            setcookie('awb_session', $sid, time()+3600*24*30, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['awb_session'] = $sid;
        }
        return 'g'.sanitize_key($_COOKIE['awb_session']);
    }

    public static function download_temp_image($url) {
        $temp_dir = sys_get_temp_dir();
        $temp_file = $temp_dir . '/awb_ref_' . wp_generate_uuid4() . '.jpg';
        
        $response = wp_remote_get($url, array('timeout' => 30));
        if (is_wp_error($response)) {
            return new WP_Error('download_failed', 'Referenzbild konnte nicht heruntergeladen werden: ' . $response->get_error_message());
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return new WP_Error('empty_image', 'Referenzbild ist leer');
        }
        
        file_put_contents($temp_file, $image_data);
        return $temp_file;
    }

    public static function save_png($data_or_url){
        $up = wp_upload_dir();
        $base = trailingslashit($up['basedir']).'ai-wallpaper/'.self::session_id();
        if (!file_exists($base)) wp_mkdir_p($base);
        $file = $base.'/job_'.wp_generate_uuid4().'.png';
        if ( preg_match('#^https?://#', $data_or_url) ){
            $r = wp_remote_get($data_or_url, array('timeout'=>60));
            if (is_wp_error($r)) return false;
            file_put_contents($file, wp_remote_retrieve_body($r));
        } else {
            file_put_contents($file, base64_decode($data_or_url));
        }
        $url = str_replace($up['basedir'], $up['baseurl'], $file);
        return array($file, $url);
    }

    public static function build_prompt($name,$desc,$style,$master_prompt='',$meta_fields=array()){
        // If no master prompt is set, use minimal prompt (just the name)
        if ( empty( $master_prompt ) ) {
            // For reference images, we want minimal prompts - just use the name
            $tpl = '#Name';
            error_log( 'AWB Debug: Empty master prompt - using minimal template: ' . $tpl );
        } else {
            $tpl = $master_prompt;
            error_log( 'AWB Debug: Using product master prompt: ' . $tpl );
        }
        
        // Basic replacements
        $repl = array(
            '#Name'        => $name ?: 'DEMO',
            '#NAME'        => $name ?: 'DEMO', // support uppercase placeholder
            '#Beschreibung'=> $desc ?: '',
            '#Stil'        => $style ?: 'Graffiti, Street Art, 3D shadow',
        );
        
        // Add meta field replacements in format <#metakey#>
        if ( is_array( $meta_fields ) ) {
            foreach ( $meta_fields as $key => $value ) {
                $placeholder = '<#' . $key . '#>';
                $repl[$placeholder] = $value ?: '';
            }
        }
        
        return strtr($tpl, $repl);
    }

    public static function check_limits(){
        $o = AWB_Settings::get();
        $uid = is_user_logged_in() ? 'u'.get_current_user_id() : 'ip'.md5($_SERVER['REMOTE_ADDR'] ?? '0');
        $day = date('Ymd');
        $key_user = 'awb_cnt_'.$uid.'_'.$day;
        $key_total= 'awb_cnt_total_'.$day;
        $user_cnt = intval( get_transient($key_user) );
        $tot_cnt  = intval( get_transient($key_total) );

        if ($o['limit_user_day'] > 0 && $user_cnt >= $o['limit_user_day']){
            return new WP_Error('limit','Tageslimit pro Nutzer erreicht.');
        }
        if ($o['limit_total_day'] > 0 && $tot_cnt >= $o['limit_total_day']){
            return new WP_Error('limit','Gesamtes Tageslimit erreicht.');
        }
        // increase counters
        set_transient($key_user, $user_cnt+1, DAY_IN_SECONDS);
        set_transient($key_total, $tot_cnt+1, DAY_IN_SECONDS);
        return true;
    }

    public static function generate($name,$desc,$style,$correction='',$master_prompt='',$meta_fields=array(),$reference_image=''){
        $o = AWB_Settings::get();
        if ( empty($o['api_key']) ) return new WP_Error('no_key','Kein API-Key konfiguriert.');

        $limit = self::check_limits();
        if (is_wp_error($limit)) return $limit;

        $timestamp = date('Y-m-d H:i:s');
        
        // Check if we have a reference image - use different approaches
        if (!empty($reference_image)) {
            error_log( 'AWB Debug [' . $timestamp . ']: Using EDITS API with reference image: ' . $reference_image );
            file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI: Using EDITS API with reference: ' . $reference_image . PHP_EOL, FILE_APPEND | LOCK_EX );
            return self::generate_with_edits_api($name, $desc, $style, $correction, $master_prompt, $meta_fields, $o, $timestamp, $reference_image);
        } else {
            error_log( 'AWB Debug [' . $timestamp . ']: Using GENERATIONS API (no reference image)' );
            file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI: Using GENERATIONS API (no reference)' . PHP_EOL, FILE_APPEND | LOCK_EX );
            return self::generate_with_generations_api($name, $desc, $style, $correction, $master_prompt, $meta_fields, $o, $timestamp);
        }
    }
    
    private static function generate_with_edits_api($name, $desc, $style, $correction, $master_prompt, $meta_fields, $settings, $timestamp, $reference_image) {
        // Build prompt for edits API
        $prompt_text = self::build_prompt($name, $desc, $style, $master_prompt, $meta_fields) . ($correction ? "\nKorrektur: " . $correction : '');
        
        // Download reference image
        $image_response = wp_remote_get($reference_image, array('timeout' => 30));
        if (is_wp_error($image_response)) {
            error_log( 'AWB Debug [' . $timestamp . ']: Failed to download reference image: ' . $image_response->get_error_message() );
            return new WP_Error('download_failed', 'Referenzbild konnte nicht heruntergeladen werden: ' . $image_response->get_error_message());
        }
        
        $image_data = wp_remote_retrieve_body($image_response);
        if (empty($image_data)) {
            return new WP_Error('download_failed', 'Referenzbild ist leer');
        }
        
        // Log final prompt and image info for debugging
        error_log( 'AWB Debug [' . $timestamp . ']: EDITS API - Final prompt: ' . $prompt_text );
        error_log( 'AWB Debug [' . $timestamp . ']: EDITS API - Image size: ' . strlen($image_data) . ' bytes' );
        error_log( 'AWB Debug [' . $timestamp . ']: EDITS API - Reference URL: ' . $reference_image );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI EDITS: Prompt = ' . $prompt_text . PHP_EOL, FILE_APPEND | LOCK_EX );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI EDITS: Image size = ' . strlen($image_data) . ' bytes' . PHP_EOL, FILE_APPEND | LOCK_EX );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI EDITS: Reference URL = ' . $reference_image . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        // Create BASIC multipart form data - ONLY documented parameters
        $boundary = 'awb_boundary_' . uniqid();
        $body = '';
        
        // 1. IMAGE - Required
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"reference.png\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= $image_data . "\r\n";
        
        // 2. PROMPT - Required  
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
        $body .= $prompt_text . "\r\n";
        
        // 3. MODEL - Required (gpt-image-1)
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "gpt-image-1\r\n";
        
        // 4. SIZE - Optional but recommended
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
        $body .= $settings['size'] . "\r\n";
        
        // Close boundary
        $body .= "--{$boundary}--\r\n";
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => max(30, intval($settings['timeout'])),
        );
        
        error_log( 'AWB Debug [' . $timestamp . ']: BASIC EDITS API - Only documented parameters' );
        error_log( 'AWB Debug [' . $timestamp . ']: Parameters: image, prompt, model, size' );
        error_log( 'AWB Debug [' . $timestamp . ']: API Endpoint: https://api.openai.com/v1/images/edits' );
        
        $res = wp_remote_post('https://api.openai.com/v1/images/edits', $args);
        return self::process_openai_response($res, $timestamp, 'EDITS');
    }
    
    private static function generate_with_generations_api($name, $desc, $style, $correction, $master_prompt, $meta_fields, $settings, $timestamp) {
        // Build prompt normally for generations API
        $prompt_text = self::build_prompt($name, $desc, $style, $master_prompt, $meta_fields) . ($correction ? "\nKorrektur: " . $correction : '');
        
        // Log final prompt for debugging
        error_log( 'AWB Debug [' . $timestamp . ']: GENERATIONS API - Final prompt: ' . substr($prompt_text, 0, 200) . '...' );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI GENERATIONS: Prompt = ' . substr($prompt_text, 0, 200) . '...' . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        $payload = array(
            'model'  => $settings['model'],
            'prompt' => $prompt_text,
            'size'   => $settings['size'],
        );
        // Quality parameter removed - causes API errors
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($payload),
            'timeout' => max(30, intval($settings['timeout'])),
        );

        $res = wp_remote_post('https://api.openai.com/v1/images/generations', $args);
        return self::process_openai_response($res, $timestamp, 'GENERATIONS');
    }
    
    private static function process_openai_response($res, $timestamp, $api_type) {
        if (is_wp_error($res)){
            self::log(array('curl_error'=>$res->get_error_message()));
            error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - CURL Error: ' . $res->get_error_message() );
            return $res;
        }
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $headers = wp_remote_retrieve_headers($res);
        
        error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - Response Code: ' . $code );
        error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - Response Headers: ' . print_r($headers, true) );
        error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - Response Body: ' . substr($body, 0, 1000) );
        
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': Response Code = ' . $code . PHP_EOL, FILE_APPEND | LOCK_EX );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': Response Headers = ' . print_r($headers, true) . PHP_EOL, FILE_APPEND | LOCK_EX );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': Response Body = ' . substr($body, 0, 1000) . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        if ($code<200 || $code>=300){
            self::log(array('api_error'=>$code,'body'=>substr($body,0,500)));
            $msg = 'HTTP '.$code;
            $j = json_decode($body, true);
            if (!empty($j['error']['message'])) $msg .= ': '.$j['error']['message'];
            error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - API Error: ' . $msg );
            error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - Full Error Response: ' . $body );
            file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': ERROR = ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX );
            file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': FULL ERROR = ' . $body . PHP_EOL, FILE_APPEND | LOCK_EX );
            return new WP_Error('api', $msg);
        }
        $j = json_decode($body, true);
        
        // Enhanced debugging for response structure
        error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - Success! Response structure: ' . print_r($j, true) );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - ' . $api_type . ' SUCCESS Response: ' . print_r($j, true) . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        $img = isset($j['data'][0]['url']) ? $j['data'][0]['url'] : ( $j['data'][0]['b64_json'] ?? '' );
        if (empty($img)){
            self::log(array('no_image_in_response'=>$body));
            error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - No image in response' );
            return new WP_Error('no_img','Antwort ohne Bilddaten.');
        }
        
        error_log( 'AWB Debug [' . $timestamp . ']: ' . $api_type . ' - SUCCESS! Image received' );
        file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', $timestamp . ' - OpenAI ' . $api_type . ': SUCCESS - Image received' . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        $saved = self::save_png($img);
        if (!$saved) return new WP_Error('save','Bild konnte nicht gespeichert werden.');
        return array('file'=>$saved[0], 'url'=>$saved[1]);
    }

}
