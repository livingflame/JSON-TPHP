<?php 
namespace JsonTemplate;

/*
 * Allows scoped lookup of variables.
 * If the variable isn't in the current context, then we search up the stack.
 */
class ScopedContext implements \Iterator
{
	protected $positions = array();
	protected $module = '';
	protected $stack;
	public function __construct($data,$module) {
		$this->module = $module;
		$this->stack = array($data);
		$this->name_stack = array('@');
	}

	public function __toString()
	{
		return sprintf("<Context %s>",implode(" ",$this->name_stack));
	}

	public function pushPredicate($name)
	{
		$end = end($this->stack);
		$token = str_replace("or ",'',$name);
		$token = str_replace("?",'',$token);

        if($classname = $this->module->getPredicate($token)){
            $predicate = new $classname();
            $new_context = $predicate->check($end);
            $this->name_stack[] = $name;
            $this->stack[] = $new_context;
            return $new_context;
        } else {
            if(is_array($end)){
                if(isset($end[$token])){
                    $new_context = $end[$token];
                }else{
                    return false;
                }
            }elseif(is_object($end)){
                // since json_decode returns StdClass
                // check if scope is an object
                if(property_exists($end,$token)){
                    $new_context = $end->$token;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }

		$this->name_stack[] = $name;
		$this->stack[] = $new_context;
		return $new_context;
	}

	public function pushSection($name)
	{
		$end = end($this->stack);
		if(is_array($end)){
			if(isset($end[$name])){
				$new_context = $end[$name];
			}else{
				return false;
			}
		}elseif(is_object($end)){
			// since json_decode returns StdClass
			// check if scope is an object
			if(property_exists($end,$name)){
				$new_context = $end->$name;
			}else{
				return false;
			}
		}else{
			return false;
		}
		$this->name_stack[] = $name;
		$this->stack[] = $new_context;
		return $new_context;
	}

	public function pop()
	{
		array_pop($this->name_stack);
		return array_pop($this->stack);
	}

	public function cursorValue()
	{
		return end($this->stack);
	}

	// Iterator functions
	// Assumes that the top of the stack is a list.
	// NOTE: Iteration alters scope
	public function rewind() {
		$this->positions[] = 0;
		$this->stack[] = array();
	}

	public function current() {
		return end($this->stack);
	}

	public function key() {
		return end($this->positions);
	}

	public function next() {
		++$this->positions[count($this->positions)-1];
	}

	public function valid() {
		$len = count($this->stack);
		$pos = end($this->positions);
		$items = $this->stack[$len-2];
		if(is_array($items) && count($items)>$pos){
			$this->stack[$len-1] = $items[$pos];
			return true;
		}else{
			array_pop($this->stack);
			array_pop($this->positions);
			return false;
		}
	}

	public function undefined($name,$use_bool = FALSE) {
		
		if(!$use_bool){
			if ($this->module->config('strict_variables')) {
				throw new \JsonTemplateUndefinedVariable(sprintf('%s is not defined',$name));
			} elseif($undefined_str = $this->module->config('undefined_str')) {
				return $undefined_str;
			} else {
				return '{' . $name . '}';
			}
		}
		return FALSE;
	}

	// Get the value associated with a name in the current context.	The current
	// context could be an associative array or a StdClass object
 	public function lookup($name,$use_bool = FALSE) {
		if ($name == '@') {
			return end($this->stack);
		}

		$parts = explode('.',$name);
		$value = $this->lookUpStack($parts[0],$use_bool);

		$count=count($parts);
		
		if ($count > 1) {
			for ($i = 1; $i < $count; $i++) {
				$namepart=$parts[$i];
				if(is_array($value)){
					if(!isset($value[$namepart])){
						return $this->undefined($name,$use_bool);
					}else{
						$value= $value[$namepart];
					}
				}elseif(is_object($value)){
					if(!property_exists($value,$namepart)){
						return $this->undefined($name,$use_bool);
					}else{
						$value= $value->$namepart;
					}
				} else {
					return $this->undefined($name,$use_bool);
				}
			}
		}
		return $value;
	}
			
	public function lookUpStack($name,$use_bool = FALSE)
	{
		$i = count($this->stack)-1;
		while(true){
			$context = $this->stack[$i];
			if ($name=='@index'){
				$key=$this->key();
				if($key==-1){
					$i--;
				} else {
					return $key+1;  // @index is 1-based
				}
			} else {
				if(is_array($context)){
					if(!isset($context[$name])){
						$i -= 1;
					}else{
						return $context[$name];
					}
				}elseif(is_object($context)){
					if(!property_exists($context,$name)){
						$i -= 1;
					}else{
						return $context->$name;
					}
				}else{
					$i -= 1;
				}
			}
			if($i<= -1){
				return $this->undefined($name,$use_bool);
			}
		}
	}
}