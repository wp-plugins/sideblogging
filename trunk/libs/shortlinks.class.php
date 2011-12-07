<?php
class Shortlinks {
	
	private $http;
	private $login;
	private $password;
	
	public function __construct() {
		if(!class_exists('WP_Http'))
			include_once( ABSPATH . WPINC. '/class-http.php' );			
		$this->http = new WP_Http;
	}
	
	public function setApi($login,$password='') {
		$this->login = $login;
		$this->password = $password;
	}
	
	public function getLink($url,$service) {
		if(method_exists($this,$service))
			return $this->$service(urlencode($url));
		else
			return false;
	}
	
	public function getSupportedServices() {
		return array(
			'isgd' => 'is.gd',
			'bitly' => 'bit.ly',
			'jmp' => 'j.mp',
			'googl' => 'goo.gl',
			'tinyurl' => 'tinyurl.com',
			'supr' => 'su.pr',
			'cligs' => 'cli.gs',
			'twurlnl' => 'twurl.nl',
			'fongs' => 'fon.gs',
		);
	}

	/* Services function */
	
	private function isgd($url) {
		$result = $this->http->request('http://is.gd/api.php?longurl='.$url);
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return $result['body'];
		else
			return false;
	}
	
	private function tinyurl($url) {
		$result = $this->http->request('http://tinyurl.com/api-create.php?url='.$url);
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return $result['body'];
		else
			return false;
	}
	
	private function supr($url) {
		$result = $this->http->request('http://su.pr/api/simpleshorten?version=1.0&url='.$url);
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return $result['body'];
		else
			return false;
	}
		
	private function cligs($url) {
		$result = $this->http->request('http://cli.gs/api/v1/cligs/create?url='.$url);
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return $result['body'];
		else
			return false;
	}
	
	private function fongs($url) {
		$result = $this->http->request('http://fon.gs/create.php?url='.$url);
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return trim(strstr($result['body'],' '));
		else
			return false;
	}

	private function twurlnl($url) {
		$body = array('link' => array('url' => urldecode($url)));
		$result = $this->http->request('http://tweetburner.com/links', array( 'method' => 'POST', 'body' => $body) );
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return $result['body'];
		else
			return false;
	}
	
	private function bitly($url,$domain='bit.ly') {
		if(empty($this->login) || empty($this->password))
			return false;
		
		$result = $this->http->request('http://api.'.$domain.'/v3/shorten?login='.$this->login.'&apiKey='.$this->password.'&longUrl='.$url.'&format=json');
		if(!is_wp_error($result) && $result['response']['code'] == 200)
		{
			$content = json_decode($result['body'],true);
			if($content['status_code'] == 200)
				return $content['data']['url'];
			else
				return false;
		}
		else
			return false;		
	}
	
	private function jmp($url) {
		return $this->bitly($url,'j.mp');
	}

	private function googl($url) {
	
		if(!empty($this->login))
			$key = 'key='.$this->login;
		else
			$key = '?key=AIzaSyCH6NtebjrGRYClJa7MfFnA1DhC06GcSpU';
	
		$headers = array('Content-Type' => 'application/json');
		$body = array('longUrl' => urldecode($url));
		$body = json_encode($body);
		$result = $this->http->request('https://www.googleapis.com/urlshortener/v1/url'.$key, array('method' => 'POST', 'headers' => $headers, 'body' => $body, 'sslverify' => false) );
		if(!is_wp_error($result) && $result['response']['code'] == 200)
			return json_decode($result['body'])->id;
		else
			return false;
	}
}