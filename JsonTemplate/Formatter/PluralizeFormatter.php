<?php 
namespace JsonTemplate\Formatter;

class PluralizeFormatter extends FormatterAbstract
{
	public $args  = array(NULL,'s');
	public function __construct() {
		$this->func = 'pluralize';
	}
	public function addArgs($args = array()){
		$count = 0;
		if(count($args) == 1){
			$count = 1;
		}
		foreach($args as $arg){
			$this->args[$count] = $arg;
			$count++;
		}
	}
	public function pluralize($quantity, $singular = null, $plural='s'){
        if(is_array($quantity) || (class_exists('Countable') && $quantity instanceof \Countable)){
            $quantity = count($quantity);
        } 
        if(is_string($quantity)){
            $quantity = (int) $quantity;
        }
		return ($quantity < 2 ) ? $singular : $plural;
	}
}