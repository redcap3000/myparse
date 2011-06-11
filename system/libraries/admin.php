<?php
if(!defined('IN_MYPARSE'))exit;

class admin{
	function __construct($system_folder,$conf_name=null){
		$this->config_name = ($conf_name==null?'config':$conf_name);
		$this->e_file = $system_folder.'/system/conf/'.$this->config_name.'.php';
		$this->html = self::get_config();
		$this->html .=($_POST && $_POST['Update']?self::writeConfig():NULL);
		//if($_POST && $_POST['Update']) self::reload_form(5);
	}
	private function reload_form($refresh=0,$url=NULL){	
		unset($_POST);die(header("Refresh: $refresh; url=".$this->url->url));
	}
	
	// doesn't show the updated config properly a
	private function writeConfig(){
	unset($_POST['Update']);
		foreach($_POST as $key=>$value)
			$result []= "'$key'=>" . ($value == 'true' || $value == 'false' || is_numeric($value)?$value:"'$value'");
		if (is_writable($this->e_file)) {
		    if (!$handle = fopen($this->e_file, 'w')) 
		      die("Cannot open file ($this->e_file)");
		    if (fwrite($handle, '<?php
				class '.$this->config_name.'{ 
				public static $_ = array('."\n\t" .implode(",\n\t\t",$result) . ");
				}") === FALSE) 
		       return ("Cannot write to file ($this->e_file)");
		    return self::reload_form(0)."Success, wrote to file ($this->e_file)";
		
		 fclose($handle);
		} else return "The file $this->e_file is not writeable, please change file permissions to 755 or better";
			
	}
	public function get_string_between($string, $start, $end){
		if (strpos(" ".$string,$start) == 0) return '';
		$string = " ".$string;
		$ini = strpos($string,$start) + strlen($start);
		return substr($string,$ini,strpos($string,$end,$ini) - $ini);
	}
	
	private function get_config(){
		/* idea here is to first replace some obvious things and make it easier to for get_string_between to do its magic...
		   this could be done for the writing back of files, we simply search for the changed values, correlating the line to the key to see if it  matches
		   store content to another place, and rewrite ALL values not paying close attentionto certain unneeded formatting (splaces betweween the equals sign and so on
		*/
		
		$fd = fopen ($this->e_file, "r");
		$result=self::get_string_between(fread ($fd,filesize ($this->e_file)),'public static $_ = array(',');');
		fclose ($fd);
		$result = explode(',',$result);
		
		foreach($result as $a=>$b)
			$result[$a] = explode('=>',str_replace("'",'',trim($b)));

		foreach($result as $a=>$b){
			unset($key_name);
			foreach($b as $c=>$d)
			{
				if($c==0) $key_name = trim($d); 
				elseif($c==1 && $key_name) $result[$key_name] = trim($d);	
			}
			unset($result[$a]);
		}
		$return = '<h1>Myparse Configuration Editor</h1>
			<form id="myp_config" method="post">';
		
		foreach($result as $name=>$value){
		// get the size of the value to switch between field selection sizes
		$v_size = strlen($value);
		//die('<b>'.strlen($value) . '</b>');
			$return .= '<fieldset>
							<legend>'.$name . '</legend>' 
							.($value=='false' || $value=='true'?
								'<label>On</label><input type="radio" name="'.$name.'" value="true"' .  ($value=='true'? 'CHECKED':'') .'>
															<label>Off</label><input type="radio" name="'.$name.'" value="false"' .  ($value=='false'? 'CHECKED':'') .'><br>':
															'<input type="'.($name=='db_pass'?'password':'text' . ($v_size > 100?' size="200" ':'size="50"' )).'" name="'. $name . '" value='."'". $value. "'" .'>').'</fieldset>';
		}
		return $return . '<input type="submit" name="Update" value="Update"></form>';
	}	
}

