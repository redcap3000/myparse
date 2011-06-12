<?php
// for simple user registration

if(!$_POST['username']) echo ' <b>Please enter a username.</b><br>';
else{
		mysqli_query($mysqli_link,"select username from ".config::$_['table_prefix']." where username ='" . $_POST['username'] . "';");
		if(mysqli_affected_rows($mysqli_link) > 0) echo '<b>'.$_POST['username'].' username exists, please select another.</b><br>';			
		elseif(!$_POST['pass'] || !$_POST['pass_confirm']) echo '<b>Please enter a password and confirmation</b><br>';
		elseif($_POST['pass'] != $_POST['pass_confirm']) echo '<b>Passwords do not match please re-enter and try again.</b><br>';
		elseif(!$_POST['email']) echo '<b>Please enter an email address.</b><br>';
		// defaults to userlevel 5
		else {
			get_results("INSERT INTO `".config::$_['table_prefix']."users` ( `username`, `full_name`, `password`, `usergroup`, `email`) VALUES ( '".$_POST['username']."', '".$_POST['fullname']."', '".md5($_POST['pass'])."', '5', '".$_POST['email']."');",1);
			echo '<h2> Thank you for registering!</h2>';
		
			}
		}
		// report sucess somewhere .... possibly send email out to user ? do a registration confirmation link to avoid abuse ?