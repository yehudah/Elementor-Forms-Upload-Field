<?php
/*
Plugin Name: Elementor Pro Forms - Upload Field
Description: This extension will add upload field to Elementor Pro Forms
Plugin URI: http://wpdev.co.il
Author: Yehuda Hassine
Author URI: http://wpdev.co.il
Version: 1.0
License: GPL3
Text Domain: epfu
*/
use ElementorPro\Classes\Utils;

define( 'EPFU_URL', plugins_url( '/', __FILE__ ) );

class Epfu {

	private $upload_labels = array();

	public function __construct() {
		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'elementor_pro/forms/field_types', [ $this, 'add_field' ] );
		add_action( 'elementor_pro/forms/render_field/file', [ $this , 'render' ], 10, 3  );
		add_action( 'elementor_pro/forms/valid_record_submitted', [ $this, 'fetch_upload_labels'], 10, 3 );
		add_filter( 'elementor_pro/forms/wp_mail_message', [ $this, 'remove_file_html' ] );
		add_action( 'elementor_pro/forms/mail_sent', [ $this, 'mail_sent' ], 10, 3 );
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'epfu',
			EPFU_URL . 'script.js',
			[
				'jquery'
			],
			ELEMENTOR_PRO_VERSION,
			true
		);		
	}

	public function add_field( $field_types ) {
		$field_types['file'] = __( 'Upload', 'epfu' );

		return $field_types;
	}

	public function render( $item, $item_index, $form ) {
		$form->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );
		echo '<input size="1" ' . $form->get_render_attribute_string( 'input' . $item_index ) . '>';
	}

	public function fetch_upload_labels( $form_id, $settings, $record ) {
		foreach ( $record['fields'] as $key => $value ) {
			if ( 'file' === $value['type'] ) {
				$this->upload_labels[] = $value['title'] . ':';
			}
		}
	}

	public function remove_file_html( $email_text ) {
		$replace = array_pad( array(), count( $this->upload_labels ), '' );
		$email_text = str_replace( $this->upload_labels, $replace, $email_text );

		return $email_text;
	}


	public function mail_sent( $form_id, $settings, $record ) {
		if ( isset( $_FILES ) && ! empty( $_FILES['form_fields'] ) ) {

			if ( ! function_exists( 'wp_handle_upload' ) ) {
			    require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}

			$attachments = [];
			$file_names = array_values( $_FILES['form_fields']['name'] );
			$file_pathes = array_values( $_FILES['form_fields']['tmp_name'] );

			foreach ( $file_pathes as $key => $file_path ) {
				if ( ! empty( $file_path ) ) {
					$info_original = pathinfo( $file_names[ $key ] );
					$info_upload = pathinfo( $file_path );

					$new_path = $info_upload['dirname'] . DIRECTORY_SEPARATOR . $info_original['basename'];;

					rename( $file_path, $new_path );	

					$attachments[] = $new_path;
				}
			}

			if ( empty( $attachments ) ) {
				return;
			}

			$email_to = trim( $settings['email_to'] );
			if ( empty( $email_to ) ) {
				$email_to = get_option( 'admin_email' );
			}

			$email_subject = trim( $settings['email_subject'] );
			if ( empty( $email_subject ) ) {
				$email_subject = sprintf( __( 'New message from "%s"', 'epfu' ), get_bloginfo( 'name' ) );
			}

			$email_from_name = $settings['email_from_name'];
			if ( empty( $email_from_name ) ) {
				$email_from_name = get_bloginfo( 'name' );
			}

			$email_from = $settings['email_from'];
			if ( empty( $email_from ) ) {
				$email_from = get_bloginfo( 'admin_email' );
			}

			$email_reply_to_setting = $settings['email_reply_to'];
			$email_reply_to = '';

			if ( ! empty( $email_reply_to_setting ) ) {
				foreach ( $fields as $field_index => $field ) {
					if ( $field['_id'] === $email_reply_to_setting ) {
						$email_reply_to = $form_raw_data[ $field_index ];
						break;
					}
				}
			}

			if ( empty( $email_reply_to ) ) {
				$email_reply_to = 'noreplay@' . Utils::get_site_domain();
			}

			$headers = sprintf( 'From: %s <%s>' . "\r\n", $email_from_name, $email_from );
			$headers .= sprintf( 'Reply-To: %s' . "\r\n", $email_reply_to );

			if ( 'yes' === $settings['send_html'] ) {
				$headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
				$email_text = nl2br( $email_text );
			}

			$headers    = apply_filters( 'elementor_pro/forms/wp_mail_headers', $headers );
			$count = count( $attachments );
			$email_text = sprintf( esc_html( _n( '%d uploaded file attached.', '%d uploaded files attached.', $count, 'epfu'  ) ), $count );
						
			wp_mail( $email_to, $email_subject, $email_text, $headers, $attachments );
		
		}
	}
}

new Epfu;