<?php
/*
 Plugin Name: Wiki
 Plugin URI: http://premium.wpmudev.org/project/wiki
 Description: Add a wiki to your blog
 Author: S H Mohanjith (Incsub)
 WDP ID:
 Version: 1.0.0a2
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
    var $current_version = '1.0.0';
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
	add_action('wp_head', array(&$this, 'output_css'));
    	
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
	add_filter('the_content', array(&$this, 'the_content'));
	add_filter('role_has_cap', array(&$this, 'role_has_cap'), 10, 3);
	add_filter('user_has_cap', array(&$this, 'user_has_cap'), 10, 3);
	
	add_filter('get_edit_post_link', array(&$this, 'get_edit_post_link'));
	add_filter('comments_open', array(&$this, 'comments_open'), 10, 1);
	
	// White list the options to make sure non super admin can save wiki options 
	// add_filter('whitelist_options', array(&$this, 'whitelist_options'));
	
	if ( !empty($wpdb->base_prefix) ) {
	    $this->db_prefix = $wpdb->base_prefix;
	} else {
	    $this->db_prefix = $wpdb->prefix;
	}
	
	$this->_options['default'] = get_option('wiki_default', array( 
        ));
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
	    
	    if ($this->product_template = locate_template($templates)) {
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
	return $this->product_template;
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
	    case 'restore':
	    case 'diff':
		if ( !$left_revision  = get_post( $left ) ) {
		    break;
		}
		if ( !$right_revision = get_post( $right ) ) {
		    break;
		}
		
		if ( !current_user_can( 'read_post', $left_revision->ID ) || !current_user_can( 'read_post', $right_revision->ID ) ) {
		    break;
		}
		
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
			$redirect = get_edit_post_link($post->ID, 'display');
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
		
		$post_title = '<a href="' . get_edit_post_link() . '">' . get_the_title() . '</a>';
		$h2 = sprintf( __( 'Compare Revisions of &#8220;%1$s&#8221;' ), $post_title );
		$title = __( 'Revisions' );
		
		$left  = $left_revision->ID;
		$right = $right_revision->ID;
	    case 'history':
		$args = array( 'format' => 'form-table', 'parent' => true, 'right' => $right, 'left' => $left );
		if ( ! WP_POST_REVISIONS || !post_type_supports($post->post_type, 'revisions') ) {
		    $args['type'] = 'autosave';
		}
		
		if (!isset($h2)) {
		    $post_title = '<a href="' . get_edit_post_link() . '">' . get_the_title() . '</a>';
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
		$new_content .= '<div class="incsub_wiki_content">' . $content . '</div>';
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
    
    function tabs() {
	global $post, $incsub_tab_check;
	
	$incsub_tab_check = 1;
	
	$classes['page'] = array('incsub_wiki_link_page');
	$classes['discussion'] = array('incsub_wiki_link_discussion');
	$classes['history'] = array('incsub_wiki_link_history');
	$classes['edit'] = array('incsub_wiki_link_edit');
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
		    $classes['edit'][] = 'current';
		    break;
		case 'create':
		    $classes['create'][] = 'current';
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
	    $tabs .= '<li class="'.join(' ', $classes['edit']).'" ><a href="'.get_edit_post_link($post->ID, 'display').'" >' . __('Edit', $this->translation_domain) . '</a></li>';
	    $tabs .= '<li class="'.join(' ', $classes['create']).'"><a href="'.get_edit_post_link($post->ID, 'display').'?action=create">'.__('Create new', $this->translation_domain).'</a></li>';
	    $tabs .= '</ul>';
	}
	
	$incsub_tab_check = 0;
	
	return $tabs;
    }
    
    function get_edit_post_link($url, $id = 0, $context = 'display') {
	global $post;
	if ($post->post_type == 'incsub_wiki') {
	    return get_permalink().'?action=edit';
	}
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
	
	/* translators: post revision: 1: when, 2: author name */
	$titlef = _x( '%1$s by %2$s', 'post revision' );
	
	if ( $parent )
	    array_unshift( $revisions, $post );
	    
	$rows = '';
        $class = false;
	$can_edit_post = current_user_can( 'edit_post', $post->ID );
        foreach ( $revisions as $revision ) {
	    if ( !current_user_can( 'read_post', $revision->ID ) )
		continue;
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
		
		if ( $post->ID != $revision->ID && $can_edit_post )
		    $actions = '<a href="' . wp_nonce_url( add_query_arg( array( 'revision' => $revision->ID, 'action' => 'restore' ) ), "restore-post_$post->ID|$revision->ID" ) . '">' . __( 'Restore' ) . '</a>';
		else
		    $actions = '';
		    
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
	global $current_user, $blog_id;
	
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
		    case 'edit_wiki':
			if (isset($args[2])) {
			    $post = get_post($args[2]);
			} else if (isset($_REQUEST['post_ID'])) {
			    $post = get_post($_REQUEST['post_ID']);
			}
			
			if ($post) {			    
			    $meta = get_post_custom($args[2]);
			    $current_privileges = unserialize($meta["incsub_wiki_privileges"][0]);
			    
			    if (!$current_privileges) {
				$current_privileges = array();
			    }
			    if ($current_user->ID == 0) {
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
	$this->_options = array(
        );
	
	add_option('wiki_default', $this->_options['default']);
    }
    
    /**
     * Add the admin menus
     * 
     * @see		http://codex.wordpress.org/Adding_Administration_Menus
     */
    function admin_menu() {
	// Nothing to do
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
		'capability_type' => array('wiki', 'wikis'),
		'hierarchical' => true,
		'map_meta_cap' => true,
		'query_var' => true,
		'supports' => $supports,
		'rewrite' => false
	    )
	);
	
	$wiki_structure = '/wiki/%wiki%';
	
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

    function the_content($content) {
	global $post;
	
	if ($post->post_type != 'incsub_wiki') {
	    return $content;
	}
	
	$top = "";
	
	$crumbs = array();
	foreach($post->ancestors as $parent_pid) {
	    $parent_post = get_post($parent_pid);
	    
	    $crumbs[] = '<a href="'.get_permalink($parent_pid).'" class="incsub_wiki_crumbs">'.$parent_post->post_title.'</a>';
	}
	
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
	
	return '<div class="incsub_wiki-top entry-utility">'.$top.'</div> '.$content.' <div class="incsub_wiki-bottom entry-utility">'.$bottom.'</div>';
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
	if ($_POST['post_type'] == 'incsub_wiki') {
	    $post_name = $_POST['post_title'];
	}
	
	return $post_name;
    }
    
    function privileges_meta_box() {
	global $post;
	$settings = get_option('incsub_wiki_settings');
	$meta = get_post_custom($post->ID);
	
	$current_privileges = unserialize($meta["incsub_wiki_privileges"][0]);
	if (!is_array($current_privileges)) {
	    $current_privileges = array('edit_posts');
	}
	$privileges = array('anyone' => 'Anyone', 'network' => 'Network users', 'site' => 'Site users', 'edit_posts' => 'Users who can edit posts in this site');
	?>
	<input type="hidden" name="incsub_wiki_privileges_meta" value="1" />
	<div class="alignleft">
	    <b><?php _e('Allow editing by', $this->translation_domain); ?></b><br/>
	    <?php foreach ($privileges as $key => $privilege) { ?>
	    <label><input type="checkbox" name="incsub_wiki_privileges[]" value="<?php print $key; ?>" <?php print (in_array($key, $current_privileges))?'checked="checked"':''; ?> /> <?php _e($privilege, $this->translation_domain); ?></label><br/>
	    <?php } ?>
	</div>
	<div class="clear"></div>
	<?php
    }
    
    function notifications_meta_box() {
	global $post;
	$settings = get_option('incsub_wiki_settings');
	$meta = get_post_custom($post->ID);
	if ($meta == array()) {
	    $meta = array('incsub_wiki_email_notification' => array('enabled'));
	}
	?>
	<input type="hidden" name="incsub_wiki_notifications_meta" value="1" />
	<div class="alignleft">
	    <label><input type="checkbox" name="incsub_wiki_email_notification" value="enabled" <?php print ($meta["incsub_wiki_email_notification"][0] == "")?'':'checked="checked"'; ?> /> <?php _e('Enable e-mail notifications', $this->translation_domain); ?></label>
	</div>
	<div class="clear"></div>
	<?php
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
					// print_r($nnnode);
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

$wiki = new Wiki();
