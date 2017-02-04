<?php 
namespace JsonTemplate\Formatter;

abstract class FormatterAbstract
{
	public $module;
	public $context;
	public $func;
	public $args = array();
	public function __construct($func = 'format') {
		$this->func = $func;
	}
	public function setModule($module){
		$this->module = $module;
	}
	public function addArgs($args = array()){
		$this->args = $args;
	}
	public function setContext($context){
		$this->context = $context;
	}
	public function getContext(){
		return $this->context;
	}
	public function call($val){
		$args = array();
		$args[] = $val;
		foreach($this->args as $arg){
			if ((strpos($arg, '"') !== false) || (strpos($arg, "'") !== false)) {

				if((substr($arg,0,strlen('"')) == '"') && substr($arg,-1*strlen('"')) == '"'){
					$arg = substr($arg,strlen('"'),-1*strlen('"'));
				} elseif((substr($arg,0,strlen("'")) == "'") && substr($arg,-1*strlen("'")) == "'"){
					$arg = substr($arg,strlen("'"),-1*strlen("'"));
				}

			} elseif(is_numeric($arg)) {
				$arg = $lookup;
			} elseif(strtolower($arg) === 'null' || strtolower($arg) === 'false' || strtolower($arg) === 'true') {
				switch(strtolower($arg)){
					case 'null':
						$arg = NULL;
						break;
					case 'false':
						$arg = FALSE;
						break;
					case 'true':
						$arg = TRUE;
						break;
				}
				
			} elseif($lookup = $this->context->lookup($arg,TRUE)) {
				$arg = $lookup;
			}
			$args[] = $arg;
		}
		return $this->funcCall($val,$this->func,$args);
	}
	public function funcCall($val,$func,$args){
		$method_exists = method_exists(get_class($this),$func);
		$method_callable = is_callable(array($this,$func));
		if($method_exists && $method_callable){
			return call_user_func_array(array($this,$func),$args);
		} elseif(is_callable($func,$args)) {
			return call_user_func_array($func,$args);
		}
		return $val;
	}
	public function format($str){
		return $str;
	}
}
