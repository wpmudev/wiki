<?php

class NewWikisWidget extends WP_Widget {
		function __construct() {
			global $wiki;
			
			$widget_ops = array( 'description' => __('Display New Wiki Pages', 'wiki') );
			$control_ops = array( 'title' => __('New Wikis', 'wiki'), 'hierarchical' => 'yes' );
				
			parent::WP_Widget( 'incsub_new_wikis', __('New Wikis', 'wiki'), $widget_ops, $control_ops );
		}
		
		function widget($args, $instance) {
		global $wpdb, $current_site, $post, $wiki_tree, $wiki;
		
		extract($args);
		
		$options = $instance;
		
		$title = apply_filters('widget_title', empty($instance['title']) ? __('New Wikis', 'wiki') : $instance['title'], $instance, $this->id_base);
		$hierarchical = $instance['hierarchical'];
		
		if ($hierarchical == 'yes') {
			$hierarchical = 0;
		} else if ($hierarchical == 'no') {
			$hierarchical = 1;
		}
		
		?>
		<?php echo $before_widget; ?>
		<?php echo $before_title . $title . $after_title; ?>
		<?php
			$wiki_posts = get_posts(
					array(
					'post_parent' => 0,
					'post_type' => 'incsub_wiki',
					'orderby' => 'post_date',
					'order' => 'DESC',
					'numberposts' => 100000
					));
		?>
			<ul>
			<?php
			foreach ($wiki_posts as $wiki) {
			?>
				<li><a href="<?php print get_permalink($wiki->ID); ?>" class="<?php print ($wiki->ID == $post->ID)?'current':''; ?>" ><?php print $wiki->post_title; ?></a>
				<?php ($hierarchical == 0 || $hierarchical > 1)?$this->_print_sub_wikis($wiki, $hierarchical, 2):''; ?>
				</li>
			<?php
			}
			?>
			</ul>
			<br />
			<?php echo $after_widget; ?>
		<?php
		}
		
		function _print_sub_wikis($wiki, $level, $current_level) {
		global $post;
		
		$sub_wikis = get_posts(
				array('post_parent' => $wiki->ID,
						 'post_type' => 'incsub_wiki',
						 'orderby' => 'post_date',
						 'order' => 'DESC',
						 'numberposts' => 100000
				));
		?>
		<ul>
			<?php
			foreach ($sub_wikis as $sub_wiki) {
			?>
				<li><a href="<?php print get_permalink($sub_wiki->ID); ?>" class="<?php print ($sub_wiki->ID == $post->ID)?'current':''; ?>" ><?php print $sub_wiki->post_title; ?></a>
				<?php ($level == 0 || $level > $current_level)?$this->_print_sub_wikis($sub_wiki, $level, $current_level+1):''; ?>
				</li>
			<?php
			}
			?>
		</ul>
		<?php
		}
		
		function update($new_instance, $old_instance) {
			global $wiki;
			$instance = $old_instance;
			$new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('New Wikis', 'wiki'), 'hierarchical' => 'yes') );
			$instance['title'] = strip_tags($new_instance['title']);
			$instance['hierarchical'] = $new_instance['hierarchical'];
			return $instance;
		}
		
		function form($instance) {
			global $wiki;
		$instance = wp_parse_args( (array) $instance, array( 'title' => __('New Wikis', 'wiki'), 'hierarchical' => 'yes'));
		$options = array('title' => strip_tags($instance['title']), 'hierarchical' => $instance['hierarchical']);
		
		if ($options['hierarchical'] == 'yes') {
			$options['hierarchical'] = 0;
		} else if ($options['hierarchical'] == 'no') {
			$options['hierarchical'] = 1;
		}
		?>
		<div style="text-align:left">
				<label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Title', 'wiki'); ?>:<br />
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
				</label>
			<label for="<?php echo $this->get_field_id('hierarchical'); ?>" style="line-height:35px;display:block;"><?php _e('Levels', 'wiki'); ?>:<br />
					<select id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>" >
				<?php for ($i=1; $i<5; $i++) { ?>
				<option value="<?php echo $i; ?>" <?php if ($options['hierarchical'] == $i){ echo 'selected="selected"'; } ?> ><?php _e($i, 'wiki'); ?></option>
				<?php } ?>
				<option value="0" <?php if ($options['hierarchical'] == 0){ echo 'selected="selected"'; } ?> ><?php _e('Unlimited', 'wiki'); ?></option>
					</select>
				</label>
			<input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
		</div>
		<?php
		}
}

