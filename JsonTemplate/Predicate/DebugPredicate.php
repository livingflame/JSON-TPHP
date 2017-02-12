<?php 
namespace JsonTemplate\Predicate;

class DebugPredicate
{
    public function check($stack){
        $new_context = FALSE;
		if(is_array($stack) && isset($stack['debug'])){
			$new_context = $stack['debug'];
		}elseif(is_object($stack) && property_exists($stack,'debug')){
			$new_context = $stack->debug;
		}
        return $new_context;
    }
}
?>