<?php

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>

<title>myparse installer</title>
<style type ="text/css">
form{width: 350px;margin: auto 40px;}
fieldset{padding-bottom:10px; margin-bottom:10px;float:left; width:320px;}
legend{font-size:25px;padding:5px;}
input, label{float:left;}
label{width:100%;font-size:20px;padding:5px;padding-bottom:10px;}
button{float:left;clear:both;}
textarea{width:600px; height:90px;}
</style>
</head>

<body>

<div class="" id="content">
<h1>myparse installer</h1>

<div id="stylized" class="myform">';
if (!isset($_POST['db_host']) or !isset($_POST['db_name']) or !isset($_POST['db_user']) or !isset($_POST['pass']) or !isset($_POST['pass_confirm']) or ($_POST['pass'] != $_POST['pass_confirm'])){
// else read config file/ user_vars file , parse out the values, and use them as the values in the config files

// need to use my form processing for this so we don't lose values tht have already been entered unless we add a shitload of if clauses...

	echo '
	
<form method="post" id="form">

<fieldset><legend>Admin Login</legend><label>Username</label><input
	type="text" name="username" value="admin" />'.($_POST&& !$_POST['username']?'<b>Please enter username.</b>':'').'
	<label>Password*</label><input
	type="password" name="pass"/>
	<label>Password Confirm*</label><input
	type="password" name="pass_confirm"/>
	<label>Email</label><input
	type="text" name="email"/>
	<label>Fullname</label><input
	type="text" name="fullname"/>
	</fieldset>

<fieldset><legend> Database Settings</legend> <label>Host</label><input
	type="text" name="db_host" value="localhost" /> <label>database</label><input
	type="text" name="db_name" value="" /> <label>username</label><input
	type="text" name="db_user" value="" /> <label>password</label><input
	type="password" name="db_pass" value="" />
	</fieldset>
<b>Optional settings: Leave fields alone to disable</b>	

	
	<fieldset>
		<legend> SEO Settings</legend>
		<label>Description</label>
		<input type="text" name="description" value="Your site description." size="40"/> 
		<label>Site Keywords</label>
		<input type="text" name="base_keywords" value="your,keywords,here" size="20" /> 
	</fieldset>
	
	<fieldset>
		<legend>Google Analytics</legend>
		<label>GA-ID</label>
		<input type="text" name="ga_id" value="UA-11111111-1" /> 
	</fieldset>
<button type="submit">Install</button>
</form>';
// to add - the 'additive title' option, as well as 'enable full page caching->cachingtimeout
}else{
	$mysqli_link = mysqli_init();
	if (!$mysqli_link)     die('mysqli_init failed');
	if (!mysqli_real_connect($mysqli_link, $_POST['db_host'], $_POST['db_user'], $_POST['db_pass'],$_POST['db_name'])) die('<h3>Invalid MySQL database settings. Please check.</h3>');
	// test db vars to see that they work!
	if(mysqli_query($mysqli_link,"SELECT id from mp_blocks LIMIT 1") == 1 || mysqli_query($mysqli_link,"SELECT id from mp_templates LIMIT 1"))
	die('<h1>Myparse is already installed</h1><h4>Please remove tables "mp_blocks" and/or "mp_templates" from ' . $_POST['db_name'] . ' and run this installer again.</h4>');
	if($_POST['pass'] != $_POST['pass_confirm']) die("<h3>Admin account passwords do not match.</h3>");
	else {
	echo '<a href="admin">Loginto admin panel</a> with : username:'. $_POST['username'] . ' password : ' . $_POST['pass'];
	
	 //$_POST['pass'] = md5(md5($_POST['pass']));
	}
	// make admin user run this after the structure is created as stand alone query ??
	
	
	$myparse_install ="CREATE TABLE IF NOT EXISTS `mp_blocks` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `block_name` varchar(255) DEFAULT NULL,
  `block_template` varchar(255) DEFAULT NULL COMMENT 'As defined in mp_templates',
  `block_type` enum('raw_html','inline_css','parse','html_head','dyn_head','full_doc','full_html','paginate') DEFAULT NULL COMMENT 'Special processing.',
  `block_order` tinyint(3) unsigned DEFAULT NULL,
  `urls` varchar(512) DEFAULT NULL COMMENT 'URL to show block in.',
  `master_select` text DEFAULT NULL COMMENT 'SQL statement here',
  `block_content` mediumtext DEFAULT NULL COMMENT 'HTML data here.',
  `page_title` varchar(255) DEFAULT NULL COMMENT 'This value becomes the URL''s page title.',
  `block_options` text DEFAULT NULL COMMENT 'option:value::',
  `status` enum('1','0') NOT NULL DEFAULT '1' COMMENT 'Show hide (1/0)',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


CREATE TABLE IF NOT EXISTS `mp_templates` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `block_template` varchar(255) NOT NULL,
  `block_type` enum('raw_html','inline_css','parse','html_head','dyn_head','full_doc','full_html','paginate') DEFAULT NULL,
  `page_title` varchar(312) DEFAULT NULL,
  `master_select` text DEFAULT NULL,
  `block_options` text DEFAULT NULL,
  `block_content` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

INSERT INTO `mp_blocks` VALUES (1,'logout','logout',NULL,NULL,'logout',NULL,NULL,NULL,NULL,'1'),(2,'admin','','',5,'admin','','','','permissions:SystemGOD::edit_conf:config','1'),(3,'admin_menu','','',4,'admin,admin_sqwizard,admin_blocks,admin_sqleete,admin_users,admin_groups','','<ul id=\\\"admin_menu\\\"><li><h4>Myparse</h4></li>\r\n<li><a href=\\\"admin\\\">Server Conf</a></li>\r\n<li><a href=\\\"admin_blocks\\\">Blocks</a></li>\r\n<li><a href=\\\"admin_users\\\">Users</a></li>\r\n<li><a href=\\\"admin_groups\\\">Groups</a></li>\r\n<li><a href=\\\"admin_sqwizard\\\">Form Wizard</a></li></ul>','','permissions:SystemGOD::unauthorized_msg:<form method=\\\"post\\\">\r\n				<fieldset>\r\n				<legend>Please login 1</legend>\r\n				<label>Username</label>\r\n				    <input type=\\\"text\\\" name=\\\"user_login\\\">\r\n				<label>Password</label>\r\n				    <input type=\\\"password\\\" name=\\\"password\\\" >\r\n				</fieldset>\r\n				\r\n				<input type=\\\"submit\\\" name=\\\"submit\\\" value=\\\"Login\\\">\r\n				</form>','1'),(4,'admin_blocks','','',5,'admin_blocks','','','','permissions:SystemGOD::form_edit_config:id&&+~block_name&&required+~block_template&&record_select[]mp_templates||id||block_template||0+~block_type&&+~block_order&&validation[]block_order+~urls&&required+~master_select&&+~block_content&&+~page_title&&+~block_options&&+~status&&required::form_record_config:mp_blocks&&id--id--block_name--block_type--urls--status::form_mode:3 ','1'),(5,'admin_groups','','',5,'admin_groups','','','','permissions:SystemGOD::form_edit_config:id&&+~name&&required--unique--validation[]name+~group_permissions&&+~group_level&&required--validation[]group_level::form_record_config:mp_groups&&id--id--name--group_permissions--group_level::form_mode:3','1'),(6,'admin_sqwizard','','',5,'admin_sqwizard','','','','load_class:sqwizard::permissions:SystemGOD','1'),(7,'header',NULL,'raw_html',0,'*',NULL,'<div id =\"page\">\r\n <div id =\"header\">\r\n  <h1>myparse</h1>\r\n </div>\r\n <div id =\"content\">\r\n\r\n','myparse','','1'),(8,'homepage',NULL,'raw_html',1,'homepage','','<h1>Welcome to myparse!</h1>\r\n\r\n<p>Please edit these records and replace with your site!</p>\r\n\r\n<p>A basic div structure is present for you here to modify, throw away, or use.</p>',NULL,NULL,'1'),(9,'footer',NULL,'raw_html',9,'*',NULL,' </div>\r\n <div id=\"footer\">\r\n  <b>2010</b>\r\n </div>\r\n</div>','','','1'),(10,'admin_users','','',0,'admin_users','','','','permissions:SystemGOD::form_edit_config:userid&&list+~username&&required--unique+~full_name&&required+~usergroup&&record_select[]mp_groups||id||name||0||Select usergroup--required+~email&&required--validation[]email+~last_ip&&+~logins_number&&+~randkey&&+~is_active&&::form_record_config:mp_users&&userid--userid--username--full_name--email--is_active::form_mode:3','1');

INSERT INTO `mp_templates` (`block_template`, `block_type`, `page_title`, `master_select`, `block_options`, `block_content`) VALUES
('login', 'raw_html', NULL, NULL, 'permissions:hide', '\r\n<form method=\"post\">\r\n<fieldset>\r\n<legend>Please login</legend>\r\n<label>Username</label>\r\n    <input type=\"text\" name=\"user_login\">\r\n<label>Password</label>\r\n    <input type=\"password\" name=\"password\" >\r\n</fieldset>\r\n\r\n<input type=\"submit\">\r\n</form>'),
('logout', NULL, NULL, NULL, 'logout:true::redirect:[root]::no_cache:true', NULL);
	
CREATE TABLE IF NOT EXISTS `mp_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `session_start` int(11) NOT NULL,
  `last_hit` int(11) NOT NULL,
  `user_session` varchar(255) NOT NULL,
  `hits` int(11) NOT NULL,
  `user_ip` varchar(100) NOT NULL,
  `last_ip` varchar(100) NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `mp_users` (
  `userid` int(9) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL DEFAULT '',
  `full_name` varchar(255) NOT NULL,
  `password` varchar(100) NOT NULL DEFAULT '',
  `usergroup` int(10) NOT NULL DEFAULT '0',
  `email` varchar(100) NOT NULL DEFAULT '',
  `first_ip` varchar(40) NOT NULL DEFAULT '0',
  `first_login` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `last_ip` varchar(40) NOT NULL,
  `logins_number` int(11) NOT NULL,
  `randkey` varchar(255) NOT NULL,
  `is_active` int(5) NOT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


CREATE TABLE IF NOT EXISTS `mp_groups` (
  `id` int(3) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `group_permissions` varchar(255) NOT NULL,
  `group_level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

INSERT INTO `mp_groups` (`id`, `name`, `group_permissions`, `group_level`) VALUES
(1, 'System Administrators', 'SystemGOD', 1),
(2, 'Modules Administrators', 'ModulesGOD', 2),
(3, 'Guests', 'ViewPublished', 100),
(4, 'Banned users', 'ViewPublished', 101),
(5, 'Normal User', 'normal', 4),
(6, 'employees', 'employees', 3);
";
	
	
	
$seo_meta = "INSERT INTO `mp_blocks` (`block_name`, `block_template`, `block_type`, `block_order`, `urls`, `master_select`, `block_content`, `page_title`, `block_options`, `status`) VALUES
('seo', 'seo_meta', '', 0, '*', NULL, NULL, NULL, NULL, 1);

INSERT INTO `mp_templates` (`block_template`, `block_type`, `page_title`, `master_select`, `block_options`, `block_content`) VALUES
('seo_meta', 'html_head', NULL, NULL, NULL, '<meta name=\"description\" content=\"[description]\" />\r\n<meta name=\"keywords\" content=\"[base_keywords]\" />\r\n<link rel=\"canonical\" href=\"[root][pass_url]\" />');

";
	// you could just try and use the $_SERVER variable instead of the counting...
	
	//$page_strlen =  strlen($pageURL);
	
	$pageURL = "http://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$page = explode('/',$pageURL);
	$p_count = count($page);
	$install_root = ($p_count > 1? '/' . $page[$p_count - 2] : '/');
	
// ideal make 'enviornments' enabled by default, and allow the definition of the first enviornment on install
// allow for a 'switcher' of sorts to go between different env in an admin interface
	$config_file =
'<?php
	class config{ 
		public static $_ = array(
			\'db_name\'=>\''.$_POST['db_name'].'\',
			\'allow_multiple_sessions\'=>true,
			\'db_user\'=>\''.$_POST['db_user'].'\',
			\'db_pass\'=>\''.$_POST['db_pass'].'\',
			\'db_host\'=>\''.$_POST['db_host'].'\',
			\'url\'=>\'http://' . $_SERVER["SERVER_NAME"]. $_SERVER["REQUEST_URI"] .'/\',
			\'block_table\'=>\'mp_blocks\',
			\'template_table\'=>\'mp_templates\',
			\'table_prefix\'=>\'\',
			\'stats\'=>false,
			\'add_titles\'=>false,
			\'enable_cache\'=>false,
			\'page_timeout\'=>0,
			\'compress_cache_output\'=>false,
			\'debug\'=>false);
				}';
				

	$htaccess_file = 
'<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType application/json "access plus 1 year"
ExpiresByType application/pdf "access plus 1 year"
ExpiresByType application/x-shockwave-flash "access plus 1 year"
ExpiresByType image/bmp "access plus 1 year"
ExpiresByType image/gif "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType image/png "access plus 1 year"
ExpiresByType image/svg+xml "access plus 1 year"
ExpiresByType image/tiff "access plus 1 year"
ExpiresByType image/vnd.microsoft.icon "access plus 1 year"
ExpiresByType text/css "access plus 1 year"
ExpiresByType video/x-flv "access plus 1 year"
ExpiresByType application/xslt+xml "access plus 1 year"
ExpiresByType image/svg+xml "access plus 1 year"
ExpiresByType application/mathml+xml "access plus 1 year"
ExpiresByType application/rss+xml "access plus 1 year"
ExpiresByType application/x-javascript "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType text/ecmascript "access plus 1 year"
ExpiresByType text/javascript "access plus 1 year"
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilter DEFLATE application/atom+xml
AddOutputFilter DEFLATE application/json
AddOutputFilter DEFLATE application/xhtml+xml
AddOutputFilter DEFLATE application/xml
AddOutputFilter DEFLATE text/css
AddOutputFilter DEFLATE text/html
AddOutputFilter DEFLATE text/plain
AddOutputFilter DEFLATE text/x-component
AddOutputFilter DEFLATE text/xml
# The following MIME types are in the process of registration
AddOutputFilter DEFLATE application/xslt+xml
AddOutputFilter DEFLATE image/svg+xml
# The following MIME types are NOT registered
AddOutputFilter DEFLATE application/mathml+xml
AddOutputFilter DEFLATE application/rss+xml
AddOutputFilter DEFLATE application/javascript
AddOutputFilter DEFLATE application/x-javascript
AddOutputFilter DEFLATE text/ecmascript
AddOutputFilter DEFLATE text/javascript
</IfModule>
Header unset ETag
FileETag None
Options +FollowSymLinks
RewriteEngine on
RewriteBase '.$_SERVER["REQUEST_URI"].'/
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{SCRIPT_FILENAME} !-d
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteRule ^(.*)$ index.php?p=$1 [L,QSA]';

// use sqlee for this form.

$admin_user = "INSERT INTO `mp_users` ( `username`, `full_name`, `password`, `usergroup`, `email`) VALUES ( '".$_POST['username']."', '".$_POST['fullname']."', '".md5($_POST['pass'])."', '1', '".$_POST['email']."');";
// this is sht.	
// turn this into checkboxes and let the user set this up from the admin interface to simplify initial install
	
	// if ikipress is not installed.. basic blocks..
if(($_POST['description'] != '' && $_POST['description'] != 'Your site description.') || ($_POST['base_keywords'] != '' && $_POST['base_keywords'] != 'your,keywords,here'))$myparse_install .= $seo_meta;
$myparse_install .= $basic_blocks;

		
$myparse_install .= $google_a;
	// leaving this in so I easily check the installation query for adding options
	//echo '<textarea>' . $myparse_install.$permissions_system.$aiki_default_groups.$admin_user . '</textarea> ';
	
echo (mysqli_multi_query($mysqli_link,$myparse_install.$permissions_system.$aiki_default_groups.$admin_user)?
	'<h1>Configuration setup successful </h1>
		<h2>pending actions below:</h2>
		<h3>Please create these files:</h3><p>By copying and pasting the text below into new documents on your server in the location indicated.</p><h4>"/system/conf/config.php"</h4>
	<textarea>' . $config_file . '</textarea> ' .'<h4>"/system/conf/user_vars.php"</h4><h4>"/.htaccess"</h4><i>(place in main/root directory of your myparse installation)</i><br/><textarea>' . $htaccess_file . '</textarea>
	':'Problem with installation query.');

}
echo '</div>
</div>
</body>
</html>';