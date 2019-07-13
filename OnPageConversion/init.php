<?php
/*
Plugin Name: OnPageConversion
Plugin URI: http://страница_с_описанием_плагина_и_его_обновлений
Description: Краткое описание плагина.
Version: 1.2
Author: Evgeniy Rezanov
Author URI: https://www.upwork.com/fl/evgeniirezanov
*/

require_once 'inc/activation.php';
require_once 'inc/metaboxes.php';

class OnPageConversion {

    public static $alturl;

    public static $need_replace;

    public static function init() {
	    add_action( 'activated_plugin', array( __CLASS__, 'opc_activation_redirect') );
		add_action( 'wp', array( __CLASS__, 'opc_ai_page' ) );
        add_action( 'the_content', array( __CLASS__, 'opc_show_alt_content') );
        add_action( 'wp_footer', array( __CLASS__, 'opc_add_javascript') );
	}

    // активация плагина
    public static function opc_activation_redirect( $plugin ) {
        if( $plugin == plugin_basename( __FILE__ ) ) {
            exit( wp_redirect( admin_url( 'admin.php?page=onpageconversion_activation' ) ) );
        }
    }

    // загрузка страницы
    public static function opc_ai_page () {
        global $wp_query;
        // если это страница front-end
        if (!is_admin()) {
            $post_id = $wp_query->post->ID;
            if ('page' === get_post_type($post_id)) {
                $flag = 0;
                // проверим есть ли у нее альтернативные страницы
                for ($i = 1; $i <= 5; $i++) {
                    $alt_url = get_post_meta($post_id, 'opc_alt_page_'.$i, true);
                    if ($alt_url) {
                        $flag++;
                    }
                }
                // если есть то обратимся к api
                if ($flag > 0) {
                    $cookie_key = get_option('opc_cookie_key');
                    $api_call_timeout_ms = get_option('opc_api_call_timeout_ms');
                    if(isset($_COOKIE[$cookie_key])){
                        //Если пользовательская кука присутствует, то посылаем её значение в запросе
                        $url = "https://convertme.onpageconversion.com/api/wordpress/v1.0/plugin/ai-page.json";
                        $base_id = get_permalink($post_id);
                        $user_ip = self::get_the_user_ip();
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $body_parameter_array = array(
                            'base_id'       => get_permalink($post_id),
                            'user_ip'       => self::get_the_user_ip(),
                            'user_agent'    => (string)$_SERVER['HTTP_USER_AGENT'],
                            'cookie_value'  => $_COOKIE[$cookie_key]
                        );
                        // аргументы вызова метода API ai-page.json
                        $args_urls = array(
            				'method'	   => 'POST',
                            'timeout'      => $api_call_timeout_ms/1000,
                            'redirection'  => 5,
                            'headers' => array(
                                'content-type' =>  'application/json',
                                'x-access-key' =>  get_option('opc_access_key'),
                            ),
            				'body' => json_encode($body_parameter_array, JSON_UNESCAPED_SLASHES),
                        );
                        $response_ai_page = wp_remote_request($url, $args_urls);
                        //var_dump($response_ai_page);
                        $response_ai_page_code = wp_remote_retrieve_response_code($response_ai_page);
                        if ($response_ai_page_code === 200) {
                            $response_ai_page_body = json_decode(wp_remote_retrieve_body($response_ai_page));
                            $cookie_value = $response_ai_page_body->cookie_value;
                            $alt_url_id = $response_ai_page_body->alt_url_id;
                            $cache_msg = $response_ai_page_body->cache_msg;
                            $cookie_expires = (string)$response_ai_page_body->cookie_expires;
                            // подменяем контент страницы
                            self::$alturl = $alt_url_id;
                            self::$need_replace = true;
                        }
                    } else {
                        //все равно показываем альтернативную страницу
                        //Если пользовательской куки нет, то посылаем запрос без неё (она вернётся в response и её надо установить пользователю)
                        $url = "https://convertme.onpageconversion.com/api/wordpress/v1.0/plugin/ai-page.json";

                        $base_id = get_permalink($post_id);
                        $user_ip = self::get_the_user_ip();
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $body_parameter_array = array(
                            'base_id'    => get_permalink($post_id),
                            'user_ip'    => self::get_the_user_ip(),
                            'user_agent' => (string)$_SERVER['HTTP_USER_AGENT'],
                        );
                        $args_urls = array(
            				'method'	   => 'POST',
                            'timeout'      => $api_call_timeout_ms/1000,
                            'redirection'  => 5,
                            'headers' => array(
                                'content-type' =>  'application/json',
                                'x-access-key' =>  get_option('opc_access_key'),
                            ),
            				'body' => json_encode($body_parameter_array, JSON_UNESCAPED_SLASHES),
                        );

                        $response_ai_page = wp_remote_request($url, $args_urls);
                        $response_ai_page_code = wp_remote_retrieve_response_code($response_ai_page);
                        //var_dump($response_ai_page);
                        if ($response_ai_page_code === 200) {
                            $response_ai_page_body = json_decode(wp_remote_retrieve_body($response_ai_page));
                            $cookie_value = $response_ai_page_body->cookie_value;
                            $alt_url_id = $response_ai_page_body->alt_url_id;
                            $cache_msg = $response_ai_page_body->cache_msg;
                            $find = array( 'http://', 'https://' );
                            $coockie_domain = str_replace( $find, '', site_url() );
                            $cookie_expires = $response_ai_page_body->cookie_expires;
                            $cookie_expires = strtotime($cookie_expires);
                            $res=setcookie( $cookie_key, $cookie_value, $cookie_expires, '/', $coockie_domain );
                            // подменяем контент страницы
                            self::$alturl = $alt_url_id;
                            self::$need_replace = true;
                        }
                    }
                }
            }
        }
    }

    // получение IP пользователя
    public static function get_the_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return apply_filters('wpb_get_ip', $ip);
    }
    /*
    public static function pippin_get_image_id( $image_url ) {
	       global $wpdb;
	          $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url ));
	             return $attachment[0];
    }
    */

    // подмена контента
    public static function opc_show_alt_content( $content ) {
        // TODO: добавить проверку на базовую страницу
        //if (is_page('base')){
            if (self::$alturl<>'' AND self::$need_replace) {
                 $alt_page_id = self::$alturl;
        		 $alt_post = get_post($alt_page_id);
        		 $content = $alt_post->post_content;
            }
    	//}
    	return $content;
    }

    // JS script на всех страницах сайта
    public static function opc_add_javascript(){
        // если есть опция opc_script
        $opc_script = get_option('opc_script');
	    if ($opc_script) {
            // выведем скрипт на всех страницах сайта
            echo $opc_script;
        }
    }
}
if (class_exists('OnPageConversion')) {
	OnPageConversion::init();
};
?>
