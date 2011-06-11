 myparse version 2 (PHP)
 
 
  @author		Ronaldo Barbachano http://www.redcapmedia.com
  @copyright  (c) June 2011
  @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
  @link		http://www.myparse.org


!!! Now with full form support, including validation, cross-table record referencing and more !!!

Changes -

Added Sqlee libraries -
	including sqwizard and sqleer
	Adds support for generated forms (from mysql tables) - create editors, and insert only forms
	Form wizard - no code involved, copy and paste..
	Adds support for email lists and campaigns
	
Some features have been removed but will be added back shortly...This release focuses on forms, record handling and the new conf files.

What is myparse

Myparse is a lightweight, low resource, php web application framework with a simple to understand templating system and easy to use, highly flexible database-record display engine. Elements on a web page are organized by 'blocks' which exist as records inside of a table that allow users, developers, and designers to use the same interface to customize their websites. Myparse can be used alongside existing CMS systems, such as wordpress, without making changes to existing databases or code.

Please checkout the myparse.org for tutorials and documentation.

How it Works

Myparse relies heavily on MySQL to return a specialized set of records that are dealt with as PHP objects. Once the record is retrieved with an advanced sql statement (based on the url entered, among other things), the php interprets its fields, manipulates the data (block_content field) and renders it to screen. Developers are able to manipulate any of the objects properties through use of the 'block_options' field, or create new ones if needed. Developers are also able to use data returned from a SQL statement inside of block fields (allowing developers to dynamically manipulate blocks' properties and data.)

Built In Features

Myparse supports many advanced features;

MySQL based Sessions & Authentication - makes it very difficult to hack a session, myparse only stores a session key inside a cookie that is compared against a temporary session record on the database, in the display of every page an authorized user views. If these keys do not match, the requested page isn't rendered.

MySQL based Template Processing - create templates in a separate table that become active through a 'join' statement in the master query.

Advanced Plugins - create your own plugins and functions that are accessible directly through 'blocks' and templates using simple mark up.

Features Planned -

Email manager - manage email campaigns
MySQL Table Wizard - create new mysql tables