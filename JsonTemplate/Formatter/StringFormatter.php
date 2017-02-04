<?php 
namespace JsonTemplate\Formatter;

class StringFormatter extends FormatterAbstract
{
	public function format($str)
	{
		if ($str===null)
			return 'null';
		return (string)$str;
	}
}