<?php 
namespace JsonTemplate;
class Module
{
	public static $template_dir = "templates/";
	public $other_templates = array();
	public $section_re = '/(repeated)?\s*(section)\s+(\S+)?/'; 
	public $definition_re = '/^(define)\s+:(\S+)/';
	public $template_re = '/^(template)\s+(\S+)?\s*(.*)/';
	public $option_re = '/^([a-zA-Z\-]+):\s*(.*)/';
	public $or_re = '/or(?:\s+(.+))?/';
	//public $or_re = '/(or)\s+(\S+)?\s*(.*)/';
	public $if_re = '/if(?:\s+(.+))?/';
	public $option_names = array('meta','format-char','default-formatter');
	public $token_re_cache = array();
    public $config = array(
		'debug' 			=>	FALSE,
		'charset' 			=>	"UTF-8",
		'cache' 			=>	FALSE,
		'auto_reload' 		=>	FALSE,
		'strip_tags' 		=>	FALSE,
		'strict_variables' 	=>	FALSE,
		'auto_escape' 		=>	TRUE,
		'theme' 			=>	"default",
		'extension' 		=>	".jsont",
		'template_file' 	=>	'index',
		'undefined_str'		=> null,
		'meta'				=> '{}',
		'format_char' 		=> '|',
		'more_formatters'	=> null,
		'default_formatter'	=> 'str',
	);
	public $formatters = array(
		'html'				=> '\\JsonTemplate\\Formatter\\HtmlFormatter',
		'html-attr-value'	=> '\\JsonTemplate\\Formatter\\HtmlAttributeValueFormatter',
		'htmltag'			=> '\\JsonTemplate\\Formatter\\HtmlAttributeValueFormatter',
		'raw'				=> '\\JsonTemplate\\Formatter\\RawFormatter',
		'size'				=> '\\JsonTemplate\\Formatter\\SizeFormatter',
		'url-params'		=> '\\JsonTemplate\\Formatter\\UrlParamsFormatter',
		'url-param-value'	=> '\\JsonTemplate\\Formatter\\UrlParamValueFormatter',
		'str'				=> '\\JsonTemplate\\Formatter\\StringFormatter',
		'pluralize'			=> '\\JsonTemplate\\Formatter\\PluralizeFormatter',
		'template'			=> '\\JsonTemplate\\Formatter\\TemplateFormatter',
		'escape'			=> '\\JsonTemplate\\Formatter\\EscapeFormatter',
	);
	
	public $predicates = array(
		'singular'			=> '\\JsonTemplate\\Predicate\\SingularPredicate',
		'plural'			=> '\\JsonTemplate\\Predicate\\PluralPredicate',
		'Debug'			    => '\\JsonTemplate\\Predicate\\DebugPredicate',
	);

	public function __construct($dir = '')
	{
		if($dir){
			self::$template_dir = $dir;
		}
	}

	/** 
	 * provides access to class $config property
	 * 
	 * either returns a value from the $config property 
	 * or add a key=>value pair to the $config property
	 * 
	 * @access  public
	 * @param 	string $name required. should be a key from the $config property
	 * @param 	string $value optional.
	 * @return 	string returns a value from the $config property 
	 */
	public function config( $name, $value = null )
	{
        if ( func_num_args() === 1 ) {
            if ( is_array($name) ) {
                $this->config = array_merge($this->config, $name);
            } 
			else {
                return in_array($name, array_keys($this->config)) ? $this->config[$name] : null;
            }
        } else {
			if('charset' === $name){
				$value = $this->validEncodimg($value);
			}
            $this->config[$name] = $value;
        }
    }
	public function getPredicate($predicate){
        if(isset($this->predicates[$predicate])){
            return $this->predicates[$predicate];
        }
        return NULL;
    }
	public function addToTemplate($name,$content){
		if($name){
			if(!isset($this->other_templates[$name])){
				$this->other_templates[$name] = '';
			}
			$this->other_templates[$name] .= $content;
		}
	}

	/*
	 * Split and validate metacharacters.
	 *
	 * Example: '{}' -> ('{', '}')
	 *
	 * This is public so the syntax highlighter and other tools can use it.
	 */
	public function splitMeta($meta)
	{
		$n = strlen($meta);
		if($n % 2 == 1){
			throw new \JsonTemplate\Error\ConfigurationError(sprintf('%s has an odd number of metacharacters', $meta));
		}
		return array(substr($meta,0,$n/2),substr($meta,$n/2));
	}

	/* Return a regular expression for tokenization.
	 * Args:
	 *   meta_left, meta_right: e.g. '{' and '}'
	 *
	 * - The regular expressions are memoized.
	 * - This function is public so the syntax highlighter can use it.
	 */
	public function makeTokenRegex($meta_left, $meta_right)
	{
		$key = $meta_left.$meta_right;
		if(!in_array($key,array_keys($this->token_re_cache))){
			$this->token_re_cache[$key] = '/('.preg_quote($meta_left).'.+?'.preg_quote($meta_right).'\n?)/';
		}
		return $this->token_re_cache[$key];
	}

	/*
	  Compile the template string, calling methods on the 'program builder'.

	  Args:
	    template_str: The template string.  It should not have any compilation
		options in the header -- those are parsed by fromString/fromFile
	    options: array of compilation options, possible keys are:
		    meta: The metacharacters to use
		    more_formatters: A function which maps format strings to
			*other functions*.  The resulting functions should take a data
			array value (a JSON atom, or an array itself), and return a
			string to be shown on the page.  These are often used for HTML escaping,
			etc.  There is a default set of formatters available if more_formatters
			is not passed.
		    default_formatter: The formatter to use for substitutions that are missing a
			formatter.  The 'str' formatter the "default default" -- it just tries
			to convert the context value to a string in some unspecified manner.
	    builder: Something with the interface of ProgramBuilder

	  Returns:
	    The compiled program (obtained from the builder)

	  Throws:
	    The various subclasses of CompilationError.  For example, if
	    default_formatter=null, and a variable is missing a formatter, then
	    \JsonTemplate\Error\MissingFormatterError is raised.

	  This function is public so it can be used by other tools, e.g. a syntax
	  checking tool run before submitting a template to source control.
	*/
	
	public function compileTemplate($template_str, $builder)
	{
		list($meta_left,$meta_right) = $this->splitMeta( $this->config('meta') );
		$template_str = str_replace('\\'.$meta_left,'{.meta-left}',$template_str);
		$template_str = str_replace('\\'.$meta_right,'{.meta-right}',$template_str);

		# : is meant to look like Python 3000 formatting {foo:.3f}.  According to
		# PEP 3101, that's also what .NET uses.
		# | is more readable, but, more importantly, reminiscent of pipes, which is
		# useful for multiple formatters, e.g. {name|js-string|html}
		
		if(!in_array($this->config('format_char'),array(':','|'))){
			throw new \JsonTemplate\Error\ConfigurationError(sprintf('Only format characters : and | are accepted (got %s)',$this->config('format_char')));
		}

		# Need () for preg_split
		
		$token_re = $this->makeTokenRegex($meta_left, $meta_right);
		$tokens = preg_split($token_re, $template_str, -1, PREG_SPLIT_DELIM_CAPTURE);

		# If we go to -1, then we got too many {end}.  If end at 1, then we're missing
		# an {end}.
		$definition = '';
		$balance_counter = 0;
        $multiline_comment = false;
		foreach($tokens as $i=>$token){
			$orig_token = $token;
			if(($i % 2) == 0){
				if($token){
                    if($multiline_comment){
                        continue;
                    }
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						$builder->append($token);
					}
				}
			}else{
				$had_newline = false;
				if(substr($token,-1)=="\n"){
				 	$token = substr($token,0,-1);
					$had_newline = true;
				}

				//assert('substr($token,0,strlen($meta_left)) == $meta_left;');
				//assert('substr($token,-1*strlen($meta_right)) == $meta_right;');

				$token = substr($token,strlen($meta_left),-1*strlen($meta_right));

				// if it is a comment
				if(substr($token,0,1)=="#"){
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					}
                    /*  multiline comment
                    {##BEGIN}
                    {##END}
                    */
                    if($token == "##BEGIN"){
                        $multiline_comment =  TRUE;
                    }
                    
                    if($token == "##END"){
                        $multiline_comment =  FALSE;
                    }
					continue;
				}

                if($multiline_comment){
                    continue;
                }

				$literal='';
				// if it's a keyword directive
				if(substr($token,0,1)=='.'){
					$token = substr($token,1);
					switch($token){
					case 'meta-left':
						$literal = $meta_left;
						break;
					case 'meta-right':
						$literal = $meta_right;
						break;
					case 'space':
						$literal = ' ';
						break;
					case 'tab':
						$literal = "\t";
						break;
					case 'newline':
						$literal = "\n";
						break;
					}
				}

				if($literal){
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						$builder->append($literal);
					}
					continue;
				}

				if(preg_match($this->template_re,$token,$match)){
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						if(isset($this->other_templates[$match[2]])) {
							$this->compileTemplate($this->other_templates[$match[2]], $builder);
						}
					}
					continue;
				}

                if(mb_substr($token, 0, 1, 'utf-8') === ":"){

                    if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
                        $match = substr($token, 1);
						if(isset($this->other_templates[$match])) {
							$this->compileTemplate($this->other_templates[$match], $builder);
						}
					}
					continue;
                }

				if(preg_match($this->section_re,$token,$match)){
					$balance_counter += 1;
                    echo \Debug::dump($match,$token);
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						$builder->newSection($match[1],$match[3]);
					}

					continue;
				}

				if(preg_match($this->or_re,$token,$match)){
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
                        //echo \Debug::dump($match,$token);
                        $builder->newClause($match[0]);
					}
					continue;
				}

				if($token == 'alternates with'){
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						$builder->newClause($token);
					}
					continue;
				}

				if(preg_match($this->definition_re,$token,$match)){
					$definition = $match[2];
                    
					$balance_counter += 1;
					continue;
				}

				if(substr($token,-1,1)=="?"){
					$balance_counter += 1;
					if($definition){
						$this->addToTemplate($definition,$orig_token);
					} else {
						$builder->newPredicate($token);
					}
					continue;
				}

				if($token == 'end'){
					$balance_counter -= 1;

					if(!$definition){
						$builder->endSection();
					}
					if($definition && ($balance_counter > 0)){
						$this->addToTemplate($definition,$orig_token);
					} 

					if($definition && $balance_counter == 0){
						$definition = '';
					}
					if($had_newline){
						if($definition){
							$this->addToTemplate($definition,"");
						} else {
							$builder->append("");
						}
					}
					if($balance_counter < 0){
						# TODO: Show some context for errors
						throw new \JsonTemplate\Error\SyntaxError(sprintf(
							'Got too many %s.end%s statements.  You may have mistyped an '.
							"earlier %s.section%s or %s.repeated section%s directive.",
							$meta_left, $meta_right,$meta_left, $meta_right,$meta_left, $meta_right));
					}
					continue;
				}
				if($definition){
					$this->addToTemplate($definition,$orig_token);
				} else {
					# Now we know the directive is a substitution.
					$parts = explode($this->config('format_char'),$token);
					if(count($parts) == 1){
						if(!$this->config('default_formatter')){
							throw new \JsonTemplate\Error\MissingFormatterError('This template requires explicit formatters.');
							# If no formatter is specified, the default is the 'str' formatter,
							# which the user can define however they desire.
						}
						$name = $token;
						$formatters = array($this->config('default_formatter'));

					}else{
						$name = array_shift($parts);
						$formatters = $parts;
					}
                    //echo \Debug::dump($formatters);
					$builder->appendSubstitution($name,$formatters);
				}
				if($had_newline){
					if($definition){
						$this->addToTemplate($definition,"");
					} else {
						$builder->append("");
					}
				}
			}
		}
		
		if($balance_counter != 0){
			throw new \JsonTemplate\Error\SyntaxError(sprintf('Got too few %send%s statements', $meta_left, $meta_right));
		}
		//echo \Debug::dump($builder);
		//dump_debug($builder);
		//dump($builder);
		return $builder->root();
	}


	// {repeated section foo}
	public function doRepeatedSection($block, $context, $callback)
	{
		if($block->section_name == '@'){
			# If the name is @, we stay in the enclosing context, but assume it's a
			# list, and repeat this block many times.
			$items = $context->lookup('@');
			if(!is_array($items)){
				throw new \JsonTemplate\Error\EvaluationError(sprintf('Expected a list; got %s', gettype($items)));
			}
			$pushed = false;
		}else{
			$items = $context->pushSection($block->section_name);
			$pushed = true;
		}
		//echo \Debug::dump($items);
		if($items){
			$last_index = count($items) - 1;
			# NOTE: Iteration mutates the context!
			foreach($context as $i=>$data){
				# execute the statements in the block for every item in the list.  execute
				# the alternate block on every iteration except the last.
				# Each item could be an atom (string, integer, etc.) or a dictionary.
				$this->execute($block->statements('default'), $context, $callback,$block->section_name);
				if($i != $last_index){
					$this->execute($block->statements('alternates with'), $context, $callback,'alternates with');
				}
			}
			
		}else{
			$this->execute($block->statements('or'), $context, $callback,'or');
		}
		
		if($pushed){
			$context->pop();
		}
	}

	// {section foo}
	public function doPredicate($block, $context, $callback)
	{
       
		//echo debugTraceAsString(debug_backtrace());
		# If a section isn't present in the dictionary, or is None, then don't show it
		# at all.
		$res = $context->pushPredicate($block->section_name);
        $context->pop();
        
		if($res){
			$this->execute($block->statements('default'), $context, $callback,$block->section_name);
		}else{
            $keys =  $block->getAllStatementKeys();
            //$res = $context->pushPredicate($block->section_name);
            foreach($keys as $key){
                if(!$res && $key != 'or' && $key != 'default'){
                    $res = $context->pushPredicate($key);
                    $context->pop();
                    if($res){
                        
                        $this->execute($block->statements($key), $context, $callback,$key);
                    }
                }
            }

		}
        if(!$res){
            $this->execute($block->statements('or'), $context, $callback,'or');
        }
	}

	// {section foo}
	public function doSection($block, $context, $callback)
	{
		//echo debugTraceAsString(debug_backtrace());
		# If a section isn't present in the dictionary, or is None, then don't show it
		# at all.
		$res = $context->pushSection($block->section_name);
		if($res){
			$this->execute($block->statements('default'), $context, $callback,$block->section_name);
            $context->pop();
		}else{
            $this->execute($block->statements('or'), $context, $callback,'or');
		}

	}

	// Variable substitution, e.g. {foo}
	public function doSubstitute($name, $formatters, $context, $callback=null)
	{
		if(!($context instanceof \JsonTemplate\ScopedContext)){
			throw new \JsonTemplate\Error\EvaluationError(sprintf('Error not valid context %s',$context));
		}
		# So we can have {.section is_new}new since {@}{.end}.  Hopefully this idiom
		# is OK.

		if($name == '@'){
			$value = $context->cursorValue();
		}else{
			try{
				$value = $context->lookup($name);
			}catch(\JsonTemplate\Error\UndefinedVariableError $e){
				throw $e;
			}catch(Exception $e){
				throw new \JsonTemplate\Error\EvaluationError(sprintf(
					'Error evaluating %s in context %s: %s', $name, $context, $e->getMessage()
				));
			}
		}

		foreach($formatters as $f){
			try{
				$f->setContext($context);
				$value = $f->call($value);
			}catch(Exception $e){
				throw new \JsonTemplate\Error\EvaluationError(sprintf(
					'Formatting value %s with formatter %s raised exception: %s',
					 $value, $f, $e), $e);
			}
		}
		if($callback instanceof \JsonTemplate\Callback\CallbackAbstract){
			return $callback->call($value);
		}elseif(is_string($callback)){
			return $callback($value);
		}else{
			return $value;
		}
	}

	/*
	 * execute a bunch of template statements in a \JsonTemplate\ScopedContext.
  	 * Args:
     * callback: Strings are "written" to this callback function.
	 *
  	 * This is called in a mutually recursive fashion.
	 */
	public function execute($statements, $context, $callback,$name = null)
	{
		if(!is_array($statements)){
			$statements = array($statements);
		}
		foreach($statements as $i=>$statement){
			if(is_string($statement)){
				if($callback instanceof \JsonTemplate\Callback\CallbackAbstract){
					$callback->call($statement);
				}elseif(is_string($callback)){
					$callback($statement);
				}
			}else{
				try{
					if($statement instanceof \JsonTemplate\Callback\CallbackAbstract){
						$statement->call($context, $callback);
					}
				}catch(\JsonTemplate\Error\UndefinedVariableError $e){
					# Show context for statements
					$start = max(0,$i-3);
					$end = $i+3;
					$e->near = array_slice($statements,$start,$end);
					throw $e;
				}
			}
		}
	}

}
