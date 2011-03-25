<?php
/*
 Plugin Name: Wiki
 Plugin URI: http://premium.wpmudev.org/project/wiki
 Description: Add a wiki to your blog
 Author: S H Mohanjith (Incsub)
 WDP ID: 168
 Version: 1.0.1a1
 Author URI: http://premium.wpmudev.org
*/
/**
 * @global	object	$wiki	Convenient access to the chat object
 */
global $wiki;

/**
 * Wiki object (PHP4 compatible)
 * 
 * Add a wiki to your blog
 * 
 * @since 1.0.0a2
 * @author S H Mohanjith <moha@mohanjith.net>
 */
class Wiki {
    /**
     * @todo Update version number for new releases
     *
     * @var		string	$current_version	Current version
     */
    var $current_version = '1.0.1a1';
    /**
     * @var		string	$translation_domain	Translation domain
     */
    var $translation_domain = 'wiki';
    
    var $db_prefix = '';
    
    /**
     * @var		array	$_options		Consolidated options
     */
    var $_options = array();
    
    /**
     * Get the table name with prefixes
     * 
     * @global	object	$wpdb
     * @param	string	$table	Table name
     * @return	string			Table name complete with prefixes
     */
    function tablename($table) {
	global $wpdb;
    	// We use a single table for all chats accross the network
    	return $wpdb->base_prefix.'wiki_'.$table;
    }
	
    /**
     * Initializing object
     * 
     * Plugin register actions, filters and hooks. 
     */
    function Wiki() {
	global $wpdb;
	
	// Activation deactivation hooks
	register_activation_hook(__FILE__, array(&$this, 'install'));
	register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
	
        // Actions
	add_action('init', array(&$this, 'init'), 0);
	add_action('init', array(&$this, 'post_action'));
	add_action('wp_head', array(&$this, 'output_css'));
	add_action('wp_head', array(&$this, 'output_js'), 0);
    	
	add_action('admin_print_styles-settings_page_wiki', array(&$this, 'admin_styles'));
    	add_action('admin_print_scripts-settings_page_wiki', array(&$this, 'admin_scripts'));
	
	add_action('add_meta_boxes_incsub_wiki', array(&$this, 'meta_boxes') );
	add_action('wp_insert_post', array(&$this, 'save_wiki_meta'), 10, 2 );
	
    	add_filter('admin_menu', array(&$this, 'admin_menu'));
	
	add_action('widgets_init', array(&$this, 'widgets_init'));
	add_action('pre_post_update', array(&$this, 'send_notifications'), 50, 1);
	add_action('template_redirect', array(&$this, 'load_templates') );
	
	add_filter('post_type_link', array(&$this, 'post_type_link'), 10, 3);
	add_filter('name_save_pre', array(&$this, 'name_save'));
	// add_filter('the_content', array(&$this, 'the_content'));
	add_filter('role_has_cap', array(&$this, 'role_has_cap'), 10, 3);
	add_filter('user_has_cap', array(&$this, 'user_has_cap'), 10, 3);
	
	add_filter('get_edit_post_link', array(&$this, 'get_edit_post_link'));
	add_filter('comments_open', array(&$this, 'comments_open'), 10, 1);
	
	add_filter('rewrite_rules_array', array(&$this, 'add_rewrite_rules'));
	add_action('option_rewrite_rules', array(&$this, 'check_rewrite_rules'));
	
	add_filter('user_can_richedit', array(&$this, 'user_can_richedit'));
	
	// White list the options to make sure non super admin can save wiki options 
	// add_filter('whitelist_options', array(&$this, 'whitelist_options'));
	
	if ( !empty($wpdb->base_prefix) ) {
	    $this->db_prefix = $wpdb->base_prefix;
	} else {
	    $this->db_prefix = $wpdb->prefix;
	}
	
	$this->_options['default'] = get_option('wiki_default', array('slug' => 'wiki'));
    }
    
    function load_templates() {
	global $wp_query;
	
	if ($wp_query->is_single && $wp_query->query_vars['post_type'] == 'incsub_wiki') {
	    //check for custom theme templates
	    $wiki_name = get_query_var('incsub_wiki');
	    $wiki_id = (int) $wp_query->get_queried_object_id();
	    $templates = array();
	    
	    if ( $product_name ) {
		$templates[] = "incsub_wiki-$wiki_name.php";
	    }
	    
	    if ( $product_id ) {
		$templates[] = "incsub_wiki-$wiki_id.php";
	    }
	    $templates[] = "incsub_wiki.php";
	    
	    if ($this->wiki_template = locate_template($templates)) {
	      add_filter('template_include', array(&$this, 'custom_template') );
	    } else {
	      //otherwise load the page template and use our own theme
	      $wp_query->is_single = null;
	      $wp_query->is_page = 1;
	      add_filter('the_content', array(&$this, 'theme'), 99 );
	    }
	    $this->is_wiki_page = true;
	}
    }
    
    function custom_template() {
	return $this->wiki_template;
    }
    
    function user_can_richedit($wp_rich_edit) {
	global $post;
	
	if ($post->post_type) {
	    return true;
	}
	return $wp_rich_edit;
    }
    
    function add_rewrite_rules($rules){
	$settings = get_option('incsub_wiki_settings');
	
	$new_rules = array();
	
	$new_rules[$this->_options['default']['slug'].'/(.+?)/?$'] = 'index.php?incsub_wiki=$matches[1]';
	
	return array_merge($new_rules, $rules);
    }
    
    function check_rewrite_rules($value) {
	//prevent an infinite loop
	if ( ! post_type_exists( 'incsub_wiki' ) )
	    return;
	
	if (!is_array($value))
	    $value = array();
	
	$array_key = $this->_options['default']['slug'].'/(.+?)';
	if ( !array_key_exists($array_key, $value) ) {
	    $this->flush_rewrite();
	}
    }
    
    function flush_rewrite() {
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
    }
    
    function comments_open($open) {
	global $post, $incsub_tab_check;
	
	if ($post->post_type == 'incsub_wiki' && $_REQUEST['action'] != 'discussion') {
	    if ($incsub_tab_check == 0 && !isset($_POST['submit'])) {
		return false;
	    }
	}
	return $open;
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
	if ( 'autosave' != $post_data['action']  && 'auto-draft' == $post_data['post_status'] )
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
	// print_r($post_data); exit();
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
    
    function post_action() {
	global $post;
	
	switch ($_REQUEST['action']) {
	    case 'editpost':
		if (wp_verify_nonce($_POST['_wpnonce'], "wiki-editpost_{$_POST['post_ID']}")) {
		    $post_id = $this->edit_post($_POST);
		    wp_redirect(get_permalink($post_id));
		    exit();
		}
		break;
	}
    }

    function theme($content) {
	global $post;
	
	$new_content  = '<div class="incsub_wiki incsub_wiki_single">';
	$new_content .= '<div class="incsub_wiki_tabs incsub_wiki_tabs_top">' . $this->tabs() . '<div class="incsub_wiki_clear"></div></div>';
	
	$revision_id = isset($_REQUEST['revision'])?absint($_REQUEST['revision']):0;
    	$left        = isset($_REQUEST['left'])?absint($_REQUEST['left']):0;
	$right       = isset($_REQUEST['right'])?absint($_REQUEST['right']):0;
	$action      = isset($_REQUEST['action'])?$_REQUEST['action']:'view';
	
	switch ($_REQUEST['action']) {
	    case 'discussion':
		break;
	    case 'edit':
		set_include_path(get_include_path().PATH_SEPARATOR.ABSPATH.'wp-admin');
		
		$post_type_object = get_post_type_object($post->post_type);
		
		$p = $post;
		
		if ( empty($post->ID) )
		    wp_die( __('You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?') );
		
		if ( !current_user_can($post_type_object->cap->edit_post, $post_id) )
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
		$post = $this->post_to_edit($post_id);
		
		$new_content .= $this->get_edit_form();
		break;
	    case 'restore':
		if ( !$revision = wp_get_post_revision( $revision_id ) )
		    break;
		if ( !current_user_can( 'edit_post', $revision->post_parent ) )
		    break;
		if ( !$post = get_post( $revision->post_parent ) )
		    break;
		
		// Revisions disabled and we're not looking at an autosave
		if ( ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) && !wp_is_post_autosave( $revision ) ) {
		    $redirect = get_permalink().'?action=edit';
		    break;
		}
		
		check_admin_referer( "restore-post_$post->ID|$revision->ID" );
		
		wp_restore_post_revision( $revision->ID );
		$redirect = add_query_arg( array( 'message' => 5, 'revision' => $revision->ID ), get_permalink().'?action=edit' );
		break;
	    case 'diff':
		if ( !$left_revision  = get_post( $left ) ) {
		    break;
		}
		if ( !$right_revision = get_post( $right ) ) {
		    break;
		}
		
		/*if ( !current_user_can( 'read_post', $left_revision->ID ) || !current_user_can( 'read_post', $right_revision->ID ) ) {
		    break;
		}*/
		
		// If we're comparing a revision to itself, redirect to the 'view' page for that revision or the edit page for that post
		if ( $left_revision->ID == $right_revision->ID ) {
		    $redirect = get_edit_post_link( $left_revision->ID );
		    include( ABSPATH . 'wp-admin/js/revisions-js.php' );
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
		$h2 = sprintf( __( 'Compare Revisions of &#8220;%1$s&#8221;' ), $post_title );
		$title = __( 'Revisions' );
		
		$left  = $left_revision->ID;
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
		    $h2 = sprintf( __( 'Revision for &#8220;%1$s&#8221; created on %2$s' ), $post_title, $revision_title );
		}
		
		$new_content .= '<h3 class="long-header">'.$h2.'</h3>';
		$new_content .= '<table class="form-table ie-fixed">';
		$new_content .= '<col class="th" />';
		
		if ( 'diff' == $action ) :
		    $new_content .= '<tr id="revision">';
		    $new_content .= '<th scope="row"></th>';
		    $new_content .= '<th scope="col" class="th-full">';
		    $new_content .= '<span class="alignleft">'.sprintf( __('Older: %s', $this->translation_domain), wp_post_revision_title( $left_revision, false ) ).'</span>';
		    $new_content .= '<span class="alignright">'.sprintf( __('Newer: %s', $this->translation_domain), wp_post_revision_title( $right_revision, false ) ).'</span>';
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
		    $new_content .= '<tr id="revision-field-<?php echo $field; ?>">';
		    $new_content .= '<th scope="row">'.esc_html( $field_title ).'</th>';
		    $new_content .= '<td><div class="pre">'.$rcontent.'</div></td>';
		    $new_content .= '</tr>';
		endforeach;
		
		if ( 'diff' == $action && $identical ) :
		    $new_content .= '<tr><td colspan="2"><div class="updated"><p>'.__( 'These revisions are identical.', $this->translation_domain ). '</p></div></td></tr>';
		endif;
		
		$new_content .= '</table>';

		$new_content .= '<br class="clear" />';
		$new_content .= '<div class="incsub_wiki_revisions">' . $this->list_post_revisions( $post, $args ) . '</div>';
		$redirect = false;
		break;
	    default:
		$top = "";
		
		$crumbs = array();
		foreach($post->ancestors as $parent_pid) {
		    $parent_post = get_post($parent_pid);
		    
		    $crumbs[] = '<a href="'.get_permalink($parent_pid).'" class="incsub_wiki_crumbs">'.$parent_post->post_title.'</a>';
		}
		
		$crumbs[] = '<span class="incsub_wiki_crumbs">'.$post->post_title.'</span>';
		
		sort($crumbs);
		
		$top .= join(get_option("incsub_meta_seperator", " > "), $crumbs);
		
		$children = get_children('post_parent='.$post->ID.'&post_type=incsub_wiki');
		
		$crumbs = array();
		foreach($children as $child) {
		    $crumbs[] = '<a href="'.get_permalink($child->ID).'" class="incsub_wiki_crumbs">'.$child->post_title.'</a>';
		}
		
		$bottom = "<h3>".__('Sub Wikis', $this->translation_domain) . "</h3> <ul><li>";
		
		$bottom .= join("</li><li>", $crumbs);
		
		if (count($crumbs) == 0) {
		    $bottom = "";
		} else {
		    $bottom .= "</li></ul>";
		}
		
		$revisions = wp_get_post_revisions($post->ID);
		
		if (current_user_can('edit_wiki')) {
		    $bottom .= '<div class="incsub_wiki-meta">';
		    if (is_array($revisions) && count($revisions) > 0) {
			$revision = array_shift($revisions);
		    }
		    $bottom .= '</div>';
		}
		
		$notification_meta = get_post_custom($post->ID, array('incsub_wiki_email_notification' => 'enabled'));
		
		if ($notification_meta['incsub_wiki_email_notification'][0] == 'enabled' && !$this->is_subscribed()) {
		    if (is_user_logged_in()) {
			    $bottom .= '<div class="incsub_wiki-subscribe"><a href="'.wp_nonce_url(add_query_arg(array('post_id' => $post->ID, 'subscribe' => 1)), "wiki-subscribe-wiki_$post->ID" ).'">'.__('Notify me of changes', $this->translation_domain).'</a></div>';
		    } else {
			if (!empty($_COOKIE['incsub_wiki_email'])) {
			    $user_email = $_COOKIE['incsub_wiki_email'];
			} else {
			    $user_email = "";
			}
			$bottom .= '<div class="incsub_wiki-subscribe">'.
			'<form action="" method="post">'.
			'<label>'.__('E-mail', $this->translation_domain).': <input type="text" name="email" id="email" value="'.$user_email.'" /></label> &nbsp;'.
			'<input type="hidden" name="post_id" id="post_id" value="'.$post->ID.'" />'.
			'<input type="submit" name="subscribe" id="subscribe" value="'.__('Notify me of changes', $this->translation_domain).'" />'.
			'<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-subscribe-wiki_$post->ID").'" />'.
			'</form>'.
			'</div>';
		    }
		}
		$new_content  = '<div class="incsub_wiki_top">' . $top . '</div>'. $new_content;
		$new_content .= '<div class="incsub_wiki_content">' . $content . '</div>';
		$new_content .= '<div class="incsub_wiki_bottom">' . $bottom . '</div>';
		$redirect = false;
	}
	
	$new_content .= '</div>';
	
	if ( !comments_open() ) {
	    $new_content .= '<style type="text/css">'.
	    '#comments { display: none; }'.
	    '</style>';
	} else {
	    $new_content .= '<style type="text/css">'.
	    '.hentry { margin-bottom: 5px; }'.
	    '</style>';
	}
	
	// Empty post_type means either malformed object found, or no valid parent was found.
	if ( !$redirect && empty($post->post_type) ) {
	    $redirect = 'edit.php';
	}
	
	if ( !empty($redirect) ) {
	    wp_redirect( $redirect );
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
	$post->post_title   = apply_filters( 'default_title',   $post_title, $post   );
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
    
    function get_edit_form() {
	global $post;
	
	$xcontent  = '<h3>'.__('Edit', $this->translation_domain).'</h3>';
	$xcontent .= '<form action="'.get_permalink().'" method="post">';
	if (isset($_REQUEST['eaction']) && $_REQUEST['eaction'] == 'create') {
	    $edit_post = $this->get_default_post_to_edit($post->post_type, true, $post->ID);
	    $xcontent .= '<input type="hidden" name="parent_id" id="parent_id" value="'.$post->ID.'" />';
	    $xcontent .= '<input type="hidden" name="original_publish" id="original_publish" value="Publish" />';
	    $xcontent .= '<input type="hidden" name="publish" id="publish" value="Publish" />';
	} else {
	    $edit_post = $post;
	    $xcontent .= '<input type="hidden" name="parent_id" id="parent_id" value="'.$edit_post->post_parent.'" />';
	    $xcontent .= '<input type="hidden" name="original_publish" id="original_publish" value="Update" />';
	}
	$xcontent .= '<input type="hidden" name="post_type" id="post_type" value="'.$edit_post->post_type.'" />';
	$xcontent .= '<input type="hidden" name="post_ID" id="wiki_id" value="'.$edit_post->ID.'" />';
	$xcontent .= '<input type="hidden" name="post_status" id="wiki_id" value="published" />';
	$xcontent .= '<input type="hidden" name="comment_status" id="comment_status" value="open" />';
	$xcontent .= '<input type="hidden" name="action" id="wiki_action" value="editpost" />';
	$xcontent .= '<div><input type="text" name="post_title" id="wiki_title" value="'.$edit_post->post_title.'" class="incsub_wiki_title" size="30" /></div>';
	$xcontent .= '<div><textarea tabindex="2" name="content" id="wiki_content" class="incusb_wiki_tinymce" cols="40" rows="10" >'.$edit_post->post_content.'</textarea></div>';
	$xcontent .= '<input type="hidden" name="_wpnonce" id="_wpnonce" value="'.wp_create_nonce("wiki-editpost_{$edit_post->ID}").'" />';
	
	if (is_user_logged_in()) {
	    $xcontent .= $this->get_meta_form();
	}
	$xcontent .= '<div class="incsub_wiki_clear">';
	$xcontent .= '<input type="submit" name="save" id="btn_save" value="'.__('Save', $this->translation_domain).'" />&nbsp;';
	$xcontent .= '<a href="'.get_permalink().'?action=edit">'.__('Cancel', $this->translation_domain).'</a>';
	$xcontent .= '</div>';
	$xcontent .= '</form>';
	
	$wiki_admin = new WikiAdmin();
	
	$wiki_admin->tiny_mce(true, array("editor_selector" => "incusb_wiki_tinymce"));
	
	return $xcontent;
    }
    
    function get_meta_form() {
	global $post;
	
	$content  = '';
	
	$content .= '<div class="incsub_wiki_meta_box">'.$this->notifications_meta_box(false).'</div>';
	$content .= '<div class="incsub_wiki_meta_box">'.$this->privileges_meta_box(false).'</div>';
	
	return $content;
    }
    
    function tabs() {
	global $post, $incsub_tab_check;
	
	$incsub_tab_check = 1;
	
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
	$tabs  = '<ul class="left">';
	$tabs .= '<li class="'.join(' ', $classes['page']).'" ><a href="'.get_permalink().'" >' . __('Page', $this->translation_domain) . '</a></li>';
	if (comments_open()) {
	    $tabs .= '<li class="'.join(' ', $classes['discussion']).'" ><a href="'.get_permalink().'?action=discussion" >' . __('Discussion', $this->translation_domain) . '</a></li>';
	}
	$tabs .= '<li class="'.join(' ', $classes['history']).'" ><a href="'.get_permalink().'?action=history" >' . __('History', $this->translation_domain) . '</a></li>';
	$tabs .= '</ul>';
	
	$post_type_object = get_post_type_object( $post->post_type );
	
	if (current_user_can($post_type_object->cap->edit_post, $post->ID)) {
	    $tabs .= '<ul class="right">';
	    $tabs .= '<li class="'.join(' ', $classes['edit']).'" ><a href="'.get_permalink().'?action=edit" >' . __('Edit', $this->translation_domain) . '</a></li>';
	    if (is_user_logged_in()) {
	    $tabs .= '<li class="'.join(' ', $classes['advanced_edit']).'" ><a href="'.get_edit_post_link().'" >' . __('Advanced', $this->translation_domain) . '</a></li>';
	    }
	    $tabs .= '<li class="'.join(' ', $classes['create']).'"><a href="'.get_permalink().'?action=edit&eaction=create">'.__('Create new', $this->translation_domain).'</a></li>';
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
     *   (bool)   parent : include the parent (the "Current Revision") in the list.
     *   (string) format : 'list' or 'form-table'.  'list' outputs UL, 'form-table'
     *                     outputs TABLE with UI.
     *   (int)    right  : what revision is currently being viewed - used in
     *                     form-table format.
     *   (int)    left   : what revision is currently being diffed against right -
     *                     used in form-table format.
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
	
	// print_r($revisions);
	
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
		    $left_checked = $right_checked ? ' checked="checked"' : ''; // [sic] (the next one)
		
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
	    $content .= '<input type="submit" class="button-secondary" value="'.esc_attr( 'Compare Revisions' ).'" />';
	    $content .= '<input type="hidden" name="action" value="diff" />';
	    $content .= '<input type="hidden" name="post_type" value="'.esc_attr($post->post_type).'" />';
	    $content .= '</div>';
	    $content .= '</div>';
	    $content .= '<br class="clear" />';
	    $content .= '<table class="widefat post-revisions" cellspacing="0" id="post-revisions">';
	    $content .= '<col /><col /><col style="width: 33%" /><col style="width: 33%" /><col style="width: 33%" />';
	    $content .= '<thead>';
	    $content .= '<tr>';
	    $content .= '<th scope="col">'._x( 'Old', 'revisions column name', $this->translation_domain ).'</th>';
	    $content .= '<th scope="col">'._x( 'New', 'revisions column name', $this->translation_domain ).'</th>';
	    $content .= '<th scope="col">'._x( 'Date Created', 'revisions column name', $this->translation_domain ).'</th>';
	    $content .= '<th scope="col">'.__( 'Author', $this->translation_domain, $this->translation_domain ).'</th>';
	    $content .= '<th scope="col" class="action-links">'.__( 'Actions', $this->translation_domain ).'</th>';
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
    
    function user_has_cap($allcaps, $caps = null, $args = null) {
	global $current_user, $blog_id, $post;
	
	$capable = false;
	
	if (preg_match('/(_wiki|_wikis)/i', join($caps, ',')) > 0) {
	    if (in_array('administrator', $current_user->roles)) {
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
			    $meta = get_post_custom($edit_post->ID);
			    $current_privileges = unserialize($meta["incsub_wiki_privileges"][0]);
			    
			    if (!$current_privileges) {
				$current_privileges = array();
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
    }
    
    /**
     * Activation hook
     * 
     * Create tables if they don't exist and add plugin options
     * 
     * @see		http://codex.wordpress.org/Function_Reference/register_activation_hook
     * 
     * @global	object	$wpdb
     */
    function install() {
        global $wpdb;
        
        /**
         * WordPress database upgrade/creation functions
         */
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Get the correct character collate
        if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if ( ! empty($wpdb->collate) )
	    $charset_collate .= " COLLATE $wpdb->collate";
	
	// Setup the subscription table
	$sql_main =
	"CREATE TABLE `" . $this->db_prefix . "wiki_subscriptions` (
	    `ID` bigint(20) unsigned NOT NULL auto_increment,
	    `blog_id` bigint(20) NOT NULL,
	    `wiki_id` bigint(20) NOT NULL,
	    `user_id` bigint(20),
	    `email` VARCHAR(255),
	    PRIMARY KEY  (`ID`)
	) ENGINE=MyISAM;";
	
	dbDelta($sql_main);
	
	// Default chat options
	$this->_options = array('default' => array(
	    'slug' => 'wiki'
        ));
	
	add_option('wiki_default', $this->_options['default']);
    }
    
    /**
     * Add the admin menus
     * 
     * @see		http://codex.wordpress.org/Adding_Administration_Menus
     */
    function admin_menu() {
	$page = add_submenu_page('edit.php?post_type=incsub_wiki', __('Wiki Settings', $this->translation_domain), __('Wiki Settings', $this->translation_domain), 'manage_options', 'incsub_wiki', array(&$this, 'options_page'));
	add_action( 'admin_print_scripts-' . $page, array(&$this, 'admin_script_settings') );
	add_action( 'admin_print_styles-' . $page, array(&$this, 'admin_css_settings') );
    }
    
    function admin_script_settings() {
	// Nothing to do
    }
    
    function admin_css_settings() {
	// Nothing to do
    }
    
    function options_page() {
	if(!current_user_can('manage_options')) {
  		echo "<p>" . __('Nice Try...', $this->translation_domain) . "</p>";  //If accessed properly, this message doesn't appear.
  		return;
  	}
	if (wp_verify_nonce($_POST['_wpnonce'], 'incsub_wiki-update-options')) {
	    $this->_options['default']['slug'] = $_POST['wiki_default']['slug'];
	    update_option('wiki_default', $this->_options['default']);
	    wp_redirect('edit.php?post_type=incsub_wiki&page=incsub_wiki&incsub_wiki_settings_saved=1');
	}
	if ($_GET['incsub_wiki_settings_saved'] == 1) {
          echo '<div class="updated fade"><p>'.__('Settings saved.', $this->translation_domain).'</p></div>';
        }
	?>
	<div class="wrap">
	    <h2><?php _e('Wiki Settings', $this->translation_domain); ?></h2>
	    <form method="post" action="edit.php?post_type=incsub_wiki&amp;page=incsub_wiki">
	    <?php wp_nonce_field('incsub_wiki-update-options'); ?>
	    <table>
		    <tr valign="top">
			    <td><label for="incsub_wiki-slug"><?php _e('Wiki Slug', $this->translation_domain); ?></label> </td>
			    <td> /<input type="text" size="20" id="incsub_wiki-slug" name="wiki_default[slug]" value="<?php print $this->_options['default']['slug']; ?>" /></td>
		    </tr>
	    </table>
	    
	    <p class="submit">
		<input type="submit" name="submit_settings" value="<?php _e('Save Changes', $this->translation_domain) ?>" />
	    </p>
	</form>
	<?php
    }
    
    /**
     * Deactivation hook
     * 
     * @see		http://codex.wordpress.org/Function_Reference/register_deactivation_hook
     * 
     * @global	object	$wpdb
     */
    function uninstall() {
    	global $wpdb;
	// Nothing to do
    }
    
    /**
     * Initialize the plugin
     * 
     * @see		http://codex.wordpress.org/Plugin_API/Action_Reference
     * @see		http://adambrown.info/p/wp_hooks/hook/init
     */
    function init() {
	global $wpdb, $wp_rewrite, $current_user, $blog_id;
	
	if (preg_match('/mu\-plugin/', PLUGINDIR) > 0) {
	    load_muplugin_textdomain($this->translation_domain, dirname(plugin_basename(__FILE__)).'/languages');
	} else {
	    load_plugin_textdomain($this->translation_domain, false, dirname(plugin_basename(__FILE__)).'/languages');
	}
	
	wp_register_script('incsub_wiki_js', plugins_url('wiki/js/wiki-utils.js'), null, $this->current_version);
	
	$labels = array(
	    'name' => __('Wikis', $this->translation_domain),
	    'singular_name' => __('Wiki', $this->translation_domain),
	    'add_new' => __('Add WIki', $this->translation_domain),
	    'add_new_item' => __('Add New Wiki', $this->translation_domain),
	    'edit_item' => __('Edit Wiki', $this->translation_domain),
	    'new_item' => __('New Wiki', $this->translation_domain),
	    'view_item' => __('View Wiki', $this->translation_domain),
	    'search_items' => __('Search Wiki', $this->translation_domain),
	    'not_found' =>  __('No Wiki found', $this->translation_domain),
	    'not_found_in_trash' => __('No wikis found in Trash', $this->translation_domain),
	    'menu_name' => __('Wikis', $this->translation_domain)
	);
	
	$supports = array( 'title', 'editor', 'author', 'revisions', 'comments', 'page-attributes');
	
	register_post_type( 'incsub_wiki',
	    array(
		'labels' => $labels,
		'public' => true,
		'show_ui' => true,
		'publicly_queryable' => true,
		'capability_type' => 'wiki',
		'hierarchical' => true,
		'map_meta_cap' => true,
		'query_var' => true,
		'supports' => $supports,
		'rewrite' => false
	    )
	);
	
	$wiki_structure = '/'.$this->_options['default']['slug'].'/%wiki%';
	
	$wp_rewrite->add_rewrite_tag("%wiki%", '(.+?)', "incsub_wiki=");
	$wp_rewrite->add_permastruct('incsub_wiki', $wiki_structure, false);
	
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
	    } else if (is_user_logged_in()){
		if ($wpdb->insert("{$this->db_prefix}wiki_subscriptions",
		    array('blog_id' => $blog_id,
		    'wiki_id' => $_REQUEST['post_id'],
		    'user_id' => $current_user->ID))) {
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
     * Output CSS
     */
    function output_css() {
        echo '<link rel="stylesheet" href="' . plugins_url('wiki/css/style.css') . '" type="text/css" />';
    }
    
    function output_js() {
	wp_enqueue_script('utils');
    }
    
    function is_subscribed() {
	global $wpdb, $current_user, $post;
	
	if (is_user_logged_in()) {
	    if ($wpdb->get_var("SELECT ID FROM {$this->db_prefix}wiki_subscriptions WHERE wiki_id = {$post->ID} AND user_id = {$current_user->ID}") > 0) {
		return true;
	    }
	} else if ($wpdb->get_var("SELECT ID FROM {$this->db_prefix}wiki_subscriptions WHERE wiki_id = {$post->ID} AND email = '{$_COOKIE['incsub_wiki_email']}'") > 0) {
	    return true;
	}
	
	return false;
    }
    
    function meta_boxes() {
	global $post, $current_user;
	
	if ($post->post_author == $current_user->ID || current_user_can('edit_posts')) {
	    add_meta_box('incsub-wiki-privileges', __('Wiki Privileges', $this->translation_domain), array(&$this, 'privileges_meta_box'), 'incsub_wiki', 'side');
	    add_meta_box('incsub-wiki-notifications', __('Wiki E-mail Notifications', $this->translation_domain), array(&$this, 'notifications_meta_box'), 'incsub_wiki', 'side');
	}
    }
    
    function post_type_link($permalink, $post_id, $leavename) {
	$post = get_post($post_id);
	
	$rewritecode = array(
	    '%wiki%',
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
		    $permalink = str_replace('%wiki%', "{$uri}%wiki%", $permalink);
		}
	    }
	    
	    $rewritereplace = array(
	    	($post->post_name == "")?$post->id:$post->post_name
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
    
    function privileges_meta_box($echo = true) {
	global $post;
	$settings = get_option('incsub_wiki_settings');
	$meta = get_post_custom($post->ID);
	
	$content  = '';
	$current_privileges = unserialize($meta["incsub_wiki_privileges"][0]);
	if (!is_array($current_privileges)) {
	    $current_privileges = array('edit_posts');
	}
	$privileges = array('anyone' => 'Anyone', 'network' => 'Network users', 'site' => 'Site users', 'edit_posts' => 'Users who can edit posts in this site');
	
	$content .= '<input type="hidden" name="incsub_wiki_privileges_meta" value="1" />';
	$content .= '<div class="alignleft">';
	$content .= '<b>'. __('Allow editing by', $this->translation_domain).'</b><br/>';
	foreach ($privileges as $key => $privilege) {
	    $content .= '<label class="incsub_wiki_label_roles"><input type="checkbox" name="incsub_wiki_privileges[]" value="'.$key.'" '.((in_array($key, $current_privileges))?'checked="checked"':'').' /> '.__($privilege, $this->translation_domain).'</label><br class="incsub_wiki_br_roles"/>';
	}
	$content .= '</div>';
	$content .= '<div class="clear"></div>';
	
	if ($echo) {
	    echo $content;
	}
	return $content;
    }
    
    function notifications_meta_box($echo = true) {
	global $post;
	$settings = get_option('incsub_wiki_settings');
	$meta = get_post_custom($post->ID);
	if ($meta == array()) {
	    $meta = array('incsub_wiki_email_notification' => array('enabled'));
	}
	$content  = '';
	$content .= '<input type="hidden" name="incsub_wiki_notifications_meta" value="1" />';
	$content .= '<div class="alignleft">';
	$content .= '<label><input type="checkbox" name="incsub_wiki_email_notification" value="enabled" '.(($meta["incsub_wiki_email_notification"][0] == "")?'':'checked="checked"').' /> '.__('Enable e-mail notifications', $this->translation_domain).'</label>';
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
      
	if ( $post->post_type == "incsub_wiki" && isset( $_POST['incsub_wiki_privileges_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    update_post_meta($post_id, 'incsub_wiki_privileges', $_POST['incsub_wiki_privileges']);
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_wiki_save_privileges_meta', $post_id, $meta );
	}
	
	if ( $post->post_type == "incsub_wiki" && isset( $_POST['incsub_wiki_notifications_meta'] ) ) {
	    $meta = get_post_custom($post_id);
	    
	    update_post_meta($post_id, 'incsub_wiki_email_notification', $_POST['incsub_wiki_email_notification']);
	    
	    //for any other plugin to hook into
	    do_action( 'incsub_wiki_save_notifications_meta', $post_id, $meta );
	}
    }
    
    function widgets_init() {
	register_widget('WikiWidget');
    }
    
    function send_notifications($post_id) {
	global $wpdb;
	// We do autosaves manually with wp_create_post_autosave()
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
	
	$revert_url = wp_nonce_url(add_query_arg(array('revision' => $revision->ID), admin_url('revision.php')), "restore-post_$post->ID|$revision->ID" );
	
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
	$wiki_notification_content['user'] = "Dear Subscriber,

POST_TITLE was changed

You can read the Wiki page in full here: POST_URL

EXCERPT

Thanks,
BLOGNAME

Cancel subscription: CANCEL_URL";

	$wiki_notification_content['author'] = "Dear Author,

POST_TITLE was changed

You can read the Wiki page in full here: POST_URL
You can revert the changes: REVERT_URL

EXCERPT

Thanks,
BLOGNAME

Cancel subscription: CANCEL_URL";

	//format notification text
	foreach ($wiki_notification_content as $key => $content) {
	    $wiki_notification_content[$key] = str_replace("BLOGNAME",$blog_name,$wiki_notification_content[$key]);
	    $wiki_notification_content[$key] = str_replace("POST_TITLE",$post_title,$wiki_notification_content[$key]);
	    $wiki_notification_content[$key] = str_replace("EXCERPT",$post_excerpt,$wiki_notification_content[$key]);
	    $wiki_notification_content[$key] = str_replace("POST_URL",$post_url,$wiki_notification_content[$key]);
	    $wiki_notification_content[$key] = str_replace("REVERT_URL",$revert_url,$wiki_notification_content[$key]);
	    $wiki_notification_content[$key] = str_replace("\'","'",$wiki_notification_content[$key]);
	}
	
	$query = "SELECT * FROM " . $this->db_prefix . "wiki_subscriptions WHERE wiki_id = {$post->ID}";
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
		$subject_content = $blog_name . ': ' . __('Wiki Page Changes', $this->translation_domain);
		$from_email = $admin_email;
		$message_headers = "MIME-Version: 1.0\n" . "From: " . $blog_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		wp_mail($subscription_to, $subject_content, $loop_notification_content, $message_headers);
	    }
	}
    }
}

class WikiWidget extends WP_Widget {
    
    /**
     * @var		string	$translation_domain	Translation domain
     */
    var $translation_domain = 'wiki';
    
    function WikiWidget() {
	$widget_ops = array( 'description' => __('Display Wiki Pages', $this->translation_domain) );
        $control_ops = array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes' );
        $this->WP_Widget( 'incsub_wiki', __('Wiki', $this->translation_domain), $widget_ops, $control_ops );
    }
    
    function widget($args, $instance) {
	global $wpdb, $current_site, $post, $wiki_tree;
	
	extract($args);
	
	$options = $instance;
	
	$title = apply_filters('widget_title', empty($instance['title']) ? __('Wiki', $this->translation_domain) : $instance['title'], $instance, $this->id_base);
	
	?>
	<?php echo $before_widget; ?>
	<?php echo $before_title . $title . $after_title; ?>
	<?php
	    $wiki_tree = array();
	    $wiki_posts = get_posts('post_type=incsub_wiki&order_by=menu_order');
	    
	    // 1st pass
	    foreach ($wiki_posts as $wiki_post) {
		if ($wiki_post->post_parent == 0) {
		    $wiki_tree[$wiki_post->ID] = array($wiki_post);
		}
		if ($wiki_post->ID == $post->ID) {
		    $wiki_post->classes = 'current';
		}
	    }
	    
	    if ($options['hierarchical'] == 'yes') {
		// 2nd pass
		foreach ($wiki_posts as $wiki_post) {
		    if ($wiki_post->post_parent != 0) {
			if (isset($wiki_tree[$wiki_post->post_parent])) {
			    $wiki_tree[$wiki_post->post_parent][$wiki_post->ID] = array($wiki_post);
			}
		    }
		}
		
		// 3rd pass
		foreach ($wiki_posts as $wiki_post) {
		    if ($wiki_post->post_parent != 0) {
			if (!isset($wiki_tree[$wiki_post->post_parent])) {
			    $n = get_post($wiki_post->post_parent);
			    if ($n->post_parent != 0) {
				$wiki_tree[$n->post_parent][$wiki_post->post_parent] = array($n);
				$wiki_tree[$n->post_parent][$wiki_post->post_parent][$wiki_post->ID] = array($wiki_post);
			    } else {
				$wiki_tree[$wiki_post->post_parent] = array($n);
				$wiki_tree[$wiki_post->post_parent][$wiki_post->ID] = array($wiki_post);
			    }
			}
		    }
		}
	    }
	?>
	    <ul>
		<?php
		foreach ($wiki_tree as $node) {
		    $leaf = array_shift($node);
		    if (count($node) > 0) {
		?>
		    <li><a href="<?php print get_permalink($leaf->ID); ?>" class="<?php print $leaf->classes; ?>" ><?php print $leaf->post_title; ?></a>
		    <ul>
		<?php
			foreach ($node as $nnode) {
			    $leaf = array_shift($nnode);
			    if (count($nnode) > 0) {
			    ?>
				<li><a href="<?php print get_permalink($leaf->ID); ?>" class="<?php print $leaf->classes; ?>" ><?php print $leaf->post_title; ?></a>
				<ul>
			    <?php
				    foreach ($nnode as $nnnode) {
					$leaf = array_shift($nnnode);
					?>
					    <li><a href="<?php print get_permalink($leaf->ID); ?>" class="<?php print $leaf->classes; ?>" ><?php print $leaf->post_title; ?></a></li>
				       <?php
				    }
			    ?>
				</ul>
				</li>
			    <?php
			    } else {
			    ?>
				 <li><a href="<?php print get_permalink($leaf->ID); ?>" class="<?php print $leaf->classes; ?>" ><?php print $leaf->post_title; ?></a></li>
			    <?php
			    }
			}
		?>
		    </ul>
		    </li>
		<?php
		    } else {
		?>
		     <li><a href="<?php print get_permalink($leaf->ID); ?>" class="<?php print $leaf->classes; ?>" ><?php print $leaf->post_title; ?></a></li>
		<?php
		    }
		}
		?>
	    </ul>
        <br />
        <?php echo $after_widget; ?>
	<?php
    }
    
    function update($new_instance, $old_instance) {
	$instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes') );
        $instance['title'] = strip_tags($new_instance['title']);
	$instance['hierarchical'] = $new_instance['hierarchical'];
	
        return $instance;
    }
    
    function form($instance) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes'));
        $options = array('title' => strip_tags($instance['title']), 'hierarchical' => $instance['hierarchical']);
	
	?>
	<div style="text-align:left">
            <label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Title', $this->translation_domain); ?>:<br />
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
	    <label for="<?php echo $this->get_field_id('hierarchical'); ?>" style="line-height:35px;display:block;"><?php _e('Only top level', $this->translation_domain); ?>:<br />
                <select id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>" >
		    <option value="no" <?php if ($options['hierarchical'] == 'no'){ echo 'selected="selected"'; } ?> ><?php _e('Yes', $this->translation_domain); ?></option>
		    <option value="yes" <?php if ($options['hierarchical'] == 'yes'){ echo 'selected="selected"'; } ?> ><?php _e('No', $this->translation_domain); ?></option>
                </select>
            </label>
	    <input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
	</div>
	<?php
    }
}

class WikiAdmin {
    /**
     * Adds the TinyMCE editor used on the Write and Edit screens.
     *
     * @package WordPress
     * @since 2.7.0
     *
     * TinyMCE is loaded separately from other Javascript by using wp-tinymce.php. It outputs concatenated
     * and optionaly pre-compressed version of the core and all default plugins. Additional plugins are loaded
     * directly by TinyMCE using non-blocking method. Custom plugins can be refreshed by adding a query string
     * to the URL when queueing them with the mce_external_plugins filter.
     *
     * @param bool $teeny optional Output a trimmed down version used in Press This.
     * @param mixed $settings optional An array that can add to or overwrite the default TinyMCE settings.
     */
    function tiny_mce( $teeny = false, $settings = false ) {
	global $concatenate_scripts, $compress_scripts, $tinymce_version, $editor_styles;
	
	if ( ! user_can_richedit() )
	    return;
	
	$baseurl = includes_url('js/tinymce');
	
	$mce_locale = ( '' == get_locale() ) ? 'en' : strtolower( substr(get_locale(), 0, 2) ); // only ISO 639-1
	
	/*
	The following filter allows localization scripts to change the languages displayed in the spellchecker's drop-down menu.
	By default it uses Google's spellchecker API, but can be configured to use PSpell/ASpell if installed on the server.
	The + sign marks the default language. More information:
	http://wiki.moxiecode.com/index.php/TinyMCE:Plugins/spellchecker
	*/
	$mce_spellchecker_languages = apply_filters('mce_spellchecker_languages', '+English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr,German=de,Italian=it,Polish=pl,Portuguese=pt,Spanish=es,Swedish=sv');
	
	if ( $teeny ) {
	    $plugins = apply_filters( 'teeny_mce_plugins', array('inlinepopups', 'fullscreen', 'wordpress', 'wplink', 'wpdialogs') );
	    $ext_plugins = '';
	} else {
	    $plugins = array( 'inlinepopups', 'spellchecker', 'paste', 'wordpress', 'fullscreen', 'wpeditimage', 'wpgallery', 'tabfocus', 'wplink', 'wpdialogs' );
	    
	    /*
	    The following filter takes an associative array of external plugins for TinyMCE in the form 'plugin_name' => 'url'.
	    It adds the plugin's name to TinyMCE's plugins init and the call to PluginManager to load the plugin.
	    The url should be absolute and should include the js file name to be loaded. Example:
	    array( 'myplugin' => 'http://my-site.com/wp-content/plugins/myfolder/mce_plugin.js' )
	    If the plugin uses a button, it should be added with one of the "$mce_buttons" filters.
	    */
	    $mce_external_plugins = apply_filters('mce_external_plugins', array());
	    
	    $ext_plugins = '';
	    if ( ! empty($mce_external_plugins) ) {
		
		/*
		The following filter loads external language files for TinyMCE plugins.
		It takes an associative array 'plugin_name' => 'path', where path is the
		include path to the file. The language file should follow the same format as
		/tinymce/langs/wp-langs.php and should define a variable $strings that
		holds all translated strings.
		When this filter is not used, the function will try to load {mce_locale}.js.
		If that is not found, en.js will be tried next.
		*/
		$mce_external_languages = apply_filters('mce_external_languages', array());
		$loaded_langs = array();
		$strings = '';
		if ( ! empty($mce_external_languages) ) {
		    foreach ( $mce_external_languages as $name => $path ) {
			if ( @is_file($path) && @is_readable($path) ) {
			    include_once($path);
			    $ext_plugins .= $strings . "\n";
			    $loaded_langs[] = $name;
			}
		    }
		}
		
		foreach ( $mce_external_plugins as $name => $url ) {
		    if ( is_ssl() ) $url = str_replace('http://', 'https://', $url);
		    $plugins[] = '-' . $name;
		    $plugurl = dirname($url);
		    $strings = $str1 = $str2 = '';
		    if ( ! in_array($name, $loaded_langs) ) {
		        $path = str_replace( WP_PLUGIN_URL, '', $plugurl );
		        $path = WP_PLUGIN_DIR . $path . '/langs/';
		        if ( function_exists('realpath') )
			    $path = trailingslashit( realpath($path) );
			if ( @is_file($path . $mce_locale . '.js') )
			    $strings .= @file_get_contents($path . $mce_locale . '.js') . "\n";
			if ( @is_file($path . $mce_locale . '_dlg.js') )
			    $strings .= @file_get_contents($path . $mce_locale . '_dlg.js') . "\n";
			if ( 'en' != $mce_locale && empty($strings) ) {
			    if ( @is_file($path . 'en.js') ) {
			        $str1 = @file_get_contents($path . 'en.js');
			        $strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str1, 1 ) . "\n";
			    }
			    if ( @is_file($path . 'en_dlg.js') ) {
			        $str2 = @file_get_contents($path . 'en_dlg.js');
			        $strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str2, 1 ) . "\n";
			    }
			}
			if ( ! empty($strings) )
			    $ext_plugins .= "\n" . $strings . "\n";
		    }
		    $ext_plugins .= 'tinyMCEPreInit.load_ext("' . $plugurl . '", "' . $mce_locale . '");' . "\n";
		    $ext_plugins .= 'tinymce.PluginManager.load("' . $name . '", "' . $url . '");' . "\n";
		}
	    }
	}
	
	if ( $teeny ) {
	    $mce_buttons = apply_filters( 'teeny_mce_buttons', array('bold, italic, underline, blockquote, separator, strikethrough, bullist, numlist,justifyleft, justifycenter, justifyright, undo, redo, link, unlink, fullscreen') );
	    $mce_buttons = implode($mce_buttons, ',');
	    $mce_buttons_2 = $mce_buttons_3 = $mce_buttons_4 = '';
	} else {
	    $mce_buttons = apply_filters('mce_buttons', array('bold', 'italic', 'strikethrough', '|', 'bullist', 'numlist', 'blockquote', '|', 'justifyleft', 'justifycenter', 'justifyright', '|', 'link', 'unlink', 'wp_more', '|', 'spellchecker', 'fullscreen', 'wp_adv' ));
	    $mce_buttons = implode($mce_buttons, ',');
	    $mce_buttons_2 = array( 'formatselect', 'underline', 'justifyfull', 'forecolor', '|', 'pastetext', 'pasteword', 'removeformat', '|', 'charmap', '|', 'outdent', 'indent', '|', 'undo', 'redo', 'wp_help' );
	    $mce_buttons_2 = apply_filters('mce_buttons_2', $mce_buttons_2);
	    $mce_buttons_2 = implode($mce_buttons_2, ',');
	    $mce_buttons_3 = apply_filters('mce_buttons_3', array());
	    $mce_buttons_3 = implode($mce_buttons_3, ',');
	    $mce_buttons_4 = apply_filters('mce_buttons_4', array());
	    $mce_buttons_4 = implode($mce_buttons_4, ',');
	}
	$no_captions = (bool) apply_filters( 'disable_captions', '' );

	// TinyMCE init settings
	$initArray = array (
	    'mode' => 'specific_textareas',
	    'editor_selector' => 'theEditor',
	    'width' => '100%',
	    'theme' => 'advanced',
	    'skin' => 'wp_theme',
	    'theme_advanced_buttons1' => $mce_buttons,
	    'theme_advanced_buttons2' => $mce_buttons_2,
	    'theme_advanced_buttons3' => $mce_buttons_3,
	    'theme_advanced_buttons4' => $mce_buttons_4,
	    'language' => $mce_locale,
	    'spellchecker_languages' => $mce_spellchecker_languages,
	    'theme_advanced_toolbar_location' => 'top',
	    'theme_advanced_toolbar_align' => 'left',
	    'theme_advanced_statusbar_location' => 'bottom',
	    'theme_advanced_resizing' => true,
	    'theme_advanced_resize_horizontal' => false,
	    'dialog_type' => 'modal',
	    'formats' => "{
		alignleft : [
		    {selector : 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles : {textAlign : 'left'}},
		    {selector : 'img,table', classes : 'alignleft'}
		],
		aligncenter : [
		    {selector : 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles : {textAlign : 'center'}},
		    {selector : 'img,table', classes : 'aligncenter'}
		],
		alignright : [
		    {selector : 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles : {textAlign : 'right'}},
		    {selector : 'img,table', classes : 'alignright'}
		],
		strikethrough : {inline : 'del'}
	    }",
	    'relative_urls' => false,
	    'remove_script_host' => false,
	    'convert_urls' => false,
	    'apply_source_formatting' => false,
	    'remove_linebreaks' => true,
	    'gecko_spellcheck' => true,
	    'entities' => '38,amp,60,lt,62,gt',
	    'accessibility_focus' => true,
	    'tabfocus_elements' => 'major-publishing-actions',
	    'media_strict' => false,
	    'paste_remove_styles' => true,
	    'paste_remove_spans' => true,
	    'paste_strip_class_attributes' => 'all',
	    'paste_text_use_dialog' => true,
	    'wpeditimage_disable_captions' => $no_captions,
	    'plugins' => implode( ',', $plugins ),
	);
	
	if ( ! empty( $editor_styles ) && is_array( $editor_styles ) ) {
	    $mce_css = array();
	    $style_uri = get_stylesheet_directory_uri();
	    if ( ! is_child_theme() ) {
	        foreach ( $editor_styles as $file )
	            $mce_css[] = "$style_uri/$file";
	    } else {
		$style_dir    = get_stylesheet_directory();
		$template_uri = get_template_directory_uri();
		$template_dir = get_template_directory();
		foreach ( $editor_styles as $file ) {
		    if ( file_exists( "$template_dir/$file" ) )
			$mce_css[] = "$template_uri/$file";
		    if ( file_exists( "$style_dir/$file" ) )
		        $mce_css[] = "$style_uri/$file";
		}
	    }
	    $mce_css = implode( ',', $mce_css );
	} else {
	    $mce_css = '';
	}
	$mce_css = trim( apply_filters( 'mce_css', $mce_css ), ' ,' );
	if ( ! empty($mce_css) )
	    $initArray['content_css'] = $mce_css;
	if ( is_array($settings) )
	    $initArray = array_merge($initArray, $settings);
	// For people who really REALLY know what they're doing with TinyMCE
	// You can modify initArray to add, remove, change elements of the config before tinyMCE.init
	// Setting "valid_elements", "invalid_elements" and "extended_valid_elements" can be done through "tiny_mce_before_init".
	// Best is to use the default cleanup by not specifying valid_elements, as TinyMCE contains full set of XHTML 1.0.
	if ( $teeny ) {
	    $initArray = apply_filters('teeny_mce_before_init', $initArray);
	} else {
	    $initArray = apply_filters('tiny_mce_before_init', $initArray);
	}
	if ( empty($initArray['theme_advanced_buttons3']) && !empty($initArray['theme_advanced_buttons4']) ) {
	    $initArray['theme_advanced_buttons3'] = $initArray['theme_advanced_buttons4'];
	    $initArray['theme_advanced_buttons4'] = '';
	}
	if ( ! isset($concatenate_scripts) )
	    script_concat_settings();
	$language = $initArray['language'];
	$compressed = $compress_scripts && $concatenate_scripts && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
	    && false !== strpos( strtolower($_SERVER['HTTP_ACCEPT_ENCODING']), 'gzip');
	/**
	 * Deprecated
	 *
	 * The tiny_mce_version filter is not needed since external plugins are loaded directly by TinyMCE.
	 * These plugins can be refreshed by appending query string to the URL passed to mce_external_plugins filter.
	 * If the plugin has a popup dialog, a query string can be added to the button action that opens it (in the plugin's code).
	 */
	$version = apply_filters('tiny_mce_version', '');
	$version = 'ver=' . $tinymce_version . $version;
	if ( 'en' != $language )
	    include_once(ABSPATH . WPINC . '/js/tinymce/langs/wp-langs.php');
	
	$mce_options = '';
	foreach ( $initArray as $k => $v ) {
	    if ( is_bool($v) ) {
		$val = $v ? 'true' : 'false';
		$mce_options .= $k . ':' . $val . ', ';
		continue;
	    } elseif ( !empty($v) && is_string($v) && ( '{' == $v{0} || '[' == $v{0} ) ) {
		$mce_options .= $k . ':' . $v . ', ';
		continue;
	    }
	    $mce_options .= $k . ':"' . $v . '", ';
	}
	
	$mce_options = rtrim( trim($mce_options), '\n\r,' ); ?>
	
	<script type="text/javascript">
	/* <![CDATA[ */
	tinyMCEPreInit = {
		base : "<?php echo $baseurl; ?>",
		suffix : "",
		query : "<?php echo $version; ?>",
		mceInit : {<?php echo $mce_options; ?>},
		load_ext : function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
	};
	/* ]]> */
	</script>
    <?php
	if ( $compressed )
	    echo "<script type='text/javascript' src='$baseurl/wp-tinymce.php?c=1&amp;$version'></script>\n";
	else
	    echo "<script type='text/javascript' src='$baseurl/tiny_mce.js?$version'></script>\n";
	
	if ( 'en' != $language && isset($lang) )
	    echo "<script type='text/javascript'>\n$lang\n</script>\n";
	else
	    echo "<script type='text/javascript' src='$baseurl/langs/wp-langs-en.js?$version'></script>\n";
    ?>
	<script type="text/javascript">
	/* <![CDATA[ */
	<?php
	    if ( $ext_plugins )
		echo "$ext_plugins\n";
	
	    if ( ! $compressed ) {
	?>
	(function(){var t=tinyMCEPreInit,sl=tinymce.ScriptLoader,ln=t.mceInit.language,th=t.mceInit.theme,pl=t.mceInit.plugins;sl.markDone(t.base+'/langs/'+ln+'.js');sl.markDone(t.base+'/themes/'+th+'/langs/'+ln+'.js');sl.markDone(t.base+'/themes/'+th+'/langs/'+ln+'_dlg.js');tinymce.each(pl.split(','),function(n){if(n&&n.charAt(0)!='-'){sl.markDone(t.base+'/plugins/'+n+'/langs/'+ln+'.js');sl.markDone(t.base+'/plugins/'+n+'/langs/'+ln+'_dlg.js');}});})();
	<?php } ?>
	tinyMCE.init(tinyMCEPreInit.mceInit);
	/* ]]> */
	</script>
    <?php

	// Load additional inline scripts based on active plugins.
	if ( in_array( 'wpdialogs', $plugins ) ) {
	    wp_print_scripts( array( 'wpdialogs-popup' ) );
	    wp_print_styles('wp-jquery-ui-dialog');
	}
    }
    
    function tiny_mce_preload_dialogs() { ?>
	<div id="preloaded-dialogs" style="display:none;">
    <?php do_action('tiny_mce_preload_dialogs'); ?>
	</div>
    <?php
    }
}

$wiki = new Wiki();

if ( !function_exists( 'wdp_un_check' ) ) {
    add_action( 'admin_notices', 'wdp_un_check', 5 );
    add_action( 'network_admin_notices', 'wdp_un_check', 5 );

    function wdp_un_check() {
        if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
	    echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
    }
}
