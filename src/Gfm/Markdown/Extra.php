<?php

namespace Gfm\Markdown;

\Michelf\Markdown::MARKDOWNLIB_VERSION;


class Extra extends \Michelf\_MarkdownExtra_TmpImpl
{

	public function __construct()
	{
		parent::__construct();

		$this->document_gamut += array(
			"doGfmCodeBlocks" => 5,
		);
		$this->block_gamut += array(
			"doGfmCodeBlocks" => 5,
		);
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
				# 1: Opening marker
				(
					`{3} # Marker: three `.
				)
				[ ]*
				(?:
					\.?([-_:a-zA-Z0-9]+) # 2: standalone class name
				|
					' . $this->id_class_attr_catch_re . ' # 3: Extra attributes
				)?
				[ ]* \n # Whitespace and newline following marker.

				# 4: Content
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
		$classname =& $matches[2];
		$attrs =& $matches[3];
		$codeblock = $matches[4];
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
		$codeblock = preg_replace_callback('/^\n+/',
			array(&$this, '_doGfmCodeBlocks_newlines'), $codeblock);

		if ($classname != "") {
			if ($classname{0} == '.')
				$classname = substr($classname, 1);
			$attr_str = ' class="' . $this->code_class_prefix . $classname . '"';
		} else {
			$attr_str = $this->doExtraAttributes("pre", $attrs);
		}

		$attr_str = ' class="prettyprint"';
		$codeblock = "<pre$attr_str>$codeblock</pre>";
		return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
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