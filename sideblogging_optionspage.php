<?php	
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
		echo '<option value="0">'.__('Deactivate',self::domain).'</option>';
		echo '<option '.selected(1,$options['comments'],false).' value="1">'.__('Activate',self::domain).'</option>';
		echo '</select>';
		echo '</td></tr>';
		
		echo '<tr valign="top">
		<th scope="row">
		<label for="sideblogging_mainrss">'.__('Asides in the main RSS feed',self::domain).'</label>
		</th><td>';
		echo '<select name="sideblogging[mainrss]" id="sideblogging_mainrss">';
		echo '<option value="0">'.__('Deactivate',self::domain).'</option>';
		echo '<option '.selected(1,$options['mainrss'],false).' value="1">'.__('Activate',self::domain).'</option>';
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
						<img src="'.SIDEBLOGGING_URL.'/images/facebook.png" alt="Connexion à Facebook" />
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