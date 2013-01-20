<?php

class NewWikisWidget extends WP_Widget {
    
    /**
     * @var		string	$translation_domain	Translation domain
     */
    var $translation_domain = 'wiki';
    
    function __construct() {
	$widget_ops = array( 'description' => __('Display New Wiki Pages', $this->translation_domain) );
        $control_ops = array( 'title' => __('New Wikis', $this->translation_domain), 'hierarchical' => 'yes' );
        
	parent::WP_Widget( 'incsub_new_wikis', __('New Wikis', $this->translation_domain), $widget_ops, $control_ops );
    }
    
    function widget($args, $instance) {
	global $wpdb, $current_site, $post, $wiki_tree;
	
	extract($args);
	
	$options = $instance;
	
	$title = apply_filters('widget_title', empty($instance['title']) ? __('New Wikis', $this->translation_domain) : $instance['title'], $instance, $this->id_base);
	$hierarchical = $instance['hierarchical'];
	
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
			<?php print ($hierarchical == 'yes')?$this->_print_sub_wikis($wiki):''; ?>
		    </li>
		<?php
		}
		?>
	    </ul>
        <br />
        <?php echo $after_widget; ?>
	<?php
    }
    
    function _print_sub_wikis($wiki) {
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
		    <?php print $this->_print_sub_wikis($sub_wiki); ?>
	        </li>
	    <?php
		}
	    ?>
	</ul>
	<?php
    }
    
    function update($new_instance, $old_instance) {
	$instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => __('New Wikis', $this->translation_domain), 'hierarchical' => 'yes') );
        $instance['title'] = strip_tags($new_instance['title']);
	$instance['hierarchical'] = $new_instance['hierarchical'];
	
        return $instance;
    }
    
    function form($instance) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => __('New Wikis', $this->translation_domain), 'hierarchical' => 'yes'));
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

