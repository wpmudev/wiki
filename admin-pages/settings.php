<?php

class Wiki_Admin_Page_Settings {
	function __construct() {
		$this->maybe_save_settings();
		add_action('admin_menu', array(&$this, 'admin_menu'));
	}
	
	/**
	 * Adds the admin menus
	 * 
	 * @see		http://codex.wordpress.org/Adding_Administration_Menus
	 */
	function admin_menu() {
		$page = add_submenu_page('edit.php?post_type=incsub_wiki', __('Wiki Settings', 'wiki'), __('Wiki Settings', 'wiki'), 'manage_options', 'incsub_wiki', array(&$this, 'display_settings'));
	}
	
	function display_settings() {
		global $wiki;
		
		if ( ! current_user_can('manage_options') )
			wp_die(__('You do not have permission to access this page', 'wiki'));	//If accessed properly, this message doesn't appear.
		
		if ( isset($_GET['incsub_wiki_settings_saved']) && $_GET['incsub_wiki_settings_saved'] == 1 )
			echo '<div class="updated fade"><p>'.__('Settings saved.', 'wiki').'</p></div>';
		?>
		<div class="wrap">
			<h2><?php _e('Wiki Settings', 'wiki'); ?></h2>
			<form method="post" action="edit.php?post_type=incsub_wiki&amp;page=incsub_wiki">
			<?php wp_nonce_field('wiki_save_settings', 'wiki_settings_nonce'); ?>
			<table class="form-table">
				<tr valign="top">
					<th><label for="incsub_wiki-slug"><?php _e('Wiki Slug', 'wiki'); ?></label> </th>
					<td> /<input type="text" size="20" id="incsub_wiki-slug" name="wiki[slug]" value="<?php echo $wiki->get_setting('slug'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th><label for="incsub_wiki-breadcrumbs_in_title"><?php _e('Number of breadcrumbs to add to title', 'wiki'); ?></label> </th>
					<td><input type="text" size="2" id="incsub_wiki-breadcrumbs_in_title" name="wiki[breadcrumbs_in_title]" value="<?php echo $wiki->get_setting('breadcrumbs_in_title'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th><label for="incsub_wiki-wiki_name"><?php _e('What do you want to call Wikis?', 'wiki'); ?></label> </th>
					<td><input type="text" size="20" id="incsub_wiki-wiki_name" name="wiki[wiki_name]" value="<?php echo $wiki->get_setting('wiki_name'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th><label for="incsub_wiki-sub_wiki_name"><?php _e('What do you want to call Sub Wikis?', 'wiki'); ?></label> </th>
					<td><input type="text" size="20" id="incsub_wiki-sub_wiki_name" name="wiki[sub_wiki_name]" value="<?php echo $wiki->get_setting('sub_wiki_name'); ?>" /></td>
				</tr>
				<tr valign="top">
					<th><label for="incsub_wiki-sub_wiki_order_by"><?php _e('How should Sub Wikis be ordered?', 'wiki'); ?></label> </th>
					<td><select id="incsub_wiki-sub_wiki_order_by" name="wiki[sub_wiki_order_by]" >
						<option value="menu_order" <?php selected($wiki->get_setting('sub_wiki_order_by', 'menu_order')); ?>><?php _e('Menu Order/Order Created', 'wiki'); ?></option>
						<option value="title" <?php selected($wiki->get_setting('sub_wiki_order_by', 'title')); ?>><?php _e('Title', 'wiki'); ?></option>
						<option value="rand" <?php selected($wiki->get_setting('sub_wiki_order_by', 'rand')); ?>><?php _e('Random', 'wiki'); ?></option>
							 </select></td>
				</tr>
				<tr valign="top">
					<th><label for="incsub_wiki-sub_wiki_order"><?php _e('What order should Sub Wikis be ordered?', 'wiki'); ?></label> </th>
					<td><select id="incsub_wiki-sub_wiki_order" name="wiki[sub_wiki_order]" >
						<option value="ASC" <?php selected($wiki->get_setting('sub_wiki_order', 'ASC')); ?>><?php _e('Ascending', 'wiki'); ?></option>
						<option value="DESC" <?php selected($wiki->get_setting('sub_wiki_order', 'DESC')); ?>><?php _e('Descending', 'wiki'); ?></option>
							 </select></td>
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
			</table>
			
			<p class="submit">
			<input type="submit" class="button-primary" name="submit_settings" value="<?php _e('Save Changes', 'wiki') ?>" />
			</p>
		</form>
		<?php			
	}
	
	function maybe_save_settings() {
		global $wiki;
		
		if ( isset($_POST['wiki_settings_nonce']) ) {
			check_admin_referer('wiki_save_settings', 'wiki_settings_nonce');
			
			$new_slug = untrailingslashit($_POST['wiki']['slug']);
			
			if ( $wiki->get_setting('slug') != $new_slug )
				update_option('wiki_flush_rewrites', 1);
		
			$wiki->settings['slug'] = $new_slug;
			$wiki->settings['breadcrumbs_in_title'] = intval($_POST['wiki']['breadcrumbs_in_title']);
			$wiki->settings['wiki_name'] = $_POST['wiki']['wiki_name'];
			$wiki->settings['sub_wiki_name'] = $_POST['wiki']['sub_wiki_name'];
			$wiki->settings['sub_wiki_order_by'] = $_POST['wiki']['sub_wiki_order_by'];
			$wiki->settings['sub_wiki_order'] = $_POST['wiki']['sub_wiki_order'];
			update_option('wiki_settings', $wiki->settings);
			
			if ( !function_exists('get_editable_roles') )
				require_once ABSPATH . 'wp-admin/includes/user.php';
			
			$roles = get_editable_roles();
			
			foreach ( $roles as $role_key => $role ) {
				$role_obj = get_role($role_key);
				if ( isset($_POST['edit_wiki_privileges'][$role_key]) )
					$role_obj->add_cap('edit_wiki_privileges');
				else
					$role_obj->remove_cap('edit_wiki_privileges');
			}
				
			wp_redirect('edit.php?post_type=incsub_wiki&page=incsub_wiki&incsub_wiki_settings_saved=1');
			exit;
		}
	}
}

new Wiki_Admin_Page_Settings();
