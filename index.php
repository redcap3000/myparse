<?php
/**
 * myparse version 2 (PHP)
 * @author		Ronaldo Barbachano http://www.redcapmedia.com
 * @copyright  (c) June 2011
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @link		http://www.myparse.org
 */
// turn off error_reporting for live sites
//error_reporting(0);
	define('IN_MYPARSE', true);
if (function_exists('realpath') AND @realpath(dirname(__FILE__)) !== FALSE)	$system_folder = realpath(dirname(__FILE__));
// until we turn these config files into objects this is the easiest most effective way to load this stuff is to keep these globals
		// can't load config files from object :(
	if (!file_exists($system_folder."/system/conf/config.php")) 
		die(require_once($system_folder."/system/bs/installer.php"));
	else require_once($system_folder."/system/conf/config.php");

	new myparse_page();

class myparse_page{
	function __construct(){
		global $system_folder;
		$this->system_folder = $system_folder;
		if(get_class($this) == 'myparse_page'){
				$this->query_count = 0;
				$this->start_time = (float) array_sum(explode(' ',microtime()));
				// overhead memory - the memory php + mysqli uses before any form actions occur
				$this->oh_memory = round(memory_get_usage() / 1024);
		}
		$head= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8"></meta> 
		<meta http-equiv="Content-Style-Type" content="text/css" />
		<title>';

		$this->url = (isset($_GET["p"]) ? str_replace("|", "/", $_GET["p"]):NULL);
		if($this->url != NULL){
			// validate 'passed' url... attempt to remove common SQL injection techniques
			// also apply a salt to encode the url, so if a user attemptes to manually enter
			// unrecognized values then it won't work ... 
			
			// for now if the url isn't valid set to null - should error 404 and disallow any attacks
				if(stripos($this->url, " ") == TRUE){
					$this->url=NULL;
					//exit;
				}else{
					$this->pass_url = $this->url;
					$this->url = explode("/", $this->url);
					$this->url_count = count($this->url);
					}
		}else{
			$this->url[0] = 'homepage';
			$this->pass_url = 'homepage';
		}
	
	// this object in the construct method creates an infinite loop, must distinguish a child construct method call to avoid a 'nothing' output 
	// this could be placed outside the object after myparse page has excuted (and if it returns nothing, i.e. is not a cached file) then this code may execute
	// well their wont be a layout in this so does this always run ?
	// do caching from the env class

		if(get_class($this) == 'myparse_page'){
		// do nothing if its a child constructor this should be more elegant i assume... the construct method is a bad place to put this code
			require_once($system_folder.'/system/blocks.php');
			$layout = new myparse_layout();
			// do not like meta_head .. it probably needs another variable that disappears because of the $myparse call that creates an object inside the function call
			if($layout->keywords || $layout->description) require("$system_folder/system/bs/meta_head.php");
			// renders html page for caching, 'full output' (displays a single block as a url), or block by block display
			if(!$layout->html_output) die('<h1>Error 404 Page not found.</h1>.');
			// if the layout has full_output data, exit the script with that data.
			if($layout->full_output) die($layout->full_output);
			// begin building the layout object, starting with the head
			if(config::$_['stats']==true) $layout->html_output .= self::stats();
			// no cache is an option that will not let a url be cached .. no_cache:true in the 'block_options' will do it..
			//	$head .= ($this->page_title ? $this->page_title . "</title>\n" :"default</title>\n" ) .($layout->html_head?$layout->html_head:'') ;	
			// not sure how this affects things ... but this library is to cache the page, probably move that code in here to keep track of it better its only a few lines anyway
			if($layout->html_output && !$layout->no_cache && config::$_['cache']) {
			// put file should be on index .. but perhaps we should create another class there to support the URL 
				if($put_file != ''){
					if(!$layout->no_cache && file_exists($put_file) && fopen($put_file, "r") == $head . "\n</head>\n<body>\n" . $layout->html_output) 
					touch($put_file);
					elseif($layout->no_cache && file_exists($put_file))
					// destroy/disable existing cache file
					unlink($put_file);
					if(!$layout->full_output && !config::$_['compress_cache_output']) 
						file_put_contents("$put_file", $head . "\n</head>\n<body>\n" . $layout->html_output);
					else 
						file_put_contents("$put_file", preg_replace("/\r?\n/m", "",$head . '</head><body>' . $layout->html_output));
				}
			}elseif($layout->no_cache){}
			else{
			// could get the name of the http:// site, or if theres something at the end of that like www.site.com/here title it that instead of 'default'
				echo $head . ($layout->page_title ? $layout->page_title : 'default') .'</title>'."\n" .
				// write html_output to screen
				($layout->html_head? $layout->html_head : '') ."	\n\t</head>\n\t<body>\n" . flush() . $layout->html_output . flush();
				}
		}
	// user registration callout
			if($_POST['registration'])require("$system_folder/system/bs/registration.php");
	}
	public function stats($x=null){
		global $num_queries;
		return ($x!=NULL ?($x=='memory'?round(memory_get_usage() / 1024):
				($x=='memory_peak'? round(memory_get_peak_usage() / 1024):
					 ($x=='load_time'?sprintf("%.4f", (((float) array_sum(explode(' ',microtime())))-$this->start_time)):
					 	($x=='oh_memory'? $this->oh_memory:NULL)))):"\n<div class='stats'>Query Count: $num_queries\n<br/>Overhead Memory use:\t$this->oh_memory k<br/>Memory use:\t". round(memory_get_usage() / 1024) ." k  <br/>Peak use:\t" .round(memory_get_peak_usage() / 1024). " k <br/>Net memory:\t".(round(memory_get_usage() / 1024) - $this->oh_memory) ." k \n</br>Load Time:\t" . sprintf("%.4f", (((float) array_sum(explode(' ',microtime())))-$this->start_time))." seconds</div> \n");
	}
}	