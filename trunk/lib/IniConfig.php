<?php
rcs_id('$Id: IniConfig.php,v 1.55 2004-10-14 19:23:58 rurban Exp $');

/**
 * A configurator intended to read it's config from a PHP-style INI file,
 * instead of a PHP file.
 *
 * Pass a filename to the IniConfig() function and it will read all it's
 * definitions from there, all by itself, and proceed to do a mass-define
 * of all valid PHPWiki config items.  In this way, we can hopefully be
 * totally backwards-compatible with the old index.php method, while still
 * providing a much tastier on-going experience.
 *
 * @author: Joby Walker, Reini Urban, Matthew Palmer
 */
/*
 * Copyright 2004 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * DONE:
 * - Convert the value lists to provide defaults, so that every "if
 *      (defined())" and "if (!defined())" can fuck off to the dismal hole
 *      it belongs in.
 * TODO:
 * - Old-style index.php => config/config.ini converter.
 *
 * - config.ini => config.php dumper for faster startup.
 *
 * - Don't use too much globals for easier integration into other projects
 *   (namespace pollution). (gforge, phpnuke, postnuke, phpBB2, carolina, ...)
 *   Use one global $phpwiki object instead which holds the cfg vars, constants 
 *   and all other globals.
 *     (global $FieldSeparator, $charset, $WikiNameRegexp, $KeywordLinkRegexp;
 *      global $DisabledActions, $DBParams, $LANG, $AllActionPages)
 *
 * - Resurrect the larger "config object" code (in config/) so it'll aid the
 *   GUI config writers, and allow us to do proper validation and default
 *   value handling.
 *
 * - Get rid of WikiNameRegexp and KeywordLinkRegexp as globals by finding
 *   everywhere that uses them as variables and modify the code to use
 *   them as constants. Will involve hacking around
 *   pcre_fix_posix_classes (probably with redefines()).
 */

include_once (dirname(__FILE__)."/config.php");
include_once (dirname(__FILE__)."/FileFinder.php");

function IniConfig($file) {
    
    // List of all valid config options to be define()d which take "values" (not
    // booleans). Needs to be categorised, and generally made a lot tidier. 
    $_IC_VALID_VALUE = array
        ('WIKI_NAME', 'ADMIN_USER', 'ADMIN_PASSWD',
         'DEFAULT_DUMP_DIR', 'HTML_DUMP_DIR',
         'HTML_DUMP_SUFFIX', 'MAX_UPLOAD_SIZE', 'MINOR_EDIT_TIMEOUT',
         'ACCESS_LOG', 'CACHE_CONTROL', 'CACHE_CONTROL_MAX_AGE',
         'PASSWORD_LENGTH_MINIMUM', 'USER_AUTH_POLICY', 
         'GROUP_METHOD',
         'EDITING_POLICY', 'THEME', 'CHARSET',
         'DEFAULT_LANGUAGE', 'WIKI_PGSRC', 'DEFAULT_WIKI_PGSRC',
         'ALLOWED_PROTOCOLS', 'INLINE_IMAGES', 'SUBPAGE_SEPARATOR',
         'DATABASE_PREFIX', 'DATABASE_DSN', 'DATABASE_TYPE',
         'INTERWIKI_MAP_FILE', 'COPYRIGHTPAGE_TITLE', 'COPYRIGHTPAGE_URL',
         'AUTHORPAGE_TITLE', 'AUTHORPAGE_URL', 'SERVER_NAME', 'SERVER_PORT',
         'SCRIPT_NAME', 'DATA_PATH', 'PHPWIKI_DIR', 'VIRTUAL_PATH',
         'WIKI_NAME_REGEXP',
         //'PLUGIN_CACHED_CACHE_DIR',
         'PLUGIN_CACHED_DATABASE', 'PLUGIN_CACHED_FILENAME_PREFIX',
         'PLUGIN_CACHED_HIGHWATER', 'PLUGIN_CACHED_LOWWATER', 'PLUGIN_CACHED_MAXLIFETIME',
         'PLUGIN_CACHED_MAXARGLEN', 'PLUGIN_CACHED_IMGTYPES'
         );

    // Optional values which need to be defined.
    // These are not defined in config-default.ini and empty if not defined.
    $_IC_OPTIONAL_VALUE = array
        ( 
         'DEBUG', 'TEMP_DIR',
         'LDAP_AUTH_HOST','LDAP_SET_OPTION','LDAP_BASE_DN', 'LDAP_AUTH_USER',
         'LDAP_AUTH_PASSWORD','LDAP_SEARCH_FIELD','LDAP_OU_GROUP','LDAP_OU_USERS',
         'AUTH_USER_FILE','DBAUTH_AUTH_DSN',
         'IMAP_AUTH_HOST', 'POP3_AUTH_HOST',
         'AUTH_USER_FILE', 'AUTH_GROUP_FILE', 'AUTH_SESS_USER', 'AUTH_SESS_LEVEL',
         'GOOGLE_LICENSE_KEY','FORTUNE_DIR',
         'DISABLE_GETIMAGESIZE','DBADMIN_USER','DBADMIN_PASSWD'
         );

    // List of all valid config options to be define()d which take booleans.
    $_IC_VALID_BOOL = array
        ('ENABLE_USER_NEW', 'ENABLE_PAGEPERM', 'ENABLE_EDIT_TOOLBAR', 'JS_SEARCHREPLACE',
         'ENABLE_REVERSE_DNS', 'ENCRYPTED_PASSWD', 'ZIPDUMP_AUTH', 
         'ENABLE_RAW_HTML', 'ENABLE_RAW_HTML_LOCKEDONLY', 'ENABLE_RAW_HTML_SAFE', 
         'STRICT_MAILABLE_PAGEDUMPS', 'COMPRESS_OUTPUT',
         'WIKIDB_NOCACHE_MARKUP', 'ALLOW_ANON_USER', 'ALLOW_ANON_EDIT',
         'ALLOW_BOGO_LOGIN', 'ALLOW_USER_PASSWORDS',
         'AUTH_USER_FILE_STORABLE', 'ALLOW_HTTP_AUTH_LOGIN',
         'ALLOW_USER_LOGIN', 'ALLOW_LDAP_LOGIN', 'ALLOW_IMAP_LOGIN',
         'WARN_NONPUBLIC_INTERWIKIMAP', 'USE_PATH_INFO',
         'DISABLE_HTTP_REDIRECT',
         'PLUGIN_CACHED_USECACHE', 'PLUGIN_CACHED_FORCE_SYNCMAP',
         'ENABLE_XHTML_XML'
         );

    if(!file_exists($file)){
        trigger_error("Datasource file '$file' does not exist", E_USER_ERROR);
        exit();
    }
         
    $rs = @parse_ini_file($file);
    $rsdef = @parse_ini_file(dirname(__FILE__)."/../config/config-default.ini");
    foreach ($rsdef as $k => $v) {
    	if (defined($k)) {
    	    $rs[$k] = constant($k);
    	} elseif (!isset($rs[$k])) {
    	    $rs[$k] = $v;
    	}
    }

    foreach ($_IC_VALID_VALUE as $item) {
        if (defined($item)) {
            unset($rs[$item]);
            continue;
        }
        if (array_key_exists($item, $rs)) {
            define($item, $rs[$item]);
            unset($rs[$item]);
        //} elseif (array_key_exists($item, $rsdef)) {
        //    define($item, $rsdef[$item]);
        // calculate them later or not at all:
        } elseif (in_array($item,
                           array('DATABASE_PREFIX', 'SERVER_NAME', 'SERVER_PORT',
                                 'SCRIPT_NAME', 'DATA_PATH', 'PHPWIKI_DIR', 'VIRTUAL_PATH',
                                 'LDAP_AUTH_HOST','IMAP_AUTH_HOST','POP3_AUTH_HOST',
                                 'PLUGIN_CACHED_CACHE_DIR'))) 
        {
            ;
        } else {
            trigger_error(sprintf("missing config setting for %s",$item));
        }
    }

    // Boolean options are slightly special - if they're set to any of
    // '', 'false', '0', or 'no' (all case-insensitive) then the value will
    // be a boolean false, otherwise if there is anything set it'll
    // be true.
    foreach ($_IC_VALID_BOOL as $item) {
        if (defined($item)) {
            unset($rs[$item]);
            continue;
        }
        if (array_key_exists($item, $rs)) {
            $val = $rs[$item];
        //} elseif (array_key_exists($item, $rsdef)) {
        //    $val = $rsdef[$item];
        } else {
            $val = false; //trigger_error(sprintf("missing boolean config setting for %s",$item));
        }
        
        // calculate them later: old or dynamic constants
        if (!array_key_exists($item, $rs) and
            in_array($item,array('USE_PATH_INFO', 'USE_DB_SESSION',
                                 'ALLOW_HTTP_AUTH_LOGIN', 'ALLOW_LDAP_LOGIN',
                                 'ALLOW_IMAP_LOGIN', 'ALLOW_USER_LOGIN',
                                 'REQUIRE_SIGNIN_BEFORE_EDIT',
                                 'WIKIDB_NOCACHE_MARKUP')))
        {
            ;
        }
        elseif (!$val) {
            define($item, false);
        }
        elseif (strtolower($val) == 'false' ||
                strtolower($val) == 'no' ||
                $val == '' ||
                $val == false ||
                $val == '0') {
            define($item, false);
        }
        else {
            define($item, true);
        }
        unset($rs[$item]);
    }

    // Special handling for some config options
    if (!empty($rs['INCLUDE_PATH'])) {
        ini_set('include_path', $rs['INCLUDE_PATH']);
    }
    if (!empty($rs['SESSION_SAVE_PATH'])) {
        ini_set('session.save_path', $rs['SESSION_SAVE_PATH']);
    }

    // Database
    global $DBParams;
    $DBParams['dbtype'] = DATABASE_TYPE;
    $DBParams['dsn'] = DATABASE_DSN;
    if (defined('DATABASE_PREFIX'))
        $DBParams['prefix'] = DATABASE_PREFIX;
    elseif (isset($rs['DATABASE_PREFIX']))
        $DBParams['prefix'] = $rs['DATABASE_PREFIX'];
    $DBParams['db_session_table'] = @$rs['DATABASE_SESSION_TABLE'];
    $DBParams['dba_handler'] = @$rs['DATABASE_DBA_HANDLER'];
    $DBParams['directory'] = @$rs['DATABASE_DIRECTORY'];
    $DBParams['timeout'] = @$rs['DATABASE_TIMEOUT'];
    foreach (array('DATABASE_TYPE','DATABASE_DSN','DATABASE_SESSION_TABLE','DATABASE_DBA_HANDLER',
    	           'DATABASE_DIRECTORY','DATABASE_TIMEOUT','DATABASE_PREFIX') as $item) {
    	unset($rs[$item]);
    }
    // USE_DB_SESSION default logic:
    if (!defined('USE_DB_SESSION')) {
        if ($DBParams['db_session_table'] 
            and in_array($DBParams['dbtype'], array('SQL','ADODB'/*,'dba'*/))) {
            define('USE_DB_SESSION', true);
        } else {
            define('USE_DB_SESSION', false);
        }
    }

    // Expiry stuff
    global $ExpireParams;
    foreach (array('major','minor','author') as $major) {
    	foreach (array('max_age','min_age','min_keep','keep','max_keep') as $max) {
    	    $item = strtoupper($major) . '_'. strtoupper($max);
            if (defined($item)) $val = constant($item);
            elseif (array_key_exists($item, $rs))
                $val = $rs[$item];
    	    elseif (array_key_exists($item, $rsdef))
                $val = $rsdef[$item];
            if (!isset($ExpireParams[$major]))
                $ExpireParams[$major] = array();
            $ExpireParams[$major][$max] = $val;
            unset($rs[$item]);
    	}
    }

    // User authentication
    if (!isset($GLOBALS['USER_AUTH_ORDER']))
        if (isset($rs['USER_AUTH_ORDER']))
            $GLOBALS['USER_AUTH_ORDER'] = preg_split('/\s*:\s*/', 
                                                     $rs['USER_AUTH_ORDER']);
        else 
            $GLOBALS['USER_AUTH_ORDER'] = array("PersonalPage");

    // Now it's the external DB authentication stuff's turn
    if (in_array('Db', $GLOBALS['USER_AUTH_ORDER']) && empty($rs['DBAUTH_AUTH_DSN'])) {
        $rs['DBAUTH_AUTH_DSN'] = $DBParams['dsn'];
    }
    
    global $DBAuthParams;
    $DBAP_MAP = array('DBAUTH_AUTH_DSN' => 'auth_dsn',
                      'DBAUTH_AUTH_CHECK' => 'auth_check',
                      'DBAUTH_AUTH_USER_EXISTS' => 'auth_user_exists',
                      'DBAUTH_AUTH_CRYPT_METHOD' => 'auth_crypt_method',
                      'DBAUTH_AUTH_UPDATE' => 'auth_update',
                      'DBAUTH_AUTH_CREATE' => 'auth_create',
                      'DBAUTH_PREF_SELECT' => 'pref_select',
                      'DBAUTH_PREF_UPDATE' => 'pref_update',
                      'DBAUTH_IS_MEMBER' => 'is_member',
                      'DBAUTH_GROUP_MEMBERS' => 'group_members',
                      'DBAUTH_USER_GROUPS' => 'user_groups'
                      );
    foreach ($DBAP_MAP as $rskey => $apkey) {
        if (defined($rskey)) {
            $DBAuthParams[$apkey] = constant($rskey);
        } elseif (isset($rs[$rskey])) {
            $DBAuthParams[$apkey] = $rs[$rskey];
        } elseif (isset($rsdef[$rskey])) {
            $DBAuthParams[$apkey] = $rsdef[$rskey];
        }
        unset($rs[$rskey]);
    }

    // optional values will be set to '' to simplify the logic.
    foreach ($_IC_OPTIONAL_VALUE as $item) {
        if (defined($item)) {
            unset($rs[$item]);
            continue;
        }
        if (array_key_exists($item, $rs)) {
            define($item, $rs[$item]);
            unset($rs[$item]);
        } else 
            define($item, '');
    }

    // LDAP bind options
    global $LDAP_SET_OPTION;
    if (defined('LDAP_SET_OPTION') and LDAP_SET_OPTION) {
        $optlist = preg_split('/\s*:\s*/', LDAP_SET_OPTION);
        foreach ($optlist as $opt) {
            $bits = preg_split('/\s*=\s*/', $opt, 2);
            if (count($bits) == 2) {
                if (is_string($bits[0]) and defined($bits[0]))
                    $bits[0] = constant($bits[0]);
                $LDAP_SET_OPTION[$bits[0]] = $bits[1];
            }
            else {
                // Possibly throw some sort of error?
            }
        }
    }

    // Default Wiki pages to force loading from pgsrc
    global $GenericPages;
    $GenericPages = preg_split('/\s*:\s*/', @$rs['DEFAULT_WIKI_PAGES']);

    // Wiki name regexp:  Should be a define(), but might needed to be changed at runtime
    // (different LC_CHAR need different posix classes)
    global $WikiNameRegexp;
    $WikiNameRegexp = constant('WIKI_NAME_REGEXP');
    if (!trim($WikiNameRegexp))
       $WikiNameRegexp = '(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])';

    // Another "too-tricky" redefine
    global $KeywordLinkRegexp;
    if (!isset($rs['KEYWORDS'])) $rs['KEYWORDS'] = @$rsdef['KEYWORDS'];
    if (!isset($rs['KEYWORDS'])) $rs['KEYWORDS'] = "Category:Topic";
    $keywords = preg_split('/\s*:\s*/', $rs['KEYWORDS']);
    if (empty($keywords)) $keywords = array("Category","Topic");
    $KeywordLinkRegexp = '(?<=' . implode('|^', $keywords) . ')[[:upper:]].*$';
        
    global $DisabledActions;
    if (!array_key_exists('DISABLED_ACTIONS',$rs) and array_key_exists('DISABLED_ACTIONS',$rsdef))
        $rs['DISABLED_ACTIONS'] = @$rsdef['DISABLED_ACTIONS'];
    if (array_key_exists('DISABLED_ACTIONS',$rs))
        $DisabledActions = preg_split('/\s*:\s*/', $rs['DISABLED_ACTIONS']);
    
    /*global $AllowedProtocols, $InlineImages;
    $AllowedProtocols = constant("ALLOWED_PROTOCOLS");
    $InlineImages = constant("INLINE_IMAGES");*/

    global $PLUGIN_CACHED_IMGTYPES;
    $PLUGIN_CACHED_IMGTYPES = preg_split('/\s*[|:]\s*/', PLUGIN_CACHED_IMGTYPES);
    if (empty($rs['PLUGIN_CACHED_CACHE_DIR']) and !empty($rsdef['PLUGIN_CACHED_CACHE_DIR']))
        $rs['PLUGIN_CACHED_CACHE_DIR'] = $rsdef['PLUGIN_CACHED_CACHE_DIR'];
    if (empty($rs['PLUGIN_CACHED_CACHE_DIR'])) {
        if (!FindFile('/tmp/cache', 1)) {
            if (!FindFile('/tmp', 1)) {
                mkdir('/tmp', 777);
            }
            mkdir('/tmp/cache', 777);
        }
        // will throw an error if not exists.
        define('PLUGIN_CACHED_CACHE_DIR', FindFile('/tmp/cache',false,1)); 
    } else {
        define('PLUGIN_CACHED_CACHE_DIR', $rs['PLUGIN_CACHED_CACHE_DIR']);
        // will throw an error if not exists.
        FindFile(PLUGIN_CACHED_CACHE_DIR);
    }

    // process the rest of the config.ini settings:
    foreach ($rs as $item => $value) {
        if (defined($item)) {
            continue;
        } else {
            define($item, $value);
        }
    }

    fix_configs();
}

// moved from lib/config.php
function fix_configs() {
    global $FieldSeparator, $charset, $WikiNameRegexp, $KeywordLinkRegexp, $AllActionPages;
    global $HTTP_SERVER_VARS, $DBParams, $LANG;

    // init FileFinder to add proper include paths
    require_once(dirname(__FILE__)."/FileFinder.php");
    FindFile("lib/interwiki.map",true);
    
    // "\x80"-"\x9f" (and "\x00" - "\x1f") are non-printing control
    // chars in iso-8859-*
    // $FieldSeparator = "\263"; // this is a superscript 3 in ISO-8859-1.
    // $FieldSeparator = "\xFF"; // this byte should never appear in utf-8
    // FIXME: get rid of constant. pref is dynamic and language specific
    $charset = CHARSET;
    // Disabled: Let the admin decide which charset.
    //if (isset($LANG) and in_array($LANG,array('zh')))
    //    $charset = 'utf-8';
    if (strtolower($charset) == 'utf-8')
        $FieldSeparator = "\xFF";
    else
        $FieldSeparator = "\x81";

    if (!defined('DEFAULT_LANGUAGE')) // not needed anymore
        define('DEFAULT_LANGUAGE', 'en');
    update_locale(isset($LANG) ? $LANG : DEFAULT_LANGUAGE);

    // Set up (possibly fake) gettext()
    //
    if (!function_exists ('bindtextdomain')) {
        $locale = array();

        function gettext ($text) { 
            global $locale;
            if (!empty ($locale[$text]))
                return $locale[$text];
            return $text;
        }

        function _ ($text) {
            return gettext($text);
        }
    }
    else {
        // Working around really weird gettext problems: (4.3.2, 4.3.6 win)
        // bindtextdomain() returns the current domain path.
        // 1. If the script is not index.php but something like "de", on a different path
        //    then bindtextdomain() fails, but after chdir to the correct path it will work okay.
        // 2. But the weird error "Undefined variable: bindtextdomain" is generated then.
        $bindtextdomain_path = FindFile("locale", false, true);
        if (isWindows())
            $bindtextdomain_path = str_replace("/","\\",$bindtextdomain_path);
        $bindtextdomain_real = @bindtextdomain("phpwiki", $bindtextdomain);
        if ($bindtextdomain_real != $bindtextdomain_path) {
            // this will happen with virtual_paths. chdir and try again.
            chdir($bindtextdomain_path);
            $bindtextdomain_real = @bindtextdomain("phpwiki", $bindtextdomain);
        }
        textdomain("phpwiki");
        if ($bindtextdomain_real != $bindtextdomain_path) { // change back
            chdir($bindtextdomain_real . (isWindows() ? "\\.." : "/.."));
        }
    }

    $WikiNameRegexp = pcre_fix_posix_classes($WikiNameRegexp);
    $KeywordLinkRegexp = pcre_fix_posix_classes($KeywordLinkRegexp);

    $AllActionPages = explode(':',
                              'AllPages:BackLinks:CreatePage:DebugInfo:EditMetaData:FindPage:'
                              .'FullRecentChanges:FullTextSearch:FuzzyPages:InterWikiSearch:'
                              .'LikePages:MostPopular:'
                              .'OrphanedPages:PageDump:PageHistory:PageInfo:RandomPage:RateIt:'
                              .'RecentChanges:RecentEdits:RecentComments:RelatedChanges:TitleSearch:'
                              .'TranslateText:UpLoad:UserPreferences:WantedPages:WhoIsOnline:'
                              .'PhpWikiAdministration/Remove:PhpWikiAdministration/Chmod:'
                              .'PhpWikiAdministration/Rename:PhpWikiAdministration/Replace:'
                              .'PhpWikiAdministration/SetAcl:PhpWikiAdministration/Chown'
                              );

    //////////////////////////////////////////////////////////////////
    // Autodetect URL settings:
    //
    foreach (array('SERVER_NAME','SERVER_PORT') as $var) {
        //FIXME: for CGI without _SERVER
        if (!defined($var) and !empty($HTTP_SERVER_VARS[$var]))
            define($var, $HTTP_SERVER_VARS[$var]);
    }
    if (!defined('SERVER_PROTOCOL')) {
        if (empty($HTTP_SERVER_VARS['HTTPS']) || $HTTP_SERVER_VARS['HTTPS'] == 'off')
            define('SERVER_PROTOCOL', 'http');
        else
            define('SERVER_PROTOCOL', 'https');
    }

    if (!defined('SCRIPT_NAME'))
        define('SCRIPT_NAME', deduce_script_name());

    if (!defined('USE_PATH_INFO')) {
        if (isCGI())
            define('USE_PATH_INFO', false);
        else {
            /*
             * If SCRIPT_NAME does not look like php source file,
             * or user cgi we assume that php is getting run by an
             * action handler in /cgi-bin.  In this case,
             * I think there is no way to get Apache to pass
             * useful PATH_INFO to the php script (PATH_INFO
             * is used to the the php interpreter where the
             * php script is...)
             */
            switch (php_sapi_name()) {
            case 'apache':
            case 'apache2handler':
                define('USE_PATH_INFO', true);
                break;
            case 'cgi':
            case 'apache2filter':
                define('USE_PATH_INFO', false);
                break;
            default:
                define('USE_PATH_INFO', ereg('\.(php3?|cgi)$', SCRIPT_NAME));
                break;
            }
        }
    }
     
    // If user has not defined PHPWIKI_DIR, and we need it
    if (!defined('PHPWIKI_DIR') and !file_exists("themes/default")) {
    	$themes_dir = FindFile("themes");
        define('PHPWIKI_DIR', dirname($themes_dir));
    }
        
    if (!defined('VIRTUAL_PATH')) {
        // We'd like to auto-detect when the cases where apaches
        // 'Action' directive (or similar means) is used to
        // redirect page requests to a cgi-handler.
        //
        // In cases like this, requests for e.g. /wiki/HomePage
        // get redirected to a cgi-script called, say,
        // /path/to/wiki/index.php.  The script gets all
        // of /wiki/HomePage as it's PATH_INFO.
        //
        // The problem is:
        //   How to detect when this has happened reliably?
        //   How to pick out the "virtual path" (in this case '/wiki')?
        //
        // (Another time an redirect might occur is to a DirectoryIndex
        // -- the requested URI is '/wikidir/', the request gets
        // passed to '/wikidir/index.php'.  In this case, the
        // proper VIRTUAL_PATH is '/wikidir/index.php', since the
        // pages will appear at e.g. '/wikidir/index.php/HomePage'.
        //

        $REDIRECT_URL = &$HTTP_SERVER_VARS['REDIRECT_URL'];
        if (USE_PATH_INFO and isset($REDIRECT_URL)
            and ! IsProbablyRedirectToIndex()) {
            // FIXME: This is a hack, and won't work if the requested
            // pagename has a slash in it.
            $temp = strtr(dirname($REDIRECT_URL . 'x'),"\\",'/');
            if ( ($temp == '/') || ($temp == '\\') )
                $temp = '';
            define('VIRTUAL_PATH', $temp);
        } else {
            define('VIRTUAL_PATH', SCRIPT_NAME);
        }
    }

    // If user has not defined DATA_PATH, we want to use relative URLs.
    if (!defined('DATA_PATH')) {
        // fix similar to the one suggested by jkalmbach for 
        // installations in the webrootdir, like "http://phpwiki.org/HomePage"
        $temp = dirname(SCRIPT_NAME);
        if ( ($temp == '/') || ($temp == '\\') )
            $temp = '';
        define('DATA_PATH', $temp);
        /*
        if (USE_PATH_INFO)
            define('DATA_PATH', '..');
        */
    }

    if (SERVER_PORT
        && SERVER_PORT != (SERVER_PROTOCOL == 'https' ? 443 : 80)) {
        define('SERVER_URL',
               SERVER_PROTOCOL . '://' . SERVER_NAME . ':' . SERVER_PORT);
    }
    else {
        define('SERVER_URL',
               SERVER_PROTOCOL . '://' . SERVER_NAME);
    }

    if (VIRTUAL_PATH != SCRIPT_NAME) {
        // Apache action handlers are used.
        define('PATH_INFO_PREFIX', VIRTUAL_PATH . '/');
    }
    else
        define('PATH_INFO_PREFIX', '/');

    define('PHPWIKI_BASE_URL',
           SERVER_URL . (USE_PATH_INFO ? VIRTUAL_PATH . '/' : SCRIPT_NAME));

    // Detect PrettyWiki setup (not loading index.php directly)
    // $SCRIPT_FILENAME should be the same as __FILE__ in index.php
    if (!isset($SCRIPT_FILENAME))
        $SCRIPT_FILENAME = @$HTTP_SERVER_VARS['SCRIPT_FILENAME'];
    if (!isset($SCRIPT_FILENAME))
        $SCRIPT_FILENAME = @$HTTP_ENV_VARS['SCRIPT_FILENAME'];
    if (!isset($SCRIPT_FILENAME))
        $SCRIPT_FILENAME = dirname(__FILE__.'/../') . '/index.php';
    if (isWindows())
        $SCRIPT_FILENAME = strtr($SCRIPT_FILENAME, '/', '\\');
    define('SCRIPT_FILENAME', $SCRIPT_FILENAME);

    //////////////////////////////////////////////////////////////////
    // Select database
    //
    if (empty($DBParams['dbtype']))
        $DBParams['dbtype'] = 'dba';

    if (!defined('THEME'))
        define('THEME', 'default');

    if (!defined('WIKI_NAME'))
        define('WIKI_NAME', _("An unnamed PhpWiki"));

    if (!defined('HOME_PAGE'))
        define('HOME_PAGE', _("HomePage"));

    // FIXME: delete
    // Access log
    if (!defined('ACCESS_LOG'))
        define('ACCESS_LOG', '');

    // FIXME: delete
    // Get remote host name, if apache hasn't done it for us
    if (empty($HTTP_SERVER_VARS['REMOTE_HOST']) && ENABLE_REVERSE_DNS)
        $HTTP_SERVER_VARS['REMOTE_HOST'] = gethostbyaddr($HTTP_SERVER_VARS['REMOTE_ADDR']);

    // check whether the crypt() function is needed and present
    if (defined('ENCRYPTED_PASSWD') && !function_exists('crypt')) {
        $error = sprintf(_("Encrypted passwords cannot be used: %s."),
                         "'function crypt()' not available in this version of php");
        trigger_error($error);
    }

    if (!defined('ADMIN_PASSWD') or ADMIN_PASSWD == '')
        trigger_error(_("The admin password cannot be empty. Please update your config/config.ini"));

    if (defined('USE_DB_SESSION') and USE_DB_SESSION) {
        if (! $DBParams['db_session_table'] ) {
            $DBParams['db_session_table'] = @$DBParams['prefix'] . 'session';
            trigger_error(sprintf(_("DATABASE_SESSION_TABLE configuration set to %s."), 
                                  $DBParams['db_session_table']),
                          E_USER_ERROR);
        }
    }
    // legacy:
    if (!defined('ENABLE_USER_NEW')) define('ENABLE_USER_NEW',true);
    if (!defined('ALLOW_USER_LOGIN'))
        define('ALLOW_USER_LOGIN', defined('ALLOW_USER_PASSWORDS') && ALLOW_USER_PASSWORDS);
    if (!defined('ALLOW_ANON_USER')) define('ALLOW_ANON_USER', true); 
    if (!defined('ALLOW_ANON_EDIT')) define('ALLOW_ANON_EDIT', false); 
    if (!defined('REQUIRE_SIGNIN_BEFORE_EDIT')) define('REQUIRE_SIGNIN_BEFORE_EDIT', ! ALLOW_ANON_EDIT);
    if (!defined('ALLOW_BOGO_LOGIN')) define('ALLOW_BOGO_LOGIN', true);
    if (!defined('ALLOW_LDAP_LOGIN')) define('ALLOW_LDAP_LOGIN', defined('LDAP_AUTH_HOST'));
    if (!defined('ALLOW_IMAP_LOGIN')) define('ALLOW_IMAP_LOGIN', defined('IMAP_AUTH_HOST'));

    if (ALLOW_USER_LOGIN and !empty($DBAuthParams) and empty($DBAuthParams['auth_dsn'])) {
        if (isset($DBParams['dsn']))
            $DBAuthParams['auth_dsn'] = $DBParams['dsn'];
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.54  2004/10/14 17:13:01  rurban
// use DATABASE_PREFIX
//
// Revision 1.53  2004/10/12 13:13:19  rurban
// php5 compatibility (5.0.1 ok)
//
// Revision 1.52  2004/10/04 23:38:07  rurban
// unittest fix
//
// Revision 1.51  2004/09/20 13:40:19  rurban
// define all config.ini settings, only the supported will be taken from -default.
// support USE_EXTERNAL_HTML2PDF renderer (htmldoc tested)
//
// Revision 1.50  2004/09/06 09:28:58  rurban
// fix PLUGIN_CACHED_CACHE_DIR fallback logic. ini entry did not work before
//
// Revision 1.49  2004/07/13 13:07:27  rurban
// improved DB_SESSION logic
//
// Revision 1.48  2004/07/05 13:09:37  rurban
// ENABLE_RAW_HTML_LOCKEDONLY, ENABLE_RAW_HTML_SAFE
//
// Revision 1.47  2004/07/03 16:51:05  rurban
// optional DBADMIN_USER:DBADMIN_PASSWD for action=upgrade (if no ALTER permission)
// added atomic mysql REPLACE for PearDB as in ADODB
// fixed _lock_tables typo links => link
// fixes unserialize ADODB bug in line 180
//
// Revision 1.46  2004/07/02 09:55:58  rurban
// more stability fixes: new DISABLE_GETIMAGESIZE if your php crashes when loading LinkIcons: failing getimagesize in old phps; blockparser stabilized
//
// Revision 1.45  2004/07/01 08:51:21  rurban
// dumphtml: added exclude, print pagename before processing
//
// Revision 1.44  2004/06/29 08:52:22  rurban
// Use ...version() $need_content argument in WikiDB also:
// To reduce the memory footprint for larger sets of pagelists,
// we don't cache the content (only true or false) and
// we purge the pagedata (_cached_html) also.
// _cached_html is only cached for the current pagename.
// => Vastly improved page existance check, ACL check, ...
//
// Now only PagedList info=content or size needs the whole content, esp. if sortable.
//
// Revision 1.43  2004/06/29 06:48:02  rurban
// Improve LDAP auth and GROUP_LDAP membership:
//   no error message on false password,
//   added two new config vars: LDAP_OU_USERS and LDAP_OU_GROUP with GROUP_METHOD=LDAP
//   fixed two group queries (this -> user)
// stdlib: ConvertOldMarkup still flawed
//
// Revision 1.42  2004/06/28 15:01:07  rurban
// fixed LDAP_SET_OPTION handling, LDAP error on connection problem
//
// Revision 1.41  2004/06/25 14:29:17  rurban
// WikiGroup refactoring:
//   global group attached to user, code for not_current user.
//   improved helpers for special groups (avoid double invocations)
// new experimental config option ENABLE_XHTML_XML (fails with IE, and document.write())
// fixed a XHTML validation error on userprefs.tmpl
//
// Revision 1.40  2004/06/22 07:12:48  rurban
// removed USE_TAGLINES constant
//
// Revision 1.39  2004/06/21 16:22:28  rurban
// add DEFAULT_DUMP_DIR and HTML_DUMP_DIR constants, for easier cmdline dumps,
// fixed dumping buttons locally (images/buttons/),
// support pages arg for dumphtml,
// optional directory arg for dumpserial + dumphtml,
// fix a AllPages warning,
// show dump warnings/errors on DEBUG,
// don't warn just ignore on wikilens pagelist columns, if not loaded.
// RateIt pagelist column is called "rating", not "ratingwidget" (Dan?)
//
// Revision 1.38  2004/06/21 08:39:36  rurban
// pear/Cache update from Cache-1.5.4 (added db and trifile container)
// pear/DB update from DB-1.6.1 (mysql bugfixes, php5 compat, DB_PORTABILITY features)
//
// Revision 1.37  2004/06/19 12:32:37  rurban
// new TEMP_DIR for ziplib
//
// Revision 1.36  2004/06/19 10:06:37  rurban
// Moved lib/plugincache-config.php to config/*.ini
// use PLUGIN_CACHED_* constants instead of global $CacheParams
//
// Revision 1.35  2004/06/15 09:15:52  rurban
// IMPORTANT: fixed passwd handling for passwords stored in prefs:
//   fix encrypted usage, actually store and retrieve them from db
//   fix bogologin with passwd set.
// fix php crashes with call-time pass-by-reference (references wrongly used
//   in declaration AND call). This affected mainly Apache2 and IIS.
//   (Thanks to John Cole to detect this!)
//
// Revision 1.34  2004/06/13 13:54:25  rurban
// Catch fatals on the four dump calls (as file and zip, as html and mimified)
// FoafViewer: Check against external requirements, instead of fatal.
// Change output for xhtmldumps: using file:// urls to the local fs.
// Catch SOAP fatal by checking for GOOGLE_LICENSE_KEY
// Import GOOGLE_LICENSE_KEY and FORTUNE_DIR from config.ini.
//
// Revision 1.33  2004/06/08 19:48:16  rurban
// fixed foreign setup: no ugly skipped msg for the GenericPages, load english actionpages if translated not found
//
// Revision 1.32  2004/06/08 10:54:46  rurban
// better acl dump representation, read back acl and owner
//
// Revision 1.31  2004/06/06 16:58:51  rurban
// added more required ActionPages for foreign languages
// install now english ActionPages if no localized are found. (again)
// fixed default anon user level to be 0, instead of -1
//   (wrong "required administrator to view this page"...)
//
// Revision 1.30  2004/06/04 12:40:21  rurban
// Restrict valid usernames to prevent from attacks against external auth or compromise
// possible holes.
// Fix various WikiUser old issues with default IMAP,LDAP,POP3 configs. Removed these.
// Fxied more warnings
//
// Revision 1.29  2004/06/04 11:58:38  rurban
// added USE_TAGLINES
//
// Revision 1.28  2004/06/03 20:42:49  rurban
// fixed bad warning #964850
//
// Revision 1.27  2004/06/03 10:18:19  rurban
// fix FileUser locking issues, new config ENABLE_PAGEPERM
//
// Revision 1.26  2004/06/02 18:01:45  rurban
// init global FileFinder to add proper include paths at startup
//   adds PHPWIKI_DIR if started from another dir, lib/pear also
// fix slashify for Windows
// fix USER_AUTH_POLICY=old, use only USER_AUTH_ORDER methods (besides HttpAuth)
//
// Revision 1.25  2004/05/27 17:49:05  rurban
// renamed DB_Session to DbSession (in CVS also)
// added WikiDB->getParam and WikiDB->getAuthParam method to get rid of globals
// remove leading slash in error message
// added force_unlock parameter to File_Passwd (no return on stale locks)
// fixed adodb session AffectedRows
// added FileFinder helpers to unify local filenames and DATA_PATH names
// editpage.php: new edit toolbar javascript on ENABLE_EDIT_TOOLBAR
//
// Revision 1.24  2004/05/18 13:33:13  rurban
// we already have a CGI function
//
// Revision 1.23  2004/05/17 17:43:29  rurban
// CGI: no PATH_INFO fix
//
// Revision 1.22  2004/05/16 22:07:35  rurban
// check more config-default and predefined constants
// various PagePerm fixes:
//   fix default PagePerms, esp. edit and view for Bogo and Password users
//   implemented Creator and Owner
//   BOGOUSERS renamed to BOGOUSER
// fixed syntax errors in signin.tmpl
//
// Revision 1.21  2004/05/08 22:55:12  rurban
// Fixed longstanding sf.net:demo problem. endless loop, caused by an empty definition of
// WIKI_NAME_REGEXP. Exactly this constant wasn't checked for its default setting.
//
// Revision 1.20  2004/05/08 20:21:00  rurban
// remove php tags in Log
//
// Revision 1.19  2004/05/08 19:55:29  rurban
// support <span>inlined plugin-result</span>:
//   if the plugin is parsed inside a line, use <span> instead of
//   <div tightenable top bottom>
//   e.g. for "This is the current Phpwiki <plugin SystemInfo version> version.
//
// Revision 1.18  2004/05/08 16:58:19  rurban
// don't ignore some false config values (e.g. USE_PATH_INFO false was ignored)
//
// Revision 1.17  2004/05/06 19:26:15  rurban
// improve stability, trying to find the InlineParser endless loop on sf.net
//
// remove end-of-zip comments to fix sf.net bug #777278 and probably #859628
//
// Revision 1.16  2004/05/02 15:10:05  rurban
// new finally reliable way to detect if /index.php is called directly
//   and if to include lib/main.php
// new global AllActionPages
// SetupWiki now loads all mandatory pages: HOME_PAGE, action pages, and warns if not.
// WikiTranslation what=buttons for Carsten to create the missing MacOSX buttons
// PageGroupTestOne => subpages
// renamed PhpWikiRss to PhpWikiRecentChanges
// more docs, default configs, ...
//
// Revision 1.15  2004/05/01 15:59:29  rurban
// more php-4.0.6 compatibility: superglobals
//
// Revision 1.14  2004/04/29 23:25:12  rurban
// re-ordered locale init (as in 1.3.9)
// fixed loadfile with subpages, and merge/restore anyway
//   (sf.net bug #844188)
//
// Revision 1.13  2004/04/29 21:54:05  rurban
// typo
//
// Revision 1.12  2004/04/27 16:16:27  rurban
// more subtle config problems with defaults
//
// Revision 1.11  2004/04/26 20:44:34  rurban
// locking table specific for better databases
//
// Revision 1.10  2004/04/26 13:22:32  rurban
// calculate bool old or dynamic constants later
//
// Revision 1.9  2004/04/26 12:15:01  rurban
// check default config values
//
// Revision 1.8  2004/04/23 16:55:59  zorloc
// If using Db auth and DBAUTH_AUTH_DSN is empty set DBAUTH_AUTH_DSN to $DBParams['dsn']
//
// Revision 1.7  2004/04/20 22:26:27  zorloc
// Removed Pear_Config for parse_ini_file().
//
// Revision 1.6  2004/04/20 18:10:27  rurban
// config refactoring:
//   FileFinder is needed for WikiFarm scripts calling index.php
//   config run-time calls moved to lib/IniConfig.php:fix_configs()
//   added PHPWIKI_DIR smart-detection code (Theme finder)
//   moved FileFind to lib/FileFinder.php
//   cleaned lib/config.php
//
// Revision 1.5  2004/04/20 17:21:57  rurban
// WikiFarm code: honor predefined constants
//
// Revision 1.4  2004/04/20 17:08:19  rurban
// Some IniConfig fixes: prepend our private lib/pear dir
//   switch from " to ' in the auth statements
//   use error handling.
// WikiUserNew changes for the new "'$variable'" syntax
//   in the statements
// TODO: optimization to put config vars into the session.
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>