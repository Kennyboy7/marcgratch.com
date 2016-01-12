<div class="container">
	
    <div class="row" id="blog" >

	<?php if ( ( jaxlite_template('sidebar') == "left-sidebar" ) || ( jaxlite_template('sidebar') == "right-sidebar" ) ) : ?>
        
        <div class="<?php echo jaxlite_template('span') .' '. jaxlite_template('sidebar'); ?> blog"> 
       
        	<div class="row"> 
        
    <?php 
	
		endif; 
	
		if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

            <article <?php post_class(); ?> >
    
				<?php do_action('jaxlite_postformat'); ?>
        
                <div style="clear:both"></div>
            
			</article>
		
		<?php 
			
			endwhile; 

			if ( jaxlite_setting('jaxlite_infinitescroll_system') == "on" ) :
				
				do_action('jaxlite_infinitescroll_script','jaxlite_home'); 
						
			endif;

			else:  
			
		?>

			<article class="pin-article col-md-12" >
        
                <div class="article">
                        
                    <div class="post-article">
        
                        <h1><?php _e( 'Content not found',"jaxlite" ) ?></h1>           
                        
                        <p> <?php _e( 'No article found in this blog.',"jaxlite"); ?> </p>
        
                        <h2> <?php _e( 'What can i do?',"jaxlite" ) ?> </h2>           
        
                        <p> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php bloginfo('name') ?>"> <?php _e( 'Back to the homepage',"jaxlite"); ?> </a> </p>
                      
                        <p> <?php _e( 'Make a search, from the below form:',"jaxlite"); ?> </p>
                        
                        <section class="contact-form">
                        
                            <form method="get" id="searchform" action="<?php echo home_url( '/' ); ?>">
                                 <input type="text" value="<?php _e( 'Search', "jaxlite" ) ?>" name="s" id="s" onblur="if (this.value == '') {this.value = '<?php _e( 'Search', "jaxlite" ) ?>';}" onfocus="if (this.value == '<?php _e( 'Search', "jaxlite" ) ?>') {this.value = '';}" class="input-search"/>
                                 <input type="submit" id="searchsubmit" class="button-search" value="<?php _e( 'Search', "jaxlite" ) ?>" />
                            </form>
                            
                            <div class="clear"></div>
                            
                        </section>
        
                    </div>
         
               </div>
        
            </article>
	
		<?php endif; ?>
        
	<?php if ( ( jaxlite_template('sidebar') == "left-sidebar" ) || ( jaxlite_template('sidebar') == "right-sidebar" ) ) : ?>
        
        </div>
        
    </div>
        
    <?php endif; ?>
        
        <section id="sidebar" class="post-container col-md-4">
                
            <div class="sidebar-box">
    
                <?php 
                        
                    if ( is_active_sidebar('side-sidebar-area')) { 
                            
                        dynamic_sidebar('side-sidebar-area');
                            
                    } else { 
                                    
                        the_widget( 'WP_Widget_Archives','',
                        
                        array(  'before_widget' => '<div class="post-article widget-box">',
                                'after_widget'  => '</div>',
                                'before_title'  => '<div class="title-container"><h3 class="title">',
                                'after_title'   => '</h3></div>'
                        ));
                
                        the_widget( 'WP_Widget_Calendar',
                        array("title"=> __('Calendar',"jaxlite")),
                        array(  'before_widget' => '<div class="post-article widget-box">',
                                'after_widget'  => '</div>',
                                'before_title'  => '<div class="title-container"><h3 class="title">',
                                'after_title'   => '</h3></div>'
                        ));
                
                        the_widget( 'WP_Widget_Categories','',
                        array(  'before_widget' => '<div class="post-article widget-box">',
                                'after_widget'  => '</div>',
                                'before_title'  => '<div class="title-container"><h3 class="title">',
                                'after_title'   => '</h3></div>'
                        ));
                            
                    } 
                        
                ?>
    
            </div>
            
        </section>
    
    </div>

	<?php 

		do_action( 'jaxlite_pagination', 'home'); 
	
	?>

</div>