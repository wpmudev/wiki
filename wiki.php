<?php
/*
 Plugin Name: Wiki
 Plugin URI: http://premium.wpmudev.org/project/wiki
 Description: Add a wiki to your blog
 Author: S H Mohanjith (Incsub)
 WDP ID: 168
 Version: 1.2.3.2
 Author URI: http://premium.wpmudev.org
 Text Domain: incsub_wiki
*/
/**
 * @global	object	$wiki	Convenient access to the chat object
 */
global $wiki;

define( 'WIKI_SLUG_TAGS', 'tags' );
define( 'WIKI_SLUG_CATEGORIES', 'categories' );
define( 'WIKI_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );

if (!defined('WIKI_DEMO_FOR_NON_SUPPORTER'))
    define('WIKI_DEMO_FOR_NON_SUPPORTER', false);

if ( WIKI_DEMO_FOR_NON_SUPPORTER && function_exists('is_supporter') && !is_supporter()) {
    function wiki_non_suppporter_admin_menu() {
	global $psts;
	add_menu_page(__('Wiki', 'incsub_wiki'), __('Wiki', 'incsub_wiki'), 'edit_posts', 'incsub_wiki', array(&$psts, 'feature_notice'), null, 30);
    }
    
    add_action('admin_menu', 'wiki_non_suppporter_admin_menu');
} else {
    include_once 'wiki-include.php';
}

include_once 'lib/wpmudev-dashboard-notification/wpmudev-dash-notification.php';
