<?php
/*
 Plugin Name: GitHub Flavored Markdown for WordPress
 Plugin URI: https://github.com/makotokw/wp-gfm
 Version: 0.4
 Description: Converts block in GitHub Flavored Markdown by using shortcode [gfm] and support PHP-Markdown by using shortcode [markdown]
 Author: makoto_kw
 Author URI: http://makotokw.com/
 License: MIT
 */

class WP_GFM
{
	const NAME = 'WP_GFM';
	const VERSION = '0.4';
	const DEFAULT_RENDER_URL = 'https://api.github.com/markdown/raw';

	public $agent = '';
	public $url = '';
	public $hasConverter = false;
	public $gfmOptions = array();

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

		$this->gfmOptions = wp_parse_args((array)get_option('gfm'), array(
			'render_url' => self::DEFAULT_RENDER_URL
		));

		add_action('the_content', array($this, 'the_content'), 7);
		add_filter('edit_page_form', array($this, 'edit_form_advanced')); // for page
		add_filter('edit_form_advanced', array($this, 'edit_form_advanced')); // for post
		wp_enqueue_style('gfm', $this->url . '/css/pygments.css', array(), self::VERSION);

		if (is_admin()) {
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'admin_menu'));
		}
	}

	function admin_init()
	{
		register_setting('gfm_option_group', 'gfm', array($this, 'option_gfm'));

		add_settings_section(
			'setting_section_gfm',
			'GitHub Flavored Markdown',
			array($this, 'print_section_gfm'),
			'gfm-setting-admin'
		);

		add_settings_field(
			'render_url',
			'Render URL',
			array($this, 'create_gfm_render_url_field'),
			'gfm-setting-admin',
			'setting_section_gfm'
		);
	}

	function admin_menu()
	{
		if (function_exists('add_options_page')) {
			add_options_page(
				'GFM Plugin Settings',
				'WP GFM',
				'manage_options',
				'wp-gfm',
				array($this, 'options_page')
			);
		}
	}

	function options_page()
	{
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Settings</h2>
		<form method="post">
			<?php
			settings_fields('gfm_option_group');
			do_settings_sections( 'gfm-setting-admin' );
			?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
	}

	function option_gfm($input)
	{
		if (get_option('gfm') === false) {
			add_option('gfm', $input);
		} else {
			update_option('gfm', $input);
		}
		return $input;
	}

	function print_section_gfm()
	{
	}

	function create_gfm_render_url_field() {
	?><input type="text" id="gfm_render_url" name="gfm[render_url]"
			 value="<?php echo $this->gfmOptions['render_url'] ?>" class="regular-text"/><?php
}

	function shortcode_gfm($atts, $content = '')
	{
		$renderUrl = @$this->gfmOptions['render_url'];
		return '<div class="gfm-content">' . $this->convert_html_by_render_url($renderUrl, $content) . '</div>';
	}

	function shortcode_markdown($atts, $content = '')
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

	function edit_form_advanced()
	{
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
	$plugin = WP_GFM::getInstance();

	// use Michelf/Markdown if PHP 5.3+
	if (defined('PHP_VERSION_ID')) {
		if (PHP_VERSION_ID >= 50300) {
			if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
				require_once dirname(__FILE__) . '/vendor/autoload.php';
				$plugin->hasConverter = true;
			}
		}
	}

	include_once 'updater.php';

	if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin
		new WP_GitHub_Updater(
			array(
				'slug' => plugin_basename(__FILE__),
				'proper_folder_name' => 'wp-gfm',
				'api_url' => 'https://api.github.com/repos/makotokw/wp-gfm',
				'raw_url' => 'https://raw.github.com/makotokw/wp-gfm/master',
				'github_url' => 'https://github.com/makotokw/wp-gfm',
				'zip_url' => 'https://github.com/makotokw/wp-gfm/archive/master.zip',
				'sslverify' => true,
				'requires' => '3.0',
				'tested' => '3.6',
				'readme' => 'README.md',
				'access_token' => '',
			)
		);
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
