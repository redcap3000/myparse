<?php
if(!defined('IN_MYPARSE'))exit;

class sqleer extends sqlee{
	function __construct($system_folder){
	// load sqlee.php (hope there is a valid db connx! make new object and use the band_aid method couldn't get auto load to work properly :/
		parent::__construct($system_folder);
		if($_POST['emailer_send']=='Send'){
			if($_POST['email_msg_id'] == '' || !$_POST['email_msg_id']) echo '<div class="status">' . 'Please select a message to send'. '</div>';
			// get info from post ?
			// the table with the messages in it
			// hard code it for sfb but create more flexible for full featured version
			// to be set as block options
			$_POST['message_table'] = 'sqlee_email_msg';
			$_POST['email_table'] = 'sqlee_emails';
			//	$post['email_msg_id']
				// validate these two addresses...
			//	$post['email_from']
			if(!$_POST['email_reply_to'] && $_POST['email_from'])$_POST['email_reply_to'] =$_POST['email_from'];
				
			echo '<div class="status">'. self::send_mailer($_POST['email_table'],$_POST['message_table'],$_POST['email_msg_id'],array('from'=>$_POST['email_from'],'reply_to'=>$_POST['reply_to'])) . '</div>';
		}
	}
	
	// bandaid needs to be its own function in another library i'm tired of recalling it...
		public function build_emailer_form($from,$table=NULL,$reply=NULL){
		return		
		"<form method='post'>
			<table>
				<tr>
					<td>
						<label>Select message to send</label>
					</td>
					<td>
					".($table == NULL?self::build_email_message_list():self::build_email_message_list($table))
		
				    ."</td>
				    </tr>
					<tr>
						<td><label>From</label></td>
						<td><input type='text' name = 'email_from' value='$from'></input></td>	
					</tr>
					<tr>
						<td>
							<label>Reply-to</label></td>
						<td>
							<input type='text' name = 'email_reply_to' value='".($reply == NULL ? $from:$reply)."'></input>
						</td>
					</tr>
					<tr>
						<td colspan='2'>	
							<input style='float:right;' type='submit' name ='emailer_send' value='Send'>
						</td>
					</tr>
			</table>
		</form>
		";	
	}

	public function build_email_message_list($emailer_table='sqlee_email_msg'){
		$emailer_table = self::band_aid("select `id`,`subject`,`status` from `$emailer_table`",4);
		foreach($emailer_table as $row)
			$result .= "<option value='".$row['id']."'>".$row['subject']."</option>\n";
		return '<select name="email_msg_id"><option value="">Select Message to send</option>' . $result . '</select>';
	}

	public function send_mailer($email_table,$emailer_table,$mail_id,$mode=NULL,$field=NULL){
	// field defaults to 'email' but change if the field name is different inside the table
		$field= ($field?$field:'email');
		$email_table = self::band_aid("select `$field` from `$email_table`;",4);
		$email_table = (count($email_table) > 1?$email_table:$email_table[0]);
		$sucess_query = "UPDATE `$emailer_table` set `status` = 'sent' where id='$mail_id'";
		$emailer_table = self::band_aid("select `id`,`subject`,`message`,`status` from `$emailer_table` where `id` = '$mail_id';",4);
		// ask for confirmation on sending the email if the emailer table status is 'sent'
		$emailer_table = $emailer_table[0];	
		if($emailer_table['status'] == 'sent'){
		// to do allow user to resend email...
			return 'This email has already been sent. Please change the mode to "draft" to resend.';
		}else{
		// we should change the field from the send_mail if the email is sucessfully sent.. but hard coding it for now
		// for simplicity sake
			self::band_aid($sucess_query,2);
			return ($mode?self::send_mail($email_table,$emailer_table['subject'],$emailer_table['message'],$mode['from'],$mode['reply_to'],$mode['xmailer']):self::send_mail($email_table,$emailer_table['subject'],$emailer_table['message']));
		}
	}

	public function send_mail($to,$subject,$body,$from=NULL,$reply_to=NULL,$xmailer=NULL,$cc=NULL,$bcc=NULL,$mime=NULL,$content_type=NULL)
	{
	// will send emails one at a time if passed multiple addresses.
	// can send a single email, or a bunch of emails provided a list of emails with commas
		$to = (is_string($to) && strpos(',',$to) !== FALSE ?explode(',',trim($to)):(is_array($to)?$to:$to));
		$header['MIME-Version: '] = (!$mime?'1.0':$mime);
		$header['Content-type: '] = (!$content_type?'text/html; charset=iso-8859-1':$content_type);
		if	($from) 			$header ['From: ']= $from;
		if	($reply_to)			$header ['Reply-To: ']= $reply_to;
		if	($xmailer) 			$header ['X-Mailer: ']= $xmailer;
		if	($cc) 				$header ['CC: ']= $cc;
		if	($bcc) 				$header ['BCC: ']= $bcc;
		if  ($xmailer) 			$header ['X-Mailer: ']= ($xmailer == 'php'?'PHP/'.phpversion():$xmailer);
		//$header= implode("\r\n",$header);
		// yikes we don't have a key in the header to use implode bad php! bad php..
		foreach($header as $key=>$value) $headers .= "$key$value\r\n";
		unset($header);	
		if(is_array($to)){ 
			foreach($to as $r)
				if(!mail($r, $subject, $body,$headers)) $result[] = $email;
			}
		elseif(!mail($to,$subject,$body,$headers)) $result = $to;		
		unset($headers,$to,$from,$reply_to,$xmailer,$cc,$bcc);
		return (!$result?'Everyone received the message.':(count($result)==1? "$result[0] did not get the message.":impode(', ',$result) . " did not get the message."));
	
	}
	}