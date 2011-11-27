<?php
global $blog_id, $wp_query, $wiki, $post, $current_user;
get_header( 'wiki' );
?>
<div id="primary" class="wiki-primary-event">
    <div id="content">
        <div class="padder">
            <div id="wiki-page-wrapper">
                <h2 class="pagetitle"><?php the_title(); ?></h2>
                
                <div class="incsub_wiki incsub_wiki_single">
                    <div class="incsub_wiki_tabs incsub_wiki_tabs_top"><?php echo $wiki->tabs(); ?><div class="incsub_wiki_clear"></div></div>
                </div>
                <?php 
                $revision_id = isset($_REQUEST['revision'])?absint($_REQUEST['revision']):0;
                $left        = isset($_REQUEST['left'])?absint($_REQUEST['left']):0;
                $right       = isset($_REQUEST['right'])?absint($_REQUEST['right']):0;
                $action      = isset($_REQUEST['action'])?$_REQUEST['action']:'view';
                
                if ($action == 'discussion') {
                   comments_template( '', true );
                } else {
                    echo $wiki->decider(apply_filters('the_content', $post->post_content), $action, $revision_id, $left, $right);
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php get_sidebar('wiki'); ?>
</div>
<?php get_footer('wiki'); ?>
