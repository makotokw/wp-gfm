<?php
/*
 Plugin Name: GitHub Flavored Markdown for WordPress
 Plugin URI: https://github.com/makotokw/wp-gfm
 Version: 0.3
 Description: Converts block in GitHub Flavored Markdown by using shortcode [gfm] and support PHP-Markdown by using shortcode [markdown]
 Author: makoto_kw
 Author URI: http://makotokw.com/
 */

class WP_GFM
{
	const NAME = 'WP_GFM';
	const VERSION = '0.3';

	public $agent = '';
	public $url = '';
	public $renderUrl = 'https://api.github.com/markdown/raw';
	public $hasConverter = false;

	static function getInstance()
	{
		static $plugin = null;
		if (!$plugin) {
			$plugin = new WP_GFM();
		}
		return $plugin;
	}

	private function __construct()
	{
		$this->agent = self::NAME . '/' . self::VERSION;
		$wpurl = (function_exists('site_url')) ? site_url() : get_bloginfo('wpurl');
		$this->url = $wpurl . '/wp-content/plugins/' . basename(dirname(__FILE__));

		add_action('the_content', array($this, 'the_content'), 7);
		add_filter('edit_page_form', array($this, 'edit_form_advanced')); // for page
		add_filter('edit_form_advanced', array($this, 'edit_form_advanced')); // for post
		wp_enqueue_style('gfm', $this->url . '/css/pygments.css', array(), self::VERSION);
	}

	function shortcode_gfm($atts, $content='')
	{
		return '<div class="gfm-content">' . $this->convert_html_by_render_url($this->renderUrl, $content). '</div>';
	}

	function shortcode_markdown($atts, $content='')
	{
		if ($this->hasConverter) {
			return '<div class="markdown-content">' . \Gfm\Markdown\Extra::defaultTransform($content) . '</div>';
		}
		return $content;

	}

	function the_content($content)
	{
		$content = preg_replace_callback('/\[markdown\](.*?)\[\/markdown\]/s', create_function('$matches', 'return wp_markdown($matches[1]);'), $content);
		$content = preg_replace_callback('/\[gfm\](.*?)\[\/gfm\]/s', create_function('$matches', 'return wp_fgm($matches[1]);'), $content);
		return $content;
	}

function edit_form_advanced() {
	?>
	<script type="text/javascript" src="<?php echo $this->url ?>/admin.js"></script>
<?php
}

	function convert_html_by_render_url($renderUrl, $text)
	{
		$response = wp_remote_request($renderUrl,
			array(
				'method' => 'POST',
				'timeout' => 10,
				'user-agent' => $this->agent,
				'headers' => array('Content-Type' => 'text/plain; charset=UTF-8'),
				'body' => $text,
			)
		);
		if (is_wp_error($response)) {
			$msg = self::NAME . ' HttpError: ' . $response->get_error_message();
			error_log($msg . ' on ' . $renderUrl);
			return $msg;
		}

		if ($response && isset($response['response']['code']) && $response['response']['code'] != 200) {
			$msg = sprintf(self::NAME . ' HttpError: %s %s', $response['response']['code'], $response['response']['message']);
			error_log($msg . ' on ' . $renderUrl);
			return $msg;
		}
		return wp_remote_retrieve_body($response);
	}
}

add_action('init', 'wp_gfm_init');

function wp_gfm_init()
{
	WP_GFM::getInstance();

	if (file_exists(dirname(__FILE__) . '/config.php')) {
		$config = require(dirname(__FILE__) . '/config.php');
		WP_GFM::getInstance()->renderUrl = $config['renderUrl'];
		unset($config);
	}

	// use Michelf/Markdown if PHP 5.3+
	if (defined('PHP_VERSION_ID')) {
		if (PHP_VERSION_ID >= 50300) {
			if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
				require_once dirname(__FILE__) . '/vendor/autoload.php';
				WP_GFM::getInstance()->hasConverter = true;
			}
		}
	}

}

function wp_markdown($content)
{
	$p = WP_GFM::getInstance();
	return $p->shortcode_markdown(null, $content);
}

function wp_fgm($content)
{
	$p = WP_GFM::getInstance();
	if (!empty($p->renderUrl)) {
		return $p->shortcode_gfm(null, $content);
	} else {
		return $p->shortcode_markdown(null, $content);
	}
}
