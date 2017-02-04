<?php 
namespace JsonTemplate\Formatter;

class RawFormatter extends FormatterAbstract
{
	public function format($str)
	{
		return $str;
	}
}