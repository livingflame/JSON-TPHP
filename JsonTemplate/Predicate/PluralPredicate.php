<?php 
namespace JsonTemplate\Predicate;

class PluralPredicate{
    public function check($quantity){
        if(is_array($quantity) || (class_exists('Countable') && $quantity instanceof \Countable)){
            $quantity = count($quantity);
        } 
        if(is_string($quantity)){
            $quantity = (int) $quantity;
        }
		return ($quantity < 2 ) ? FALSE : TRUE;
    }
}
?>