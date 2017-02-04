<?php 
namespace JsonTemplate\Formatter;

class HtmlFormatter extends FormatterAbstract
{
	public function format($str)
	{
		return htmlspecialchars($str,ENT_NOQUOTES);
	}
}