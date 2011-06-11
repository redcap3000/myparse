<?php
//if(!defined('IN_MYPARSE'))exit;


	
	// to do figure out 'default' column processing ... do get a number of fields value to help with web creation on first screen ?
class sqleete extends myparse_layout{
	
	// why does datetime have a length column ?
	
	// so theres a weird little thing with strpos equaling zero, to remdy in some places.. i just add a space seems to make me avoid use of !== construct or should it be ==!	
	static $data_types = array(
	'char'		=>	array(array('CHAR','VARCHAR'),												'(length) [CHARACTER SET charset_name] [COLLATE collation_name]'),
	'text'		=>	array(array('TINYTEXT','TEXT','MEDIUMTEXT','LONGTEXT'),						'[BINARY] [CHARACTER SET charset_name] [COLLATE collation_name]'),
	'number'	=>	array(array('BIT','TINYINT','SMALLINT','MEDIUMINT','INT','INTEGER','BIGINT'),	'[(length)] [UNSIGNED] [UNSIGNED ZEROFILL] AUTO_INCREMENT'),
	'time'		=>	array(array('DATE','TIME','TIMESTAMP','DATETIME','YEAR'), ' ON UPDATE CURRENT_TIMESTAMP'),
	'set'		=>	array(array('ENUM','SET'),													'(value1,value2,value3,...) [CHARACTER SET charset_name] [COLLATE collation_name]'),
	
	'decimal'	=>	array(array('FLOAT','DOUBLE','DECIMAL','REAL'),						'[(length[,decimals])] [UNSIGNED] [ZEROFILL]'),
	'binary'	=>	array(array('BINARY','VARBINARY'),'(length)'),
	'blob'		=>	array(array('TINYBLOB','BLOB','MEDIUMBLOB','LONGBLOB'))
	
	);
	
	static $mysql_keys = array('PRIMARY','UNIQUE','INDEX','FULLTEXT');
	// unsigned zero fill a little tricky to get to show ... 
	static $mysql_attr = array('BINARY','UNSIGNED','UNSIGNED ZEROFILL','ON UPDATE CURRENT_TIMESTAMP');
	
	
	// could still match 'CURRENT TIMESTAMP' with the 'time'[2] value ??
	static $mysql_default = array('NULL','CURRENT TIMESTAMP','AS DEFINED');
	
	
	function make_option($value,$name=NULL){return "<option value='$value'>".(!$name?$value:$name)."</option>";}
	
	private function check_data_type($p1,$dtypes,$types,$value,$container_type='option'){

//		check_data_type('FULLTEXT',self::$data_types['text'][0],$mysql_keys,$value);
		foreach($types as $loc=>$d_value){
								
								if($d_value == $p1 &&  in_array($value,$dtypes)){
									if($container_type == 'checkbox') return true;
									$types [$loc]= "<option value='$d_value'>" . $d_value ."</option>";
									
									}
								elseif($d_value ==$p1)
									unset($types[$loc]);
								elseif($d_value !=$p1)
									$types [$loc]="<option value='$d_value'>" . $d_value . "</option>";
							
							}

		return($types?$types:NULL);

	}
	
	function __construct($system_folder){
		parent::__construct($system_folder);
	//	die(self::$data_types['time'][1]);
		$this->types = array();
		foreach(self::$data_types as $process)
				if(is_array($process[0]))$this->types = array_merge($this->types, $process[0]);
				
		foreach($this->types as $t)
			$temp .= self::make_option($t);
			
		$this->types = $temp;
		// turn types into HTML option code	
		// probably better to not make this in construct	
		$this->html= self::build_form();

	}

	private function build_attr_menu($key_num,$field_name,$extra){
	// examines field type to determine what values should be in the attribute menu
		//strpos(string haystack, mixed needle [, int offset])
		foreach(self::$mysql_attr as $attr)
			if(strpos($extra,$attr))
				$menu .= "<option value='$attr'>$attr</option>";
				
		return($menu ?'<td><select name="'.$field_name.'|-|'.$key_num.'">' . $menu . '</select></td>' : '<td></td>');
	}
	
	function build_form(){
		// build the select line ??
		$editor_fields = array('field_length','field_attr','field_null','field_index','field_col');

		if($_POST && $_POST['table_name'] && $_POST['n']){
		// show the 'n' number of fields here...
			if($_POST['n']) $n = $_POST['n'];
			// pass the table name to the next screen too as hidden var
			if($_POST['table_name']) $table_name = $_POST['table_name'];
			//unset($_POST['table_name']);
			//unset($_POST['n']);
		
		// now we are at second record selection screen where we can no limit the availablity of options based on the field type designation ?
		// process existing post variables ?
				$result = '<h2>'.$table_name.'</h2>
			<form method="POST">
			<input type="hidden" name="table_name" value ="'.$table_name.'">
				<table><tr><th>field name</th><th>type</th></tr>
				
			';
			
			// idea categorize the field types and put in multiple drop down menus? how do we know which to select?
			// make a simple drop down menu that lets a user select like 'integer' , 'decimel', 'varchar','text', 'time'
			// and then be presented with alist of variables that match that designation based on the static value
			
				for($x =1; $x <= $n; $x++){
				$result .= "\n<tr><td>". self::build_field_line('field_name|-|'.$x,'text') . '</td>' .'<td>' . '<select name="field_type|-|'.$x.'">' . $this->types . " </select></td></tr>\n" ;
				}

			}

			// do this on third screen ....
		if(!$_POST['n'] && $_POST['table_name']){
							$result = '<h2>'.$_POST['table_name'].'</h2>
			<form method="POST">
			<input type="hidden" name="table_name" value ="'.$_POST['table_name'].'">
				<table><tr><th>field name</th><th>length</th><th>Collation</th><th>A_I</th><th>Null</th><th>default</th><th>Attributes</th><th>Index</th><th>Comment</th>
				
			';

			foreach($_POST as $key=>$value){
			// ignore submit variable ?
				if($key != 'sumbit' && $key !='table_name'){
					$temp_key = explode('|-|',$key);
					// temp_key[0] = field_name temp_key[1] = the field number 
			
					if($temp_key[0] == 'field_name')
							$result .= "\n<tr><td><input type='hidden' value='$value' name='$key'>$value\n" ;
					if ($temp_key[0] == 'field_type'){
					// this isn't the valid field type because we haven't set it yet...
						
						$result .= "<br><small><b>$value</b></small></td>";
					
						$extra = self::get_mysql_extra($value);
						//if($value == 'DATETIME') die($extra);
						$result .= ($extra && strpos($extra, 'length')?"\n<td>\n" . self::build_field_line('field_length|-|'.$temp_key[1],'text') . "\n</td>\n": "\n<td><small>n/a</small></td>\n").
									(strpos($extra,'COLLATE')?"\n<td>" . self::build_field_line('field_col|-|'.$temp_key[1],'text') . "\n</td>":'<td><small>n/a</small></td>');
						// builds null field, index .. maybe remove the 'FULLTEXT' column for non text columns foreach($this->mysql_keys)	
						// reset default option menu	
						// filter default selection items by field type	
						$field_defaults = self::check_data_type('CURRENT TIMESTAMP',self::$data_types['time'][0],self::$mysql_default,$value);
						// filters 'index' column by field type ($value)	
					//	$mysql_keys = self::$mysql_keys;
						$mysql_keys = self::check_data_type('FULLTEXT',self::$data_types['text'][0],self::$mysql_keys,$value);
						$mysql_attr = NULL;
						foreach(self::$mysql_attr as $loc=>$attr)
							if(strpos($extra,$attr))
								$mysql_attr .= self::make_option($attr);
						$result .= '<td>'.(in_array($value, self::$data_types['number'][0])==true?self::build_field_line('field_auto_increment|-|'.$temp_key[1],'checkbox'):'') . '</td>'.
									'<td>'. self::build_field_line('field_null|-|'.$temp_key[1],'checkbox').'</td>'.
										'<td><select name="field_default|-|'.$temp_key[1].'"><option value=""></option>'.implode('',$field_defaults) . '</select><br>'.
									    self::build_field_line('field_default|-|'.$temp_key[1],'text').'</td><td>'.
									    
									    ($mysql_attr?'<select name="field_attributes|-|'.$temp_key[1].'"><option value="">'.$mysql_attr.'</option></select>':'').
									    
									   '</td><td><select name="field_index|-|'.$temp_key[1].'"><option value=""></option>'.implode('',$mysql_keys) . '</select></td>'.
									   
									   '<td>' . self::build_field_line('field_comment|-|'.$temp_key[1],'text') . '</td>
									   </tr>';

						}
					}

				}
				
			}
			
		elseif(!$_POST){
			$result = '
				<tr><td><form method="POST">
					Table name<input type="text" name="table_name"><br>
					Fields <input type="text" name="n">
					</td></tr>';

	}
		return $result . '<tr><td><input type="submit" value="Continue"></td></tr></table></form>';
	
	}
	
	function build_field_line($field_name,$data_type=NULL,$type=NULL){
	// not sure if it is not null .. use this to quickly get the 'extra' ? or just call it from the syntax
	// make a selection list with genf input ??
	// set null to type of field if something other than text input (for selection menus??
	
		if($_POST && count($_POST) > 1&& array_key_exists($field_name,$_POST))
			return self::genf_input(NULL,$_POST,$field_name,$data_type);
		else
			return self::genf_input(NULL,NULL,$field_name,$data_type);
		// how do we do the default null not null processing look at myphpadmin	
	
	}

	private function get_mysql_extra($field_type,$cat=NULL){
	// this matches a field type with the option in the datatypes array, can go faster if a cat is provided !
		if($cat!=NULL && (is_array(self::$data_types[$cat][0])&&in_array(strtoupper($field_type),self::$data_types[$cat][0])))
			return self::$data_types[$cat][1];
		else
			foreach(self::$data_types as $key=>$type)
				if(in_array($field_type,$type[0]) && $type[1]) return $type[1];	
		
	}
// get this from elsewhere please .... ok for now but DRY!!
	private function genf_input($template,$edit,$std_name,$type='text',$extra=NULL)
	{
	
		
	// clean up edit vars so we don't have to see any double apostrphies make sanitization function
		switch ($type){ 
		// still faster than loading sqlee
		// sometimes post vars are already enclosed in quotes!
		// validations are going to the default.. need to change a 'validation' entry to something better.... aghhh but how??? 
				default:	return "\t". '<input type="text" name="'.$std_name. '" value="'. self::iffy($edit,$_POST,$std_name).'"' .  ($extra!=NULL && !is_array($extra)?$extra:'').'>'			; 		break;
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
							for($x=0; $x <= 45; $x+=15)
							//for($x=0; $x <= 59; $x++)
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
					$result = self::band_aid("SELECT ". ($this->fields?$this->fields:'*'). " FROM $std_name GROUP BY $pkey;",1);
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
					return "\n<table width='100%'>\n" . $return .  "\n</table>\n";
				// the idea is here to get a series of fields to display, and create a link that when pressed will go to edit that block
					break;
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
					
					//die("SELECT $extra[1],$extra[2] FROM $extra[0] ".(!$this->where?'':$this->where)." GROUP BY $extra[2];");
					
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
					return(' <input type="'.$type.'" name="'.$std_name.'" value = "'.($option_values?$option_values:'true').'">');
				break;
				case 'checkbox': 		return(' <input type="'.$type.'" name="'.$std_name.'" value = "'.($extra?$extra:'true').'"'.($_POST[$std_name] == 'true' || $options[$std_name] == 'true'? ' CHECKED ':'') .'>');	break;
				case "textarea": 		return "<textarea name='$std_name' $extra[0] >".stripslashes( ($edit[$std_name] || $_POST[$std_name]? stripslashes(str_replace("''","'",($edit[$std_name]?$edit[$std_name] : $_POST[$std_name]))):'')).'</textarea>';break;
				}			
	}



	private static function iffy(&$ar1,&$ar2,$title,$string=NULL){	
	// this is a weird kind of array checker.. i didn't like the way it looked so i made this function
	// basically we look for the key of title inside of array1, if that doesn't exist we look for that value in array2, either way it is returned
	// you could pass a number into title as well
	// if string is provided, if both values do not exist then we return that string 
	// this is used in conjuction with the specific form 'value' (text input, textareas) fields to properly populate its value when in edit mode
	// would this be faster if array functions were to be used?
		return ($string==NULL?($ar1[$title]?$ar1[$title]:$ar2[$title]):($ar1[$title]?$ar1[$title]:($ar2[$title]?$ar2[$title]:$string))) ;	
	}
}
