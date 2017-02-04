<?php 
namespace JsonTemplate\Callback;

class ModuleCallback extends FunctionCallback
{
	public $module;
	public function setModule($module){
		$this->module = $module;
	}
	public function call()
	{
		$args = func_get_args();
		$args = array_merge($this->args,$args);
		
		return call_user_func_array(array($this->module,$this->function),$args);
	}
}