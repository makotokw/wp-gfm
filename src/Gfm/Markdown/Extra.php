<?php

namespace Gfm\Markdown;

\Michelf\Markdown::MARKDOWNLIB_VERSION;

class Extra extends \Michelf\_MarkdownExtra_TmpImpl
{

	public function __construct()
	{
		parent::__construct();
	}

	protected function hashHTMLBlocks($text)
	{
		$text = $this->doGfmCodeBlocks($text);
		return parent::hashHTMLBlocks($text);
	}

	protected function doGfmCodeBlocks($text)
	{
		#
		# Adding the Gfm code block syntax to regular Markdown:
		#
		# ```
		# Code block
		# ```
		#
		$less_than_tab = $this->tab_width;

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
			array(&$this, '_doGfmCodeBlocks_callback'), $text);

		return $text;
	}


	protected function _doGfmCodeBlocks_callback($matches)
	{
		$option = $matches[2];
		$codeblock = $matches[3];

		@list ($language, $title) = explode(':', $option);

		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);


		// TODO
//		if ($code = Pygments::pygmentize($codeblock)) {
//			return $code;
//		}

		$codeblock = preg_replace_callback('/^\n+/',
			array(&$this, '_doGfmCodeBlocks_newlines'), $codeblock);

		$class = 'prettyprint';
		if (!empty($language)) {
			if ($language == 'bash') $language = 'bsh';
			$class .= ' lang-'.$language;
		}
		$attr_str = ' class="'.$class.'"';
		if (!empty($title)) {
			$attr_str .= ' title="'.$title.'"';
		}
		$block = "<pre$attr_str>$codeblock</pre>";

		return "\n\n" . $this->hashBlock($block) . "\n\n";
	}

	protected function _doGfmCodeBlocks_newlines($matches)
	{
		return str_repeat("<br$this->empty_element_suffix",
			strlen($matches[0]));
	}

	protected function _doFencedCodeBlocks_callback($matches)
	{
		return $this->_doGfmCodeBlocks_callback($matches);
	}

	protected function _doCodeBlocks_callback($matches)
	{
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$codeblock = "<pre class=\"prettyprint\">$codeblock\n</pre>";
		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}

}