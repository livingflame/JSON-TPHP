<?php 
namespace JsonTemplate;

/*
 * Receives method calls from the parser, and constructs a tree of Section
 * instances.
 */

class ProgramBuilder
{
	
	public $current_block;
	public $module;
	public $stack;
	public $more_formatters;
	/*
	more_formatters: A function which returns a function to apply to the
	value, given a format string.  It can return null, in which case the
	DefaultFormatters class is consulted.
	*/
	public function __construct($section,$module)
	{
		$this->module = $module;
		$this->current_block = $section;
		$this->stack = array($this->current_block);
	}

        // statement: append a literal
	public function append($statement)
	{
		$this->current_block->append($statement);
	}

        // The user's formatters are consulted first, then the default formatters.
	private function getFormatter($format_str)
	{
		$more_formatters = $this->module->config('more_formatters');
		$orig_format_str = $format_str;
		$args = array();
		$func = 'format';
		$slash = explode('/', $format_str);
		
		if(count($slash) == 1){
			$space = explode(' ', $format_str);
			$format_str = $space[0];
			unset($space[0]);
			$args = $space;
			unset($space);
		} else {
			$format_str = $slash[0];
			unset($slash[0]);
			$args = $slash;
			unset($slash);
		}
		if(!empty($more_formatters)){
			if($more_formatters instanceof \JsonTemplate\Callback\CallbackAbstract){
				$func = $more_formatters;
			}elseif(is_array($more_formatters) && isset($more_formatters[$format_str])){
				$func = $more_formatters[$format_str];
			}elseif(is_callable($more_formatters)){
				$func = $more_formatters;
			}
		}
		
		if(isset($this->module->formatters[$format_str])){
			$formatter = $this->module->formatters[$format_str];
		} else {
			$formatter = 'JsonTemplate\\Formatter\\GenericFormatter';
		}
		
		
		$formatter_obj = new $formatter($func);
		$formatter_obj->setModule($this->module);
		$formatter_obj->addArgs($args);

		return $formatter_obj;
		/*  throw new \JsonTemplate\Error\BadFormatterError(sprintf('%s is not a valid formatter', $format_str));  */
	}

	public function appendSubstitution($name, $formatters)
	{
		foreach($formatters as $k=>$f){
			$formatters[$k] = $this->getFormatter($f);
		}

		$callback = new \JsonTemplate\Callback\ModuleCallback('doSubstitute', $name, $formatters);
		$callback->setModule($this->module);
		$this->current_block->append($callback);
	}

    	// For sections or repeated sections
	public function newPredicate($section_name)
	{
        echo \Debug::dump($section_name);
		$new_block = new \JsonTemplate\Section($section_name);
		$callback = new \JsonTemplate\Callback\ModuleCallback('doPredicate', $new_block);
		$callback->setModule($this->module);
		$this->current_block->append($callback);
		$this->stack[] = $new_block;
		$this->current_block = $new_block;
	}

    	// For sections or repeated sections
	public function newSection($repeated, $section_name)
	{
		$new_block = new \JsonTemplate\Section($section_name);
		$func = ($repeated) ? 'doRepeatedSection' : 'doSection';
		
		$callback = new \JsonTemplate\Callback\ModuleCallback($func, $new_block);
		$callback->setModule($this->module);
		$this->current_block->append($callback);
		$this->stack[] = $new_block;
		$this->current_block = $new_block;
	}

	/*
	 * TODO: throw errors if the clause isn't appropriate for the current block
	 * isn't a 'repeated section' (e.g. alternates with in a non-repeated
	 * section)
	 */
	public function newClause($name)
	{
		$this->current_block->newClause($name);
	}


	public function endSection()
	{
		array_pop($this->stack);
		$this->current_block = end($this->stack);
	}

	public function root()
	{
		return $this->current_block;
	}
}