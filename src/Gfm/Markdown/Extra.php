<?php

namespace Gfm\Markdown;

use Gfm\Pygments;

\Michelf\Markdown::MARKDOWNLIB_VERSION;

class Extra extends \Michelf\_MarkdownExtra_TmpImpl
{
	public static $useAutoLinkExtras = false;
	public static $fencedCodeBlocksTemplate = '<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>';

	public function __construct()
	{
		parent::__construct();
	}

	protected function hashHTMLBlocks($text)
	{
		$text = $this->doFencedCodeBlocks($text);
		return parent::hashHTMLBlocks($text);
	}

	protected function doAutoLinks($text)
	{
		$text = parent::doAutoLinks($text);
		if (self::$useAutoLinkExtras) {
			$text = $this->doAutoLinksExtra($text);
		}
		return $text;
	}

	protected function doAutoLinksExtra($text)
	{
		$text = preg_replace_callback('{([^\'"])((https?|ftp|dict):[^\'">\s]+)}i',
			array(&$this, '_doAutoLinks__extra_url_callback'), $text);
		return $text;
	}

	protected function _doAutoLinks__extra_url_callback($matches) {
		$url = $this->encodeAttribute($matches[2]);
		$link = "<a href=\"$url\">$url</a>";
		return $matches[1] . $this->hashPart($link);
	}

	protected function doFencedCodeBlocks($text)
	{
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
			array(&$this, '_doFencedCodeBlocks_callback'), $text);

		return $text;
	}


	protected function _doFencedCodeBlocks_callback($matches)
	{
		$option = $matches[2];
		$codeblock = $matches[3];

		@list ($lang, $title) = explode(':', $option);

		// TODO
//		if ($code = Pygments::pygmentize($codeblock)) {
//			return $code;
//		}

		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		$codeblock = preg_replace_callback('/^\n+/',
			array(&$this, '_doFencedCodeBlocks_newlines'), $codeblock);

		$block = $this->applyCodeBlockTemplate($lang, $title, $codeblock);

		return "\n\n" . $this->hashBlock($block) . "\n\n";
	}

	protected function _doFencedCodeBlocks_newlines($matches)
	{
		return str_repeat("<br$this->empty_element_suffix",
			strlen($matches[0]));
	}

	protected function _doCodeBlocks_callback($matches)
	{
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$block = $this->applyCodeBlockTemplate('', '', $codeblock);

		return "\n\n".$this->hashBlock($block)."\n\n";
	}

	protected function applyCodeBlockTemplate($lang, $title, $codeblock)
	{
		list ($before, $after) = explode('{{codeblock}}', self::$fencedCodeBlocksTemplate);

		if (strpos($before, 'prettyprint') !== false) {

			// The lang-* class specifies the language file extensions.
			// File extensions supported by default include
			// "bsh", "c", "cc", "cpp", "cs", "csh", "cyc", "cv", "htm", "html",
			// "java", "js", "m", "mxml", "perl", "pl", "pm", "py", "rb", "sh",
			// "xhtml", "xml", "xsl".
			if (!empty($lang)) {
				$fileExtensions = array(
					'ruby' => 'rb',
					'bash' => 'bsh',
				);
				if (array_key_exists($lang, $fileExtensions)) {
					$lang = $fileExtensions[$lang];
				}
			}
		}

		foreach (array('lang' => $lang, 'title' => $title) as $name => $value) {
			if (is_null($value)) {
				$value = '';
			}
			$name = '{{' . $name . '}}';
			$before = str_replace($name, $value, $before);
			$after = str_replace($name, $value, $after);
		}

		return $before . $codeblock . $after;
	}
}