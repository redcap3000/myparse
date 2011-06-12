<?php
	if(!defined('IN_MYPARSE'))die('No direct script access allowed');

class myparse_layout extends myparse_page
{
	// for block options, value can be defined as null or specific genf input values ? do this processing for more stuff when calling genf input instead of the complicated way i'm doing it now
	
	public $db,$query_count,$user_session,$username,$system_folder,$membership;
	
	// to do unfavor pregmatches in favor of strpos, str_replace, and get_string_between

	// these correspond to the user table in mp_users (hard coded for now because want to avoid another database call everytime to look these up)
	static public $membership_fields = array('username'=>'','userid'=>'','usergroup'=>'','full_name'=>'','agency'=>'','address'=>'','group_permissions'=>'','group_level'=>'');

	function __destruct(){
		if($this->db){
		mysqli_close($this->db);
		}
	}
	function __construct(){
		global $system_folder;
		parent::__construct($system_folder);
		
		if(!class_exists('user_vars'))
			require($system_folder.'/system/conf/user_vars.php');
		
		
		
		if(!$this->db){
			$this->db = mysqli_init();
			if (!$this->db)     die('mysqli_init failed');
			$this->query_count = 0;
		}
		if(!mysqli_real_connect($this->db, config::$_["db_host"], config::$_["db_user"], config::$_["db_pass"],config::$_["db_name"]))die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		$this->config = config::$_;
		if(!$this->membership) $this->membership = ($_POST['user_login'] && $_POST['password']?$this->login($_POST['user_login'],$_POST['password']):(!$_SESSION['mypuser']?$this->check():$this->membership));
		// the master sql select statement that returns blocks based on the URL, and does some simple conditional checking to speed up processing
		$mp_blocks=config::$_['block_table'];
		
		$mp_templates=config::$_['template_table'];

		/* this is for membership processing, if a member is valid, then we retreve the session, and add it to the master select statement
		 this way we have access to all the parameters without setting anything, making syntax easy, and making it easy to use the dynamic optioning system to define permissions, and unauthorized messages.this method also allows the rest of the page to appear, minus any authorized content.
		*/
		if($this->membership && $_SESSION['mypuser']){
			$extraJoins = ' join mp_sessions s on s.user_session="' . $_SESSION['mypuser'] .'" join mp_users u on s.user_id = u.userid join mp_groups g on g.id = u.usergroup';
			$extraFields = ', s.user_id, s.user_session,g.group_permissions, g.group_level';
		}
		// turn this into a stored procedure for enhanced security (make two one for membership session one without)
		// could store this sql statement to global ? and then use it for the admin interface
		$sql= "
			SELECT
				urls, 
				IFNULL(t.page_title,b.page_title) as page_title,
				t.page_title as template_p_title,
				IFNULL(t.master_select,b.master_select) as master_select,
				IFNULL(t.block_content,b.block_content) as block_content,
				IFNULL(b.block_options,t.block_options) as block_options,
				IFNULL(t.block_type,b.block_type) as block_type, 
				NULLIF(b.urls,'*') as unique_block_exists,
				status $extraFields
			FROM $mp_blocks b left outer join $mp_templates t on b.block_template = t.block_template $extraJoins
			WHERE 
				(FIND_IN_SET('$this->pass_url', b.urls) > 0 
				or 
				FIND_IN_SET('".$this->url[0]."',b.urls) > 0 ) or b.urls = '*' 
			HAVING 
				status=1
			ORDER BY 
				b.block_order, b.id";						
		$module_blocks=	self::get_results($sql);
		// if this call fails get_results will exit the page, and if debug mode is on report an error.
		if(is_array($module_blocks)){
		foreach($module_blocks as &$tested_block)
			if ($tested_block->unique_block_exists != NULL) {
				$unique_block_exists = true; 
				end;
				}
			}
		if ($unique_block_exists) {
			foreach ( $module_blocks as $block ){
				// weed out blocks that shouldn't be there but have been selected anyway (with block_url_match) put config calls in the if statement below
				// from here we go to createBlock which will handle rendering and display, as well as any types of special block types
				// think about garbage collection here .. attempt to remove blocks from module_blocks  that have been processed ?
				if(self::block_url_match(array($block->block_type,stripslashes($block->master_select),stripslashes($block->urls))) == true){
				// consider turning the block into a self reliant object ?
					self::createBlock($block);
					// find location of block in $module_blocks and remove it? get value of the array pointer perhaps
				}
				else {
					unset($module_blocks);
					die('url match failed ');
					}
				}		
			}		
		unset($module_blocks);
	}
	public function reload_form($refresh=0){	
		unset($_POST);die(header("Refresh: $refresh; url=".$this->url->url));
	}

	// begin membership functions move to new class
	public function check(){
		// check for login
		if ((isset ($this->config["allow_guest_sessions"]) and $this->config["allow_guest_sessions"] != false) || @$_COOKIE["PHPSESSID"])
			session_start();
		if (!isset($username) and isset($_SESSION['mypuser']))
		$username = self::get_results("SELECT ".implode(',',array_keys(self::$membership_fields))." FROM mp_users join mp_sessions on userid=user_id join mp_groups g on g.id = usergroup where user_session='". $_SESSION['mypuser'] . "' LIMIT 1");
		$run_query = (isset($username)?(config::$_["allow_multiple_sessions"] == false?"DELETE FROM `mp_sessions` WHERE `user_session`!='". $_SESSION['mypuser'] . "' and `user_name` = '".$username[0]->username."';":''):NULL).
			($_SESSION['mypuser']?"UPDATE `mp_sessions` SET `last_hit` = ".time().", last_ip = '".self::get_ip()."', hits = hits+ 1 WHERE `user_session`='" . $_SESSION['mypuser'] . "' LIMIT 1;":NULL);
		//$run_query .=  "DELETE FROM `mp_sessions` WHERE last_hit < ".time().'-'.(isset(config::$_["session_timeout"])?config::$_["session_timeout"]:2000);
		if($run_query)self::get_results($run_query,1);
		return $username[0];
	
	}
	public function login ($username, $password){
	// onlogin refresh whatever page to get rid of the post variable and return to normal
		$password = stripslashes($password);
		$password =md5($password);
		$time_now = time();
		if ( !isset($_SESSION['mypuser']) and !isset($_SESSION['guest']))session_start();
		$get_user = self::get_results("SELECT ".implode(',',array_keys(self::$membership_fields))." from mp_users join mp_groups g on g.id = usergroup where username='$username' and password='$password' limit 1");
		// need to point this function to membership and stop repeating myself
		if($get_user){
			$host_name = $_SERVER['HTTP_HOST'];
			$user_ip = self::get_ip();	
			$_SESSION['mypuser'] = substr(md5(uniqid(rand(),true)),1,100);
			// also update a stats table if needed ?
			$run_query .= (config::$_['allow_multiple_sessions']==false?"DELETE FROM `mp_sessions` WHERE `user_name` = '" . $get_user[0]->username . "' and `user_id`='" . $get_user[0]->userid . "';":NULL). "INSERT INTO mp_sessions VALUES ('', '" . $get_user[0]->userid . "', '". $get_user[0]->username . "' , '$time_now', '$time_now' ,'". $_SESSION['mypuser'] . "', '1', '', '');". "UPDATE `mp_users` SET `last_login`= NOW(),`last_ip`='$user_ip', `logins_number`=`logins_number`+1 WHERE `userid`='".$get_user[0]->userid."' LIMIT 1";
			self::get_results($run_query,1);
			
			// fixes a problem when we login and go into an infintine loop ...
			if($_POST['user_login'] && $_POST['password']) self::reload_form();
			else return ($get_user[0]);
			// return the permissions ??
		} else echo '<center><b>Sorry wrong username or password</b></center>';
	}
	public function get_ip(){
		$ipParts = explode(".", $_SERVER['REMOTE_ADDR']);
		if ($ipParts[0] == "165" && $ipParts[1] == "21") 
			if (getenv("HTTP_CLIENT_IP")) 
				$ip = getenv("HTTP_CLIENT_IP");
			 elseif (getenv("HTTP_X_FORWARDED_FOR")) 
				$ip = getenv("HTTP_X_FORWARDED_FOR");
			 elseif (getenv("REMOTE_ADDR")) 
				$ip = getenv("REMOTE_ADDR");
			
		 else 
			return $_SERVER['REMOTE_ADDR'];
		
		return $ip;
	}
	// end membership functions
	
	// matches blocks to urls (doesn't show blocks that   may be selected in the query and applies conditionals based on returned field values

	private function createBlock(&$block){
	//global $this->db;
	$this->paginate=false;
		if($block->block_options){
			$block->block_options = self::membership_vars($block->block_options);
			$block->block_options = stripslashes($block->block_options);
			// keep copy of old keys for array intersection and block processing ?
			// block options will add the options to the block; returning the entire block back
			// use array combine instead of passing the entire block?
			$block = self::get_block_options($block);
			
			if($block->logout == 'true' && $block->group_permissions && $block->user_session){
				LogOut();
				// could/should parse this $block-redirect to take [root]
				if($block->redirect) header("Location: $block->redirect");
				exit;
			}
			if((!$block->user_session) && $block->permissions){
				// show the block and exit (don't show anything else.. hopefully this is the only block in the url other than globals..
				$this->html_output .= ($block->unauthorized_msg?$block->unauthorized_msg:'');
				unset($block);
				end;
			}
			if($block->permissions && $block->permissions != $block->group_permissions && $block->permissions != 'hide'){
				$group_level = self::get_results('SELECT group_level from mp_groups where group_permissions ="'.$block->permissions . '"');
				if($group_level[0]->group_level < $block->group_level){
					$block->block_content = ($block->unauthorized_msg?$block->unauthorized_msg:'<h3>You do not have permission to view this data.</h3>');
				}
			}
			if(($block->permissions && (((!$block->user_session) && !$block->hide_block)) || ($block->hide_block))){
				// Special permission to be set for blocks to hide when authorized (specifically the login screen) also hides authorized blocks when not logged in (without a 'you do not have permission' message)
				$block->block_content = '';
				unset($block); 
			}
			if($block->load_class){
			// this is much easier than the php parser from aiki framework .. probably adapt it
			// do we still need to pass 'system_folder' var for parent constructs ? some blocks need specific parameters passed to them
				$class= $block->load_class;
				if(!class_exists($class));
					require($this->system_folder.'/system/libraries/'.$class.'.php');
					// systemfolder must be passed in order for the parent construct method to work 
					// may consider creating a class that pre-generates the class files based on some basic parameters
					$block->load_class = new $class($this->system_folder);
					$block->block_content .= $block->load_class->html;
			
			
			/* ambitius but rubbish ?
			
				$block->block_content .= self::smartLoad($block->load_class);
			*/
			}
			if($block->edit_conf){
				// load admin with an extra selector ???
				if(!class_exists('admin')){
					require($this->system_folder.'/system/libraries/admin.php');
					$block->edit_conf = new admin($this->system_folder,$block->edit_conf);
					$block->block_content .= $block->edit_conf->html;
				}
			}
			if($block->form_edit_config && $block->form_record_config){
				if(!class_exists('sqlee') && !$form){
					require($this->system_folder.'/system/libraries/sqlee.php');
					$form = new sqlee($this->system_folder);
					}
					require_once($this->system_folder.'/system/conf/sqlee_conf.php');
				
					foreach(explode(' ',sqlee_conf::$_['block_options']) as $key){
						$temp_check[$key] = '';
					}
					$sqlee_arguments = array_intersect_key(get_object_vars($block),$temp_check);
					
					if(count($sqlee_arguments)>1 && $form){
						$form = $form->record_editor($sqlee_arguments);
						$this->html_output .= $form;
					}
				}
			
			if($block->description && $this->user_var['[description]'] && !strstr('((',$block->description)) $this->description []= $block->description;
			// inner_sql - simple way to insert data into a sql selection statement
			// check to see that a limit doesn't already exist in statement
			
			if($block->keywords && $this->user_var['[keywords]']) $this->keywords []= trim($block->keywords);
			// destroy blocks that want to be hidden
			if($block->hide_urls)
				foreach(explode(',',$block->hide_urls) as $hide)
					if($hide == $block->urls || $hide == $this->pass_url)
						unset($block); 
			}			
		// sql select statement processing
		if($block->master_select){
			$block->master_select = self::processVars($block->master_select);
			// this includes checking for a (!!) , after the (!!) a user  may provide a backup sql select statement if the first statement fails
			$master_selects = explode('(!!)',$block->master_select);
			if(count($master_selects) == 2){
				$url1=self::get_string_between ($master_selects[0], '(!(', ')!)');
				// this basically says that if the URL isn't recognized, then run the second select statement this may need more testing...
			    $block->master_select = ($url1==$this->url[$url1]?$master_selects[0]:$master_selects[1]);
			    // problem if multiple paginate block types exist... problems will ensue... try using unique block_title/template
			}elseif($block->block_type == 'paginate'){
				$block = self::paginate($block);
				
				}
				unset($master_selects);
		}
		// create actual content
		// stipulations.. raw_html doesn't do any title processing... this is problematic..	
		$block->block_content = stripslashes($block->block_content);
		// whats the diff between block content and block_html ??
		
		switch ($block->block_type){
				default:				self::createBlockContent($block); $this->html_output .= self::processVars($block->block_content); 							break;			
				case "raw_html":		$this->html_output .= $block->block_content; 																				break;
				case "inline_css":   	$this->html_head .= "\n" . '<style type ="text/css">' . preg_replace("/\r?\n/m", "",$block->block_content) . "</style>\n" ;	break;
				case "parse":			$this->html_output .= self::processVars($block->block_content);																	break;
				case "html_head":		$this->html_head .= self::processVars($block->block_content);																		break;
				case "dyn_head":		self::createBlockContent($block); $this->html_head .=  self::processVars($this->block_html);								break;
				case "full_doc":		$this->full_output = $block->block_content;	end;																			break;
				case "full_html":		self::createBlockContent($block);$this->full_output = $this->block_html;end;										break;
				}
		unset($this->block_html); 
}
	private function createBlockContent($block){
		// process whole block for options, consider removing processVar command calls to improve speed, its being run too too often
			$block->block_content = htmlspecialchars_decode(self::processVars($block->block_content));
			if ($block->master_select && $block->master_select != ''){
					$no_loop_part = self::get_string_between ($block->block_content, '(start(', ')start)');
					$block->block_content = str_replace('(start('.$no_loop_part.')start)', '', $block->block_content);
					$no_loop_bottom_part = self::get_string_between ($block->block_content, '(stop(', ')stop)');
					$block->block_content = str_replace('(stop('.$no_loop_bottom_part.')stop)', '', $block->block_content);
					// made a change here.. removed two doubled up apply url's and process vars.. oups.
					
					// this might be a bit early to be processing apply url on query 
					$block_select = self::get_results(self::processVars(self::v_url_replace($block->master_select)));
					// if the master select has url vars then make it 404 exit
					if (!$block_select and $normal_selects[1]) $block_select = self::get_results("$normal_selects[1]");
					$newblock = $block->block_content;
					if ($block_select){
						foreach ( $block_select as &$block_value )
						{
							$parsed = self::parsDBpars($block->page_title, $block_value);
							// the strstr is to remove multiple results (specifically for paginated pages)
							if($block->page_title)$this->page_title .= (!strstr($this->page_title,"$parsed")? $parsed:'');
							if($block->block_options){
								$block->block_options = self::parsDBpars($block->block_options,$block_value);
								$block = self::get_block_options($block);
								if($block->keywords && $this->user_var['[keywords]'])$this->keywords []= trim($block->keywords);
								if($block->description && $this->user_var['[description]']) $this->description []= strip_tags($block->description);
							}
							$blockContents .= self::parsDBpars($newblock, $block_value);
						}
						unset($newblock);
						$processed_block =  self::sql(self::parsDBpars($no_loop_part, $block_value).self::v_url_replace($blockContents).self::parsDBpars($no_loop_bottom_part, $block_value));
					}else{
						// exit 404 hmm this is misleading to put a self::error_404 here this can report empty sql queries ... 
						//self::error_404('Sql query <b> ' . processVars($block->master_select) . '</b> failed',1);
						echo ($block->empty_msg ? self::processVars($block->empty_msg):NULL);
						// wanted to still process sql_markup to create conditional options if no results were available... 								
						unset($block);
						unset($processed_block);
					}
				}else{
					// added a little call to disable the 'add titles' all together, useful for wordpress homepages that have a lot of posts.
					if($block->page_title && config::$_['add_titles'] && $block->disable_add_titles!=false) $this->page_title .= $block->page_title;
					elseif($block->page_title) $this->page_title = $block->page_title;	
					$processed_block =  self::processBlock($block->block_content);
					 }
		if (!$processed_block){
			// hmm not sure about this call
				if($block->error_msg) self::processBlock($block);
				elseif(!$block->block_output) $block->block_output = (!$block->full_output ? '<h1>404 Page Not Found</h1>':NULL);
		}else $this->block_html .=  self::v_url_replace(self::php_parser($processed_block));
	}
	public function get_block_options(&$block){
	/* switch between the template options/block options, prefering the block_options over the template. uses get option to process the options, 
	and dynamically adds them to the passed block, returns the block when finished  use preg replace for safety to remove foriegn characters that will throw off the php */
	// try to do without
	// fix this if an option string ends with the '::' we need to remove it
		if(substr($block->block_options,-2) == '::') $block->block_options = substr($block->block_options,0,-2);
		$temp = self::get_options($block->block_options);
		foreach($temp as $option=>$value)
			$block->{str_replace(' ', '_', trim($option))} = self::php_parser($value); 
		// do some other cleaning here too
		//	$clean_option = ;	may or may not work...	
		return $block;
	}
	private function block_url_match($b){
	
	// this might be able to be optimized further
// pass block->master_select, block_type
	foreach (explode(",", $b[2]) as $compare_url)
			{	
				$compare_url = trim($compare_url);
				if($compare_url == $this->pass_url) return true;
				if($compare_url == '*') return true;
				else{
					if(trim($b[0]) == 'paginate'){
						// check to see if extra url directories are added after pagination vars also check to see that the last 2 variables are numbers
						$b_count = count($this->url);
						$compare_count = self::get_site_level($compare_url) + (self::get_site_level()==1?0:self::get_site_level());
						if($compare_count != $b_count){ 
							die('Pagination error. Compare count: ' . $compare_count . '+ 3 != ' .intval($this->url_count). ' $b == ' .$b_count);
							if($b[1]){
								// check to see if (!( )!) vars exist check count of pass_url etc..
								str_replace(array("(!(",')!)'),'',$b[1],$count);
								if ($count > 0) return true;
							}
						}
						else{
						// may return true but still doesn't show anything WTF.
							return true;
						} 	
					}else
					// this exits pages that have a master select but are not paginated and do not match
						if($b[1] != ''){
							 $count_select = $b[1];
							 if(self::gCount($count_select,$url) + 1 != count($this->url) || (self::gCount($count_select,array($this->url,$this->url_count,$this->pass_url)) == 0 && $this->pass_url != 'homepage') )die('Pagination "gCount" error. $count_select = ' . $count_select);	 
						 }
									
					if($this->pass_url != $compare_url && ($b[0] != 'paginate' )) {
						// this will show us blocks that have a master/template select, but are not paginated, etc. but still dynamically select data based on the url					
						if((!$b[1])) 
							;
							//die('Pagination/ block mismatch error: ' . $compare_url . ' does not match ' . $this->pass_url);
						else return true;
					}
					else return true;
				}						
			}
			return false;
			}
	private function gCount($vars){
		// examines a master select statement to determine the url count for invalid url catching
		//$vars_a = array();
		for($n = 0; $n < count($this->url); $n++)  $vars_a []= (str_replace("(!($n)!)",'',$vars) ? $n : NULL);
		// select last element and return URL count - 1 (for zero element)
		return end($vars_a);
	}
	public function processBlock($block){ 
	// for blocks with record displays (master_select)
	return self::sql(self::v_url_replace(self::parsDBpars(str_replace(array("(start(",")start)","(stop(",")stop)"),"", $block), '')));

	}
	public function get_options($mp_config,$option=NULL){
	// allow options to access the membership_vars ??
	// do stuff to mp_config early on ...
		$mp_config = self::membership_vars($mp_config);
	//takes any string in the option:value::option:value format, and returns an object with option as the object parameter, and value as its value.
		 foreach(explode('::',trim($mp_config)) as $o_arr){
		 	//add limit of 2, error checking
			 	$r = explode(':',$o_arr,2);
			 	$r[1] = self::processVars($r[1]);
			 	// if this statement isn't reached, then we terminate at the end of the function
			 	if($r[0] == $option) return $r[1];	
			 	$return_arr []= $r;		
			 }			 
		// returns an object that contains parameters and values returns false if requested option was not found
	 	return ($option == NULL ?self::toObject($return_arr):self::toAssoc($return_arr));
		}
	private function toObject($array) {
	// creates STD object and uses $array[0] as the parameter name, and $array[1] as its value
		$obj = new stdClass();
		foreach ($array as $arr) $obj->$arr[0] = $arr[1];
		return $obj;
		}	
		
	private function toAssoc($array) {
	// creates STD object and uses $array[0] as the parameter name, and $array[1] as its value
		//$obj = new stdClass();
			foreach ($array as $arr) if(is_array($arr) && count($arr) == 2)$arrr[$arr[0]] = $arr[1];
		return $arrr;
		}		
		
	public function v_url_replace($query){
	// replaces urls in queries, also checks with the site level and makes changes based on that when needed	
		if (preg_match_all( '/\(\!\((.*)\)\!\)/U', $query, $matches ) > 0){
			foreach ($matches[1] as &$parsed){
				// this works fine for non paginated but if paginated i get fucked hard core.
					if (isset($parsed)){
						$loc =(!$this->paginate?$parsed:$parsed - self::get_site_level() - ($loc==0?1:$loc));
						if($loc == 0 && $parsed == 1) $loc += 1;
							$query = @str_replace("(!($parsed)!)", $this->url[$loc], $query);
						 }
				}
			}
			else{
				foreach($this->url as $key=>$value){
					$key2 = $key -self::get_site_level();
					$query= str_replace("(!($key)!)",$value,$query);				
				}
			}		
	return $query;
	}
	public function processVars($text){
		// could be handled better much like the other var processor (with for loop)
		$text = self::parse_vars($text,user_vars::$_);
		$text = self::membership_vars($text);
		// url values may need to be subtracted if we are installing from a non-root directory ...
		return str_replace(config::$_['url'].'/', config::$_['url'], str_replace('[root]', config::$_['url'], $text));
		}
			
	// this function should replace the processVars mess ...
	public function parse_vars($text,$vars){
	// loads an associtative array and checks the provided text to see if they exist within the appropriate construct
	// does not take into account any membership ... which is the diff between the var below... will not 
	// remove vars that do not exist.. but should
	if(is_array($vars))
		foreach($vars as $key=>$value)
			if(strpos($text, $key) !== false) $text = str_replace( $key, $value, $text);
	return $text;
	}
	
	public function membership_vars($text){
	// switch between an existing membership object, or the static class variable if not logged in
	// latter will remove the admin variables from the text.
		if(is_object($this->membership)) $replace_fields = get_object_vars($this->membership);
		else $replace_fields = self::$membership_fields;
		foreach($replace_fields as $key=>$value)
				if(strpos($text, '['.$key.']') !== false) $text = str_replace( '['.$key.']', $value, $text);
		return $text;
	}
	
	private function smartLoad($class,$bypass=null,$path='/system/libraries/'){
	// kinda like load except this one checks if the class exists before requiring, also allows
	// people to define the path of the class name, and if bypass isn't null 'smartLoad_var' is present will automatically
	// return that parameter (default is 'html')
		$class_path = $path . $class . '.php';
		if(!class_exists($class) && file_exists($class_path)) 
			require($class_path);
		else 
			return false;
		if($bypass !=NULL) return true;
		else{
			$smart_var = (config::$_['smartLoad_var']?config::$_['smartLoad_var']:NULL);
			$c = new $class($this->system_folder);
			// may want to look for the html parameter by getting the classes parameters?
			return (array_key_exists($smart_var,get_class_vars($class))?$c->$class->$smart_var:true);
			}
	}	
/**  from - Aiki framework (PHP)*/
 /* Aiki framework (PHP) @author		Aikilab http://www.aikilab.com  @copyright  (c) 2008-2010 Aikilab
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html @link		http://www.aikiframework.org
 */	
 	public function sql($text){
		if (preg_match_all('/\(sql\((.*)\)sql\)/Us', $text, $sqlmatches) > 0) foreach ($sqlmatches[1] as &$match) $text = preg_replace('/\(sql\('.preg_quote($match, '/').'\)sql\)/Us', self::sql_query($match) , $text);
		return trim(preg_replace('/\(select(.*)\)/Us', '', $text));
		}

	public function sql_query($match){
	// this needs get_site_level magic too ...
		$count_sql = preg_match_all('/\((.*)\)/s', $match, $matches);
		unset($match);
		foreach ($matches[1] as &$sql){
			$sql_html = explode("||", trim($sql));
				if ($sql_html[0]){
				$results = self::get_results($sql_html[0]);
					if ($results){foreach ($results as &$result) {
						$result_key = array_flip(get_object_vars($result));
						foreach ($result as $field)	$match .= str_replace("[-[".$result_key[$field]."]-]", $field,  (str_replace($sql_html[0]."||", '', $sql)));
						}
					}
				}
			}
		return $match;
	}
 	public function load($class){
		$objects = array();
		if (isset($objects[$class])) return $objects[$class];
			if (file_exists($this->system_folder ."/system/libraries/$class.php"))
				{
				if(class_exists($class))
					require($this->system_folder ."/system/libraries/$class.php");
				$objects[$class] = new $class();
				$this->$class = $objects[$class];
			}
			else return false;
		}
 	private function php_parser($text){
	// rewritten for speed/shorthand notation from aikiframework
		if (!preg_match ("/\<form(.*)\<php (.*) php\>(.*)\<\/form\>/Us", $text)){
			if (preg_match_all('/\<php (.*) php\>/Us', $text, $matches) > 0)
			foreach ($matches[1] as &$php_function){
				if (preg_match('/\$myparse\-\>(.*)\-\>(.*)\(\)\;/Us', $php_function))
				{
					$class = self::get_string_between($php_function, '$myparse->', '->');
					$function = self::get_string_between($php_function, '$myparse->'.$class.'->', '();');
					$output = ($this->$class ? $this->$class->$function() : $this->load($class) . ($myparse->$class ? $this->$class->$function($vars_array) : ''));
				}elseif (preg_match('/\$myparse\-\>(.*)\-\>(.*)\((.*)\)\;/Us', $php_function)){
					$class = self::get_string_between($php_function, '$myparse->', '->');
					$function = self::get_string_between($php_function, '$myparse->'.$class.'->', '(');
					preg_match('/'.$function.'\((.*)\)\;$/Us', $php_function, $vars_match);
					$vars_array = ($vars_match[1] ? $vars_match[1] : '');
					if ($this->$class) $output = $this->$class->$function($vars_array);
					else{
						$this->load($class);
						$output = ($this->$class ? $this->$class->$function($vars_array) : '');
					}
				}
				if (preg_match('/\$myparse\-\>(.*)\-\>(.*)\((.*)\)\;/Us', $php_function)) $text = str_replace("<php $php_function php>", $output , $text);
				}
				return $text;
			}
			else return $text;
	}
	public function convert_to_specialchars($text){return str_replace(array(")", "(", "[", "]", "{", "|", "}", "<", ">", "_"), array("&#41;", "&#40;", "&#91;", "&#93;", "&#123;", "&#124;", "&#125;", "&#60;", "&#62;", "&#95;"),htmlspecialchars($text));}
	public function convert_to_html($text){return $text;}		
	public function get_string_between($string, $start, $end){
		if (strpos(" ".$string,$start) == 0) return '';
		$string = " ".$string;
		$ini = strpos($string,$start) + strlen($start);
		return substr($string,$ini,strpos($string,$end,$ini) - $ini);
	}
	public function parsDBpars($text, $block_value = ''){
	// processes sql statements to replace markup with the returned values
	preg_match_all( '/\(\((.*)\)\)/U', $text, $matches );
	foreach ($matches[1] as &$parsed){
		$is_array = self::get_string_between($parsed, "[", "]");
		if ($is_array){
			$array = @unserialize($block_value->str_replace("[$is_array]", "", $parsed));
			$block_value->$parsed = (isset($array["$is_array"]) ? $array["$is_array"] : '');
			}
		$text = str_replace("(($parsed))", (!isset($block_value->$parsed)? '': $block_value->$parsed), $text);
		}
	return $text;
}
	
// end of aiki functions		

// get results and band aid do the same thing ... integrate bandaid better into get results or change the bandaid syntax where available  - or try to auto detect 
// certain queries to return its output as something useful (for example if we get a result with only one row, or contains the text 'limit 1' 
//then we are sure to return a single row of data, or if their is only one item in an assoc. array then we return that item 

	private function get_site_level($path=NULL){
	// also takes a path and can return the number of slashes in it (minus one trailing slash)
		return ($path!=NULL?substr_count ($path,'/') + 1: substr_count (config::$_['url'] , '/', 7) -1);
	}
	private function paginate($block)
	{
		global $num_queries;
		$this->paginate = true;
		// if only one exists then we are at root
		// else must add and subtract turn this into function that stores a parameter
		$p_count = self::get_site_level($this->pass_url);
		$p_count += self::get_site_level();
		$b_url = explode('/', $block->urls);
		$b_url_count = count($b_url);
		$check_var = str_replace('(!(', '', $block->master_select, $dynamic_var);
		// this only gets the location not the actual value
			$limit = ($p_count - 2) - self::get_site_level();
			$offset =($p_count - 1) - self::get_site_level();			
		if ($p_count == $b_url_count)
			{
				$url_limit = $this->url_count - 2;
				$block->master_select .= (!$b_url[$b_url_count -1] ? ' LIMIT '. $b_url[$b_url_count -1] : ' LIMIT ' . $this->url[$url_limit]);
			}
		else {
			$block->master_select .= ' LIMIT '.$this->url[$limit]. ' OFFSET ' . $this->url[$offset] . '';
			// will need to do trickery with these values to get all this crap to work :/
			
			if (!($this->url[$offset]) || !($this->url[$limit])) die();
		// yikes it sets but its not updating itself ?? this statement must be modified later on
		}
		// prevent this automatic limit from having more than 25 entries
		// make config::$_['pagination_record_limit']
		if ($this->url[$limit] > 25) $this->url[$limit] = 25;
		// check block for pagination markup
		// switched to array processing to avoid duplicate statements
		foreach (array('[next]', '[prev]', '[last]', '[pages]') as $pag_var) if (stripos($block->block_content, $pag_var)) $pag_count [] = $pag_var;
		$offset_value = $this->url[$this->url_count - 1];
		$limit_value = $this->url[$this->url_count-2];
		if (count($pag_count)>0 && $offset_value > -1) {
				// yes very difficult to follow, but easier when read from right to left these rules were created after hours of trial and error and dwindled down to a few lines of code
				// process the select statement to run a query that returns chop up existing sql statement to run modified query to get the total rows
				$p_query = str_replace("OFFSET $offset_value", '', str_replace('LIMIT '.$limit_value.'', '', $block->master_select));
				$total = mysqli_num_rows(mysqli_query($this->db, self::v_url_replace($p_query)));
				// check to see that requested offset is not greater than the total (amount visible)
				if ($total <= $offset_value - $this->url[$limit]) die();
				// increment query counter
				$num_queries += 1;
				// use passed url to get the records displayed per page
				$limit_var = $this->url[$limit];
				// offset of records to display
				$offset_var = $this->url[$offset];
				// the next set of records
				$next = $offset_var + $limit_var;
				// create individual page links for records inbetween have this change to show the total number of results pages like search engines (link to result sets, so 
				// have links point to different offsets, instead of switching to the 1/0 mode add the first number to zero (or offset) and continue until that number is within range of total records...
				if (in_array('[pages]', $pag_count)) {
					if ($limit_var >1 && $next >0 && $next < $total) 
						for ($start = $next  ;$start <= ($limit_var + $next); $start++) 
							$pages .= (($start < ($total))?' <a class="pag-page" href="[root]'. self::generate_path($this->url_count, 2). '1/'. $start. '"'. ">$start</a> ":'');
							
					$block->block_content = str_replace("[pages]", $pages, $block->block_content);
				}
				if (in_array('[prev]', $pag_count)) {
					// prev value is not being set until here
					$prev = ($limit_var == $offset_var ? 0 : $offset_var - $limit_var);
					$block->block_content = ($prev >= 0 && $prev != $next - 1
											?
											str_replace('[prev]', '<a class="pag-prev" href="[root]/'. self::generate_path($this->url_count + 2, 3) . $prev . '">Previous</a> ', $block->block_content)
											:
											str_replace('[prev]', '', $block->block_content));
				}
				if (in_array('[next]', $pag_count)||in_array('[last]', $pag_count)) 
					$block->block_content = 
					// whats plus one for?
					(($offset_var + $limit_var) < ($total)?
						($next == $total ?
							str_replace('[last]', '<a class="pag-next" href="[root]/'. self::generate_path($this->url_count + 1, 2) . $next.'">Last</a> ', 
							str_replace('[next]', '', $block->block_content))
							:
							str_replace('[next]', '<a class="pag-next" href="[root]/'. self::generate_path($this->url_count + 1, 2) . $next.'">Next</a> ',
							str_replace('[last]', '<a class="pag-last" href="[root]/'. self::generate_path($this->url_count + 1, 3) . '1/'.($total-1).'">Last</a> ',
							$block->block_content)))
							:str_replace(array('[next]', '[last]'), '', $block->block_content));
			}
			return $block;
	}
	private function generate_path($count, $offset)
	// this adjusts returned links - for when multiple '/' in a url exist - to put the new numbers in the right place.
		{
		// do a check for the site directory level
		$s_level = self::get_site_level();
		$count += $s_level;
		//$offset -= self::get_site_level();
		// now we have a url that is one longer than it needs to be ... hhmph
		// returns a path hopefully excluding the zero element ?? (not needed>>)
		for ($n = $s_level; $n <= ($count - $offset); $n++) $l .= $this->url[$n-1]."/";
		// return array list instead of stuff to be replaced.. just easier for now ...
		return $l;}

	public function get_results($query,$m_out=0,$db_string=''){
	global $num_queries,$user_vars;
	// support stored procedures  to speed up sqlee repetitve record selects ?
		$num_queries +=1;
	// m_out 0  returns object as results (normal) , this will have no return if multiquery (when passed more than one queries seperated with a ';')
	// m_out 1  .. results as string m_out 2 .. results as array (if using multiquery)	
	// last variable takes a coded database string to initiate a different database/table connection with the provided credentials
	// trim this string b4 checking
	// [0] 			[1]		[2]		[3]
	//dbhost.name  db.user db.pwd db.name
		if($db_string != '' || $db_string != NULL){
			mysqli_close($this->db); 
			$this->db = mysqli_init();
			$db_string = explode(' ',$db_string);
			if (!$this->db)     die('mysqli_init failed');
			if (!mysqli_real_connect($this->db, $db_string[0], $db_string[1], $db_string[2],$db_string[3])) die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}
		// removing transaction code never used this anyway
		if(count(explode(';',$query))>1){ 
			// run the multiquery, either with the existing mysqli_link, or the created mysqli_r
			mysqli_multi_query($this->db, $query);	
			if($m_out>0){
				   do {
				   	// display results if present and m_out variable is designated this statement is usually intended for update/insert/delete statements, and not for SELECT statements
				       if ($result = mysqli_store_result($this->db)) {
				           while ($row = mysqli_fetch_row($result))
				           // this one designates wether we return an array, or a single string as the result. the single string result is useful for assembling data from multiple tables
				           	foreach($row as $c)
				           		if($m_out==1) $results[]=$c;
				           		elseif($m_out==2) $results .= $c;
				           		elseif($m_out==3) $results .= ($results?','.$c:$c);		
				            mysqli_free_result($result);
				       }     
				   }
				   while (mysqli_next_result($this->db));
				   return $results;
				   }
			}else{
			// just process one query similar to above... maybe try to turn into single block would require modification to the do while loop ...
				$result = mysqli_query($this->db,$query);
			
				if ($result){
						while($return = mysqli_fetch_object($result)){ 
							//if($m_out>0) $results .= $return;
							 $results[] = $return;
							} 
							return $results;
							}
				}
			// return a messaging giving the sql error & the statement that caused it if config['debug'] is enabled.
			die(mysqli_error($this->db) . '<br/><textarea style="background-color:lightgray; width:80%; height:400px; margin:50px;">' .$query. '</textarea>');						
			}

	public function band_aid($query,$mode=NULL){
	global $num_queries;
	$num_queries +=1;
		if ($mode == 2) {
		// mode 2 inserts query and returns the id of the inserted record
			mysqli_query($this->db,$query);
			return mysqli_insert_id($this->db);
		}elseif($mode >2){
		// this clause first creates a result variable where processing happens based again on the mode mode '3' returns true false, but I believe empty datasets are returned as true
			$result = mysqli_query($this->db,$query);
			if(!$result) return;
			// could store failed queries somewhere for debug purposes..
			if($mode==4){
			// mode '4' returns array with associative array
				while($row = mysqli_fetch_assoc($result)) 
					$return []= $row;
					}
			elseif($mode==3){
			// mode '3' returns regular array with values, or array with array with values
				while($row = mysqli_fetch_row($result))
					$return []= (count($row) == 1?$row[0]:$row);
					 }
			return (count($return) == 1?$return[0]:$return);
		}
		// otherwise if no mode, we return an assoc- which must be looped through to retrieve
		// if mode is equal to 1 then we simply pass the query through without any special return vales
		return ($mode == NULL?mysqli_fetch_assoc(mysqli_query($this->db,$query)):mysqli_query($this->db,$query)) ;	
	}
}