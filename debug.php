<?php
//http://codeaid.net/php/improved-var_dump%28%29-with-colored-output
class Debug
{

    private static $maps = array(
			'string'	=> '/(string\((?P<length>\d+)\)) (?P<value>\"(?<!\\\).*\")/i',
			'array'		=> '/\[\"(?P<key>.+)\"(?:\:\"(?P<class>[a-z0-9_\\\]+)\")?(?:\:(?P<scope>public|protected|private))?\]=>/Ui',
			'countable'	=> '/(?P<type>array|int|string)\((?P<count>\d+)\)/',
			'resource'	=> '/resource\((?P<count>\d+)\) of type \((?P<class>[a-z0-9_\\\]+)\)/',
			'bool'		=> '/bool\((?P<value>true|false)\)/',
			'float'		=> '/float\((?P<value>[0-9\.]+)\)/',
			'object'	=> '/object\((?P<class>[a-z_\\\]+)\)\#(?P<id>\d+) \((?P<count>\d+)\)/i',
        );
    /**
     * Dumps information about multiple variables
     *
     * @return void
     */
    public static function dumpMulti()
    {
        // get variables to dump
        $args = func_get_args();
 
        // loop through all items to output
        foreach ($args as $arg) {
            self::dump($arg);
        }
    }

    public static function dump_debug($input, $collapse=true) {
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

                    echo $isTerminal ? "[" . $key . "] => " : "<span style='color:black'>[" . $key . "]&nbsp;=>&nbsp;</span>";

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
    public static function priorToDump($variable, $caption = null){
        // don't dump anything in non-development environments
        //if (APPLICATION_ENV !== 'development') {
          //  return;
       // }
 
        // prepare the output string
        $html = '';
 
        // start the output buffering
        ob_start();
 
        // generate the output
        self::dump_debug($variable);
        //var_dump($variable);
 
        // get the output
        $output = ob_get_clean();
 

 /*  			if(preg_match($pattern,$output,$match)){
				echo "<pre>";
				echo "<h2>" . $function."</h2>";
				var_dump($match);
				echo "</pre>";
			}
			$output = htmlspecialchars($output);
        foreach (self::$maps as $function => $pattern) {

            $output = preg_replace_callback($pattern, array('self', '_process' . ucfirst($function)), $output);
        }
   */
        return array('caption'=> $caption,'output'=> $output);
    }
    /**
     * Dump information about a variable
     *
     * @param mixed $variable Variable to dump
     * @param string $caption Caption of the dump
     * @return void
     */
    public static function dump($variable, $caption = null)
    {
        $d = self::priorToDump($variable, $caption);
 
        $header = '';
        if (!empty($d['caption'])) {
            $header = '<h2 style="' . self::_getHeaderCss() . '">' . $d['caption'] . '</h2>';
        }
 
        return '<pre style="' . self::_getContainerCss() . '">' . $header . $d['output'] . '</pre>';
    }
    
    public static function compare($d1,$d2){
        $str = '<table>';
        $str .= '    <tr>';
        $str .= '        <th><h2 style="' . self::_getHeaderCss() . '">'.$d1['caption'].'</h2></th>';
        $str .= '        <th><h2 style="' . self::_getHeaderCss() . '">'.$d2['caption'].'</h2></th>';
        $str .= '    </tr>';
        $str .= '    <tr>';
        $str .= '        <td><pre style="' . self::_getContainerCss() . '">'.$d1['output'].'</pre></td>';
        $str .= '        <td><pre style="' . self::_getContainerCss() . '">'.$d2['output'].'</pre></td>';
        $str .= '    </tr>';
        $str .= '</table>';
        return $str;
    }
 
    /**
     * Process strings
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processString(array $matches)
    {
        return '<span style="color: #0000FF;">string</span>(<span style="color: #1287DB;">' . $matches['length'] . ')</span> <span style="color: #6B6E6E;">' . htmlspecialchars($matches['value'], ENT_QUOTES,'UTF-8') . '</span>';
    }
 
 
    /**
     * Process arrays
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processArray(array $matches)
    {
        // prepare the key name
        $key = '<span style="color: #008000;">"' . $matches['key'] . '"</span>';
        $class = '';
        $scope = '';
 
        // prepare the parent class name
        if (isset($matches['class']) && !empty($matches['class'])) {
            $class = ':<span style="color: #4D5D94;">"' . $matches['class'] . '"</span>';
        }
 
        // prepare the scope indicator
        if (isset($matches['scope']) && !empty($matches['scope'])) {
            $scope = ':<span style="color: #666666;">' . $matches['scope'] . '</span>';
        }
 
        // return the final string
        return '[' . $key . $class . $scope . ']=>';
    }
 
 
    /**
     * Process countables
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processCountable(array $matches)
    {
        $type = '<span style="color: #0000FF;">' . $matches['type'] . '</span>';
        $count = '(<span style="color: #1287DB;">' . $matches['count'] . '</span>)';
 
        return $type . $count;
    }
 
 
    /**
     * Process boolean values
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processBool(array $matches)
    {
        return '<span style="color: #0000FF;">bool</span>(<span style="color: #0000FF;">' . $matches['value'] . '</span>)';
    }
 
 
    /**
     * Process floats
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processFloat(array $matches)
    {
        return '<span style="color: #0000FF;">float</span>(<span style="color: #1287DB;">' . $matches['value'] . '</span>)';
    }
 
 
    /**
     * Process resources
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processResource(array $matches)
    {
        return '<span style="color: #0000FF;">resource</span>(<span style="color: #1287DB;">' . $matches['count'] . '</span>) of type (<span style="color: #4D5D94;">' . $matches['class'] . '</span>)';
    }
 
 
    /**
     * Process objects
     *
     * @param array $matches Matches from preg_*
     * @return string
     */
    private static function _processObject(array $matches)
    {
        return '<span style="color: #0000FF;">object</span>(<span style="color: #4D5D94;">' . $matches['class'] . '</span>)#' . $matches['id'] . ' (<span style="color: #1287DB;">' . $matches['count'] . '</span>)';
    }
 
 
    /**
     * Get the CSS string for the output container
     *
     * @return string
     */
    private static function _getContainerCss()
    {
        return self::_arrayToCss(array(
            'background-color'      => '#d6ffef',
            'border'                => '1px solid #bbb',
            'border-radius'         => '4px',
            '-moz-border-radius'    => '4px',
            '-webkit-border-radius' => '4px',
            'font-size'             => '12px',
            'line-height'           => '1.4em',
            'margin'                => '30px',
            'padding'               => '7px',
            'overflow'               => 'auto',
        ));
    }
 
 
    /**
     * Get the CSS string for the output header
     *
     * @return string
     */
    private static function _getHeaderCss()
    {
 
        return self::_arrayToCss(array(
            'border-bottom' => '1px solid #bbb',
            'font-size'     => '18px',
            'font-weight'   => 'bold',
            'margin'        => '0 0 10px 0',
            'padding'       => '3px 0 10px 0',
        ));
    }
 
 
    /**
     * Convert a key/value pair array into a CSS string
     *
     * @param array $rules List of rules to process
     * @return string
     */
    private static function _arrayToCss(array $rules)
    {
        $strings = array();
 
        foreach ($rules as $key => $value) {
            $strings[] = $key . ': ' . $value;
        }
 
        return join('; ', $strings);
    }

}