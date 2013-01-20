<?php

class WikiWidget extends WP_Widget {
    
    /**
     * @var		string	$translation_domain	Translation domain
     */
    var $translation_domain = 'wiki';
    
    function __construct() {
	global $wiki;
	
	$widget_ops = array( 'description' => __('Display Wiki Pages', $this->translation_domain) );
        $control_ops = array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes', 'order_by' => $wiki->_options['default']['sub_wiki_order_by'], 'order' => $wiki->_options['default']['sub_wiki_order'] );
        
	parent::WP_Widget( 'incsub_wiki', __('Wiki', $this->translation_domain), $widget_ops, $control_ops );
    }
    
    function widget($args, $instance) {
	global $wpdb, $current_site, $post, $wiki_tree;
	
	extract($args);
	
	$options = $instance;
	
	$title = apply_filters('widget_title', empty($instance['title']) ? __('Wiki', $this->translation_domain) : $instance['title'], $instance, $this->id_base);
	$hierarchical = $instance['hierarchical'];
	$order_by = $instance['order_by'];
	$order = $instance['order'];
	
	?>
	<?php echo $before_widget; ?>
	<?php echo $before_title . $title . $after_title; ?>
	<?php
	    $wiki_posts = get_posts(
			    array(
				'post_parent' => 0,
				'post_type' => 'incsub_wiki',
				'orderby' => $order_by,
				'order' => $order,
				'numberposts' => 100000
			    ));
	?>
	    <ul>
		<?php
		foreach ($wiki_posts as $wiki) {
		?>
		    <li><a href="<?php print get_permalink($wiki->ID); ?>" class="<?php print ($wiki->ID == $post->ID)?'current':''; ?>" ><?php print $wiki->post_title; ?></a>
			<?php print ($hierarchical == 'yes')?$this->_print_sub_wikis($wiki, $order_by, $order):''; ?>
		    </li>
		<?php
		}
		?>
	    </ul>
        <br />
        <?php echo $after_widget; ?>
	<?php
    }
    
    function _print_sub_wikis($wiki, $order_by, $order) {
	global $post;
	
	$sub_wikis = get_posts(
			array('post_parent' => $wiki->ID,
			      'post_type' => 'incsub_wiki',
			      'orderby' => $order_by,
			      'order' => $order,
			      'numberposts' => 100000
			));
	?>
	<ul>
	    <?php
		foreach ($sub_wikis as $sub_wiki) {
	    ?>
	        <li><a href="<?php print get_permalink($sub_wiki->ID); ?>" class="<?php print ($sub_wiki->ID == $post->ID)?'current':''; ?>" ><?php print $sub_wiki->post_title; ?></a>
		    <?php print $this->_print_sub_wikis($sub_wiki, $order_by, $order); ?>
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
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes', 'order_by' => $wiki->_options['default']['sub_wiki_order_by'], 'order' => $wiki->_options['default']['sub_wiki_order']) );
        $instance['title'] = strip_tags($new_instance['title']);
	$instance['hierarchical'] = $new_instance['hierarchical'];
	$instance['order_by'] = $new_instance['order_by'];
	$instance['order'] = $new_instance['order'];
	
        return $instance;
    }
    
    function form($instance) {
	global $wiki;
	
	$instance = wp_parse_args( (array) $instance, array( 'title' => __('Wiki', $this->translation_domain), 'hierarchical' => 'yes', 'order_by' => $wiki->_options['default']['sub_wiki_order_by'], 'order' => $wiki->_options['default']['sub_wiki_order']));
        $options = array('title' => strip_tags($instance['title']), 'hierarchical' => $instance['hierarchical'], 'order_by' => $instance['order_by'], 'order' => $instance['order']);
	
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
	    <label for="<?php echo $this->get_field_id('order_by'); ?>" style="line-height:35px;display:block;"><?php _e('Order by', $this->translation_domain); ?>:<br />
                <select id="<?php echo $this->get_field_id('order_by'); ?>" name="<?php echo $this->get_field_name('order_by'); ?>" >
		    <option value="menu_order" <?php if ($options['order_by'] == 'menu_order'){ echo 'selected="selected"'; } ?> ><?php _e('Menu Order/Order Created', $this->translation_domain); ?></option>
		    <option value="title" <?php if ($options['order_by'] == 'title'){ echo 'selected="selected"'; } ?> ><?php _e('Title', $this->translation_domain); ?></option>
		    <option value="rand" <?php if ($options['order_by'] == 'rand'){ echo 'selected="selected"'; } ?> ><?php _e('Random', $this->translation_domain); ?></option>
                </select>
            </label>
	    <label for="<?php echo $this->get_field_id('order'); ?>" style="line-height:35px;display:block;"><?php _e('Order', $this->translation_domain); ?>:<br />
                <select id="<?php echo $this->get_field_id('order'); ?>" name="<?php echo $this->get_field_name('order'); ?>" >
		    <option value="ASC" <?php if ($options['order'] == 'ASC'){ echo 'selected="selected"'; } ?> ><?php _e('Ascending', $this->translation_domain); ?></option>
		    <option value="DESC" <?php if ($options['order'] == 'DESC'){ echo 'selected="selected"'; } ?> ><?php _e('Descending', $this->translation_domain); ?></option>
                </select>
            </label>
	    <input type="hidden" name="wiki-submit" id="wiki-submit" value="1" />
	</div>
	<?php
    }
}

