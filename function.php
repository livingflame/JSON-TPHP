<?php 
    function dump_debug($input, $collapse=TRUE) {
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
                    break;
                case "Double": 
                case "Float": 
                    $type = "Float";
                    $type_color = "#0099c5";
                    $type_length = strlen($data);
                    $type_data = htmlentities($data); break;
                    break;
                case "Integer": 
                    $type_color = "red";
                    $type_length = strlen($data);
                    $type_data = htmlentities($data); break;
                    break;
                case "Boolean": 
                    $type_color = "#92008d";
                    $type_length = strlen($data);
                    $type_data = $data ? "TRUE" : "FALSE"; break;
                    break;
                case "NULL": 
                    $type_length = 0; break;
                    break;
                case "Array": 
                    $type_length = count($data);
                    break;
            }

            if (in_array($type, array("Object", "Array"))) {
                $notEmpty = false;
                if($type == "Object"){
                    // start the output buffering
                    ob_start();
                    // generate the output
                    var_dump($data);
                    // get the output
                    $output = ob_get_clean();
                    preg_match('/object\((?P<class>[a-z_\\\]+)\)\#(?P<id>\d+) \((?P<count>\d+)\)/i',$output,$match);
                    $type .= " (" . $match['class'] . ") #" . $match['id'] ;
                }
                foreach($data as $key => $value) {
                    if (!$notEmpty) {
                        $notEmpty = true;

                        if ($isTerminal) {
                            echo $type . ($type_length !== null ? "(" . $type_length . ")" : "")."\n";

                        } else {
                            $id = substr(md5(rand().":".$key.":".$level), 0, 8);

                            echo "<a href=\"javascript:toggleDisplay('". $id ."');\" style=\"text-decoration:none\">";
                            echo "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>";
                            echo "</a>";
                            echo "<span id=\"plus". $id ."\" style=\"display: " . ($collapse ? "inline" : "none") . ";\">&nbsp;&#10549;</span>";
                            echo "<div id=\"container". $id ."\" style=\"display: " . ($collapse ? "none" : "inline") . ";\">";
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
                    ob_start();
                    dump_debug($arg);
                    $output = ob_get_clean();
					$error_arg .= '<li>' . $output . '</li>';
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