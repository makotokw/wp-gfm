<?php

use Makotokw\WordPress\Gfm\Gfm;

/**
 * Plugin Name: GitHub Flavored Markdown for WordPress
 * Plugin URI: https://github.com/makotokw/wp-gfm
 * Version: 0.11
 * Description: Converts block in GitHub Flavored Markdown by using shortcode <code>[gfm]</code> and support PHP-Markdown by using shortcode <code>[markdown]</code>
 * Author: makoto_kw
 * Author URI: https://www.makotokw.com/
 * License: MIT
 * Requires at least: 3.1
 * Requires PHP: 5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

class WP_GFM {
	/**
	 * plugin url
	 * @var string
	 */
	private $url;

	/**
	 * @var array
	 */
	private $syntax_highlight_libs;

	/**
	 * plugin settings in Admin page
	 * @var array
	 */
	public $gfm_options;

	/**
	 * @var string
	 */
	private $ad_html;

	/**
	 * @return WP_GFM
	 */
	public static function get_instance() {
		static $plugin = null;
		if ( ! $plugin ) {
			$plugin = new static();
		}
		return $plugin;
	}

	private function __construct() {
		$this->url   = plugins_url( '', __FILE__ );

		$this->syntax_highlight_libs = array();
		$this->syntax_highlight_libs['codeprettify'] = array(
			'name' => 'Google Code Prettify',
			'url' => 'https://github.com/googlearchive/code-prettify',
			'format' => '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>',
		);
		$this->syntax_highlight_libs['highlightjs'] = array(
			'name' => 'highlight.js',
			'url' => 'https://highlightjs.org/',
			'format' => '<pre><code class="{{lang}}">{{codeblock}}</code></pre>',
		);
		$this->syntax_highlight_libs['prism'] = array(
			'name' => 'Prism',
			'url' => 'https://prismjs.com/',
			'format' => '<pre><code class="language-{{lang}}">{{codeblock}}</code></pre>',
		);
		$this->syntax_highlight_libs['none'] = array(
			'name' => 'None',
			'format' => '<pre class="lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>',
		);

		// sets default value for Code completion
		$this->gfm_options = array(
			'php_md_use_autolink' => true,
			'general_ad' => true,
			'php_md_always_convert' => false,
			'syntax_highlight' => 'codeprettify',
			'overwrite_fenced_code_blocks_template' => false,
			'php_md_fenced_code_blocks_template' => $this->syntax_highlight_libs['codeprettify']['format'],
		);

		$this->ad_html = '<div class="wp-gfm-ad"><span class="wp-gfm-powered-by">Markdown with by <img alt="❤" src="https://s.w.org/images/core/emoji/72x72/2764.png" width="10" height="10"> <a href="https://github.com/makotokw/wp-gfm" target="_blank" rel="nofollow noopener" title="makotokw/wp-gfm">wp-gfm</a></span></div>';

		$this->init();
	}

	private function init() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_quicktags' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_assets' ) );
		}

		$this->gfm_options = wp_parse_args(
			(array) get_option( 'gfm' ),
			$this->gfm_options
		);

		if ( class_exists( Gfm::class ) ) {
			Gfm::setElementCssPrefix( 'wp-gfm-' );
			// @codingStandardsIgnoreStart
			Gfm::$useAutoLinkExtras        = true == $this->gfm_options['php_md_use_autolink'];
			if ( $this->gfm_options['overwrite_fenced_code_blocks_template']) {
				Gfm::$fencedCodeBlocksTemplate = $this->gfm_options['php_md_fenced_code_blocks_template'];
			} else {
				Gfm::$fencedCodeBlocksTemplate = $this->syntax_highlight_libs[$this->gfm_options['syntax_highlight']]['format'];
			}
			// @codingStandardsIgnoreEnd
		}

		if ( $this->gfm_options['php_md_always_convert'] ) {
			add_action( 'the_content', array( $this, 'force_convert' ), 7 );
		} else {
			// should do shortcode before wpautop filter
			add_action( 'the_content', array( $this, 'do_markdown_shortcode' ), 7 );
			add_filter( 'no_texturize_shortcodes', array( $this, 'shortcodes_to_exempt_from_wptexturize' ) );
			// do not use shortcode because of conflict html tags added by wpautop
			//add_shortcode( 'markdown', array( $this, 'shortcode_markdown' ) );
			//add_shortcode( 'gfm', array( $this, 'shortcode_markdown' ) );
		}

		if ( $this->gfm_options['general_ad'] ) {
			add_action( 'the_content', array( $this, 'append_plugin_ad' ), 20 );
		}

		add_shortcode( 'embed_markdown', array( $this, 'shortcode_embed_markdown' ) );
		add_filter( 'pre_comment_content', array( $this, 'pre_comment_content' ), 5 );
	}

	/**
	 * wp_enqueue_scripts action
	 */
	public function wp_enqueue_assets() {
		$index_asset = include( __DIR__ . '/build/index.asset.php' );
		wp_enqueue_style( 'wp-gfm', $this->url . '/build/style-index.css', array(), $index_asset['version'] );

		$sh_lib = strtolower( $this->gfm_options['syntax_highlight'] );
		if ( 'none' !== $sh_lib ) {
			/** @noinspection PhpIncludeInspection */
			$sh_asset = include( __DIR__ . "build/sh-{$sh_lib}.asset.php" );
			wp_enqueue_script( "wp-gfm-sh-{$sh_lib}", $this->url . "/build/sh-{$sh_lib}.js", false, $sh_asset['version'] );
			wp_enqueue_style( "wp-gfm-sh-{$sh_lib}", $this->url . "/build/sh-{$sh_lib}.css", array(), $sh_asset['version'] );
		}
	}

	/**
	 * admin_init action
	 */
	public function admin_init() {
		register_setting( 'gfm_option_group', 'gfm_array', array( $this, 'option_sanitize_gfm' ) );

		// general
		add_settings_section(
			'setting_section_general',
			'General',
			array( $this, 'print_section_general' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'autolink',
			'',
			array( $this, 'print_autolink_field' ),
			'gfm-setting-admin',
			'setting_section_general'
		);

		add_settings_field(
			'general_ad',
			'',
			array( $this, 'print_gfm_general_ad_field' ),
			'gfm-setting-admin',
			'setting_section_general'
		);

		add_settings_field(
			'always_convert',
			'',
			array( $this, 'print_always_convert_field' ),
			'gfm-setting-admin',
			'setting_section_general'
		);

		// Fenced Code Blocks
		add_settings_section(
			'setting_section_fenced_code_blocks',
			'Fenced Code Blocks',
			array( $this, 'print_section_fenced_code_blocks' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'syntax_highlight',
			'Syntax Highlight',
			array( $this, 'print_syntax_highlight_field' ),
			'gfm-setting-admin',
			'setting_section_fenced_code_blocks'
		);

		add_settings_field(
			'fenced_code_blocks_template',
			'HTML Template',
			array( $this, 'print_fenced_code_blocks_template_field' ),
			'gfm-setting-admin',
			'setting_section_fenced_code_blocks'
		);
	}

	/**
	 * admin_menu action
	 */
	public function admin_menu() {
		if ( function_exists( 'add_options_page' ) ) {
			add_options_page(
				'GitHub Flavored Markdown Plugin Settings',
				'GFM',
				'manage_options',
				'wp-gfm',
				array( $this, 'options_page' )
			);
		}
	}

	/**
	 * add_options_page
	 */
	public function options_page() {
		?>
		<div class="wrap wrap-wp-gfm">

			<h2>GitHub Flavored Markdown</h2>

			<!--suppress HtmlUnknownTarget -->
			<form method="post" action="options.php">
				<?php
				settings_fields( 'gfm_option_group' );
				do_settings_sections( 'gfm-setting-admin' );
				?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * register_setting sanitize_callback
	 * @param $input
	 *
	 * @return mixed
	 * @see register_setting
	 */
	public function option_sanitize_gfm( $input ) {
		if ( get_option( 'gfm' ) === false ) {
			add_option( 'gfm', $input );
		} else {
			update_option( 'gfm', $input );
		}
		return $input;
	}

	/**
	 * add_settings_section callback
	 * @see add_settings_section
	 */
	public function print_section_general() {
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_gfm_general_ad_field() {
		echo '<label for="gfm_general_ad"><input type="checkbox" id="gfm_general_ad" name="gfm_array[general_ad]" value="1" '
			. checked( 1, $this->gfm_options['general_ad'], false ) . ' > Add a plugin link</label>';
		echo '<p class="description">The following example will be added to a content that has <code>[markdown]</code>.' . $this->ad_html . '</p>';
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_always_convert_field() {
		echo '<label for="gfm_always_convert"><input type="checkbox" id="gfm_always_convert" name="gfm_array[php_md_always_convert]" value="1" '
			. checked( 1, $this->gfm_options['php_md_always_convert'], false ) . ' > All contents are Markdown!</label>'
			. '<p class="description">The plugin converts all contents even if it is not surrounded by [markdown]</p>';
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_autolink_field() {
		echo '<label for="gfm_autolink"><input id=gfm_autolink" type="checkbox" name="gfm_array[php_md_use_autolink]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_use_autolink'], false ) . '> Use AutoLink</label>';
	}

	/**
	 * add_settings_section callback
	 * @see add_settings_section
	 */
	public function print_section_fenced_code_blocks() {
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_syntax_highlight_field() {
		$value = $this->gfm_options['syntax_highlight'];
		echo '<fieldset>';
		foreach ( $this->syntax_highlight_libs as $option_value => $lib ) {
			$checked = $value === $option_value ? ' checked="checked" ' : ' ';
			echo '<p><label><input type="radio" name="gfm_array[syntax_highlight]" value="' . $option_value . '" ' . $checked . '> ' . $lib['name'] . "</label></p>\n";
		}
		echo '</fieldset>';
	}

	public function print_fenced_code_blocks_template_field() {
		echo '<fieldset>';
		echo '<label for="gfm_overwrite_fenced_code_blocks_template"><input type="checkbox" id="gfm_overwrite_fenced_code_blocks_template" name="gfm_array[overwrite_fenced_code_blocks_template]" value="1" class="code" '
			 . checked( 1, $this->gfm_options['overwrite_fenced_code_blocks_template'], false ) . '> Overwrite HTML Template</label>';
		echo '</fieldset>';

		$textarea_value = esc_attr( $this->gfm_options['php_md_fenced_code_blocks_template'] );
		echo '<textarea id="gfm_fenced_code_blocks_template" name="gfm_array[php_md_fenced_code_blocks_template]" class="large-text" cols="" rows="5">' . $textarea_value . '</textarea>';
		echo '<p class="description">';
		echo 'You can use <code>{{lang}}</code>, <code>{{title}}</code>, <code>{{codeblock}}</code> as parameter.<br/>';
		echo <<<'HTML'
<pre>
```{{lang}}:{{title}}
{{codeblock}}
```
</pre>
HTML;
		echo 'The following examples are defaults.<br/>';
		foreach ( $this->syntax_highlight_libs as $lib ) {
			if ( isset( $lib['url'] ) ) {
				echo '<a href="' . $lib['url'] . '" target="_blank" rel="noopener">' . $lib['name'] . '</a>: ';
			} else {
				echo $lib['name'] . ': ';
			}
			echo '<code>' . esc_attr( $lib['format'] ) . '</code><br/>';
		}
		echo '</p>';
		echo '<script type="text/javascript">';
		echo <<<'JS'
(function ($) {
	var $check = $('#gfm_overwrite_fenced_code_blocks_template');
	var $textarea = $('#gfm_fenced_code_blocks_template');
	function refresh_textarea() {
		$textarea.attr('readonly', !$check.prop('checked'));
	}
	$check.change(function () {
		refresh_textarea();
	});
	refresh_textarea();
})(jQuery);
JS;
		echo '</script>';
	}

	/**
	 * add_settings_section callback
	 * @see add_settings_section
	 */
	public function print_section_gfm() {
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_gfm_render_url_field() {
		$value = esc_attr( $this->gfm_options['render_url'] );
		echo '<input type="text" id="gfm_render_url" name="gfm_array[render_url]" value="' . $value . '" class="regular-text"/>';
	}

	/**
	 * no_texturize_shortcodes action
	 * @param string[] $shortcodes
	 *
	 * @return mixed
	 */
	public function shortcodes_to_exempt_from_wptexturize( $shortcodes ) {
		$shortcodes[] = 'markdown';
		$shortcodes[] = 'gfm';
		return $shortcodes;
	}

	/**
	 * @param string $markdown_content
	 *
	 * @return string
	 */
	private function convert_html( $markdown_content ) {
		if ( class_exists( Gfm::class ) ) {
			$content = '<div class="markdown-body markdown-content">' . Gfm::defaultTransform( $markdown_content ) . '</div>';
		} else {
			$content = $markdown_content;
		}
		return $content;
	}

	/**
	 * shortcode callback
	 * [markdown]MARKDOWN_CONTENT[/markdown]
	 * @param $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function shortcode_markdown( /** @noinspection PhpUnusedParameterInspection */ $atts, $content = '' ) {
		$content = do_shortcode( $content );
		return $this->convert_html( $content );
	}

	/**
	 * shortcode callback
	 * [embed_markdown url=""]
	 * @param array $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function shortcode_embed_markdown( $atts, /** @noinspection PhpUnusedParameterInspection */ $content ) {
		/**
		 * @var string $url
		 */
		$defaults = array( 'url' => '' );
		extract( shortcode_atts( $defaults, $atts ) ); // phpcs:ignore
		if ( empty( $url ) ) {
			return '';
		}

		$args = array();
		$response = wp_remote_get( $url, $args );
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );

			// https://raw.githubusercontent.com/makotokw/wp-gfm/master/README.md ->
			// https://github.com/makotokw/wp-gfm/blob/master/README.md
			$r = '/^https?:\/\/raw\.githubusercontent\.com\/([^\/]+)\/([^\/]+)\//';
			if ( preg_match( $r, $url ) ) {
				$url = preg_replace( $r, 'https://github.com/$1/$2/blob/', $url );
				$url = '<a href="' . $url . '">' . $url . '</a>';
			}

			return '<div class="markdown-file">'
				. $this->convert_html( $body )
				. '<div class="markdown-meta">' . $url . $this->ad_html . '</div>'
				. '</div>';
		}
		return '';
	}

	/**
	 * the_content action
	 * @param $content
	 *
	 * @return string
	 */
	public function force_convert( $content ) {
		$content = preg_replace( '{\[/?markdown]}', '', $content );
		return $this->shortcode_markdown( null, $content );
	}

	/**
	 * the_content action
	 * @param $content
	 *
	 * @return string
	 */
	public function do_markdown_shortcode( $content ) {
		if ( class_exists( Gfm::class ) ) {
			if ( isset( $GLOBALS['post'] ) ) {
				if ( isset( $GLOBALS['post']->ID ) ) {
					Gfm::setElementIdPrefix( 'post-' . $GLOBALS['post']->ID . '-md-' );
				}
			}
		}

		$content = preg_replace_callback(
			'/\[markdown](.*?)\[\/markdown]/s',
			function ( $matches ) {
				$p = WP_GFM::get_instance();
				return $p->shortcode_markdown( null, $matches[1] );
			},
			$content
		);
		$content = preg_replace_callback(
			'/\[gfm](.*?)\[\/gfm]/s',
			function ( $matches ) {
				$p = WP_GFM::get_instance();
				return $p->shortcode_markdown( null, $matches[1] );
			},
			$content
		);
		return $content;
	}

	/**
	 * the_content action
	 * @param $context
	 *
	 * @return string
	 */
	public function append_plugin_ad( $context ) {
		if ( strpos( $context, '<div class="markdown-body markdown-content">' ) !== false ) {
			return $context . '<div class="wp-gfm-footer">' . $this->ad_html . '</div>';
		}
		return $context;
	}

	/**
	 * pre_comment_content filter
	 * @param $comment
	 *
	 * @return string
	 */
	public function pre_comment_content( $comment ) {
		$comment = stripslashes( $comment );
		$comment = $this->do_markdown_shortcode( $comment );
		$comment = addslashes( $comment );
		return $comment;
	}

	/**
	 * admin_print_footer_scripts action
	 */
	public function admin_quicktags() {
		// http://wordpress.stackexchange.com/questions/37849/add-custom-shortcode-button-to-editor
		/* Add custom Quicktag buttons to the editor Wordpress ver. 3.3 and above only
		 *
		 * Params for this are:
		 * - Button HTML ID (required)
		 * - Button display, value="" attribute (required)
		 * - Opening Tag (required)
		 * - Closing Tag (required)
		 * - Access key, accesskey="" attribute for the button (optional)
		 * - Title, title="" attribute (optional)
		 * - Priority/position on bar, 1-9 = first, 11-19 = second, 21-29 = third, etc. (optional)
		 */
		?>
		<script type="text/javascript">
			(function ($) {
				if (typeof(QTags) !== 'undefined') {
					$.each( ['markdown'], function (index, c) {
						QTags.addButton(c, '[' + c + ']', '[' + c + ']', '[/' + c + ']');
					});
				}
			})(jQuery);
		</script>
		<?php
	}
}

add_action( 'init', 'wp_gfm_init' );

function wp_gfm_init() {
	if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
	}

	WP_GFM::get_instance();

	include_once 'updater.php';
	if ( is_admin() && class_exists( 'WP_GitHub_Updater' ) ) {
		/** @noinspection PhpUnusedLocalVariableInspection */
		$updater = new WP_GitHub_Updater(
			array(
				'slug'               => plugin_basename( __FILE__ ),
				'proper_folder_name' => 'wp-gfm',
				'api_url'            => 'https://api.github.com/repos/makotokw/wp-gfm',
				'raw_url'            => 'https://raw.github.com/makotokw/wp-gfm/master',
				'github_url'         => 'https://github.com/makotokw/wp-gfm',
				'zip_url'            => 'https://github.com/makotokw/wp-gfm/archive/master.zip',
				'sslverify'          => true,
				'requires'           => '3.1',
				'tested'             => '4.3.1',
				'readme'             => 'README.md',
			)
		);
	}
}

