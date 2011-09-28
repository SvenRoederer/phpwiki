<?php
rcs_id('$Id: main.php,v 1.13 2001-04-09 19:31:49 dairiki Exp $');
include "lib/config.php";
include "lib/stdlib.php";
include "lib/userauth.php";
include "lib/logger.php";

if (ACCESS_LOG)
{
   $LogEntry = new AccessLogEntry;

   function _write_log () { $GLOBALS['LogEntry']->write(ACCESS_LOG); }
   register_shutdown_function('_write_log');
}

if (USE_PATH_INFO && !isset($PATH_INFO)
    && (!isset($REDIRECT_URL) || !preg_match(',/$,', $REDIRECT_URL)))
{
   $LogEntry->status = 302;	// "302 Found"
   header("Location: " . SERVER_URL . preg_replace('/(\?|$)/', '/\1', $REQUEST_URI, 1));
   exit;
}

function DeducePagename () 
{
   global $pagename, $PATH_INFO, $QUERY_STRING;
   
   if (isset($pagename))
      return fix_magic_quotes_gpc($pagename);

   if (USE_PATH_INFO && isset($PATH_INFO))
   {
      fix_magic_quotes_gpc($PATH_INFO);
      if (ereg('^' . PATH_INFO_PREFIX . '(..*)$', $PATH_INFO, $m))
	 return $m[1];
   }

   if (isset($QUERY_STRING) && preg_match('/^[^&=]+$/', $QUERY_STRING))
      return urldecode(fix_magic_quotes_gpc($QUERY_STRING));

   return gettext("HomePage");
}

$pagename = DeducePagename();

if (!empty($action))
{
   $action = trim(fix_magic_quotes_gpc($action));
}
else if (isset($diff))
{
   // Fix for compatibility with very old diff links in RecentChanges.
   // (The [phpwiki:?diff=PageName] style links are fixed elsewhere.)
   $action = 'diff';
   $pagename = fix_magic_quotes_gpc($diff);
   unset($diff);
}
else
{
   $action = 'browse';
}

function IsSafeAction ($action)
{
   if (! ZIPDUMP_AUTH and $action == 'zip')
      return true;
   return in_array ( $action, array('browse',
				    'info', 'diff', 'search',
				    'edit', 'save',
				    'login', 'logout',
				    'setprefs') );
}

function get_auth_mode ($action) 
{
   switch ($action) {
      case 'logout':
	 return  'LOGOUT';
      case 'login':
	 return 'LOGIN';
      default:
	 if (IsSafeAction($action))
	    return 'ANON_OK';
	 else
	    return 'REQUIRE_AUTH';
   }
}

   
$user = new WikiUser(get_auth_mode($action));
if ($user->is_authenticated())
   $LogEntry->user = $user->id();



// All requests require the database
$dbi = OpenDataBase($WikiPageStore);

if ( $action == 'browse' && $pagename == gettext("HomePage") ) {
   // if there is no HomePage, create a basic set of Wiki pages
   if ( ! IsWikiPage($dbi, gettext("HomePage")) ) {
      include_once("lib/loadsave.php");
      SetupWiki($dbi);
      ExitWiki();
   }
}

// FIXME: I think this is redundant.
if (!IsSafeAction($action))
   $user->must_be_admin($action);
if (isset($DisabledActions) && in_array($action, $DisabledActions))
   ExitWiki(gettext("Action $action is disabled in this wiki."));
   
// Enable the output of most of the warning messages.
// The warnings will screw up zip files and setpref though.
if ($action != 'zip' && $action != 'setprefs')
   PostponeErrorMessages(E_NOTICE);

switch ($action) {
   case 'edit':
      include "lib/editpage.php";
      break;

   case 'search':
      if (isset($searchtype) && ($searchtype == 'full')) {
	 include "lib/fullsearch.php";
      }
      else {
	 include "lib/search.php";
      }
      break;
      
   case 'save':
      include "lib/savepage.php";
      break;
   case 'info':
      include "lib/pageinfo.php";
      break;
   case 'diff':
      include "lib/diff.php";
      break;
      
   case 'zip':
      include_once("lib/loadsave.php");
      MakeWikiZip($dbi, isset($include) && $include == 'all');
      // I don't think it hurts to add cruft at the end of the zip file.
      echo "\n========================================================\n";
      echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$RCS_IDS\n";
      break;

   case 'upload':
      include_once("lib/loadsave.php");
      LoadPostFile($dbi, 'file');
      break;
   
   case 'dumpserial':
      if (empty($directory))
	 ExitWiki(gettext("You must specify a directory to dump to"));

      include_once("lib/loadsave.php");
      DumpToDir($dbi, fix_magic_quotes_gpc($directory));
      break;

   case 'loadfile':
      if (empty($source))
	 ExitWiki(gettext("You must specify a source to read from"));

      include_once("lib/loadsave.php");
      LoadFileOrDir($dbi, fix_magic_quotes_gpc($source));
      break;

   case 'remove':
      include 'admin/removepage.php';
      break;
    
   case 'lock':
   case 'unlock':
      include "admin/lockpage.php";
      include "lib/display.php";
      break;

   case 'setprefs':
      $prefs = $user->getPreferences($GLOBALS);
      if (!empty($edit_area_width))
	 $prefs['edit_area.width'] = $edit_area_width;
      if (!empty($edit_area_height))
	 $prefs['edit_area.height'] = $edit_area_height;
      $user->setPreferences($prefs);

      PostponeErrorMessages(E_ALL & ~E_NOTICE);

      include "lib/display.php";
      break;
   
   case 'browse':
   case 'login':
   case 'logout':
      include "lib/display.php";
      break;

   default:
      echo QElement('p', sprintf("Bad action: '%s'", urlencode($action)));
      break;
}

ExitWiki();

// For emacs users
// Local Variables:
// mode: php
// c-file-style: "ellemtel"
// End:   
?>