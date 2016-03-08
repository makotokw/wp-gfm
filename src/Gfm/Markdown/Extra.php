<?php
namespace Gfm\Markdown;

use Michelf\MarkdownExtra;

class Extra extends MarkdownExtra
{
	public static $useAutoLinkExtras = false;
	public static $fencedCodeBlocksTemplate = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

	static protected $elementCssPrefix = 'gfm-';
	static protected $elementIdPrefix = 'gfm-';
	static protected $elementCounts = array();

	public function __construct() {
		parent::__construct();
		$this->span_gamut['markTableOfContents'] = 5;
		$this->document_gamut['doTableOfContents'] = 50;
	}

	/**
	 * @return string
	 */
	public static function getElementIdPrefix() {
		return self::$elementIdPrefix;
	}

	/**
	 * @param string $idPrefix
	 */
	public static function setElementIdPrefix( $idPrefix ) {
		self::$elementIdPrefix = $idPrefix;
	}

	/**
	 * @return string
	 */
	public static function getElementCssPrefix() {
		return self::$elementCssPrefix;
	}

	/**
	 * @param string $elementCssPrefix
	 */
	public static function setElementCssPrefix( $elementCssPrefix ) {
		self::$elementCssPrefix = $elementCssPrefix;
	}

	/**
	 * @return string
	 */
	public static function createElementId() {
		if ( ! array_key_exists( self::$elementIdPrefix, self::$elementCounts ) ) {
			self::$elementCounts[ self::$elementIdPrefix ] = 0;
		}
		self::$elementCounts[ self::$elementIdPrefix ]++;
		return self::$elementIdPrefix . self::$elementCounts[ self::$elementIdPrefix ];
	}

	public static function resetElementCount() {
		self::$elementCounts = array();
	}

	protected function teardown() {
		parent::teardown();
	}

	protected function markTableOfContents( $text ) {
		if ( preg_match( '/^\[(|>)TOC\]$/i', $text, $tocMatches ) ) {
			$block = ( '' == $tocMatches[1] ) ? 'LTOC' : 'RTOC';
			$hash = sha1( time() );
			return $block . $hash;
		}
		return $text;
	}

	protected function doTableOfContents( $text ) {
		#
		# Adds TOC support by including the following on a single line:
		#
		# [TOC]
		#
		# TOC Requirements:
		#     * Only headings 2-6
		#     * Headings must have an ID
		#     * Builds TOC with headings _after_ the [TOC] tag

		if ( preg_match( '/(L|R)TOC[\w]{40}/mi', $text, $tocMatches, PREG_OFFSET_CAPTURE ) ) {
			$mark = $tocMatches[0][0];
			$toc = '';
			if ( preg_match_all( '/<h([2-6]) id="([0-9a-z_-]+)">(.*?)<\/h\1>/i', $text, $headers, PREG_SET_ORDER, $tocMatches[0][1] ) ) {
				$alignCls = 'R' == $tocMatches[1][0] ? 'right' : 'left';
				$cls = self::getElementCssPrefix();
				$toc .= <<<"EOF"
<div class="{$cls}toc-content {$alignCls}">
EOF;
				$prevLevel = 0;
				foreach ( $headers as $header ) {
					$level = (int) $header[1] - 1; // 2-origin to 1-origin
					$anchorId = $header[2];
					$label = $header[3];
					if ( $prevLevel < $level ) {
						for ( $i = 0; $prevLevel + $i < $level; $i++ ) {
							$toc .= '<ul>';
						}
					} else if ( $prevLevel > $level ) {
						for ( $i = 0; $prevLevel - $i > $level; $i++ ) {
							$toc .= '</ul>';
						}
					}
					$toc .= '<li><a href="#' . $anchorId . '">' . htmlspecialchars( $label ) . '</a></li>' . PHP_EOL;
					$prevLevel = $level;
				}
				while ( $prevLevel > 0 ) {
					$toc .= '</ul>';
					$prevLevel--;
				}
				$toc .= <<<"EOF"
</div>
EOF;
			}
			$text = str_replace( $mark, $toc, $text );
		}

		return trim( $text, "\n" );
	}

	protected function doHeaders( $text ) {
		#
		# Redefined to add id attribute support.
		#
		# Setext-style headers:
		#     Header 1  {#header1}
		#     ========
		#
		#     Header 2  {#header2}
		#     --------
		#
		$text = preg_replace_callback(
			'{
				(^.+?)                              # $1: Header text
				(?:[ ]+\{\#([-_:a-zA-Z0-9]+)\})?    # $2: Id attribute
				[ ]*\n(=+|-+)[ ]*\n+                # $3: Header footer
			}mx',
			array( $this, '_doHeaders_callback_setext' ),
			$text
		);

		# atx-style headers:
		#   # Header 1        {#header1}
		#   ## Header 2       {#header2}
		#   ## Header 2 with closing hashes ##  {#header3}
		#   ...
		#   ###### Header 6   {#header2}
		#
		$text = preg_replace_callback(
			'{
				 ^(\#{1,6})  # $1 = string of #\'s
				 [ ]*
				 (.+?)       # $2 = Header text
				 [ ]*
				 \#*         # optional closing #\'s (not counted)
				 (?:[ ]+\{\#([-_:a-zA-Z0-9]+)\})? # id attribute
				 [ ]*\n+
			}xm',
			array( $this, '_doHeaders_callback_atx' ),
			$text
		);
		return $text;
	}

	protected function _doHeaders_attr( $attr ) {
		if ( empty( $attr ) ) {
			return '';
		}
		return " id=\"$attr\"";
	}

	protected function _doHeaders_callback_setext( $matches ) {
		if ( '-' == $matches[3] && preg_match( '{^- }', $matches[1] ) ) {
			return $matches[0];
		}
		$level = '=' == $matches[3]{0}  ? 1 : 2;
		$attr = $this->_doHeaders_attr( $id =& $matches[2] );
		$block = "<h$level$attr>" . $this->runSpanGamut( $matches[1] ) . "</h$level>";
		return "\n" . $this->hashBlock( $block ) . "\n\n";
	}

	protected function _doHeaders_callback_atx( $matches ) {
		$level = strlen( $matches[1] );
		$attr = isset($matches[3]) ? $matches[3] : '';
		if ( empty( $attr ) ) {
			$attr = '#' . $this->createElementId();
		} else {
			$attr = '#' . $attr;
		}
		$attr = $this->doExtraAttributes( "h$level", $attr );
		$block = "<h$level$attr>" . $this->runSpanGamut( $matches[2] ) . "</h$level>";
		return "\n" . $this->hashBlock( $block ) . "\n\n";
	}

	protected function doAutoLinks( $text ) {
		$text = parent::doAutoLinks( $text );
		if ( self::$useAutoLinkExtras ) {
			$text = $this->doAutoLinksExtra( $text );
		}
		return $text;
	}

	protected function doAutoLinksExtra( $text ) {
		$text = preg_replace_callback(
			'{((https?|ftp)://[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|])}i',
			array( $this, '_doAutoLinks__extra_url_callback' ),
			$text
		);
		return $text;
	}

	protected function _doAutoLinks__extra_url_callback( $matches ) {
		$url = $this->encodeAttribute( $matches[1] );
		$link = '<a href="' . $url . '">' . $url . '</a>';
		return $this->hashPart( $link );
	}

	protected function doFencedCodeBlocks( $text ) {
		#
		# Adding the Gfm code block syntax to regular Markdown:
		#
		# ```
		# Code block
		# ```
		#
		$text = preg_replace_callback('{
				(?:\n|\A)
				# 1: Opening marker three `.
				(`{3})

				(|.+) # 2: Language:title
				[ ]* \n # Whitespace and newline following marker.

				# 3: Content
				(
					(?>
						(?!\1 [ ]* \n)	# Not a closing marker.
						.*\n+
					)+
				)

				# Closing marker.
				\1 [ ]* \n
			}xm',
			array( $this, '_doFencedCodeBlocks_callback' ),
			$text
		);

		return $text;
	}

	protected function _doFencedCodeBlocks_callback( $matches ) {
		$option = $matches[2];
		$code_block = $matches[3];

		$option = explode( ':', $option );
		$lang = $option[0];
		$title = ( count( $option ) >= 2 ) ? $option[1] : '';

		$code_block = htmlspecialchars( $code_block, ENT_NOQUOTES );

		$code_block = preg_replace_callback(
			'/^\n+/',
			array( $this, '_doFencedCodeBlocks_newlines' ),
			$code_block
		);

		$block = $this->applyCodeBlockTemplate( $lang, $title, $code_block );

		return "\n\n" . $this->hashBlock( $block ) . "\n\n";
	}

	protected function _doFencedCodeBlocks_newlines( $matches ) {
		return str_repeat(
			"<br$this->empty_element_suffix",
			strlen( $matches[0] )
		);
	}

	protected function _doCodeBlocks_callback( $matches ) {
		$code_block = $matches[1];

		$code_block = $this->outdent( $code_block );
		$code_block = htmlspecialchars( $code_block, ENT_NOQUOTES );

		# trim leading newlines and trailing newlines
		$code_block = preg_replace( '/\A\n+|\n+\z/', '', $code_block );

		$block = $this->applyCodeBlockTemplate( '', '', $code_block );

		return "\n\n" . $this->hashBlock( $block ) . "\n\n";
	}

	protected function applyCodeBlockTemplate( $lang, $title, $codeblock ) {
		list ($before, $after) = explode( '{{codeblock}}', self::$fencedCodeBlocksTemplate );

		if ( strpos( $before, 'prettyprint' ) !== false ) {

			// The lang-* class specifies the language file extensions.
			// File extensions supported by default include
			// "bsh", "c", "cc", "cpp", "cs", "csh", "cyc", "cv", "htm", "html",
			// "java", "js", "m", "mxml", "perl", "pl", "pm", "py", "rb", "sh",
			// "xhtml", "xml", "xsl".
			if ( ! empty( $lang ) ) {
				$fileExtensions = array(
					'ruby' => 'rb',
					'bash' => 'bsh',
				);
				if ( array_key_exists( $lang, $fileExtensions ) ) {
					$lang = $fileExtensions[ $lang ];
				}
			}
		}

		foreach ( array( 'lang' => $lang, 'title' => $title ) as $name => $value ) {
			if ( is_null( $value ) ) {
				$value = '';
			}
			$name = '{{' . $name . '}}';
			$before = str_replace( $name, $value, $before );
			$after = str_replace( $name, $value, $after );
		}

		return $before . $codeblock . $after;
	}
}
