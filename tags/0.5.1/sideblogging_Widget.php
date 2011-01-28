<?php
// Sidebar widget
class SideBlogging_Widget extends WP_Widget {

    function SideBlogging_Widget() {
        $widget_ops = array(
            'classname' => 'widget_sideblogging',
            'description' => __('Display asides in a widget',Sideblogging::domain)
        );
        $this->WP_Widget(false, 'SideBlogging',$widget_ops);
    }

    function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $number = isset($instance['number']) ? intval($instance['number']) : '';
        if($number <= 0) $number = 5;
		$displayrss = isset($instance['displayrss']) ? $instance['displayrss'] : false;
		$displayarchive = isset($instance['displayarchive']) ? $instance['displayarchive'] : false;
            
        echo '<p>';
        echo '<label for="'.$this->get_field_id('title').'">';
        echo __('Title:').' <input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.$title.'" />';
        echo '</label><br />';
        
        echo '<label for="'.$this->get_field_id('number').'">';
        _e('Number of asides to display:',Sideblogging::domain);
        echo ' <select name="'.$this->get_field_name('number').'" id="'.$this->get_field_id('number').'" class="widefat">';
        foreach(range(1,20) as $n)
            echo '<option value="'.$n.'" '.selected($number,$n,false).'>'.$n.'</option>';
        echo '</select></label></p>';
		echo '<p>';
		echo '<label for="'.$this->get_field_id('displayrss').'">';
		echo '<input '.checked($displayrss,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('displayrss').'" id="'.$this->get_field_id('displayrss').'" />';
		echo ' '.__('Display a link to a RSS feed',Sideblogging::domain).'</label>';
		echo '<br /><label for="'.$this->get_field_id('displayarchive').'">';
		echo '<input '.checked($displayarchive,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('displayarchive').'" id="'.$this->get_field_id('displayarchive').'" />';
		echo ' '.__('Display a link to an archive page',Sideblogging::domain).'</label>';
		echo '</p>';
    }
    
    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = intval($new_instance['number']);
        $instance['displayrss'] = isset($new_instance['displayrss']) ? true : false;
        $instance['displayarchive'] = isset($new_instance['displayarchive']) ? true : false;
        return $instance;
    }

    function widget($args, $instance) {
        // outputs the content of the widget
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        $number = intval($instance['number']);
        
        if(empty($title))
            $title = __('Asides',Sideblogging::domain);
        if($number <= 0)
            $number = 5;
			
		$displayrss = isset($instance['displayrss']) ? $instance['displayrss'] : false;
		$displayarchive = isset($instance['displayarchive']) ? $instance['displayarchive'] : false;
		
        echo $before_widget;
        echo $before_title;
		echo $title;
		if($displayrss == true) 
		{
			echo ' <a href="';
			if (get_option('permalink_structure') != '')
				echo get_bloginfo('url').'/asides/feed/';
			else
				echo get_bloginfo('rss2_url').'&amp;post_type=asides';
			echo '"><img src="'.SIDEBLOGGING_URL.'/images/rss.png" alt="RSS" title="RSS" /></a>';
		}
		echo $after_title;
	
		$asides = new WP_Query('post_type=asides&posts_per_page='.$number.'&orderby=date&order=DESC');
       		
	   //The Loop
        if ($asides->have_posts())
        {
            echo '<ul>';
            while ( $asides->have_posts() )
            {
                $asides->the_post();
                $content = get_the_content();
                $title = get_the_title();
                $title = preg_replace('#http://([a-zA-Z0-9-_./\?=&]+)#i', '<a href="$0">$0</a>', $title);
                $title = preg_replace('#@([a-zA-Z0-9-_]+)#i', '<a href="http://twitter.com/$1">$0</a>', $title);

                echo '<li>'.$title;

                if(strlen(strip_tags(trim($content),'<img><audio><video><embed><object>')) > 0)
                {
                    if(preg_match('#youtube.com|dailymotion.com|wat.tv|.flv|&lt;video|<video#',$content))
                    {
                        $image = 'video.gif';
                        $alt = 'Lien vers la vidéo';
                    }
                    else if(preg_match('#.mp3|.ogg|&lt;audio|<audio#',$content))
                    {
                        $image = 'music.gif';
                        $alt = 'Lien vers le son';
                    }
                    else if(preg_match('#&lt;embed|&lt;object|<embed|<object#',$content))
                    {
                        $image = 'video.gif';
                        $alt = 'Lien vers la vidéo';
                    }
                    else if(preg_match('#&lt;img|<img#',$content))
                    {
                        $image = 'image.gif';
                        $alt = 'Lien vers l\'image';
                    }
                    else
                    {
                        $image = 'other.gif';
                        $alt = 'Lien vers la brève';
                    }
                    
                    echo ' <a href="'.get_permalink().'">
                    <img src="'.SIDEBLOGGING_URL.'/images/'.$image.'" alt="'.$alt.'" title="'.$alt.'" />
                    </a>';
                }
                echo '</li>';
            }
            echo '</ul>';
			
			if($displayarchive == true) 
			{
				echo '<p class="sideblogging_more"><a href="';
				if (get_option('permalink_structure') != '')
					echo get_bloginfo('url').'/asides/';
				else
					echo get_bloginfo('url').'?post_type=asides';
				echo '">'.__('More',Sideblogging::domain).' &raquo;</a></p>';
			}
        }
        echo $after_widget;
    }
}
?>