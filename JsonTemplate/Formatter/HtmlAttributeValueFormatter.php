<?php 
namespace JsonTemplate\Formatter;

class HtmlAttributeValueFormatter extends FormatterAbstract
{
	public function format($str)
	{
		return htmlspecialchars($str);
	}

}
