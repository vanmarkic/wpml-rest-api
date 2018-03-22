<?php

namespace WpmlRestApi;

/**
 * WPML REST API class
 */

class WpmlRestApi
{
    public static function getApiNamespace()
    {
        return 'wp/v2';
    }

    public static function getPluginNamespace()
    {
        return 'wpml-rest-api/v2';
    }

    public function registerRoutes()
    {
        register_rest_route(self::getPluginNamespace(), '/languages', array(
                array(
                        'methods'  => \WP_REST_Server::READABLE,
                        'callback' => array( $this, 'getLanguages' ),
                )
        ));
    }

    public static function getLanguages()
    {
        $rest_url = trailingslashit(get_rest_url() . self::getPluginNamespace() . '/languages/');

        $languages = array();
        $languages['id'] = 1;
        $languages['active_languages'] = apply_filters('wpml_active_languages', null);
        $languages['wpml_default_language'] = apply_filters('wpml_default_language', null);
        $languages['wpml_current_language'] = apply_filters('wpml_current_language', null);
        $languages['meta']['links']['collection'] = $rest_url;
        return $languages;
    }
}
