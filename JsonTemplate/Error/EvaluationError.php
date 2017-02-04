<?php
namespace JsonTemplate\Error;

/*
 * Base class for errors that happen when expanding the template.
 *
 * This class of errors generally involve the data array or the execution of
 * the formatters.
 */
class EvaluationError extends ErrorHandler
{

	public function __construct($msg,$original_exception=null)
	{
		parent::__construct($msg);
		$this->original_exception = $original_exception;
	}
}