<?php 
function dump_debug($input, $collapse=false) {
    $recursive = function($data, $level=0) use (&$recursive, $collapse) {
        global $argv;

        $isTerminal = isset($argv);

        if (!$isTerminal && $level == 0 && !defined("DUMP_DEBUG_SCRIPT")) {
            define("DUMP_DEBUG_SCRIPT", true);

            echo '<script language="Javascript">function toggleDisplay(id) {';
            echo 'var state = document.getElementById("container"+id).style.display;';
            echo 'document.getElementById("container"+id).style.display = state == "inline" ? "none" : "inline";';
            echo 'document.getElementById("plus"+id).style.display = state == "inline" ? "inline" : "none";';
            echo '}</script>'."\n";
        }

        $type = !is_string($data) && is_callable($data) ? "Callable" : ucfirst(gettype($data));
        $type_data = null;
        $type_color = null;
        $type_length = null;

        switch ($type) {
            case "String": 
                $type_color = "green";
                $type_length = strlen($data);
                $type_data = "\"" . htmlentities($data) . "\""; break;

            case "Double": 
            case "Float": 
                $type = "Float";
                $type_color = "#0099c5";
                $type_length = strlen($data);
                $type_data = htmlentities($data); break;

            case "Integer": 
                $type_color = "red";
                $type_length = strlen($data);
                $type_data = htmlentities($data); break;

            case "Boolean": 
                $type_color = "#92008d";
                $type_length = strlen($data);
                $type_data = $data ? "TRUE" : "FALSE"; break;

            case "NULL": 
                $type_length = 0; break;

            case "Array": 
                $type_length = count($data);
        }

        if (in_array($type, array("Object", "Array"))) {
            $notEmpty = false;

            foreach($data as $key => $value) {
                if (!$notEmpty) {
                    $notEmpty = true;
					if(gettype($value) == "object"){
						// start the output buffering
						ob_start();
				 		// generate the output
						var_dump($value);
						// get the output
						$output = ob_get_clean();
						preg_match('/object\((?P<class>[a-z_\\\]+)\)\#(?P<id>\d+) \((?P<count>\d+)\)/i',$output,$match);

						$type .= " (" . $match['class'] . ") #" . $match['id'] ;
					}

					
                    if ($isTerminal) {
                        echo $type . ($type_length !== null ? "(" . $type_length . ")" : "")."\n";

                    } else {
                        $id = substr(md5(rand().":".$key.":".$level), 0, 8);

                        echo "<a href=\"javascript:toggleDisplay('". $id ."');\" style=\"text-decoration:none\">";
                        echo "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>";
                        echo "</a>";
                        echo "<span id=\"plus". $id ."\" style=\"display: " . ($collapse ? "inline" : "none") . ";\">&nbsp;&#10549;</span>";
                        echo "<div id=\"container". $id ."\" style=\"display: " . ($collapse ? "" : "inline") . ";\">";
                        echo "<br />";
                    }

                    for ($i=0; $i <= $level; $i++) {
                        echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    }

                    echo $isTerminal ? "\n" : "<br />";
                }

                for ($i=0; $i <= $level; $i++) {
                    echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                }

                echo $isTerminal ? "[\"" . $key . "\"] => " : "<span style='color:black'>[\"" . $key . "\"]&nbsp;=>&nbsp;</span>";

                call_user_func($recursive, $value, $level+1);
            }

            if ($notEmpty) {
                for ($i=0; $i <= $level; $i++) {
                    echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                }

                if (!$isTerminal) {
                    echo "</div>";
                }

            } else {
                echo $isTerminal ? 
                        $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "  " : 
                        "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>&nbsp;&nbsp;";
            }

        } else {
            echo $isTerminal ? 
                    $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "  " : 
                    "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>&nbsp;&nbsp;";

            if ($type_data != null) {
                echo $isTerminal ? $type_data : "<span style='color:" . $type_color . "'>" . $type_data . "</span>";
            }
        }

        echo $isTerminal ? "\n" : "<br />";
    };

    call_user_func($recursive, $input);
}

////////////////////////////////////////////////////////
// Function:         dump
// Inspired from:     PHP.net Contributions
// Description: Helps with php debugging

function dump(&$var, $info = FALSE)
{
    $scope = false;
    $prefix = 'unique';
    $suffix = 'value';
  
    if($scope) $vals = $scope;
    else $vals = $GLOBALS;

    $old = $var;
    $var = $new = $prefix.rand().$suffix; $vname = FALSE;
    foreach($vals as $key => $val) if($val === $new) $vname = $key;
    $var = $old;

    echo "<pre style='margin: 0px 0px 10px 0px; display: block; background: white; color: black; font-family: Verdana; border: 1px solid #cccccc; padding: 5px; font-size: 10px; line-height: 13px;'>";
    if($info != FALSE) echo "<b style='color: red;'>$info:</b><br>";
    do_dump($var, '$'.$vname);
    echo "</pre>";
}

////////////////////////////////////////////////////////
// Function:         do_dump
// Inspired from:     PHP.net Contributions
// Description: Better GI than print_r or var_dump

function do_dump(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
{
    $do_dump_indent = "<span style='color:#eeeeee;'>|</span> &nbsp;&nbsp; ";
    $reference = $reference.$var_name;
    $keyvar = 'the_do_dump_recursion_protection_scheme'; $keyname = 'referenced_object_name';

    if (is_array($var) && isset($var[$keyvar]))
    {
        $real_var = &$var[$keyvar];
        $real_name = &$var[$keyname];
        $type = ucfirst(gettype($real_var));
        echo "$indent$var_name <span style='color:#a2a2a2'>$type</span> = <span style='color:#e87800;'>&amp;$real_name</span><br>";
    }
    else
    {
        $var = array($keyvar => $var, $keyname => $reference);
        $avar = &$var[$keyvar];
    
        $type = ucfirst(gettype($avar));
        if($type == "String") $type_color = "<span style='color:green'>";
        elseif($type == "Integer") $type_color = "<span style='color:red'>";
        elseif($type == "Double"){ $type_color = "<span style='color:#0099c5'>"; $type = "Float"; }
        elseif($type == "Boolean") $type_color = "<span style='color:#92008d'>";
        elseif($type == "NULL") $type_color = "<span style='color:black'>";
    
        if(is_array($avar))
        {
            $count = count($avar);
            echo "$indent" . ($var_name ? "$var_name => ":"") . "<span style='color:#a2a2a2'>$type ($count)</span><br>$indent(<br>";
            $keys = array_keys($avar);
            foreach($keys as $name)
            {
                $value = &$avar[$name];
                do_dump($value, "['$name']", $indent.$do_dump_indent, $reference);
            }
            echo "$indent)<br>";
        }
        elseif(is_object($avar))
        {

            echo "$indent$var_name <span style='color:#a2a2a2'>$type (" . get_class($avar) . ")#4 </span><br>$indent(<br>";
            foreach($avar as $name=>$value) do_dump($value, "$name", $indent.$do_dump_indent, $reference);
            echo "$indent)<br>";
        }
        elseif(is_int($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
        elseif(is_string($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color\"" . htmlspecialchars($avar,ENT_NOQUOTES,'UTF-8') . "\"</span><br>"; 
        elseif(is_float($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
        elseif(is_bool($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color".($avar == 1 ? "TRUE":"FALSE")."</span><br>";
        elseif(is_null($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> {$type_color}NULL</span><br>";
        else echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $avar<br>";

        $var = $var[$keyvar];
    }
}

    function debugTraceAsString($trace_array, $ignore = 0 ){
        $trace = '';
        $c = 0;
		$trace_count = count($trace_array);
        foreach($trace_array as $key => $stackPoint){
			if ($key < $ignore) { 
				continue; 
			}

            $stackfile = (isset($stackPoint['file'])) ? path($stackPoint['file']) : "";
            $stackline = (isset($stackPoint['line'])) ? "[" . $stackPoint['line'] . "]" : "[internal function]";
            $stackargs = (isset($stackPoint['args'])) ? $stackPoint['args'] : array();	
            $stacktype = (isset($stackPoint['type'])) ? $stackPoint['type'] : "";
            $stackclass = (isset($stackPoint['class'])) ? $stackPoint['class'] : "";
            $stackfunc = (isset($stackPoint['function'])) ? $stackPoint['function'] : "";
            if($stackclass){
				$error_arg = '<ol>';
				$count = 0;
				foreach($stackargs as $args => $arg) {
					$error_arg .= '<li>' . processType($arg) . '</li>';
					$count++;
				}
                $error_arg .= '</ol>';
				$trace .= '<div style="background-color: #d6ffef; border: 1px solid #bbb; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; font-size: 12px; line-height: 1.4em; margin: 30px; padding: 7px; overflow: auto"><h2 style="border-bottom: 1px solid #bbb; font-size: 18px; font-weight: bold; margin: 0 0 10px 0; padding: 3px 0 10px 0">'. $c . ". " . $stackfile . $stackline . " &raquo; $" . $stackclass . $stacktype . $stackfunc . '()</h2>';
				$trace .= $error_arg . "\n</div>";
				$c++;
			}
        }
        return $trace;
    }
	function processType($var,$tabs = 0){
		$result = "\n";
        for ($i = 0; $i <= $tabs; $i++) {
            echo "\t";
        }
		switch (gettype($var)) {
			case "boolean":
				$result .= ($var) ? 'TRUE' : 'FALSE';
				break;
			case "integer":
			case "double":
				$result .= $var;
				break;
			case "string":
				$result .= '"'.htmlspecialchars(path($var),ENT_NOQUOTES,'UTF-8').'"';
				break;
			case "object":
				$result .= '$'. get_class($var);
				break;
			case "array":
				$result .= 'array(';
				$i = 0;
				foreach($var AS $key => $arg){
					if(!is_int($key)){
						$result .= '"' . $key . '"' . ' => ';
					}
					$result .= processType($arg,$tabs+1);
					$i++;
					if(sizeof($var) > $i){
						$result .= ', ';
					}
				}
				$result .= ')';
				break;
			default:
				$result .= gettype($var);
				break;
		}
		return $result;
	}
	
	function path($file){
		if(strpos($file, DOC_ROOT) === 0){
			$file = 'DOC_ROOT' . DS . substr($file, strlen(DOC_ROOT));
		}
		return $file;
	}