<?php
/*
 Plugin Name: Github Flavored Markdown for WordPress
 Plugin URI: https://github.com/makotokw/wp-gfm
 Version: 0.1
 Description: Converts block in GitHub Flavored Markdown by using shortcode [markdown]
 Author: makoto_kw
 Author URI: http://www.makotokw.com/
 */

class WP_GFM
{
	const NAME = 'WP_GFM';
	const VERSION = '0.1';

	var $agent = '';
	var $url = '';
	var $renderUrl = 'https://api.github.com/markdown/raw';
	
	static function getInstance() {
		static $plugin = null;
		if (!$plugin) {
			$plugin = new WP_GFM();
		}
		return $plugin;
	}
	
	private function __construct() {
		$this->agent = self::NAME.'/'.self::VERSION;
		$wpurl = (function_exists('site_url')) ? site_url() : get_bloginfo('wpurl');
		$this->url = $wpurl.'/wp-content/plugins/'.end(explode(DIRECTORY_SEPARATOR, dirname(__FILE__)));
		// add_action('wp_head', array($this,'head'));
		add_action('the_content', array($this,'the_content'), 7);
		add_filter('edit_page_form', array($this,'edit_form_advanced')); // for page
		add_filter('edit_form_advanced', array($this,'edit_form_advanced')); // for post
	}
	
	function head() {
?>
<?php
	}
	
	function the_content($str) {
		$replace = 'return wp_markdown($matches[2]);';
		return preg_replace_callback('/\[(md|markdown)\](.*?)\[\/(md|markdown)\]/s',create_function('$matches',$replace),$str);
	}
	
	function edit_form_advanced() {
?>
<script type="text/javascript" src="<?php echo $this->url?>/admin.js"></script>
<?php
	}
	
	function convert($text) {
		return '<div class="markdown_content">'.$this->convert_html($text).'</div>';
	}
	
	function convert_html($text) {
		$response = wp_remote_request($this->renderUrl,
			array(
				'method'=>'POST',
				'user-agent'=>$this->agent,
				'headers'=>array('Content-Type'=>'text/plain; charset=UTF-8'),
				'body'=>$text,
			)
		);
		//var_dump($response);
		if (is_wp_error($response)) {
			$msg = self::NAME.' HttpError: '.$response->get_error_message();
			error_log($msg.' on '.$this->renderUrl);
			return $msg;
		}

		if ($response && isset($response['response']['code']) && $response['response']['code'] != 200) {
			$msg = sprintf(self::NAME.' HttpError: %s %s', $response['response']['code'], $response['response']['message']);
			error_log($msg.' on '.$this->renderUrl);
			return $msg;
		}
		return wp_remote_retrieve_body($response);
	}
}

WP_GFM::getInstance();

if (file_exists(dirname(__FILE__).'/config.php')) {
	$config = require(dirname(__FILE__).'/config.php');
	WP_GFM::getInstance()->renderUrl = $config['renderUrl'];
	unset($config);
}

function wp_markdown($text) {
	$p = WP_GFM::getInstance();
	return $p->convert($text);
}