<?php
global $blog_id, $wp_query, $wiki, $post, $current_user;
get_header( 'wiki' );
?>
<div id="primary" class="wiki-primary-event">
    <div id="content">
        <div class="padder">
            <div id="wiki-page-wrapper">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                
                <div class="incsub_wiki incsub_wiki_single">
                    <?php _e('Wiki page you are looking for does not exist. Feel free to create it yourself.', $wiki->translation_domain); ?>
                    <div class="incsub_wiki_tabs incsub_wiki_tabs_top"><div class="incsub_wiki_clear"></div></div>
                </div>
                <?php
                echo $wiki->get_new_wiki_form();
                ?>
            </div>
        </div>
    </div>
    
    <?php get_sidebar('wiki'); ?>
</div>
<?php get_footer('wiki'); ?>
