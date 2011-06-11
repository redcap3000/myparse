<?php
/* cachemap v.1 for myparse
	Ronaldo Barbachano October 21, 2010
	Provides a simple way to dynamically generate a sitemap (based on sitemaps.org protocool)
	using existing cache files
	future features might be the ability to dynamically set page rank and update frequency ..
 	
 	HOW TO USE
 	Add this to a block or template with the block/template type of 'full_html'
 	<php $myparse->cachemap->sitetree('1'); php>
 	
 		TODO
	Allow options to update 'frequency' based on '/' - more slashes mean less frequent updates, 
	Change priority of urls based on the number of '/' (sub directories)
	Allow option to split up sitetree into multiple files (sitemap1.xml , sitemap2.xml , sitemap3.xml )
	
 */
 
	class cachemap{
		public function sitetree($o){
		global $system_folder,$config;
		//die (self::getOption(0,'no_map'));
		if(isset($config["page_cache"])){
		$dir = "$system_folder/" . $config["page_cache"];
		foreach(scandir($dir) as $cache_file){
		$sitemap.= ($cache_file != '.' && $cache_file != '..' && $cache_file != '.htaccess' ? "<url>\n <loc>".($cache_file != '.homepage'?str_replace('_','/',str_replace('.',$config['url'],$cache_file)) : $config['url'])."</loc>\n <lastmod>".date("Y-m-d",filemtime($dir.'/'.$cache_file))."</lastmod>\n <priority>.5</priority>\n</url>\n":'');	

		header("Content-type: text/xml");
		return '<?xml version="1.0" encoding="UTF-8"?>
	<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	' ."\n". $sitemap .  '
	</urlset>';
	}
		}else
		// ideally still try to make the tree, but if the cache folder is empty display this message below:
			die('<h1>Please enable caching in system/config.php to use this sitemap</h1>');
		 
		}
	}	