<?php
/* OnPageConversion Activation Page */
// TODO: переписать в static class
class onpageconversion_Activation_Page {

    public function __construct() {
	    add_action( 'admin_menu', array( $this, 'wph_create_settings' ) );
		add_action( 'admin_init', array( $this, 'wph_setup_sections' ) );
		add_action( 'admin_init', array( $this, 'wph_setup_fields' ) );
        add_action( 'redirect_to_parameters', array( $this, 'opc_activation_redirect') );
	}

    // TODO: нужен редирект если скипаем 2й экран с параметрами
    function opc_activation_redirect( ) {
        exit( wp_redirect( admin_url( 'admin.php?page=onpageconversion_parameters' ) ) );
    }

    public function wph_create_settings() {
	    $page_title = 'OnPageConversion activations';
		$menu_title = 'OnPageConversion';
		$capability = 'manage_options';
		$slug = 'onpageconversion_activation';
		$callback = array($this, 'wph_settings_content');
		$icon = 'dashicons-admin-settings';
		$position = 60;
		$hook = add_menu_page($page_title, $menu_title, $capability, $slug, $callback, $icon, $position);
        add_action('load-'.$hook, array( $this, 'do_on_plugin_settings_save'));
	}

    public function wph_settings_content() {
        ?>
	   <div class="wrap">
			<h1>OnPageConversion</h1>
			<?php settings_errors('onpageconversion_activation_msg'); ?>
			<form method="POST" action="options.php">
			<?php
				settings_fields( 'onpageconversion_activation' );
				do_settings_sections( 'onpageconversion_activation' );
				submit_button();
			?>
			</form>
		</div>
        <?php
	}

    public function wph_setup_sections() {
        add_settings_section( 'onpageconversion_activation_section', 'Activation', array(), 'onpageconversion_activation' );
	}

    public function wph_setup_fields() {
	       $fields = array(
            array(
				'label' => 'Email',
				'id' => 'opc_email',
				'type' => 'text',
				'section' => 'onpageconversion_activation_section',
                'placeholder' => 'you@mail.com',
				'desc' => 'Your email at OnPageConversion.com',
			),
            array(
				'label' => 'Password',
				'id' => 'opc_password',
				'type' => 'text',
				'section' => 'onpageconversion_activation_section',
                'placeholder' => 'Password',
				'desc' => 'Your password at OnPageConversion.com',
			),
		);
		foreach( $fields as $field ){
			add_settings_field( $field['id'], $field['label'], array( $this, 'wph_field_callback' ), 'onpageconversion_activation', $field['section'], $field );
			register_setting( 'onpageconversion_activation', $field['id'] );
		}
	}

    public function wph_field_callback( $field ) {
        $users = get_users( array(
            	'role'   => 'administrator',
            	'fields' => ['user_email'],
        ) );

        $emails = wp_list_pluck( $users, 'user_email' );

        $admin_email = $emails[0];

        $opc_email = get_option('opc_email');
        $value = get_option( $field['id'] );

		switch ( $field['type'] ) {
			default:
                if ($field['id'] == 'opc_email' & empty($opc_email)){
                    printf( '<input name="%1$s" id="%1$s" type="%2$s" value="'.$admin_email.'" />',
    					$field['id'],
    					$field['type'],
    					$field['placeholder'],
    					$value
    				);
                } else {
                    printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />',
    					$field['id'],
    					$field['type'],
    					$field['placeholder'],
    					$value
    				);
                }
		}
		if( $desc = $field['desc'] ) {
			printf( '<p class="description">%s </p>', $desc );
		}
	}

    // вызов API при сохранении начальных настроек
    public function do_on_plugin_settings_save(){
        if(isset($_GET['settings-updated']) && $_GET['settings-updated']){
            // Активация по API
            $url = 'https://convertme.onpageconversion.com/api/wordpress/v1.0/messages.json';
            // язык текущего пользователя
            $lang = get_user_locale(get_current_user_id());
            // аргументы вызова метода API messages.json
            $args = array(
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers' => array(),
                'body'    => array(
                    'code'       => 'first_screen',
                    'language'   => $lang
                ),
                'cookies' => array()
            );

            // вызываем метод API messages.json
            $messages_response = wp_remote_get( $url, $args );
            if ( is_wp_error( $messages_response ) ){
                $user_notice_activate =  $messages_response->get_error_message();
                add_settings_error( 'onpageconversion_activation_msg', 'setting_update', $user_notice_activate, 'error' );
            } else {
                $messages_response_code = wp_remote_retrieve_response_code($messages_response);
                $messages_response_msg = wp_remote_retrieve_response_message($messages_response);
                $messages_user_notice = 'Call to messages.json. Response: '.$messages_response_code.':('.$messages_response_msg.')';

                //200 - OK, всё хорошо: либо был создан новый пользователь с новым ключём, либо вернулась информация уже существующего пользователя.
                if ($messages_response_code === 200) {
                    $messages_response_body = json_decode(wp_remote_retrieve_body($messages_response));
                    // TODO: Если вернулся 200-ый статус, то в response body вернётся JSON, и в базу данных Wordpress-a сохранить параметры:

                    //add_settings_error( 'onpageconversion_activation_msg', 'setting_update', $user_notice.' '.$opc_text->help_text, 'success' );
                    //print_r($opc_parameters);
                    //do_action('redirect_to_parameters');

                    // Вызываем 2й метод API activate.json
                    $url_activate = "https://convertme.onpageconversion.com/api/wordpress/v1.0/plugin/activate.json";
                    // аргументы вызова метода API activate.json
                    $opc_email_option = get_option('opc_email');
                    $opc_password = get_option('opc_password');

                    $site_url = get_site_url();
                    $args_activate = array(
                    	'timeout'     => 45,
                    	'redirection' => 5,
                    	'httpversion' => '1.0',
                    	'blocking'    => true,
                    	'headers' => array(),
                    	'body'    => array(
                            'domain'    => $site_url,
                            'email'     => $opc_email_option,
                            'password'  => $opc_password,
                            'language'  => $lang,
                        ),
                    	'cookies' => array()
                    );
                    $response_activate = wp_remote_post( $url_activate, $args_activate );
                    $response_code_activate = wp_remote_retrieve_response_code($response_activate);
                    $response_message_activate = wp_remote_retrieve_response_message($response_activate);
                    $user_notice_activate = $response_code_activate.':'.$response_message_activate;
                    // Если вернулся код 200
                    if ($response_code_activate === 200) {
                        $response_body_activate = json_decode(wp_remote_retrieve_body($response_activate));

                        $opc_user_created = (string)$response_body_activate->user_created;
                        $opc_cookie_key = (string)$response_body_activate->cookie_key;
                        $opc_limit_base_pages = $response_body_activate->limit_base_pages;
                        $opc_js_script =  $response_body_activate->js_script;
                        $opc_access_key_created = esc_html($response_body_activate->access_key_created);
                        $opc_api_call_timeout_ms = esc_html($response_body_activate->api_call_timeout_ms);
                        $opc_access_keys_array = $response_body_activate->access_keys;
                        foreach ($response_body_activate->access_keys as $access_key) {
                            $opc_access_key = $access_key->access_key;
                            $opc_domain = $access_key->domain;
                            $opc_type = $access_key->type;
                        }

                        $messages_user_notice .= '<br> Call to activate.json. Response:'.$response_code_activate.'('.$response_message_activate.')<br>';

                        // сформируем сообщение для пользователя
                        $messages_user_notice .='<br>api_call_timeout_ms='.$opc_api_call_timeout_ms;
                        $messages_user_notice .='<br>access_key='.$opc_access_key;
                        $messages_user_notice .='<br>domain='.$opc_domain;
                        $messages_user_notice .='<br>type='.$opc_type;
                        $messages_user_notice .='<br>cookie_key='.$opc_cookie_key;
                        $messages_user_notice .='<br>limit_base_pages='.$opc_limit_base_pages;
                        //$messages_user_notice .='<br>script='.$opc_js_script;
                        // Сохраним параметры в таблицу опций
    					add_option('opc_api_call_timeout_ms', $opc_api_call_timeout_ms);
    					add_option('opc_access_key', $opc_access_key);
    					add_option('opc_domain', $opc_domain);
    					add_option('opc_type', $opc_type);
                        add_option('opc_cookie_key', $opc_cookie_key);
                        add_option('opc_limit_base_pages', $opc_limit_base_pages);
                        add_option('opc_script', $opc_js_script);

                        add_settings_error( 'onpageconversion_activation_msg', 'setting_update', $messages_user_notice, 'success' );

                    } else {
                        add_settings_error( 'onpageconversion_activation_msg', 'setting_update', $user_notice_activate, 'error' );

                        if (is_wp_error($response_activate)) {
                            $error_message = $response_activate->get_error_message();
                        }
                    }
                } else {
                    add_settings_error( 'onpageconversion_activation_msg', 'setting_update', $user_notice, 'error' );
                    //do_action('redirect_to_parameters');
                    $response_body = json_decode(wp_remote_retrieve_body( $response ));
                    if (is_wp_error( $response )) {
                        $error_message = $response->get_error_message();
                    }
                }
            }
        }
    }

}
new onpageconversion_Activation_Page();

/* OnPageConversion Settings Page
class onpageconversion_Parameters_Page {

    public function __construct() {
	    add_action( 'admin_menu', array( $this, 'opc_create_settings' ) );
		add_action( 'admin_init', array( $this, 'opc_setup_sections' ) );
		add_action( 'admin_init', array( $this, 'opc_setup_fields' ) );

	}

    public function opc_create_settings() {
		$page_title = 'OnPageConversion parameters';
		$menu_title = 'Parameters';
		$capability = 'manage_options';
		$menu_slug = 'onpageconversion_parameters';
		$callback = array($this, 'opc_settings_content');
        $parent_slug = 'onpageconversion_activation';
        $hook = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback );
        add_action('load-'.$hook, array( $this,'do_on_plugin_parameters_save'));
	}

    public function opc_settings_content() { ?>
		<div class="wrap">
			<h1>OnPageConversion</h1>
			<?php settings_errors('onpageconversion_parameters_msg'); ?>
			<form method="POST" action="options.php">
				<?php
					settings_fields( 'onpageconversion_parameters' );
					do_settings_sections( 'onpageconversion_parameters' );
					submit_button();
				?>
			</form>
		</div>
        <?php
	}

    public function opc_setup_sections() {
        add_settings_section( 'onpageconversion_parameters_section', 'Parameters', array(), 'onpageconversion_parameters' );
	}

    public function opc_setup_fields() {
	       $fields = array(
            array(
				'label' => 'Activation key',
				'id' => 'opc_key',
				'type' => 'text',
				'section' => 'onpageconversion_parameters_section',
                'placeholder' => '123',
				'desc' => 'Your activation key',
			),
			array(
				'label' => 'Timeout',
				'id' => 'opc_timeout',
				'type' => 'text',
				'section' => 'onpageconversion_parameters_section',
                'placeholder' => '123',
				'desc' => 'Default timeout for api calls',
			),
            array(
				'label' => 'Number of alternative pages',
				'id' => 'opc_pages_number',
				'type' => 'text',
				'section' => 'onpageconversion_parameters_section',
                'placeholder' => '123',
				'desc' => 'Maximum number of alternative pages',
			),
		);
		foreach( $fields as $field ){
			add_settings_field( $field['id'], $field['label'], array( $this, 'opc_field_callback' ), 'onpageconversion_parameters', $field['section'], $field );
			register_setting( 'onpageconversion_parameters', $field['id'] );
		}
	}

    public function opc_field_callback( $field ) {
	    $value = get_option( $field['id'] );
		switch ( $field['type'] ) {
			default:
				printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />',
					$field['id'],
					$field['type'],
					$field['placeholder'],
					$value
				);
		}
		if( $desc = $field['desc'] ) {
			printf( '<p class="description">%s </p>', $desc );
		}
	}

    // вызов API при сохранении начальных настроек

    public function do_on_plugin_parameters_save(){
        if(isset($_GET['settings-updated']) && $_GET['settings-updated']){
            // Настройка Параметров
            $url = "https://convertme.onpageconversion.com/api/wordpress/v1.0/plugin/activate.json";
            $site_url = get_site_url();
            $args = array(
            	'timeout'     => 5,
            	'redirection' => 5,
            	'httpversion' => '1.0',
            	'blocking'    => true,
            	'headers' => array(),
            	'body'    => array(
                    'domain'    => $site_url,
                    'email'     =>'admin@wordpress-site.com',
                    'password'  =>'my-secret-password',
                    'language'  =>'en',
                ),
            	'cookies' => array()
            );
            $response = wp_remote_post( $url, $args );
            // ответ
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_message = wp_remote_retrieve_response_message( $response );
            $user_notice = $response_code.':'.$response_message;
            if ($response_code === 200) {
                $response_body = json_decode(wp_remote_retrieve_body( $response ));
                add_settings_error( 'onpageconversion_parameters_msg', 'setting_update', $user_notice, 'success' );
            } else {
                add_settings_error( 'onpageconversion_parameters_msg', 'setting_update', $user_notice, 'error' );

                if (is_wp_error( $response )) {
                    $error_message = $response->get_error_message();
                }
            }
        }
    }
}

new onpageconversion_Parameters_Page();*/
?>
