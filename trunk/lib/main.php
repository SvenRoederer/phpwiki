<?php //-*-php-*-
rcs_id('$Id: main.php,v 1.184 2004-11-01 10:43:57 rurban Exp $');

define ('USE_PREFS_IN_PAGE', true);

//include "lib/config.php";
require_once(dirname(__FILE__)."/stdlib.php");
require_once('lib/Request.php');
require_once('lib/WikiDB.php');
if (ENABLE_USER_NEW)
    require_once("lib/WikiUserNew.php");
else
    require_once("lib/WikiUser.php");
require_once("lib/WikiGroup.php");
require_once("lib/PagePerm.php");

class WikiRequest extends Request {
    // var $_dbi;

    function WikiRequest () {
        $this->_dbi = WikiDB::open($GLOBALS['DBParams']);
        //if ((DEBUG & _DEBUG_TRACE) or (time() % 50 == 0))
        //    $this->_dbi->_backend->optimize();
        if (in_array('File', $this->_dbi->getAuthParam('USER_AUTH_ORDER'))) {
            // force our local copy, until the pear version is fixed.
            include_once(dirname(__FILE__)."/pear/File_Passwd.php");
        }
        if (USE_DB_SESSION) {
            include_once('lib/DbSession.php');
            $dbi =& $this->_dbi;
            $this->_dbsession = & new DbSession($dbi, $dbi->getParam('prefix') . $dbi->getParam('db_session_table'));
        }
// Fixme: Does pear reset the error mask to 1? We have to find the culprit
//$x = error_reporting();
$this->version = phpwiki_version();
        $this->Request();

        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        // Restore auth state. This doesn't check for proper authorization!
        if (ENABLE_USER_NEW) {
            $userid = $this->_deduceUsername();	
            if (isset($this->_user) and 
                !empty($this->_user->_authhow) and 
                $this->_user->_authhow == 'session')
            {
                // users might switch in a session between the two objects.
                // restore old auth level here or in updateAuthAndPrefs?
                //$user = $this->getSessionVar('wiki_user');
                // revive db handle, because these don't survive sessions
                if (isset($this->_user) and 
                     ( ! isa($this->_user, WikiUserClassname())
                       or (strtolower(get_class($this->_user)) == '_passuser')))
                {
                    $this->_user = WikiUser($userid, $this->_user->_prefs);
                }
	        // revive other db handle
	        if (isset($this->_user->_prefs->_method) and 
	                 ($this->_user->_prefs->_method == 'SQL' or 
	                 $this->_user->_prefs->_method == 'ADODB'))
	            $this->_user->_HomePagehandle = $this->getPage($userid);
	        // need to update the lockfile filehandle
	        if (  isa($this->_user,'_FilePassUser') and 
	              $this->_user->_file->lockfile and 
	              !$this->_user->_file->fplock  )
	        {
	            //$level = $this->_user->_level;
	            $this->_user = UpgradeUser($this->_user, 
	                                       new _FilePassUser($userid, $this->_user->_prefs, $this->_user->_file->filename));
                    //$this->_user->_level = $level;
                }
            	$this->_prefs = & $this->_user->_prefs;
            } else {
                $user = WikiUser($userid);
                $this->_user = & $user;
                $this->_prefs = & $this->_user->_prefs;
            }
        } else {
            $this->_user = new WikiUser($this, $this->_deduceUsername());
            $this->_prefs = $this->_user->getPreferences();
        }
    }

    function initializeLang () {
        $user_lang = $this->getPref('lang');
        $_lang = @$this->_prefs->_prefs['lang'];
        //check changed LANG and THEME inside a session. 
        // (e.g. by using another baseurl)
        if (isset($this->_user->_authhow) and 
            $this->_user->_authhow == 'session' and 
            !isset($_lang->lang) and 
            $user_lang != $GLOBALS['LANG'])
        {
            $user_lang = $GLOBALS['LANG'];
        }
        if (isset($user_lang)) {
            //trigger_error("DEBUG: initializeLang() ". $user_lang ." calling update_locale()...");
            update_locale($user_lang);
            FindLocalizedButtonFile(".",'missing_ok','reinit');
        }
    }

    function initializeTheme () {
        global $WikiTheme;

        // Load theme
        $user_theme = $this->getPref('theme');
        $_theme = @$this->_prefs->_prefs['theme'];
        //check changed LANG and THEME inside a session. 
        // (e.g. by using another baseurl)
        if (isset($this->_user->_authhow) and 
            $this->_user->_authhow == 'session' and 
            !isset($_theme->theme) and
            defined('THEME') and 
            $user_theme != THEME)
        {
            include_once("themes/" . THEME . "/themeinfo.php");
        }
        if (empty($WikiTheme) and isset($user_theme)) {
            if (strcspn($user_theme,"./\x00]") != strlen($user_theme)) {
            	trigger_error(sprintf("invalid theme '%s': Invalid characters detected", $user_theme),
            	              E_USER_WARNING);
                $user_theme = "default";
            }
            include_once("themes/$user_theme/themeinfo.php");
        }
        if (empty($WikiTheme) and defined('THEME'))
            include_once("themes/" . THEME . "/themeinfo.php");
        if (empty($WikiTheme))
            include_once("themes/default/themeinfo.php");
        assert(!empty($WikiTheme));
    }

    // This really maybe should be part of the constructor, but since it
    // may involve HTML/template output, the global $request really needs
    // to be initialized before we do this stuff.
    function updateAuthAndPrefs () {

        if (isset($this->_user) and (!isa($this->_user,WikiUserClassname()))) {
            $this->_user = false;	
        }
        // Handle authentication request, if any.
        if ($auth_args = $this->getArg('auth')) {
            $this->setArg('auth', false);
            $this->_handleAuthRequest($auth_args); // possible NORETURN
        }
        elseif ( ! $this->_user or 
                 (isa($this->_user,WikiUserClassname()) and ! $this->_user->isSignedIn())) {
            // If not auth request, try to sign in as saved user.
            if (($saved_user = $this->getPref('userid')) != false) {
                $this->_signIn($saved_user);
            }
        }

        // Save preferences in session and cookie
        if (isset($this->_user) and 
            (!isset($this->_user->_authhow) or $this->_user->_authhow != 'session')) {
            $id_only = true; 
            $this->_user->setPreferences($this->_prefs, $id_only);
        } else {
            $this->setSessionVar('wiki_user', $this->_user);
            //$this->setSessionVar('wiki_prefs', $this->_prefs);
        }

        // Ensure user has permissions for action
        // HACK ALERT: We may not set the request arg to create, 
        // since the pageeditor has an ugly logic for action == create.
        $action = $this->getArg('action');
  	if ($action == 'edit' or $action == 'create') {
            $page = $this->getPage();
            if (! $page->exists() )
                $action = 'create';
            else
                $action = 'edit';
  	}
        $require_level = $this->requiredAuthority($action);
        if (! $this->_user->hasAuthority($require_level))
            $this->_notAuthorized($require_level); // NORETURN
    }

    function getUser () {
        if (isset($this->_user))
            return $this->_user;
        else
            return $GLOBALS['ForbiddenUser'];
    }
    
    function & getGroup () {
        if (isset($this->_user) and isset($this->_user->_group))
            return $this->_user->_group;
        else
            return WikiGroup::getGroup();
    }

    function getPrefs () {
        return $this->_prefs;
    }

    // Convenience function:
    function getPref ($key) {
        if (isset($this->_prefs)) {
            return $this->_prefs->get($key);
        }
    }
    function getDbh () {
        return $this->_dbi;
    }

    /**
     * Get requested page from the page database.
     * By default it will grab the page requested via the URL
     *
     * This is a convenience function.
     * @param string $pagename Name of page to get.
     * @return WikiDB_Page Object with methods to pull data from
     * database for the page requested.
     */
    function getPage ($pagename = false) {
        //if (!isset($this->_dbi)) $this->getDbh();
        if (!$pagename) 
            $pagename = $this->getArg('pagename');
        return $this->_dbi->getPage($pagename);
    }

    /** Get URL for POST actions.
     *
     * Officially, we should just use SCRIPT_NAME (or some such),
     * but that causes problems when we try to issue a redirect, e.g.
     * after saving a page.
     *
     * Some browsers (at least NS4 and Mozilla 0.97 won't accept
     * a redirect from a page to itself.)
     *
     * So, as a HACK, we include pagename and action as query args in
     * the URL.  (These should be ignored when we receive the POST
     * request.)
     */
    function getPostURL ($pagename=false) {
        global $HTTP_GET_VARS;

        if ($pagename === false)
            $pagename = $this->getArg('pagename');
        $action = $this->getArg('action');
        if (!empty($HTTP_GET_VARS['start_debug'])) // zend ide support
            return WikiURL($pagename, array('action' => $action, 'start_debug' => 1));
        else
            return WikiURL($pagename, array('action' => $action));
    }
    
    function _handleAuthRequest ($auth_args) {
        if (!is_array($auth_args))
            return;

        // Ignore password unless POST'ed.
        if (!$this->isPost())
            unset($auth_args['passwd']);

        $olduser = $this->_user;
        $user = $this->_user->AuthCheck($auth_args);
        if (isa($user, WikiUserClassname())) {
            // Successful login (or logout.)
            $this->_setUser($user);
        }
        elseif (is_string($user)) {
            // Login attempt failed.
            $fail_message = $user;
            $auth_args['pass_required'] = true;
            // If no password was submitted, it's not really
            // a failure --- just need to prompt for password...
            if (!ALLOW_USER_PASSWORDS 
                and ALLOW_BOGO_LOGIN 
                and !isset($auth_args['passwd'])) 
            {
                $fail_message = false;
            }
            $olduser->PrintLoginForm($this, $auth_args, $fail_message, 'newpage');
            $this->finish();    //NORETURN
        }
        else {
            // Login request cancelled.
        }
    }

    /**
     * Attempt to sign in (bogo-login).
     *
     * Fails silently.
     *
     * @param $userid string Userid to attempt to sign in as.
     * @access private
     */
    function _signIn ($userid) {
        if (ENABLE_USER_NEW) {
            if (! $this->_user )
                $this->_user = new _BogoUser($userid);
            // FIXME: is this always false? shouldn't we try passuser first?
            if (! $this->_user ) 
                $this->_user = new _PassUser($userid);
        }
        $user = $this->_user->AuthCheck(array('userid' => $userid));
        if (isa($user,WikiUserClassname())) {
            $this->_setUser($user); // success!
        }
    }

    // login or logout or restore state
    function _setUser ($user) {
        $this->_user = $user;
        if (defined('MAIN_setUser')) return;
        define('MAIN_setUser',true);
        $this->setCookieVar('WIKI_ID', $user->getAuthenticatedId(),
                            COOKIE_EXPIRATION_DAYS, COOKIE_DOMAIN);
        if ($user->isSignedIn())
            $user->_authhow = 'signin';

        // Save userid to prefs..
        if ( empty($this->_user->_prefs)) {
            $this->_user->_prefs = $this->_user->getPreferences();
            $this->_prefs =& $this->_user->_prefs;
        }
        $this->_user->_group = $this->getGroup();
        // avoid recursive objects and session resource handles
        // avoid overlarge session data (max 4000 byte!)
        if (isset($user->_group)) {
            unset($user->_group->_request);
            unset($user->_group->_user);
        }
        if (ENABLE_USER_NEW) {
            unset($user->_HomePagehandle);
            unset($user->_auth_dbi);
        } else {
            unset($user->_dbi);
            unset($user->_authdbi);
            unset($user->_homepage);
            unset($user->_request);
        }
        if (empty($user->page))
            $user->page = $this->getArg('pagename');
        if (empty($user->action))
            $user->action = $this->getArg('action');
        $this->setSessionVar('wiki_user', $user);
        $this->_prefs->set('userid',
                           $user->isSignedIn() ? $user->getId() : '');
        $this->initializeTheme();
    }

    /* Permission system */
    function getLevelDescription($level) {
    	static $levels = false;
    	if (!$levels) 
    	    $levels = array('x-1' => _("FORBIDDEN"),
                            'x0'  => _("ANON"),
                            'x1'  => _("BOGO"),
                            'x2'  => _("USER"),
                            'x10' => _("ADMIN"),
                            'x100'=> _("UNOBTAINABLE"));
    	return $levels["x".$level];
    }
    
    function _notAuthorized ($require_level) {
        // Display the authority message in the Wiki's default
        // language, in case it is not english.
        //
        // Note that normally a user will not see such an error once
        // logged in, unless the admin has altered the default
        // disallowed wikiactions. In that case we should probably
        // check the user's language prefs too at this point; this
        // would be a situation which is not really handled with the
        // current code.
        if (empty($GLOBALS['LANG']))
            update_locale(DEFAULT_LANGUAGE);

        // User does not have required authority.  Prompt for login.
        $what = $this->getActionDescription($this->getArg('action'));
        $pass_required = ($require_level >= WIKIAUTH_USER);
        if ($require_level == WIKIAUTH_UNOBTAINABLE) {
            global $DisabledActions;
	    if ($DisabledActions and in_array($action, $DisabledActions)) {
            	$msg = fmt("%s is disallowed on this wiki.",
                           $this->getDisallowedActionDescription($this->getArg('action')));
		$this->finish();
		return;
	    }
            if (class_exists('PagePermission')) {
                $user =& $this->_user;
            	$status = $user->isAuthenticated() ? _("authenticated") : _("not authenticated");
            	$msg = fmt("%s %s %s is disallowed on this wiki for %s user '%s' (level: %s).",
                           _("Missing PagePermission:"),
                           action2access($this->getArg('action')),
                           $this->getArg('pagename'),
                           $status, $user->getId(), $this->getLevelDescription($user->_level));
                // TODO: add link to action=setacl
                $user->PrintLoginForm($this, compact('pass_required'), $msg);
                $this->finish();
		return;
            } else {
            	$msg = fmt("%s is disallowed on this wiki.",
                           $this->getDisallowedActionDescription($this->getArg('action')));
                $this->_user->PrintLoginForm($this, compact('require_level','pass_required'), $msg);
		$this->finish();
		return;
            }
        }
        elseif ($require_level == WIKIAUTH_BOGO)
            $msg = fmt("You must sign in to %s.", $what);
        elseif ($require_level == WIKIAUTH_USER)
            $msg = fmt("You must log in to %s.", $what);
        elseif ($require_level == WIKIAUTH_ANON)
            $msg = fmt("Access for you is forbidden to %s.", $what);
        else
            $msg = fmt("You must be an administrator to %s.", $what);

        $this->_user->PrintLoginForm($this, compact('require_level','pass_required'), $msg);
        $this->finish();    // NORETURN
    }

    // Fixme: for PagePermissions we'll need other strings, 
    // relevant to the requested page, not just for the action on the whole wiki.
    function getActionDescription($action) {
        static $actionDescriptions;
        if (! $actionDescriptions) {
            $actionDescriptions
            = array('browse'     => _("view this page"),
                    'diff'       => _("diff this page"),
                    'dumphtml'   => _("dump html pages"),
                    'dumpserial' => _("dump serial pages"),
                    'edit'       => _("edit this page"),
                    'revert'     => _("revert to a previous version of this page"),
                    'create'     => _("create this page"),
                    'loadfile'   => _("load files into this wiki"),
                    'lock'       => _("lock this page"),
                    'remove'     => _("remove this page"),
                    'unlock'     => _("unlock this page"),
                    'upload'     => _("upload a zip dump"),
                    'verify'     => _("verify the current action"),
                    'viewsource' => _("view the source of this page"),
                    'xmlrpc'     => _("access this wiki via XML-RPC"),
                    'soap'       => _("access this wiki via SOAP"),
                    'zip'        => _("download a zip dump from this wiki"),
                    'ziphtml'    => _("download an html zip dump from this wiki")
                    );
        }
        if (in_array($action, array_keys($actionDescriptions)))
            return $actionDescriptions[$action];
        else
            return $action;
    }
    
    /**
TODO: check against these cases:
        if ($DisabledActions and in_array($action, $DisabledActions))
            return WIKIAUTH_UNOBTAINABLE;

    	if (ENABLE_PAGEPERM and class_exists("PagePermission")) {
    	   return requiredAuthorityForPage($action);
    	   
=> Browsing pages is disallowed on this wiki for authenticated user 'rurban' (level: BOGO).
    */
    function getDisallowedActionDescription($action) {
        static $disallowedActionDescriptions;
        
        if (! $disallowedActionDescriptions) {
            $disallowedActionDescriptions
            = array('browse'     => _("Browsing pages"),
                    'diff'       => _("Diffing pages"),
                    'dumphtml'   => _("Dumping html pages"),
                    'dumpserial' => _("Dumping serial pages"),
                    'edit'       => _("Editing pages"),
                    'revert'     => _("Reverting to a previous version of pages"),
                    'create'     => _("Creating pages"),
                    'loadfile'   => _("Loading files"),
                    'lock'       => _("Locking pages"),
                    'remove'     => _("Removing pages"),
                    'unlock'     => _("Unlocking pages"),
                    'upload'     => _("Uploading zip dumps"),
                    'verify'     => _("Verify the current action"),
                    'viewsource' => _("Viewing the source of pages"),
                    'xmlrpc'     => _("XML-RPC access"),
                    'soap'       => _("SOAP access"),
                    'zip'        => _("Downloading zip dumps"),
                    'ziphtml'    => _("Downloading html zip dumps")
                    );
        }
        if (in_array($action, array_keys($disallowedActionDescriptions)))
            return $disallowedActionDescriptions[$action];
        else
            return $action;
    }

    function requiredAuthority ($action) {
        $auth = $this->requiredAuthorityForAction($action);
        if (!ALLOW_ANON_USER) return WIKIAUTH_USER;
        
        /*
         * This is a hook for plugins to require authority
         * for posting to them.
         *
         * IMPORTANT: this is not a secure check, so the plugin
         * may not assume that any POSTs to it are authorized.
         * All this does is cause PhpWiki to prompt for login
         * if the user doesn't have the required authority.
         */
        if ($this->isPost()) {
            $post_auth = $this->getArg('require_authority_for_post');
            if ($post_auth !== false)
                $auth = max($auth, $post_auth);
        }
        return $auth;
    }
        
    function requiredAuthorityForAction ($action) {
        global $DisabledActions;
        
        if ($DisabledActions and in_array($action, $DisabledActions))
            return WIKIAUTH_UNOBTAINABLE;
            
    	if (ENABLE_PAGEPERM and class_exists("PagePermission")) {
    	   return requiredAuthorityForPage($action);
    	} else {
          // FIXME: clean up. 
          switch ($action) {
            case 'browse':
            case 'viewsource':
            case 'diff':
            case 'select':
            case 'xmlrpc':
            case 'search':
            case 'pdf':
                return WIKIAUTH_ANON;

            case 'zip':
            case 'ziphtml':
                if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
                    return WIKIAUTH_ADMIN;
                return WIKIAUTH_ANON;

            case 'edit':
            case 'revert':
            case 'soap':
                if (defined('REQUIRE_SIGNIN_BEFORE_EDIT') && REQUIRE_SIGNIN_BEFORE_EDIT)
                    return WIKIAUTH_BOGO;
                return WIKIAUTH_ANON;
                // return WIKIAUTH_BOGO;

            case 'create':
                $page = $this->getPage();
                $current = $page->getCurrentRevision();
                if ($current->hasDefaultContents())
                    return $this->requiredAuthorityForAction('edit');
                return $this->requiredAuthorityForAction('browse');

            case 'upload':
            case 'dumpserial':
            case 'dumphtml':
            case 'loadfile':
            case 'remove':
            case 'lock':
            case 'unlock':
            case 'upgrade':
            case 'chown':
            case 'setacl':
            case 'rename':
                return WIKIAUTH_ADMIN;

            /* authcheck occurs only in the plugin.
               required actionpage RateIt */
            /*
            case 'rate':
            case 'delete_rating':
                // Perhaps this should be WIKIAUTH_USER
                return WIKIAUTH_BOGO;
            */

            default:
                global $WikiNameRegexp;
                if (preg_match("/$WikiNameRegexp\Z/A", $action))
                    return WIKIAUTH_ANON; // ActionPage.
                else
                    return WIKIAUTH_ADMIN;
          }
        }
    }
    /* End of Permission system */

    function possiblyDeflowerVirginWiki () {
        if ($this->getArg('action') != 'browse')
            return;
        if ($this->getArg('pagename') != HOME_PAGE)
            return;

        $page = $this->getPage();
        $current = $page->getCurrentRevision();
        if ($current->getVersion() > 0)
            return;             // Homepage exists.

        include('lib/loadsave.php');
        SetupWiki($this);
        $this->finish();        // NORETURN
    }

    function handleAction () {
        $action = $this->getArg('action');
        $method = "action_$action";
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
        elseif ($page = $this->findActionPage($action)) {
            $this->actionpage($page);
        }
        else {
            $this->finish(fmt("%s: Bad action", $action));
        }
    }
    
    function finish ($errormsg = false) {
        static $in_exit = 0;

        if ($in_exit)
            exit();        // just in case CloseDataBase calls us
        $in_exit = true;

        global $ErrorManager;
        $ErrorManager->flushPostponedErrors();

        if (!empty($errormsg)) {
            PrintXML(HTML::br(),
                     HTML::hr(),
                     HTML::h2(_("Fatal PhpWiki Error")),
                     $errormsg);
            // HACK:
            echo "\n</body></html>";
        }
        if (is_object($this->_user)) {
            $this->_user->page   = $this->getArg('pagename');
            $this->_user->action = $this->getArg('action');
            unset($this->_user->_HomePagehandle);
            unset($this->_user->_auth_dbi);
	}
        if (!empty($this->_dbi)) {
            session_write_close();
            $this->_dbi->close();
            unset($this->_dbi);
        }
        Request::finish();
        exit;
    }

    /**
     * Generally pagename is rawurlencoded for older browsers or mozilla.
     * Typing a pagename into the IE bar will utf-8 encode it, so we have to 
     * fix that with fixTitleEncoding().
     * If USE_PATH_INFO = true, the pagename is stripped from the "/DATA_PATH/PageName&arg=value" line.
     * If false, we support either "/index.php?pagename=PageName&arg=value",
     * or the first arg (1.2.x style): "/index.php?PageName&arg=value"
     */
    function _deducePagename () {
        if (trim(rawurldecode($this->getArg('pagename'))))
            return fixTitleEncoding(rawurldecode($this->getArg('pagename')));

        if (USE_PATH_INFO) {
            $pathinfo = $this->get('PATH_INFO');
            if (empty($pathinfo)) { // fix for CGI
                $path = $this->get('REQUEST_URI');
                $script = $this->get('SCRIPT_NAME');
                $pathinfo = substr($path,strlen($script));
                $pathinfo = preg_replace('/\?.+$/','',$pathinfo);
            }
            $tail = substr($pathinfo, strlen(PATH_INFO_PREFIX));

            if (trim($tail) != '' and $pathinfo == PATH_INFO_PREFIX . $tail) {
                return fixTitleEncoding($tail);
            }
        }
        elseif ($this->isPost()) {
            /*
             * In general, for security reasons, HTTP_GET_VARS should be ignored
             * on POST requests, but we make an exception here (only for pagename).
             *
             * The justification for this hack is the following
             * asymmetry: When POSTing with USE_PATH_INFO set, the
             * pagename can (and should) be communicated through the
             * request URL via PATH_INFO.  When POSTing with
             * USE_PATH_INFO off, this cannot be done --- the only way
             * to communicate the pagename through the URL is via
             * QUERY_ARGS (HTTP_GET_VARS).
             */
            global $HTTP_GET_VARS;
            if (isset($HTTP_GET_VARS['pagename']) and trim($HTTP_GET_VARS['pagename'])) { 
                return fixTitleEncoding(rawurldecode($HTTP_GET_VARS['pagename']));
            }
        }

        /*
         * Support for PhpWiki 1.2 style requests.
         * Strip off "&" args (?PageName&action=...&start_debug,...)
         */
        $query_string = $this->get('QUERY_STRING');
        if (trim(rawurldecode($query_string)) and preg_match('/^([^&=]+)(&.+)?$/', $query_string, $m)) {
            return fixTitleEncoding(rawurldecode($m[1]));
        }

        return fixTitleEncoding(HOME_PAGE);
    }

    function _deduceAction () {
        if (!($action = $this->getArg('action'))) {
            // Detect XML-RPC requests
            if ($this->isPost()
                && $this->get('CONTENT_TYPE') == 'text/xml') {
                global $HTTP_RAW_POST_DATA;
                if (strstr($HTTP_RAW_POST_DATA, '<methodCall>')) {
                    return 'xmlrpc';
                }
            }
            return 'browse';    // Default if no action specified.
        }

        if (method_exists($this, "action_$action"))
            return $action;

        // Allow for, e.g. action=LikePages
        if ($this->isActionPage($action))
            return $action;

        // Handle untranslated actionpages in non-english
        // (people playing with switching languages)
        if (0 and $GLOBALS['LANG'] != 'en') {
            require_once("lib/plugin/_WikiTranslation.php");
            $trans = new WikiPlugin__WikiTranslation();
            $en_action = $trans->translate($action,'en',$GLOBALS['LANG']);
            if ($this->isActionPage($en_action))
                return $en_action;
        }

        trigger_error("$action: Unknown action", E_USER_NOTICE);
        return 'browse';
    }

    function _deduceUsername() {
        global $HTTP_SERVER_VARS, $HTTP_ENV_VARS;

        if (!empty($this->args['auth']) and !empty($this->args['auth']['userid']))
            return $this->args['auth']['userid'];

        if (!empty($HTTP_SERVER_VARS['PHP_AUTH_USER']))
            return $HTTP_SERVER_VARS['PHP_AUTH_USER'];
        if (!empty($HTTP_ENV_VARS['REMOTE_USER']))
            return $HTTP_ENV_VARS['REMOTE_USER'];
            
        if ($user = $this->getSessionVar('wiki_user')) {
            $this->_user = $user;
            $this->_user->_authhow = 'session';
            return ENABLE_USER_NEW ? $user->UserName() : $this->_user;
        }
        if ($userid = $this->getCookieVar('WIKI_ID')) {
            if (!empty($userid) and substr($userid,0,2) != 's:') {
                $this->_user->authhow = 'cookie';
                return $userid;
            }
        }
        return false;
    }
    
    function _isActionPage ($pagename) {
        $dbi = $this->getDbh();
        $page = $dbi->getPage($pagename);
        if (!$page) return false;
        $rev = $page->getCurrentRevision();
        // FIXME: more restrictive check for sane plugin?
        if (strstr($rev->getPackedContent(), '<?plugin'))
            return true;
        if (!$rev->hasDefaultContents())
            trigger_error("$pagename: Does not appear to be an 'action page'", E_USER_NOTICE);
        return false;
    }

    function findActionPage ($action) {
        static $cache;

        // check for translated version, as per users preferred language
        // (or system default in case it is not en)
        $translation = gettext($action);

        if (isset($cache) and isset($cache[$translation]))
            return $cache[$translation];

        // check for cached translated version
        if ($this->_isActionPage($translation))
            return $cache[$action] = $translation;

        // Allow for, e.g. action=LikePages
        if (!isWikiWord($action))
            return $cache[$action] = false;

        // check for translated version (default language)
        global $LANG;
        if ($LANG != "en") {
            require_once("lib/WikiPlugin.php");
            require_once("lib/plugin/_WikiTranslation.php");
            $trans = new WikiPlugin__WikiTranslation();
            $trans->lang = $LANG;
	    $default = $trans->translate_to_en($action, $LANG);
            if ($this->_isActionPage($default))
                return $cache[$action] = $default;
        } else {
            $default = $translation;
        }
        
        // check for english version
        if ($action != $translation and $action != $default) {
            if ($this->_isActionPage($action))
                return $cache[$action] = $action;
        }

        trigger_error("$action: Cannot find action page", E_USER_NOTICE);
        return $cache[$action] = false;
    }
    
    function isActionPage ($pagename) {
        return $this->findActionPage($pagename);
    }

    function action_browse () {
        $this->buffer_output();
        include_once("lib/display.php");
        displayPage($this);
    }

    function action_verify () {
        $this->action_browse();
    }

    function actionpage ($action) {
        $this->buffer_output();
        include_once("lib/display.php");
        actionPage($this, $action);
    }

    function adminActionSubpage ($subpage) {
        $page = _("PhpWikiAdministration")."/".$subpage;
        $action = $this->findActionPage($page);
        if ($action) {
            $this->setArg('s',$this->getArg('pagename'));
            $this->setArg('verify',1);
            $this->setArg('action',$action);
            $this->actionpage($action);
        } else {
            trigger_error($page.": Cannot find action page", E_USER_WARNING);
        }
    }

    function action_chown () {
        $this->adminActionSubpage(_("Chown"));
    }

    function action_setacl () {
        $this->adminActionSubpage(_("SetAcl"));
    }

    function action_rename () {
        $this->adminActionSubpage(_("Rename"));
    }

    function action_dump () {
        $action = $this->findActionPage(_("PageDump"));
        if ($action) {
            $this->actionpage($action);
        } else {
            // redirect to action=upgrade if admin?
            trigger_error(_("PageDump").": Cannot find action page", E_USER_WARNING);
        }
    }

    function action_diff () {
        $this->buffer_output();
        include_once "lib/diff.php";
        showDiff($this);
    }

    function action_search () {
        // This is obsolete: reformulate URL and redirect.
        // FIXME: this whole section should probably be deleted.
        if ($this->getArg('searchtype') == 'full') {
            $search_page = _("FullTextSearch");
        }
        else {
            $search_page = _("TitleSearch");
        }
        $this->redirect(WikiURL($search_page,
                                array('s' => $this->getArg('searchterm')),
                                'absolute_url'));
    }

    function action_edit () {
        $this->buffer_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->editPage();
    }

    function action_create () {
        $this->action_edit();
    }
    
    function action_viewsource () {
        $this->buffer_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->viewSource();
    }

    function action_lock () {
        $page = $this->getPage();
        $page->set('locked', true);
        $this->_dbi->touch();
        $this->action_browse();
    }

    function action_unlock () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        $page = $this->getPage();
        $page->set('locked', false);
        $this->_dbi->touch();
        $this->action_browse();
    }

    function action_remove () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        $pagename = $this->getArg('pagename');
        if (strstr($pagename,_('PhpWikiAdministration'))) {
            $this->action_browse();
        } else {
            include('lib/removepage.php');
            RemovePage($this);
        }
    }

    function action_xmlrpc () {
        include_once("lib/XmlRpcServer.php");
        $xmlrpc = new XmlRpcServer($this);
        $xmlrpc->service();
    }
    
    function action_revert () {
        include_once "lib/loadsave.php";
        RevertPage($this);
    }

    function action_zip () {
        include_once("lib/loadsave.php");
        MakeWikiZip($this);
        // I don't think it hurts to add cruft at the end of the zip file.
        //echo "\n========================================================\n";
        //echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
    }

    function action_ziphtml () {
        include_once("lib/loadsave.php");
        MakeWikiZipHtml($this);
        // I don't think it hurts to add cruft at the end of the zip file.
        echo "\n========================================================\n";
        echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
    }

    function action_dumpserial () {
        include_once("lib/loadsave.php");
        DumpToDir($this);
    }

    function action_dumphtml () {
        include_once("lib/loadsave.php");
        DumpHtmlToDir($this);
    }

    function action_upload () {
        include_once("lib/loadsave.php");
        LoadPostFile($this);
    }

    function action_upgrade () {
        include_once("lib/loadsave.php");
        include_once("lib/upgrade.php");
        DoUpgrade($this);
    }

    function action_loadfile () {
        include_once("lib/loadsave.php");
        LoadFileOrDir($this);
    }

    function action_pdf () {
    	include_once("lib/pdf.php");
    	ConvertAndDisplayPdf($this);
    }
    
}

//FIXME: deprecated
function is_safe_action ($action) {
    return WikiRequest::requiredAuthorityForAction($action) < WIKIAUTH_ADMIN;
}

function validateSessionPath() {
    // Try to defer any session.save_path PHP errors before any html
    // is output, which causes some versions of IE to display a blank
    // page (due to its strict mode while parsing a page?).
    if (! is_writeable(ini_get('session.save_path'))) {
        $tmpdir = '/tmp';
        trigger_error
            (sprintf(_("%s is not writable."),
                     _("The session.save_path directory"))
             . "\n"
             . sprintf(_("Please ensure that %s is writable, or redefine %s in config/config.ini."),
                       sprintf(_("the directory '%s'"),
                               ini_get('session.save_path')),
                       'session.save_path')
             . "\n"
             . sprintf(_("Attempting to use the directory '%s' instead."),
                       $tmpdir)
             , E_USER_NOTICE);
        if (! is_writeable($tmpdir)) {
            trigger_error
                (sprintf(_("%s is not writable."), $tmpdir)
                 . "\n"
                 . _("Users will not be able to sign in.")
                 , E_USER_NOTICE);
        }
        else
            ini_set('session.save_path', $tmpdir);
    }
}

function main () {
    if (!USE_DB_SESSION)
        validateSessionPath();

    global $request;
    if ((DEBUG & _DEBUG_APD) and extension_loaded("apd"))
        apd_set_session_trace(9);

    // Postpone warnings
    global $ErrorManager;
    if (defined('E_STRICT')) // and (E_ALL & E_STRICT)) // strict php5?
        $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE|E_USER_WARNING|E_WARNING|E_STRICT);
    else
        $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE|E_USER_WARNING|E_WARNING);
    $request = new WikiRequest();

    $action = $request->getArg('action');
    if (substr($action, 0, 3) != 'zip') {
    	if ($action == 'pdf')
    	    $ErrorManager->setPostponedErrorMask(-1); // everything
    	//else // reject postponing of warnings
        //    $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
    }

    /*
     * Allow for disabling of markup cache.
     * (Mostly for debugging ... hopefully.)
     *
     * See also <?plugin WikiAdminUtils action=purge-cache ?>
     */
    if (!defined('WIKIDB_NOCACHE_MARKUP') and $request->getArg('nocache'))
        define('WIKIDB_NOCACHE_MARKUP', $request->getArg('nocache'));
    
    // Initialize with system defaults in case user not logged in.
    // Should this go into constructor?
    $request->initializeTheme();

    $request->updateAuthAndPrefs();
    $request->initializeLang();
    
    //FIXME:
    //if ($user->is_authenticated())
    //  $LogEntry->user = $user->getId();

    // Memory optimization:
    // http://www.procata.com/blog/archives/2004/05/27/rephlux-and-php-memory-usage/
    // kill the global PEAR _PEAR_destructor_object_list
    if (!empty($_PEAR_destructor_object_list))
        $_PEAR_destructor_object_list = array();
    $request->possiblyDeflowerVirginWiki();
    
// hack! define proper actions for these.
if (defined('WIKI_XMLRPC') and WIKI_XMLRPC) return;
if (defined('WIKI_SOAP')   and WIKI_SOAP)   return;

    $validators = array('wikiname' => WIKI_NAME,
                        'args'     => hash($request->getArgs()),
                        'prefs'    => hash($request->getPrefs()));
    if (CACHE_CONTROL == 'STRICT') {
        $dbi = $request->getDbh();
        $timestamp = $dbi->getTimestamp();
        $validators['mtime'] = $timestamp;
        $validators['%mtime'] = (int)$timestamp;
    }
    // FIXME: we should try to generate strong validators when possible,
    // but for now, our validator is weak, since equal validators do not
    // indicate byte-level equality of content.  (Due to DEBUG timing output, etc...)
    //
    // (If DEBUG if off, this may be a strong validator, but I'm going
    // to go the paranoid route here pending further study and testing.)
    //
    $validators['%weak'] = true;
    $request->setValidators($validators);
   
    $request->handleAction();

    if (DEBUG and DEBUG & _DEBUG_INFO) phpinfo(INFO_VARIABLES);
    $request->finish();
}

//$x = error_reporting();  // DEBUG: why is it 1 here? should be E_ALL
if (defined('E_STRICT') and (E_ALL & E_STRICT)) // strict php5?
    error_reporting(E_ALL & ~E_STRICT); 	// exclude E_STRICT
else
    error_reporting(E_ALL); // php4
// don't run the main loop for special requests (test, getimg, xmlrpc, soap, ...)
if (!defined('PHPWIKI_NOMAIN') or !PHPWIKI_NOMAIN)
    main();


// $Log: not supported by cvs2svn $
// Revision 1.183  2004/10/14 19:23:58  rurban
// remove debugging prints
//
// Revision 1.182  2004/10/12 13:13:19  rurban
// php5 compatibility (5.0.1 ok)
//
// Revision 1.181  2004/10/07 16:08:58  rurban
// fixed broken FileUser session handling.
//   thanks to Arnaud Fontaine for detecting this.
// enable file user Administrator membership.
//
// Revision 1.180  2004/10/04 23:39:34  rurban
// just aesthetics
//
// Revision 1.179  2004/09/25 18:57:42  rurban
// better ACL error message: view not browse, change not setacl, ...
//
// Revision 1.178  2004/09/25 16:27:36  rurban
// better not allowed description: on global disallowed, and on missing pageperms
//
// Revision 1.177  2004/09/14 10:31:09  rurban
// exclude E_STRICT for php5: untested. I believe this must be set earlier because the parsing step is already strict, and this is called at run-time
//
// Revision 1.176  2004/08/05 17:33:22  rurban
// aesthetic typo
//
// Revision 1.175  2004/07/13 13:08:25  rurban
// fix PEAR memory waste issues
//
// Revision 1.174  2004/07/08 13:50:32  rurban
// various unit test fixes: print error backtrace on _DEBUG_TRACE; allusers fix; new PHPWIKI_NOMAIN constant for omitting the mainloop
//
// Revision 1.173  2004/07/05 12:57:54  rurban
// add mysql timeout
//
// Revision 1.172  2004/07/03 08:04:19  rurban
// fixed implicit PersonalPage login (e.g. on edit), fixed to check against create ACL on create, not edit
//
// Revision 1.171  2004/06/29 09:30:42  rurban
// force string hash
//
// Revision 1.170  2004/06/25 14:29:20  rurban
// WikiGroup refactoring:
//   global group attached to user, code for not_current user.
//   improved helpers for special groups (avoid double invocations)
// new experimental config option ENABLE_XHTML_XML (fails with IE, and document.write())
// fixed a XHTML validation error on userprefs.tmpl
//
// Revision 1.169  2004/06/20 14:42:54  rurban
// various php5 fixes (still broken at blockparser)
//
// Revision 1.168  2004/06/17 10:39:18  rurban
// fix reverse translation of possible actionpage
//
// Revision 1.167  2004/06/16 13:21:16  rurban
// stabilize on failing ldap queries or bind
//
// Revision 1.166  2004/06/15 09:15:52  rurban
// IMPORTANT: fixed passwd handling for passwords stored in prefs:
//   fix encrypted usage, actually store and retrieve them from db
//   fix bogologin with passwd set.
// fix php crashes with call-time pass-by-reference (references wrongly used
//   in declaration AND call). This affected mainly Apache2 and IIS.
//   (Thanks to John Cole to detect this!)
//
// Revision 1.165  2004/06/14 11:31:37  rurban
// renamed global $Theme to $WikiTheme (gforge nameclash)
// inherit PageList default options from PageList
//   default sortby=pagename
// use options in PageList_Selectable (limit, sortby, ...)
// added action revert, with button at action=diff
// added option regex to WikiAdminSearchReplace
//
// Revision 1.164  2004/06/13 13:54:25  rurban
// Catch fatals on the four dump calls (as file and zip, as html and mimified)
// FoafViewer: Check against external requirements, instead of fatal.
// Change output for xhtmldumps: using file:// urls to the local fs.
// Catch SOAP fatal by checking for GOOGLE_LICENSE_KEY
// Import GOOGLE_LICENSE_KEY and FORTUNE_DIR from config.ini.
//
// Revision 1.163  2004/06/13 11:35:32  rurban
// check for create action on action=edit not to fool PagePerm checks
//
// Revision 1.162  2004/06/08 10:05:11  rurban
// simplified admin action shortcuts
//
// Revision 1.161  2004/06/07 22:58:40  rurban
// simplified chown, setacl, dump actions
//
// Revision 1.160  2004/06/07 22:44:14  rurban
// added simplified chown, setacl actions
//
// Revision 1.159  2004/06/06 16:58:51  rurban
// added more required ActionPages for foreign languages
// install now english ActionPages if no localized are found. (again)
// fixed default anon user level to be 0, instead of -1
//   (wrong "required administrator to view this page"...)
//
// Revision 1.158  2004/06/04 20:32:53  rurban
// Several locale related improvements suggested by Pierrick Meignen
// LDAP fix by John Cole
// reanable admin check without ENABLE_PAGEPERM in the admin plugins
//
// Revision 1.157  2004/06/04 12:40:21  rurban
// Restrict valid usernames to prevent from attacks against external auth or compromise
// possible holes.
// Fix various WikiUser old issues with default IMAP,LDAP,POP3 configs. Removed these.
// Fxied more warnings
//
// Revision 1.156  2004/06/03 17:58:16  rurban
// support immediate LANG and THEME switch inside a session
//
// Revision 1.155  2004/06/03 10:18:19  rurban
// fix FileUser locking issues, new config ENABLE_PAGEPERM
//
// Revision 1.154  2004/06/02 18:01:46  rurban
// init global FileFinder to add proper include paths at startup
//   adds PHPWIKI_DIR if started from another dir, lib/pear also
// fix slashify for Windows
// fix USER_AUTH_POLICY=old, use only USER_AUTH_ORDER methods (besides HttpAuth)
//
// Revision 1.153  2004/06/01 15:28:00  rurban
// AdminUser only ADMIN_USER not member of Administrators
// some RateIt improvements by dfrankow
// edit_toolbar buttons
//
// Revision 1.152  2004/05/27 17:49:06  rurban
// renamed DB_Session to DbSession (in CVS also)
// added WikiDB->getParam and WikiDB->getAuthParam method to get rid of globals
// remove leading slash in error message
// added force_unlock parameter to File_Passwd (no return on stale locks)
// fixed adodb session AffectedRows
// added FileFinder helpers to unify local filenames and DATA_PATH names
// editpage.php: new edit toolbar javascript on ENABLE_EDIT_TOOLBAR
//
// Revision 1.151  2004/05/25 12:40:48  rurban
// trim the pagename
//
// Revision 1.150  2004/05/25 10:18:44  rurban
// Check for UTF-8 URLs; Internet Explorer produces these if you
// type non-ASCII chars in the URL bar or follow unescaped links.
// Fixes sf.net bug #953949
// src: languages/Language.php:checkTitleEncoding() from mediawiki
//
// Revision 1.149  2004/05/18 13:31:19  rurban
// hold warnings until headers are sent. new Error-style with collapsed output of repeated messages
//
// Revision 1.148  2004/05/17 17:43:29  rurban
// CGI: no PATH_INFO fix
//
// Revision 1.147  2004/05/15 19:48:33  rurban
// fix some too loose PagePerms for signed, but not authenticated users
//  (admin, owner, creator)
// no double login page header, better login msg.
// moved action_pdf to lib/pdf.php
//
// Revision 1.146  2004/05/15 18:31:01  rurban
// some action=pdf Request fixes: With MSIE it works now. Now the work with the page formatting begins.
//
// Revision 1.145  2004/05/12 10:49:55  rurban
// require_once fix for those libs which are loaded before FileFinder and
//   its automatic include_path fix, and where require_once doesn't grok
//   dirname(__FILE__) != './lib'
// upgrade fix with PearDB
// navbar.tmpl: remove spaces for IE &nbsp; button alignment
//
// Revision 1.144  2004/05/06 19:26:16  rurban
// improve stability, trying to find the InlineParser endless loop on sf.net
//
// remove end-of-zip comments to fix sf.net bug #777278 and probably #859628
//
// Revision 1.143  2004/05/06 17:30:38  rurban
// CategoryGroup: oops, dos2unix eol
// improved phpwiki_version:
//   pre -= .0001 (1.3.10pre: 1030.099)
//   -p1 += .001 (1.3.9-p1: 1030.091)
// improved InstallTable for mysql and generic SQL versions and all newer tables so far.
// abstracted more ADODB/PearDB methods for action=upgrade stuff:
//   backend->backendType(), backend->database(),
//   backend->listOfFields(),
//   backend->listOfTables(),
//
// Revision 1.142  2004/05/04 22:34:25  rurban
// more pdf support
//
// Revision 1.141  2004/05/03 13:16:47  rurban
// fixed UserPreferences update, esp for boolean and int
//
// Revision 1.140  2004/05/02 21:26:38  rurban
// limit user session data (HomePageHandle and auth_dbi have to invalidated anyway)
//   because they will not survive db sessions, if too large.
// extended action=upgrade
// some WikiTranslation button work
// revert WIKIAUTH_UNOBTAINABLE (need it for main.php)
// some temp. session debug statements
//
// Revision 1.139  2004/05/02 15:10:07  rurban
// new finally reliable way to detect if /index.php is called directly
//   and if to include lib/main.php
// new global AllActionPages
// SetupWiki now loads all mandatory pages: HOME_PAGE, action pages, and warns if not.
// WikiTranslation what=buttons for Carsten to create the missing MacOSX buttons
// PageGroupTestOne => subpages
// renamed PhpWikiRss to PhpWikiRecentChanges
// more docs, default configs, ...
//
// Revision 1.138  2004/05/01 15:59:29  rurban
// more php-4.0.6 compatibility: superglobals
//
// Revision 1.137  2004/04/29 19:39:44  rurban
// special support for formatted plugins (one-liners)
//   like <small><plugin BlaBla ></small>
// iter->asArray() helper for PopularNearby
// db_session for older php's (no &func() allowed)
//
// Revision 1.136  2004/04/29 17:18:19  zorloc
// Fixes permission failure issues.  With PagePermissions and Disabled Actions when user did not have permission WIKIAUTH_FORBIDDEN was returned.  In WikiUser this was ok because WIKIAUTH_FORBIDDEN had a value of 11 -- thus no user could perform that action.  But WikiUserNew has a WIKIAUTH_FORBIDDEN value of -1 -- thus a user without sufficent permission to do anything.  The solution is a new high value permission level (WIKIAUTH_UNOBTAINABLE) to be the default level for access failure.
//
// Revision 1.135  2004/04/26 12:15:01  rurban
// check default config values
//
// Revision 1.134  2004/04/23 06:46:37  zorloc
// Leave DB connection open when USE_DB_SESSION is true so that session info can be written to the DB.
//
// Revision 1.133  2004/04/20 18:10:31  rurban
// config refactoring:
//   FileFinder is needed for WikiFarm scripts calling index.php
//   config run-time calls moved to lib/IniConfig.php:fix_configs()
//   added PHPWIKI_DIR smart-detection code (Theme finder)
//   moved FileFind to lib/FileFinder.php
//   cleaned lib/config.php
//
// Revision 1.132  2004/04/19 21:51:41  rurban
// php5 compatibility: it works!
//
// Revision 1.131  2004/04/19 18:27:45  rurban
// Prevent from some PHP5 warnings (ref args, no :: object init)
//   php5 runs now through, just one wrong XmlElement object init missing
// Removed unneccesary UpgradeUser lines
// Changed WikiLink to omit version if current (RecentChanges)
//
// Revision 1.130  2004/04/18 00:25:53  rurban
// allow "0" pagename
//
// Revision 1.129  2004/04/07 23:13:19  rurban
// fixed pear/File_Passwd for Windows
// fixed FilePassUser sessions (filehandle revive) and password update
//
// Revision 1.128  2004/04/02 15:06:55  rurban
// fixed a nasty ADODB_mysql session update bug
// improved UserPreferences layout (tabled hints)
// fixed UserPreferences auth handling
// improved auth stability
// improved old cookie handling: fixed deletion of old cookies with paths
//
// Revision 1.127  2004/03/25 17:00:31  rurban
// more code to convert old-style pref array to new hash
//
// Revision 1.126  2004/03/24 19:39:03  rurban
// php5 workaround code (plus some interim debugging code in XmlElement)
//   php5 doesn't work yet with the current XmlElement class constructors,
//   WikiUserNew does work better than php4.
// rewrote WikiUserNew user upgrading to ease php5 update
// fixed pref handling in WikiUserNew
// added Email Notification
// added simple Email verification
// removed emailVerify userpref subclass: just a email property
// changed pref binary storage layout: numarray => hash of non default values
// print optimize message only if really done.
// forced new cookie policy: delete pref cookies, use only WIKI_ID as plain string.
//   prefs should be stored in db or homepage, besides the current session.
//
// Revision 1.125  2004/03/14 16:30:52  rurban
// db-handle session revivification, dba fixes
//
// Revision 1.124  2004/03/12 15:48:07  rurban
// fixed explodePageList: wrong sortby argument order in UnfoldSubpages
// simplified lib/stdlib.php:explodePageList
//
// Revision 1.123  2004/03/10 15:41:27  rurban
// use default pref mysql table
//
// Revision 1.122  2004/03/08 18:17:09  rurban
// added more WikiGroup::getMembersOf methods, esp. for special groups
// fixed $LDAP_SET_OPTIONS
// fixed _AuthInfo group methods
//
// Revision 1.121  2004/03/01 13:48:45  rurban
// rename fix
// p[] consistency fix
//
// Revision 1.120  2004/03/01 10:22:41  rurban
// initializeTheme optimize
//
// Revision 1.119  2004/02/26 20:45:06  rurban
// check for ALLOW_ANON_USER = false
//
// Revision 1.118  2004/02/26 01:32:03  rurban
// fixed session login with old WikiUser object. strangely, the errormask gets corruoted to 1, Pear???
//
// Revision 1.117  2004/02/24 17:19:37  rurban
// debugging helpers only
//
// Revision 1.116  2004/02/24 15:17:14  rurban
// improved auth errors with individual pages. the fact that you may not browse a certain admin page does not conclude that you may not browse the whole wiki. renamed browse => view
//
// Revision 1.115  2004/02/15 21:34:37  rurban
// PageList enhanced and improved.
// fixed new WikiAdmin... plugins
// editpage, Theme with exp. htmlarea framework
//   (htmlarea yet committed, this is really questionable)
// WikiUser... code with better session handling for prefs
// enhanced UserPreferences (again)
// RecentChanges for show_deleted: how should pages be deleted then?
//
// Revision 1.114  2004/02/15 17:30:13  rurban
// workaround for lost db connnection handle on session restauration (->_auth_dbi)
// fixed getPreferences() (esp. from sessions)
// fixed setPreferences() (update and set),
// fixed AdoDb DB statements,
// update prefs only at UserPreferences POST (for testing)
// unified db prefs methods (but in external pref classes yet)
//
// Revision 1.113  2004/02/12 13:05:49  rurban
// Rename functional for PearDB backend
// some other minor changes
// SiteMap comes with a not yet functional feature request: includepages (tbd)
//
// Revision 1.112  2004/02/09 03:58:12  rurban
// for now default DB_SESSION to false
// PagePerm:
//   * not existing perms will now query the parent, and not
//     return the default perm
//   * added pagePermissions func which returns the object per page
//   * added getAccessDescription
// WikiUserNew:
//   * added global ->prepare (not yet used) with smart user/pref/member table prefixing.
//   * force init of authdbh in the 2 db classes
// main:
//   * fixed session handling (not triple auth request anymore)
//   * don't store cookie prefs with sessions
// stdlib: global obj2hash helper from _AuthInfo, also needed for PagePerm
//
// Revision 1.111  2004/02/07 10:41:25  rurban
// fixed auth from session (still double code but works)
// fixed GroupDB
// fixed DbPassUser upgrade and policy=old
// added GroupLdap
//
// Revision 1.110  2004/02/03 09:45:39  rurban
// LDAP cleanup, start of new Pref classes
//
// Revision 1.109  2004/01/30 19:57:58  rurban
// fixed DBAuthParams['pref_select']: wrong _auth_dbi object used.
//
// Revision 1.108  2004/01/28 14:34:14  rurban
// session table takes the common prefix
// + various minor stuff
// reallow password changing
//
// Revision 1.107  2004/01/27 23:23:39  rurban
// renamed ->Username => _userid for consistency
// renamed mayCheckPassword => mayCheckPass
// fixed recursion problem in WikiUserNew
// fixed bogo login (but not quite 100% ready yet, password storage)
//
// Revision 1.106  2004/01/26 09:17:49  rurban
// * changed stored pref representation as before.
//   the array of objects is 1) bigger and 2)
//   less portable. If we would import packed pref
//   objects and the object definition was changed, PHP would fail.
//   This doesn't happen with an simple array of non-default values.
// * use $prefs->retrieve and $prefs->store methods, where retrieve
//   understands the interim format of array of objects also.
// * simplified $prefs->get() and fixed $prefs->set()
// * added $user->_userid and class '_WikiUser' portability functions
// * fixed $user object ->_level upgrading, mostly using sessions.
//   this fixes yesterdays problems with loosing authorization level.
// * fixed WikiUserNew::checkPass to return the _level
// * fixed WikiUserNew::isSignedIn
// * added explodePageList to class PageList, support sortby arg
// * fixed UserPreferences for WikiUserNew
// * fixed WikiPlugin for empty defaults array
// * UnfoldSubpages: added pagename arg, renamed pages arg,
//   removed sort arg, support sortby arg
//
// Revision 1.105  2004/01/25 03:57:15  rurban
// WikiUserNew support (temp. ENABLE_USER_NEW constant)
//
// Revision 1.104  2003/12/26 06:41:16  carstenklapp
// Bugfix: Try to defer OS errors about session.save_path and ACCESS_LOG,
// so they don't prevent IE from partially (or not at all) rendering the
// page. This should help a little for the IE user who encounters trouble
// when setting up a new PhpWiki for the first time.
//
// Revision 1.103  2003/12/02 00:10:00  carstenklapp
// Bugfix: Ongoing work to untangle UserPreferences/WikiUser/request code
// mess: UserPreferences should take effect immediately now upon signing
// in.
//
// Revision 1.102  2003/11/25 22:55:32  carstenklapp
// Localization bugfix: For wikis where English is not the default system
// language, make sure that the authority error message (i.e. "You must
// sign in to edit pages in this wiki" etc.) is displayed in the wiki's
// default language. Previously it would always display in English.
// (Added call to update_locale() before displaying any messages prior to
// the login prompt.)
//
// Revision 1.101  2003/11/25 21:49:44  carstenklapp
// Bugfix: For a non-english wiki or when the user's preference is not
// english, the wiki would always use the english ActionPage first if it
// was present rather than the appropriate localised variant. (PhpWikis
// running only in english or Wikis running ONLY without any english
// ActionPages would not notice this bug, only when both english and
// localised ActionPages were in the DB.) Now we check for the localised
// variant first.
//
// Revision 1.100  2003/11/18 16:54:18  carstenklapp
// Reformatting only: Tabs to spaces, added rcs log.
//


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
