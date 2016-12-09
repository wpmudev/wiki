<?php

class Wiki_Premium {
	/**
	 * Refers to our single instance of the class
	 *
	 * @since 1.2.5
	 * @access private
	 */
	private static $_instance = null;
	
	/**
	 * Refers to our single instance of the wiki class
	 *
	 * @since 1.2.5
	 * @access public
	 */
	public $wiki = null;
	
	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.2.5
	 * @access public
	 */
	public static function get_instance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new Wiki_Premium();
		}
		
		return self::$_instance;
	}
	
	/**
	 * Registers custom taxonomies
	 *
	 * @since 1.2.4
	 * @access public
	 */
		/**
	 * Registers plugin taxonomies
	 * @since 1.2.4
	 */
	public function register_taxonomies() {
		$slug = $this->wiki->settings['slug'] . '/' . $this->wiki->slug_categories;
		register_taxonomy('incsub_wiki_category', 'incsub_wiki', array(
			'hierarchical' => true,
			'rewrite' => array(
				'slug' => $slug,
				'with_front' => false
			),
			'capabilities' => array(
				'manage_terms' => 'edit_others_wikis',
				'edit_terms' => 'edit_others_wikis',
				'delete_terms' => 'edit_others_wikis',
				'assign_terms' => 'edit_published_wikis'
			),
			'labels' => array(
				'name' => __( 'Wiki Categories', 'wiki' ),
				'singular_name' => __( 'Wiki Category', 'wiki' ),
				'search_items' => __( 'Search Wiki Categories', 'wiki' ),
				'all_items' => __( 'All Wiki Categories', 'wiki' ),
				'parent_item' => __( 'Parent Wiki Category', 'wiki' ),
				'parent_item_colon' => __( 'Parent Wiki Category:', 'wiki' ),
				'edit_item' => __( 'Edit Wiki Category', 'wiki' ),
				'update_item' => __( 'Update Wiki Category', 'wiki' ),
				'add_new_item' => __( 'Add New Wiki Category', 'wiki' ),
				'new_item_name' => __( 'New Wiki Category Name', 'wiki' ),
			),
			'show_admin_column' => true,
		));

		$slug = $this->wiki->settings['slug'] . '/' . $this->wiki->slug_tags;
		register_taxonomy('incsub_wiki_tag', 'incsub_wiki', array(
			'rewrite' => array(
				'slug' => $slug,
				'with_front' => false
			),
			'capabilities' => array(
				'manage_terms' => 'edit_others_wikis',
				'edit_terms' => 'edit_others_wikis',
				'delete_terms' => 'edit_others_wikis',
				'assign_terms' => 'edit_published_wikis'
			),
			'labels' => array(
				'name'			=> __( 'Wiki Tags', 'wiki' ),
				'singular_name'	=> __( 'Wiki Tag', 'wiki' ),
				'search_items'	=> __( 'Search Wiki Tags', 'wiki' ),
				'popular_items'	=> __( 'Popular Wiki Tags', 'wiki' ),
				'all_items'		=> __( 'All Wiki Tags', 'wiki' ),
				'edit_item'		=> __( 'Edit Wiki Tag', 'wiki' ),
				'update_item'	=> __( 'Update Wiki Tag', 'wiki' ),
				'add_new_item'	=> __( 'Add New Wiki Tag', 'wiki' ),
				'new_item_name'	=> __( 'New Wiki Tag Name', 'wiki' ),
				'separate_items_with_commas'	=> __( 'Separate wiki tags with commas', 'wiki' ),
				'add_or_remove_items'			=> __( 'Add or remove wiki tags', 'wiki' ),
				'choose_from_most_used'			=> __( 'Choose from the most used wiki tags', 'wiki' ),
			),
			'show_admin_column' => true,
		));
	}
	
	/**
	 * Displays the admin page settings
	 *
	 * @since 1.2.5
	 * @access public
	 */
	public function admin_page_settings() { ?>
<tr valign="top">
	<th><label for="incsub_wiki-breadcrumbs_in_title"><?php _e('Number of breadcrumbs to add to title', 'wiki'); ?></label> </th>
	<td><input type="text" size="2" id="incsub_wiki-breadcrumbs_in_title" name="wiki[breadcrumbs_in_title]" value="<?php echo $this->wiki->get_setting('breadcrumbs_in_title'); ?>" /></td>
</tr>
<tr valign="top">
	<th><label for="incsub_wiki-wiki_name"><?php _e('What do you want to call Wikis?', 'wiki'); ?></label> </th>
	<td><input type="text" size="20" id="incsub_wiki-wiki_name" name="wiki[wiki_name]" value="<?php echo $this->wiki->get_setting('wiki_name'); ?>" /></td>
</tr>
<tr valign="top">
	<th><label for="incsub_wiki-sub_wiki_name"><?php _e('What do you want to call Sub Wikis?', 'wiki'); ?></label> </th>
	<td><input type="text" size="20" id="incsub_wiki-sub_wiki_name" name="wiki[sub_wiki_name]" value="<?php echo $this->wiki->get_setting('sub_wiki_name'); ?>" /></td>
</tr>
<tr valign="top">
	<th><label for="incsub_wiki-sub_wiki_order_by"><?php _e('How should Sub Wikis be ordered?', 'wiki'); ?></label> </th>
	<td>
		<select id="incsub_wiki-sub_wiki_order_by" name="wiki[sub_wiki_order_by]" >
			<option value="menu_order" <?php selected($this->wiki->get_setting('sub_wiki_order_by'), 'menu_order'); ?>><?php _e('Menu Order/Order Created', 'wiki'); ?></option>
			<option value="title" <?php selected($this->wiki->get_setting('sub_wiki_order_by'), 'title'); ?>><?php _e('Title', 'wiki'); ?></option>
			<option value="rand" <?php selected($this->wiki->get_setting('sub_wiki_order_by'), 'rand'); ?>><?php _e('Random', 'wiki'); ?></option>
		</select>
	</td>
</tr>
<tr valign="top">
	<th><label for="incsub_wiki-sub_wiki_order"><?php _e('What order should Sub Wikis be ordered?', 'wiki'); ?></label> </th>
	<td>
		<select id="incsub_wiki-sub_wiki_order" name="wiki[sub_wiki_order]" >
			<option value="ASC" <?php selected($this->wiki->get_setting('sub_wiki_order'), 'ASC'); ?>><?php _e('Ascending', 'wiki'); ?></option>
			<option value="DESC" <?php selected($this->wiki->get_setting('sub_wiki_order'), 'DESC'); ?>><?php _e('Descending', 'wiki'); ?></option>
		</select>
	</td>
</tr>
<tr valign="top">
	<th><label><?php _e('Who can edit wiki privileges?', 'wiki'); ?></label> </th>
	<td>
		<?php
		$editable_roles = get_editable_roles();
		foreach ($editable_roles as $role_key => $role) {
			$role_obj = get_role($role_key);
			?>
			<label><input type="checkbox" name="edit_wiki_privileges[<?php echo $role_key; ?>]" value="<?php echo $role_key; ?>" <?php echo $role_obj->has_cap('edit_wiki_privileges')?'checked="checked"':''; ?> /> <?php echo $role['name']; ?></label><br/>
			<?php
		}
		?>
	</td>
</tr>
	<?php
	}
	
	/**
	 * Saves additional settings
	 *
	 * @since 1.2.5
	 * @access public
	 * @param array $settings
	 * @param array $postdata
	 * @return array
	 */
	public function save_settings( $settings, $postdata ) {
		$settings['breadcrumbs_in_title'] = intval($_POST['wiki']['breadcrumbs_in_title']);
		$settings['wiki_name'] = $_POST['wiki']['wiki_name'];
		$settings['sub_wiki_name'] = $_POST['wiki']['sub_wiki_name'];
		$settings['sub_wiki_order_by'] = $_POST['wiki']['sub_wiki_order_by'];
		$settings['sub_wiki_order'] = $_POST['wiki']['sub_wiki_order'];
		return $settings;
	}
	
	/**
	 * Adds meta boxes
	 *
	 * @since 1.2.5
	 * @access public
	 * @param object $post
	 */
	public function add_meta_boxes( $post ) {
		if ( $post->post_author == wp_get_current_user()->ID || current_user_can('edit_posts') ) {
			add_meta_box('incsub-wiki-privileges', __('Wiki Privileges', 'wiki'), array(&$this, 'privileges_meta_box'), 'incsub_wiki', 'side');
		}
	}
	
	/**
	 * Displays the privileges meta box
	 *
	 * @since 1.2.5
	 * @access public
	 * @param object $post
	 * @param bool $echo
	 */
	public function privileges_meta_box( $post, $echo = true ) {
		$settings = get_option('wiki_settings');
		$content	= '';
		$current_privileges = (array) get_post_meta($post->ID, 'incsub_wiki_privileges', true);
		$privileges = array(
			'anyone' => __('Anyone', 'wiki'),
			'network' => __('Network users', 'wiki'),
			'site' => __('Site users', 'wiki'),
			'edit_posts' => __('Users who can edit posts in this site', 'wiki')
			);
		
		$content .= '<input type="hidden" name="incsub_wiki_privileges_meta" value="1" />';
		$content .= '<div class="alignleft">';
		$content .= '<b>'. __('Allow editing by', 'wiki').'</b><br/>';
		
		foreach ( $privileges as $key => $privilege ) {
			$content .= '<label class="incsub_wiki_label_roles"><input type="checkbox" name="incsub_wiki_privileges[]" value="'.$key.'" '.((in_array($key, $current_privileges))?'checked="checked"':'').' /> '.$privilege.'</label><br class="incsub_wiki_br_roles"/>';
		}
		
		$content .= '</div>';
		$content .= '<div class="clear"></div>';
		
		if ( $echo ) {
			echo $content;
		}
		
		return $content;
	}
	
	/**
	 * Saves the wiki's meta info
	 *
	 * @since 1.2.5
	 * @access public
	 * @action wp_insert_post
	 * @param int $post_id 
	 * @param object $post
	 */
	public function save_wiki_meta( $post_id, $post = null ) {
		//skip quick edit
		if ( defined('DOING_AJAX') && DOING_AJAX ) { return; }

		if ( get_post_type($post_id) == "incsub_wiki" && isset($_POST['incsub_wiki_tags']) ) {
			$wiki_tags = $_POST['incsub_wiki_tags'];
			
			wp_set_post_terms($post_id, $wiki_tags, 'incsub_wiki_tag');
			
			//for any other plugin to hook into
			do_action( 'incsub_wiki_save_taxonomy_tags', $post_id, $wiki_tags );
		}
		
		if ( get_post_type($post_id) == "incsub_wiki" && isset($_POST['incsub_wiki_category']) ) {
			$wiki_category = array( (int) $_POST['incsub_wiki_category'] );
			
			wp_set_post_terms( $post_id, $wiki_category, 'incsub_wiki_category' );
			
			//for any other plugin to hook into
			do_action('incsub_wiki_save_taxonomy_category', $post_id, $wiki_category);
		}
			
		if ( get_post_type($post_id) == "incsub_wiki" && isset($_POST['incsub_wiki_privileges']) ) {
			$meta = get_post_custom($post_id);
			
			update_post_meta($post_id, 'incsub_wiki_privileges', $_POST['incsub_wiki_privileges']);
			
			//for any other plugin to hook into
			do_action( 'incsub_wiki_save_privileges_meta', $post_id, $meta );
		}		
	}
	
	/**
	 * Displays the wiki taxonomies dropdown
	 *
	 * @since 1.2.5
	 * @access public
	 * @param bool $echo
	 */

	public function wiki_taxonomies( $echo = true ) {
		global $post, $edit_post;
		
		$wiki = isset($post) ? $post : $edit_post;
		$wiki_tags = wp_get_object_terms( $wiki->ID, 'incsub_wiki_tag', array( 'fields' => 'names' ) );

		$wiki_cats = wp_get_object_terms( $wiki->ID, 'incsub_wiki_category', array( 'fields' => 'ids' ) );
		$wiki_cat = empty( $wiki_cats ) ? false : reset( $wiki_cats );
		
		$content	= '';
		$content .= '<table id="wiki-taxonomies">';
		$content .= '<tr>';
		$content .= '<td id="wiki-category-td">';
		$content .= wp_dropdown_categories( array(
						'orderby' => 'name',
						'order' => 'ASC',
						'taxonomy' => 'incsub_wiki_category',
						'selected' => $wiki_cat,
						'hide_empty' => false,
						'hierarchical' => true,
						'name' => 'incsub_wiki_category',
						'class' => '',
						'echo' => false,
						'show_option_none' => __( 'Select category...', 'wiki')
					) );
		$content .= '</td>';
		$content .= '<td id="wiki-tags-label">';
		$content .= '<label for="wiki-tags">'.__('Tags:', 'wiki').'</label>';
		$content .= '</td>';
		$content .= '<td id="wiki-tags-td">';
		$content .= '<input type="text" id="incsub_wiki-tags" name="incsub_wiki_tags" value="'. implode( ', ', $wiki_tags ).'" />';
		$content .= '</td></tr></table>';
		
		if ( $echo ) {
			echo $content;
		}
		
		return $content;
	}
	
	/**
	 * Initializes widgets
	 *
	 * @since 1.2.5
	 * @access public
	 */
	public function widgets_init() {
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/SearchWikisWidget.php';
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/NewWikisWidget.php';
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/PopularWikisWidget.php';
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/WikiCategoriesWidget.php';
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/WikiTagsWidget.php';
		include_once $this->wiki->plugin_dir . 'premium/lib/classes/WikiTagCloudWidget.php';
		
		register_widget('SearchWikisWidget');
		register_widget('NewWikisWidget');
		register_widget('PopularWikisWidget');
		register_widget('WikiCategoriesWidget');
		register_widget('WikiTagsWidget');
		register_widget('WikiTagCloudWidget');
	}
	
	/**
	 * Modifies the term link
	 *
	 * @since 1.2.5
	 * @access public
	 * @param string $termlink
	 * @param object $term
	 * @param string $taxonomy
	 * @return string
	 */
	public function term_link( $termlink, $term, $taxonomy ) {
		$rewritecode = array(
			'%incsub_wiki_category%',
			'%incsub_wiki_tag%'
		);
		
		if ( preg_match('/^incsub_wiki_/', $term->taxonomy) > 0 && '' != $termlink ) {
			$rewritereplace = array(
				($term->slug == "") ? (isset($term->term_id) ? $term->term_id : 0) : $term->slug,
				($term->slug == "") ? (isset($term->term_id) ? $term->term_id : 0) : $term->slug
			);
			$termlink = str_replace($rewritecode, $rewritereplace, $termlink);
		}
		
		return $termlink;
	}


	/**
	 * Constructor function
	 *
	 * @since 1.2.5
	 * @access private
	 */
	private function __construct() {
		$this->wiki = Wiki::get_instance();
		
		add_filter('wiki_save_settings', array(&$this, 'save_settings'), 10, 2);
		add_filter('term_link', array(&$this, 'term_link'), 10, 3);		
		add_action('add_meta_boxes_incsub_wiki', array(&$this, 'add_meta_boxes'));
		add_action('wp_insert_post', array(&$this, 'save_wiki_meta'), 10, 2);
		add_action('widgets_init', array(&$this, 'widgets_init'));
	}
}

Wiki_Premium::get_instance();