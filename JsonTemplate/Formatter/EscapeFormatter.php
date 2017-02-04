<?php 
namespace JsonTemplate\Formatter;

class EscapeFormatter extends FormatterAbstract
{
	protected $supported_encoding = array();
	public function __construct() {
		$this->func = 'escape';
	}
	public function validEncodimg($encoding){
		if(empty($this->supported_encoding)){
			$this->supported_encoding = mb_list_encodings();
		}

		if( in_array($encoding,$this->supported_encoding) ){
			return $encoding;
		} else {
			throw new \InvalidArgumentException('Invalid encoding provided!');
		}
	}
	public function escape($value, $strip_html = NULL, $convert_entities = NULL, $charset = NULL){
		$value = (string)$value;
		if (is_string($value)) {
			if(is_null($strip_html)){
				$strip_html = $this->module->config('strip_tags');
			}
			if ($strip_html) {
				$value = strip_tags($value);
			}
			if(is_null($convert_entities)){
				$convert_entities = $this->module->config('auto_escape');
			}
			if ($convert_entities) {
				if(!$charset){
					$charset = $this->module->config('charset');
				}
				$charset = $this->validEncodimg($charset);
				$value = htmlspecialchars($value, ENT_QUOTES,$charset);
			}
		}
		return $value;
	}
	public function format($str, $strip_html = NULL, $convert_entities = NULL, $charset = NULL)
	{
		if ($str===null)
			return 'null';
		return $this->escape((string)$str);
	}
}