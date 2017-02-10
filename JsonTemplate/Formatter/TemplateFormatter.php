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
        echo \Debug::dump($data);
        echo \Debug::dump($template);
        echo \Debug::dump($var);
		if($var){
			$data = array(
				$var => $data
			);
		}

		if(isset($this->module->other_templates[$template])){
			return $this->template->fromString($this->module->other_templates[$template],$data);
		} else {
            $dir = $this->module->config('template_dir');
            $tpl_file = $dir . $template;
            if(substr($template,-6,6)===".jsont" || file_exists($tpl_file . '.jsont')){
                return $this->template->fromFile($tpl_file,$data);
            } else {
                throw new \JsonTemplate\Error\NotFoundTemplateError(sprintf(
					'Unable to find template (got %s)',$template));
            }

		}
		return NULL;
	}
}