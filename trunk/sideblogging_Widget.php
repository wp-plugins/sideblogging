<?php
// Sidebar widget
class SideBlogging_Widget extends WP_Widget {

	private $options;

    public function SideBlogging_Widget() {
        $widget_ops = array(
            'classname' => 'widget_sideblogging',
            'description' => __('Display asides in a widget',Sideblogging::domain)
        );
        $this->WP_Widget(false, 'SideBlogging',$widget_ops);
    }

    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $number = isset($instance['number']) ? intval($instance['number']) : '';
        if($number <= 0) $number = 5;
		$displayimg = isset($instance['displayimg']) ? $instance['displayimg'] : false;
		$linktitle = isset($instance['linktitle']) ? $instance['linktitle'] : false;
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
		echo '<label for="'.$this->get_field_id('displayimg').'">';
		echo '<input '.checked($displayimg,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('displayimg').'" id="'.$this->get_field_id('displayimg').'" />';
		echo ' '.__('Link on an image after the title',Sideblogging::domain).'</label>';
		echo '<br /><label for="'.$this->get_field_id('linktitle').'">';
		echo '<input '.checked($linktitle,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('linktitle').'" id="'.$this->get_field_id('linktitle').'" />';
		echo ' '.__('Asides are clickable (if content is not empty)',Sideblogging::domain).'</label>';
		echo '<br /><label for="'.$this->get_field_id('displayrss').'">';
		echo '<input '.checked($displayrss,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('displayrss').'" id="'.$this->get_field_id('displayrss').'" />';
		echo ' '.__('Show a link to a RSS feed',Sideblogging::domain).'</label>';
		echo '<br /><label for="'.$this->get_field_id('displayarchive').'">';
		echo '<input '.checked($displayarchive,true,false).' class="checkbox" type="checkbox" name="'.$this->get_field_name('displayarchive').'" id="'.$this->get_field_id('displayarchive').'" />';
		echo ' '.__('Show a link to an archive page',Sideblogging::domain).'</label>';
		echo '</p>';
    }
    
    /** @see WP_Widget::update */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = intval($new_instance['number']);
        $instance['displayimg'] = isset($new_instance['displayimg']) ? true : false;
        $instance['linktitle'] = isset($new_instance['linktitle']) ? true : false;
        $instance['displayrss'] = isset($new_instance['displayrss']) ? true : false;
        $instance['displayarchive'] = isset($new_instance['displayarchive']) ? true : false;
        return $instance;
    }
	
	public function getOptions() {
		if(empty($this->options))
			$this->options = get_option('sideblogging');
		
		return $this->options;
	}
	
	public function getSlug() {
		$options = $this->getOptions();
		if(isset($options['slug']) && !empty($options['slug']))
			return $options['slug'];
		else
			return 'asides';
	}

    public function widget($args, $instance) {
        // outputs the content of the widget
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        $number = intval($instance['number']);
        
        if(empty($title))
            $title = __('Asides',Sideblogging::domain);
        if($number <= 0)
            $number = 5;
			
		$displayimg = isset($instance['displayimg']) ? $instance['displayimg'] : false;
		$linktitle = isset($instance['linktitle']) ? $instance['linktitle'] : false;
		
		$displayrss = isset($instance['displayrss']) ? $instance['displayrss'] : false;
		$displayarchive = isset($instance['displayarchive']) ? $instance['displayarchive'] : false;
		
        echo $before_widget;
        echo $before_title;
		echo $title;
		if($displayrss) 
		{
			echo ' <a href="';
			if (get_option('permalink_structure') != '')
				echo get_bloginfo('url').'/'.$this->getSlug().'/feed/';
			else
				echo get_bloginfo('rss2_url').'&amp;post_type=asides';
			echo '"><img src="'.SIDEBLOGGING_URL.'/images/rss.png" alt="RSS" title="RSS" /></a>';
		}
		echo $after_title;
	
		$asides = new WP_Query('post_type=asides&posts_per_page='.$number.'&orderby=date&order=DESC');
       		
	   //The Loop
        if ($asides->have_posts())
        {
			if($displayimg)
			{
				$options = get_option('sideblogging');
				if($options['imagedir'] != '')
					$imagedir = $options['imagedir'];
				else
					$imagedir = SIDEBLOGGING_URL.'/images/';
			}
		
            echo '<ul>';
            while ($asides->have_posts())
            {
                $asides->the_post();
                $content = get_the_content();
                $title = get_the_title();

                echo '<li>';
				
				if($linktitle && strlen($content) > 0)
					echo '<a href="'.get_permalink().'">'.$title.'</a>';
				else
				{
					// Si le titre n'est pas un lien, on effectue quelques remplacements à l'intérieur
					$title = preg_replace('#https?://([a-zA-Z0-9-_./\?=&]+)#i', '<a href="$0">$0</a>', $title);
					$title = preg_replace('#@([a-zA-Z0-9-_]+)#i', '<a href="http://twitter.com/$1">$0</a>', $title);
					echo $title;
				}

                if($displayimg && strlen(strip_tags(trim($content),'<img><audio><video><embed><object>')) > 0)
                {
                    if(preg_match('#youtube.com|dailymotion.com|wat.tv|.flv|&lt;video|<video#',$content)) {
                        $image = 'video.gif';
                        $alt = '*';
                    } else if(preg_match('#.mp3|.ogg|&lt;audio|<audio#',$content)) {
                        $image = 'music.gif';
                        $alt = '*';
                    } else if(preg_match('#&lt;embed|&lt;object|<embed|<object#',$content)) {
                        $image = 'video.gif';
                        $alt = '*';
                    } else if(preg_match('#&lt;img|<img#',$content)) {
                        $image = 'image.gif';
                        $alt = '*';
                    } else {
                        $image = 'other.gif';
                        $alt = '*';
                    }
					
                    echo ' <a href="'.get_permalink().'">
                    <img src="'.$imagedir.$image.'" alt="'.$alt.'" title="'.$alt.'" />
                    </a>';
                }
                echo '</li>';
            }
            echo '</ul>';
			
			if($displayarchive) 
			{				
				echo '<p class="sideblogging_more"><a href="';
				if (get_option('permalink_structure') != '')
					echo get_bloginfo('url').'/'.$this->getSlug().'/';
				else
					echo get_bloginfo('url').'?post_type=asides';
				echo '">'.__('More',Sideblogging::domain).' &raquo;</a></p>';
			}
        }
		
		
        echo $after_widget;
    }
}
?>