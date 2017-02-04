<?php 
namespace JsonTemplate\Formatter;

class GenericFormatter extends FormatterAbstract
{
	public function funcCall($val,$func,$args){
		if($func instanceof \JsonTemplate\Callback\CallbackAbstract){
			return $func->call($args);
		} elseif(is_callable($func,$args)) {
			return call_user_func_array($func,$args);
		}
		return $val;
	}
}