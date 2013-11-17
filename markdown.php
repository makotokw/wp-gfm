<?php
/*
 Plugin Name: GitHub Flavored Markdown for WordPress
 Plugin URI: https://github.com/makotokw/wp-gfm
 Version: 0.5
 Description: Converts block in GitHub Flavored Markdown by using shortcode [gfm] and support PHP-Markdown by using shortcode [markdown]
 Author: makoto_kw
 Author URI: http://makotokw.com/
 License: MIT
 */

class WP_GFM
{
	const NAME = 'WP_GFM';
	const VERSION = '0.5';
	const DEFAULT_RENDER_URL = 'https://api.github.com/markdown/raw';

	// google-code-prettify: https://code.google.com/p/google-code-prettify/
	const FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

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
			'php_md_always_convert' => false,
			'php_md_use_autolink' => false,
			'php_md_fenced_code_blocks_template' => self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY,
			'render_url' => self::DEFAULT_RENDER_URL,
		));

		add_filter('edit_page_form', array($this, 'edit_form_advanced')); // for page
		add_filter('edit_form_advanced', array($this, 'edit_form_advanced')); // for post

		if (is_admin()) {
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'admin_menu'));
		} else {
			add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_styles'));
			add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
		}
	}

	function php_markdown_init()
	{
		if (class_exists('\Gfm\Markdown\Extra')) {
			$this->hasConverter = true;
			\Gfm\Markdown\Extra::$useAutoLinkExtras = $this->gfmOptions['php_md_use_autolink'] == true;
			\Gfm\Markdown\Extra::$fencedCodeBlocksTemplate = $this->gfmOptions['php_md_fenced_code_blocks_template'];
		}

		if ($this->gfmOptions['php_md_always_convert']) {
			add_action('the_content', array($this, 'force_convert'), 7);
		} else {
			add_action('the_content', array($this, 'the_content'), 7);
		}
	}

	function wp_enqueue_styles()
	{
		wp_enqueue_style('gfm', $this->url . '/css/pygments.css', array(), self::VERSION);
	}

	function wp_enqueue_scripts()
	{

	}

	function admin_init()
	{
		register_setting('gfm_option_group', 'gfm_array', array($this, 'option_sanitize_gfm'));

		add_settings_section(
			'setting_section_php_markdown',
			'PHP Markdown',
			array($this, 'print_section_php_markdown'),
			'gfm-setting-admin'
		);

		add_settings_field(
			'php_md_always_convert',
			'',
			array($this, 'create_gfm_php_md_always_convert_field'),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_use_autolink',
			'',
			array($this, 'create_gfm_php_md_use_autolink_field'),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_fenced_code_blocks_template',
			'Fenced Code Blocks Tempalte',
			array($this, 'create_gfm_php_md_fenced_code_blocks_template_field'),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

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
		<h2>WP GFM Settings</h2>

		<form method="post" action="options.php">
			<?php
			settings_fields('gfm_option_group');
			do_settings_sections('gfm-setting-admin');
			?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
	}

	function option_sanitize_gfm($input)
	{
		if (get_option('gfm') === false) {
			add_option('gfm', $input);
		} else {
			update_option('gfm', $input);
		}
		return $input;
	}

	function print_section_php_markdown()
	{
	}

	function create_gfm_php_md_always_convert_field()
	{
		echo '<input type="checkbox" id="php_md_always_convert" name="gfm_array[php_md_always_convert] value="1" class="code" '
			. checked(1, $this->gfmOptions['php_md_always_convert'], false) . ' /> All contents are markdown!'
			. '<p class="description">The plugin converts content even if it is not surrounded by [markdown]</p>';
	}

	function create_gfm_php_md_use_autolink_field()
	{
		echo '<input type="checkbox" id="gfm_php_md_use_autolink" name="gfm_array[php_md_use_autolink] value="1" class="code" '
			. checked(1, $this->gfmOptions['php_md_use_autolink'], false) . ' /> Use AutoLink';
	}

	function create_gfm_php_md_fenced_code_blocks_template_field()
	{
		$value = esc_attr($this->gfmOptions['php_md_fenced_code_blocks_template']);
		echo '<textarea id="gfm_php_md_fenced_code_blocks_template" name="gfm_array[php_md_fenced_code_blocks_template]" class="large-text">' . $value . '</textarea>'
		. '<p class="description">'
		. '{{lang}}, {{title}}, {{codeblock}}<br/>'
		. 'For <a href="https://code.google.com/p/google-code-prettify/" target="_blank">google-code-prettify</a>: <code>' . esc_attr(self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY) . '</code><br/>'
		. '</p>'
		;
	}

	function print_section_gfm()
	{
	}

	function create_gfm_render_url_field() {
		$value = esc_attr($this->gfmOptions['render_url']);
		echo '<input type="text" id="gfm_render_url" name="gfm_array[render_url]" value="' . $value .'" class="regular-text"/>';
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

	function force_convert($content)
	{
		$content = preg_replace('{\[/?markdown\]}', '', $content);
		return wp_markdown($content);
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
				$plugin->php_markdown_init();
			}
		}
	}

	include_once 'updater.php';
	if (is_admin() && class_exists('WP_GitHub_Updater')) {
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
	if (!empty($p->gfmOptions['render_url'])) {
		return $p->shortcode_gfm(null, $content);
	} else {
		return $p->shortcode_markdown(null, $content);
	}
}
