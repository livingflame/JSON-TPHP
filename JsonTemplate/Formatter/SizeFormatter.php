<?php 
namespace JsonTemplate\Formatter;

class SizeFormatter extends FormatterAbstract
{
	# Used for the length of an array or a string
	public function format($obj)
	{
		if(is_string($obj)){
			return strlen($obj);
		}else{
			return count($obj);
		}
	}
}
