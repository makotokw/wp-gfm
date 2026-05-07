<?php

use Gfm\Markdown\Extra as GfmMarkdownExtra;

/**
Plugin Name: GitHub Flavored Markdown for WordPress
Plugin URI: https://github.com/makotokw/wp-gfm
Version: 0.11
Description: Converts block in GitHub Flavored Markdown by using shortcode <code>[gfm]</code> and support PHP-Markdown by using shortcode <code>[markdown]</code>
Author: makoto_kw
Author URI: https://makotokw.com/
License: MIT
 */

/** @noinspection RegExpRedundantEscape */

class WP_GFM {
	const NAME               = 'WP_GFM';
	const VERSION            = '0.11';
	const DEFAULT_RENDER_URL = 'https://api.github.com/markdown/raw';

	// google-code-prettify: https://code.google.com/p/google-code-prettify/
	const FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

	public $agent         = '';
	public $url           = '';
	public $has_converter = false;
	public $gfm_options   = array();
	public $ad_html       = '';

	public static function get_instance() {
		static $plugin = null;
		if ( ! $plugin ) {
			$plugin = new WP_GFM();
		}
		return $plugin;
	}

	private function __construct() {
		$this->agent = self::NAME . '/' . self::VERSION;
		$this->url   = plugins_url( '', __FILE__ );

		$this->gfm_options = wp_parse_args(
			(array) get_option( 'gfm' ),
			array(
				'general_ad'                         => false,
				'php_md_always_convert'              => false,
				'php_md_use_autolink'                => false,
				'php_md_fenced_code_blocks_template' => self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY,
				'render_url'                         => self::DEFAULT_RENDER_URL,
			)
		);

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_quicktags' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_styles' ) );
		}

		$this->ad_html = '<div class="wp-gfm-ad"><span class="wp-gfm-powered-by">Markdown with by <img alt="❤" src="https://s.w.org/images/core/emoji/72x72/2764.png" width="10" height="10"> <a href="https://github.com/makotokw/wp-gfm" target="_blank" rel="nofollow noopener" title="makotokw/wp-gfm">wp-gfm</a></span></div>';
	}

	public function wp_enqueue_styles() {
		wp_enqueue_style( 'wp-gfm', $this->url . '/css/markdown.css', array(), self::VERSION );
	}

	public function php_markdown_init() {
		if ( class_exists( '\Gfm\Markdown\Extra' ) ) {
			$this->has_converter = true;
			GfmMarkdownExtra::setElementCssPrefix( 'wp-gfm-' );
			// @codingStandardsIgnoreStart
			GfmMarkdownExtra::$useAutoLinkExtras        = true == $this->gfm_options['php_md_use_autolink'];
			GfmMarkdownExtra::$fencedCodeBlocksTemplate = $this->gfm_options['php_md_fenced_code_blocks_template'];
			// @codingStandardsIgnoreEnd
		}

		if ( $this->gfm_options['php_md_always_convert'] ) {
			add_action( 'the_content', array( $this, 'force_convert' ), 7 );
		} else {
			add_action( 'the_content', array( $this, 'the_content' ), 7 );
		}

		if ( $this->gfm_options['general_ad'] ) {
			add_action( 'the_content', array( $this, 'the_content_ad' ), 8 );
		}

		add_shortcode( 'embed_markdown', array( $this, 'shortcode_embed_markdown' ) );
		add_filter( 'pre_comment_content', array( $this, 'pre_comment_content' ), 5 );
	}

	public function admin_init() {
		register_setting( 'gfm_option_group', 'gfm_array', array( $this, 'option_sanitize_gfm' ) );

		add_settings_section(
			'setting_section_general',
			'General',
			array( $this, 'setting_section_general' ),
			'gfm-setting-admin'
		);

		add_settings_field(
			'general_ad',
			'',
			array( $this, 'create_gfm_general_ad_field' ),
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
			array( $this, 'create_gfm_php_md_always_convert_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_use_autolink',
			'',
			array( $this, 'create_gfm_php_md_use_autolink_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);

		add_settings_field(
			'php_md_fenced_code_blocks_template',
			'Fenced Code Blocks Template',
			array( $this, 'create_gfm_php_md_fenced_code_blocks_template_field' ),
			'gfm-setting-admin',
			'setting_section_php_markdown'
		);
	}

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

	public function option_sanitize_gfm( $input ) {
		if ( get_option( 'gfm' ) === false ) {
			add_option( 'gfm', $input );
		} else {
			update_option( 'gfm', $input );
		}
		return $input;
	}

	public function setting_section_general() {
	}

	public function create_gfm_general_ad_field() {
		echo '<input type="checkbox" id="general_ad" name="gfm_array[general_ad]" value="1" class="code" '
			. checked( 1, $this->gfm_options['general_ad'], false ) . ' /> Add a link of wp-gfm plugin to content';
	}

	public function print_section_php_markdown() {
	}

	public function create_gfm_php_md_always_convert_field() {
		echo '<input type="checkbox" id="php_md_always_convert" name="gfm_array[php_md_always_convert]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_always_convert'], false ) . ' /> All contents are Markdown!'
			. '<p class="description">The plugin converts content even if it is not surrounded by [markdown]</p>';
	}

	public function create_gfm_php_md_use_autolink_field() {
		echo '<input type="checkbox" id="gfm_php_md_use_autolink" name="gfm_array[php_md_use_autolink]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_use_autolink'], false ) . ' /> Use AutoLink';
	}

	public function create_gfm_php_md_fenced_code_blocks_template_field() {
		$value = $this->gfm_options['php_md_fenced_code_blocks_template'];
		echo '<textarea id="gfm_php_md_fenced_code_blocks_template" name="gfm_array[php_md_fenced_code_blocks_template]" class="large-text">' . esc_textarea( $value ) . '</textarea>'
			. '<p class="description">'
			. '{{lang}}, {{title}}, {{codeblock}}<br/>'
			. 'For <a href="https://code.google.com/p/google-code-prettify/" target="_blank" rel="noopener">google-code-prettify</a>: <code>' . esc_attr( self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY ) . '</code><br/>'
			. '</p>';
	}

	public function shortcode_markdown( /** @noinspection PhpUnusedParameterInspection */ $atts, $content = '' ) {
		if ( $this->has_converter ) {
			return '<div class="markdown-body markdown-content">' . GfmMarkdownExtra::defaultTransform( $content ) . '</div>';
		}
		return $content;
	}

	/**
	 * @param $atts
	 * @return string
	 */
	public function shortcode_embed_markdown( $atts ) {
		$parsed_atts = shortcode_atts( array( 'url' => '' ), $atts );
		$url         = $parsed_atts['url'];
		if ( empty( $url ) ) {
			return '';
		}

		$args     = array();
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
				. $this->shortcode_markdown( $atts, $body )
				. '<div class="markdown-meta">' . $url . $this->ad_html . '</div>'
				. '</div>';
		}
		return '';
	}

	public function force_convert( $content ) {
		$content = preg_replace( '{\[/?markdown]}', '', $content );
		return wp_markdown( $content );
	}

	public function the_content( $content ) {
		if ( class_exists( '\Gfm\Markdown\Extra' ) ) {
			if ( isset( $GLOBALS['post'] ) ) {
				if ( isset( $GLOBALS['post']->ID ) ) {
					GfmMarkdownExtra::setElementIdPrefix( 'post-' . $GLOBALS['post']->ID . '-md-' );
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

		// fallback for v0.x [gfm]
		return preg_replace_callback(
			'/\[gfm](.*?)\[\/gfm]/s',
			function ( $matches ) {
				return wp_markdown( $matches[1] );
			},
			$content
		);
	}

	public function the_content_ad( $context ) {
		if ( strpos( $context, '<div class="markdown-body markdown-content">' ) !== false ) {
			return $context . '<div class="wp-gfm-footer">' . $this->ad_html . '</div>';
		}
		return $context;
	}

	public function pre_comment_content( $comment ) {
		$comment = stripslashes( $comment );
		$comment = $this->the_content( $comment );

		return addslashes( $comment );
	}

	public function admin_quicktags() {
		if ( ! wp_script_is( 'quicktags' ) ) {
			return;
		}
		// http://wordpress.stackexchange.com/questions/37849/add-custom-shortcode-button-to-editor
		/* Add custom Quicktag buttons to the editor WordPress ver. 3.3 and above only
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
		$script = <<<'JS'
			(function ($) {
				if (typeof(QTags) != 'undefined') {
					const ids = ['markdown'];
					$.each(ids, function (index, c) {
						QTags.addButton(c, '[' + c + ']', '[' + c + ']', '[/' + c + ']');
					});
				}
			})(jQuery);
		JS;
		wp_add_inline_script( 'quicktags', $script );
	}
}

add_action( 'init', 'wp_gfm_init' );

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- WordPress plugin bootstrap pattern.

function wp_gfm_init() {
	$plugin = WP_GFM::get_instance();

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
		$plugin->php_markdown_init();
	}

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
				'requires'           => '5.0',
				'tested'             => '6.9.0',
				'readme'             => 'README.md',
			)
		);
	}
}

function wp_markdown( $content ) {
	$p = WP_GFM::get_instance();
	return $p->shortcode_markdown( null, $content );
}
