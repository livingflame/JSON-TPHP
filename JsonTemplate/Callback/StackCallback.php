<?php 
namespace JsonTemplate\Callback;

// stores the first parameter in an array
class StackCallback extends CallbackAbstract
{
	protected $stack = array();

	public function call()
	{
		$this->stack[] = func_get_arg(0);
	}

	public function get()
	{
		return $this->stack;
	}
}