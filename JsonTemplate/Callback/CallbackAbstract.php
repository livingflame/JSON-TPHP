<?php 
namespace JsonTemplate\Callback;

/*
 * represents a callback since PHP has no equivalent
 */
abstract class CallbackAbstract
{
	abstract public function call();
}