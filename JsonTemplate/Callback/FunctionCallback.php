<?php 
namespace JsonTemplate\Callback;

// calls a function passing all the parameters
class FunctionCallback extends CallbackAbstract
{
	protected $function = '';
	protected $args = array();

	public function __construct()
	{
		
		$args = func_get_args();
		
		$this->function = array_shift($args);
		$this->args = $args;
	}

	public function call()
	{
		$args = func_get_args();
		$args = array_merge($this->args,$args);
		return call_user_func_array($this->function,$args);
	}
}