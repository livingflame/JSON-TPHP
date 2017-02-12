<?php 
namespace JsonTemplate;

// Represents a (repeated) section.
class Section
{
	public $statements = array();
	public $current_clause = array();
	/*
	 * Args:
	 * section_name: name given as an argument to the section
	 */
	public function __construct($section_name=null)
	{
		$this->section_name = $section_name;
		$this->current_clause = array();
	    $this->statements = array(
			'default'=>&$this->current_clause
		);
	}

	public function __toString()
	{
		try{
			return sprintf('<Block %s>', $this->section_name);
		}catch(Exception $e){
			return $e->getMessage();
		}
	}
    public function getAllStatement(){
        return $this->statements;
    }
    public function getAllStatementKeys(){
        return array_keys($this->statements);
    }

	public function statements($clause='default')
	{
        if(!isset($this->statements[$clause])){
            $this->newClause($clause);
        }
        return $this->statements[$clause];
	}

	public function newClause($clause_name)
	{
		$new_clause = array();
		$this->statements[$clause_name] = &$new_clause;
		$this->current_clause = &$new_clause;
	}

	// append a statement to this block.
	public function append($statement)
	{
		array_push($this->current_clause, $statement);
	}
}