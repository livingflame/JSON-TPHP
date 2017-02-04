<?php
namespace JsonTemplate\Error;
/*
 * Base class for all exceptions in this module.
 * Thus you can catch Error to catch all exceptions thrown by this module
 */
class ErrorHandler extends \Exception
{
	public function __construct($msg,$near=null)
	{
		/*
		This helps people debug their templates.

		If a variable isn't defined, then some context is shown in the traceback.
		TODO: Attach context for other errors.
		 */
		parent::__construct($msg);
		$this->near = $near;
		if($this->near){
			$this->message .= "\n\nNear: ".$this->near;
		}
	}
}