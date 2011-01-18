<?php
/*
 Plugin Name: Wiki
 Plugin URI: http://premium.wpmudev.org/project/wiki
 Description: Add a wiki to your blog
 Author: S H Mohanjith (Incsub)
 WDP ID: 159
 Version: 1.0.0
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
 * @since 1.0.0
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
	add_action('init', array(&$this, 'init'));
	add_action('wp_head', array(&$this, 'output_css'));
    	
	add_action('admin_print_styles-settings_page_wiki', array(&$this, 'admin_styles'));
    	add_action('admin_print_scripts-settings_page_wiki', array(&$this, 'admin_scripts'));
	
	add_action('add_meta_boxes_incsub_wiki', array(&$this, 'meta_boxes') );
	add_action('wp_insert_post', array(&$this, 'save_wiki_meta'), 10, 2 );
	
    	add_filter('admin_menu', array(&$this, 'admin_menu'));
	
	// add_action('option_rewrite_rules', array(&$this, 'check_rewrite_rules') );
	add_action('widgets_init', array(&$this, 'widgets_init'));
	add_action('pre_post_update', array(&$this, 'send_notifications'), 50, 1);
	
	add_filter('post_type_link', array(&$this, 'post_type_link'), 10, 3);
	add_filter('name_save_pre', array(&$this, 'name_save'));
	add_filter('the_content', array(&$this, 'the_content'));
	
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
	
	add_option('wiki_default', $this->_chat_options['default']);
    }
    
    /**
     * Add the admin menus
     * 
     * @see		http://codex.wordpress.org/Adding_Administration_Menus
     */
    function admin_menu() {
	add_options_page(__('Wiki Plugin Options', $this->translation_domain), __('Chat', $this->translation_domain), 8, 'chat', array(&$this, 'plugin_options'));
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
	
	$supports = array( 'title', 'editor', 'author', 'revisions', 'page-attributes');
	
	register_post_type( 'incsub_wiki',
	    array(
		'labels' => $labels,
		'public' => true,
		'show_ui' => true,
		'publicly_queryable' => true,
		'capability_type' => 'wiki',
		'capabilities' => array(
		    'edit_wiki', 'read_wiki', 'delete_wiki', 'edit_others_wiki', 'publish_wiki', 'read_private_wiki',
		    'read', 'delete_wiki', 'delete_private_wiki', 'delete_published_wiki', 'delete_others_wiki',
		    'edit_private_wiki', 'edit_published_wiki'),
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
		$bottom .= '<a href="'.wp_nonce_url(add_query_arg(array('revision' => $revision->ID), admin_url('revision.php')), "restore-post_$post->ID|$revision->ID" ).'">'.__('Revisions', $this->translation_domain).'</a> &nbsp;';
	    }
	    $bottom .= '<a href="'.admin_url('post-new.php?post_type=incsub_wiki').'">'.__('Create new Wiki', $this->translation_domain).'</a>'.
	    '</div>';
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
	add_meta_box('incsub-wiki-privileges', __('Wiki Privileges', $this->translation_domain), array(&$this, 'privileges_meta_box'), 'incsub_wiki', 'side');
	add_meta_box('incsub-wiki-notifications', __('Wiki E-mail Notifications', $this->translation_domain), array(&$this, 'notifications_meta_box'), 'incsub_wiki', 'side');
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
	$privileges = array(/*'anyone' => 'Anyone', 'network' => 'Network users', 'site' => 'Site users', */'edit_posts' => 'Users who can edit posts in this site');
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
    
    function widget($args) {
	global $wpdb, $current_site, $post, $wiki_tree;
	extract($args);
	
	$defaults = array('hierarchical' => 'no', 'title' => __('Wiki', $this->translation_domain));
	$options = (array) get_option('wiki_widget_options', $defaults);
	
	foreach ( $defaults as $key => $value )
	    if ( !isset($options[$key]) )
		$options[$key] = $defaults[$key];
	?>
	<?php echo $before_widget; ?>
	<?php echo $before_title . __($options['title'], $this->translation_domain) . $after_title; ?>
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
    
    function widget_control() {
	global $wpdb;
	$options = $newoptions = get_option('widget_users');
	if ( $_POST['wiki-submit'] ) {
	    $newoptions['title'] = $_POST['wiki-title'];
	    $newoptions['hierarchical'] = $_POST['wiki-hierarchical'];
	}
	if ( $options != $newoptions ) {
	    $options = $newoptions;
	    update_option('wiki_widget_options', $options);
	}
	?>
	<div style="text-align:left">
            <label for="wiki-title" style="line-height:35px;display:block;"><?php _e('Title', $this->translation_domain); ?>:<br />
		<input class="widefat" id="wiki-title" name="wiki-title" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
	    <label for="wiki-hierarchical" style="line-height:35px;display:block;"><?php _e('Only top level', $this->translation_domain); ?>:<br />
                <select name="wiki-hierarchical" id="wiki-hierarchical" >
		    <option value="no" <?php if ($options['hierarchical'] == 'no'){ echo 'selected="selected"'; } ?> ><?php _e('Yes', $this->translation_domain); ?></option>
		    <option value="yes" <?php if ($options['hierarchical'] == 'yes'){ echo 'selected="selected"'; } ?> ><?php _e('No', $this->translation_domain); ?></option>
                </select>
            </label>
	    <input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
	</div>
	<?php
    }
    
    function widgets_init() {
	register_sidebar_widget(array(__('Wiki', $this->translation_domain), 'widgets'), array(&$this, 'widget'));
	register_widget_control(array(__('Wiki', $this->translation_domain), 'widgets'), array(&$this, 'widget_control'));
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

$wiki = new Wiki();
