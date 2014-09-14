<?php
/*
Plugin Name: Wiki
Plugin URI: http://premium.wpmudev.org/project/wiki
Description: Add a wiki to your blog
Author: WPMU DEV
WDP ID: 168
Version: 1.2.5.2
Author URI: http://premium.wpmudev.org
Text Domain: wiki
*/

/*
Copyright 2007-2014 Incsub (http://incsub.com)
Author - S H Mohanjith
Contributors - Jonathan Cowher

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class Wiki {
	// @var string Current version
	var $version = '1.2.5.2';
	// @var string The db prefix
	var $db_prefix = '';
	// @var string The plugin settings
	var $settings = array();
	// @var string The slug to use for wiki tags
	var $slug_tags = 'tags';
	// @var string The slug to use for wiki categories
	var $slug_categories = 'categories';
	// @var string The directory where this plugin resides
	var $plugin_dir = '';
	// @var string The base url of the plugin
	var $plugin_url = '';
	
	/**
	 * Refers to our single instance of the class
	 *
	 * @since 1.2.5
	 * @access private
	 */
	private static $_instance = null;

	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.2.5
	 * @access public
	 */
	public static function get_instance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new Wiki();
		}
		
		return self::$_instance;
	}

	/**
	 * Constructor function
	 *
	 * @since 1.2.5
	 * @access private
	 */
	private function __construct() {
		$this->init_vars();
		
		if ( WIKI_DEMO_FOR_NON_SUPPORTER && function_exists('is_supporter') && ! is_supporter() ) {
			add_action('admin_menu', array(&$this, 'non_suppporter_admin_menu'));
			return;
		}

		add_action('init', array(&$this, 'init'));
		add_action('init', array(&$this, 'maybe_flush_rewrites'), 999);
		//add_action('current_screen', function(){ echo get_current_screen()->id; });
		
		add_action('wpmu_new_blog', array(&$this, 'new_blog'), 10, 6);
		
		add_action('admin_print_styles-settings_page_wiki', array(&$this, 'admin_styles'));
		add_action('admin_print_scripts-settings_page_wiki', array(&$this, 'admin_scripts'));
		
		add_action('add_meta_boxes_incsub_wiki', array(&$this, 'meta_boxes') );
		add_action('wp_insert_post', array(&$this, 'save_wiki_meta'), 10, 2 );

		add_filter('get_next_post_where', array(&$this, 'get_next_post_where'));
		add_filter('get_previous_post_where', array(&$this, 'get_previous_post_where'));
		add_filter('get_next_post_sort', array(&$this, 'get_next_post_sort'));
		add_filter('get_previous_post_sort', array(&$this, 'get_previous_post_sort'));
		
		add_action('widgets_init', array(&$this, 'widgets_init'));
		add_action('pre_post_update', array(&$this, 'send_notifications'), 50, 1);
		add_filter('the_content', array(&$this, 'theme'), 999);	//set to really low priority. we want this to run after all other filters, otherwise undesired output may result.
		add_action('template_include', array(&$this, 'load_templates') );
		
		add_filter('name_save_pre', array(&$this, 'name_save'));
		
		add_filter('role_has_cap', array(&$this, 'role_has_cap'), 10, 3);
		add_filter('user_has_cap', array(&$this, 'user_has_cap'), 10, 3);
		
		add_filter('get_edit_post_link', array(&$this, 'get_edit_post_link'));
		add_filter('comments_open', array(&$this, 'comments_open'), 10, 1);
		
		add_filter('user_can_richedit', array(&$this, 'user_can_richedit'));
		add_filter('wp_title', array(&$this, 'wp_title'), 10, 3);
		add_filter('the_title', array(&$this, 'the_title'), 10, 2);
		
		add_filter('404_template', array(&$this, 'not_found_template'));
		
		add_action('pre_get_posts', array( &$this, 'pre_get_posts'));
		
		add_filter('request', array(&$this, 'request'));
		
		add_filter('body_class', array(&$this, 'body_class'), 10);
		
		add_action('wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts'), 10);
	}
	
	/**
	 * Runs when adding a new blog in multisite
	 *
	 * @param int $blog_id
	 * @param int $user_id,
	 * @param string $domain
	 * @param string $path
	 * @param int $site_id
	 * @param array $meta
	 */
	function new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		if ( is_plugin_active_for_network('wiki/wiki.php') )
			$this->setup_blog($blog_id);
	}

	/**
	 * Get the table name with prefixes
	 *
	 * @param string $table	Table name
	 * @uses $wpdb
	 * @return string Table name complete with prefixes
	 */
	function tablename($table) {
		global $wpdb;
		return $wpdb->base_prefix.'wiki_'.$table;
	}
	

	function non_suppporter_admin_menu() {
		global $psts;
		add_menu_page(__('Wiki', 'wiki'), __('Wiki', 'wiki'), 'edit_posts', 'incsub_wiki', array(&$psts, 'feature_notice'), null, 30);
	}
	
	/**
	 * Initialize plugin variables
	 * @since 1.2.4
	 */
	function init_vars() {
		global $wpdb;
		
		$this->db_prefix = ( !empty($wpdb->base_prefix) ) ? $wpdb->base_prefix : $wpdb->prefix;
		$this->plugin_dir = plugin_dir_path(__FILE__);
		$this->plugin_url = plugin_dir_url(__FILE__);
		
		if ( !defined('WIKI_DEMO_FOR_NON_SUPPORTER') )
			define('WIKI_DEMO_FOR_NON_SUPPORTER', false);
	}
	
	function request( $query_vars ) {
		if (!is_admin() && isset($query_vars['post_type']) && 'incsub_wiki' == $query_vars['post_type'] && (isset($query_vars['orderby']) && $query_vars['orderby'] == 'menu_order title') && $query_vars['posts_per_page'] == '-1') {
			$query_vars['orderby'] = 'menu_order';
			unset($query_vars['posts_per_page']);
			unset($query_vars['posts_per_archive_page']);
			return $query_vars;
		}
		
		return $query_vars;
	}
	
	function the_title( $title, $id = false ) {
		global $wp_query, $post;
		
		if (!$id && get_query_var('post_type') == 'incsub_wiki' && $wp_query->is_404) {
			$post_type_object = get_post_type_object( get_query_var('post_type') );
			
			if (current_user_can($post_type_object->cap->publish_posts)) {
				return ucwords(get_query_var('name'));
			}
		}
		
		return $title;
	}
	
	function body_class($classes) {
		if (get_query_var('post_type') == 'incsub_wiki') {
			if (!in_array('incsub_wiki', $classes)) {
				$classes[] = 'incsub_wiki';
			}
			
			if (is_singular() && !in_array('single-incsub_wiki', $classes)) {
				$classes[] = 'single-incsub_wiki';
			}
		}
		
		return $classes;
	}
	
	function not_found_template( $path ) {
		global $wp_query;
		
		if ( 'incsub_wiki' != get_query_var('post_type') )
			return $path;
		
		$post_type_object = get_post_type_object( get_query_var('post_type') );
		
		if (current_user_can($post_type_object->cap->publish_posts)) {
			$type = reset( explode( '_', current_filter() ) );
			$file = basename( $path );
			
			if ( empty( $path ) || "$type.php" == $file ) {
				// A more specific template was not found, so load the default one
				$path = $this->plugin_dir . "default-templates/$type-incsub_wiki.php";
			}
			if ( file_exists( get_stylesheet_directory() . "/$type-incsub_wiki.php" ) ) {
				$path = get_stylesheet_directory() . "/$type-incsub_wiki.php";
			}
		}
		return $path;
	}
		
	function load_templates( $template ) {
		global $wp_query, $post;
		
		if ( is_single() && 'incsub_wiki' == get_post_type() ) {
			//check for custom theme templates
			$wiki_name = $post->post_name;
			$wiki_id = (int) $post->ID;
			$templates = array('incsub_wiki.php');
			
			if ( $wiki_name )
				$templates[] = "incsub_wiki-$wiki_name.php";
			
			if ( $wiki_id )
				$templates[] = "incsub_wiki-$wiki_id.php";
			
			if ( $new_template = locate_template($templates) ) {
				remove_filter('the_content', array(&$this, 'theme'), 1);
				return $new_template;
			}
		}
		
		return $template;
	}
		
	function pre_get_posts( $query ) {
		if( $query->is_main_query() && !is_admin() && !empty($query->query_vars['incsub_wiki']) && preg_match('/\//', $query->query_vars['incsub_wiki']) == 0 ) {
			$query->query_vars['post_parent'] = 0;
		}
	}
		
	function user_can_richedit($wp_rich_edit) {
		global $wp_query;
		
		if (get_query_var('post_type') == 'incsub_wiki') {
			return true;
		}
		return $wp_rich_edit;
	}
		
	/**
	 * Checks to see if rewrites should be flushed and flushes them
	 *
	 * @since 1.2.4
	 */
	function maybe_flush_rewrites() {
		if ( !get_option('wiki_flush_rewrites') )
			return;
			
		flush_rewrite_rules();
		delete_option('wiki_flush_rewrites');
	}
	
	function comments_open($open) {
		global $wp_query, $incsub_tab_check;
		
		$action = isset($_REQUEST['action'])?$_REQUEST['action']:'view';
		if (get_query_var('post_type') == 'incsub_wiki' && ($action != 'discussion')) {
			if ($incsub_tab_check == 0 && !isset($_POST['submit']) && !isset($_POST['Submit'])) {
				return false;
			}
		}
		return $open;
	}
	
	function wp_title($title, $sep, $seplocation) {
		global $post, $wp_query;
		
		$tmp_title = "";
		$bc = 0;
		if (!$post && get_query_var('post_type') == 'incsub_wiki' && $wp_query->is_404) {
			$post_type_object = get_post_type_object( get_query_var('post_type') );
			if (current_user_can($post_type_object->cap->publish_posts)) {
				$tmp_title = ucwords(get_query_var('name'));
				if ($seplocation == 'left') {
					$title = " {$sep} {$tmp_title}";
				}
				if ($seplocation == 'right') {
					$title = " {$tmp_title} {$sep} ";
				}
			}
		} else {
			if (isset($post->ancestors) && is_array($post->ancestors)) {
				foreach($post->ancestors as $parent_pid) {
					if ($bc >= $this->settings['breadcrumbs_in_title']) {
						break;
					}
					$parent_post = get_post($parent_pid);
					
					if ($seplocation == 'left') {
						$tmp_title .= " {$sep} ";
					}
					$tmp_title .= $parent_post->post_title;
					if ($seplocation == 'right') {
						$tmp_title .= " {$sep} ";
					}
					$bc++;
				}
			}
			
			$tmp_title = trim($tmp_title);
			if (!empty($tmp_title)) {
				if ($seplocation == 'left') {
					$title = "{$title} {$tmp_title} ";
				}
				if ($seplocation == 'right') {
					$title .= " {$tmp_title} ";
				}
			}
		}
		
		return $title;
	}
		
	/**
	 * Rename $_POST data from form names to DB post columns.
	 *
	 * Manipulates $_POST directly.
	 *
	 * @package WordPress
	 * @since 2.6.0
	 *
	 * @param bool $update Are we updating a pre-existing post?
	 * @param array $post_data Array of post data. Defaults to the contents of $_POST.
	 * @return object|bool WP_Error on failure, true on success.
	 */
	function _translate_postdata( $update = false, $post_data = null ) {
		if ( empty($post_data) )
			$post_data = &$_POST;
	
		if ( $update )
			$post_data['ID'] = (int) $post_data['post_ID'];
			
		$post_data['post_content'] = isset($post_data['content']) ? $post_data['content'] : '';
		$post_data['post_excerpt'] = isset($post_data['excerpt']) ? $post_data['excerpt'] : '';
		$post_data['post_parent'] = isset($post_data['parent_id'])? $post_data['parent_id'] : '';
		if ( isset($post_data['trackback_url']) )
			$post_data['to_ping'] = $post_data['trackback_url'];
	
		if ( !isset($post_data['user_ID']) )
			$post_data['user_ID'] = $GLOBALS['user_ID'];
	
		if (!empty ( $post_data['post_author_override'] ) ) {
			$post_data['post_author'] = (int) $post_data['post_author_override'];
		} else {
			if (!empty ( $post_data['post_author'] ) ) {
				$post_data['post_author'] = (int) $post_data['post_author'];
			} else {
				$post_data['post_author'] = (int) $post_data['user_ID'];
			}
		}
	
		$ptype = get_post_type_object( $post_data['post_type'] );
		if ( isset($post_data['user_ID']) && ($post_data['post_author'] != $post_data['user_ID']) ) {
			if ( !current_user_can( $ptype->cap->edit_others_posts ) ) {
				if ( 'page' == $post_data['post_type'] ) {
					return new WP_Error( 'edit_others_pages', $update ?
						__( 'You are not allowed to edit pages as this user.' ) :
						__( 'You are not allowed to create pages as this user.' )
					);
				} else {
					return new WP_Error( 'edit_others_posts', $update ?
						__( 'You are not allowed to edit posts as this user.' ) :
						__( 'You are not allowed to post as this user.' )
					);
				}
			}
		}
	
		// What to do based on which button they pressed
		if ( isset($post_data['saveasdraft']) && '' != $post_data['saveasdraft'] )
			$post_data['post_status'] = 'draft';
		if ( isset($post_data['saveasprivate']) && '' != $post_data['saveasprivate'] )
			$post_data['post_status'] = 'private';
		if ( isset($post_data['publish']) && ( '' != $post_data['publish'] ) && ( !isset($post_data['post_status']) || $post_data['post_status'] != 'private' ) )
			$post_data['post_status'] = 'publish';
		if ( isset($post_data['advanced']) && '' != $post_data['advanced'] )
			$post_data['post_status'] = 'draft';
		if ( isset($post_data['pending']) && '' != $post_data['pending'] )
			$post_data['post_status'] = 'pending';
	
		if ( isset( $post_data['ID'] ) )
			$post_id = $post_data['ID'];
		else
			$post_id = false;
		$previous_status = $post_id ? get_post_field( 'post_status', $post_id ) : false;
	
		// Posts 'submitted for approval' present are submitted to $_POST the same as if they were being published.
		// Change status from 'publish' to 'pending' if user lacks permissions to publish or to resave published posts.
		if ( isset($post_data['post_status']) && ('publish' == $post_data['post_status'] && !current_user_can( $ptype->cap->publish_posts )) )
			if ( $previous_status != 'publish' || !current_user_can( 'edit_post', $post_id ) )
				$post_data['post_status'] = 'pending';
	
		if ( ! isset($post_data['post_status']) )
			$post_data['post_status'] = $previous_status;
	
		if (!isset( $post_data['comment_status'] ))
			$post_data['comment_status'] = 'closed';
	
		if (!isset( $post_data['ping_status'] ))
			$post_data['ping_status'] = 'closed';
	
		foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {
			if ( !empty( $post_data['hidden_' . $timeunit] ) && $post_data['hidden_' . $timeunit] != $post_data[$timeunit] ) {
				$post_data['edit_date'] = '1';
				break;
			}
		}
	
		if ( !empty( $post_data['edit_date'] ) ) {
			$aa = $post_data['aa'];
			$mm = $post_data['mm'];
			$jj = $post_data['jj'];
			$hh = $post_data['hh'];
			$mn = $post_data['mn'];
			$ss = $post_data['ss'];
			$aa = ($aa <= 0 ) ? date('Y') : $aa;
			$mm = ($mm <= 0 ) ? date('n') : $mm;
			$jj = ($jj > 31 ) ? 31 : $jj;
			$jj = ($jj <= 0 ) ? date('j') : $jj;
			$hh = ($hh > 23 ) ? $hh -24 : $hh;
			$mn = ($mn > 59 ) ? $mn -60 : $mn;
			$ss = ($ss > 59 ) ? $ss -60 : $ss;
			$post_data['post_date'] = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
		}
	
		return $post_data;
	}
		
	/**
	 * Update an existing post with values provided in $_POST.
	 *
	 * @since 1.5.0
	 *
	 * @param array $post_data Optional.
	 * @return int Post ID.
	 */
	function edit_post( $post_data = null ) {
		if ( empty($post_data) )
			$post_data = &$_POST;
		
		$post_ID = (int) $post_data['post_ID'];
		
		$ptype = get_post_type_object($post_data['post_type']);
		
		if ( !current_user_can( $ptype->cap->edit_post, $post_ID ) ) {
			if ( 'page' == $post_data['post_type'] )
				wp_die( __('You are not allowed to edit this page.' ));
			else
				wp_die( __('You are not allowed to edit this post.' ));
		}
		
		// Autosave shouldn't save too soon after a real save
		if ( 'autosave' == $post_data['action'] ) {
			$post =& get_post( $post_ID );
			$now = time();
			$then = strtotime($post->post_date_gmt . ' +0000');
			$delta = AUTOSAVE_INTERVAL / 2;
			if ( ($now - $then) < $delta )
				return $post_ID;
		}
		
		$post_data = $this->_translate_postdata( true, $post_data );
		$post_data['post_status'] = 'publish';
		if ( is_wp_error($post_data) )
			wp_die( $post_data->get_error_message() );
		if ( 'autosave' != $post_data['action']	 && 'auto-draft' == $post_data['post_status'] )
			$post_data['post_status'] = 'draft';
		
		if ( isset($post_data['visibility']) ) {
			switch ( $post_data['visibility'] ) {
			case 'public' :
				$post_data['post_password'] = '';
				break;
			case 'password' :
				unset( $post_data['sticky'] );
				break;
			case 'private' :
				$post_data['post_status'] = 'private';
				$post_data['post_password'] = '';
				unset( $post_data['sticky'] );
				break;
			}
		}
		
		// Post Formats
		if ( current_theme_supports( 'post-formats' ) && isset( $post_data['post_format'] ) ) {
			$formats = get_theme_support( 'post-formats' );
			if ( is_array( $formats ) ) {
				$formats = $formats[0];
				if ( in_array( $post_data['post_format'], $formats ) ) {
					set_post_format( $post_ID, $post_data['post_format'] );
				} elseif ( '0' == $post_data['post_format'] ) {
					set_post_format( $post_ID, false );
				}
			}
		}
		// Meta Stuff
		if ( isset($post_data['meta']) && $post_data['meta'] ) {
			foreach ( $post_data['meta'] as $key => $value ) {
				if ( !$meta = get_post_meta_by_id( $key ) )
					continue;
				if ( $meta->post_id != $post_ID )
					continue;
				update_meta( $key, $value['key'], $value['value'] );
			}
		}
		
		if ( isset($post_data['deletemeta']) && $post_data['deletemeta'] ) {
			foreach ( $post_data['deletemeta'] as $key => $value ) {
				if ( !$meta = get_post_meta_by_id( $key ) )
					continue;
				if ( $meta->post_id != $post_ID )
					continue;
				delete_meta( $key );
			}
		}
		
		// add_meta( $post_ID );
		
		update_post_meta( $post_ID, '_edit_last', $GLOBALS['current_user']->ID );
		
		wp_update_post( $post_data );
		
		// Reunite any orphaned attachments with their parent
		if ( !$draft_ids = get_user_option( 'autosave_draft_ids' ) )
			$draft_ids = array();
		if ( $draft_temp_id = (int) array_search( $post_ID, $draft_ids ) )
			_relocate_children( $draft_temp_id, $post_ID );
		
		$this->set_post_lock( $post_ID, $GLOBALS['current_user']->ID );
		
		if ( current_user_can( $ptype->cap->edit_others_posts ) ) {
			if ( ! empty( $post_data['sticky'] ) )
				stick_post( $post_ID );
			else
				unstick_post( $post_ID );
		}
		
		return $post_ID;
	}
		
	function theme( $content ) {
		global $post;
		
		if ( !is_single() || 'incsub_wiki' != get_post_type() )
			return $content;
		
		if ( post_password_required() )
			return $content;
			
		if ( function_exists('is_main_query') && !is_main_query() )
			return $content;
		
		$revision_id = isset($_REQUEST['revision'])?absint($_REQUEST['revision']):0;
		$left				= isset($_REQUEST['left'])?absint($_REQUEST['left']):0;
		$right				= isset($_REQUEST['right'])?absint($_REQUEST['right']):0;
		$action			= isset($_REQUEST['action'])?$_REQUEST['action']:'view';
		
		$new_content = '';
		
		if ($action != 'edit') {
			$new_content .= '<div class="incsub_wiki incsub_wiki_single">';
			
			if ( isset($_GET['restored']) ) {
				$new_content .= '<div class="incsub_wiki_message">' . __('Revision restored successfully', 'wiki') . ' <a class="dismiss" href="#">x</a></div>';
			}
			
			$new_content .= '<div class="incsub_wiki_tabs incsub_wiki_tabs_top">' . $this->tabs() . '<div class="incsub_wiki_clear"></div></div>';
			$new_content .= $this->decider($content, $action, $revision_id, $left, $right);
		} else {
			$new_content .= $this->get_edit_form(false);
		}
		
		if ( !comments_open() ) {
			$new_content .= '<style type="text/css">'.
			'#comments { display: none; }'.
				'.comments { display: none; }'.
			'</style>';
		} else {
			$new_content .= '<style type="text/css">'.
			'.hentry { margin-bottom: 5px; }'.
			'</style>';
		}
		
		return $new_content;
	}

	function decider($content, $action, $revision_id = null, $left = null, $right = null, $stray_close = true) {
		global $post;
		
		$new_content = '';
		
		switch ($action) {
			case 'discussion':
				break;
			case 'edit':
				set_include_path(get_include_path().PATH_SEPARATOR.ABSPATH.'wp-admin');
				
				$post_type_object = get_post_type_object($post->post_type);
				
				$p = $post;
				
				if ( empty($post->ID) )
					wp_die( __('You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?') );
				
				if ( !current_user_can($post_type_object->cap->edit_post, $post->ID) )
					wp_die( __('You are not allowed to edit this item.') );
					
				if ( 'trash' == $post->post_status )
					wp_die( __('You can&#8217;t edit this item because it is in the Trash. Please restore it and try again.') );
					
				if ( null == $post_type_object )
					wp_die( __('Unknown post type.') );
					
				$post_type = $post->post_type;
				
				if ( $last = $this->check_post_lock( $post->ID ) ) {
					add_action('admin_notices', '_admin_notice_post_locked' );
				} else {
					$this->set_post_lock( $post->ID );
					wp_enqueue_script('autosave');
				}
				
				$title = $post_type_object->labels->edit_item;
				$post = $this->post_to_edit($post->ID);
				
				$new_content = $this->get_edit_form(false);
				
				break;
			case 'restore':
				if ( ! $revision = wp_get_post_revision( $revision_id ) )
					break;
				if ( ! current_user_can( 'edit_post', $revision->post_parent ) )
					break;
				if ( ! $post = get_post( $revision->post_parent ) )
					break;

				// Revisions disabled and we're not looking at an autosave
				if ( ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) && !wp_is_post_autosave( $revision ) ) {
					$redirect = get_permalink().'?action=edit';
					break;
				}
				
				check_admin_referer( "restore-post_$post->ID|$revision->ID" );

				wp_restore_post_revision( $revision->ID );
				$redirect = add_query_arg('restored', 1, get_permalink());
				break;
			case 'diff':
				if ( !$left_revision	= get_post( $left ) ) {
					break;
				}
				if ( !$right_revision = get_post( $right ) ) {
					break;
				}

				// If we're comparing a revision to itself, redirect to the 'view' page for that revision or the edit page for that post
				if ( $left_revision->ID == $right_revision->ID ) {
					$redirect = get_permalink().'?action=edit';
					break;
				}

				// Don't allow reverse diffs?
				if ( strtotime($right_revision->post_modified_gmt) < strtotime($left_revision->post_modified_gmt) ) {
					$redirect = add_query_arg( array( 'left' => $right, 'right' => $left ) );
					break;
				}

				if ( $left_revision->ID == $right_revision->post_parent ) // right is a revision of left
					$post =& $left_revision;
				elseif ( $left_revision->post_parent == $right_revision->ID ) // left is a revision of right
					$post =& $right_revision;
				elseif ( $left_revision->post_parent == $right_revision->post_parent ) // both are revisions of common parent
					$post = get_post( $left_revision->post_parent );
				else
					break; // Don't diff two unrelated revisions
				
				if ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) { // Revisions disabled
					if (
					// we're not looking at an autosave
						( !wp_is_post_autosave( $left_revision ) && !wp_is_post_autosave( $right_revision ) )
					||
					// we're not comparing an autosave to the current post
					( $post->ID !== $left_revision->ID && $post->ID !== $right_revision->ID )
					) {
					$redirect = get_permalink().'?action=edit';
					break;
					}
				}

				if (
					// They're the same
					$left_revision->ID == $right_revision->ID
					||
					// Neither is a revision
					( !wp_get_post_revision( $left_revision->ID ) && !wp_get_post_revision( $right_revision->ID ) )
					) {
					break;
				}

				$post_title = '<a href="' . get_permalink().'?action=edit' . '">' . get_the_title() . '</a>';
				$h2 = sprintf( __( 'Compare Revisions of &#8220;%1$s&#8221;', 'wiki' ), $post_title );
				$title = __( 'Revisions' );

				$left	 = $left_revision->ID;
				$right = $right_revision->ID;
			case 'history':
				$args = array( 'format' => 'form-table', 'parent' => false, 'right' => $right, 'left' => $left );
				if ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) {
					$args['type'] = 'autosave';
				}

				if (!isset($h2)) {
					$post_title = '<a href="' . get_permalink().'?action=edit' . '">' . get_the_title() . '</a>';
					$revisions = wp_get_post_revisions( $post->ID );
					$revision = array_shift($revisions);
					$revision_title = wp_post_revision_title( $revision, false );
					$h2 = sprintf( __( 'Revision for &#8220;%1$s&#8221; created on %2$s', 'wiki' ), $post_title, $revision_title );
				}

				$new_content .= '<h3 class="long-header">'.$h2.'</h3>';
				$new_content .= '<table class="form-table ie-fixed">';
				$new_content .= '<col class="th" />';
				
				if ( 'diff' == $action ) :
					$new_content .= '<tr id="revision">';
					$new_content .= '<th scope="row"></th>';
					$new_content .= '<th scope="col" class="th-full">';
					$new_content .= '<span class="alignleft">'.sprintf( __('Older: %s', 'wiki'), wp_post_revision_title( $left_revision, false ) ).'</span>';
					$new_content .= '<span class="alignright">'.sprintf( __('Newer: %s', 'wiki'), wp_post_revision_title( $right_revision, false ) ).'</span>';
					$new_content .= '</th>';
					$new_content .= '</tr>';
				endif;

				// use get_post_to_edit filters?
				$identical = true;
				foreach ( _wp_post_revision_fields() as $field => $field_title ) :
					if ( 'diff' == $action ) {
						$left_content = apply_filters( "_wp_post_revision_field_$field", $left_revision->$field, $field );
						$right_content = apply_filters( "_wp_post_revision_field_$field", $right_revision->$field, $field );
						if ( !$rcontent = wp_text_diff( $left_content, $right_content ) )
							continue; // There is no difference between left and right
						$identical = false;
				} else {
						add_filter( "_wp_post_revision_field_$field", 'htmlspecialchars' );
						$rcontent = apply_filters( "_wp_post_revision_field_$field", $revision->$field, $field );
				}
					$new_content .= '<tr id="revision-field-' . $field . '">';
					$new_content .= '<th scope="row">'.esc_html( $field_title ).'</th>';
					$new_content .= '<td><div class="pre">'.$rcontent.'</div></td>';
					$new_content .= '</tr>';
				endforeach;
				
				if ( 'diff' == $action && $identical ) :
					$new_content .= '<tr><td colspan="2"><div class="updated"><p>'.__( 'These revisions are identical.', 'wiki' ). '</p></div></td></tr>';
				endif;
				
				$new_content .= '</table>';
				
				$new_content .= '<br class="clear" />';
				$new_content .= '<div class="incsub_wiki_revisions">' . $this->list_post_revisions( $post, $args ) . '</div>';
				$redirect = false;
				break;
			default:
				$top = "";
				
				$crumbs = array('<a href="'.home_url($this->settings['slug']).'" class="incsub_wiki_crumbs">'.$this->settings['wiki_name'].'</a>');
				foreach($post->ancestors as $parent_pid) {
					$parent_post = get_post($parent_pid);
					
					$crumbs[] = '<a href="'.get_permalink($parent_pid).'" class="incsub_wiki_crumbs">'.$parent_post->post_title.'</a>';
				}
				
				$crumbs[] = '<span class="incsub_wiki_crumbs">'.$post->post_title.'</span>';
				
				sort($crumbs);
				
				$top .= join(get_option("incsub_meta_seperator", " > "), $crumbs);
				
				$taxonomy = "";
				
				if ( class_exists('Wiki_Premium') ) {
					$category_list = get_the_term_list( 0, 'incsub_wiki_category', __( 'Category:', 'wiki' ) . ' <span class="incsub_wiki-category">', '', '</span> ' );
					$tags_list = get_the_term_list( 0, 'incsub_wiki_tag', __( 'Tags:', 'wiki' ) . ' <span class="incsub_wiki-tags">', ' ', '</span> ' );
					
					$taxonomy .= apply_filters('the_terms', $category_list, 'incsub_wiki_category', __( 'Category:', 'wiki' ) . ' <span class="incsub_wiki-category">', '', '</span> ' );
					$taxonomy .= apply_filters('the_terms', $tags_list, 'incsub_wiki_tag', __( 'Tags:', 'wiki' ) . ' <span class="incsub_wiki-tags">', ' ', '</span> ' );
				}
				
				$children = get_posts(array(
					'post_parent' => $post->ID,
					'post_type' => 'incsub_wiki',
					'orderby' => $this->settings['sub_wiki_order_by'],
					'order' => $this->settings['sub_wiki_order'],
					'numberposts' => 100000
				));
				
				$crumbs = array();
				foreach($children as $child) {
					$crumbs[] = '<a href="'.get_permalink($child->ID).'" class="incsub_wiki_crumbs">'.$child->post_title.'</a>';
				}
				
				$bottom = "<h3>" . $this->settings['sub_wiki_name'] . "</h3> <ul><li>";
				
				$bottom .= join("</li><li>", $crumbs);
				
				if (count($crumbs) == 0) {
					$bottom = $taxonomy;
				} else {
					$bottom .= "</li></ul>";
					$bottom = "{$taxonomy} {$bottom}";
				}
				
				$revisions = wp_get_post_revisions($post->ID);
				
				if (current_user_can('edit_wiki', $post->ID)) {
					$bottom .= '<div class="incsub_wiki-meta">';
					if (is_array($revisions) && count($revisions) > 0) {
					$revision = array_shift($revisions);
					}
					$bottom .= '</div>';
				}
				
				$notification_meta = get_post_meta($post->ID, 'incsub_wiki_email_notification', true);
				
				if ( $notification_meta == 'enabled' && !$this->is_subscribed() ) {
					if (is_user_logged_in()) {
						$bottom .= '<div class="incsub_wiki-subscribe"><a href="'.wp_nonce_url(add_query_arg(array('post_id' => $post->ID, 'subscribe' => 1)), "wiki-subscribe-wiki_$post->ID" ).'">'.__('Notify me of changes', 'wiki').'</a></div>';
					} else {
						if (!empty($_COOKIE['incsub_wiki_email'])) {
							$user_email = $_COOKIE['incsub_wiki_email'];
						} else {
							$user_email = "";
						}
				
						$bottom .= '<div class="incsub_wiki-subscribe">'.
						'<form action="" method="post">'.
						'<label>'.__('E-mail', 'wiki').': <input type="text" name="email" id="email" value="'.$user_email.'" /></label> &nbsp;'.
						'<input type="hidden" name="post_id" id="post_id" value="'.$post->ID.'" />'.
						'<input type="submit" name="subscribe" id="subscribe" value="'.__('Notify me of changes', 'wiki').'" />'.
						'<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-subscribe-wiki_$post->ID").'" />'.
						'</form>'.
						'</div>';
					}
				}
				
				$new_content	= '<div class="incsub_wiki_top">' . $top . '</div>'. $new_content;
				$new_content .= '<div class="incsub_wiki_content">' . $content . '</div>';
				$new_content .= '<div class="incsub_wiki_bottom">' . $bottom . '</div>';
				$redirect = false;
		}
		
		if ($stray_close) {
			$new_content .= '</div>';
		}
		
		// Empty post_type means either malformed object found, or no valid parent was found.
		if ( isset($redirect) && !$redirect && empty($post->post_type) ) {
			$redirect = 'edit.php';
		}
		
		if ( !empty($redirect) ) {
			echo '<script type="text/javascript">'.
			'window.location = "'.$redirect.'";'.
			'</script>';
			exit;
		}
		
		return $new_content;
	}

	/**
	 * Default post information to use when populating the "Write Post" form.
	 *
	 * @since 2.0.0
	 *
	 * @param string $post_type A post type string, defaults to 'post'.
	 * @return object stdClass object containing all the default post data as attributes
	 */
	function get_default_post_to_edit( $post_type = 'post', $create_in_db = false, $parent_id = 0 ) {
		global $wpdb;
	
		$post_title = '';
		if ( !empty( $_REQUEST['post_title'] ) )
			$post_title = esc_html( stripslashes( $_REQUEST['post_title'] ));
	
		$post_content = '';
		if ( !empty( $_REQUEST['content'] ) )
			$post_content = esc_html( stripslashes( $_REQUEST['content'] ));
	
		$post_excerpt = '';
		if ( !empty( $_REQUEST['excerpt'] ) )
			$post_excerpt = esc_html( stripslashes( $_REQUEST['excerpt'] ));
	
		if ( $create_in_db ) {
			// Cleanup old auto-drafts more than 7 days old
			$old_posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft' AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );
			foreach ( (array) $old_posts as $delete )
			wp_delete_post( $delete, true ); // Force delete
			$post_id = wp_insert_post( array( 'post_parent' => $parent_id, 'post_title' => __( 'Auto Draft' ), 'post_type' => $post_type, 'post_status' => 'auto-draft' ) );
			$post = get_post( $post_id );
			if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) && get_option( 'default_post_format' ) )
			set_post_format( $post, get_option( 'default_post_format' ) );
			// Copy wiki privileges
			$privileges = get_post_meta($post->post_parent, 'incsub_wiki_privileges');
			update_post_meta($post->ID, 'incsub_wiki_privileges', $privileges[0]);
		} else {
			$post->ID = 0;
			$post->post_author = '';
			$post->post_date = '';
			$post->post_date_gmt = '';
			$post->post_password = '';
			$post->post_type = $post_type;
			$post->post_status = 'draft';
			$post->to_ping = '';
			$post->pinged = '';
			$post->comment_status = get_option( 'default_comment_status' );
			$post->ping_status = get_option( 'default_ping_status' );
			$post->post_pingback = get_option( 'default_pingback_flag' );
			$post->post_category = get_option( 'default_category' );
			$post->page_template = 'default';
			$post->post_parent = 0;
			$post->menu_order = 0;
		}
	
		$post->post_content = apply_filters( 'default_content', $post_content, $post );
		$post->post_title		= apply_filters( 'default_title',		$post_title, $post	 );
		$post->post_excerpt = apply_filters( 'default_excerpt', $post_excerpt, $post );
		$post->post_name = '';
	
		return $post;
	}

	function enqueue_comment_hotkeys_js() {
		if ( 'true' == get_user_option( 'comment_shortcuts' ) )
				wp_enqueue_script( 'jquery-table-hotkeys' );
	}
		
		/**
		 * Get an existing post and format it for editing.
		 *
		 * @since 2.0.0
		 *
		 * @param unknown_type $id
		 * @return unknown
		 */
	function post_to_edit( $id ) {
		$post = get_post( $id, OBJECT, 'edit' );
		
		if ( $post->post_type == 'page' )
			$post->page_template = get_post_meta( $id, '_wp_page_template', true );
		return $post;
	}
		
		/**
		 * Check to see if the post is currently being edited by another user.
		 *
		 * @since 2.5.0
		 *
		 * @param int $post_id ID of the post to check for editing
		 * @return bool|int False: not locked or locked by current user. Int: user ID of user with lock.
		 */
	function check_post_lock( $post_id ) {
		if ( !$post = get_post( $post_id ) )
			return false;
		
		if ( !$lock = get_post_meta( $post->ID, '_edit_lock', true ) )
			return false;
		
		$lock = explode( ':', $lock );
		$time = $lock[0];
		$user = isset( $lock[1] ) ? $lock[1] : get_post_meta( $post->ID, '_edit_last', true );
		
		$time_window = apply_filters( 'wp_check_post_lock_window', AUTOSAVE_INTERVAL * 2 );
		
		if ( $time && $time > time() - $time_window && $user != get_current_user_id() )
			return $user;
		return false;
		}
		
		/**
		 * Mark the post as currently being edited by the current user
		 *
		 * @since 2.5.0
		 *
		 * @param int $post_id ID of the post to being edited
		 * @return bool Returns false if the post doesn't exist of there is no current user
		 */
		function set_post_lock( $post_id ) {
		if ( !$post = get_post( $post_id ) )
			return false;
		if ( 0 == ($user_id = get_current_user_id()) )
			return false;
		
		$now = time();
		$lock = "$now:$user_id";
		
		update_post_meta( $post->ID, '_edit_lock', $lock );
	}

	/**
	 * Alters the get_next_post_where clause so that the next post link returns the correct wiki
	 *
	 * @since 1.2.5.2
	 * @access public
	 * @filter get_next_post_where
	 */
	function get_next_post_where( $sql ) {
		global $wpdb, $post;
		
		if ( ! is_main_query() || ! is_singular('incsub_wiki') ) {
    	return $sql;
    }
		
		switch ( $this->get_setting('sub_wiki_order_by') ) {
			case 'menu_order' :
				$sql  = $wpdb->prepare("WHERE p.menu_order " . (( $this->get_setting('sub_wiki_order') == 'ASC') ? '>' : '<') . " %d AND p.post_type = 'incsub_wiki' AND p.post_status = 'publish'", $post->menu_order);
				$sql .= $wpdb->prepare(" AND p.post_parent = %d", $post->post_parent);
				break;
				
			case 'title' :
				$sql  = $wpdb->prepare("WHERE p.post_title " . (( $this->get_setting('sub_wiki_order') == 'ASC') ? '>' : '<') . " %s AND p.post_type = 'incsub_wiki' AND p.post_status = 'publish'", $post->post_title);
				$sql .= $wpdb->prepare(" AND p.post_parent = %d", $post->post_parent);
				break;				
		}
		
		return $sql;
	}

	/**
	 * Alters the get_previous_post_where clause so that the previous post link returns the correct wiki
	 *
	 * @since 1.2.5.2
	 * @access public
	 * @filter get_previous_post_where
	 */	
	function get_previous_post_where( $sql ) {
		global $wpdb, $post;
		
		if ( ! is_main_query() || ! is_singular('incsub_wiki') ) {
    	return $sql;
    }
    
		switch ( $this->get_setting('sub_wiki_order_by') ) {
			case 'menu_order' :
				$sql  = $wpdb->prepare("WHERE p.menu_order " . (( $this->get_setting('sub_wiki_order') == 'ASC') ? '<' : '>') . " %d AND p.post_type = 'incsub_wiki' AND p.post_status = 'publish'", $post->menu_order);
				$sql .= $wpdb->prepare(" AND p.post_parent = %d", $post->post_parent);
				break;
				
			case 'title' :
				$sql  = $wpdb->prepare("WHERE p.post_title " . (( $this->get_setting('sub_wiki_order') == 'ASC') ? '<' : '>') . " %s AND p.post_type = 'incsub_wiki' AND p.post_status = 'publish'", $post->post_title);
				$sql .= $wpdb->prepare(" AND p.post_parent = %d", $post->post_parent);
				break;				
		}
		
		return $sql;
	}

	/**
	 * Alters the get_next_post_sort clause so that the next post link returns the correct wiki
	 *
	 * @since 1.2.5.2
	 * @access public
	 * @filter get_next_post_sort
	 */		
	function get_next_post_sort( $sql ) {
		global $wpdb, $post;

		if ( ! is_main_query() || ! is_singular('incsub_wiki') ) {
    	return $sql;
    }
    
		switch ( $this->get_setting('sub_wiki_order_by') ) {
			case 'menu_order' :
				$sql = 'ORDER BY p.menu_order ' . (( $this->get_setting('sub_wiki_order') == 'ASC') ? 'ASC' : 'DESC') . ' LIMIT 1';
				break;
				
			case 'title' :
				$sql = 'ORDER BY p.post_title ' . (( $this->get_setting('sub_wiki_order') == 'ASC') ? 'ASC' : 'DESC') . ' LIMIT 1';
				break;
		}
		
		return $sql;
	}

	/**
	 * Alters the get_previous_post_sort clause so that the previous post link returns the correct wiki
	 *
	 * @since 1.2.5.2
	 * @access public
	 * @filter get_previous_post_sort
	 */			
	function get_previous_post_sort( $sql ) {
		global $wpdb, $post;

		if ( ! is_main_query() || ! is_singular('incsub_wiki') ) {
    	return $sql;
    }
    
		switch ( $this->get_setting('sub_wiki_order_by') ) {
			case 'menu_order' :
				$sql = 'ORDER BY p.menu_order ' . (( $this->get_setting('sub_wiki_order') == 'ASC') ? 'DESC' : 'ASC') . ' LIMIT 1';
				break;
			
			case 'title' :
				$sql = 'ORDER BY p.post_title ' . (( $this->get_setting('sub_wiki_order') == 'ASC') ? 'DESC' : 'ASC') . ' LIMIT 1';
				break;
		}
		
		return $sql;
	}
		
	/**
	 * Safely retrieve a setting
	 *
	 * @param string $key
	 * @param mixed $default The value to return if the setting key is not set
	 * @since 1.2.3
	 */
	function get_setting( $key, $default = false ) {
		return isset($this->settings[$key]) ? $this->settings[$key] : $default;
	}

	function new_wiki_form() {
		global $wp_version, $wp_query, $edit_post, $post_id, $post_ID;
		
		echo '<div class="incsub_wiki incsub_wiki_single">';
		echo '<div class="incsub_wiki_tabs incsub_wiki_tabs_top"><div class="incsub_wiki_clear"></div></div>';
			
		echo '<h3>'.__('Edit', 'wiki').'</h3>';
		echo	'<form action="" method="post">';
		$edit_post = $this->get_default_post_to_edit(get_query_var('post_type'), true, 0);
		
		$post_id = $edit_post->ID;
		$post_ID = $post_id;
		
		$slug_parts = preg_split('/\//', $wp_query->query_vars['incsub_wiki']);
		
		if (count($slug_parts) > 1) {
			for ($i=count($slug_parts)-1; $i>=0; $i--) {
				$parent_post = get_posts(array('name' => $slug_parts[$i], 'post_type' => 'incsub_wiki', 'post_status' => 'publish'));
				if (is_array($parent_post) && count($parent_post) > 0) {
					break;
				}
			}
			$parent_post = $parent_post[0];
		}
		
		echo	'<input type="hidden" name="parent_id" id="parent_id" value="'.$parent_post->ID.'" />';
		echo	'<input type="hidden" name="original_publish" id="original_publish" value="Publish" />';
		echo	'<input type="hidden" name="publish" id="publish" value="Publish" />';
		echo	'<input type="hidden" name="post_type" id="post_type" value="'.$edit_post->post_type.'" />';
		echo	'<input type="hidden" name="post_ID" id="wiki_id" value="'.$edit_post->ID.'" />';
		echo	'<input type="hidden" name="post_status" id="wiki_id" value="published" />';
		echo	'<input type="hidden" name="comment_status" id="comment_status" value="open" />';
		echo	'<input type="hidden" name="action" id="wiki_action" value="editpost" />';
		echo	'<div><input type="hidden" name="post_title" id="wiki_title" value="'.ucwords(get_query_var('name')).'" class="incsub_wiki_title" size="30" /></div>';
		echo	'<div>';
		wp_editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));
		echo	'</div>';
		echo	'<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-editpost_{$edit_post->ID}").'" />';
		
		if (is_user_logged_in()) {
			echo	 $this->get_meta_form();
		}
		echo	'<div class="incsub_wiki_clear">';
		echo	'<input type="submit" name="save" id="btn_save" value="'.__('Save', 'wiki').'" />&nbsp;';
		echo	'<a href="'.get_permalink().'">'.__('Cancel', 'wiki').'</a>';
		echo	'</div>';
		echo	'</form>';
		echo	'</div>';
		
		echo '<style type="text/css">'.
			'#comments { display: none; }'.
			'.comments { display: none; }'.
		'</style>';
		
		return '';
	}
		
	function get_edit_form($showheader = false) {
		global $post, $wp_version, $edit_post, $post_id, $post_ID;
		
		if ( !current_user_can('edit_wiki', $post->ID) && !current_user_can('edit_wikis', $post->ID) && !current_user_can('edit_others_wikis', $post->ID) && !current_user_can('edit_published_wikis', $post->ID) ) {
			return __('You do not have permission to view this page.', 'wiki');
		}
		
		$return = '';
		$stack = debug_backtrace();
		
		// Jet pack compatibility
		if (isset($stack[3]) && isset($stack[3]['class']) 
			&& isset($stack[3]['function']) && $stack[3]['class'] == 'Jetpack_PostImages' 
			&& $stack[3]['function'] == 'from_html') return $showheader;
		
		if ($showheader) {
			$return .= '<div class="incsub_wiki incsub_wiki_single">';
			$return .= '<div class="incsub_wiki_tabs incsub_wiki_tabs_top">' . $this->tabs() . '<div class="incsub_wiki_clear"></div></div>';
		}
		$return .= '<h2>'.__('Edit', 'wiki').'</h2>';
		$return .=	'<form action="'.get_permalink().'" method="post">';
		if (isset($_REQUEST['eaction']) && $_REQUEST['eaction'] == 'create') {
			$edit_post = $this->get_default_post_to_edit($post->post_type, true, $post->ID);
			$return .=	 '<input type="hidden" name="parent_id" id="parent_id" value="'.$post->ID.'" />';
			$return .=	 '<input type="hidden" name="original_publish" id="original_publish" value="Publish" />';
			$return .=	 '<input type="hidden" name="publish" id="publish" value="Publish" />';
		} else {
			$edit_post = $post;
			$return .=	 '<input type="hidden" name="parent_id" id="parent_id" value="'.$edit_post->post_parent.'" />';
			$return .=	 '<input type="hidden" name="original_publish" id="original_publish" value="Update" />';
		}
		
		$post_id = $edit_post->ID;
		$post_ID = $post_id;
		
		$return .=	'<input type="hidden" name="post_type" id="post_type" value="'.$edit_post->post_type.'" />';
		$return .=	'<input type="hidden" name="post_ID" id="wiki_id" value="'.$edit_post->ID.'" />';

		if ( 'private' == $edit_post->post_status ) {
				$edit_post->post_password = '';
				$visibility = 'private';
					$visibility_trans = __('Private');
		} elseif ( !empty( $edit_post->post_password ) ) {
				$visibility = 'password';
				$visibility_trans = __('Password protected');
		} else {
				$visibility = 'public';
				$visibility_trans = __('Public');
		}

		$return .= '<input type="hidden" name="post_status" id="wiki_post_status" value="'.$edit_post->post_status.'" />';
		$return .= '<input type="hidden" name="visibility" id="wiki_visibility" value="'.$visibility.'" />';

		$return .= '<input type="hidden" name="comment_status" id="comment_status" value="'.$edit_post->comment_status.'" />';
		$return .= '<input type="hidden" name="action" id="wiki_action" value="editpost" />';
		$return .= '<div><input type="text" name="post_title" id="wiki_title" value="'.$edit_post->post_title.'" class="incsub_wiki_title" size="30" /></div>';
		$return .= '<div>';
		
		if ( @ob_start() ) {
			// Output buffering is on, capture the output from wp_editor() and append it to the $return variable
			wp_editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));
			$return .= ob_get_clean();
		} else {
			/*
			This is hacky, but without output buffering on we needed to make a copy of the built-in _WP_Editors class and
			change the editor() method to return the output instead of echo it. The only bad thing about this is that we
			also had to remove the media_buttons action so plugins/themes won't be able to tie into it
			*/
			require_once $this->plugin_dir . 'lib/classes/WPEditor.php';
			$return .= WikiEditor::editor($edit_post->post_content, 'wikicontent', array('textarea_name' => 'content'));
		}
		
		$return .= '</div>';
		$return .= '<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-editpost_{$edit_post->ID}").'" />';
		
		if (is_user_logged_in()) {
			$return .= $this->get_meta_form(true);
		}
		
		$return .= '<div class="incsub_wiki_clear incsub_wiki_form_buttons">';
		$return .= '<input type="submit" name="save" id="btn_save" value="'.__('Save', 'wiki').'" />&nbsp;';
		$return .= '<a href="'.get_permalink().'">'.__('Cancel', 'wiki').'</a>';
		$return .= '</div>';
		$return .= '</form>';
		
		if ($showheader) {
			$return .= '</div>';
		}
		
		$return .= '<style type="text/css">'.
			'#comments { display: none; }'.
			'.comments { display: none; }'.
		'</style>';
		
		return $return;
	}
		
	function get_meta_form( $frontend = false ) {
		global $post;
		
		$content	= '';
		
		if ( class_exists('Wiki_Premium') ) {
			$content .= ( $frontend ) ? '<h3 class="incsub_wiki_header">' . __('Wiki Categories/Tags', 'wiki') . '</h3>' : '';		
			$content .= '<div class="incsub_wiki_meta_box">'. Wiki_Premium::get_instance()->wiki_taxonomies(false) . '</div>';
		}
		
		$content .= ( $frontend ) ? '<h3 class="incsub_wiki_header">' . __('Wiki Notifications', 'wiki') . '</h3>' : '';
		$content .= '<div class="incsub_wiki_meta_box">' . $this->notifications_meta_box($post, false) . '</div>';
		
		if ( current_user_can('edit_wiki_privileges') && class_exists('Wiki_Premium') ) {
			$content .= ( $frontend ) ? '<h3 class="incsub_wiki_header">' . __('Wiki Privileges', 'wiki') . '</h3>' : '';
			$content .= '<div class="incsub_wiki_meta_box">' . Wiki_Premium::get_instance()->privileges_meta_box($post, false) . '</div>';
		}
		
		return $content;
	}
	
	function tabs() {
		global $post, $incsub_tab_check, $wp_query;
		
		$incsub_tab_check = 1;
		$permalink = get_permalink();
		
		$classes = array();
		$classes['page'] = array('incsub_wiki_link_page');
		$classes['discussion'] = array('incsub_wiki_link_discussion');
		$classes['history'] = array('incsub_wiki_link_history');
		$classes['edit'] = array('incsub_wiki_link_edit');
		$classes['advanced_edit'] = array('incsub_wiki_link_advanced_edit');
		$classes['create'] = array('incsub_wiki_link_create');
		
		if (!isset($_REQUEST['action'])) {
			$classes['page'][] = 'current';
		}
		if (isset($_REQUEST['action'])) {
			switch ($_REQUEST['action']) {
				case 'page':
					$classes['page'][] = 'current';
					break;
				case 'discussion':
					$classes['discussion'][] = 'current';
					break;
				case 'restore':
				case 'diff':
				case 'history':
					$classes['history'][] = 'current';
					break;
				case 'edit':
					if (isset($_REQUEST['eaction']) && $_REQUEST['eaction'] == 'create')
					$classes['create'][] = 'current';
					else
					$classes['edit'][] = 'current';
					break;
			}
		}
	
		
		
		$tabs	 = '<ul class="left">';
		$tabs .= '<li class="'.join(' ', $classes['page']).'" ><a href="' . $permalink . '" >' . __('Page', 'wiki') . '</a></li>';
		if (comments_open()) {
			$tabs .= '<li class="'.join(' ', $classes['discussion']).'" ><a href="' . add_query_arg('action', 'discussion', $permalink) . '">' . __('Discussion', 'wiki') . '</a></li>';
		}
		$tabs .= '<li class="'.join(' ', $classes['history']).'" ><a href="' . add_query_arg('action', 'history', $permalink) . '">' . __('History', 'wiki') . '</a></li>';
		$tabs .= '</ul>';
		
		$post_type_object = get_post_type_object( get_query_var('post_type') );
		
		if ($post && current_user_can($post_type_object->cap->edit_post, $post->ID)) {
			$tabs .= '<ul class="right">';
			$tabs .= '<li class="'.join(' ', $classes['edit']).'" ><a href="' . add_query_arg('action', 'edit', $permalink) . '">' . __('Edit', 'wiki') . '</a></li>';
			if (is_user_logged_in()) {
			$tabs .= '<li class="'.join(' ', $classes['advanced_edit']).'" ><a href="' . get_edit_post_link() . '" >' . __('Advanced', 'wiki') . '</a></li>';
			}
			$tabs .= '<li class="'.join(' ', $classes['create']).'"><a href="' . add_query_arg(array('action' => 'edit', 'eaction' => 'create'), $permalink) . '">'.__('Create new', 'wiki').'</a></li>';
			$tabs .= '</ul>';
		}
		
		$incsub_tab_check = 0;
		
		return $tabs;
	}
		
	function get_edit_post_link($url, $id = 0, $context = 'display') {
		global $post;
		return $url;
	}
		
	/**
	 * Display list of a post's revisions.
	 *
	 * Can output either a UL with edit links or a TABLE with diff interface, and
	 * restore action links.
	 *
	 * Second argument controls parameters:
	 *	 (bool)		parent : include the parent (the "Current Revision") in the list.
	 *	 (string) format : 'list' or 'form-table'.	'list' outputs UL, 'form-table'
	 *										 outputs TABLE with UI.
	 *	 (int)		right	 : what revision is currently being viewed - used in
	 *										 form-table format.
	 *	 (int)		left	 : what revision is currently being diffed against right -
	 *										 used in form-table format.
	 *
	 * @package WordPress
	 * @subpackage Post_Revisions
	 * @since 2.6.0
	 *
	 * @uses wp_get_post_revisions()
	 * @uses wp_post_revision_title()
	 * @uses get_edit_post_link()
	 * @uses get_the_author_meta()
	 *
	 * @todo split into two functions (list, form-table) ?
	 *
	 * @param int|object $post_id Post ID or post object.
	 * @param string|array $args See description {@link wp_parse_args()}.
	 * @return null
	 */
	function list_post_revisions( $post_id = 0, $args = null ) {
		if ( !$post = get_post( $post_id ) )
			return;
		
		$content = '';
		$defaults = array( 'parent' => false, 'right' => false, 'left' => false, 'format' => 'list', 'type' => 'all' );
		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );
		switch ( $type ) {
			case 'autosave' :
				if ( !$autosave = wp_get_post_autosave( $post->ID ) )
					return;
				$revisions = array( $autosave );
				break;
			case 'revision' : // just revisions - remove autosave later
			case 'all' :
			default :
				if ( !$revisions = wp_get_post_revisions( $post->ID ) )
					 return;
				break;
		}
		
		/* translators: post revision: 1: when, 2: author name */
		$titlef = _x( '%1$s by %2$s', 'post revision' );
		
		if ( $parent )
			array_unshift( $revisions, $post );
			
		$rows = '';
		$class = false;
		$can_edit_post = current_user_can( 'edit_wiki', $post->ID );
		foreach ( $revisions as $revision ) {
			/*if ( !current_user_can( 'read_post', $revision->ID ) )
			continue;*/
			if ( 'revision' === $type && wp_is_post_autosave( $revision ) )
				continue;
			
			$date = wp_post_revision_title( $revision, false );
			$name = get_the_author_meta( 'display_name', $revision->post_author );
			
			if ( 'form-table' == $format ) {
				if ( $left )
					$left_checked = $left == $revision->ID ? ' checked="checked"' : '';
				else
					$left_checked = (isset($right_checked) && $right_checked) ? ' checked="checked"' : ''; // [sic] (the next one)
				
				$right_checked = $right == $revision->ID ? ' checked="checked"' : '';
				
				$class = $class ? '' : " class='alternate'";
				
				if ( $post->ID != $revision->ID && $can_edit_post && current_user_can( 'read_post', $revision->ID ) )
					$actions = '<a href="' . wp_nonce_url( add_query_arg( array( 'revision' => $revision->ID, 'action' => 'restore' ) ), "restore-post_$post->ID|$revision->ID" ) . '">' . __( 'Restore' ) . '</a>';
				else
					$actions = ' ';
					
				$rows .= "<tr$class>\n";
				$rows .= "\t<td style='white-space: nowrap' scope='row'><input type='radio' name='left' value='{$revision->ID}' {$left_checked} /></td>\n";
				$rows .= "\t<td style='white-space: nowrap' scope='row'><input type='radio' name='right' value='{$revision->ID}' {$right_checked} /></td>\n";
				$rows .= "\t<td>$date</td>\n";
				$rows .= "\t<td>$name</td>\n";
				$rows .= "\t<td class='action-links'>$actions</td>\n";
				$rows .= "</tr>\n";
			} else {
				$title = sprintf( $titlef, $date, $name );
				$rows .= "\t<li>$title</li>\n";
			}
		}
		if ( 'form-table' == $format ) :
			$content .= '<form action="'.get_permalink().'" method="get">';
			$content .= '<div class="tablenav">';
			$content .= '<div class="alignleft">';
			$content .= '<input type="submit" class="button-secondary" value="'.esc_attr( __('Compare Revisions', 'wiki' ) ).'" />';
			$content .= '<input type="hidden" name="action" value="diff" />';
			$content .= '<input type="hidden" name="post_type" value="'.esc_attr($post->post_type).'" />';
			$content .= '</div>';
			$content .= '</div>';
			$content .= '<br class="clear" />';
			$content .= '<table class="widefat post-revisions" cellspacing="0" id="post-revisions">';
			$content .= '<col /><col /><col style="width: 33%" /><col style="width: 33%" /><col style="width: 33%" />';
			$content .= '<thead>';
			$content .= '<tr>';
			$content .= '<th scope="col">'._x( 'Old', 'revisions column name', 'wiki' ).'</th>';
			$content .= '<th scope="col">'._x( 'New', 'revisions column name', 'wiki' ).'</th>';
			$content .= '<th scope="col">'._x( 'Date Created', 'revisions column name', 'wiki' ).'</th>';
			$content .= '<th scope="col">'.__( 'Author', 'wiki', 'wiki' ).'</th>';
			$content .= '<th scope="col" class="action-links">'.__( 'Actions', 'wiki' ).'</th>';
			$content .= '</tr>';
			$content .= '</thead>';
			$content .= '<tbody>';
			$content .= $rows;
			$content .= '</tbody>';
			$content .= '</table>';
			$content .= '</form>';
		else :
			$content .= "<ul class='post-revisions'>\n";
			$content .= $rows;
			$content .= "</ul>";
		endif;
		return $content;
	}
		
	function user_has_cap( $allcaps, $caps = null, $args = null ) {
		global $current_user, $blog_id, $post;
		
		$capable = false;
		
		if (preg_match('/(_wiki|_wikis)/i', join($caps, ',')) > 0) {
			if (in_array('administrator', $current_user->roles) || is_super_admin()) {
				foreach ($caps as $cap) {
					$allcaps[$cap] = 1;
				}
				return $allcaps;
			}
			foreach ($caps as $cap) {
				$capable = false;
				switch ($cap) {
					case 'read_wiki':
						$capable = true;
						break;
						
					case 'edit_others_wikis':
					case 'edit_published_wikis':
					case 'edit_wikis':
					case 'edit_wiki':
						if (isset($args[2])) {
							$edit_post = get_post($args[2]);
						} else if (isset($_REQUEST['post_ID'])) {
							$edit_post = get_post($_REQUEST['post_ID']);
						} else {
							$edit_post = $post;
						}
						
						if ($edit_post) {
							$current_privileges = get_post_meta($edit_post->ID, 'incsub_wiki_privileges', true);
							
							if ( empty($current_privileges) ) {
								$current_privileges = array('edit_posts');
							}
							
							if ($edit_post->post_status == 'auto-draft') {
								$capable = true;
							} else if ($current_user->ID == 0) {
								if (in_array('anyone', $current_privileges)) {
									$capable = true;
								}
							} else {
								if (in_array('edit_posts', $current_privileges) && current_user_can('edit_posts')) {
									$capable = true;
								} else if (in_array('site', $current_privileges) && current_user_can_for_blog($blog_id, 'read')) {
									$capable = true;
								} else if (in_array('network', $current_privileges) && is_user_logged_in()) {
									$capable = true;
								} else if (in_array('anyone', $current_privileges)) {
									$capable = true;
								}
							}
						} else if (current_user_can('edit_posts')) {
							$capable = true;
						}
						break;
						
					default:
						if (isset($args[1]) && isset($args[2])) {
							if (current_user_can(preg_replace('/_wiki/i', '_post', $cap), $args[1], $args[2])) {
								$capable = true;
							}
						} else if (isset($args[1])) {
							if (current_user_can(preg_replace('/_wiki/i', '_post', $cap), $args[1])) {
								$capable = true;
							}
						} else if (current_user_can(preg_replace('/_wiki/i', '_post', $cap))) {
							$capable = true;
						}
						break;
				}
				
				if ($capable) {
					$allcaps[$cap] = 1;
				}
			}
		}
		
		return $allcaps;
	}
		
	function role_has_cap($capabilities, $cap, $name) {
		// nothing to do
		return $capabilities;
	}
		
	/**
	 * Install
	 *
	 * @$uses	$wpdb
	 */
	function install() {
		global $wpdb;
		
		if ( get_option('wiki_version', false) == $this->version )
			return;
		
		// WordPress database upgrade/creation functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// Get the correct character collate
		$charset_collate = '';
		if ( ! empty($wpdb->charset) )
			$charset_collate .= "DEFAULT CHARSET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
		
		// Setup the subscription table
		dbDelta("
			CREATE TABLE {$this->db_prefix}wiki_subscriptions (
				ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				blog_id BIGINT(20) NOT NULL,
				wiki_id BIGINT(20) NOT NULL,
				user_id BIGINT(20),
				email VARCHAR(255),
				PRIMARY KEY  (ID)
			) ENGINE=InnoDB $charset_collate;");

		$this->setup_blog();
							
		update_option('wiki_version', $this->version);
	}
		
	/**
	 * Sets up a blog - called from install() and new_blog()
	 *
	 * @param int $blog (Optional) If multisite blog_id will be passed, otherwise will be NULL.
	 */
	function setup_blog( $blog_id = NULL ) {
		if ( !is_null($blog_id) )
			switch_to_blog($blog_id);
			
		// Set admin permissions
		$role = get_role('administrator');
		$role->add_cap('edit_wiki_privileges');

		// Set default settings
		$default_settings = array(
			'slug' => 'wiki',
			'breadcrumbs_in_title' => 0,
			'wiki_name' => __('Wikis', 'wiki'),
			'sub_wiki_name' => __('Sub Wikis', 'wiki'),
			'sub_wiki_order_by' => 'menu_order',
			'sub_wiki_order' => 'ASC'
		);

		// Migrate and delete old settings option name which isn't very intuitive
		if ( $settings = get_option('wiki_default') )
			delete_option('wiki_default');
		else
			$settings = get_option('wiki_settings');
		
		// Merge settings
		if ( is_array($settings) )
			$settings = wp_parse_args($settings, $default_settings);
		else
			$settings = $default_settings;
			
		// Update settings
		$this->settings = $settings;
					
		update_option('wiki_settings', $settings);
		update_option('wiki_flush_rewrites', 1);
		
		if ( !is_null($blog_id) ) {
			restore_current_blog();
			refresh_blog_details($blog_id);
		}
	}
					
	/**
	 * Initialize the plugin
	 * 
	 * @see		http://codex.wordpress.org/Plugin_API/Action_Reference
	 * @see		http://adambrown.info/p/wp_hooks/hook/init
	 */
	function init() {
		global $wpdb, $wp_rewrite, $current_user, $blog_id, $wp_roles;
		
		$this->install();	//we run this here because activation hooks aren't triggered when updating - see http://wp.mu/8kv
		
		if ( is_admin() ) {
			$this->init_admin_pages();
		}
		
		$this->settings = get_option('wiki_settings');
		
		if (preg_match('/mu\-plugin/', $this->plugin_dir) > 0)
			load_muplugin_textdomain('wiki', dirname(plugin_basename(__FILE__)).'/languages');
		else
			load_plugin_textdomain('wiki', false, dirname(plugin_basename(__FILE__)).'/languages');
		
		if ( class_exists('Wiki_Premium') ) {
			// taxonomies MUST be registered before custom post types
			Wiki_Premium::get_instance()->register_taxonomies();
		}
		
		$this->register_post_types();
		
		if (isset($_REQUEST['action'])) {
			switch ($_REQUEST['action']) {
				case 'editpost':
					// editing an existing wiki using the frontend editor
					if (wp_verify_nonce($_POST['_wpnonce'], "wiki-editpost_{$_POST['post_ID']}")) {
						$post_id = $this->edit_post($_POST);
						wp_redirect(get_permalink($post_id));
						exit();
					}
					break;
			}
		}

		if (isset($_REQUEST['subscribe']) && wp_verify_nonce($_REQUEST['_wpnonce'], "wiki-subscribe-wiki_{$_REQUEST['post_id']}")) {
			if (isset($_REQUEST['email'])) {
				if ($wpdb->insert("{$this->db_prefix}wiki_subscriptions",
					array('blog_id' => $blog_id,
					'wiki_id' => $_REQUEST['post_id'],
					'email' => $_REQUEST['email']))) {
					setcookie('incsub_wiki_email', $_REQUEST['email'], time()+3600*24*365, '/');
					wp_redirect(get_permalink($_REQUEST['post_id']));
					exit();
				}
			} elseif (is_user_logged_in()) {
				$result = $wpdb->insert("{$this->db_prefix}wiki_subscriptions", array(
					'blog_id' => $blog_id,
					'wiki_id' => $_REQUEST['post_id'],
					'user_id' => $current_user->ID
				));
				
				if ( false !== $result ) {
					wp_redirect(get_permalink($_REQUEST['post_id']));
					exit();		 
				}
			}
		}
			
		if (isset($_GET['action']) && $_GET['action'] == 'cancel-wiki-subscription') {
			if ($wpdb->query("DELETE FROM {$this->db_prefix}wiki_subscriptions WHERE ID = ".intval($_GET['sid']).";")) {
				wp_redirect(get_option('siteurl'));
				exit();	
			}
		}
	}
	
	/**
	 * Initialize the plugin admin pages
	 */
	function init_admin_pages() {
		$files = $this->get_dir_files($this->plugin_dir . 'admin-pages');
		
		foreach ( $files as $file )
			include_once $file;
	}
	
	/**
	 * Get all files from a given directory
	 * @param string $dir The full path of the directory
	 * @param string $ext Get only files with a given extension. Set to NULL to get all files.
	 */
	function get_dir_files( $dir, $ext = 'php' ) {
		$files = array();
		$dir = trailingslashit($dir);
		
		if ( !is_null($ext) )
			$ext = '.' . $ext;
		
		if ( !is_readable($dir) )
			return false;
		
		$files = glob($dir . '*' . $ext);
		
		return ( empty($files) ) ? false : $files;
	}
	
	/**
	 * Registers plugin custom post types
	 * @since 1.2.4
	 */
	function register_post_types() {
		$slug = $this->settings['slug'];
		register_post_type('incsub_wiki', array(
				'labels' => array(
					'name' => __('Wikis', 'wiki'),
					'singular_name' => __('Wiki', 'wiki'),
					'add_new' => __('Add Wiki', 'wiki'),
					'add_new_item' => __('Add New Wiki', 'wiki'),
					'edit_item' => __('Edit Wiki', 'wiki'),
					'new_item' => __('New Wiki', 'wiki'),
					'view_item' => __('View Wiki', 'wiki'),
					'search_items' => __('Search Wiki', 'wiki'),
					'not_found' =>	 __('No wiki found', 'wiki'),
					'not_found_in_trash' => __('No wikis found in Trash', 'wiki'),
					'menu_name' => __('Wikis', 'wiki')
				),
				'public' => true,
				'capability_type' => 'wiki',
				'hierarchical' => true,
				'map_meta_cap' => true,
				'query_var' => true,
				'supports' => array(
					'title',
					'editor',
					'author',
					'revisions',
					'comments',
					'page-attributes',
					'thumbnail',
				),
				'has_archive' => true,
				'rewrite' => array(
					'slug' => $slug,
					'with_front' => false
				),
				'menu_icon' => $this->plugin_url . '/images/icon.png',
				'taxonomies' => array(
					'incsub_wiki_category',
					'incsub_wiki_tag',
				),
			)
		);
	}

	function wp_enqueue_scripts() {
		if ( get_query_var('post_type') != 'incsub_wiki' ) { return; }
		
		wp_enqueue_script('utils');
		wp_enqueue_script('jquery');
		wp_enqueue_script('incsub_wiki-js', $this->plugin_url . 'js/wiki.js', array('jquery'), $this->version);
		wp_enqueue_style('incsub_wiki-css', $this->plugin_url . 'css/style.css', null, $this->version);
		wp_enqueue_style('incsub_wiki-print-css', $this->plugin_url . 'css/print.css', null, $this->version, 'print');
		
		wp_localize_script('incsub_wiki-js', 'Wiki', array(
			'restoreMessage' => __('Are you sure you want to restore to this revision?', 'wiki'),
		));
	}
		
	function is_subscribed() {
		global $wpdb, $current_user, $post, $blog_id;
		
		if ( is_user_logged_in() )
			return $wpdb->get_var("SELECT COUNT(ID) FROM {$this->db_prefix}wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID} AND user_id = {$current_user->ID}");
		
		if ( isset($_COOKIE['incsub_wiki_email']) )
			return (bool) $wpdb->get_var("SELECT COUNT(ID) FROM {$this->db_prefix}wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID} AND email = '{$_COOKIE['incsub_wiki_email']}'");
		
		return false;
	}
		
	function meta_boxes() {
		global $post, $current_user;
		
		if ($post->post_author == $current_user->ID || current_user_can('edit_posts')) {
			add_meta_box('incsub-wiki-notifications', __('Wiki E-mail Notifications', 'wiki'), array(&$this, 'notifications_meta_box'), 'incsub_wiki', 'side');
		}
	}
		
	function post_type_link($permalink, $post_id, $leavename) {
		$post = get_post($post_id);
		
		$rewritecode = array(
			'%incsub_wiki%'
		);
		
		if ($post->post_type == 'incsub_wiki' && '' != $permalink) {
			
			$ptype = get_post_type_object($post->post_type);
			
			if ($ptype->hierarchical) {
			$uri = get_page_uri($post);
			$uri = untrailingslashit($uri);
			$uri = strrev( stristr( strrev( $uri ), '/' ) );
			$uri = untrailingslashit($uri);
			
			if (!empty($uri)) {
				$uri .= '/';
				$permalink = str_replace('%incsub_wiki%', "{$uri}%incsub_wiki%", $permalink);
			}
			}
			
			$rewritereplace = array(
				($post->post_name == "")?(isset($post->id)?$post->id:0):$post->post_name
			);
			$permalink = str_replace($rewritecode, $rewritereplace, $permalink);
		} else {
			// if they're not using the fancy permalink option
		}
		
		return $permalink;
	}
			
	function name_save($post_name) {
		if ($_POST['post_type'] == 'incsub_wiki' && empty($post_name)) {
			$post_name = $_POST['post_title'];
		}
		
		return $post_name;
	}
					
	function notifications_meta_box( $post, $echo = true ) {
		$settings = get_option('incsub_wiki_settings');
		$email_notify = get_post_meta($post->ID, 'incsub_wiki_email_notification', true);
		
		if ( false === $email_notify )
			$email_notify = 'enabled';
				
		$content	= '';
		$content .= '<input type="hidden" name="incsub_wiki_notifications_meta" value="1" />';
		$content .= '<div class="alignleft">';
		$content .= '<label><input type="checkbox" name="incsub_wiki_email_notification" value="enabled" ' . checked('enabled', $email_notify, false) .' /> '.__('Enable e-mail notifications', 'wiki').'</label>';
		$content .= '</div>';
		$content .= '<div class="clear"></div>';
		
		if ($echo) {
			echo $content;
		}
		return $content;
	}
		
	function save_wiki_meta($post_id, $post = null) {
		//skip quick edit
		if ( defined('DOING_AJAX') )
			return;
				
		if ( $post->post_type == "incsub_wiki" && isset( $_POST['incsub_wiki_notifications_meta'] ) ) {
			$meta = get_post_custom($post_id);
			$email_notify = isset($_POST['incsub_wiki_email_notification']) ? $_POST['incsub_wiki_email_notification'] : 0;
			
			update_post_meta($post_id, 'incsub_wiki_email_notification', $email_notify);
			
			//for any other plugin to hook into
			do_action( 'incsub_wiki_save_notifications_meta', $post_id, $meta );
		}
	}
		
	function widgets_init() {
		include_once 'lib/classes/WikiWidget.php';
		register_widget('WikiWidget');
	}
	
	function send_notifications($post_id) {
		global $wpdb;
		
		// We do autosaves manually with wp_publish_posts_autosave()
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return;

		if ( !$post = get_post( $post_id, ARRAY_A ) )
				return;

		if ( $post['post_type'] != 'incsub_wiki' || !post_type_supports($post['post_type'], 'revisions') )
				return;

		// all revisions and (possibly) one autosave
		$revisions = wp_get_post_revisions($post_id, array( 'order' => 'ASC' ));

		$revision = array_pop($revisions);
	
		$post = get_post($post_id);
		
		$cancel_url = get_option('siteurl') . '?action=cancel-wiki-subscription&sid=';
		$admin_email = get_option('admin_email');
		$post_title = $post->post_title;
		$post_content = $post->post_content;
		$post_url = get_permalink($post_id);
		
		$revisions = wp_get_post_revisions($post->ID);
		$revision = array_shift($revisions);
		
		if ($revision) {
			$revert_url = wp_nonce_url(add_query_arg(array('revision' => $revision->ID), admin_url('revision.php')), "restore-post_$post->ID|$revision->ID" );
		} else {
			$revert_url = "";
		}
		
		//cleanup title
		$blog_name = get_option('blogname');
		$post_title = strip_tags($post_title);
		//cleanup content
		$post_content = strip_tags($post_content);
		//get excerpt
		$post_excerpt = $post_content;
		if (strlen($post_excerpt) > 255) {
			$post_excerpt = substr($post_excerpt,0,252) . '...';
		}
		
		$wiki_notification_content = array();
		$wiki_notification_content['user'] = sprintf(__("Dear Subscriber,

%s was changed

You can read the Wiki page in full here: %s

%s

Thanks,
BLOGNAME

Cancel subscription: CANCEL_URL", 'POST TITLE', 'wiki'), 'POST_URL', 'EXCERPT', 'BLOGNAME');

		if ($revision) {
			$wiki_notification_content['author'] = sprintf(__("Dear Author,

%s was changed

You can read the Wiki page in full here: %s
You can revert the changes: %s

%s

Thanks,
%s

Cancel subscription: %s", 'wiki'), 'POST_TITLE', 'POST_URL', 'REVERT_URL', 'EXCERPT', 'BLOGNAME', 'CANCEL_URL');
			 } else {
			$wiki_notification_content['author'] = sprintf(__("Dear Author,

%s was changed

You can read the Wiki page in full here: %s

%s

Thanks,
%s

Cancel subscription: %s", 'wiki'), 'POST_TITLE', 'POST_URL', 'EXCERPT', 'BLOGNAME', 'CANCEL_URL');
			 }

		//format notification text
		foreach ($wiki_notification_content as $key => $content) {
			$wiki_notification_content[$key] = str_replace("BLOGNAME",$blog_name,$wiki_notification_content[$key]);
			$wiki_notification_content[$key] = str_replace("POST_TITLE",$post_title,$wiki_notification_content[$key]);
			$wiki_notification_content[$key] = str_replace("EXCERPT",$post_excerpt,$wiki_notification_content[$key]);
			$wiki_notification_content[$key] = str_replace("POST_URL",$post_url,$wiki_notification_content[$key]);
			$wiki_notification_content[$key] = str_replace("REVERT_URL",$revert_url,$wiki_notification_content[$key]);
			$wiki_notification_content[$key] = str_replace("\'","'",$wiki_notification_content[$key]);
		}
		
		global $blog_id;
	
		$query = "SELECT * FROM " . $this->db_prefix . "wiki_subscriptions WHERE blog_id = {$blog_id} AND wiki_id = {$post->ID}";
		$subscription_emails = $wpdb->get_results( $query, ARRAY_A );
		
		if (count($subscription_emails) > 0){
			foreach ($subscription_emails as $subscription_email){
			$loop_notification_content = $wiki_notification_content['user'];
			
			$loop_notification_content = $wiki_notification_content['user'];
			
			if ($subscription_email['user_id'] > 0) {
				if ($subscription_email['user_id'] == $post->post_author) {
				$loop_notification_content = $wiki_notification_content['author'];
				}
				$user = get_userdata($subscription_email['user_id']);
				$subscription_to = $user->user_email;
			} else {
				$subscription_to = $subscription_email['email'];
			}
			
			$loop_notification_content = str_replace("CANCEL_URL",$cancel_url . $subscription_email['ID'],$loop_notification_content);
			$subject_content = $blog_name . ': ' . __('Wiki Page Changes', 'wiki');
			$from_email = $admin_email;
			$message_headers = "MIME-Version: 1.0\n" . "From: " . $blog_name .	 " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
			wp_mail($subscription_to, $subject_content, $loop_notification_content, $message_headers);
			}
		}
	}
}

$wiki = Wiki::get_instance();

if ( file_exists($wiki->plugin_dir . 'premium/wiki-premium.php') ) {
	require_once $wiki->plugin_dir . 'premium/wiki-premium.php';
}
