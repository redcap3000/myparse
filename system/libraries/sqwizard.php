<?php
if(!defined('IN_MYPARSE'))exit;

/**
 * sqlee v2 (PHP)
  
 * @author		Ronaldo Barbachano http://www.redcapmedia.com
 * @copyright  (c) May 2011
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @link		http://www.myparse.org
 
 sqwizard - form wizard 
 
*/


// Agh check record select bug ,sometimes adds extra '--' if record select is the only option
class sqwizard extends myparse_layout{
public $html;
	function __construct($system_folder){
		parent::__construct($system_folder);
		$this->html = self::form_wizard();
	}
	// might be able to put this elsewhere (sqlee?)
	private function show_tables($option=NULL){
	// prevent from redoing all these queries on 'record_select' stuff without having to create a variable in the for loops..
		if(!$this->show_tables){
			foreach(self::band_aid('show tables;',3) as $table_name){
				// check if the table has any records
				// this is to prevent tables without any records from appearing in the
				// form editor - users must put at least one record (even if blank)
				// could do it for the user but doesn't 'feel' right messing with someones
					$r_check = self::band_aid("select 1 from $table_name limit 1;",3);
					if($r_check == 1) $return .= "\n\t\t<option value='$table_name'>$table_name</option>";
				}
			$this->show_tables = "<option value='' selected>Select $option</option>". $return;
			
		}
		return $this->show_tables;
	}
	private function makeArg($array=NULL){
	// this converts an associtative array into the syntax sqlee needs to
	// generate a form, simply multi-level imploding and easy-to-edit serialization
		$the_count = count($array);
		$counter = 1;
		foreach($array as $key=>$item){
			if(is_array($item)){
				if(is_string($key)) $result .= "$key&&";
				foreach($item as $key2=>$item2){
					if(is_array($item2))$result .= $key2.'[]'.implode('||',$item2) . ($n?'--':'');
					$n=1;
					}
				foreach($item as $key2=>$item2)
					if(is_array($item2)) unset($item[$key2]); 
				$result .= implode('--',$item) . ($the_count != $counter?'+~':'')  ;
				}
		$counter++;
		}
	return  $result;
	}
	public function form_wizard(){
	// need to create a master array with all the 'static' post variables, but for now this sorta works
		if($_POST['handler']) return;
		if($_POST && $_POST['table']){ 
		// LEVEL 1 - Column selector we pick out what columns are in the table to be able to select options for them at this page needs improvement
			foreach(self::band_aid('show columns from `'.$_POST['table'].'`;',4) as $row)
				foreach($row as $field=>$value)
				{
					if($field == 'Field'){
						$fields[]= $value;
						$field_name = $value;
					}
					elseif($field== 'Type'){
						if(strpos($value , '(') > 0 || strpos($value , ' ') > 0){
							$field_size = explode('(',$value);
							$field_size[1] = substr($field_size[1], 0, strrpos($field_size[1], ')'));
							$field_type[$field_name]= $field_size[0];
							}
						else $field_type[$field_name] = trim($value);
						// look at first three chars and look for 'enum' process fieldtype here (and make exception lists for record_selects for fields of enum type
					}
					//elseif($field == 'Null' && $value=='NO') $checked[$field_name]=$value;
						// force values of NO to validate (hide the 'require' button for these fields)
					elseif($field == 'Key'){
					// simple key processing.. not sure how to tackle MUL except to treat it like any other.. 
						 if($value=='PRI')$list_pkey = $field_name;
						elseif($value=='UNI') $checked[$field_name]=$value;
					
						}
					}
		// now generate the lists.. to do show/hide checkboxes when the option can't really use them according to column settings 
		// at the very least hide the first row ... ?
		// idea 'check boxes' to force certain values and set 'edible to false ...
			$field_options = array('list','editor','required','unique','record_select','file');
			// file field type probably can't be unique
			// filetype can only be validated as ?? nothing.
			// not supporting decimils yet.. just need the ergei formula for it its probably [0-9].[0-9] i assume...
			// defining our validation fields by setting the key to the type of validation, and an array with the mysql field types that will properly support this validation
			// can add additional validation types very easily...
			
			// define these in the object?
			$mfs = array(
				'dates'=>array('datetime','date','timestamp'),
				'numbers'=> array('tinyint','smallint','int','integer','bigint'),
				'chars'=>array('varchar','char','tinytext'),
				'paragraphs'=>array('text','mediumtext','longtext'))
				;
				// combined fields ...
			$mfs=array_merge($mfs,array(	
				'alphanum'=>array_merge($mfs['chars'],$mfs['paragraphs']),
				'string'=>array_merge($mfs['chars'],$mfs['paragraphs']),
				// extras
				'special'=>array('enum','set'),
				'file'=>array('varchar')
				));
				
				// gotta be careful on 'varchar' field types not being large enough to even hold the validation number for fields like ussocialsecurity, usphone, creditcard, username
			$field_defs = array('email'=> &$mfs['chars'],
								'number'=> &$mfs['numbers'],
								'url'=>&$mfs['chars'],
								'alpha'=>&$mfs['alphanum'],
								'string'=>&$mfs['string'],
								'usphone'=>&$mfs['chars'],
								'creditcard'=>&$mfs['numbers'],
								'username'=>&$mfs['chars'],
								'ip'=>&$mfs['chars'],
								'strongpassword'=>&$mfs['chars'],
								'ussocialsecurity'=>&$mfs['chars'],
								'imagelink'=>&$mfs['chars']);
								
			$field_defs_keys = array_keys($field_defs);	
			// fields that cannot have a field definition 
			// basically varchar /int / tinyint etc.. should be the only field for field validations in a perfect world

			$result.= "<table class='sqlee_wizard'>\n\t<form class='sqlee_wizard' method='post'>\n\t\t<tr>\n\t\t\t<th>Field</th>\n";
			foreach ($field_options as $header)
					$result .= "\t\t\t<th id='h_$header'>$header</th>\n";
			$result .= "<th>Validation</th>\t\t</tr>\n";
			foreach($fields as $field){
				$result .= "\t\t<tr>\n\t\t\t<td class='fname'>$field</td>\n";
				foreach($field_options as $option)
				// this runs through and generates the appropriate fields and selection values usually based on the type of field we are creating
					$result .= ($list_pkey != $field?($option =='record_select' && ($field_type[$field] != 'enum')?"<td><select name = '$field-$option'>" . self::show_tables() . '</select></td>':((in_array($field_type[$field], $mfs['special']) || in_array($field_type[$field], $mfs['paragraphs'])) && ($option =='record_select' || $option=='unique') || (!in_array($field_type[$field], $mfs['file']) && $option=='file') ?"<td></td>":"\t\t\t<td><input type='checkbox' name='$field"."[]' value='$option'".($checked[$field] && $option == 'required'?' CHECKED ':'')."></td>\n")):($list_pkey == $field && ($option == 'list' || $option == 'editor')?"\t\t\t<td class='pkey'><input type='checkbox' name='$field"."[]' value='$option' ".($option=='list' || $option=='editor'?"CHECKED ":'')."></td>\n":NULL));
				// so we go ahead and show options if thy are 'list' or 'editor' inside of the 'pkey' column we may want to make this row different to
				if($list_pkey != $field){	
					foreach($field_defs_keys as $validation)
				// use function i made elsewhere to make these options ?
						if(in_array($field_type[$field],$field_defs[$validation]) )
							$val_options .= "<option value='$validation'>$validation</option>";
					if($val_options) {	
						$result .= "<td><select name='$field-validation'><option value=''>Select type</option>$val_options</select></td>";
						// unset val options for next row
						unset($val_options);
						}	
									
					}else $result .= "</td>";			
						$result .= "\t\t\t</tr>\n";
			}	
			$result .= "\t\t<tr><td colspan='7'><br><h4>Fields using validation will NOT work with 'record' select. Please don't select both!</h4></td></tr><tr><td><input type='hidden' name='table_e' value='".$_POST['table']."'><input class='s_button' type='submit' value='Continue...'></td></tr>\n\t</form>\n</table>\n";
		}elseif($_POST && !$_POST['level_4'] && $_POST['table_e']){
		// this causes a bad load ... prevent this from happening
		// post level_4 doesn't get set so this executes when not needed... if we have a record select and a validation option present
		// LEVEL 2 - weed out variables that are empty this cleans up 'checked' boxes that have been selected and contain no other attributes ties into the automatic null/not null column processing
			foreach($_POST as $key=>$value)
				if($value == '') unset($_POST[$key]);
				if(is_array($value) && count($value == 1) && $value[0] == 'required') unset($_POST[$key]);	
				elseif(is_array($value)){				
					if(in_array('list',$value)) $list_fields [] = $key;
					if(in_array('editor',$value)) $editor_fields [] = $key;
							}
			// this should only show if we have items in the post that have a 'record select'
			$table = $_POST['table_e'];
			unset($_POST['table_e']);
			foreach($_POST as $key2=>$value2){
				$temp =explode('-',$key2);
				if(count($temp) > 1 && $temp[1] == 'record_select'){
				//	die(print_r($this));
				// god dammit these really do need their own ?
					$fields = self::band_aid("SELECT * FROM $value2 LIMIT 1;",4);
					//die(print_r($fields));
					// if a table is empty we can't do 'record_selects' properly.. could insert a blank record and remove it when we leave, running 
					// truncate...
					// or simply not show them in the 'get tables' list to even make a form from ...
					$fields = array_keys($fields);
					$option_menu ='';
					$option_menu_2 ='';
					foreach($fields as $floc=>$field) {
						$option_menu .= "<option value='$field'>$field</option>\n\t";
						$option_menu_2 .= "<option value='$field'".($floc == 1  ? ' SELECTED':NULL).">$field</option>\n\t";  
						
						}
					// this is for the second menu to automatically select the second menu item... could be done better...		
					
					
					if(is_array($_POST[$temp[0]]))
						$_POST[$temp[0]][array_search($temp[1],$_POST[$temp[0]])]= $temp[1] . '[]' . $value2;
					$result .= "<tr><td>$temp[0]</td>
									<td><select name='$temp[0]-$temp[1]-field'>\n\t$option_menu</select>
									</td>
									<td><select name='$temp[0]-$temp[1]-value'>\n\t$option_menu_2</select>
									</td>
									<td>
									<input type='radio' name='$temp[0]-$temp[1]-null' value='false' CHECKED>Null<br/>
									<input type='radio' name='$temp[0]-$temp[1]-null' value='true'>Not Null
									</td>
									<td>
									<input type = 'text' name='$temp[0]-$temp[1]-message' size='20'>
									</td>
									<td>
									<input type = 'text' name='$temp[0]-$temp[1]-where' size='40'>
									</td>
								</tr>\n";
					$x =1;	
					}				
				}
			// this means we have no record selects to process and should spit out the makeArgs ...	
			// is this needed ?????
			$result =($x != 1?'': "\n<form class='sqlee_wizard' method='POST'>
										<table>
											<tr>
												<td><h3>Choose Additional Options for Record Selections</h3></td>
											</tr>
											<tr>
												<td><p>These setting define the behavior of the specified fields below.</p></td>
											</tr>
											<tr>
												<th>Field</th><th>Select Value</th><th>Select Title</th><th>Null</th><th>Selection message</th><th>Where Clause</th>
											</tr>
											$result
											<tr>
												<td><input type='submit' value='Next'></td>
											</tr>
											\n\t</table><input type='hidden' name='level_4' value='".(serialize($_POST))."'>
										<input type='hidden' name='table_e' value='$table'>
									</form>");
			if(!$x){
				foreach($_POST as $key=>$array){
					if(is_array($array))
						foreach($array as $key2=>$param){
							if($param == 'editor' || $param == 'list') {
								unset($_POST[$key][$key2]);
								if($param == 'list') $list_fields []=$key;
								elseif($param == 'editor') $edit_fields []= $key;
								}
						}
						// search for 'validation' to add that option to the syntax
				}
				// check for validation variables
				foreach($_POST as $key=>$array){
					if(strpos($key,'validation') !== false) {
					// what happens if we do the below before we come to the validation key?
						$temp= explode('-',$key); 
						$temp = $temp[0];
						$_POST[$temp] []= "validation[]$temp";
						unset($_POST[$key]);	
						unset($temp);
						
					}
				}
				foreach($_POST as $key=>$array){
						$kloc = array_search($key,$edit_fields);
					// this needs improvement
					// this double and is problematic if we are at a specific place	
					
					// this double and thing is troublesome.. while it doesn't throw anything off its ugly...
					// also prevent people from going to the 'records_select' field if they select it results in gazillion errors... do an onconstruct check for the validation
					// and then modifiy the post to go to the right place
						$edit_fields[$kloc] = $edit_fields[$kloc] . '&&' . (is_array($array)? (count($array) > 1? implode('--',$array):$array[0]) : $array) ;	
					}	
				}
				// so we can add a conditional to check that the param is both editor and list ?, this is that unique action that turns a list into a list editor and vis versa
				// we need more '&&' here ?
		if(is_array($edit_fields)){
				$arg1 =  implode('+~',$edit_fields);
				// adding in an extra 'pkey' field for security feature (also can be used to quickly disable edit/delete capability in any form)
				$arg2 = $table .'&&' . $list_fields[0] . '--' .implode('--',$list_fields);
				// need to return instead of echoing 
				echo "<div class='sqlee_wizard'>Your record editor syntax is 'form_edit:$arg1 :: record_config:$arg2'</div>";
				}
			
		}elseif($_POST && $_POST['level_4']){
			$level_4 = unserialize(stripslashes($_POST['level_4']));
			//heres another simpler way to do this ... repurpose the fields and then combine
			foreach($_POST as $key=>$value){
				if($key != 'level_4' && $key != 'table_e'){
				$exp = explode('-',$key);
				$old_loc = strpos($level_4[$exp[0]], $exp[1]);
				foreach($level_4 as $key2=>$value2){
					if($key2 == $exp[0]){
						foreach($value2 as $key3=>$value3)
							if(is_string($value3)){ 
								$temp = explode('[]',$value3);
								if(count($temp)==2){
									$con = $temp[0];
									$level_4[$key2][$con] []= $temp[1];
									unset($level_4[$key2][$key3]);
									}
							}
					}
				}
				if($value != '')
				$level_4[$exp[0]][$exp[1]][] = ($value == 'true'?1:($value == 'false'?0:$value)); 
				}
			}
			unset($_POST['level_4']);
			foreach($level_4 as $key=>$item){
			if($item == '') unset($level_4[$key]);
			else{
					if(is_array($item)){
						foreach($item as $key2=>$value){
							// clean this up
							if($value == 'list'){
								$the_fields[$value] []=$key;
								if(count($item) > 1)
								unset($level_4[$key][$key2]);
							}elseif($value == 'editor' && array_key_exists('record_select',$level_4[$key]))
							// we push the first value of the record select array to allow for the list to contain the edit/delete buttons
								unset($level_4[$key][$key2]);
							elseif($value =='editor')
								unset($level_4[$key][$key2]);
						}
					}
				}
			}
			// the 'safe argument...'		
			$arg2 = $_POST['table_e'] . '&&'. implode('--',$the_fields['list']);
			unset($level_4['table_e']);
			
			// DRY
			foreach($level_4 as $key=>$value){
					if(strpos($key,'validation') !== false) {
					// what happens if we do the below before we come to the validation key?
						$temp= explode('-',$key); 
						$temp = $temp[0];
						$level_4[$temp] []= "validation[]$temp";
						unset($level_4[$key]);	
						unset($temp);
						
					}
			}
			$arg1 = self::makeArg($level_4);
			$arg3= explode('&&',$arg2);
			$fields_temp = explode('--',$arg3[1]);
			$arg3[1] = $fields_temp[0] . '--' . $arg3[1];
			$arg3 = implode('&&',$arg3);
			// arg three adds the first field to itself to deal with a little security design pattern i created to quickly disable editing features of a table
			echo "<div class='sqlee_wizard'> Your record editor php syntax is<textarea class='record_syntax'>".'$'."form->record_editor('$arg1','$arg3')</textarea></div>";
		}
		elseif (!$_POST) $result .= "<form class='sqlee_wizard' method='post'><select name='table'>\n".self::show_tables()."\n</select><input type='submit' value='Create Form'><h4>Only tables with at least 1 record can use this wizard.</h4></form>";
		return $result;
		// make a screen to let the user enter in their database information to connect to maybe use genf input to do the easy work for us?
	}

}