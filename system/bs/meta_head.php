<?php

/* this is the build options bootstrap library, by bootstrap I mean scripts that
 are not object oriented, and can be called into the program flow after the block has
 been processed...

 this specific bootstrap library is to dynamically process block/template options to create 
 'smart' tag and description variables. When implemented properly, will allow developers to add tags to any block/template
 and to also easily provide dynamically generated tags/descriptions without having to worry about duplicate entries 
 or keeping track of meta across multiple dynamic pages..

 more meta information to come... this system is encouraged to be edited by all!
*/
	if($user_var['[keywords]'] && $layout->keywords) $layout = build_meta_head($layout,'keywords');
	if($user_var['[description]'] && $layout->description) $layout = build_meta_head($layout,'description');
		
	function build_meta_head($layout,$option){
	global $config;
		$asm = explode(',',trim($config["$option"]));
		// check to see that the value isn't a parsed one before adding it...
		foreach($layout->{$option} as &$tag_line) 
			if(!strstr($tag_line,'((')) $asm = array_merge($asm,explode(',',trim($tag_line)));

		$layout->{$option} = implode(($option=='keywords'?",":' '), array_unique($asm));
		$layout->html_head .= '<meta name="'.$option .'" content="'. $layout->{$option}.'" />' . "\n";
		unset($layout->{$option});
		unset($asm);
		return $layout;
	}
		/* todo allow override_html to allow formatting of output string....
	 builds a user_var for insertion into its originating block
	 layout - myparse layout to pass, $option is the option to process inside the block/template
	 $pass_var is
	 values are hard coded inside of config.php as the meta tag name as the array reference title
	 this will probably need to change
	*/