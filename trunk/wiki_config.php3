<!-- $Id: wiki_config.php3,v 1.12 2000-06-25 20:00:22 wainstead Exp $ -->
<?
   /*
      Constants and settings. Edit the values below for
      your site. You need two image files, a banner and 
      a signature. The dbm file MUST be writable by the
      web server or this won't work. If you configure your
      server to allow index.php3 as an index file, you 
      can just give the URL without the script name.
   */

   // You should set the $ServerAddress as below, and comment out
   // the if/else below.
   $ServerAddress = "http://127.0.0.1:8080/phpwiki/";

/*
   if (preg_match("#(.*?)([^/]*$)#", $REQUEST_URI, $matches)) {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT" . $matches[1];
   } else {
      $ServerAddress = "http://$SERVER_NAME:$SERVER_PORT$REQUEST_URI";
   }
*/
   // if you are using MySQL instead of a DBM to store your
   // Wiki pages, use wiki_mysql.php3 instead of wiki_dbmlib.php3
   // See INSTALL.mysql for details on using MySQL

   // if you are using Postgressl instead of a DBM to store your
   // Wiki pages, use wiki_pgsql.php3 instead of wiki_dbmlib.php3
   // See INSTALL.pgsql for details on using Postgresql

   // DBM settings (default)
   include "wiki_dbmlib.php3";
   $WikiDataBase = "/tmp/wikidb"; // must be server-writable!
   $ArchiveDataBase = "/tmp/wikiarchive"; // see above!

   // MySQL settings (thanks Arno Hollosi! <ahollosi@iname.com>)
   // Comment out the lines above (for the DBM) if you use these

/*   include "wiki_mysql.php3";
   $WikiDataBase = "wiki";
   $ArchiveDataBase = "archive";
   $mysql_server = 'localhost';
   $mysql_user = 'root';
   $mysql_pwd = '';
   $mysql_db = 'wiki';
*/

/*
   // PostgreSQL settings. 
   include "wiki_pgsql.php3";
   $WikiDataBase = "wiki";
   $ArchiveDataBase = "archive";
   $pg_dbhost    = "localhost";
   $pg_dbport    = "5432";
*/

/*
   // MiniSQL (mSQL) settings.
   include "wiki_msql.php3";
   $msql_db = "wiki";

   $WikiDataBase = array();
   $ArchiveDataBase = array();

   $WikiDataBase['table']         = "wiki";
   $WikiDataBase['page_table']    = "wikipages";
   $ArchiveDataBase['table']      = "archive";
   $ArchiveDataBase['page_table'] = "archivepages";
*/

   $ScriptName = "index.php3";


   // Template files (filenames are relative to script position)
   $templates = array(
   	"BROWSE" => "templates/browse.html",
	"EDITPAGE" => "templates/editpage.html",
	"EDITLINKS" => "templates/editlinks.html",
	"MESSAGE" => "templates/message.html"
	);

   $SignatureImg = "$ServerAddress/signature.png";
   $logo = "wikibase.png";

   // date & time formats used to display modification times, etc.
   // formats are given as format strings to PHP date() function
   $datetimeformat = "F j, Y";	// may contain time of day
   $dateformat = "F j, Y";	// must not contain time

   // allowed protocols for links - be careful not to allow "javscript:"
   $AllowedProtocols = "http|https|mailto|ftp|news|gopher";
   
   // you shouldn't have to edit anyting below this line

   $ScriptUrl = $ServerAddress . $ScriptName;
   $LogoImage = "<img src='${ServerAddress}$logo' border='0'>";
   $LogoImage = "<a href='$ScriptUrl'>$LogoImage</a>";

   $FieldSeparator = "\263";

   // Apache won't show REMOTE_HOST unless the admin configured it
   // properly. We'll be nice and see if it's there.
   empty($REMOTE_HOST) ?
      ($remoteuser = $REMOTE_ADDR) : ($remoteuser = $REMOTE_HOST);


   // number of user-defined external links, i.e. "[1]"
   define("NUM_LINKS", 12);

   // try this many times if the dbm is unavailable
   define("MAX_DBM_ATTEMPTS", 20);

   // constants used for HTML output. List tags like UL and 
   // OL have a depth of one, PRE has a depth of 0.
   define("ZERO_DEPTH", 0);
   define("SINGLE_DEPTH", 1);

?>
