<?php

class onpageconversionalatMetabox {

	private static $screen = array(
		'page',
	);

    private static $meta_fields = array(
		array(
			'label' => 'Alternative page id 1',
			'id' => 'opc_alt_page_1',
			'type' => 'text',
		),
		array(
			'label' => 'Alternative page id 2',
			'id' => 'opc_alt_page_2',
			'type' => 'text',
		),
		array(
			'label' => 'Alternative page id 3',
			'id' => 'opc_alt_page_3',
			'type' => 'text',
		),
		array(
			'label' => 'Alternative page id 4',
			'id' => 'opc_alt_page_4',
			'type' => 'text',
		),
		array(
			'label' => 'Alternative page id 5',
			'id' => 'opc_alt_page_5',
			'type' => 'text',
		),
	);

    public static function init() {
	    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_fields' ) );
        add_action( 'save_post', array( __CLASS__, 'call_opc_api') );
        add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

	}

    public static function add_meta_boxes() {
	    foreach ( self::$screen as $single_screen ) {
			add_meta_box(
				'onpageconversionalat',
				__( 'OnPageConversional aternative pages', 'onpageconversion' ),
				array( __CLASS__, 'meta_box_callback' ),
				$single_screen,
				'normal',
				'low'
			);
		}
	}

    public static function meta_box_callback( $post ) {
	    wp_nonce_field( 'onpageconversionalat_data', 'onpageconversionalat_nonce' );
		echo 'Enter OnPageConversional aternative pages';
		self::field_generator( $post );
	}

    public static function field_generator( $post ) {
	    $output = '';
		foreach ( self::$meta_fields as $meta_field ) {
			$label = '<label for="' . $meta_field['id'] . '">' . $meta_field['label'] . '</label>';
			$meta_value = get_post_meta( $post->ID, $meta_field['id'], true );
			if ( empty( $meta_value ) ) {
				$meta_value = $meta_field['default']; }
			switch ( $meta_field['type'] ) {
				default:
					$input = sprintf(
						'<input %s id="%s" name="%s" type="%s" value="%s">',
						$meta_field['type'] !== 'color' ? 'style="width: 100%"' : '',
						$meta_field['id'],
						$meta_field['id'],
						$meta_field['type'],
						$meta_value
					);
			}
			$output .= self::format_rows( $label, $input );
		}
		echo '<table class="form-table"><tbody>' . $output . '</tbody></table>';
	}

    public static function format_rows( $label, $input ) {
		return '<tr><th>'.$label.'</th><td>'.$input.'</td></tr>';
	}

    public static function save_fields( $post_id ) {
	    if ( ! isset( $_POST['onpageconversionalat_nonce'] ) )
			return $post_id;
		$nonce = $_POST['onpageconversionalat_nonce'];
		if ( !wp_verify_nonce( $nonce, 'onpageconversionalat_data' ) )
			return $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;
		foreach ( self::$meta_fields as $meta_field ) {
			if ( isset( $_POST[ $meta_field['id'] ] ) ) {
				switch ( $meta_field['type'] ) {
					case 'email':
						$_POST[ $meta_field['id'] ] = sanitize_email( $_POST[ $meta_field['id'] ] );
						break;
					case 'text':
						$_POST[ $meta_field['id'] ] = sanitize_text_field( $_POST[ $meta_field['id'] ] );
						break;
				}
				update_post_meta( $post_id, $meta_field['id'], $_POST[ $meta_field['id'] ] );
			} else if ( $meta_field['type'] === 'checkbox' ) {
				update_post_meta( $post_id, $meta_field['id'], '0' );
			}
		}
	}

    public static function call_opc_api ( $post_id ) {
        if ( ! isset( $_POST['onpageconversionalat_nonce'] ) )
			return $post_id;
		$nonce = $_POST['onpageconversionalat_nonce'];
		if ( !wp_verify_nonce( $nonce, 'onpageconversionalat_data' ) )
			return $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

        // Вызываем метод API для передачи альтернативных страниц urls.json
        $url = "https://convertme.onpageconversion.com/api/wordpress/v1.0/plugin/urls.json";
            // соберм альтернативные url для передачи в сервис OnPageConversion
            $alternative_pages_array = array();
            // Проверим все 5 мет, занесем в массив
            for ($i = 1; $i <= 5; $i++) {
                $alt_url = get_post_meta($post_id, 'opc_alt_page_'.$i, true);
                if ($alt_url) {
                    $alternative_pages_array[] = array(
							'id'     => $alt_url,
                        	'active' => true
                    );
                }
            }

            $current_url = get_permalink($post_id);
			$new_array = array(
                'base_id'   => $current_url,
				'urls'      => $alternative_pages_array
			);

			$access_key = get_option('opc_access_key');
			$api_call_timeout_ms = get_option('opc_api_call_timeout_ms');

            // аргументы вызова метода API urls.json
            $args_urls = array(
				'method'	=> 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'headers' => array(
                    'content-type' =>  'application/json',
                    'x-access-key' =>  $access_key,
                ),
				'body' => json_encode($new_array, JSON_UNESCAPED_SLASHES),
            );

            $response_activate = wp_remote_request($url, $args_urls);
            $response_code_activate = wp_remote_retrieve_response_code($response_activate);
            $response_message_activate = wp_remote_retrieve_response_message($response_activate);
            $user_notice_activate = $response_code_activate.':'.$response_message_activate;

            // Если вернулся код 200
            if ($response_code_activate === 200) {
                $response_body_activate = json_decode(wp_remote_retrieve_body($response_activate));
                add_post_meta($post_id, 'opc_save_alt_page_date', date('m/d/Y h:i:s a', time()), true);
                add_post_meta($post_id, 'opc_save_alt_page_msg', $user_notice_activate, true);
                // добавим сообщение об успешном обращении к API
                add_filter( 'redirect_post_location', array( __CLASS__, 'add_notice_query_var' ), 99 );
            } else {
                if (is_wp_error($response_activate)) {
                    $error_message = $response_activate->get_error_message();
                }
				// добавим сообщение об ошибке при обращении к API
                add_post_meta($post_id, 'opc_save_alt_page_date', date('m/d/Y h:i:s a', time()), true);
                add_post_meta($post_id, 'opc_save_alt_page_response_code',$user_notice_activate, true);
                add_filter( 'redirect_post_location', array( __CLASS__, 'add_notice_query_var' ), 99 );
            }
        return $post_id;
    }

    public static function add_notice_query_var( $location ) {
        remove_filter( 'redirect_post_location', array( __CLASS__, 'add_notice_query_var' ), 99 );
        return add_query_arg( array( 'opc_saved_base_page' => 'ok' ), $location );
    }

    public static function admin_notices() {
        global $post;
        if ( !isset($_GET['opc_saved_base_page']) & !is_null(get_post_meta($post->ID, 'opc_save_alt_page_msg', true)) ) {
            return;
        } else {
        $msg = get_post_meta($post->ID, 'opc_save_alt_page_msg', true);
        if ($msg=='200:OK') {
            ?>
            <div class="updated is-dismissible">
                <p><?php esc_html_e( "OnPageConversion[".$msg."]", 'onpageconversion' ); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="error is-dismissible">
                <p><?php esc_html_e( "OnPageConversion[".$msg."]", 'onpageconversion' ); ?></p>
            </div>
            <?php
        }
        }
    }
}
if (class_exists('onpageconversionalatMetabox')) {
	onpageconversionalatMetabox::init();
};

?>
