<?php
/* myparse env v1
	Ronaldo Barbachano October 2010
	Simple enviornment switching. 
	This code processes a users input, accepts different
	syntax and returns an array with the correct database connection settings,
	also will unset a variable, which the tells myparse to use the original connection
	(connects to tables on the same database)
	
	Syntax explained in system/conf/env.php
*/


 function set_env($set_env){
	// do error checking on db strings
		$db_string = explode('::',$set_env);
		$s_count = count($db_string);
		$dbs_count = count(explode(' ', trim($db_string[0])));
		$db_string[2] = 'mp_blocks';
		$db_string[3] = 'mp_templates';	 
			if(($db_string && $s_count ==2) || ($s_count==1 && $dbs_count== 1)){
				 // process statement with both table prefix and connection string
				 $db_string[2] = $db_string[0] . '_' . $db_string[2];
				 $db_string[3] = $db_string[0] . '_' . $db_string[3];
				 if($s_count ==1 && $dbs_count==1) unset($db_string[0]);
			}
			return $db_string;		
		}