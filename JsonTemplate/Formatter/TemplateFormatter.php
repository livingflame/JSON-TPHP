<?php 
namespace JsonTemplate\Formatter;

class TemplateFormatter extends FormatterAbstract
{
	
	public $template;
	public function __construct() {
		$this->func = 'template';
		$this->template = \JsonTemplate\Presentation::factory();
	}
	public function template($data,$template,$var = NULL)
	{
		if($var){
			$data = array(
				$var => $data
			);
		}

		$dir = $this->module->config('template_dir');
		$tpl_file = $dir . $template;
		if(substr($template,-6,6)===".jsont" && file_exists($tpl_file)){
			return $this->template->fromFile($tpl_file,$data);
		} elseif(isset($this->module->other_templates[$template])){
			return $this->template->fromString($this->module->other_templates[$template],$data);
		} else {
			throw new \JsonTemplate\Error\NotFoundTemplateError(sprintf(
					'Unable to find template (got %s)',$template));
		}
		return NULL;
	}
}