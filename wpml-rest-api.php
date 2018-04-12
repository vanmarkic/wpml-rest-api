<?php
/*
Plugin Name: WPML REST API
Version: 1.0
Description: Adds links to posts in other languages into the results of a WP REST API query for sites running the WPML plugin.
Author: Shawn Hooper
Author URI: https://profiles.wordpress.org/shooper
*/

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use WpmlRestApi\WpmlRestApi;

require 'inc/class-wpml-rest-api.php';

add_action('rest_api_init', 'wpmlrestapi_init', 1000);

function wpmlrestapi_init()
{

    // Check if WPML is installed
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    if (!class_exists('Sitepress')) {
        return;
    }

    $available_langs = wpml_get_active_languages_filter('', array('skip_missing' => false, ));

    if (! empty($available_langs) && ! isset($GLOBALS['icl_language_switched']) || ! $GLOBALS['icl_language_switched']) {
        if (isset($_REQUEST['wpml_lang'])) {
            $lang = $_REQUEST['wpml_lang'];
        } elseif (isset($_REQUEST['lang'])) {
            $lang = $_REQUEST['lang'];
        }

        if (isset($lang) && in_array($lang, array_keys($available_langs))) {
            do_action('wpml_switch_language', $lang);
        }
    }

    // Add WPML fields to all post types
    // Thanks to Roy Sivan for this trick.
    // http://www.roysivan.com/wp-api-v2-adding-fields-to-all-post-types/#.VsH0e5MrLcM

    $post_types = get_post_types(array( 'public' => true, 'exclude_from_search' => false ), 'names');
    foreach ($post_types as $post_type) {
        wpmlrestapi_register_api_field($post_type);
    }
}

function wpmlrestapi_register_api_field($post_type)
{
    register_rest_field(
        $post_type,
        'wpml_language_information',
        array(
            'get_callback'    => 'wpmlrestapi_slug_get_current_locale',
            'update_callback' => null,
            'schema'          => null,
        )
    );

    register_rest_field(
        $post_type,
        'wpml_translations',
        array(
            'get_callback'    => 'wpmlrestapi_slug_get_translations',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

/**
* Retrieve available translations
*
* @param array $object Details of current post.
* @param string $field_name Name of field.
* @param WP_REST_Request $request Current request
*
* @return mixed
*/
function wpmlrestapi_slug_get_translations($object, $field_name, $request)
{
    global $sitepress;
    $languages = apply_filters('wpml_active_languages', null);
    $translations = [];

    foreach ($languages as $language) {
        $post_id = apply_filters('wpml_object_id', $object['id'], $object['type'], false, $language['language_code']);

        $skip_missing = (bool) !apply_filters('wpml_setting', true, 'icl_lso_link_empty');

        if ($post_id === null && $skip_missing) {
            continue;
        }

        $post = get_post($post_id);

        $href= apply_filters('wpml_ls_language_url', $language[ 'url' ], $language);

        $translations[] = array(
            'post_id' => $post_id,
            'post_title' => $post_id ? $post->post_title : null,
            'slug' => $post_id ? $post->post_name : null,
            'path' => wp_parse_url($post_id ? get_permalink($post) : $language['url'], PHP_URL_PATH),
            'langugage_code' => $language['language_code'],
            'locale' => $language['default_locale'],
            'native_name' => $language['native_name'],
            'translated_name' => $language['translated_name'],
            'active' => (bool) $language['active'],
            'url' => $post_id ? get_permalink($post_id) : $language['url'],
        );
    }

    return $translations;
}

/**
 * Retrieve the current locale
 *
 * @param array $object Details of current post.
 * @param string $field_name Name of field.
 * @param WP_REST_Request $request Current request
 *
 * @return mixed
 */
function wpmlrestapi_slug_get_current_locale($object, $field_name, $request)
{
    $langInfo = apply_filters('wpml_post_language_details', null);
    return $langInfo;
}

/**
 * Init WPML REST API routes.
 *
 */
function wpml_rest_api_init()
{
    add_filter('rest_api_init', array(new WpmlRestApi, 'registerRoutes'));
}

add_action('init', 'wpml_rest_api_init');
