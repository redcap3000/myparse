<?php
/**
 * sqlee v3 (PHP)
 * @author		Ronaldo Barbachano http://www.redcapmedia.com
 * @copyright  (c) May 2011
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @link		http://www.myparse.org
 
 Modified specifically for myparse integration and mp_blocks block_option field processing (only when present mysql field is presented).
 restrict block option field display to admin interfaces
 
 encode password in php file
 
 fix formatting for sqwizard on the record select screen (some stuff is inside tables in the wrong place)
 
 automatically add form wizard results to a selected record at users' requests
 
 allow users to graphically edit existing forms inside the block option at the mp_blocks editor screen !!
 
 IMPORTANT
 
 allow for certain fields to be 'locked' (handier with record selects)
 
 
*/
if(!defined('IN_MYPARSE'))exit;

class sqlee extends myparse_layout{
	public $table,$action,$id,$pkey,$fields;

	function __construct($system_folder){
		parent::__construct($system_folder);
		if($_POST['restart'] || $_POST['delete'] == 'Cancel') die(parent::reload_form());		
		// examine post for date variables! check each passed post by exploding on '-' (this will be fine for sqlee but not for sqwizard)
		// loads sqlee_conf::$_['upload_path']
		// idedally config files should be loaded from the block option
		// dynamically select the class name ??
		// '/system/conf/' could be a config::$_['conf_path'] = '/system/conf/'
		$class = get_class($this);
		$class_conf_path = $system_folder.'/system/conf/'. $class . '_conf';
	
		if(!class_exists($class) && file_exists($class_conf_path))require($class_conf_path);
		
		if($_FILES){
			foreach($_FILES as $field_name=>$file){		
			// look for a file field
			// designate 'upload' folder somehow in a config (but never in the post object!!)
			// generate stamp to prevent the same file name problem ..
			$path = $system_folder . sqlee_conf::$_['upload_path'] (sqlee_conf::$_['upload_timestamp'] == true?(date('B') + 0).'_':'') . $file['name'];
			// do validations and checks ..
			// next add a post variable for the post handler to store if it doesnt exist try to create it
				if(is_uploaded_file($file['tmp_name'])&& !copy($file['tmp_name'],$path))
					echo '\n<div class="status"> error while copying the uploaded file</div>';
				else  
					$_POST[$field_name] = (date('B') + date('s')).'_' .$file['name'];
			}
			// make directory if possible and chmod it
			 // swatch internet time plus the seconds, should prevent duplicate filenames
		}
		if($_POST) $_POST = self::postHandler();
	}

	public function genHandler($over=NULL){
	// for now genHandler processes the 'validation' when fields should be validated inside of the genf_input to properly report that an error has occured
	// in the html form... how do we do this without repeating tons of code?
	if($over != NULL) return 'select * from `'.$this->table.'` where '.$this->pkey.' = '.$this->id.' LIMIT 1' ;
		switch($this->action){
			case 'update':
			// do validation here ... maybe make a function
				if(($this->action != 'stop' ||$this->action != 'hash_error') && ($_POST && $_POST['handler'])){
				// this occurs when a person posts a handler
					$count = count($_POST);
					// this is a silly way to update the fields .. we should just find a better way to compare rather than figure out why we have an extra comma..
					
					if($count > 1 && !$_POST['delete_confirm']){
					// count is greater than 1 when we are doing an insert or update, the delete /delete_confirm actions do not send anything except the handler
						// remove one from count to account for unsetting the handler .. this is temporary removing for the extra handler and handler_ser
						// this could be more secure through the use of some string comparison functions
						// prevent fields that do not exist in the edit fields from bein inserted by someone attempting to manipulate the sql strings ?
						// validate insertion fields??? DO 'empty' COMPARISONS here .. don't update post's that are identical to the edits ?
						// run and report update satement
						$validation = self::validateInsertFields(1);
						if(!is_array($validation)){
							foreach($_POST as $key=>$value){
								if($key!='handler'){
									$value = addslashes($value);
									$the_fields []= " `$key` = '$value'";
									}
							}
							$statement .= implode(',',$the_fields). " WHERE `$this->pkey`=$this->id ";
						// now we are reporting successful update and going back to blank screen ... hmmm are we generating a gen handler?
							$result.= (self::band_aid("UPDATE $this->table SET $statement",1)?'<h4 class="status">Update successful.</h4>':'<h4 class="s_error">Problem with update</h4>' ."UPDATE $this->table SET $statement");
							unset($statement,$counter,$count);
						// store error to object parameter with the key as the name of the field
						}else
							foreach($validation as $field=>$error)	
								$this->errors[$field] []= $error;	
					}
				}
				break;
			case 'edit': 
				return self::band_aid('select * from `'.$this->table.'` where '.$this->pkey.' = '.$this->id.' LIMIT 1') ;	
			break;
			case 'save_as': break;
			case 'delete': 		
				if($_POST['delete'] == 'Delete'){
					$sql = "delete from `".$this->table."` where `".$this->pkey.'` = '.trim($this->id).' LIMIT 1;';
					$insert_id = self::band_aid($sql,1);
					$result .= ($insert_id?'<h4 class="status">Record Deleted </h4><small>Record ID:'. $this->id . '</small>':'<h4 class="status">error</h4><small>' . $sql . '</small>');					
						}
					unset($sql);
					unset($insert_id);
				break;
			case 'delete_confirm':
				if($_POST['delete'] != 'Cancel' && ($this->action != 'edit')) 
						$result.= '<form method="POST" class="delete_conf">
							<fieldset>
									'.
									self::writeHandler('delete',$this->id)
									.'<table><tr><td colspan="2">Delete this record?</td></tr><tr><td>
									<input type="submit" name="delete" value="Delete" class ="list_submit_link">
									</td><td>
									<input type="submit" name="delete" value="Cancel" class ="list_submit_link">
									</td>
									</tr>
									</table>
							</fieldset>
							</form>		
									';break;
			case 'insert':
				// need a handler to pass back info for the unique record check? will need to make a write handler to pass back the 'validation' settings for 'empty/not empty' validations ...
				// next up is to validate, perhaps add another level to the 'required' fields and designate the type of validation email - number - date - name - money  etc.
				$insert = self::validateInsertFields();
				$p_copy = $_POST;
				unset($p_copy['handler']);
				if($insert == 1){
				// would be easiest to just not do the insert and let the proceeding page handle it next page should get an 'edit' value instead of 'insert'	
					$insert = self::band_aid("insert into `$this->table`(".implode(',', array_keys($p_copy)).") VALUES ('".implode("','",$p_copy)."');",2);	
					// is this reloading ?
					// instead of this we could attempt to load the value by modifying the handler post var ? or is it too late ??
					if($insert && $_POST['handler']=='insert::')	{
					//	$_POST['handler'] = "edit::$insert";
						// newwp didn't work piece of shit
						
					// also over ride the argArg too?
					self::reload_form();
					
					}
					// is insert the value of the newly updated record ?
					// next we should get the value of the insert statement and regenerate a handler ?					
					$result.= ($insert?'<h4 class="status">Insert successful!</h4>':'<h4 class="status">Problems with insert</h4>');
					// now refresh after the insert statement ?

				}else {
					// if we get to this we end up with the wrong fields var after the post value .. minor issue but should be handled
					$result.= 'Please examine input for errors below.';
					// use array processing here ?
					foreach($insert as $field=>$error)	
						$this->errors[$field] []= $error;
					}
			break;
			}
			return ($result?$result:NULL);
	}
	
	private static function postHandler(){	
		$post = $_POST;
		foreach($post as $key=>$value){	
			if(strpos($key,'-') !== false) {
					// we'll always need to unset all of these keys for insert/updates to work properly, since these values
					// are combined into the appropriate value
				// what happens if we do the below before we come to the validation key?
					$temp= explode('-',$key); 
					switch ($temp[1]){
							case (in_array($temp[1],array('year','month','day','hour','min','sec'))):
									$field_name = $temp[0];
									$date_name = $temp[1];
									if(is_int($value + 0)) $post[$temp[0]][$temp[1]] = $value + 0;
							break;
						}
					unset($post[$key]);	
					unset($temp);
			}elseif(strpos($key,'_mbo') !== false){
				$key_n = str_replace('_mbo','',$key);
				//unset old key so it doesn't get inserted (will cause error)
					// add option value to the field
					// this code will only be exceuted within the admin (preferably)
					// sometimes this option isn't written properly ... in $option_fields isn't being imploded
				if($value != '')
					$option_fields []= $key_n. ':' . ($value == 'on'?'true':$value);
				unset($post[$key]);
			}
		}
		// replace old block option field with the new from post for insertion ..
		if(is_array($option_fields)) $post['block_options'] = implode('::', $option_fields);
		// use array key intersects for time processing ... 
		foreach($post as $key=>$value){
			if(is_array($value)){
				//no use of a for loop to do this all in one line, since we should control the order
				// of the values - so if anyone makes modifications to the way a time/date stamp form is ordered it won't break the update/insert
				// turn into an additive statement.
				$date = $value['year'].'-'.$value['month'].'-'. $value['day'];
				$year = $value['year'];
				if(count($value)==3)
					$post[$key] = $date.' 00:00:00';
				elseif(count($value)==5)
				// do the whole timestamp ... add optional support for seconds
					$post[$key] = $date. ' ' . $value['hour'].':'.$value['min'].($value['sec']?$value['sec']:':00');
				//elseif(count($value)==2)
				// process time only here support time, and also support year add support to genf input
				elseif(count($value)==1)
					$post[$key] = $value['year'];
			}
		}
		return $post;
	}
	public function record_editor($arr){
	// check to see what exists in the arr and set up the form , check for a handler and apply those actions, then generate the form array stored to an object param
		$arg =($arr['form_manager_insert'] && !$_POST?$arr['form_manager_insert']:($arr['form_edit_config']?$arr['form_edit_config']:NULL));
		// value lets us make special things happen for form_manager_insert - which is another version of a record editor (as defined) that appears when form_manger_insert is active inside of form_mode='manager'
		$edit_arg = $arr['form_record_config'];
		// this can't and SHOULDn't be null ...
		$form_filter =($arr['form_filter']?$arr['form_filter']:NULL);
		$ex = ($arr['form_mode']?$arr['form_mode']:1);
		if($ex) $this->ex = $ex;
		if($form_filter != NULL){
			$this->form_filter = $form_filter;
		}
	// sometimes we show an insert screen after a sucessful delete
		if($_POST['handler']){
			$handler = explode('::',$_POST['handler'],2);
			$h_count = count($handler);
				if($h_count == 2){
					$this->action=$handler[0];
					$this->id = $handler[1];
				}elseif($h_count == 1)
					$this->action=$handler[0];	
			}
		// these calls should first check to see if edit_arg_string already exists and then will set it to the first variable if it does not,
		// if theres no post set, because if a post is set then we have these variables
		if(!$this->argArg) $this->argArg = self::doArg($arg);
		if(!$this->editArg)  $this->editArg= self::doArg($edit_arg);
		// here we clean up the argArg array and make it more logical to access within the foreach statements in the rest of the object
		// and we also turn in arrays with arrays with one value into an array with that same value.
		$this->table = array_keys($this->editArg);
		$this->table = $this->table[0];
		$table_key_name = $this->table;
		$this->pkey = $this->editArg[$table_key_name][0];
		if(count($this->editArg[$table_key_name]) > 1) unset($this->editArg[$table_key_name][0]);
		$this->fields = (count($this->editArg[$table_key_name])>1?implode(',',$this->editArg[$table_key_name]):$this->editArg[$table_key_name][0]);
		$this->edit_fields = implode(',',array_keys($this->argArg));
		// creates a record editor only for 'public' insertions
		// attempt to get status messages if they exist, to insert them properly in an html document (otherwise they show up at the top, before the body tags!)
		$status = self::genHandler();
		
		// this mainly looks at post variables to determine the state of the form - or what elements to show which is a combination of record inserter and records listing
		return ($_POST['handler']? ($ex==2?'Form submitted sucessfully.':($status && !is_array($status)?$status:NULL).(!$_POST['delete']?self::genForm($this->edit_fields,'edit'):self::insert_record()) . ($this->action != 'edit' ||$this->action != 'insert'?(!$_POST['delete'] || $_POST['delete'] == 'Delete' || $ex == 3?self::genf_input(NULL,NULL,$this->table,'record_list',$this->pkey): ''):'')):($ex == 3? self::genf_input(NULL,NULL,$this->table,'record_list',$this->pkey) . (!$_POST['delete_confirm']?self::insert_record():''):($ex == 2? self::insert_record():($ex == 1?self::genf_input(NULL,NULL,$this->table,'record_list',$this->pkey):NULL))));
	}		
	private function genForm($fields=NULL,$action="save_new",$extra=NULL,$id=NULL){
		// this is to allow the record list to also create forms to insert
		if( $this->action == 'edit') $the_edit = $this->genHandler();
		if($this->action == 'update') {
			$the_post = $_POST;
			unset($the_post['handler']);
			}
		$counter = 1;
		if($this->action != 'delete_confirm' && $this->action != 'hash_error'){
		$file=false;
		$result = self::getColumns($this->table,$fields);
		while($row = mysqli_fetch_object($result)){
			// default 'empty value' for required database fields (default to things being ok with empty)
			$empty = 1;
			foreach($row as $key=>$result_field){
				if ($key == 'Field')$field_name = $result_field;					
				elseif ($key == 'Type'){
				$sql_field_type = $result_field;
				// some types don't have a field_size designation... search for the '(' or a space
					if(strpos($result_field , '(') > 0 || strpos($result_field , ' ') > 0){
						$field_size = explode('(',$result_field);
						$field_size[1] = substr($field_size[1], 0, strrpos($field_size[1], ')'));
						$field_type= $field_size[0];
						// storing this value because the validation values override field_types later in the loop..
						}
					else {
						$field_type = trim($result_field);
						}
					}
				elseif($key =='Key' && $result_field == 'PRI') $pkey = $field_name;
					// this is to properly fill 'enum' values and for genf function calls to remove/add a 'default' empty value (if it exists)
					// can also be used for form validation (to require fields to not be empty) also get and process field options here ...
				elseif ($key == 'Null' && $result_field == 'NO') {
					$validate = 1;
					$empty=0;
				}
				elseif ($key == 'Default' && $result_field == 'NULL')$validate =($result_field == 'NULL'?0:1);
				elseif ($key == 'Default' && $result_field != 'NULL')$validate =1;
				// this checks the row against the pkey to avoid writing it
				elseif($field_name != $pkey && $key == 'Extra' && trim($result_field) == 'on update CURRENT_TIMESTAMP'){
				// this is where the extra processing happens because it was the last field of a column return ... needs to be moved to 'comment' if i want to support
				// those inside of the editors
						$this->disabled_fields []= $field_name;
						// remove from list>
						// what about commas?!
				}
				elseif($key == 'Comment'){
					// allow for comments to be disabled ... not sure where to store this option
							if((strpos($fields,$field_name)) != false) {
						// the above first checks to see if fields parameter exists, then it checks to see if the field being looked at exists within fields, 
						// the other or clause probably can be removed ... but continues processing something if fields aren't present, or if a field setting was overridden
						// i.e. if you don't provide fields it will go by default/null keys check to see if the field type has any existing settings to process .. specifically record select
								if(is_array($this->argArg[$field_name]))
									foreach($this->argArg[$field_name] as $field=>$value)
										if(is_string($field)){
											$field_type = $field;
											$genf_extra = $value;
											end;
										}elseif($value=='file'){
										// kinda hard coding.. but for security reasons..
											$field_type = $value;
										// we cannot be 100% sure that every array option should be set as its type
										// but with the 'file' type we want to control its action inside this loop	
										}
								// Build the start of the form 
								$return .=  "\n\t<fieldset id='$field_name' class='$field_type'".(is_array($this->disabled_fields) && in_array($field_name,$this->disabled_fields)? ' DISABLED ':'').">\n\t\t<legend>".str_replace('_',' ',$field_name)."</legend>\n\t";
								if(!$field_type) $field_type = 'text';
								if(!$genf_extra) $genf_extra = 'size="'.$field_size[1].'"';
								// here we manipulate genf_input parameters based on the type of field we are dealing with
								switch ($field_type){
									case ($field_type=='file'):
										// do our field here ??
										$file= true;
										break;
								
									case ($field_type=='text' || $field_type=='validation'):
										if($sql_field_type =='text'||$sql_field_type=='mediumtext'||$sql_field_type=='longtext'||$sql_field_type=='tinytext'){
									// ideally these should be text areas if the field size is large enough to do multiline
											$genf_extra = 'rows="5" cols="70"';
											$field_type ='textarea';
											}
										else
											$genf_extra = " size ='".($field_size[1]>100?100:$field_size[1])."' ";
									break;
									case 'record_select':
										//$form_f_type = $field_type; 
									break;
									case 'selection':
									// to be implemented , ideally you should be using an enum or the record select field type
										//$form_f_type = $field_type; 
									break;
									// do math based on field_size[1] to determine the size of the editor
									case ($field_type=='mediumtext'||$field_type=='longtext'||($field_type=='tinytext' && $field_size > 150)):
										 $field_type = 'textarea'; $genf_extra = ($field_size > 150?'rows="5" cols="70"':'rows="20" cols="70"'); 
									break;
									case 'enum': 
										//$form_f_type = 'enum'; 
										// some fields do not allow for a null value, two cases exist below that correspond to the html list object 
										//- one will select the first value in an enum set, the other will allow a 'none' value where available based on the mysql column call.
										$genf_extra =($empty != 0? "$field_name,1,none":"$field_name,0"); 
										// make a switch to conform to the enum function call syntax 
										// - this allows a field to be designated to select an enum from another table to use as a value selection set
										$field_name = $this->table; 
									break;
									// consider making a two field value to allow decimals and adding them when the post variable is processed (around the time when it is filtered)
									case ($field_type=='int'||$field_type=='tinyint'||$field_type=='smallint'||$field_type=='float'||$field_type=='double'||$field_type=='double precision'||$field_type=='real decimal'): $genf_extra = 'size="10"';break;
									case ($field_type=='varchar'||$field_type=='tinyblob'||$field_type=='tinytext'):$genf_extra ='size="80"'; break;
									}
									// plug in validate here ... ?
									if($_POST && (count($_POST) == 1 ||($_POST && $_POST[$field_name] != ''))) $this->take_action = true;
								$validation_check = '';
								if($this->errors){
									// if errors are caught inside of validateInsert fields, we display them in their respective fields
									// by comparing against the errors object parameter (which contains an assoc. array with another array
									foreach($this->errors as $field=>$array)
										if($field == $field_name)
											foreach($this->errors[$field_name] as $error)
											// maybe put switch statement to be more verbose
												foreach($error as $errors)
													$validation_check .= " <small class='errors'>$errors</small> ";
								}
								if($this->disabled_fields) $genf_extra = ' DISABLED ' ;
								if($field_name == 'block_options') {
									// create master 'block option' registry that contains an actions for the post handler to process that block option ?
									if($_POST['block_options']) $the_edit['block_options'] = $_POST['block_options'];
									$options = self::get_options($the_edit['block_options'],1);
									// its looking for _mbo prefixes ... could manally ad them to the options...
									foreach($options as $x=>$y){
										$options["$x".'_mbo'] = $y;
										unset($options[$x]);
										}
									if(!class_exists('block_options_conf'))require($this->system_folder.'/system/conf/block_options_conf.php');
									// avoid processing options that do not exist
									foreach(block_options_conf::$_ as $key=>$value){
									 // this value is really referring to something else probably the self::$block_options
									 // only do this for the block options that exist by using some sort of array reduction method array_diff
									 // organize permission options ?
									 	$value = explode('::',$value);
									 	if($value[1]) $value[1] = explode(',',$value[1]);
									 	if($key != '')$return .= '<fieldset class="'. $value[0].'" id="bo_'.$key.'"><legend>'.str_replace('_',' ',$key).'</legend>' . self::genf_input(NULL,$options,$key.'_mbo',$value[0],$value[1]) . '</fieldset>';
									 }
								//$return .= "\n\t</fieldset>";
								}
								else $return .= $validation_check .self::genf_input(NULL,($the_edit?$the_edit:($_POST?$_POST:NULL)),$field_name,$field_type,$genf_extra) . ($result_field != ''?"<small class='field_info'>$result_field</small>":''). "\n\t</fieldset></fieldset>";
								
						}		
					}
				}	
			}		
		return "<form id='$this->table' method='post' ".($file?"enctype='multipart/form-data'":NULL)." style='clear:both'>\n" . $return. ($action =='insert' || $this->action == 'insert' ? self::writeHandler('insert') : self::writeHandler('update',$this->id). "\t") . '<fieldset id="form_buttons">' . ($this->ex != 2 || !$this->action || $this->errors?'<input type="submit" value="'.$action.'" class="s_button">':'')."\n".   '<input type="submit" name="restart" class="s_button" value="Start Over"></fieldset></form>';		
		}
	}
	
	private function genf_input($template,$edit,$std_name,$type='text',$extra=NULL)
	{
	// make settings for 'time' in a sqlee.conf inside of system/conf
	// also make a selector list simlar to libraries that generates the folders but for /conf to edit those vars
	// so make a block option called edit_config::config name (no spaces) make a genfinput to handle it instead of putting it in blocks
	// move pagination out of blocks into own object that sends/returns the used block fields by using array_key_intersect
	
	// clean up edit vars so we don't have to see any double apostrphies make sanitization function
		switch ($type){ 
		// sometimes post vars are already enclosed in quotes!
		// validations are going to the default.. need to change a 'validation' entry to something better.... aghhh but how??? 
				default:	return "\t". '<input type="text" name="'.$std_name. '" value="'. stripslashes(self::iffy($edit,$_POST,$std_name)).'"' .  ($extra!=NULL && !is_array($extra)?$extra:'').'>'			; 		break;
				
				case 'selection_menu':
				// to do ... create form wizard editor to make these lists or come up with easier to write seperators ...
					foreach(explode('[!]',$extra[0]) as $a=>$b){
						$temp = explode('[=]',$b);
						$extra[$temp[0]] = $temp[1];
						unset($extra[$a]);
					}
					// also can take a normal array but will set both the value and select name to the same value
					// we can't look in the $_POST because the vlues won't match? or will they ? seems to work for now
					foreach($extra as $a=>$b)
						$extra[$a] = "<option value='$b' ".($edit[$std_name] == $b?' SELECTED ':'').">".(!is_string($a)?$b:$a)."</option>";
					// turn this into a function				
					// genf_input(NULL,'active value','field_name','selection_menu',$extra)
					return '<select name="'.$std_name.'">' . implode('',$extra) . '</select>';
				break;
				
				case 'emailaction':
				// this case will send an email defined in the field by the table and record number? by making itself hidden
					return '<input type="hidden" name="'.$std_name.'" value=' . self::iffy($edit,$_POST,$std_name);
				break;
				case 'file':
				// idea could store a more complicated file varchar field that can contain a number of parameters (like file type, size etc.) that can be disemminated and displayed here .. but 
				// would cause problems when querying it (and processing it from there...)
					// in case of file we have to change the way the form is written too
					$file_value = self::iffy($edit,$_POST,$std_name,'');
					return ($file_value != ''?'<label class="existing_file"><small>File uploaded:<b>' . $file_value .'</b></small></label><br><small>Upload another file</small>':''). "<input type='file' name='$std_name'>";
				break;
				
				case ($type=='timestamp' || $type=='date' || $type =='datetime'):
				// this is pointless for fields with auto update ...
						// will return the time - need to fix the time when updating (it disappears)
						if( $extra == ' DISABLED ') return '<small id = "'.$std_name.'" class="sqlee_timestamp">'.self::iffy($edit,$_POST,$std_name,'Auto generated timestamp').'</small>';
						$ts = explode(' ',self::iffy($edit,$_POST,$std_name));
						$ts[0] = explode('-',$ts[0]);
						// hour (24) $ts[1][0]  min $ts[1][1] 
						// remove any leading zeroes from values (month and day) if our values are zero/unset etc get todays date...
						if($ts[0][0] == 0 ||$ts[0][0] == '' ) $ts[0][0] = date('Y');
						if($ts[0][1] == 0 ||$ts[0][1] == '' ) $ts[0][1] = date('m');
						if($ts[0][2] == 0 ||$ts[0][2] == '') $ts[0][2] = date('d');		
						$ts[0][1] =$ts[0][1] + 0;
						$ts[0][2] =$ts[0][2] + 0;
						$result .= "\n\t\t<fieldset class='sq_date month'>\n\t\t\t<legend>Month</legend>\n\t\t\t\t<select name='$std_name-month'>";
						// my extremely lazy way of doing things. Sorry. Didn't feel like typing in all the numberd equivalants to these months this is so we can have named months, but still store/retrieve dates that are numbered.
						$months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec');
						$x= 1;
						// implement am/pm radio box and 12 hour selection menu
						foreach($months as $key=>$value){
							$months[$value] = $x;
							unset($months[$key]);
							$x++;
						}
						foreach($months as $x=>$val){
							$result .= "\n\t\t\t\t\t<option value='$val'" . ($ts[0][1] == $val?' SELECTED ':'') . ">$x</option>";
						}
						$result .= "\n\t\t\t\t\t</select>\n\t\t\t</fieldset>\n\t\t\t<fieldset class='sq_date day'>\n\t\t\t\t<legend>Day</legend>\n\t\t\t\t\t<select name='$std_name-day'>";
						// split field up into two make same column (first column 1/3 , second 1-9 ... what about validation?
						for($x=1; $x <= 31; $x++){
							$result .= "\n\t\t\t\t\t<option value='$x'" . ($ts[0][2] == $x?' SELECTED ':'') . ">$x</option>";
						}
						// split field up into at least two selection sets to reduce the number of selection items.
						// do AM /PM processing
						$result .= "\n\t\t\t\t\t</select></fieldset><fieldset class='sq_date year'>\n\t\t\t\t\t<legend>Year</legend>\n\t\t\t\t<select name='$std_name-year'>";
						for($x=2005; $x <= 2020; $x++)
							$result .= "\n\t\t\t<option value='$x'" . ($ts[0][0] == $x?' SELECTED ':'') . ">$x</option>";
							// select current year by default .. 
						$result .= "\n\t\t\t\t\t</select></fieldset>\n\t";
						// this doesn't work for the insert form field ... :(
						if($type=='timestamp' || $type=='datetime'){
							if(!$ts[1]) {
								$minutes = date('i');
								// allow option to turn off time rounding so we can have exact times wheen needed...
								$minutes = $minutes - ($minutes % 15);
								$ts[1] = date("H:$minutes:00"); $ts[1] = date('H') . ":$minutes:00";
							}
							$ts[1] = explode(':',$ts[1]);
							$result.= "<fieldset class='sq_time min'>\n\t\t\t\t\t<legend>Minute</legend>\n\t\t\t\t<select name='$std_name-min'>";
							
							// make config for this .. if its an edit form then we need all 59 possibilities to show an exact timestamp, consider splitting up into two columns to cut back on 
							// html code...
							//for($x=0; $x <= 45; $x+=15)
							for($x=0; $x <= 59; $x++)
								$result .= "\n\t\t\t<option value='$x'" . ($ts[1][1] == $x?' SELECTED ':'') . ">$x</option>";
							$result .= "\n\t\t\t\t\t</select></fieldset>\n\t<fieldset class='sq_time hour'>\n\t\t\t\t\t<legend>Hour</legend>\n\t\t\t\t<select name='$std_name-hour'>";
							// do 24 hour use mod?
							for($x=1; $x <= 24; $x++)
								$result .= "\n\t\t\t<option value='$x'" . ($ts[1][0] == $x?' SELECTED ':($x==(date('H') + 0)? ' SELECTED ':'')) . '>'.($x > 12? ($x-12) . ($x!= 24?"\tpm":"\tam"):' ' .$x . ($x==12?"\tpm":"\tam")).'</option>';
							$result.='</select></fieldset>';
						}
						return  $result ;
					// now how to select these fields and combine them as one
				break;
				case 'record_list':
				// this option returns a table that can also be used as an editor given the id, granted the table has an 'id' field, otherwise a
				// different syntax can be used to sort/select by another variable. Perhaps userid for mp_users
					// don't show the record list if in edit or update mode... 
					if($this->action == 'edit' || $this->action == 'insert'  || $this->action == 'update') return;
					// where does extra get written ?
					$extra = explode(',',$extra);
					// proper fields not being passed in genf_input call so extra is only sending an array with the pkey most of the time
					$pkey = $extra[0];
					// this fields should be a more accurate representation of the fields to display in the record list - independently from the single page edit
					// look at edit arg
					// perhaps santize form filter ?
					// we don't have access to the membership parameter ?
					
					$result = $this->band_aid("SELECT ". ($this->fields?$this->fields:'*'). " FROM $std_name".($this->form_filter?' WHERE '.str_replace("\'",'"',$this->form_filter) :'')." GROUP BY $pkey;",1);
					// table header
					$return .= "\t<tr>\n";
					foreach(explode(',',$this->fields) as $column_name)$return .= ($column_name != $this->pkey? "\n\t\t<th>".str_replace('_',' ',$column_name)."</th>" : "<th colspan='2'></th>");
					// add an extra th for the 'actions' (edit and delete)
					$return .= "\n\t</tr>";
					$count = 1;
					// cross reference result with record select if needed - find out 
					// if in_array('record_select',$key[]) and then band aid
					while($row = mysqli_fetch_object($result)){
						unset($result_field_t);
						unset($result_field_2);
						// for alternating row colors
						// idea only require keys update/new/delete/save as actions
						$return .= "\n\t<tr class='". ($count % 2 == 0?'even':'odd') ."'>"; 
						foreach($row as $key=>$result_field){
							if(is_array($this->argArg[$key]) && array_key_exists('record_select', $this->argArg[$key])){
								if(is_string($result_field)) $result_field_t = '"'. $result_field . '"'; 
								// handle quotes ....
								// this needs to use the existing wherre to process this value if hasn't been unset already
								// this is counter productive we are selecting the id from the record with data we already have ?
								// its in the ARG ARG !! arg 
								// arg ... add that extra crap to the 'from' might that work ? if the from has a 'select' override the whole statement ??
						//		strpos(string haystack, mixed needle [, int offset])
								$result_field_2 = self::band_aid('select ' . $this->argArg[$key]['record_select'][2] . ' FROM ' . $this->argArg[$key]['record_select'][0] . ' '. ($this->where?$this->where:' WHERE '. $this->argArg[$key]['record_select'][1] .' = ' .($result_field_t ?$result_field_t :$result_field )). ' LIMIT 1',4);
								// better to do this VIA join .... automagically 
								$result_field_2= $result_field_2[0][$this->argArg[$key]['record_select'][2]];
								} 
						// find some way to generate genf_input from here too
							$return .= ($key != $this->pkey? "\n\t\t<td>".($result_field_2?$result_field_2:$result_field)."</td>":"\n\t\t".'<td>'."\n\t\t\t".'<form method="POST">'."\n\t\t\t\t".'<input class = "list_submit_link" type="submit" title="Click to Edit" value="Edit">'.self::writeHandler('edit',$row->$pkey)."\n\t\t\t".'</form></td><td>'."\n\t\t\t".'<form method="POST">'.trim(self::writeHandler('delete_confirm',$row->$pkey))."\t\t\t\t".'<input type="submit" title="Click to Delete" name="delete_confirm" class="list_submit_link" value="Delete">'."\n\t\t\t</form>\n\t\t</td>");
						}
						$return .= "\n\t</tr>";					
						$count++;
					}
					unset($count,$result,$extra,$db_fields);
					// write another handler to replace the 'delete' handler
					return "\n<table class='sqlee_record_list' width='100%'>\n" . $return .  "\n</table>\n";
				// the idea is here to get a series of fields to display, and create a link that when pressed will go to edit that block
					break;
				case 'library_select':return self::build_attr_menu(self::dir_list('/system/libraries/'),'load_class_mbo');break;
				case 'conf_select':return self::build_attr_menu(self::dir_list('/system/conf/'),'edit_conf_mbo');break;
				case 'record_select':
					unset($this->where);
				// eliminate need for 'extra' and point to own argArg objects this needs signfigant improvement
					$std_arg_c = count ($this->argArg[$std_name]['record_select']);
					if($std_arg_c >3){
						foreach($this->argArg[$std_name]['record_select'] as $loc=>$item){
							if(strpos($item,'=') !== false) {
							// make this check better !
								$this->where = ' where ' . $item . ' ';
								// unset the 'where' item so it doesn't get used as a blank value selection message if one does not exist
								unset($this->argArg[$std_name]['record_select'][$loc]);
								}
						}
					}	
					$result = self::band_aid("SELECT $extra[1],$extra[2] FROM $extra[0] ".(!$this->where?'':$this->where)." GROUP BY $extra[2];",1);
					while(!is_bool($result) && $row = mysqli_fetch_row($result)) 
		   			if($options != NULL || $template != NULL)
		   				$return .= "\n\t\t<option value='$row[0]'". ($edit[$std_name] == $row[1]? ' selected ':($options[$std_name] == $row[0]?' selected ':($template[$std_name] == $row[0]?' selected ':''))) . ">$row[1]</option>";
					elseif($options == NULL && $template == NULL)
		   			//this code specifically added to properly deal with updating a record on the same page, and properly selecting only a single value from the pulldown menu
		   				$return .= "\n\t\t<option value='$row[0]'". ($edit[$std_name] == $row[0]    ? ' selected ':'') . ">$row[1]</option>";
		   			return "\t<select name='$std_name'>".($this->argArg[$std_name]['record_select'][3]!=1?"\n\t\t<option value=''>".($this->argArg[$std_name]['record_select'][4]?$this->argArg[$std_name]['record_select'][4]:'None').'</option>':''). "$return \n\t</select>\n";
					break;
				case 'enum':
					$extra = explode(',',$extra);
					$return.= ($extra[2] || $extra[1]?'<option value="'.($extra[2]?$extra[2]:$extra[1]).'">'.str_replace('_',' ',($extra[2]?$extra[2]:$extra[1])).' </option>':'');
					$new_val=  self::band_aid("show columns from `$std_name` like '$extra[0]'");
					$str_length = strrpos($new_val['Type'],')') - strlen($new_val['Type']);
					foreach(array_unique(explode(',',substr($new_val['Type'],strpos($new_val['Type'],'(') + 1,$str_length))) as $option){
					// watch the quotes around the option...
						$clean_option = str_replace("'",'',$option);
						$return.= "\n<option value=$option".($edit[$extra{0}] == str_replace("'",'',$option) ? ' selected ':'').">".str_replace('_',' ',$clean_option) . '</option>';
						}
					unset($new_val,$str_length);
					return "\t<select name='$extra[0]'>'$return'</select>\n\t\t";
				break;
				case 'radio':			
					$option_values = explode(',',trim($extra));
					foreach($option_values as $value) $result.= "\n\t\t".'<input type="radio" value="'. $value . '">' . $value .'<br>'; 
					return' <input type="'.$type.'" name="'.$std_name.'" value = "'.($option_values?$option_values:'true').'">';
				break;
				case 'checkbox':return' <input type="'.$type.'" name="'.$std_name.'" '.($_POST[$std_name] == 'true' || $edit[$std_name] == 'true'? ' CHECKED ':'') .'>';break;
				case "textarea":return "<textarea name='$std_name' ".(is_array($extra)?$extra[0]:$extra)." >".stripslashes( ($edit[$std_name] || $_POST[$std_name]? stripslashes(str_replace("''","'",($edit[$std_name]?$edit[$std_name] : $_POST[$std_name]))):'')).'</textarea>';break;
				}			
	}
	public function insert_record(){
		return ($_POST['delete'] == 'Cancel' || $_POST['delete_confirm'] || $_POST['delete'] == 'Delete'?NULL:self::genForm($this->edit_fields,'insert'));
	}
	public function checkField($field,$value,$table=NULL){
		$table = self::rar($table,'table');
		return self::band_aid("select `$field` from `$table` where `$field` = '$value' LIMIT 1;",3);	
	}
	private function validateInsertFields($return=NULL){
	// sorta helps out, checks the post var and see's if the latest insert was a sucessful one? Without accessing the db.
		if($this->argArg){
		// this creates a new array that organizes the field names grouped by the type of field parameter
			foreach($this->argArg as $field_name=>$item){
				if(is_array($item)){ 
					foreach($item as $key=>$value){					
						if($field_name != '' && $value != '' && !is_array($value))	
							$results["$value"] []= $field_name;
							elseif(is_array($value))
								foreach($value as $x=>$y)
									if($x == 'Validation')
										$results["$y"] []= $field_name;
									// stores 'validation' options as extra keys.. should be able to take multiple validations (if possible for specific values)
									// mostly overkill 
						}
				}
			}
			if(is_array($results)){	
			foreach(array_keys($results) as $key){
				foreach($_POST as $post_key=>$value){
					// post key is the key of the field (id,name)
					// key is the name of the field attribute (unique,required)
					if(is_array($results[$key]) && in_array($post_key,$results[$key]))
						{
						// adding more values is as easy as adding a case here, and then modifying sqwizard to support the option via the arrays, I might write more code to configure custom validations
							switch ($key){
								case 'unique':
									// check field takes a field, and a value, and table we won't need to validate a field that is identical to itself if we are updating we can just unset that field before it gets updated (hopefully) or cancel the update alltogether by changing the action
									if($this->action != 'update'){
									// ideally we should get these values elsewhere in the flow of things instead of making this duplicate call
										//die("SELECT $this->pkey from `$this->table` where `$this->pkey`=$this->id ");
										$record_check = self::band_aid("SELECT * from `$this->table` where `$this->pkey`=$this->id ",4);
										
										foreach($record_check[0] as $key=>$value){
											if($record_check[$key] == $_POST[$key]){
												$same = true;
												// don't want to udpate identical fields so it can be removed
												unset($_POST[$key]);
												}
										}
									}
									// here!!! if it is equal to that and the old variable was equal to something then we need to 
									// still update the value as blank to remove it ...
									if($_POST[$post_key] != '')
										$r_check = self::checkField($post_key,$_POST[$post_key]);
										else{
										$r_check = true;
										}
									// unique values can't be empty!
									if($r_check != false && !$same && $_POST[$post_key] != ''){
										$message[$post_key] []= ' invalid unique value ';
										unset($r_check);
									}
								break;
								case 'required':
								// to do // need to check on 'update' to see that previous value exists, and to override an 'empty' insert since post variables are automatically removed if emptied, need to add those fields back in if we are setting them
								// blank before we can do offical release the BEST way to do this is to get the ID of the record, look up that record and compare values
									if($_POST[$post_key] == '') $message[$post_key] []= $key;
									// idea if it is required, and empty, then we do not need to check other validations, attempt to exit the for loop with the keys ?
									end;
								break;
								// for these cases check the $value against a preg match string
								case 'alpha':
								// letters and numbers 
									if(!ctype_alnum($value)) $message[$post_key][]=' invalid alpha-numeric values ';
									else $_POST[$post_key] = preg_replace('/[^a-zA-Z0-9]/', '', $value);
									break;
								case 'email':
								// also goes a step further and checks to see if its a valid email.
									if(!filter_var($value, FILTER_VALIDATE_EMAIL)) $message[$post_key][]=' invalid email address ';
									else {
										$email = filter_var($value, FILTER_SANITIZE_EMAIL);
										list($email, $domain) = split('@', $email, 2);
								        if (! checkdnsrr($domain, 'MX'))$message[$post_key] []= ' invalid email address '; 
									    else 
									       	$_POST[$post_key] = "$email@$domain";
									}
									break;
								case 'string':
								// letters and spaces only
									if(!preg_match('/^[A-Za-z\s ]+$/', $value)) $message[$post_key][]= ' foreign values encountered ';
									else $_POST[$post_key] = filter_var($value, FILTER_SANITIZE_STRIPPED);
									break;
								case 'creditcard':
									if(!preg_match('/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/', $value)) $message[$post_key] [] = ' invalid credit card number, please enter numbers only, no spaces or dashes ';
									break;
								case 'number':
									// zeros not being processed properly and blank values very annoying
									if (!filter_var($value, FILTER_VALIDATE_INT)) $message[$post_key][]= ' invalid number ';
									else $_POST[$post_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
									break;
								case 'usphone':
									if (!preg_match('/\(?\d{3}\)?[-\s.]?\d{3}[-\s.]\d{4}/x', $value)) $message[$post_key][]= ' invalid us phone number ';
								break;
								case 'username':
									if(!preg_match('/^[a-zA-Z\d_@.]{6,50}$/i', $value)) $message[$post_key][]= ' invalid username ';
								break;
								case 'url':
								// could do a required look up to .. but really not needed .. we don't want to validate an empty post_key value (say if someone wants to remove a URL from a non-required field)
								// if the field is removed then a 'required' flag will be set.
									if( $_POST[$post_key] != '' && !filter_var($value, FILTER_VALIDATE_URL)) $message[$post_key][]= ' invalid url ';
									else {
									//$_POST[$post_key] = filter_var($url, FILTER_SANITIZE_URL);
									}
								break;
								case 'ip':
									if(!filter_var($value, FILTER_VALIDATE_IP)) $message[$post_key][]= ' invalid ip ';
								break;
								case 'strongpassword':
									if(!preg_match('/^(?=^.{8,}$)((?=.*[A-Za-z0-9])(?=.*[A-Z])(?=.*[a-z]))^.*$/', $value)) $message[$post_key][]= ' password must contain 8 characters, 1 uppercase, 1 lowercase and 1 number ';
								break;
								case 'ussocialsecurity':
									if(!preg_match('/^[\d]{3}-[\d]{2}-[\d]{4}$/',$value)) $message[$post_key][]= ' invalid social security number ' ;
								break;
								case 'imagelink':
								// not sure how this one works
									if(!@file_get_contents($value,0,NULL,0,1)) $message[$post_key][]= ' invalid image link ';
								break;
								}				
						}
				}
			// do an action based on $key that is manipulated via the switch post_key statement
			}
		}
		// if it returns one then that means it passed validation, otherwise an array with the field as key and its errors as its value as an array are returned
			return(is_array($message)?$message:1);		
	}
	}	

	private function getColumns($table=NULL,$fields=NULL){
		$table = self::rar($table,'table');	
		$fields = self::rar($fields,'fields');
		$fields = ($fields== NULL?'*':$fields);
		// ideally get from the fields but hard to select properly without passing a variable
		foreach(explode(',',$fields) as $key=>$value){
		 $value2 =trim($value);
		 $process []= "`Field` = '$value2'";
			if($value2 == '*'){
				$process = '';
				end;
				}
		}
		return (is_array($process) ? self::band_aid("show full columns from `$table` where ".trim(implode(' OR ', $process)),1) : self::band_aid("show full columns from `$table`",1)) ;
	}
	private static function writeHandler($action,$record_id=NULL){
	// if we pass it simply an action then it will use the object parameters for its settings... we should probably try to process the arg arrays better to clean them up, or create a new array with the fields that need to be validate use array merge??
		return "\n\t<input type='hidden' name='handler' value='$action::".self::rar($record_id,'id')."'>\n";
	}
	private function doArg($arg_string){
		foreach(explode('+~',$arg_string) as $exploded){
			$temp = explode('&&',$exploded);
			if(is_array($temp) && count($temp)>1){
				$result[$temp[0]] = explode('--',$temp[1]);
				foreach($result[$temp[0]] as $key=>$look){
					$temp2 = explode('[]',$look);
					if(is_array($temp2) && count($temp2) > 1){
						$result[$temp[0]][$temp2[0]] = explode('||',$temp2[1]);
						unset($result[$temp[0]][$key]);
					}
				}
		}
		}
		foreach($result as $key=>$item)
			if(is_array($item))
				foreach($item as $option=>$value)
					if($value == NULL || $value == '')
						$result[$key] = '';
					elseif(count($item) == 1 && is_int($key)) 
						$result[$key] = $value;						
			elseif($item == '' || $item == NULL)
				unset($result[$key]);
	return $result;	
	}
	private function dir_list($path){
		$handler = opendir($this->system_folder.$path);
	    while ($file = readdir($handler)) 
	      if ($file != "." && $file != "..") 	      
	        $results[] = str_replace('.php','',$file);
	    closedir($handler);
		return $results;
	
	}
	
	private function rar($var,$attr){
	//return attribute one line getter if $var is NULL and the attr exists that attr is returned, otherwise NULL is returned the array key exists function is required to prevent a php fatal error with accessing a non existant object parameter
		return ($var==NULL && is_array($attr) && array_key_exists($attr, $this)?$this->$attr:$var);
	}
	private static function iffy(&$ar1,&$ar2,$title,$string=NULL){	
		return ($string==NULL?($ar1[$title]?$ar1[$title]:$ar2[$title]):($ar1[$title]?$ar1[$title]:($ar2[$title]?$ar2[$title]:$string))) ;	
	}

	private function build_attr_menu($array,$field_name){
	// examines field type to determine what values should be in the attribute menu
		//strpos(string haystack, mixed needle [, int offset])
		foreach($array as $key=>$value)
			$menu .= "<option value='$value'>".(   is_string($key)?$key:$value)."</option>";
				
		return($menu ?'<select name="'.$field_name.'"><option value="">Select '.str_replace('_',' ',str_replace('_mbo','',$field_name)).'</option>' . $menu . '</select>' : '');
	}
}	