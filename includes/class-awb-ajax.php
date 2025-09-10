<?php
/**
 * AJAX handlers for AI Wallpaper Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AWB_Ajax {

	public function __construct() {
		// Add the missing awb_generate action that frontend.js is calling
		add_action( 'wp_ajax_awb_generate', array( $this, 'generate_openai_image' ) );
		add_action( 'wp_ajax_nopriv_awb_generate', array( $this, 'generate_openai_image' ) );
		
		add_action( 'wp_ajax_awb_generate_openai_image', array( $this, 'generate_openai_image' ) );
		add_action( 'wp_ajax_nopriv_awb_generate_openai_image', array( $this, 'generate_openai_image' ) );
		
		// Add cropped image save handler
		add_action( 'wp_ajax_awb_save_cropped_image', array( $this, 'save_cropped_image' ) );
		add_action( 'wp_ajax_nopriv_awb_save_cropped_image', array( $this, 'save_cropped_image' ) );
	}

	/**
	 * Generate OpenAI image
	 */
	public function generate_openai_image() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'awb_ajax' ) ) {
			wp_die( 'Nonce verification failed' );
		}

		// Get parameters from frontend.js
		$product_id = intval( $_POST['product_id'] ?? 0 );
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$description = sanitize_text_field( $_POST['description'] ?? '' );
		$style = sanitize_text_field( $_POST['style'] ?? '' );
		$correction = sanitize_text_field( $_POST['correction'] ?? '' );

		// Fallback for direct prompt parameter
		if ( empty( $name ) && ! empty( $_POST['prompt'] ) ) {
			$name = sanitize_text_field( $_POST['prompt'] );
		}

		if ( empty( $name ) ) {
			wp_send_json_error( 'Name/Prompt ist erforderlich für die Bildgenerierung' );
		}

		// Check if OpenAI is configured
		$settings = AWB_Settings::get();
		if ( empty( $settings['api_key'] ) ) {
			wp_send_json_error( 'OpenAI API Key ist nicht konfiguriert. Bitte gehen Sie zu AI Wallpaper > Einstellungen und tragen Sie Ihren API Key ein.' );
		}

		// Get master prompt and reference image from product
		$master_prompt = '';
		$reference_image = '';
		$meta_fields = array();
		
		if ( $product_id ) {
			$master_prompt = get_post_meta( $product_id, '_aiw_master_prompt', true );
			$reference_image = get_post_meta( $product_id, '_aiw_reference_image', true );
			
			// Get all meta fields from the form data
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'awb_' ) === 0 ) {
					// Extract meta key name (remove awb_ prefix)
					$meta_key = substr( $key, 4 );
					$meta_fields[$meta_key] = sanitize_text_field( $value );
				}
			}
		}

		// Debug logging with timestamp
		$timestamp = date('Y-m-d H:i:s');
		error_log( 'AWB Debug [' . $timestamp . ']: ========== AJAX REQUEST START ==========' );
		error_log( 'AWB Debug [' . $timestamp . ']: Generating image with name: ' . $name . ', style: ' . $style . ', product_id: ' . $product_id );
		error_log( 'AWB Debug [' . $timestamp . ']: Master prompt: "' . $master_prompt . '"' );
		error_log( 'AWB Debug [' . $timestamp . ']: Reference image: "' . $reference_image . '"' );
		error_log( 'AWB Debug [' . $timestamp . ']: Reference image empty? ' . (empty($reference_image) ? 'YES' : 'NO') );
		error_log( 'AWB Debug [' . $timestamp . ']: Meta fields: ' . print_r( $meta_fields, true ) );
		
		// Additional debug for product_id issues
		if ($product_id === 0) {
			error_log( 'AWB Debug [' . $timestamp . ']: WARNING: product_id is 0 - no product meta will be loaded!' );
		}
		
		// Backup logs to custom file
		file_put_contents( WP_CONTENT_DIR . '/awb-debug.log', 
			$timestamp . ' - AJAX: name=' . $name . ', style=' . $style . ', product_id=' . $product_id . PHP_EOL .
			$timestamp . ' - Master prompt: ' . $master_prompt . PHP_EOL .
			$timestamp . ' - Reference image: ' . $reference_image . PHP_EOL, 
			FILE_APPEND | LOCK_EX );

		// Call correct static method with master prompt and meta fields
		$result = AWB_OpenAI::generate( $name, $description, $style, $correction, $master_prompt, $meta_fields, $reference_image );

		if ( is_wp_error( $result ) ) {
			error_log( 'AWB Debug: OpenAI error: ' . $result->get_error_message() );
			wp_send_json_error( $result->get_error_message() );
		} else {
			// Build prompt for storage
			$prompt = AWB_OpenAI::build_prompt( $name, $description, $style, $master_prompt, $meta_fields );
			if ( $correction ) {
				$prompt .= "\nKorrektur: " . $correction;
			}
			
			error_log( 'AWB Debug: Image generated successfully, URL: ' . $result['url'] );
			wp_send_json_success( array(
				'url' => $result['url'],
				'file' => $result['file'],
				'prompt' => $prompt
			) );
		}
	}

	/**
	 * Save cropped image uploaded from frontend
	 */
	public function save_cropped_image() {
		// Verify nonce if provided
		if ( isset( $_POST['nonce'] ) && ! empty( $_POST['nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['nonce'], 'awb_frontend_nonce' ) ) {
				wp_send_json_error( 'Nonce verification failed' );
			}
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['cropped_image'] ) ) {
			wp_send_json_error( 'No image file provided' );
		}

		$file = $_FILES['cropped_image'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( 'File upload error: ' . $file['error'] );
		}

		// Check file type
		$allowed_types = array( 'image/jpeg', 'image/jpg' );
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( 'Only JPG images are allowed' );
		}

		// Get size information from POST data
		$width = isset( $_POST['width'] ) ? intval( $_POST['width'] ) : 0;
		$height = isset( $_POST['height'] ) ? intval( $_POST['height'] ) : 0;
		$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';
		
		// If no order_id provided, generate a temporary one
		if ( empty( $order_id ) ) {
			$order_id = 'TMP_' . time();
		}
		
		// Generate filename with OrderID and customer size
		$upload_dir = wp_upload_dir();
		$size_part = $width && $height ? $width . 'x' . $height . 'cm' : 'unknown_size';
		$filename = $order_id . '_awb_crop_' . $size_part . '_' . wp_generate_password( 6, false ) . '.jpg';
		$file_path = $upload_dir['path'] . '/' . $filename;
		$file_url = $upload_dir['url'] . '/' . $filename;

		// Move uploaded file
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			wp_send_json_error( 'Failed to save uploaded file' );
		}

		// Create attachment
		$attachment = array(
			'guid'           => $file_url,
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'AWB Cropped Image',
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( 'Failed to create attachment' );
		}

		// Generate attachment metadata
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		wp_send_json_success( array(
			'filename' => $filename,
			'url' => $file_url,
			'attachment_id' => $attachment_id,
			'path' => $file_path
		) );
    }
}
