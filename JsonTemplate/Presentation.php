<?php 
namespace JsonTemplate;

/*
Represents a compiled template.

Like many template systems, the template string is compiled into a program,
and then it can be expanded any number of times.  For example, in a web app,
you can compile the templates once at server startup, and use the expand()
method at request handling time.  expand() uses the compiled representation.

There are various options for controlling parsing -- see compileTemplate.
Don't go crazy with metacharacters.  {}, [], {{}} or <> should cover nearly
any circumstance, e.g. generating HTML, CSS XML, JavaScript, C programs, text
files, etc.
*/

class Presentation
{
	protected $program;
	protected $module;
	protected $builder;


	/*
	Args:
	template_str: The template string.

	It also accepts all the compile options that compileTemplate does.
	*/
	public function __construct($module,$builder)
	{
		$this->module = $module;
		$this->builder = $builder;
	}

	public static function factory($dir = ''){
		$module = new \JsonTemplate\Module( $dir );
		$section = new \JsonTemplate\Section();
		$builder = new \JsonTemplate\ProgramBuilder($section,$module);
		return  new \JsonTemplate\Presentation($module,$builder);
	}

	/*
	 * This function add the passed options to the default options.
	 * If the passed options are in object form, they are converted 
	 * to associative array form first, so the class can always 
	 * access options in the array notation.
	 */
	public function processDefaultOptions( $options = array() ){
		if(is_string($options)){
			$options = json_decode($options);
		}
		$compile_options = array(
			'undefined_str'		=> null,
			'meta'				=> '{}',
			'format_char' 		=> '|',
			'more_formatters'	=> null,
			'default_formatter'	=> 'str',
		);
		if(is_object($options)){
			$options = array_merge($compile_options,get_object_vars($options));
		}else if(is_array($options)){
			$options = array_merge($compile_options,$options);
		}else{
			$options = $compile_options;
		}
		return $options;
	}

	public function addFormatter($name,$formatter){
		$this->module->addFormatter($name,$formatter);
	}

  	// Like fromString, but takes a file.
	public function fromFile($file,$data)
	{
		$file = \JsonTemplate\Module::$template_dir .  $file;
		if(is_string($file)){
			$string = file_get_contents($file);
		}else{
			while(!feof($file)){
				$string .= fgets($file,1024)."\n";
			}
		}
		return $this->fromString($string,$data);
	}

	/*
	Parse a template from a string, using a simple file format.

	This is useful when you want to include template options in a data file,
	rather than in the source code.

	The format is similar to HTTP or E-mail headers.  The first lines of the file
	can specify template options, such as the metacharacters to use.  One blank
	line must separate the options from the template body.

	Example:

	default-formatter: none
	meta: {{}}
	format-char: :
	<blank line required>
	Template goes here: {{variable:html}}
	*/

	public function fromString($string,$data)
	{
		$options = array();
		$lines = explode("\n",$string);
		foreach($lines as $k=>$line){
			if(preg_match($this->module->option_re,$line,$match)){
			# Accept something like 'Default-Formatter: raw'.  This syntax is like
			# HTTP/E-mail headers.
				$name = strtolower($match[1]);
				$value = trim($match[2]);
				if(in_array($name,$this->module->option_names)){
					$name = str_replace('-','_',$name);
					if($name == 'default_formatter' && strtolower($value) == 'none'){
						$value = null;
					}
					$options[$name] = $value;
				}else{
					break;
				}
			}else{
				break;
			}
		}

		if($options){
			if(trim($line)){
				throw new \JsonTemplate\Error\CompilationError(sprintf(
					'Must be one blank line between template options and body (got %s)',$line));
			}
			$body = implode("\n",array_slice($lines,$k+1));
		}else{
			# There were no options, so no blank line is necessary.
			$body = $string;
		}
		$compiled = $this->compile($body,$options);
		return $this->render($compiled,$data);
	}

	public function compile($template_str,$options = array()){
		$this->module->config($this->processDefaultOptions($options));
		$program = $this->module->compileTemplate($template_str, $this->builder);
		return $program->statements();
	}

	public function expand($statements,$data)
	{
		$c = new \JsonTemplate\Callback\StackCallback();
		$this->module->execute(
			$statements, 
			new \JsonTemplate\ScopedContext($data,$this->module), 
			$c
		);
		return $c->get();
	}

	public function render($template,$data = array())
	{
		if(is_string($data)){
			$data = json_decode($data);
		}
		return implode('',$this->expand($template,$data));
	}
}