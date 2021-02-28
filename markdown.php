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
 * Requires PHP: 5.3.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

class WP_GFM {
	const VERSION = '0.11';
	const DEFAULT_RENDER_URL = 'https://api.github.com/markdown/raw';

	// google-code-prettify: https://code.google.com/p/google-code-prettify/
	const FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

	/**
	 * asset file info
	 * @var array
	 */
	private $asset_file;

	/**
	 * whether has markdown converter
	 * (succeeded autoload)
	 * @var bool
	 */
	private $has_converter = false;

	/**
	 * @var string
	 */
	private $agent;

	/**
	 * plugin url
	 * @var string
	 */
	private $url;

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
			$plugin = new WP_GFM();
		}
		return $plugin;
	}

	private function __construct() {
		$this->agent = self::class . '/' . self::VERSION;
		$this->asset_file = include( __DIR__ . '/build/index.asset.php' );
		$this->url   = plugins_url( '', __FILE__ );
		$this->gfm_options = wp_parse_args(
			(array) get_option( 'gfm' ),
			array(
				'general_ad' => false,
				'php_md_always_convert' => false,
				'php_md_use_autolink' => false,
				'php_md_fenced_code_blocks_template' => self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY,
				'render_url' => self::DEFAULT_RENDER_URL,
			)
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
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_styles' ) );
		}

		if ( class_exists( Gfm::class ) ) {
			$this->has_converter = true;
			Gfm::setElementCssPrefix( 'wp-gfm-' );
			// @codingStandardsIgnoreStart
			Gfm::$useAutoLinkExtras        = true == $this->gfm_options['php_md_use_autolink'];
			Gfm::$fencedCodeBlocksTemplate = $this->gfm_options['php_md_fenced_code_blocks_template'];
			// @codingStandardsIgnoreEnd
		}

		if ( $this->gfm_options['php_md_always_convert'] ) {
			add_action( 'the_content', array( $this, 'force_convert' ), 7 );
		} else {
			// should do shortcode before wpautop filter
			add_action( 'the_content', array( $this, 'do_markdown_shortcode' ), 7 );
			//add_filter( 'no_texturize_shortcodes', array( $this, 'shortcodes_to_exempt_from_wptexturize' ) );
			//add_shortcode( 'markdown', array( $this, 'shortcode_markdown' ) );
			//add_shortcode( 'gfm', array( $this, 'shortcode_gfm' ) );
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
	public function wp_enqueue_styles() {
		wp_enqueue_style( 'wp-gfm', $this->url . '/build/style-index.css', array(), $this->asset_file['version'] );
	}

	/**
	 * admin_init action
	 */
	public function admin_init() {
		register_setting( 'gfm_option_group', 'gfm_array', array( $this, 'option_sanitize_gfm' ) );

		add_settings_section(
			'setting_section_general',
			'General',
			array( $this, 'print_section_general' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'general_ad',
			'',
			array( $this, 'print_gfm_general_ad_field' ),
			'gfm-setting-admin',
			'setting_section_general'
		);

		add_settings_section(
			'setting_section_php_markdown',
			'PHP Markdown',
			array( $this, 'print_section_php_markdown' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'php_md_always_convert',
			'',
			array( $this, 'print_gfm_php_md_always_convert_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_use_autolink',
			'',
			array( $this, 'print_gfm_php_md_use_autolink_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_fenced_code_blocks_template',
			'Fenced Code Blocks Template',
			array( $this, 'print_gfm_php_md_fenced_code_blocks_template_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_section(
			'setting_section_gfm',
			'GitHub Flavored Markdown',
			array( $this, 'print_section_gfm' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'render_url',
			'Render URL',
			array( $this, 'print_gfm_render_url_field' ),
			'gfm-setting-admin',
			'setting_section_gfm'
		);
	}

	/**
	 * admin_menu action
	 */
	public function admin_menu() {
		if ( function_exists( 'add_options_page' ) ) {
			add_options_page(
				'GFM Plugin Settings',
				'WP GFM',
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

			<h2>WP GFM Settings</h2>

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
		echo '<input type="checkbox" id="general_ad" name="gfm_array[general_ad]" value="1" class="code" '
			. checked( 1, $this->gfm_options['general_ad'], false ) . ' /> Add a link of wp-gfm plugin to content';
	}

	/**
	 * add_settings_section callback
	 * @see add_settings_section
	 */
	public function print_section_php_markdown() {
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_gfm_php_md_always_convert_field() {
		echo '<input type="checkbox" id="php_md_always_convert" name="gfm_array[php_md_always_convert]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_always_convert'], false ) . ' /> All contents are markdown!'
			. '<p class="description">The plugin converts content even if it is not surrounded by [markdown]</p>';
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_gfm_php_md_use_autolink_field() {
		echo '<input type="checkbox" id="gfm_php_md_use_autolink" name="gfm_array[php_md_use_autolink]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_use_autolink'], false ) . ' /> Use AutoLink';
	}

	/**
	 * add_settings_field callback
	 * @see add_settings_field
	 */
	public function print_gfm_php_md_fenced_code_blocks_template_field() {
		$value = esc_attr( $this->gfm_options['php_md_fenced_code_blocks_template'] );
		echo '<textarea id="gfm_php_md_fenced_code_blocks_template" name="gfm_array[php_md_fenced_code_blocks_template]" class="large-text">' . $value . '</textarea>'
			. '<p class="description">'
			. '{{lang}}, {{title}}, {{codeblock}}<br/>'
			. 'For <a href="https://code.google.com/p/google-code-prettify/" target="_blank" rel="noopener">google-code-prettify</a>: <code>' . esc_attr( self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY ) . '</code><br/>'
			. '</p>';
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
	 * @param $shortcodes
	 *
	 * @return mixed
	 */
	public function shortcodes_to_exempt_from_wptexturize( $shortcodes ) {
		$shortcodes[] = 'markdown';
		$shortcodes[] = 'gfm';
		return $shortcodes;
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
		if ( $this->has_converter ) {
			$content = '<div class="markdown-body markdown-content">' . Gfm::defaultTransform( $content ) . '</div>';
		}
		return $content;
	}

	/**
	 * shortcode callback
	 * [gfm]MARKDOWN_CONTENT[/gfm]
	 * @param $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function shortcode_gfm( /** @noinspection PhpUnusedParameterInspection */ $atts, $content = '' ) {
		$content = do_shortcode( $content );
		return '<div class="markdown-body gfm-content">' . $this->convert_html_by_render_url( $this->gfm_options['render_url'], $content ) . '</div>';
	}

	/**
	 * shortcode callback
	 * [embed_markdown url=""]
	 * @param array $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function shortcode_embed_markdown( $atts, $content ) {
		return $this->shortcode_embed( false, $atts, $content );
	}

	/**
	 * shortcode callback
	 * [embed_gfm url=""]
	 * @param array $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function shortcode_embed_gfm( $atts, $content ) {
		return $this->shortcode_embed( true, $atts, $content );
	}

	/**
	 * @param $use_gfm
	 * @param $atts
	 * @param $content
	 * @return string
	 */
	private function shortcode_embed( $use_gfm, $atts, /** @noinspection PhpUnusedParameterInspection */ $content ) {
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
			$r = '/^https?:\/\/raw\.githubusercontent\.com/';
			if ( preg_match( $r, $url ) ) {
				$url = preg_replace( $r, 'https://github.com', $url );
				$url = '<a href="' . $url . '">' . $url . '</a>';
			}

			return '<div class="markdown-file">'
				. ( $use_gfm ? $this->shortcode_gfm( $atts, $body ) : $this->shortcode_markdown( $atts, $body ) )
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
		return wp_markdown( $content );
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
				return wp_markdown( $matches[1] );
			},
			$content
		);
		$content = preg_replace_callback(
			'/\[gfm](.*?)\[\/gfm]/s',
			function ( $matches ) {
				return wp_fgm( $matches[1] );
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
	 * @param string $render_url
	 * @param string $text
	 *
	 * @return string
	 */
	private function convert_html_by_render_url( $render_url, $text ) {
		$response = wp_remote_request(
			$render_url,
			array(
				'method'     => 'POST',
				'timeout'    => 10,
				'user-agent' => $this->agent,
				'headers'    => array( 'Content-Type' => 'text/plain; charset=UTF-8' ),
				'body'       => $text,
			)
		);
		if ( is_wp_error( $response ) ) {
			$msg = self::class . ' HttpError: ' . $response->get_error_message();
			error_log( $msg . ' on ' . $render_url );
			return $msg;
		}

		if ( $response && isset( $response['response']['code'] ) && 200 != $response['response']['code'] ) {
			$msg = sprintf( self::class . ' HttpError: %s %s', $response['response']['code'], $response['response']['message'] );
			error_log( $msg . ' on ' . $render_url );
			return $msg;
		}
		return wp_remote_retrieve_body( $response );
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
					var ids = ['markdown', 'gfm'];
					$.each(ids, function (index, c) {
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

function wp_markdown( $content ) {
	$p = WP_GFM::get_instance();
	return $p->shortcode_markdown( null, $content );
}

function wp_fgm( $content ) {
	$p = WP_GFM::get_instance();
	if ( ! empty( $p->gfm_options['render_url'] ) ) {
		return $p->shortcode_gfm( null, $content );
	} else {
		return $p->shortcode_markdown( null, $content );
	}
}
