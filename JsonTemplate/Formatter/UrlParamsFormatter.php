<?php 
namespace JsonTemplate\Formatter;

class UrlParamsFormatter extends FormatterAbstract
{
    	# The argument is an associative array, and we get a a=1&b=2 string back.
	public function format($params)
	{
		if(is_array($parmas)){
			foreach($params as $k=>$v){
				$parmas[$k] = urlencode($k)."=".urlencode($v);
			}
			return implode("&",$params);
		}else{
			return urlencode($params);
		}
	}
}