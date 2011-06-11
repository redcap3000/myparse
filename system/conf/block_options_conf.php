<?php
				class block_options_conf{ 
				public static $_ = array(
	'no_cache'				=>'checkbox',
	'disable_add_titles'	=>'checkbox',
	'hide_urls'				=>'text:: size="100" ',
	'permissions'			=>'record_select::mp_groups,group_permissions,name',
	'unauthorized_msg'		=>'textarea:: rows="10" cols="40"',
	'form_edit_config'		=>'textarea:: rows="10" cols="40"',
	'form_record_config'	=>'text:: size="100" ',
	'form_mode'			=>'selection_menu:: none[=][!]insert only[=]2[!]manager[=]3',
	'form_manager_insert'=>'textarea:: rows="10" cols="40"',
	'form_filter' => 'text:: size="200"',
	'load_class' =>'library_select',
	'edit_conf'=>'conf_select');
				}