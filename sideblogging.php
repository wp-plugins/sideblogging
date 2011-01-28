<?php
/*
 * Plugin Name: SideBlogging
 * Plugin URI: http://blog.boverie.eu/sideblogging-des-breves-sur-votre-blog/
 * Description: Display asides in a widget. They can automatically be published to Twitter, Facebook, and any Status.net installation (like identi.ca).
 * Version: 0.6
 * Author: Cédric Boverie
 * Author URI: http://www.boverie.eu/
 * Text Domain: sideblogging
*/
/* Copyright 2010  Cédric Boverie  (email : ced@boverie.eu)
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
			
			// Regenerate permalink (on every admin page, need to find better)
			add_action('admin_init', array(&$this,'regenerate_rewrite_rules'));
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

	// Options page and help
	function menu() {
		$screen = add_options_page('SideBlogging', ' SideBlogging', 'manage_options', 'sideblogging', array(&$this,'options_page'));
		
		$text = '<h5>'.__('Sideblogging help',self::domain).'</h5>';
		$text .= '<p><a target="_blank" href="http://twitter.com/apps/new">'.__('Create a Twitter application',self::domain).'</a> (<a target="_blank" href="http://www.youtube.com/watch?v=TEpR1M1R9nI">'.__('video tutorial',self::domain).'</a>)<br />';
		$text .= '<a target="_blank" href="http://www.facebook.com/developers/apps.php">'.__('Create a Facebook application',self::domain).'</a> (<a target="_blank" href="http://www.youtube.com/watch?v=0EH2WQdnvUg">'.__('video tutorial',self::domain).'</a>)<br />';
		$text .= '<a target="_blank" href="http://identi.ca/settings/oauthapps/new">'.__('Create a Identi.ca application',self::domain).'</a></p>';
		
		$text .= '<h5>'.__('About Facebook',self::domain).'</h5>';
		$text .= '<p>'.__('For Facebook, you may need to modify the Site URL',self::domain).'.<br />
				'.__('To do:',self::domain).'</p>
				<ul>
				<li>'.__('Go to application settings',self::domain).'.</li>
				<li>'.__('Go to section <em>Web Site</em>',self::domain).'.</li>
				<li>'.sprintf(__('Put %s in the field Site URL',self::domain),'<strong>'.get_bloginfo('url').'/</strong>').'.</li>
				</ul>';
				
		$text .= '<h5>'.__('Debug',self::domain).'</h5>';
		$text .= '<p><a href="options-general.php?page=sideblogging&amp;debug=1">'.__('Debug information',self::domain).'</a></p>';
		add_contextual_help($screen,$text);
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
					$('#sideblogging-submit').attr('disabled','');
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

		if($post->post_date == $post->post_modified)
		{
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
				preg_match('/<img\W*src="([^"]*)".*>/U', $post->post_content, $matches);
				if(isset($matches[1]))
					$body .= '&picture='.$matches[1];
					
				// oEmbed sur les liens titre + post
				if(preg_match_all("/https?:\/\/[a-zAZ0-9-_\/\?&=.\+#]+/i", $post->post_title.' '.$post->post_content, $matches))
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
				//wp_remote_retrieve_body($request);exit;
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
		echo '<div class="wrap">';
		echo '<h2>SideBlogging</h2>';
		
		if(isset($_GET['debug']))
		{
			$options = get_option('sideblogging');
			echo '<h3>'.__('Settings').'</h3>';
			echo '<pre>';
			print_r($options);
			echo '</pre>';
			echo '<h3>Facebook</h3>';
			if(isset($options['facebook_token']['access_token']))
			{
				$result = wp_remote_get('https://graph.facebook.com/me?'.$options['facebook_token']['access_token'], array('sslverify' => false));
				$me = json_decode(wp_remote_retrieve_body($result),true);
				echo '<pre>';
				print_r($me);
				echo '</pre>';
			}
			else
				echo '<p>'.__('No Facebook account registered',self::domain).'.</p>';
				
			require_once 'libs/twitteroauth.php';
			echo '<h3>Twitter</h3>';
			if(isset($options['twitter_token']['oauth_token']))
			{
				$connection = new SidebloggingTwitterOAuth($options['twitter_consumer_key'], $options['twitter_consumer_secret'],
							$options['twitter_token']['oauth_token'],$options['twitter_token']['oauth_token_secret']);
				$content = $connection->get('account/rate_limit_status');
				echo 'Quota API :'.$content->remaining_hits.' appels restants.';
				$content = $connection->get('account/verify_credentials');
				echo '<pre>';
				print_r($content);
				echo '</pre>';
			}
			else
				echo '<p>'.__('No Twitter account registered',self::domain).'.</p>';
			
			require_once 'libs/statusnetoauth.php';
			echo '<h3>StatusNet</h3>';
			if(isset($options['statusnet_token']['oauth_token']))
			{
				$connection = new SidebloggingStatusNetOAuth($options['statusnet_url'], $options['statusnet_consumer_key'], $options['statusnet_consumer_secret'],
							$options['statusnet_token']['oauth_token'],$options['statusnet_token']['oauth_token_secret']);
				$content = $connection->get('account/verify_credentials');
				echo '<pre>';
				print_r($content);
				echo '</pre>';
			}
			else
				echo '<p>'.__('No StatusNet account registered',self::domain).'.</p>';

			echo '</div>';
			return;
		}
		else if(isset($_GET['oauth_verifier']) && $_GET['site'] == 'twitter') // Twitter vérification finale
		{
			$options = get_option('sideblogging');
			require_once('libs/twitteroauth.php');
			$connection = new SidebloggingTwitterOAuth($options['twitter_consumer_key'], $options['twitter_consumer_secret'], get_transient('oauth_token'), get_transient('oauth_token_secret'));
			$access_token = $connection->getAccessToken($_GET['oauth_verifier']);
			
			delete_transient('oauth_token');
			delete_transient('oauth_token_secret');
			
			if (200 == $connection->http_code)
			{
				$options['twitter_token']['oauth_token'] = esc_attr($access_token['oauth_token']);
				$options['twitter_token']['oauth_token_secret'] = esc_attr($access_token['oauth_token_secret']);
				$options['twitter_token']['user_id'] = intval($access_token['user_id']);
				$options['twitter_token']['screen_name'] = esc_attr($access_token['screen_name']);
				update_option('sideblogging',$options);
				echo '<div class="updated"><p><strong>'.__('Twitter account registered',self::domain).'.</strong></p></div>';
			}
			else
				echo '<div class="error"><p><strong>'.__('Error during the connection with Twitter',self::domain).'.</strong></p></div>';
		}
		else if(isset($_GET['oauth_verifier']) && $_GET['site'] == 'statusnet') // StatusNet vérification finale
		{
			$options = get_option('sideblogging');
			require_once('libs/statusnetoauth.php');
			$connection = new SidebloggingStatusNetOAuth($options['statusnet_url'],$options['statusnet_consumer_key'], $options['statusnet_consumer_secret'], get_transient('oauth_token'), get_transient('oauth_token_secret'));
			$access_token = $connection->getAccessToken($_GET['oauth_verifier']);

			delete_transient('oauth_token');
			delete_transient('oauth_token_secret');

			if (200 == $connection->http_code)
			{
				$options['statusnet_token']['oauth_token'] = esc_attr($access_token['oauth_token']);
				$options['statusnet_token']['oauth_token_secret'] = esc_attr($access_token['oauth_token_secret']);
				
				$content = $connection->get('account/verify_credentials');
				$options['statusnet_token']['user_id'] = intval($content->id);
				$options['statusnet_token']['screen_name'] = esc_attr($content->screen_name);
				
				update_option('sideblogging',$options);
				echo '<div class="updated"><p><strong>'.__('StatusNet account registered',self::domain).'.</strong></p></div>';
			}
			else
				echo '<div class="error"><p><strong>'.__('Error during the connection with StatusNet installation',self::domain).'.</strong></p></div>';
		}
		else if(isset($_GET['code'])) // Facebook vérification finale
		{
			$options = get_option('sideblogging');

			$url = 'https://graph.facebook.com/oauth/access_token?client_id='.$options['facebook_consumer_key'].'&redirect_uri='.SIDEBLOGGING_OAUTH_CALLBACK.'&client_secret='.$options['facebook_consumer_secret'].'&code='.esc_attr($_GET['code']);
			$result = wp_remote_get($url, array('sslverify' => false));
			$token = wp_remote_retrieve_body($result); 
			$result = wp_remote_get('https://graph.facebook.com/me?'.$token, array('sslverify' => false));
			$me = json_decode(wp_remote_retrieve_body($result),true);
			
			if(is_array($me))
			{
				$options['facebook_token']['access_token'] = esc_attr($token);
				$options['facebook_token']['name'] = esc_attr($me['name']);
				$options['facebook_token']['link'] = esc_url($me['link']);
				update_option('sideblogging',$options);
				echo '<div class="updated"><p><strong>'.__('Facebook account registered',self::domain).'</strong></p></div>';
			}
			else
				echo '<div class="error"><p><strong>'.__('Error during the connection with Facebook',self::domain).'</strong></p></div>';
		}
		else if(isset($_GET['action']) && $_GET['action'] == 'disconnect_from_twitter' && wp_verify_nonce($_GET['_wpnonce'], 'disconnect_from_twitter')) // Déconnexion de Twitter
		{
			$options = get_option('sideblogging');
			$options['twitter_token'] = '';
			update_option('sideblogging',$options);
		}
		else if(isset($_GET['action']) && $_GET['action'] == 'disconnect_from_statusnet' && wp_verify_nonce($_GET['_wpnonce'], 'disconnect_from_statusnet')) // Déconnexion de StatusNet
		{
			$options = get_option('sideblogging');
			$options['statusnet_token'] = '';
			update_option('sideblogging',$options);
		}
		else if(isset($_GET['action']) && $_GET['action'] == 'disconnect_from_facebook' && wp_verify_nonce($_GET['_wpnonce'], 'disconnect_from_facebook')) // Déconnexion de Facebook
		{
			$options = get_option('sideblogging');
			$options['facebook_token'] = '';
			update_option('sideblogging',$options);
		}

		$options = get_option('sideblogging');
		require_once 'libs/shortlinks.class.php';

		echo '<form action="options.php" method="post">';
		settings_fields('sideblogging_settings');

		echo '<h3>'.__('General Settings',self::domain).'</h3>';

		echo '<table class="form-table">';

		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_comments">'.__('Allow comments',self::domain).'</label>
		</th><td>';
		echo '<select name="sideblogging[comments]" id="sideblogging_comments">';
		echo '<option value="0">OFF</option>';
		echo '<option '.selected(1,$options['comments'],false).' value="1">ON</option>';
		echo '</select>';
		echo '</td></tr>';

		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_purge">'.__('Purge asides older than ',self::domain).'</label>
		</th><td>';
		echo '<input type="text" size="4" value="'.$options['purge'].'" name="sideblogging[purge]" id="sideblogging_purge" />';
		echo ' '.__('days',self::domain).' ('.__('0 for keeping asides forever',self::domain).')</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_shortener">'.__('Url shortener',self::domain).'</label>
		</th><td>';
		echo '<select name="sideblogging[shortener]" id="sideblogging_shortener">';
		echo '<option value="native">Native</option>';
		foreach(Shortlinks::getSupportedServices() as $id => $name)
		{
			echo '<option '.selected($id,$options['shortener'],false).' value="'.$id.'">'.$name.'</option>';
		}
		echo '</select>';
		echo '</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_imagedir">'.__('Images directory',self::domain).'</label>
		</th><td>';
		echo '<input type="text" size="70" value="'.$options['imagedir'].'" name="sideblogging[imagedir]" id="sideblogging_imagedir" />';
		echo ' <a href="#" onclick="document.getElementById(\'sideblogging_imagedir\').value = \''.SIDEBLOGGING_URL.'/images/\';return false;">default</a>';
		echo ' <a href="#" onclick="document.getElementById(\'sideblogging_imagedir\').value = \''.get_bloginfo('stylesheet_directory').'\';return false;">theme</a>';
		echo '</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_slug">'.__('Permalinks prefix',self::domain).'</label>
		</th><td>';
		echo '<input type="text" size="20" value="'.(isset($options['slug']) ? $options['slug'] : 'asides').'" name="sideblogging[slug]" id="sideblogging_slug" />';
		echo ' <a href="#" onclick="document.getElementById(\'sideblogging_slug\').value = \'asides\';return false;">asides</a>';
		echo '</td></tr>';
		
		if(in_array($options['shortener'],array('bitly','jmp')))
		{
			echo '<tr valign="top">
			<th scope="row">
			<label for="sideblogging_shortener_login">API Login</label>
			</th><td>';
			echo '<input type="text" class="regular-text" value="'.$options['shortener_login'].'" name="sideblogging[shortener_login]" id="sideblogging_shortener_login" />';
			echo '</td></tr>';
					
			echo '<tr valign="top">
			<th scope="row">
			<label for="sideblogging_shortener_password">API Key</label>
			</th><td>';
			echo '<input type="text" class="regular-text" value="'.$options['shortener_password'].'" name="sideblogging[shortener_password]" id="sideblogging_shortener_password" />';
			echo ' (<a target="_blank" href="http://bit.ly/a/your_api_key">'.__('Find your key',self::domain).'</a>)</td></tr>';
		}
		echo '</table>';
		
		echo '<h3>'.__('Applications Settings',self::domain).'</h3>';

		echo '<table class="form-table">';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_twitter_consumer_key">Twitter Consumer Key</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['twitter_consumer_key'].'" name="sideblogging[twitter_consumer_key]" id="sideblogging_twitter_consumer_key" />';
		echo '</td></tr>';
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_twitter_consumer_secret">Twitter Consumer Secret</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['twitter_consumer_secret'].'" name="sideblogging[twitter_consumer_secret]" id="sideblogging_twitter_consumer_secret" />';
		echo '</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_facebook_consumer_key">Facebook APP ID</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['facebook_consumer_key'].'" name="sideblogging[facebook_consumer_key]" id="sideblogging_facebook_consumer_key" />';
		echo '</td></tr>';
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_facebook_consumer_secret">Facebook Secret Key</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['facebook_consumer_secret'].'" name="sideblogging[facebook_consumer_secret]" id="sideblogging_facebook_consumer_secret" />';
		echo '</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_statusnet_url">StatusNet URL</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['statusnet_url'].'" name="sideblogging[statusnet_url]" id="sideblogging_statusnet_url" />';
		echo ' <a href="#" onclick="document.getElementById(\'sideblogging_statusnet_url\').value = \'http://identi.ca/\';return false;">Identi.ca</a>';
		echo '</td></tr>';
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_statusnet_consumer_key">StatusNet Consumer Key</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['statusnet_consumer_key'].'" name="sideblogging[statusnet_consumer_key]" id="sideblogging_statusnet_consumer_key" />';
		echo '</td></tr>';
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_statusnet_consumer_secret">StatusNet Consumer Secret</label>
		</th><td>';
		echo '<input type="text" class="regular-text" value="'.$options['statusnet_consumer_secret'].'" name="sideblogging[statusnet_consumer_secret]" id="sideblogging_statusnet_consumer_secret" />';
		echo '</td></tr>';
		
		echo '</table>';
		
		echo '<p>'.__('Don\'t forget to look at the contextual help (in the top right of page) for more informations about keys.',self::domain).'</p>';
		echo '<p class="submit"><input type="submit" class="button-primary" value="'.__('Save Changes').'" /></p>';
		
		echo '</form>';
		
		
		// Twitter
		echo '<h3>'.__('Republish on Twitter',self::domain).'</h3>';

		if(empty($options['twitter_consumer_key']) || empty($options['twitter_consumer_secret']))
		{
			echo '<p>'.__('You must configure Twitter app to be able to sign-in',self::domain).'.</p>';
		}
		else if(empty($options['twitter_token']))
		{
			echo '<p>'.__('To automatically publish your asides on Twitter, sign-in below:', self::domain).'</p>';
			echo '<p><a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=connect_to_twitter','connect_to_twitter').'">
					<img src="'.SIDEBLOGGING_URL.'/images/twitter.png" alt="Connexion à Twitter" />
				</a></p>';
		}
		else
		{
			echo '<p>'.sprintf(__('You are connected to Twitter as %s',self::domain),'<strong>@'.$options['twitter_token']['screen_name'].'</strong>').'. ';
			echo '<a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=disconnect_from_twitter','disconnect_from_twitter').'">'.__('Change account or disable',self::domain).'</a>.</p>';
		}
		
		// Facebook
		echo '<h3>'.__('Republish on Facebook',self::domain).'</h3>';

		if(!extension_loaded('openssl'))
		{
			echo '<p>'.__('Sorry, you need OpenSLL to connect with Facebook',self::domain).'.</p>';
		}
		else if(empty($options['facebook_consumer_key']) || empty($options['facebook_consumer_secret']))
		{
			echo '<p>'.__('You must configure Facebook app to be able to sign-in',self::domain).'.</p>';
		}
		else if(empty($options['facebook_token']))
		{
			echo '<p>'.__('To automatically publish your asides on Facebook, sign-in below:',self::domain).'</p>';
			echo '<p><a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=connect_to_facebook','connect_to_facebook').'">
						<img src="'.SIDEBLOGGING_URL.'/images/facebook.gif" alt="Connexion à Facebook" />
				</a></p>';
		}
		else
		{
			echo '<p>'.sprintf(__('You are connected to Facebook as %s',self::domain),'<strong>'.$options['facebook_token']['name'].'</strong>').'. ';
			echo '<a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=disconnect_from_facebook','disconnect_from_facebook').'">'.__('Change account or disable',self::domain).'</a>.</p>';
		}
		
		// StatusNet
		echo '<h3>'.__('Republish on a Identi.ca (or other StatusNet installation)',self::domain).'</h3>';
		if(empty($options['statusnet_url']) || empty($options['statusnet_consumer_key']) || empty($options['statusnet_consumer_secret']))
		{
			echo '<p>'.__('You must configure StatusNet app to be able to sign-in',self::domain).'.</p>';
		}
		else if(empty($options['statusnet_token']))
		{
			echo '<p>'.__('To automatically publish your asides on StatusNet, sign-in below:',self::domain).'</p>';
			echo '<p><a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=connect_to_statusnet','connect_to_statusnet').'">
				<img src="'.SIDEBLOGGING_URL.'/images/statusnet.png" alt="Connexion à StatusNet" />
				</a></p>';
		}
		else
		{
			echo '<p>'.sprintf(__('You are connected to StatusNet as %s',self::domain),'<strong>@'.$options['statusnet_token']['screen_name'].'</strong>').'. ';
			echo '<a href="'.wp_nonce_url('options-general.php?page=sideblogging&action=disconnect_from_statusnet','disconnect_from_statusnet').'">'.__('Change account or disable',self::domain).'</a>.</p>';
		}
		
		echo '</div>';
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
		
		$options['slug'] = esc_attr($options['slug']);
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