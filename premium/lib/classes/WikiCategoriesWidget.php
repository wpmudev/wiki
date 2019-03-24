<?php

class WikiCategoriesWidget extends WP_Widget {    
    function __construct() {
	$widget_ops = array( 'description' => __('Display Wiki Categories', 'wiki') );
        $control_ops = array( 'title' => __('Wiki Categories', 'wiki'), 'hierarchical' => 'yes' );
        
	parent::__construct( 'incsub_wiki_categories', __('Wiki Categories', 'wiki'), $widget_ops, $control_ops );
    }
    
    function widget($args, $instance) {
	global $wpdb, $current_site, $post, $wiki_tree;
	
	extract($args);
	
	$options = $instance;
	
	$title = apply_filters('widget_title', empty($instance['title']) ? __('Wiki Categories', 'wiki') : $instance['title'], $instance, $this->id_base);
	
	?>
	<?php echo $before_widget; ?>
	<?php echo $before_title . $title . $after_title; ?>
	<ul>
	    <?php echo wp_list_categories('taxonomy=incsub_wiki_category&title_li='); ?>
	</ul>
        <?php echo $after_widget; ?>
	<?php
    }
    
    function update($new_instance, $old_instance) {
	$instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('Wiki Categories', 'wiki') ) );
        $instance['title'] = strip_tags($new_instance['title']);
	
        return $instance;
    }
    
    function form($instance) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => __('Wiki Categories', 'wiki') ) );
        $options = array('title' => strip_tags($instance['title']) );
	
	?>
	<div style="text-align:left">
            <label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Title', 'wiki'); ?>:<br />
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
	    <input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
	</div>
	<?php
    }
}

