<?php
/*
 * Plugin Name: SideBlogging
 * Plugin URI: http://blog.boverie.eu/sideblogging-des-breves-sur-votre-blog/
 * Description: Display asides in a widget. They can automatically be published to Twitter, Facebook, and any Status.net installation (like identi.ca).
 * Version: 0.8.1
 * Author: Cédric Boverie
 * Author URI: http://www.boverie.eu/
 * Text Domain: sideblogging
*/
/* Copyright 2010-2011  Cédric Boverie  (email : ced@boverie.eu)
 * this program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * Along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Installation
register_activation_hook(__FILE__,array('Sideblogging','activation'));
// Désinstallation
register_deactivation_hook(__FILE__,array('Sideblogging','deactivation'));

if(!class_exists('Sideblogging')):
class Sideblogging {

	const domain = 'sideblogging';

	function Sideblogging() {
		define('SIDEBLOGGING_URL', WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)));		
		define('SIDEBLOGGING_OAUTH_CALLBACK', get_bloginfo('wpurl').'/wp-admin/options-general.php?page=sideblogging');
		load_plugin_textdomain(self::domain,false,dirname(plugin_basename(__FILE__)).'/languages/');
	
		// Register custom post type
		add_action('init', array(&$this,'post_type_asides'));
		
		// Rewrite rules
		add_filter('generate_rewrite_rules', array(&$this, 'custom_rewrite_rules'));
				
		// Register Widget
		include dirname(__FILE__).'/sideblogging_Widget.php';
		add_action('widgets_init', create_function('', 'return register_widget("SideBlogging_Widget");'));
		
		// Définir l'action que exécute la tâche planifiée
		add_action('sideblogging_cron', array(&$this,'cron'));
		
		if(is_admin())
		{
			// Register option settings
			add_action('admin_init', array(&$this,'register_settings'));
			// Register admin menu
			add_action('admin_menu', array(&$this,'menu'));
			// Header redirection
			add_action('init', array(&$this,'connect_to_oauth'));
			// Publish asides to Twitter
			add_action('publish_asides', array(&$this,'send_to_social'));
			
			// Dashboard Widget
			add_action('wp_dashboard_setup', array(&$this,'add_dashboard_widget'));
			add_action('admin_head-index.php', array(&$this,'dashboard_admin_js'));
			add_action('wp_ajax_sideblogging_widget_post', array(&$this,'ajax_action'));

		} else {
			// Asides in the main RSS Feed
			add_filter('request', array(&$this,'request'));
		}
	}
	
	function activation() {
		wp_schedule_event(time(), 'daily', 'sideblogging_cron');
		add_option('sideblogging',array());
		$options = get_option('sideblogging');
		if(empty($options['twitter_consumer_key']) || empty($options['twitter_consumer_secret']))
		{
			$options['twitter_consumer_key'] = '57iUTYmR4uTs8Qt4gDX9Ww';
			$options['twitter_consumer_secret'] = 'weDOZuv1dbaPffO8ZsEsVg25WPpugnIBAhlcsJeM';
		}
		if(empty($options['statusnet_url']) || empty($options['statusnet_consumer_key']) || empty($options['statusnet_consumer_secret']))
		{
			$options['statusnet_url'] = 'http://identi.ca';
			$options['statusnet_consumer_key'] = '60ba77cf5561e1f15fadeea3dc7bb021';
			$options['statusnet_consumer_secret'] = 'b39f9d55f380f42b96744f0143dd212d';
		}
		update_option('sideblogging',$options);
	}
	
	function deactivation() {
		wp_clear_scheduled_hook('sideblogging_cron');
		//delete_option('sideblogging');
	}
	
	function cron() {
		global $wpdb;
		$options = get_option('sideblogging');
		if(isset($options['purge']) && intval($options['purge']) != 0)
		{
			$purge = intval($options['purge']);
			$timestamp = time()-86400*$purge;
			$wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'asides' AND UNIX_TIMESTAMP(post_modified) <= $timestamp");
		}
	}
	
	function request($qv) {
		$options = get_option('sideblogging');
		if(isset($options['mainrss']) && intval($options['mainrss']) == 1) {
			if(isset($qv['feed']) && !isset($qv['post_type']))
				$qv['post_type'] = array('post', 'asides');
		}
		return $qv;
	}

	// Options page
	function menu() {
		global $sideblogging_options_page;
		$sideblogging_options_page = add_options_page('SideBlogging', ' SideBlogging', 'manage_options', 'sideblogging', array(&$this,'options_page'));
		add_action("load-$sideblogging_options_page", array(&$this,'help'));
		
		add_action("load-$sideblogging_options_page", array(&$this,'regenerate_rewrite_rules'));
	}
	
	function help() {
		global $sideblogging_options_page;
		$help = '<h5>'.__('Sideblogging help',self::domain).'</h5>';
		$help .= '<p><a target="_blank" href="http://twitter.com/apps/new">'.__('Create a Twitter application',self::domain).'</a> (<a target="_blank" href="http://www.youtube.com/watch?v=TEpR1M1R9nI">'.__('video tutorial',self::domain).'</a>)<br />';
		$help .= '<a target="_blank" href="http://www.facebook.com/developers/apps.php">'.__('Create a Facebook application',self::domain).'</a> (<a target="_blank" href="http://www.youtube.com/watch?v=0EH2WQdnvUg">'.__('video tutorial',self::domain).'</a>)<br />';
		$help .= '<a target="_blank" href="http://identi.ca/settings/oauthapps/new">'.__('Create a Identi.ca application',self::domain).'</a></p>';
		$help .= '<h5>'.__('Debug',self::domain).'</h5>';
		$help .= '<p><a href="options-general.php?page=sideblogging&amp;debug=1">'.__('Debug information',self::domain).'</a></p>';
		$facebook = '<h5>'.__('About Facebook',self::domain).'</h5>';
		$facebook .= '<p>'.__('For Facebook, you may need to modify the Site URL',self::domain).'.<br />
				'.__('To do:',self::domain).'</p>
				<ul>
				<li>'.__('Go to application settings',self::domain).'.</li>
				<li>'.__('Go to section <em>Web Site</em>',self::domain).'.</li>
				<li>'.sprintf(__('Put %s in the field Site URL',self::domain),'<strong>'.get_bloginfo('url').'/</strong>').'.</li>
				</ul>';

		// Help requires WP 3.3
		if(version_compare($GLOBALS['wp_version'], '3.3-RC1') >= 0) {
			$screen = get_current_screen();
			if($screen->id != $sideblogging_options_page)
				return;
				
			$screen->add_help_tab(array(
				'id' => 'sideblogging-help',
				'title' => 'Sideblogging',
				'content' => $help
			));
			$screen->add_help_tab(array(
				'id' => 'sideblogging-help-fb',
				'title' => 'Facebook',
				'content' => $facebook
			));
		} else {
			add_contextual_help($sideblogging_options_page, $help.$facebook);
		}
	}
	
	function add_dashboard_widget() {
		if(current_user_can('manage_options'))
			wp_add_dashboard_widget('sideblogging_dashboard_widget', __('Asides',self::domain), array(&$this,'dashboard_widget'));
	}
	
	function dashboard_widget() {
		echo '<form id="sideblogging_dashboard_form" action="" method="post" class="dashboard-widget-control-form">';
		wp_nonce_field('sideblogging-quickpost');
		echo '<div style="display:none;" id="sideblogging-status"></div>';
		echo '<textarea name="sideblogging-title" id="sideblogging-title" style="width:100%"></textarea><br />';
		echo '<span id="sideblogging-count">140</span> '.__('characters left',self::domain).'.<br />';
		echo '<input type="checkbox" name="sideblogging-draft" id="sideblogging-draft" />
		<label for="sideblogging-draft">'.__('Add additional content',self::domain).'.</label>';
		echo '</p>';
		echo '<p class="submit"><input type="submit" id="sideblogging-submit" class="button-primary" value="' . esc_attr__( 'Publish' ) . '" /> 
		<img style="display:none;" src="'.SIDEBLOGGING_URL.'/images/loading.gif" alt="Loading..." id="sideblogging-loading" /></p>';
		echo '</form>';
	}
	
	function dashboard_admin_js() {
		?>
		<script type="text/javascript" >
		jQuery(document).ready(function($) {
			$('#sideblogging_dashboard_form').submit(function() {
				$('#sideblogging-loading').show();
				$('#sideblogging-submit').attr('disabled','disabled');
				var data = {
					action: 'sideblogging_widget_post',
					_ajax_nonce: '<?php echo wp_create_nonce('sideblogging-quickpost'); ?>',
					form: $(this).serialize()
				};
				$.post(ajaxurl, data, function(response) {
					$('#sideblogging-loading').hide();
					if(response == 'ok')
					{
						$('#sideblogging-title').val('');
						$('#sideblogging-status').html('<p><?php _e('Aside published',self::domain); ?>.</p>')
						.addClass('updated').removeClass('error')
						.show(200);
					}
					else if(!isNaN(response) && response != 0)
					{
						location.href = 'post.php?action=edit&post='+response;
					}
					else
					{
						$('#sideblogging-status').html('<p><?php _e('An error occurred',self::domain); ?>.</p>')
						.addClass('error').removeClass('updated')
						.show(200);
					}
					$('#sideblogging-submit').removeAttr('disabled');
				});
				return false;
			});
			
			$('#sideblogging-title').keyup(function(e) {
				$('#sideblogging-title').val($('#sideblogging-title').val().substring(0, 140));
				var count = $('#sideblogging-title').val().length;
				var restant = 140 - count;
				$('#sideblogging-count').html(restant);
			});
		});
		</script>
		<?php
	}
	
	function ajax_action() {
		parse_str($_POST['form'], $form);
		check_ajax_referer('sideblogging-quickpost');
		
		$post['post_title'] = strip_tags(stripslashes($form['sideblogging-title']));
		$post['post_status'] = (isset($form['sideblogging-draft'])) ? 'draft' : 'publish';
		$post['post_type'] = 'asides';

		if(empty($post['post_title']))
		{
			echo '0';
			exit;
		}
		
		$id = wp_insert_post($post);
		
		if($post['post_status'] == 'draft' && $id != 0) // Brouillon OK, donc redirection
			echo $id;
		else if($id  != 0) // Publication immédiate OK
			echo 'ok';
		else // Echec publication
			echo '0';
		exit;
	}

	/* Gère la redirection vers les pages de demande de connexion Oauth */
	function connect_to_oauth() {
		if(isset($_GET['action']) && $_GET['action'] == 'connect_to_twitter' && wp_verify_nonce($_GET['_wpnonce'],'connect_to_twitter')) // Twitter redirection
		{
			require_once('libs/twitteroauth.php');
			$options = get_option('sideblogging');
			$connection = new SidebloggingTwitterOAuth($options['twitter_consumer_key'], $options['twitter_consumer_secret']);
			$request_token = $connection->getRequestToken(SIDEBLOGGING_OAUTH_CALLBACK.'&site=twitter'); // Génère des notices en cas d'erreur de connexion
			if(200 == $connection->http_code)
			{
				$token = $request_token['oauth_token'];
				set_transient('oauth_token', $token, 86400);
				set_transient('oauth_token_secret', $request_token['oauth_token_secret'], 86400);
				
				$url = $connection->getAuthorizeURL($token,false);
				wp_redirect($url.'&oauth_access_type=write');
			}
			else
				wp_die(__('Twitter is currently unavailable. Please check your keys or try again later.',self::domain));
		}
		else if(isset($_GET['action']) && $_GET['action'] == 'connect_to_facebook' && wp_verify_nonce($_GET['_wpnonce'],'connect_to_facebook')) // Facebook redirection
		{
			$options = get_option('sideblogging');
			$url = 'https://graph.facebook.com/oauth/authorize?client_id='.$options['facebook_consumer_key'].'&redirect_uri='.SIDEBLOGGING_OAUTH_CALLBACK.'&scope=publish_stream,offline_access';
			wp_redirect($url);
		}
		else if(isset($_GET['action']) && $_GET['action'] == 'connect_to_statusnet' && wp_verify_nonce($_GET['_wpnonce'],'connect_to_statusnet')) // Twitter redirection
		{
			require_once('libs/statusnetoauth.php');
			$options = get_option('sideblogging');
			$connection = new SidebloggingStatusNetOAuth($options['statusnet_url'],$options['statusnet_consumer_key'], $options['statusnet_consumer_secret']);
			$request_token = $connection->getRequestToken(SIDEBLOGGING_OAUTH_CALLBACK.'&site=statusnet'); // Génère des notices en cas d'erreur de connexion
			if(200 == $connection->http_code)
			{
				$token = $request_token['oauth_token'];
				set_transient('oauth_token', $token, 86400);
				set_transient('oauth_token_secret', $request_token['oauth_token_secret'], 86400);
				
				$url = $connection->getAuthorizeURL($token,false);
				wp_redirect($url.'&oauth_access_type=write');
			}
			else
				wp_die(__('The StatusNet installation is currently unavailable. Please check your keys or try again later.',self::domain));
		}
	}
	
	/* Publie les nouvelles brèves sur les réseaux sociaux */
	function send_to_social($post_ID) {
		$post = get_post($post_ID);

		if($post->post_date == $post->post_modified || isset($_SESSION['sideblogging_sendtosocial']))
		{
			if(isset($_SESSION['sideblogging_sendtosocial']))
			{
				unset($_SESSION['sideblogging_sendtosocial']);
			}
			
			$options = get_option('sideblogging');
			$permalink = get_permalink($post_ID);
						
			if(!isset($options['shortener']) || $options['shortener'] == 'native')
				$shortlink = wp_get_shortlink($post_ID);
			else
			{
				require_once 'libs/shortlinks.class.php';
				$shortlinks = new Shortlinks();
				$shortlinks->setApi($options['shortener_login'],$options['shortener_password']);
				$shortlink = $shortlinks->getLink($permalink,$options['shortener']);
			}
			
			if(!$shortlink)
				$shortlink = home_url('?p='.$post_ID);

			if(isset($options['twitter_token']) && !empty($options['twitter_token'])) // Twitter est configuré
			{
				require_once 'libs/twitteroauth.php';
				$content = $post->post_title;
				
				if(strlen($post->post_content) > 0)
					$content .= ' '.$shortlink;

				$connection = new SidebloggingTwitterOAuth($options['twitter_consumer_key'], $options['twitter_consumer_secret'],
							$options['twitter_token']['oauth_token'],$options['twitter_token']['oauth_token_secret']);
				$connection->post('statuses/update', array('status' => $content));
			}

			if(isset($options['statusnet_token']) && !empty($options['statusnet_token'])) // StatusNet est configuré
			{
				require_once 'libs/statusnetoauth.php';
				$content = $post->post_title;
				
				if(strlen($post->post_content) > 0)
					$content .= ' '.$shortlink;

				$connection = new SidebloggingStatusNetOAuth($options['statusnet_url'], $options['statusnet_consumer_key'], $options['statusnet_consumer_secret'],
							$options['statusnet_token']['oauth_token'],$options['statusnet_token']['oauth_token_secret']);
				$connection->post('statuses/update', array('status' => $content));
			}
			
			if(isset($options['facebook_token']) && !empty($options['facebook_token']))
			{
				$body = $options['facebook_token']['access_token'].'&message='.$post->post_title;
				
				if(strlen($post->post_content) > 0)
				{
					$body .= ' '.$shortlink;
					$body .= '&link='.$shortlink;
				}
				
				// Recherche des images dans le post
				// OLD : preg_match('/<img\W*src="([^"]*)".*>/U', $post->post_content, $matches);
				preg_match('#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im', $post->post_content, $matches);
				if(isset($matches[2]))
					$body .= '&picture='.$matches[2];
					
				// oEmbed sur les liens titre + post
				if(!isset($matches[2]) && preg_match_all("/https?:\/\/[a-zAZ0-9-_\/\?&=.\+#]+/i", $post->post_title.' '.$post->post_content, $matches))
				{
					foreach($matches[0] as $url) {
						if($embed = $this->oembed_get($url))
						{
							//print_r($embed);
							if(isset($embed->title))
								$body .= '&name='.$embed->title;
							
							if(isset($embed->thumbnail_url))
								$body .= '&picture='.$embed->thumbnail_url;
							else if(isset($embed->url))
								$body .= '&picture='.$embed->url;
							
							if(isset($embed->html))
							{
								preg_match('/<embed\W*src="([^"]*)".*>/U', $embed->html, $matches);
								if(isset($matches[1]))
									$body .= '&source='.urlencode($matches[1]);
							}
							break;
						}
					}
				}
				$request = wp_remote_post('https://graph.facebook.com/me/feed', array('body' => $body, 'sslverify' => false));
				//echo $body;print_r(wp_remote_retrieve_body($request));exit;
			}
		}
		return $post_ID;
	}
	
	function oembed_get($url) {
		require_once ABSPATH.WPINC.'/class-oembed.php';
		$oembed = new WP_oEmbed();
		$oembed_provider = $oembed->discover($url);
		if($oembed_provider)
			return $oembed->fetch($oembed_provider, $url);
			
		return false;
	}
	
	/* Page de configuration */
	function options_page() {	
		require_once 'sideblogging_optionspage.php';
	}
	
	// Register settings
	function register_settings() {
		register_setting('sideblogging_settings','sideblogging',array(&$this,'filter_options'));
	}
	
	function filter_options($options) {
		//TODO: Filtrer les options
		$options_old = get_option('sideblogging');
		
		// Si on change les clés d'applications, oubliez la connexion
		if($options_old['twitter_consumer_key'] != $options['twitter_consumer_key'] || $options_old['twitter_consumer_secret'] != $options['twitter_consumer_secret'])
			$options['twitter_token'] = '';

		if($options_old['facebook_consumer_key'] != $options['facebook_consumer_key'] || $options_old['facebook_consumer_secret'] != $options['facebook_consumer_secret'])
			$options['facebook_token'] = '';
			
		if($options_old['statusnet_url'] != $options['statusnet_url'] || $options_old['statusnet_consumer_key'] != $options['statusnet_consumer_key'] || $options_old['statusnet_consumer_secret'] != $options['statusnet_consumer_secret'])
			$options['statusnet_token'] = '';

		// Clean form option
		$options['twitter_consumer_key'] = esc_attr($options['twitter_consumer_key']);
		$options['twitter_consumer_secret'] = esc_attr($options['twitter_consumer_secret']);
		$options['facebook_consumer_key'] = esc_attr($options['facebook_consumer_key']);
		$options['facebook_consumer_secret'] = esc_attr($options['facebook_consumer_secret']);
		$options['statusnet_url'] = esc_attr($options['statusnet_url']);
		$options['statusnet_consumer_key'] = esc_attr($options['statusnet_consumer_key']);
		$options['statusnet_consumer_secret'] = esc_attr($options['statusnet_consumer_secret']);
		
		$options['slug'] = (!empty($options['slug'])) ? esc_attr($options['slug']) : 'asides';
		$options['shortener'] = esc_attr($options['shortener']);
		$options['shortener_login'] = (isset($options['shortener_login'])) ? esc_attr($options['shortener_login']) : $options_old['shortener_login'];
		$options['shortener_password'] = (isset($options['shortener_password'])) ? esc_attr($options['shortener_password']) : $options_old['shortener_password'];
		
		// Directory must always end with a /
		if(isset($options['imagedir']) && strlen($options['imagedir']) > 0)
		{
			if(substr($options['imagedir'], -1) != '/' && substr($options['imagedir'], -1) != '\\')
				$options['imagedir'] .= '/';
		}

		$options['purge'] = (is_numeric($options['purge'])) ? intval($options['purge']) : 0;
		
		if(is_array($options_old))
			$options = array_merge($options_old,$options);

		return $options;
	}
	
	// Add custom post type
	function post_type_asides() {
		
		$options = get_option('sideblogging');

		$supports = array('title','editor');
		
		if(isset($options['comments']) && $options['comments'] == 1)
			$supports[] = 'comments';
			
		if(isset($options['slug']) && !empty($options['slug']))
			$rewrite = array('slug' => $options['slug']);
		else
			$rewrite = array('slug' => 'asides');
		

		register_post_type('asides',
			array(
				'label' => __('Asides',self::domain),
				'singular_label' => __('Aside',self::domain),
				'public' => true,
				'menu_position' => 5,
				'show_ui' => true,
				'has_archive' => true,
				'capability_type' => 'post',
				'hierarchical' => false,
				'labels' => array(
					'add_new_item' => __('Add new aside',self::domain),
					'edit_item' => __('Edit aside',self::domain),
					'not_found' => __('No aside found',self::domain),
					'not_found_in_trash' => __('No aside found in trash',self::domain),
					'search_items' => __('Search asides',self::domain),
				),
				'supports' => $supports,
				'rewrite' => $rewrite,
			)
		);
	}
	
	function custom_rewrite_rules($wp_rewrite) {
		$options = get_option('sideblogging');
		if(isset($options['slug']) && !empty($options['slug']))
			$slug = $options['slug'];
		else
			$slug = 'asides';
			
		$new_rules = array();
		$new_rules[$slug.'/page/?([0-9]{1,})/?$'] = 'index.php?post_type=asides&paged=' . $wp_rewrite->preg_index(1);
		$new_rules[$slug.'/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?post_type=asides&feed=' . $wp_rewrite->preg_index(1);
		$new_rules[$slug.'/?$'] = 'index.php?post_type=asides';

		$wp_rewrite->rules = array_merge($new_rules, $wp_rewrite->rules);
				
		return $wp_rewrite;
	}
	
	function regenerate_rewrite_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
}
endif;

if(!isset($sideblogging))
	$sideblogging = new Sideblogging();