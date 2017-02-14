<?php 
//specify your own timezone, this tells PHP to use UTC.
 date_default_timezone_set( 'UTC' );
/**
 * if( @date_default_timezone_set( date_default_timezone_get() ) === false )
 * {
 *     date_default_timezone_set( 'UTC' );
 * }
 */
/**
 * Set the PHP error reporting level. If you set this in php.ini, you remove this.
 * @see  http://php.net/error_reporting
 *
 * When developing your application, it is highly recommended to enable notices
 * and strict warnings. Enable them by using: E_ALL | E_STRICT
 *
 * In a production environment, it is safe to ignore notices and strict warnings.
 * Disable them by using: E_ALL ^ E_NOTICE
 *
 * When using a legacy application with PHP >= 5.3, it is recommended to disable
 * deprecated notices. Disable with: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL | E_STRICT);

define('DS', DIRECTORY_SEPARATOR );
//Define the start time of the application, used for profiling.
define('START_TIME', microtime(TRUE));
//Define the memory usage at the start of the application, used for profiling.
define('START_MEMORY', memory_get_usage());
//INCLUDE_CHECK
define('INCLUDE_CHECK',true);
try {

	include dirname(__FILE__) . DIRECTORY_SEPARATOR . "JsonTemplate.php";
	include dirname(__FILE__) . DIRECTORY_SEPARATOR . "function.php";
$config = array();
$config["debug"] = true;
$config["app_dir"] = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
/**
* Error handler, passes flow over the exception logger with new ErrorException.
*/
function log_error( $num, $str, $file, $line, $context = null )
{
    log_exception( new ErrorException( $str, 0, $num, $file, $line ) );
}

/**
* Uncaught exception handler.
*/
function log_exception( Exception $e )
{
    global $config;
   
    if ( $config["debug"] == true )  {
        print "<div style='text-align: center;'>";
        print "<h2 style='color: rgb(190, 50, 50);'>Exception Occured:</h2>";
        print "<table style='width: 800px; display: inline-block;'>";
        print "<tr style='background-color:rgb(230,230,230);'><th style='width: 80px;'>Type</th><td>" . get_class( $e ) . "</td></tr>";
        print "<tr style='background-color:rgb(240,240,240);'><th>Message</th><td>{$e->getMessage()}</td></tr>";
        print "<tr style='background-color:rgb(230,230,230);'><th>File</th><td>{$e->getFile()}</td></tr>";
        print "<tr style='background-color:rgb(240,240,240);'><th>Line</th><td>{$e->getLine()}</td></tr>";
        print "<tr style='background-color:rgb(240,240,240);'><th>Trace</th><td>".debugTraceAsString($e->getTrace())."</td></tr>";
        print "</table></div>";
    }
    else   {
        $message = "Type: " . get_class( $e ) . "; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
        file_put_contents( $config["app_dir"] . "/tmp/logs/exceptions.log", $message . PHP_EOL, FILE_APPEND );
        header( "Location: {$config["error_page"]}" );
    }
}

/**
* Checks for a fatal error, work around for set_error_handler not working on fatal errors.
*/
function check_for_fatal(){
    $error = error_get_last();
    if ( $error["type"] == E_ERROR ){
		log_error( $error["type"], $error["message"], $error["file"], $error["line"] );
	}
}

register_shutdown_function( "check_for_fatal" );
set_error_handler( "log_error" );
set_exception_handler( "log_exception" );
	$static = 'static';
	// Document root full path
	define('DOC_ROOT',      realpath(dirname(__FILE__)) . DS);

	include_once DOC_ROOT . 'loader.php';
	$loader = new loader(array(
		'JsonTemplate' => DOC_ROOT . 'JsonTemplate',
	));
	$loader->paths( array(
		DOC_ROOT
	));

	$data = array(
		"url-base" => "http://example.com/music/",
		"playlist-name" => "Epic Playlist",
		"settings" => array(
            "debug" => FALSE
        ),
		"songs" => array(
			array(
				"best" => false,
				"url" => "1.mp3", 
				"artist" => "Grayceon", 
				"title" => "Sounds Like Thunder",
				"count" => 1
			),
			array(
				"best" => true,
				"url" => "2.mp3", 
				"artist" => "Thou", 
				"title" => "The Second",
				"count" => 0
			),
			array(
				"best" => true,
				"url" => "3.mp3", 
				"artist" => "asdasd", 
				"title" => "The Third Song",
				"count" => 2
			)
		)
	);

	$jt = \JsonTemplate\Presentation::factory(DOC_ROOT . "templates" . DS);
    /*
    $template ='<!DOCTYPE html>
    <head>
        <title>example.com: {playlist-name}</title>
    </head>
    <body>
        {# This is a comment and will be removed from the output.}
        <h2>Songs in \'{playlist-name}\'</h2>
        {.section songs}
        <ul>
            {.repeated section @}
            <li>
                {@index}
                <p><a href="{url-base|htmltag}{url|htmltag}">Play</a></p>	
                <p><i>{title}</i></p>
                <p>{artist}</p>
                {.count?}
                <p>{count} Song{count|pluralize s}</p>
                {.or}
                <p>Be the first one to like this here on \'{playlist-name}\'.</p>		
                {.end}
                {.best?}BEST{.end}
            </li>
            {.alternates with}
            <li> ---  </li>
            {.end}
        </ul>
        {.or}
        <p><em>(No page content matches)</em></p>
        {.end}
    </body>';
	$compiled = $jt->compile($template);
	echo \Debug::dump($compiled);
	echo $jt->render($compiled,$data);
    */
	echo $jt->fromFile('test.jsont',$data);
	
} catch (\Exception $e) {
	echo "<pre>";
    echo "Message: " . $e->getMessage() . "\n\n"; 
    echo "File: " . path($e->getFile()) . "\n\n"; 
    echo "Line: " . $e->getLine() . "\n\n"; 
    echo "Trace: \n" . debugTraceAsString($e->getTrace()). "\n\n"; 
	echo "</pre>"; 
}
