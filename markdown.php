<?php

/*
 Plugin Name: GitHub Flavored Markdown for WordPress
 Plugin URI: https://github.com/makotokw/wp-gfm
 Version: 0.7.3
 Description: Converts block in GitHub Flavored Markdown by using shortcode [gfm] and support PHP-Markdown by using shortcode [markdown]
 Author: makoto_kw
 Author URI: http://makotokw.com/
 License: MIT
 */

class WP_GFM
{
	const NAME = 'WP_GFM';
	const VERSION = '0.7.3';
	const DEFAULT_RENDER_URL = 'https://api.github.com/markdown/raw';

	// google-code-prettify: https://code.google.com/p/google-code-prettify/
	const FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

	public $agent         = '';
	public $url           = '';
	public $has_converter = false;
	public $gfm_options   = array();

	static function get_instance() {
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
			(array)get_option( 'gfm' ),
			array(
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
	}

	function wp_enqueue_styles() {
		wp_enqueue_style( 'wp-gfm', $this->url . '/css/markdown.css', array(), self::VERSION );
	}

	function php_markdown_init() {
		if ( class_exists( '\Gfm\Markdown\Extra' ) ) {
			$this->has_converter = true;
			\Gfm\Markdown\Extra::setElementCssPrefix( 'wp-gfm-' );
			\Gfm\Markdown\Extra::$useAutoLinkExtras        = $this->gfm_options['php_md_use_autolink'] == true;
			\Gfm\Markdown\Extra::$fencedCodeBlocksTemplate = $this->gfm_options['php_md_fenced_code_blocks_template'];
		}

		if ( $this->gfm_options['php_md_always_convert'] ) {
			add_action( 'the_content', array( $this, 'force_convert' ), 7 );
		} else {
			add_action( 'the_content', array( $this, 'the_content' ), 7 );
		}

		if ( $this->gfm_options['general_ad'] ) {
			add_action( 'the_content', array( $this, 'the_content_ad' ), 8 );
		}

		add_filter( 'pre_comment_content', array( $this, 'pre_comment_content' ), 5 );
	}

	function admin_init() {
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
			'Fenced Code Blocks Tempalte',
			array( $this, 'create_gfm_php_md_fenced_code_blocks_template_field' ),
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
			array( $this, 'create_gfm_render_url_field' ),
			'gfm-setting-admin',
			'setting_section_gfm'
		);
	}

	function admin_menu() {
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

	function options_page() {
		?>
		<div class="wrap wrap-wp-gfm">

			<h2>WP GFM Settings</h2>

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

	function option_sanitize_gfm( $input ) {
		if ( get_option( 'gfm' ) === false ) {
			add_option( 'gfm', $input );
		} else {
			update_option( 'gfm', $input );
		}
		return $input;
	}

	function print_section_general() {
	}

	function create_gfm_general_ad_field() {
		echo '<input type="checkbox" id="general_ad" name="gfm_array[general_ad]" value="1" class="code" '
			. checked( 1, $this->gfm_options['general_ad'], false ) . ' /> Add a link of wp-gfm plugin to content';
	}

	function print_section_php_markdown() {
	}

	function create_gfm_php_md_always_convert_field() {
		echo '<input type="checkbox" id="php_md_always_convert" name="gfm_array[php_md_always_convert]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_always_convert'], false ) . ' /> All contents are markdown!'
			. '<p class="description">The plugin converts content even if it is not surrounded by [markdown]</p>';
	}

	function create_gfm_php_md_use_autolink_field() {
		echo '<input type="checkbox" id="gfm_php_md_use_autolink" name="gfm_array[php_md_use_autolink]" value="1" class="code" '
			. checked( 1, $this->gfm_options['php_md_use_autolink'], false ) . ' /> Use AutoLink';
	}

	function create_gfm_php_md_fenced_code_blocks_template_field() {
		$value = esc_attr( $this->gfm_options['php_md_fenced_code_blocks_template'] );
		echo '<textarea id="gfm_php_md_fenced_code_blocks_template" name="gfm_array[php_md_fenced_code_blocks_template]" class="large-text">' . $value . '</textarea>'
			. '<p class="description">'
			. '{{lang}}, {{title}}, {{codeblock}}<br/>'
			. 'For <a href="https://code.google.com/p/google-code-prettify/" target="_blank">google-code-prettify</a>: <code>' . esc_attr( self::FENCED_CODE_BLOCKS_TEMPLATE_FOR_GOOGLE_CODE_PRETTIFY ) . '</code><br/>'
			. '</p>';
	}

	function print_section_gfm() {
	}

	function create_gfm_render_url_field() {
		$value = esc_attr( $this->gfm_options['render_url'] );
		echo '<input type="text" id="gfm_render_url" name="gfm_array[render_url]" value="' . $value . '" class="regular-text"/>';
	}

	function shortcode_gfm( $atts, $content = '' ) {
		$renderUrl = @$this->gfm_options['render_url'];
		return '<div class="gfm-content">' . $this->convert_html_by_render_url( $renderUrl, $content ) . '</div>';
	}

	function shortcode_markdown( $atts, $content = '' ) {
		if ( $this->has_converter ) {
			return '<div class="markdown-content">' . \Gfm\Markdown\Extra::defaultTransform( $content ) . '</div>';
		}
		return $content;
	}

	function force_convert( $content ) {
		$content = preg_replace( '{\[/?markdown\]}', '', $content );
		return wp_markdown( $content );
	}

	function the_content( $content ) {
		if ( class_exists( '\Gfm\Markdown\Extra' ) ) {
			if ( isset($GLOBALS['post']) ) {
				if ( isset($GLOBALS['post']->ID) ) {
					\Gfm\Markdown\Extra::setElementIdPrefix( 'post-' . $GLOBALS['post']->ID . '-md-' );
				}
			}
		}
		$content = preg_replace_callback( '/\[markdown\](.*?)\[\/markdown\]/s', create_function( '$matches', 'return wp_markdown($matches[1]);' ), $content );
		$content = preg_replace_callback( '/\[gfm\](.*?)\[\/gfm\]/s', create_function( '$matches', 'return wp_fgm($matches[1]);' ), $content );
		return $content;
	}

	function the_content_ad( $context ) {
		if ( strpos( $context, '<div class="markdown-content">' ) !== false ) {
			return $context . '<div class="wp-gfm-ad"><span class="wp-gfm-powered-by">With Markdown By <a href="https://github.com/makotokw/wp-gfm" target="_blank">wp-gfm</a></span></div>';
		}
		return $context;
	}

	function pre_comment_content( $comment ) {
		$comment = stripslashes( $comment );
		$comment = $this->the_content( $comment );
		$comment = addslashes( $comment );
		return $comment;
	}

	function convert_html_by_render_url( $renderUrl, $text ) {
		$response = wp_remote_request(
			$renderUrl,
			array(
				'method'     => 'POST',
				'timeout'    => 10,
				'user-agent' => $this->agent,
				'headers'    => array( 'Content-Type' => 'text/plain; charset=UTF-8' ),
				'body'       => $text,
			)
		);
		if ( is_wp_error( $response ) ) {
			$msg = self::NAME . ' HttpError: ' . $response->get_error_message();
			error_log( $msg . ' on ' . $renderUrl );
			return $msg;
		}

		if ( $response && isset($response['response']['code']) && $response['response']['code'] != 200 ) {
			$msg = sprintf( self::NAME . ' HttpError: %s %s', $response['response']['code'], $response['response']['message'] );
			error_log( $msg . ' on ' . $renderUrl );
			return $msg;
		}
		return wp_remote_retrieve_body( $response );
	}


	function admin_quicktags()
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
		 */ {
		?>
		<script type="text/javascript">
			(function ($) {
				if (typeof(QTags) != 'undefined') {
					$.each(['markdown', 'gfm'], function (index, c) {
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
	$plugin = WP_GFM::get_instance();

	// use Michelf/Markdown if PHP 5.3+
	if ( defined( 'PHP_VERSION_ID' ) ) {
		if ( PHP_VERSION_ID >= 50300 ) {
			if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
				require_once dirname( __FILE__ ) . '/vendor/autoload.php';
				$plugin->php_markdown_init();
			}
		}
	}

	include_once 'updater.php';
	if ( is_admin() && class_exists( 'WP_GitHub_Updater' ) ) {
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
				'tested'             => '3.9',
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
	if ( ! empty($p->gfm_options['render_url']) ) {
		return $p->shortcode_gfm( null, $content );
	} else {
		return $p->shortcode_markdown( null, $content );
	}
}
