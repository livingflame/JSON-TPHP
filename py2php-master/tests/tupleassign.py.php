<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'libpy2php');
require_once('libpy2php.php');
list($a, $b, $c) = ['python', 'to', 'php'];
pyjslib_printnl([$a, $b, $c], true);

