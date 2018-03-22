<?php

/*
Plugin Name: WPML REST API
Version: 1.0
Description: Adds links to posts in other languages into the results of a WP REST API query for sites running the WPML plugin.
Author: Shawn Hooper
Author URI: https://profiles.wordpress.org/shooper
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'rest_api_init', 'wpmlrestapi_init', 1000);

function wpmlrestapi_init() {

	// Check if WPML is installed
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	if (!is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
		return;
	}

	$available_langs = wpml_get_active_languages_filter('', array('skip_missing' => false, ) );

	if ( ! empty( $available_langs ) && ! isset( $GLOBALS['icl_language_switched'] ) || ! $GLOBALS['icl_language_switched'] ) {
		if ( isset( $_REQUEST['wpml_lang'] ) ) {
			$lang = $_REQUEST['wpml_lang'];
		} else if ( isset( $_REQUEST['lang'] ) ) {
			$lang = $_REQUEST['lang'];
		}

		if ( isset( $lang ) && in_array( $lang, array_keys( $available_langs ) ) ) {
			do_action( 'wpml_switch_language', $lang );
		}
	}

	// Add WPML fields to all post types
	// Thanks to Roy Sivan for this trick.
	// http://www.roysivan.com/wp-api-v2-adding-fields-to-all-post-types/#.VsH0e5MrLcM

	$post_types = get_post_types( array( 'public' => true, 'exclude_from_search' => false ), 'names' );
	foreach( $post_types as $post_type ) {
		wpmlrestapi_register_api_field($post_type);
	}
}

function wpmlrestapi_register_api_field($post_type) {
	register_rest_field( $post_type,
		'wpml_language_information',
		array(
			'get_callback'    => 'wpmlrestapi_slug_get_current_locale',
			'update_callback' => null,
			'schema'          => null,
		)
	);

	register_rest_field( $post_type,
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
function wpmlrestapi_slug_get_translations( $object, $field_name, $request ) {
	global $sitepress;
	$languages = apply_filters('wpml_active_languages', null);
	$translations = [];

	foreach ($languages as $language) {
		$post_id = wpml_object_id_filter($object['id'], 'post', false, $language['language_code']);
		if ($post_id === null || $post_id == $object['id']) continue;
		$thisPost = get_post($post_id);

		$href= apply_filters( 'WPML_filter_link', $language[ 'url' ], $language );
		if (strpos($href, '?') !== false) {
			$href = str_replace('?', '/' . $thisPost->post_name . '/?', $href);
		} else {

			if (substr($href, -1) !== '/') {
				$href .= '/';
			}

			$href .= $thisPost->post_name . '/';
		}

		$translations[] = array('locale' => $language['default_locale'], 'id' => $thisPost->ID, 'post_title' => $thisPost->post_title, 'href' => $href);
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
function wpmlrestapi_slug_get_current_locale( $object, $field_name, $request ) {
	$langInfo = apply_filters( 'wpml_post_language_details', NULL );
	return $langInfo;
}

/**
 * Edit Victor
 */

if ( !class_exists( 'WPML_REST_API' ) ) :

	/**
	 * WPML REST API class
	 */

	class WPML_REST_API {

		public static function get_api_namespace() {
			return 'wp/v2';
		}

		public static function get_plugin_namespace() {
			return 'wpml-rest-api/v2';
		}

		public function register_routes() {

			register_rest_route( self::get_plugin_namespace(), '/languages', array(
					array(
							'methods'  => WP_REST_Server::READABLE,
							'callback' => array( $this, 'get_languages' ),
					)
			) );

		}

		public static function get_languages() {

			$rest_url = trailingslashit( get_rest_url() . self::get_plugin_namespace() . '/languages/' );

			$languages = array();
			$languages['id'] = 1;
			$languages['active_languages'] = apply_filters( 'wpml_active_languages', NULL );
			$languages['wpml_default_language'] = apply_filters( 'wpml_default_language', NULL );
			$languages['wpml_current_language'] = apply_filters( 'wpml_current_language', NULL );
			$languages['meta']['links']['collection'] = $rest_url;
			return $languages;
		}

	}

endif;

if ( ! function_exists ( 'wpml_rest_api_init' ) ) :

	/**
	 * Init WPML REST API routes.
	 *
	 */
	function wpml_rest_api_init() {

		$wpml_rest_api = new WPML_REST_API;
		add_filter( 'rest_api_init', array( $wpml_rest_api, 'register_routes' ) );

	}

	add_action( 'init', 'wpml_rest_api_init' );

endif;
