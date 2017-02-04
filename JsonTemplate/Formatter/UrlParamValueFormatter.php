<?php 
namespace JsonTemplate\Formatter;

class UrlParamValueFormatter extends FormatterAbstract
{
	public function format($param)
	{
		return urlencode($param);
	}

}