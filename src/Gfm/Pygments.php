<?php

namespace Gfm;

class Pygments
{
	static $path = "pygmentize";

	static public function pygmentize($code, $language = "php", $title = "")
	{
		$temp_name = tempnam("/tmp", "pygmentize_");

		$file_handle = fopen($temp_name, "w");
		fwrite($file_handle, $code);
		fclose($file_handle);

		$arg = "style=colorful";
		if ($title != "") {
			$arg .= ",title=${$title}";
		}
		if ($language != "") {
			$arg .= " -l ${language}";
		}

		$command = Pygments::$path . " -f html -O ${arg} ${temp_name}";
		$output = array();
		$retVal = -1;

		exec($command, $output, $retVal);

		unlink($temp_name);

		$output_string = join("\n", $output);

		return $output_string;
	}
}
