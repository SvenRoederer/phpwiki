<?php 
/*
 * Todo: 
 *   * validate input (fix javascript, add POST checks)
 *   * start this automatically the first time
 *   * fix include_path
 *   * eval index-user.php or index.php to get the actual settings.
 *   * ask to store it in index.php or index-user.php
 * 
 * The file index-user.php will be generated which you can use as your index.php.
 */

$tdwidth = 700;
$config_file = 'index-user.php';
$fs_config_file = dirname(__FILE__) . (substr(PHP_OS,0,3) == 'WIN' ? '\\' : "/") . $config_file;
if (isset($HTTP_POST_VARS['create']))  header('Location: configurator.php?create=1#create');
printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", 'iso-8859-1'); 
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<!-- $Id: configurator.php,v 1.9 2002-09-15 17:28:27 rurban Exp $ -->
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Configuration tool for PhpWiki 1.3.x</title>
<style type="text/css" media="screen">
<!--
/* TABLE { border: thin solid black } */
body { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 80%; }
pre { font-size: 120%; }
td { border: thin solid black }
tr { border: none }
td.part { background-color: #eeaaaa; }
td.full_instructions { background-color: #eeeeee; }
td.instructions { background-color: #ffffee; width: <?php echo $tdwidth ?>; }
td.unchangeable_variable_top   { border-bottom: none; background-color: #eeeeee; }
td.unchangeable_variable_left  { top-bottom: none; background-color: #eeeeee; }
/* td.unchangeable_variable_right { top-bottom: none; background-color: #ffffff; } */
-->
</style>
<script language="JavaScript" type="text/javascript">
<!--
function update(accepted, error, value, output) {
  if (accepted) {
    document.getElementById(output).innerHTML = "<font color=\"green\">Input accepted.</font>";
  } else {
    while ((index = error.indexOf("%s")) > -1) {
      error = error.substring(0, index) + value + error.substring(index+2);
    }
    document.getElementById(output).innerHTML = "<font color=\"red\">" + error + "</font>";
  }
}

function validate(error, value, output, field) {
  update(field.value == value, error, field.value, output);
}

function validate_ereg(error, ereg, output, field) {
  regex = new RegExp(ereg);
  update(regex.test(field.value), error, field.value, output);
}

function validate_range(error, low, high, empty_ok, output, field) {
  update((empty_ok == 1 && field.value == "") ||
         (field.value >= low && field.value <= high),
         error, field.value, output);
}

function toggle_group(id) {
  group = document.getElementById(id);
  text = document.getElementById(id + "_text");
  input = document.getElementById(id + "_input");
  if (group.style.display == "none") {
    text.innerHTML = "Hide options.";
    group.style.display = "block";
    input.value = 1;
  } else {
    text.innerHTML = "Show options.";
    group.style.display = "none";
    input.value = 0;
  }
}
-->
</script>
</head>
<body>

<h1>Configuration tool for PhpWiki 1.3.x</h1>

<?php
//define('DEBUG', 1);
/**
 * The Configurator is a php script to aid in the configuration of PhpWiki.
 * Parts of this file are based on PHPWeather's configurator.php file.
 * http://sourceforge.net/projects/phpweather/
 *
 *
 * TO CHANGE THE CONFIGURATION OF YOUR PHPWIKI, DO *NOT* MODIFY THIS FILE!
 * more instructions go here
 *
 * Todo: 
 *   * start this automatically the first time
 *   * fix include_path
 *   * eval index.php to get the actual settings.
 *   * ask to store it in index.php or settings.php
 * 
 * The file settings.php will be generated which you can use as your index.php.
 */


//////////////////////////////
// begin configuration options


/**
 * Notes for the description parameter of $property:
 *
 * - Descriptive text will be changed into comments (preceeded by //)
 *   for the final output to index.php.
 *
 * - Only a limited set of html is allowed: pre, dl dt dd; it will be
 *   stripped from the final output.
 *
 * - Line breaks and spacing will be preserved for the final output.
 *
 * - Double line breaks are automatically converted to paragraphs
 *   for the html version of the descriptive text.
 *
 * - Double-quotes and dollar signs in the descriptive text must be
 *   escaped: \" and \$. Instead of escaping double-quotes you can use 
 *   single (') quotes for the enclosing quotes. 
 *
 * - Special characters like < and > must use html entities,
 *   they will be converted back to characters for the final output.
 */

$SEPARATOR = "///////////////////////////////////////////////////////////////////";

$copyright = '
Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam = array(
"Steve Wainstead", "Clifford A. Adams", "Lawrence Akka", 
"Scott R. Anderson", "Jon �slund", "Neil Brown", "Jeff Dairiki",
"St�phane Gourichon", "Jan Hidders", "Arno Hollosi", "John Jorgensen",
"Antti Kaihola", "Jeremie Kass", "Carsten Klapp", "Marco Milanesi",
"Grant Morgan", "Jan Nieuwenhuizen", "Aredridel Niothke", 
"Pablo Roca Rozas", "Sandino Araico S�nchez", "Joel Uckelman", 
"Reini Urban", "Tim Voght");

This file is part of PhpWiki.

PhpWiki is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

PhpWiki is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with PhpWiki; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
';



$preamble = "
  This is the starting file for PhpWiki. All this file does is set
  configuration options, and at the end of the file it includes() the
  file lib/main.php, where the real action begins.

  If you include this file to override the default settings here, 
  you must also include lib/main.php.

  This file is divided into seven parts: Parts Zero, One, Two, Three,
  Four, Five and Six. Each one has different configuration settings you can
  change; in all cases the default should work on your system,
  however, we recommend you tailor things to your particular setting.
";



$properties["Part Zero"] =
new part('_part0', false, "
Part Zero: If PHP needs help in finding where you installed the
rest of the PhpWiki code, you can set the include_path here.");

if (substr(PHP_OS,0,3) == 'WIN') {
    $include_path = ini_get('include_path') . ';' . dirname(__FILE__);
    if (strchr(ini_get('include_path'),'/'))
	$include_path = strtr($include_path,'\\','/');
} else {
    $include_path = ini_get('include_path') . ':' . dirname(__FILE__);
}

$properties["PHP include_path"] =
new _ini_set('include_path', $include_path, "
NOTE: phpwiki uses the PEAR library of php code for SQL database
access. Your PHP is probably already configured to set
include_path so that PHP can find the pear code. If not (or if you
change include_path here) make sure you include the path to the
PEAR code in include_path. (To find the PEAR code on your system,
search for a file named 'PEAR.php'. Some common locations are:
<pre>
  Unixish systems:
    /usr/share/php
    /usr/local/share/php
  Mac OS X:
    /System/Library/PHP
</pre>
The above examples are already included by PhpWiki. You shouldn't
have to change this unless you see a WikiFatalError:
<pre>
    lib/FileFinder.php:82: Fatal[256]: 
    DB.php: file not found
</pre>
Define the include path for this wiki: pear plus the phpwiki path
<pre>
\$include_path = '.:/Apache/php/pear:/prog/php/phpwiki';
</pre>
Windows needs ';' as path delimiter. cygwin, mac and unix ':'
<pre>
if (substr(PHP_OS,0,3) == 'WIN') {
  \$include_path = implode(';',explode(':',\$include_path));
} elseif (substr(PHP_OS,0,6) == 'CYGWIN') {
  \$include_path = '.:/usr/local/lib/php/pear'.
                     ':/usr/src/php/phpwiki';
} else {
  ;
}</pre>");

$properties["Part Null"] =
new part('_partnullheader', "", "
Part Null: Don't touch this!");



$properties["Part Null Settings"] =
new unchangeable_variable('_partnullsettings', "
define ('PHPWIKI_VERSION', '1.3.4pre');
require \"lib/prepend.php\";
rcs_id('\$Id: configurator.php,v 1.9 2002-09-15 17:28:27 rurban Exp $');", "");


$properties["Part One"] =
new part('_partone', $SEPARATOR."\n", "

Part One:
Authentication and security settings. See Part Three for more.
");



$properties["Wiki Name"] =
new _define_optional('WIKI_NAME', 'PhpWiki', "
The name of your wiki.
This is used to generate a keywords meta tag in the HTML templates,
in bookmark titles for any bookmarks made to pages in your wiki,
and during RSS generation for the title of the RSS channel.");


$properties["Reverse DNS"] =
new boolean_define('ENABLE_REVERSE_DNS',
                    array('true'  => "true. perform additional reverse dns lookups",
                          'false' => "false. just record the address as given by the httpd server"), "
If set, we will perform reverse dns lookups to try to convert the
users IP number to a host name, even if the http server didn't do it for us.");

$properties["Admin Username"] =
new _define_optional_notempty('ADMIN_USER', "", "
<a name=\"create\">You must set this! Username and password of the administrator.</a>",
"onchange=\"validate_ereg('Sorry, ADMIN_USER cannot be empty.', '^.+$', 'ADMIN_USER', this);\"");

$properties["Admin Password"] =
new _define_password_optional('ADMIN_PASSWD', "", "
For heaven's sake pick a good password.
You can also use our <a target=\"_new\" href=\"passencrypt.php\">passwordencrypter</a> to encrypt an existing password or 
the \"Create Password\" button to create a good password.",
"onchange=\"validate_ereg('Sorry, ADMIN_PASSWD must be at least 4 chars long.', '^....+$', 'ADMIN_PASSWD', this);\"");

$properties["Encrypted Password"] = 
new boolean_define_optional('ENCRYPTED_PASSWD',
                    array('true'  => "true. ADMIN_PASSWD is encrypted",
                          'false' => "false. ADMIN_PASSWD is plaintext. Not recommended!"), "
If you used the passencrypt.php utility to encode the password or use an existing unix-crypt password,
then set this to true. Recommended!");

$properties["ZIPdump Authentication"] =
new boolean_define_optional('ZIPDUMP_AUTH', 
                    array('false' => "false. Everyone may download zip dumps",
                          'true'  => "true. Only admin may download zip dumps"), "
If true, only the admin user can make zip dumps, else zip dumps
require no authentication.");

$properties["Enable Plugin RawHtml"] =
new boolean_define_optional('ENABLE_RAW_HTML', 
                    array('false' => "Disabled. Recommended on public sites.",
                          'true'  => "Enabled"), "
Allow < ?plugin RawHtml ...>. Don't do this on a publicly accessable wiki for now.");

$properties["Strict Mailable Pagedumps"] =
new boolean_define('STRICT_MAILABLE_PAGEDUMPS', 
                    array('false' => "binary",
                          'true'  => "quoted-printable"), "
If you define this to true, (MIME-type) page-dumps (either zip dumps,
or \"dumps to directory\" will be encoded using the quoted-printable
encoding.  If you're actually thinking of mailing the raw page dumps,
then this might be useful, since (among other things,) it ensures
that all lines in the message body are under 80 characters in length.

Also, setting this will cause a few additional mail headers
to be generated, so that the resulting dumps are valid
RFC 2822 e-mail messages.

Probably, you can just leave this set to false, in which case you get
raw ('binary' content-encoding) page dumps.");



$properties["HTML Dump Filename Suffix"] =
new _variable('HTML_DUMP_SUFFIX', ".html", "
Here you can change the filename suffix used for XHTML page dumps.
If you don't want any suffix just comment this out.");



$properties["Maximum Upload Size"] =
new numeric_define('MAX_UPLOAD_SIZE', "16 * 1024 * 1024", "
The maximum file upload size.");



$properties["Minor Edit Timeout"] =
new numeric_define('MINOR_EDIT_TIMEOUT', "7 * 24 * 3600", "
If the last edit is older than MINOR_EDIT_TIMEOUT seconds, the
default state for the \"minor edit\" checkbox on the edit page form
will be off.");



$properties["Disabled Actions"] =
new array_variable('DisabledActions', array(), "
Actions listed in this array will not be allowed. Actions are:
browse, diff, dumphtml, dumpserial, edit, loadfile, lock, remove, 
unlock, upload, viewsource, zip, ziphtml");

$properties["Access Log"] =
new _define_commented('ACCESS_LOG', "/var/logs/wiki_access.log", "
PhpWiki can generate an access_log (in \"NCSA combined log\" format)
for you. If you want one, define this to the name of the log file,
such as /tmp/wiki_access_log.");


$properties["Path for PHP Session Support"] =
new _ini_set('session.save_path', '/tmp', "
The login code now uses PHP's session support. Usually, the default
configuration of PHP is to store the session state information in
/tmp. That probably will work fine, but fails e.g. on clustered
servers where each server has their own distinct /tmp (this is the
case on SourceForge's project web server.) You can specify an
alternate directory in which to store state information like so
(whatever user your httpd runs as must have read/write permission
in this directory):");



$properties["Disable PHP Transparent Session ID"] =
new unchangeable_ini_set('session.use_trans_sid', "@ini_set('session.use_trans_sid', 0);", "
If your php was compiled with --enable-trans-sid it tries to
add a PHPSESSID query argument to all URL strings when cookie
support isn't detected in the client browser.  For reasons
which aren't entirely clear (PHP bug) this screws up the URLs
generated by PhpWiki.  Therefore, transparent session ids
should be disabled.  This next line does that.

(At the present time, you will not be able to log-in to PhpWiki,
or set any user preferences, unless your browser supports cookies.)");



///////// database selection


$properties["Part Two"] =
new part('_parttwo', $SEPARATOR."\n", "

Part Two:
Database Selection
");


$properties["Database Type"] =
new _variable_selection("DBParams|dbtype",
              array('dba'   => "dba DBM",
                    'SQL'   => "SQL PEAR",
                    'ADODB' => "SQL ADODB",
                    'cvs'   => "CVS File handler"), "
Select the database backend type:
Choose ADODB or SQL to use an SQL database with ADODB or PEAR.
Choose dba to use one of the standard UNIX dbm libraries.
CVS is not yet tested nor supported.
Recommended is SQL PEAR.");

$properties["Filename / Table name Prefix"] =
new _variable_commented("DBParams|prefix", "phpwiki_", "
Used by all DB types:

Prefix for filenames or table names

Currently you MUST EDIT THE SQL file too (in the schemas/
directory because we aren't doing on the fly sql generation
during the installation.");


$properties["SQL dsn Setup"] =
new unchangeable_variable('_sqldsnstuff', "", "
For SQL based backends, specify the database as a DSN
The most general form of a DSN looks like:
<pre>
  phptype(dbsyntax)://username:password@protocol+hostspec/database
</pre>
For a MySQL database, the following should work:
<pre>
   mysql://user:password@host/databasename
</pre>
<dl><dd>FIXME:</dd> <dt>My version Pear::DB seems to be broken enough that there
        is no way to connect to a mysql server over a socket right now.</dt></dl>
<pre>'dsn' => 'mysql://guest@:/var/lib/mysql/mysql.sock/test',
'dsn' => 'mysql://guest@localhost/test',
'dsn' => 'pgsql://localhost/test',</pre>");

// Choose ADODB or SQL to use an SQL database with ADODB or PEAR.
// Choose dba to use one of the standard UNIX dbm libraries.

$properties["SQL Type"] =
new _variable_selection('_dsn_sqltype',
              array('mysql' => "MySQL",
                    'pgsql' => "PostgreSQL"), "
SQL DB types");



$properties["SQL User"] =
new _variable('_dsn_sqluser', "wikiuser", "
SQL User Id:");



$properties["SQL Password"] =
new _variable('_dsn_sqlpass', "", "
SQL Password:");



$properties["SQL Database Host"] =
new _variable('_dsn_sqlhostorsock', "localhost", "
SQL Database Hostname:");



$properties["SQL Database Name"] =
new _variable('_dsn_sqldbname', "phpwiki", "
SQL Database Name:");

list($dsn_sqltype,) = $properties["SQL Type"]->value();
$dsn_sqluser = $properties["SQL User"]->value();
$dsn_sqlpass = $properties["SQL Password"]->value();
$dsn_sqlhostorsock = $properties["SQL Database Host"]->value();
$dsn_sqldbname = $properties["SQL Database Name"]->value();

$properties["SQL dsn"] =
new unchangeable_variable("DBParams['dsn']", 
  "\$DBParams['dsn'] = \"\$_dsn_sqltype://{$dsn_sqluser}:{$dsn_sqlpass}@{$dsn_sqlhostorsock}/{$dsn_sqldbname}\";", "");

$properties["dba directory"] =
new _variable("DBParams|directory", "/tmp", "
dba directory:");


$properties["dba handler"] =
new _variable_selection('DBParams|dba_handler',
              array('gdbm' => "Gdbm - GNU database manager",
                    'dbm'  => "DBM - Redhat default. On sf.net there's dbm and gdbm",
                    'db2'  => "DB2 - Sleepycat Software's DB2",
                    'db3'  => "DB3 - Sleepycat Software's DB3. Fine on Windows but not on every Linux"), "
Use 'gdbm', 'dbm', 'db2' or 'db3' depending on your DBA handler methods supported:");

$properties["dba timeout"] =
new _variable("DBParams|timeout", "20", "
Recommended values are 20 or 5.");



///////////////////



$properties["Page Revisions"] =
new unchangeable_variable('_parttworevisions', "", "

The next section controls how many old revisions of each page are
kept in the database.

There are two basic classes of revisions: major and minor. Which
class a revision belongs in is determined by whether the author
checked the \"this is a minor revision\" checkbox when they saved the
page.
 
There is, additionally, a third class of revisions: author
revisions. The most recent non-mergable revision from each distinct
author is and author revision.

The expiry parameters for each of those three classes of revisions
can be adjusted seperately. For each class there are five
parameters (usually, only two or three of the five are actually
set) which control how long those revisions are kept in the
database.
<dl>
   <dt>max_keep:</dt> <dd>If set, this specifies an absolute maximum for the
            number of archived revisions of that class. This is
            meant to be used as a safety cap when a non-zero
            min_age is specified. It should be set relatively high,
            and it's purpose is to prevent malicious or accidental
            database overflow due to someone causing an
            unreasonable number of edits in a short period of time.</dd>

  <dt>min_age:</dt>  <dd>Revisions younger than this (based upon the supplanted
            date) will be kept unless max_keep is exceeded. The age
            should be specified in days. It should be a
            non-negative, real number,</dd>

  <dt>min_keep:</dt> <dd>At least this many revisions will be kept.</dd>

  <dt>keep:</dt>     <dd>No more than this many revisions will be kept.</dd>

  <dt>max_age:</dt>  <dd>No revision older than this age will be kept.</dd>
</dl>
Supplanted date: Revisions are timestamped at the instant that they
cease being the current revision. Revision age is computed using
this timestamp, not the edit time of the page.

Merging: When a minor revision is deleted, if the preceding
revision is by the same author, the minor revision is merged with
the preceding revision before it is deleted. Essentially: this
replaces the content (and supplanted timestamp) of the previous
revision with the content after the merged minor edit, the rest of
the page metadata for the preceding version (summary, mtime, ...)
is not changed.
");


// For now the expiration parameters are statically inserted as
// an unchangeable property. You'll have to edit the resulting
// config file if you really want to change these from the default.

$properties["Expiration Parameters for Major Edits"] =
new unchangeable_variable('ExpireParams|major',
"\$ExpireParams['major'] = array('max_age' => 32,
                               'keep'    => 8);", "
Keep up to 8 major edits, but keep them no longer than a month.");

$properties["Expiration Parameters for Minor Edits"] =
new unchangeable_variable('ExpireParams|minor',
"\$ExpireParams['minor'] = array('max_age' => 7,
                               'keep'    => 4);", "
Keep up to 4 minor edits, but keep them no longer than a week.");



$properties["Expiration Parameters by Author"] =
new unchangeable_variable('ExpireParams|author',
"\$ExpireParams['author'] = array('max_age'  => 365,
                                'keep'     => 8,
                                'min_age'  => 7,
                                'max_keep' => 20);", "
Keep the latest contributions of the last 8 authors up to a year.
Additionally, (in the case of a particularly active page) try to
keep the latest contributions of all authors in the last week (even
if there are more than eight of them,) but in no case keep more
than twenty unique author revisions.");

/////////////////////////////////////////////////////////////////////

$properties["Part Three"] =
new part('_partthree', $SEPARATOR."\n", "

Part Three: (optional)
User Authentification
");

$properties["User Authentication"] =
new boolean_define_optional('ALLOW_USER_LOGIN',
                    array('true'  => "true. Check any defined passwords. (Default)",
                          'false' => "false. Don't check passwords. Legacy < 1.3.4"), "
If ALLOW_USER_LOGIN is true, any defined internal and external
authentication method is tried. 
If not, we don't care about passwords, but check the next two constants.");

$properties["HTTP Authentication"] =
new boolean_define_optional('ALLOW_HTTP_AUTH_LOGIN',
                    array('false' => "false. Ignore HTTP Authentication. (Default)",
			  'true'  => "true. Allow .htpasswd users login automatically."), "
The wiki can be optionally be protected by HTTP Auth. Use the username and password 
from there, and if this fails, try the other methods also.");

$properties["Strict Login"] =
new boolean_define_optional('ALLOW_BOGO_LOGIN',
                    array('true'  => "Users may Sign In with any WikiWord",
                          'false' => "Only admin may Sign In"), "
If ALLOW_BOGO_LOGIN is true, users are allowed to login (with
any/no password) using any userid which: 1) is not the ADMIN_USER,
2) is a valid WikiWord (matches \$WikiNameRegexp.)
If true, users may be created by themselves. Otherwise we need seperate auth. 
This might be renamed to ALLOW_SELF_REGISTRATION");

$properties["Require Sign In Before Editing"] =
new boolean_define_optional('REQUIRE_SIGNIN_BEFORE_EDIT',
                    array('false' => "Do not require Sign In",
                          'true'  => "Require Sign In"), "
If set, then if an anonymous user attempts to edit a page he will
be required to sign in.  (If ALLOW_BOGO_LOGIN is true, of course,
no password is required, but the user must still sign in under
some sort of BogoUserId.)");

if (function_exists('ldap_connect')) {
$properties["LDAP Authentication"] =
  new boolean_define_optional('ALLOW_LDAP_LOGIN',
                    array('true'  => "Allow LDAP Authentication",
	                  'false' => "Ignore LDAP"), "
LDAP Authentication
");
$properties["LDAP Host"] =
  new _define_optional('LDAP_AUTH_HOST', "localhost", "");
$properties["LDAP Root Search"] =
  new _define_optional('LDAP_AUTH_SEARCH', "ou=mycompany.com,o=My Company", 
  "Give the right LDAP root search information in the next statement.");

} else {

$properties["LDAP Authentication"] =
new unchangeable_define('ALLOW_LDAP_LOGIN', "
if (!defined('ALLOW_LDAP_LOGIN')) define('ALLOW_LDAP_LOGIN', true and function_exists('ldap_connect'));
if (!defined('LDAP_AUTH_HOST'))   define('LDAP_AUTH_HOST', 'localhost');
// Give the right LDAP root search information in the next statement. 
if (!defined('LDAP_AUTH_SEARCH')) define('LDAP_AUTH_SEARCH', 'ou=mycompany.com,o=My Company');
", "
Ignored. No LDAP support in this php. configure --with-ldap");
}

if (function_exists('imap_open')) {
$properties["IMAP Authentication"] =
  new boolean_define_optional('ALLOW_IMAP_LOGIN',
                    array('true'  => "Allow IMAP Authentication",
	                  'false' => "Ignore IMAP"), "
IMAP Authentication
");
$properties["IMAP Host"] =
  new _define_optional('IMAP_AUTH_HOST', 'localhost', '');
} else {
$properties["IMAP Authentication"] =
  new unchangeable_define('ALLOW_IMAP_LOGIN',"
// IMAP auth: check userid/passwords from a imap server, defaults to localhost
if (!defined('ALLOW_IMAP_LOGIN')) define('ALLOW_IMAP_LOGIN', true and function_exists('imap_open'));
if (!defined('IMAP_AUTH_HOST'))   define('IMAP_AUTH_HOST', 'localhost');
", "Ignored. No IMAP support in this php. configure --with-imap");
}


/////////////////////////////////////////////////////////////////////

$properties["Part Four"] =
new part('_partfour', $SEPARATOR."\n", "

Part Four:
Page appearance and layout
");



$properties["Theme"] =
new _define_selection_optional('THEME',
              array('default'  => "default",
                    'Hawaiian' => "Hawaiian",
                    'MacOSX'   => "MacOSX",
                    'Portland' => "Portland",
                    'Sidebar'  => "Sidebar",
                    'SpaceWiki' => "SpaceWiki"), "
THEME

Most of the page appearance is controlled by files in the theme
subdirectory.

There are a number of pre-defined themes shipped with PhpWiki.
Or you may create your own (e.g. by copying and then modifying one of
stock themes.)

Pick one.
<pre>
define('THEME', 'default');
define('THEME', 'Hawaiian');
define('THEME', 'MacOSX');
define('THEME', 'Portland');
define('THEME', 'Sidebar');
define('THEME', 'SpaceWiki');</pre>");



$properties["Character Set"] =
new _define_optional('CHARSET', 'iso-8859-1', "
Select a valid charset name to be inserted into the xml/html pages, 
and to reference links to the stylesheets (css). For more info see: 
http://www.iana.org/assignments/character-sets. Note that PhpWiki 
has been extensively tested only with the latin1 (iso-8859-1) 
character set.

If you change the default from iso-8859-1 PhpWiki may not work 
properly and it will require code modifications. However, character 
sets similar to iso-8859-1 may work with little or no modification 
depending on your setup. The database must also support the same 
charset, and of course the same is true for the web browser. (Some 
work is in progress hopefully to allow more flexibility in this 
area in the future).");



$properties["Language"] =
new _variable_selection_optional('LANG',
              array('en' => "English",
                    'nl' => "Nederlands",
                    'es' => "Espa�ol",
                    'fr' => "Fran�ais",
                    'de' => "Deutsch",
                    'sv' => "Svenska",
                    'it' => "Italiano",
                    ''   => "none"), "
Select your language/locale - default language is \"en\" for English.
Other languages available:<pre>
English \"en\"  (English    - HomePage)
Dutch   \"nl\" (Nederlands - ThuisPagina)
Spanish \"es\" (Espa�ol    - P�ginaPrincipal)
French  \"fr\" (Fran�ais   - Accueil)
German  \"de\" (Deutsch    - StartSeite)
Swedish \"sv\" (Svenska    - Framsida)
Italian \"it\" (Italiano   - PaginaPrincipale)
</pre>
If you set \$LANG to the empty string, your systems default language
(as determined by the applicable environment variables) will be
used.");

$properties["Language Locales"] =
new unchangeable_variable('language_locales',
"\$language_locales = array('en' => 'C',
                            'de' => 'de_DE',
                            'es' => 'es_MX',
                            'nl' => 'nl_NL',
                            'fr' => 'fr_FR',
                            'it' => 'it_IT',
                            'sv' => 'sv_SV'
                            );
if (empty(\$LC_ALL)) {
  if (empty(\$language_locales[\$LANG]))
     \$LC_ALL = \$LANG;
  else
     \$LC_ALL = \$language_locales[\$LANG];
}
putenv(\"LC_TIME=\$LC_ALL\");
", "
Setting the LANG environment variable (accomplished above) may or
may not be sufficient to cause PhpWiki to produce dates in your
native language. (It depends on the configuration of the operating
system on your http server.)  The problem is that, e.g. 'de' is
often not a valid locale.

A standard locale name is typically of  the  form
language[_territory][.codeset][@modifier],  where  language is
an ISO 639 language code, territory is an ISO 3166 country code,
and codeset  is  a  character  set or encoding identifier like
ISO-8859-1 or UTF-8.

You can tailor the locale used for time and date formatting by
setting the LC_TIME environment variable. You'll have to experiment
to find the correct setting.
gettext() fix: With setlocale() we must use the long form, 
like 'de_DE','nl_NL', 'es_MX', 'es_AR', 'fr_FR'. 
For Windows maybe even 'german'. You might fix this accordingly.");

$properties["Wiki Page Source"] =
new _define_optional('WIKI_PGSRC', 'pgsrc', "
WIKI_PGSRC -- specifies the source for the initial page contents of
the Wiki. The setting of WIKI_PGSRC only has effect when the wiki is
accessed for the first time (or after clearing the database.)
WIKI_PGSRC can either name a directory or a zip file. In either case
WIKI_PGSRC is scanned for files -- one file per page.
<pre>
// Default (old) behavior:
define('WIKI_PGSRC', 'pgsrc'); 
// New style:
define('WIKI_PGSRC', 'wiki.zip'); 
define('WIKI_PGSRC', 
       '../Logs/Hamwiki/hamwiki-20010830.zip'); 
</pre>");



$properties["Default Wiki Page Source"] =
new _define('DEFAULT_WIKI_PGSRC', 'pgsrc', "
DEFAULT_WIKI_PGSRC is only used when the language is *not* the
default (English) and when reading from a directory: in that case
some English pages are inserted into the wiki as well.
DEFAULT_WIKI_PGSRC defines where the English pages reside.

FIXME: is this really needed?
");



$properties["Generic Pages"] =
new array_variable('GenericPages', array('ReleaseNotes', 'SteveWainstead', 'TestPage'), "
These are the pages which will get loaded from DEFAULT_WIKI_PGSRC.	

FIXME: is this really needed?  Can't we just copy these pages into
the localized pgsrc?
");




$properties["Part Five"] =
new part('_partfive', $SEPARATOR."\n", "

Part Five:
Mark-up options.
");



$properties["Allowed Protocols"] =
new list_variable('AllowedProtocols', 'http|https|mailto|ftp|news|nntp|ssh|gopher', "
allowed protocols for links - be careful not to allow \"javascript:\"
URL of these types will be automatically linked.
within a named link [name|uri] one more protocol is defined: phpwiki");



$properties["Inline Images"] =
new list_variable('InlineImages', 'png|jpg|gif', "
URLs ending with the following extension should be inlined as images");



$properties["WikiName Regexp"] =
new _variable('WikiNameRegexp', "(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])", "
Perl regexp for WikiNames (\"bumpy words\")
(?&lt;!..) & (?!...) used instead of '\b' because \b matches '_' as well");

$properties["Subpage Separator"] =
new _define_optional('SUBPAGE_SEPARATOR', '/', "
One character which seperates pages from subpages. Defaults to '/', but '.' or ':' were also used.",
"onchange=\"validate_ereg('Sorry, \'%s\' must be a single character. Currently only :, / or .', '^[/:.]$', 'SUBPAGE_SEPARATOR', this);\""
);

$properties["InterWiki Map File"] =
new _define('INTERWIKI_MAP_FILE', 'lib/interwiki.map', "
InterWiki linking -- wiki-style links to other wikis on the web

The map will be taken from a page name InterWikiMap.
If that page is not found (or is not locked), or map
data can not be found in it, then the file specified
by INTERWIKI_MAP_FILE (if any) will be used.");

$properties["WARN_NONPUBLIC_INTERWIKIMAP"] =
new boolean_define('WARN_NONPUBLIC_INTERWIKIMAP',   
	array('true'  => "true",
                          'false' => "false"), "
Display a warning if the internal lib/interwiki.map is used, and 
not the public InterWikiMap page. This map is not readable from outside.");


$properties["Part Six"] =
new part('_partsix', $SEPARATOR."\n", "

Part Six (optional):
URL options -- you can probably skip this section.
");

$properties["Server Name"] =
new _define_commented_optional('SERVER_NAME', $HTTP_SERVER_VARS['SERVER_NAME'], "
Canonical name and httpd port of the server on which this PhpWiki
resides.");



$properties["Server Port"] =
new numeric_define_commented('SERVER_PORT', $HTTP_SERVER_VARS['SERVER_PORT'], "",
"onchange=\"validate_ereg('Sorry, \'%s\' is no valid port number.', '^[0-9]+$', 'SERVER_PORT', this);\"");

$scriptname = preg_replace('/configurator.php/','index.php',$HTTP_SERVER_VARS["SCRIPT_NAME"]);

$properties["Script Name"] =
new _define_commented_optional('SCRIPT_NAME', $scriptname, "
Relative URL (from the server root) of the PhpWiki script.");

$properties["Data Path"] =
new _define_commented_optional('DATA_PATH', dirname($scriptname), "
URL of the PhpWiki install directory.  (You only need to set this
if you've moved index.php out of the install directory.)  This can
be either a relative URL (from the directory where the top-level
PhpWiki script is) or an absolute one.");



$properties["PhpWiki Install Directory"] =
new _define_commented_optional('PHPWIKI_DIR', dirname(__FILE__), "
Path to the PhpWiki install directory.  This is the local
filesystem counterpart to DATA_PATH.  (If you have to set
DATA_PATH, your probably have to set this as well.)  This can be
either an absolute path, or a relative path interpreted from the
directory where the top-level PhpWiki script (normally index.php)
resides.");



$properties["Use PATH_INFO"] =
new boolean_define_commented_optional('USE_PATH_INFO', 
                    array('true'  => 'use PATH_INFO',
			  'false' => 'do not use PATH_INFO'), "
Define to 'true' to use PATH_INFO to pass the pagenames.
e.g. http://www.some.where/index.php/HomePage instead
of http://www.some.where/index.php?pagename=HomePage
FIXME: more docs (maybe in README). Default: true");



$properties["Virtual Path"] =
new _define_commented_optional('VIRTUAL_PATH', '/SomeWiki', "
VIRTUAL_PATH is the canonical URL path under which your your wiki
appears. Normally this is the same as dirname(SCRIPT_NAME), however
using, e.g. apaches mod_actions (or mod_rewrite), you can make it
something different.

If you do this, you should set VIRTUAL_PATH here.

E.g. your phpwiki might be installed at at /scripts/phpwiki/index.php,
but you've made it accessible through eg. /wiki/HomePage.

One way to do this is to create a directory named 'wiki' in your
server root. The directory contains only one file: an .htaccess
file which reads something like:
<pre>
    Action x-phpwiki-page /scripts/phpwiki/index.php
    SetHandler x-phpwiki-page
    DirectoryIndex /scripts/phpwiki/index.php
</pre>
In that case you should set VIRTUAL_PATH to '/wiki'.

(VIRTUAL_PATH is only used if USE_PATH_INFO is true.)
");



$end = "

$SEPARATOR
// Check if we were included by some other wiki version (getimg, en, ...) 
// or not. 
// If the server requested this index.php fire up the code by loading lib/main.php.
// Parallel wiki scripts can now simply include /index.php for the 
// main configuration, extend or redefine some settings and 
// load lib/main.php by themselves. 
// This overcomes the index as config problem.
$SEPARATOR

// This doesn't work with php as CGI yet!
if (defined('VIRTUAL_PATH') and defined('USE_PATH_INFO')) {
    if (\$HTTP_SERVER_VARS['SCRIPT_NAME'] == VIRTUAL_PATH) {
        include \"lib/main.php\";
    }
} else {
    if (defined('SCRIPT_NAME') and 
        (\$HTTP_SERVER_VARS['SCRIPT_NAME'] == SCRIPT_NAME)) {
        include \"lib/main.php\";
    } elseif (strstr(\$HTTP_SERVER_VARS['PHP_SELF'],'index.php')) {
        include \"lib/main.php\";
    }
}

// (c-file-style: \"gnu\")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
";



// end of configuration options
///////////////////////////////
// begin class definitions

/**
 * A basic index.php configuration line in the form of a variable.
 *
 * Produces a string in the form "$name = value;"
 * e.g.:
 * $WikiNameRegexp = "value";
 */
class _variable {

    var $config_item_name;
    var $default_value;
    var $description;
    var $prefix;
    var $jscheck;

    function _variable($config_item_name, $default_value, $description, $jscheck = '') {
        $this->config_item_name = $config_item_name;
        $this->description = $description;
        $this->default_value = $default_value;
	$this->jscheck = $jscheck;
        if (preg_match("/variable/i",get_class($this)))
	    $this->prefix = "\$";
	elseif (preg_match("/ini_set/i",get_class($this)))
            $this->prefix = "ini_get: ";
        else
	    $this->prefix = "";
    }

    function value() {
      global $HTTP_POST_VARS;
      if (isset($HTTP_POST_VARS[$this->config_item_name]))
          return $HTTP_POST_VARS[$this->config_item_name];
      else 
          return $this->default_value;
    }

    function _config_format($value) {
        $v = $this->get_config_item_name();
        // handle arrays: a|b --> a['b']
        if (strpos($v, '|')) {
            list($a, $b) = explode('|', $v);
            $v = sprintf("%s['%s']", $a, $b);
        }
        return sprintf("\$%s = \"%s\";", $v, $value);
    }

    function get_config_item_name() {
        return $this->config_item_name;
    }

    function get_config_item_header() {
       if (strchr($this->config_item_name,'|')) {
          list($var,$param) = explode('|',$this->config_item_name);
	  return "<b>" . $this->prefix . $var . "['" . $param . "']</b><br />";
       }
       elseif ($this->config_item_name[0] != '_')
	  return "<b>" . $this->prefix . $this->config_item_name . "</b><br />";
       else 
          return '';
    }

    function _get_description() {
        return $this->description;
    }

    function _get_config_line($posted_value) {
        return "\n" . $this->_config_format($posted_value);
    }

    function get_config($posted_value) {
        $d = stripHtml($this->_get_description());
        $d = str_replace("\n", "\n// ", $d) . $this->_get_config_line($posted_value) ."\n";
        return $d;
    }

    function get_instructions($title) {
        global $tdwidth;
        $i = "<p><b><h3>" . $title . "</h3></b></p>\n    " . nl2p($this->_get_description()) . "\n";
        return "<tr>\n<td width=\"$tdwidth\" class=\"instructions\">\n" . $i . "</td>\n";
    }

    function get_html() {
	return $this->get_config_item_header() . 
	    "<input type=\"text\" size=\"50\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\" " . 
	    $this->jscheck . " />" . "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
    }
}

class unchangeable_variable
extends _variable {
    function _config_format($value) {
        return "";
    }
    // function get_html() { return false; }
    function get_html() {
	return $this->get_config_item_header() . 
	"<em>Not editable.</em>" . 
	"<pre>" . $this->default_value."</pre>";
    }
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        return "${n}".$this->default_value;
    }
    function get_instructions($title) {
	global $tdwidth;
        $i = "<p><b><h3>" . $title . "</h3></b></p>\n    " . nl2p($this->_get_description()) . "\n";
        // $i = $i ."<em>Not editable.</em><br />\n<pre>" . $this->default_value."</pre>";
        return "<tr><td width=\"100%\" class=\"unchangeable_variable_top\" colspan=\"2\">\n".$i ."</td></tr>\n".
	"<tr style=\"border-top: none\"><td class=\"unchangeable_variable_left\" width=\"$tdwidth\" bgcolor=\"#eeeeee\">&nbsp;</td>";
    }
}

class unchangeable_define
extends unchangeable_variable {
    function _config_format($value) {
        return "";
    }
}
class unchangeable_ini_set
extends unchangeable_variable {
    function _config_format($value) {
        return "";
    }
}


class _variable_selection
extends _variable {
    function value() {
        global $HTTP_POST_VARS;
        if (!empty($HTTP_POST_VARS[$this->config_item_name]))
            return $HTTP_POST_VARS[$this->config_item_name];
        else {
	    list($option, $label) = current($this->default_value);
            return $this->$option;
        }
    }
    function get_html() {
	$output = $this->get_config_item_header();
        $output .= '<select name="' . $this->get_config_item_name() . "\">\n";
        /* The first option is the default */
        while(list($option, $label) = each($this->default_value)) {
            $output .= "  <option value=\"$option\">$label</option>\n";
        }
        $output .= "</select>\n  </td>\n";
        return $output;
    }
}


class _define
extends _variable {
    function _config_format($value) {
        return sprintf("define('%s', '%s');", $this->get_config_item_name(), $value);
    }
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "${n}//" . $this->_config_format("");
        else
            return "${n}" . $this->_config_format($posted_value);
    }
    function get_html() {
	return $this->get_config_item_header() . 
	    "<input type=\"text\" size=\"50\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\" {$this->jscheck} />" .
	    "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
    }
}

class _define_commented
extends _define {
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "${n}//" . $this->_config_format($posted_value);
        else if ($posted_value == '')
            return "${n}//" . $this->_config_format("");
        else
            return "${n}" . $this->_config_format($posted_value);
    }
}

class _define_commented_optional
extends _define_commented {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}

class _define_optional
extends _define {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}

class _define_optional_notempty
extends _define_optional {
    function get_html() {
	$s = $this->get_config_item_header() . 
	    "<input type=\"text\" size=\"50\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\" {$this->jscheck} />";
        if (empty($this->default_value))
	    return $s . "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: red\">Cannot be empty.</p>";
	else
	    return $s . "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
    }
}

class _variable_commented
extends _variable {
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "${n}//" . $this->_config_format($posted_value);
        else if ($posted_value == '')
            return "${n}//" . $this->_config_format("");
        else
            return "${n}" . $this->_config_format($posted_value);
    }
}

class numeric_define_commented
extends _define {
    //    var $jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' is not an integer.', '^[-+]?[0-9]+$', '" . $this->get_config_item_name() . "', this);\"";

    function get_html() {
	return numeric_define::get_html();
    }
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "${n}//" . $this->_config_format($posted_value);
        else if ($posted_value == '')
            return "${n}//" . $this->_config_format('0');
        else
            return "${n}" . $this->_config_format($posted_value);
    }
}

class _define_selection
extends _variable_selection {
    function _config_format($value) {
        return sprintf("define('%s', '%s');", $this->get_config_item_name(), $value);
    }
    function _get_config_line($posted_value) {
        return _define::_get_config_line($posted_value);
    }
    function get_html() {
        return _variable_selection::get_html();
    }
}

class _define_selection_optional
extends _define_selection {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}

class _variable_selection_optional
extends _variable_selection {
    function _config_format($value) {
        $v = $this->get_config_item_name();
        // handle arrays: a|b --> a['b']
        if (strpos($v, '|')) {
            list($a, $b) = explode('|', $v);
            $v = sprintf("%s['%s']", $a, $b);
        }
        return sprintf("if (!isset(\$%s)) { \$%s = \"%s\"; }", $v, $v, $value);
    }
}

class _define_password
extends _define {
    //    var $jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' cannot be empty.', '^.+$', '" . $this->get_config_item_name() . "', this);\"";

    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '') {
            $p = "${n}//" . $this->_config_format("");
            $p = $p . "\n// If you used the passencrypt.php utility to encode the password";
            $p = $p . "\n// then uncomment this line:";
            $p = $p . "\n//if (!defined('ENCRYPTED_PASSWD')) define('ENCRYPTED_PASSWD', true);";
            return $p;
        } else {
            if (function_exists('crypt')) {
                $salt_length = max(CRYPT_SALT_LENGTH,
                                    2 * CRYPT_STD_DES,
                                    9 * CRYPT_EXT_DES,
                                   12 * CRYPT_MD5,
                                   16 * CRYPT_BLOWFISH);
                // generate an encrypted password
                $crypt_pass = crypt($posted_value, rand_ascii($salt_length));
                $p = "${n}" . $this->_config_format($crypt_pass);
                return $p . "\ndefine('ENCRYPTED_PASSWD', true);";
            } else {
                $p = "${n}" . $this->_config_format($posted_value);
                $p = $p . "\n// If you used the passencrypt.php utility to encode the password";
                $p = $p . "\n// then uncomment this line:";
                $p = $p . "\n//define('ENCRYPTED_PASSWD', false);";
                $p = $p . "\n// Encrypted passwords cannot be used:";
                $p = $p . "\n// 'function crypt()' not available in this version of php";
                return $p;
            }
        }
    }
    function get_html() {
        return _variable_password::get_html();
    }
}

class _define_password_optional
extends _define_password {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}


class _variable_password
extends _variable {
    //    var $jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' cannot be empty.', '^.+$', '" . $this->get_config_item_name() . "', this);\"";

    function get_html() {
	global $HTTP_POST_VARS, $HTTP_GET_VARS;
	$s = $this->get_config_item_header();
        $s .= "<input type=\"password\" name=\"" . $this->get_config_item_name() . "\" value=\"" . $this->default_value . "\" {$this->jscheck} />" . 
"&nbsp;&nbsp;<input type=\"submit\" name=\"create\" value=\"Create Password\" />";
        if (isset($HTTP_POST_VARS['create']) or isset($HTTP_GET_VARS['create'])) {
	    $new_password = random_good_password();
	    $this->default_value = $new_password;
	    $s .= "<br />&nbsp;<br />Created password: <strong>$new_password</strong>";
	}
	if (empty($this->default_value))
	    $s .= "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: red\">Cannot be empty.</p>";
	elseif (strlen($this->default_value) < 4)
	    $s .= "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: red\">Must be longer than 4 chars.</p>";
	else
	    $s .= "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
	return $s;
    }
}

class numeric_define
extends _define {
    //    var $jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' is not an integer.', '^[-+]?[0-9]+$', '" . $this->get_config_item_name() . "', this);\"";

    function _config_format($value) {
        return sprintf("define('%s', %s);", $this->get_config_item_name(), $value);
    }
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "${n}//" . $this->_config_format('0');
        else
            return "${n}" . $this->_config_format($posted_value);
    }
}

class list_variable
extends _variable {
    function _get_config_line($posted_value) {
        // split the phrase by any number of commas or space characters,
        // which include " ", \r, \t, \n and \f
        $list_values = preg_split("/[\s,]+/", $posted_value, -1, PREG_SPLIT_NO_EMPTY);
        $list_values = join("|", $list_values);
        return _variable::_get_config_line($list_values);
    }
    function get_html() {
        $list_values = explode("|", $this->default_value);
        $rows = max(3, count($list_values) +1);
        $list_values = join("\n", $list_values);
        $ta = $this->get_config_item_header();
	$ta .= "<textarea cols=\"18\" rows=\"". $rows ."\" name=\"".$this->get_config_item_name()."\" {$this->jscheck}>";
        $ta .= $list_values . "</textarea>";
	$ta .= "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
        return $ta;
    }
}

class array_variable
extends _variable {
    function _config_format($value) {
        return sprintf("\$%s = array(%s);", $this->get_config_item_name(), $value);
    }
    function _get_config_line($posted_value) {
        // split the phrase by any number of commas or space characters,
        // which include " ", \r, \t, \n and \f
        $list_values = preg_split("/[\s,]+/", $posted_value, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($list_values)) {
            $list_values = "'".join("', '", $list_values)."'";
            return "\n" . $this->_config_format($list_values);
        } else
            return "\n//" . $this->_config_format('');
    }
    function get_html() {
        $list_values = join("\n", $this->default_value);
        $rows = max(3, count($this->default_value) +1);
        $ta = $this->get_config_item_header();
        $ta .= "<textarea cols=\"18\" rows=\"". $rows ."\" name=\"".$this->get_config_item_name()."\" {$this->jscheck}>";
        $ta .= $list_values . "</textarea>";
	$ta .= "<p id=\"" . $this->get_config_item_name() . "\" style=\"color: green\">Input accepted.</p>";
        return $ta;
    }

}

class _ini_set
extends _variable {
    function value() {
        global $HTTP_POST_VARS;
        if ($v = $HTTP_POST_VARS[$this->config_item_name])
            return $v;
        else {
	    return ini_get($this->get_config_item_name);
        }
    }
    function _config_format($value) {
        return sprintf("ini_set('%s', '%s');", $this->get_config_item_name(), $value);
    }
    function _get_config_line($posted_value) {
        if ($posted_value && ! $posted_value == $this->default_value)
            return "\n" . $this->_config_format($posted_value);
        else
            return "\n//" . $this->_config_format($this->default_value);
    }
}

class boolean_define
extends _define {
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        return "${n}" . $this->_config_format($posted_value);
    }
    function get_html() {
        $output = $this->get_config_item_header();
        $output .= '<select name="' . $this->get_config_item_name() . "\" {$this->jscheck}>\n";
        /* The first option is the default */
        list($option, $label) = each($this->default_value);
        $output .= "  <option value=\"$option\" selected>$label</option>\n";
        /* There can only be two options */
        list($option, $label) = each($this->default_value);
        $output .= "  <option value=\"$option\">$label</option>\n";
        $output .= "</select>\n  </td>\n";
        return $output;
    }
}

class boolean_define_optional
extends boolean_define {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}

class boolean_define_commented
extends boolean_define {
    function _get_config_line($posted_value) {
        if ($this->description)
            $n = "\n";
        list($default_value, $label) = each($this->default_value);
        if ($posted_value == $default_value)
            return "${n}//" . $this->_config_format($posted_value);
        else if ($posted_value == '')
            return "${n}//" . $this->_config_format('false');
        else
            return "${n}" . $this->_config_format($posted_value);
    }
}

class boolean_define_commented_optional
extends boolean_define_commented {
    function _config_format($value) {
	$name = $this->get_config_item_name();
        return sprintf("if (!defined('%s')) define('%s', '%s');", $name, $name, $value);
    }
}

class part
extends _variable {
    function value () { return; }
    function get_config($posted_value) {
        $d = stripHtml($this->_get_description());
        global $SEPARATOR;
        return "\n".$SEPARATOR . str_replace("\n", "\n// ", $d) ."\n$this->default_value";
    }
    function get_instructions($title) {
	$group_name = preg_replace("/\W/","",$title);
	$i = "<tr>\n<td class=\"part\" width=\"100%\" colspan=\"2\" bgcolor=\"#eeaaaa\">\n";
	if ($group_name == 'PartZero') $i = "</dl>" . $i;
        $i .= "<p><b><h2>" . $title . "</h2></b></p>\n    " . nl2p($this->_get_description()) ."\n";
	$i .= "<input id=\"{$group_name}_input\" type=\"hidden\" name=\"$group_name\" value=\"0\" />";
	$i .= "<p><a href=\"javascript:toggle_group('$group_name')\" id=\"{$group_name}_text\">Show options.</a></p>";
        return  $i ."</td></tr>\n" . "<dl id=\"$group_name\" style=\"display: none\">";
    }
    function get_html() {
        return "";
    }
}

// html utility functions
function nl2p($text) {
    return "<p>" . str_replace("\n\n", "</p>\n<p>", $text) . "</p>";
}

function stripHtml($text) {
        $d = str_replace("<pre>", "", $text);
        $d = str_replace("</pre>", "", $d);
        $d = str_replace("<dl>", "", $d);
        $d = str_replace("</dl>", "", $d);
        $d = str_replace("<dt>", "", $d);
        $d = str_replace("</dt>", "", $d);
        $d = str_replace("<dd>", "", $d);
        $d = str_replace("</dd>", "", $d);
        //restore html entities into characters
        // http://www.php.net/manual/en/function.htmlentities.php
        $trans = get_html_translation_table (HTML_ENTITIES);
        $trans = array_flip ($trans);
        $d = strtr($d, $trans);
        return $d;
}

/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
	if ($seed === '') {
	    list($usec,$sec)=explode(" ",microtime());
	    if ($usec > 0.1) 
		$seed = (double) $usec * $sec;
	    else // once in a while use the combined LCG entropy
		$seed = (double) 1000000 * substr(uniqid("",true),13);
	}
	if (function_exists('mt_srand')) {
	    mt_srand($seed); // mersenne twister
	} else {
	    srand($seed);    
	}
        $wascalled = TRUE;
    }
}

function rand_ascii($length = 1) {
    better_srand();
    $s = "";
    for ($i = 1; $i <= $length; $i++) {
	// return only typeable 7 bit ascii, avoid quotes
	if (function_exists('mt_rand'))
	    // the usually bad glibc srand()
	    $s .= chr(mt_rand(40, 126)); 
	else
	    $s .= chr(rand(40, 126));
    }
    return $s;
}

////
// Function to create better user passwords (much larger keyspace),
// suitable for user passwords.
// Sequence of random ASCII numbers, letters and some special chars.
// Note: There exist other algorithms for easy-to-remember passwords.
function random_good_password ($minlength = 5, $maxlength = 8) {
  $newpass = '';
  // assume ASCII ordering (not valid on EBCDIC systems!)
  $valid_chars = "!#%&+-.0123456789=@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz";
  $start = ord($valid_chars);
  $end   = ord(substr($valid_chars,-1));
  better_srand();
  if (function_exists('mt_rand')) // mersenne twister
      $length = mt_rand($minlength, $maxlength);
  else	// the usually bad glibc rand()
      $length = rand($minlength, $maxlength);
  while ($length > 0) {
      if (function_exists('mt_rand'))
	  $newchar = mt_rand($start, $end);
      else
	  $newchar = rand($start, $end);
      if (! strrpos($valid_chars,$newchar) ) continue; // skip holes
      $newpass .= sprintf("%c",$newchar);
      $length--;
  }
  return($newpass);
}

// debugging
function printArray($a) {
    echo "<hr />\n<pre>\n";
    print_r($a);
    echo "\n</pre>\n<hr />\n";
}

// end of class definitions
/////////////////////////////
// begin auto generation code

if (@$HTTP_POST_VARS['action'] == 'make_config') {

    $timestamp = date ('dS of F, Y H:i:s');

    $config = "<?php
/* This is a local configuration file for PhpWiki.
 * It was automatically generated by the configurator script
 * on the $timestamp.
 */

/*$copyright*/

/////////////////////////////////////////////////////////////////////
/*$preamble*/
";

    $posted = $GLOBALS['HTTP_POST_VARS'];

    if (defined('DEBUG'))
        printArray($GLOBALS['HTTP_POST_VARS']);

    foreach($properties as $option_name => $a) {
        $posted_value = $posted[$a->config_item_name];
        $config .= $properties[$option_name]->get_config($posted_value);
    }

    if (defined('DEBUG')) {
        $diemsg = "The configurator.php is provided for testing purposes only.
You can't use this file with your PhpWiki server yet!!";
        $config .= "\ndie(\"$diemsg\");\n";
    }
    $config .= $end;

    /* We first check if the config-file exists. */
    if (file_exists($fs_config_file)) {
        /* We make a backup copy of the file */
	// $config_file = 'index-user.php';
        $new_filename = preg_replace('/\.php$/', time() . '.php', $fs_config_file);
        if (@copy($fs_config_file, $new_filename)) {
            $fp = @fopen($fs_config_file, 'w');
        }
    } else {
        $fp = @fopen($fs_config_file, 'w');
    }

    if ($fp) {
        fputs($fp, $config);
        fclose($fp);
        echo "<p>The configuration was written to <code><b>$config_file</b></code>.</p>\n";
        if ($new_filename) {
            echo "<p>A backup was made to <code><b>$new_filename</b></code>.</p>\n";
        }
        echo "<p><strong>You must rename or copy this</strong> <code><b>$config_file</b></code> <strong>file to</strong> <code><b>index.php</b></code>.</p>\n";
    } else {
        echo "<p>A configuration file could <b>not</b> be written. You should copy the above configuration to a file, and manually save it as <code><b>index.php</b></code>.</p>\n";
    }

    echo "<hr />\n<p>Here's the configuration file based on your answers:</p>\n<pre>\n";
    echo htmlentities($config);
    echo "</pre>\n<hr />\n";

    echo "<p>To make any corrections, <a href=\"configurator.php\">edit the settings again</a>.</p>\n";

} else { // first time or create password
    $posted = $GLOBALS['HTTP_POST_VARS'];
    /* No action has been specified - we make a form. */

    echo '
<form action="configurator.php" method="POST">
<table cellpadding="4" cellspacing="0">
  <input type="hidden" name="action" value="make_config">
';

    while(list($property, $obj) = each($properties)) {
        echo $obj->get_instructions($property);
        if ($h = $obj->get_html()) {
            if (defined('DEBUG'))  $h = get_class($obj) . "<br />\n" . $h;
            echo "<td>".$h."</td>\n";
        }
	echo '</tr>';
    }

    echo '
</dl></table>
<p><input type="submit" value="Save ',$config_file,'"> <input type="reset" value="Clear"></p>
</form>
';
}
?>
</body>
</html>