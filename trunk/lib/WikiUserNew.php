<?php //-*-php-*-
rcs_id('$Id: WikiUserNew.php,v 1.109 2004-10-07 16:08:58 rurban Exp $');
/* Copyright (C) 2004 $ThePhpWikiProgrammingTeam
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
 * This is a complete OOP rewrite of the old WikiUser code with various
 * configurable external authentication methods.
 *
 * There's only one entry point, the function WikiUser which returns 
 * a WikiUser object, which contains the name, authlevel and user's preferences.
 * This object might get upgraded during the login step and later also.
 * There exist three preferences storage methods: cookie, homepage and db,
 * and multiple password checking methods.
 * See index.php for $USER_AUTH_ORDER[] and USER_AUTH_POLICY if 
 * ALLOW_USER_PASSWORDS is defined.
 *
 * Each user object must define the two preferences methods 
 *  getPreferences(), setPreferences(), 
 * and the following 1-4 auth methods
 *  checkPass()  must be defined by all classes,
 *  userExists() only if USER_AUTH_POLICY'=='strict' 
 *  mayChangePass()  only if the password is storable.
 *  storePass()  only if the password is storable.
 *
 * WikiUser() given no name, returns an _AnonUser (anonymous user)
 * object, who may or may not have a cookie. 
 * However, if the there's a cookie with the userid or a session, 
 * the user is upgraded to the matching user object.
 * Given a user name, returns a _BogoUser object, who may or may not 
 * have a cookie and/or PersonalPage, one of the various _PassUser objects 
 * or an _AdminUser object.
 * BTW: A BogoUser is a userid (loginname) as valid WikiWord, who might 
 * have stored a password or not. If so, his account is secure, if not
 * anybody can use it, because the username is visible e.g. in RecentChanges.
 *
 * Takes care of passwords, all preference loading/storing in the
 * user's page and any cookies. lib/main.php will query the user object to
 * verify the password as appropriate.
 *
 * @author: Reini Urban (the tricky parts), 
 *          Carsten Klapp (started rolling the ball)
 *
 * Random architectural notes, sorted by date:
 * 2004-01-25 rurban
 * Test it by defining ENABLE_USER_NEW in config/config.ini
 * 1) Now a ForbiddenUser is returned instead of false.
 * 2) Previously ALLOW_ANON_USER = false meant that anon users cannot edit, 
 *    but may browse. Now with ALLOW_ANON_USER = false he may not browse, 
 *    which is needed to disable browse PagePermissions.
 *    I added now ALLOW_ANON_EDIT = true to makes things clear. 
 *    (which replaces REQUIRE_SIGNIN_BEFORE_EDIT)
 * 2004-02-27 rurban:
 * 3) Removed pear prepare. Performance hog, and using integers as 
 *    handler doesn't help. Do simple sprintf as with adodb. And a prepare
 *    in the object init is no advantage, because in the init loop a lot of 
 *    objects are tried, but not used.
 * 4) Already gotten prefs are passed to the next object to avoid 
 *    duplicate getPreferences() calls.
 * 2004-03-18 rurban
 * 5) Major php-5 problem: $this re-assignment is disallowed by the parser
 *    So we cannot just discrimate with 
 *      if (!check_php_version(5))
 *          $this = $user;
 *    A /php5-patch.php is provided, which patches the src automatically 
 *    for php4 and php5. Default is php4.
 *    Update: not needed anymore. we use eval to fool the load-time syntax checker.
 * 2004-03-24 rurban
 * 6) enforced new cookie policy: prefs don't get stored in cookies
 *    anymore, only in homepage and/or database, but always in the 
 *    current session. old pref cookies will get deleted.
 * 2004-04-04 rurban
 * 7) Certain themes should be able to extend the predefined list 
 *    of preferences. Display/editing is done in the theme specific userprefs.tmpl,
 *    but storage must be extended to the Get/SetPreferences methods.
 *    <theme>/themeinfo.php must provide CustomUserPreferences:
 *      A list of name => _UserPreference class pairs.
 */

define('WIKIAUTH_FORBIDDEN', -1); // Completely not allowed.
define('WIKIAUTH_ANON', 0);       // Not signed in.
define('WIKIAUTH_BOGO', 1);       // Any valid WikiWord is enough.
define('WIKIAUTH_USER', 2);       // Bogo user with a password.
define('WIKIAUTH_ADMIN', 10);     // UserName == ADMIN_USER.
define('WIKIAUTH_UNOBTAINABLE', 100);  // Permissions that no user can achieve

if (!defined('COOKIE_EXPIRATION_DAYS')) define('COOKIE_EXPIRATION_DAYS', 365);
if (!defined('COOKIE_DOMAIN'))          define('COOKIE_DOMAIN', '/');

if (!defined('EDITWIDTH_MIN_COLS'))     define('EDITWIDTH_MIN_COLS',     30);
if (!defined('EDITWIDTH_MAX_COLS'))     define('EDITWIDTH_MAX_COLS',    150);
if (!defined('EDITWIDTH_DEFAULT_COLS')) define('EDITWIDTH_DEFAULT_COLS', 80);

if (!defined('EDITHEIGHT_MIN_ROWS'))     define('EDITHEIGHT_MIN_ROWS',      5);
if (!defined('EDITHEIGHT_MAX_ROWS'))     define('EDITHEIGHT_MAX_ROWS',     80);
if (!defined('EDITHEIGHT_DEFAULT_ROWS')) define('EDITHEIGHT_DEFAULT_ROWS', 22);

define('TIMEOFFSET_MIN_HOURS', -26);
define('TIMEOFFSET_MAX_HOURS',  26);
if (!defined('TIMEOFFSET_DEFAULT_HOURS')) define('TIMEOFFSET_DEFAULT_HOURS', 0);

/**
 * There are be the following constants in config/config.ini to 
 * establish login parameters:
 *
 * ALLOW_ANON_USER         default true
 * ALLOW_ANON_EDIT         default true
 * ALLOW_BOGO_LOGIN        default true
 * ALLOW_USER_PASSWORDS    default true
 * PASSWORD_LENGTH_MINIMUM default 6 ?
 *
 * To require user passwords for editing:
 * ALLOW_ANON_USER  = true
 * ALLOW_ANON_EDIT  = false   (before named REQUIRE_SIGNIN_BEFORE_EDIT)
 * ALLOW_BOGO_LOGIN = false
 * ALLOW_USER_PASSWORDS = true
 *
 * To establish a COMPLETELY private wiki, such as an internal
 * corporate one:
 * ALLOW_ANON_USER = false
 * (and probably require user passwords as described above). In this
 * case the user will be prompted to login immediately upon accessing
 * any page.
 *
 * There are other possible combinations, but the typical wiki (such
 * as http://PhpWiki.sf.net/phpwiki) would usually just leave all four 
 * enabled.
 *
 */

// The last object in the row is the bad guy...
if (!is_array($USER_AUTH_ORDER))
    $USER_AUTH_ORDER = array("Forbidden");
else
    $USER_AUTH_ORDER[] = "Forbidden";

// Local convenience functions.
function _isAnonUserAllowed() {
    return (defined('ALLOW_ANON_USER') && ALLOW_ANON_USER);
}
function _isBogoUserAllowed() {
    return (defined('ALLOW_BOGO_LOGIN') && ALLOW_BOGO_LOGIN);
}
function _isUserPasswordsAllowed() {
    return (defined('ALLOW_USER_PASSWORDS') && ALLOW_USER_PASSWORDS);
}

// Possibly upgrade userobject functions.
function _determineAdminUserOrOtherUser($UserName) {
    // Sanity check. User name is a condition of the definition of the
    // _AdminUser, _BogoUser and _passuser.
    if (!$UserName)
        return $GLOBALS['ForbiddenUser'];

    //FIXME: check admin membership later at checkPass. now we cannot raise the level.
    //$group = &WikiGroup::getGroup($GLOBALS['request']);
    if ($UserName == ADMIN_USER)
        return new _AdminUser($UserName);
    /* elseif ($group->isMember(GROUP_ADMIN)) {
        return _determineBogoUserOrPassUser($UserName);
    }
    */
    else
        return _determineBogoUserOrPassUser($UserName);
}

function _determineBogoUserOrPassUser($UserName) {
    global $ForbiddenUser;

    // Sanity check. User name is a condition of the definition of
    // _BogoUser and _PassUser.
    if (!$UserName)
        return $ForbiddenUser;

    // Check for password and possibly upgrade user object.
    // $_BogoUser = new _BogoUser($UserName);
    if (_isBogoUserAllowed()) {
        $_BogoUser = new _BogoLoginPassUser($UserName);
        if ($_BogoUser->userExists())
            return $_BogoUser;
    }
    if (_isUserPasswordsAllowed()) {
    	// PassUsers override BogoUsers if a password is stored
        if (isset($_BogoUser) and isset($_BogoUser->_prefs) and $_BogoUser->_prefs->get('passwd'))
            return new _PassUser($UserName,$_BogoUser->_prefs);
        else { 
            $_PassUser = new _PassUser($UserName,isset($_BogoUser) ? $_BogoUser->_prefs : false);
            if ($_PassUser->userExists())
                return $_PassUser;
        }
    }
    // No Bogo- or PassUser exists, or
    // passwords are not allowed, and bogo is disallowed too.
    // (Only the admin can sign in).
    return $ForbiddenUser;
}

/**
 * Primary WikiUser function, called by lib/main.php.
 * 
 * This determines the user's type and returns an appropriate user
 * object. lib/main.php then querys the resultant object for password
 * validity as necessary.
 *
 * If an _AnonUser object is returned, the user may only browse pages
 * (and save prefs in a cookie).
 *
 * To disable access but provide prefs the global $ForbiddenUser class 
 * is returned. (was previously false)
 * 
 */
function WikiUser ($UserName = '') {
    global $ForbiddenUser;

    //Maybe: Check sessionvar for username & save username into
    //sessionvar (may be more appropriate to do this in lib/main.php).
    if ($UserName) {
        $ForbiddenUser = new _ForbiddenUser($UserName);
        // Found a user name.
        return _determineAdminUserOrOtherUser($UserName);
    }
    elseif (!empty($_SESSION['userid'])) {
        // Found a user name.
        $ForbiddenUser = new _ForbiddenUser($_SESSION['userid']);
        return _determineAdminUserOrOtherUser($_SESSION['userid']);
    }
    else {
        // Check for autologin pref in cookie and possibly upgrade
        // user object to another type.
        $_AnonUser = new _AnonUser();
        if ($UserName = $_AnonUser->_userid && $_AnonUser->_prefs->get('autologin')) {
            // Found a user name.
            $ForbiddenUser = new _ForbiddenUser($UserName);
            return _determineAdminUserOrOtherUser($UserName);
        }
        else {
            $ForbiddenUser = new _ForbiddenUser();
            if (_isAnonUserAllowed())
                return $_AnonUser;
            return $ForbiddenUser; // User must sign in to browse pages.
        }
        return $ForbiddenUser;     // User must sign in with a password.
    }
    /*
    trigger_error("DEBUG: Note: End of function reached in WikiUser." . " "
                  . "Unexpectedly, an appropriate user class could not be determined.");
    return $ForbiddenUser; // Failsafe.
    */
}

/**
 * WikiUser.php use the name 'WikiUser'
 */
function WikiUserClassname() {
    return '_WikiUser';
}


/**
 * Upgrade olduser by copying properties from user to olduser.
 * We are not sure yet, for which php's a simple $this = $user works reliably,
 * (on php4 it works ok, on php5 it's currently disallowed on the parser level)
 * that's why try it the hard way.
 */
function UpgradeUser ($olduser, $user) {
    if (isa($user,'_WikiUser') and isa($olduser,'_WikiUser')) {
        // populate the upgraded class $olduser with the values from the new user object
        //only _auth_level, _current_method, _current_index,
        if (!empty($user->_level) and 
            $user->_level > $olduser->_level)
            $olduser->_level = $user->_level;
        if (!empty($user->_current_index) and
            $user->_current_index > $olduser->_current_index) {
            $olduser->_current_index = $user->_current_index;
            $olduser->_current_method = $user->_current_method;
        }
        if (!empty($user->_authmethod))
            $olduser->_authmethod = $user->_authmethod;
        /*
        foreach (get_object_vars($user) as $k => $v) {
            if (!empty($v)) $olduser->$k = $v;	
        }
        */
        $olduser->hasHomePage(); // revive db handle, because these don't survive sessions
        //$GLOBALS['request']->_user = $olduser;
        return $olduser;
    } else {
        return false;
    }
}

/**
 * Probably not needed, since we use the various user objects methods so far.
 * Anyway, here it is, looping through all available objects.
 */
function UserExists ($UserName) {
    global $request;
    if (!($user = $request->getUser()))
        $user = WikiUser($UserName);
    if (!$user) 
        return false;
    if ($user->userExists($UserName)) {
        $request->_user = $user;
        return true;
    }
    if (isa($user,'_BogoUser'))
        $user = new _PassUser($UserName,$user->_prefs);
    $class = $user->nextClass();
    if ($user = new $class($UserName,$user->_prefs)) {
        return $user->userExists($UserName);
    }
    $request->_user = $GLOBALS['ForbiddenUser'];
    return false;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/** 
 * Base WikiUser class.
 */
class _WikiUser
{
     var $_userid = '';
     var $_level = WIKIAUTH_ANON;
     var $_prefs = false;
     var $_HomePagehandle = false;

    // constructor
    function _WikiUser($UserName='', $prefs=false) {

        $this->_userid = $UserName;
        $this->_HomePagehandle = false;
        if ($UserName) {
            $this->hasHomePage();
        }
        if (empty($this->_prefs)) {
            if ($prefs) $this->_prefs = $prefs;
            else $this->getPreferences();
        }
    }

    function UserName() {
        if (!empty($this->_userid))
            return $this->_userid;
    }

    function getPreferences() {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to load prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    function setPreferences($prefs, $id_only) {
        trigger_error("DEBUG: Note: undefined _WikiUser class trying to save prefs." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    function userExists() {
        return $this->hasHomePage();
    }

    function checkPass($submitted_password) {
        // By definition, an undefined user class cannot sign in.
        trigger_error("DEBUG: Warning: undefined _WikiUser class trying to sign in." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

    // returns page_handle to user's home page or false if none
    function hasHomePage() {
        if ($this->_userid) {
            if (!empty($this->_HomePagehandle) and is_object($this->_HomePagehandle)) {
                return $this->_HomePagehandle->exists();
            }
            else {
                // check db again (maybe someone else created it since
                // we logged in.)
                global $request;
                $this->_HomePagehandle = $request->getPage($this->_userid);
                return $this->_HomePagehandle->exists();
            }
        }
        // nope
        return false;
    }

    // innocent helper: case-insensitive position in _auth_methods
    function array_position ($string, $array) {
        $string = strtolower($string);
        for ($found = 0; $found < count($array); $found++) {
            if (strtolower($array[$found]) == $string)
                return $found;
        }
        return false;
    }

    function nextAuthMethodIndex() {
        if (empty($this->_auth_methods)) 
            $this->_auth_methods = $GLOBALS['USER_AUTH_ORDER'];
        if (empty($this->_current_index)) {
            if (strtolower(get_class($this)) != '_passuser') {
            	$this->_current_method = substr(get_class($this),1,-8);
                $this->_current_index = $this->array_position($this->_current_method,
                                                              $this->_auth_methods);
            } else {
            	$this->_current_index = -1;
            }
        }
        $this->_current_index++;
        if ($this->_current_index >= count($this->_auth_methods))
            return false;
        $this->_current_method = $this->_auth_methods[$this->_current_index];
        return $this->_current_index;
    }

    function AuthMethod($index = false) {
        return $this->_auth_methods[ $index === false ? 0 : $index];
    }

    // upgrade the user object
    function nextClass() {
        if (($next = $this->nextAuthMethodIndex()) !== false) {
            $method = $this->AuthMethod($next);
            return "_".$method."PassUser";
            /*          
            if ($user = new $class($this->_userid)) {
                // prevent from endless recursion.
                //$user->_current_method = $this->_current_method;
                //$user->_current_index = $this->_current_index;
                $user = UpgradeUser($user, $this);
            }
            return $user;
            */
        }
        return "_ForbiddenPassUser";
    }

    //Fixme: for _HttpAuthPassUser
    function PrintLoginForm (&$request, $args, $fail_message = false,
                             $seperate_page = false) {
        include_once('lib/Template.php');
        // Call update_locale in case the system's default language is not 'en'.
        // (We have no user pref for lang at this point yet, no one is logged in.)
        if ($GLOBALS['LANG'] != DEFAULT_LANGUAGE)
            update_locale(DEFAULT_LANGUAGE);
        $userid = $this->_userid;
        $require_level = 0;
        extract($args); // fixme

        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        $pagename = $request->getArg('pagename');
        $nocache = 1;
        $login = Template('login',
                          compact('pagename', 'userid', 'require_level',
                                  'fail_message', 'pass_required', 'nocache'));
        // check if the html template was already processed
        $seperate_page = $seperate_page ? true : !alreadyTemplateProcessed('html');
        if ($seperate_page) {
            $page = $request->getPage($pagename);
            $revision = $page->getCurrentRevision();
            return GeneratePage($login,_("Sign In"),$revision);
        } else {
            return $login->printExpansion();
        }
    }

    /** Signed in but not password checked or empty password.
     */
    function isSignedIn() {
        return (isa($this,'_BogoUser') or isa($this,'_PassUser'));
    }

    /** This is password checked for sure.
     */
    function isAuthenticated () {
        //return isa($this,'_PassUser');
        //return isa($this,'_BogoUser') || isa($this,'_PassUser');
        return $this->_level >= WIKIAUTH_BOGO;
    }

    function isAdmin () {
        static $group; 
        if ($this->_level == WIKIAUTH_ADMIN) return true;

        if (!$group) $group = &$GLOBALS['request']->getGroup();
        return ($this->_level > WIKIAUTH_BOGO and $group->isMember(GROUP_ADMIN));
    }

    /** Name or IP for a signed user. UserName could come from a cookie e.g.
     */
    function getId () {
        return ( $this->UserName()
                 ? $this->UserName()
                 : $GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
    }

    /** Name for an authenticated user. No IP here.
     */
    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : ''); //$GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function hasAuthority ($require_level) {
        return $this->_level >= $require_level;
    }

    function isValidName ($userid = false) {
        if (!$userid)
            $userid = $this->_userid;
        return preg_match("/^[\w\.@\-]+$/",$userid) and strlen($userid) < 32;
    }

    /**
     * Called on an auth_args POST request, such as login, logout or signin.
     * TODO: Check BogoLogin users with empty password. (self-signed users)
     */
    function AuthCheck ($postargs) {
        // Normalize args, and extract.
        $keys = array('userid', 'passwd', 'require_level', 'login', 'logout',
                      'cancel');
        foreach ($keys as $key)
            $args[$key] = isset($postargs[$key]) ? $postargs[$key] : false;
        extract($args);
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int)$require_level));

        if ($logout) { // Log out
            $GLOBALS['request']->_user = new _AnonUser();
            $GLOBALS['request']->_user->_userid = '';
            $GLOBALS['request']->_user->_level = WIKIAUTH_ANON;
            return $GLOBALS['request']->_user; 
        } elseif ($cancel)
            return false;        // User hit cancel button.
        elseif (!$login && !$userid)
            return false;       // Nothing to do?

        if (!$this->isValidName($userid))
            return _("Invalid username.");;

        $authlevel = $this->checkPass($passwd === false ? '' : $passwd);
        if ($authlevel <= 0) { // anon or forbidden
            if ($passwd)	
                return _("Invalid password.");
            else
                return _("Invalid password or userid.");
        } elseif ($authlevel < $require_level) { // auth ok, but not enough 
            if (!empty($this->_current_method) and strtolower(get_class($this)) == '_passuser') 
            {
                // upgrade class
                $class = "_" . $this->_current_method . "PassUser";
                $user = new $class($userid,$this->_prefs);
                if (!check_php_version(5))
                    eval("\$this = \$user;");
                // /*PHP5 patch*/$this = $user;
                $this->_level = $authlevel;
                return $user;
            }
            $this->_userid = $userid;
            $this->_level = $authlevel;
            return _("Insufficient permissions.");
        }

        // Successful login.
        //$user = $GLOBALS['request']->_user;
        if (!empty($this->_current_method) and 
            strtolower(get_class($this)) == '_passuser') 
        {
            // upgrade class
            $class = "_" . $this->_current_method . "PassUser";
            $user = new $class($userid,$this->_prefs);
            if (!check_php_version(5))
                eval("\$this = \$user;");
            // /*PHP5 patch*/$this = $user;
            $user->_level = $authlevel;
            return $user;
        }
        $this->_userid = $userid;
        $this->_level = $authlevel;
        return $this;
    }

}

/**
 * Not authenticated in user, but he may be signed in. Basicly with view access only.
 * prefs are stored in cookies, but only the userid.
 */
class _AnonUser
extends _WikiUser
{
    var $_level = WIKIAUTH_ANON; 	// var in php-5.0.0RC1 deprecated

    /** Anon only gets to load and save prefs in a cookie, that's it.
     */
    function getPreferences() {
        global $request;

        if (empty($this->_prefs))
            $this->_prefs = new UserPreferences;
        $UserName = $this->UserName();

        // Try to read deprecated 1.3.x style cookies
        if ($cookie = $request->cookies->get_old(WIKI_NAME)) {
            if (! $unboxedcookie = $this->_prefs->retrieve($cookie)) {
                trigger_error(_("Empty Preferences or format of UserPreferences cookie not recognised.") 
                              . "\n"
                              . sprintf("%s='%s'", WIKI_NAME, $cookie)
                              . "\n"
                              . _("Default preferences will be used."),
                              E_USER_NOTICE);
            }
            /**
             * Only set if it matches the UserName who is
             * signing in or if this really is an Anon login (no
             * username). (Remember, _BogoUser and higher inherit this
             * function too!).
             */
            if (! $UserName || $UserName == @$unboxedcookie['userid']) {
                $updated = $this->_prefs->updatePrefs($unboxedcookie);
                //$this->_prefs = new UserPreferences($unboxedcookie);
                $UserName = @$unboxedcookie['userid'];
                if (is_string($UserName) and (substr($UserName,0,2) != 's:'))
                    $this->_userid = $UserName;
                else 
                    $UserName = false;    
            }
            // v1.3.8 policy: don't set PhpWiki cookies, only plaintext WIKI_ID cookies
            if (!headers_sent())
                $request->deleteCookieVar(WIKI_NAME);
        }
        // Try to read deprecated 1.3.4 style cookies
        if (! $UserName and ($cookie = $request->cookies->get_old("WIKI_PREF2"))) {
            if (! $unboxedcookie = $this->_prefs->retrieve($cookie)) {
                if (! $UserName || $UserName == $unboxedcookie['userid']) {
                    $updated = $this->_prefs->updatePrefs($unboxedcookie);
                    //$this->_prefs = new UserPreferences($unboxedcookie);
                    $UserName = $unboxedcookie['userid'];
                    if (is_string($UserName) and (substr($UserName,0,2) != 's:'))
                        $this->_userid = $UserName;
                    else 
                        $UserName = false;    
                }
                if (!headers_sent())
                    $request->deleteCookieVar("WIKI_PREF2");
            }
        }
        if (! $UserName ) {
            // Try reading userid from old PhpWiki cookie formats:
            if ($cookie = $request->cookies->get_old('WIKI_ID')) {
                if (is_string($cookie) and (substr($cookie,0,2) != 's:'))
                    $UserName = $cookie;
                elseif (is_array($cookie) and !empty($cookie['userid']))
                    $UserName = $cookie['userid'];
            }
            if (! $UserName and !headers_sent())
                $request->deleteCookieVar("WIKI_ID");
            else
                $this->_userid = $UserName;
        }

        // initializeTheme() needs at least an empty object
        /*
         if (empty($this->_prefs))
            $this->_prefs = new UserPreferences;
        */
        return $this->_prefs;
    }

    /** _AnonUser::setPreferences(): Save prefs in a cookie and session and update all global vars
     *
     * Allow for multiple wikis in same domain. Encode only the
     * _prefs array of the UserPreference object. Ideally the
     * prefs array should just be imploded into a single string or
     * something so it is completely human readable by the end
     * user. In that case stricter error checking will be needed
     * when loading the cookie.
     */
    function setPreferences($prefs, $id_only=false) {
        if (!is_object($prefs)) {
            if (is_object($this->_prefs)) {
                $updated = $this->_prefs->updatePrefs($prefs);
                $prefs =& $this->_prefs;
            } else {
                // update the prefs values from scratch. This could leed to unnecessary
                // side-effects: duplicate emailVerified, ...
                $this->_prefs = new UserPreferences($prefs);
                $updated = true;
            }
        } else {
            if (!isset($this->_prefs))
                $this->_prefs =& $prefs;
            else
                $updated = $this->_prefs->isChanged($prefs);
        }
        if ($updated) {
            if ($id_only and !headers_sent()) {
                global $request;
                // new 1.3.8 policy: no array cookies, only plain userid string as in 
                // the pre 1.3.x versions.
                // prefs should be stored besides the session in the homepagehandle or in a db.
                $request->setCookieVar('WIKI_ID', $this->_userid,
                                       COOKIE_EXPIRATION_DAYS, COOKIE_DOMAIN);
                //$request->setCookieVar(WIKI_NAME, array('userid' => $prefs->get('userid')),
                //                       COOKIE_EXPIRATION_DAYS, COOKIE_DOMAIN);
            }
        }
        $packed = $prefs->store();
        $unpacked = $prefs->unpack($packed);
        if (count($unpacked)) {
            foreach (array('_method','_select','_update') as $param) {
            	if (!empty($this->_prefs->{$param}))
            	    $prefs->{$param} = $this->_prefs->{$param};
            }
            $this->_prefs = $prefs;
            //FIXME! The following must be done in $request->_setUser(), not here,
            // to be able to iterate over multiple users, without tampering the current user.
            if (0) {
                global $request;
                $request->_prefs =& $this->_prefs; 
                $request->_user->_prefs =& $this->_prefs;
                if (isset($request->_user->_auth_dbi)) {
                    $user = $request->_user;
                    unset($user->_auth_dbi);
                    $request->setSessionVar('wiki_user', $user);
                } else {
                    //$request->setSessionVar('wiki_prefs', $this->_prefs);
                    $request->setSessionVar('wiki_user', $request->_user);
                }
            }
        }
        return $updated;
    }

    function userExists() {
        return true;
    }

    function checkPass($submitted_password) {
        return false;
        // this might happen on a old-style signin button.

        // By definition, the _AnonUser does not HAVE a password
        // (compared to _BogoUser, who has an EMPTY password).
        trigger_error("DEBUG: Warning: _AnonUser unexpectedly asked to checkPass()." . " "
                      . "Check isa(\$user, '_PassUser'), or: isa(\$user, '_AdminUser') etc. first." . " "
                      . "New subclasses of _WikiUser must override this function.");
        return false;
    }

}

/** 
 * Helper class to finish the PassUser auth loop. 
 * This is added automatically to USER_AUTH_ORDER.
 */
class _ForbiddenUser
extends _AnonUser
{
    var $_level = WIKIAUTH_FORBIDDEN;

    function checkPass($submitted_password) {
        return WIKIAUTH_FORBIDDEN;
    }

    function userExists() {
        if ($this->_HomePagehandle) return true;
        return false;
    }
}
/** 
 * The PassUser name gets created automatically. 
 * That's why this class is empty, but must exist.
 */
class _ForbiddenPassUser
extends _ForbiddenUser
{
    function dummy() {
        return;
    }
}

/**
 * Do NOT extend _BogoUser to other classes, for checkPass()
 * security. (In case of defects in code logic of the new class!)
 * The intermediate step between anon and passuser.
 * We also have the _BogoLoginPassUser class with stricter 
 * password checking, which fits into the auth loop.
 * Note: This class is not called anymore by WikiUser()
 */
class _BogoUser
extends _AnonUser
{
    function userExists() {
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
            return true;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

    function checkPass($submitted_password) {
        // By definition, BogoUser has an empty password.
        $this->userExists();
        return $this->_level;
    }
}

class _PassUser
extends _AnonUser
/**
 * Called if ALLOW_USER_PASSWORDS and Anon and Bogo failed.
 *
 * The classes for all subsequent auth methods extend from this class. 
 * This handles the auth method type dispatcher according $USER_AUTH_ORDER, 
 * the three auth method policies first-only, strict and stacked
 * and the two methods for prefs: homepage or database, 
 * if $DBAuthParams['pref_select'] is defined.
 *
 * Default is PersonalPage auth and prefs.
 * 
 * @author: Reini Urban
 * @tables: pref
 */
{
    var $_auth_dbi, $_prefs;
    var $_current_method, $_current_index;

    // check and prepare the auth and pref methods only once
    function _PassUser($UserName='', $prefs=false) {
        //global $DBAuthParams, $DBParams;
        if ($UserName) {
            if (!$this->isValidName($UserName))
                return false;
            $this->_userid = $UserName;
            if ($this->hasHomePage())
                $this->_HomePagehandle = $GLOBALS['request']->getPage($this->_userid);
        }
        $this->_authmethod = substr(get_class($this),1,-8);
        if ($this->_authmethod == 'a') $this->_authmethod = 'admin';

        // Check the configured Prefs methods
        $dbi = $this->getAuthDbh();
        $dbh = $GLOBALS['request']->getDbh();
        if ( $dbi and !isset($this->_prefs->_select) and $dbh->getAuthParam('pref_select')) {
            if (!$this->_prefs) {
            	$this->_prefs = new UserPreferences();
            	$need_pref = true;
            }
            $this->_prefs->_method = $dbh->getParam('dbtype');
            $this->_prefs->_select = $this->prepare($dbh->getAuthParam('pref_select'), "userid");
            // read-only prefs?
            if ( !isset($this->_prefs->_update) and $dbh->getAuthParam('pref_update')) {
                $this->_prefs->_update = $this->prepare($dbh->getAuthParam('pref_update'), 
                                                        array("userid", "pref_blob"));
            }
        } else {
            if (!$this->_prefs) {
            	$this->_prefs = new UserPreferences();
            	$need_pref = true;
            }
            $this->_prefs->_method = 'HomePage';
        }
        
        if (! $this->_prefs or isset($need_pref) ) {
            if ($prefs) $this->_prefs = $prefs;
            else $this->getPreferences();
        }
        
        // Upgrade to the next parent _PassUser class. Avoid recursion.
        if ( strtolower(get_class($this)) === '_passuser' ) {
            //auth policy: Check the order of the configured auth methods
            // 1. first-only: Upgrade the class here in the constructor
            // 2. old:       ignore USER_AUTH_ORDER and try to use all available methods as 
            ///              in the previous PhpWiki releases (slow)
            // 3. strict:    upgrade the class after checking the user existance in userExists()
            // 4. stacked:   upgrade the class after the password verification in checkPass()
            // Methods: PersonalPage, HttpAuth, DB, Ldap, Imap, File
            if (!defined('USER_AUTH_POLICY')) define('USER_AUTH_POLICY','old');
            if (defined('USER_AUTH_POLICY')) {
                // policy 1: only pre-define one method for all users
                if (USER_AUTH_POLICY === 'first-only') {
                    $class = $this->nextClass();
                    return new $class($UserName,$this->_prefs);
                }
                // Use the default behaviour from the previous versions:
                elseif (USER_AUTH_POLICY === 'old') {
                    // Default: try to be smart
                    // On php5 we can directly return and upgrade the Object,
                    // before we have to upgrade it manually.
                    if (!empty($GLOBALS['PHP_AUTH_USER']) or !empty($_SERVER['REMOTE_USER'])) {
                        if (check_php_version(5))
                            return new _HttpAuthPassUser($UserName,$this->_prefs);
                        else {
                            $user = new _HttpAuthPassUser($UserName,$this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    } elseif (in_array('Db', $dbh->getAuthParam('USER_AUTH_ORDER')) and
                              $dbh->getAuthParam('auth_check') and
                              ($dbh->getAuthParam('auth_dsn') or $dbh->getParam('dsn'))) {
                        if (check_php_version(5))
                            return new _DbPassUser($UserName,$this->_prefs);
                        else {
                            $user = new _DbPassUser($UserName,$this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    } elseif (in_array('LDAP', $dbh->getAuthParam('USER_AUTH_ORDER')) and
                              defined('LDAP_AUTH_HOST') and defined('LDAP_BASE_DN') and 
                              function_exists('ldap_connect')) {
                        if (check_php_version(5))
                            return new _LDAPPassUser($UserName,$this->_prefs);
                        else {
                            $user = new _LDAPPassUser($UserName,$this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    } elseif (in_array('IMAP', $dbh->getAuthParam('USER_AUTH_ORDER')) and
                              defined('IMAP_AUTH_HOST') and function_exists('imap_open')) {
                        if (check_php_version(5))
                            return new _IMAPPassUser($UserName,$this->_prefs);
                        else {
                            $user = new _IMAPPassUser($UserName,$this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    } elseif (in_array('File', $dbh->getAuthParam('USER_AUTH_ORDER')) and
                              defined('AUTH_USER_FILE') and file_exists(AUTH_USER_FILE)) {
                        if (check_php_version(5))
                            return new _FilePassUser($UserName, $this->_prefs);
                        else {
                            $user = new _FilePassUser($UserName, $this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    } else {
                        if (check_php_version(5))
                            return new _PersonalPagePassUser($UserName,$this->_prefs);
                        else {
                            $user = new _PersonalPagePassUser($UserName,$this->_prefs);
                            eval("\$this = \$user;");
                            // /*PHP5 patch*/$this = $user;
                            return $user;
                        }
                    }
                }
                else 
                    // else use the page methods defined in _PassUser.
                    return $this;
            }
        }
    }

    function getAuthDbh () {
        global $request; //, $DBParams, $DBAuthParams;

        $dbh = $request->getDbh();
        // session restauration doesn't re-connect to the database automatically, 
        // so dirty it here, to force a reconnect.
        if (isset($this->_auth_dbi)) {
            if (($dbh->getParam('dbtype') == 'SQL') and empty($this->_auth_dbi->connection))
                unset($this->_auth_dbi);
            if (($dbh->getParam('dbtype') == 'ADODB') and empty($this->_auth_dbi->_connectionID))
                unset($this->_auth_dbi);
        }
        if (empty($this->_auth_dbi)) {
            if ($dbh->getParam('dbtype') != 'SQL' and $dbh->getParam('dbtype') != 'ADODB')
                return false;
            if (empty($GLOBALS['DBAuthParams']))
                return false;
            if (!$dbh->getAuthParam('auth_dsn')) {
                $dbh = $request->getDbh(); // use phpwiki database 
            } elseif ($dbh->getAuthParam('auth_dsn') == $dbh->getParam('dsn')) {
                $dbh = $request->getDbh(); // same phpwiki database 
            } else { // use another external database handle. needs PHP >= 4.1
                $local_params = array_merge($GLOBALS['DBParams'],$GLOBALS['DBAuthParams']);
                $local_params['dsn'] = $local_params['auth_dsn'];
                $dbh = WikiDB::open($local_params);
            }       
            $this->_auth_dbi =& $dbh->_backend->_dbh;    
        }
        return $this->_auth_dbi;
    }

    function _normalize_stmt_var($var, $oldstyle = false) {
        static $valid_variables = array('userid','password','pref_blob','groupname');
        // old-style: "'$userid'"
        // new-style: '"\$userid"' or just "userid"
        $new = str_replace(array("'",'"','\$','$'),'',$var);
        if (!in_array($new,$valid_variables)) {
            trigger_error("Unknown DBAuthParam statement variable: ". $new, E_USER_ERROR);
            return false;
        }
        return !$oldstyle ? "'$".$new."'" : '"\$'.$new.'"';
    }

    // TODO: use it again for the auth and member tables
    function prepare ($stmt, $variables, $oldstyle = false) {
        global $request;
        $dbi = $request->getDbh();
        $this->getAuthDbh();
        // "'\$userid"' => '%s'
        // variables can be old-style: '"\$userid"' or new-style: "'$userid'" or just "userid"
        // old-style strings don't survive pear/Config/IniConfig treatment, that's why we changed it.
        $new = array();
        if (is_array($variables)) {
            for ($i=0; $i < count($variables); $i++) { 
                $var = $this->_normalize_stmt_var($variables[$i],$oldstyle);
                if (!$var)
                    trigger_error(sprintf("DbAuthParams: Undefined or empty statement variable %s in %s",
                                          $variables[$i], $stmt), E_USER_WARNING);
                $variables[$i] = $var;
                if (!$var) $new[] = '';
                else $new[] = '%s';
            }
        } else {
            $var = $this->_normalize_stmt_var($variables,$oldstyle);
            if (!$var)
                trigger_error(sprintf("DbAuthParams: Undefined or empty statement variable %s in %s",
                                      $variables,$stmt), E_USER_WARNING);
            $variables = $var;
            if (!$var) $new = ''; 
            else $new = '%s'; 
        }
        $prefix = $dbi->getParam('prefix');
        // probably prefix table names if in same database
        if ($prefix and isset($this->_auth_dbi) and isset($dbi->_backend->_dbh) and 
            ($dbi->getAuthParam('auth_dsn') and $dbi->getParam('dsn') == $dbi->getAuthParam('auth_dsn')))
        {
            if (!stristr($stmt, $prefix)) {
                //Do it automatically for the lazy admin? Esp. on sf.net it's nice to have
                trigger_error("TODO: Need to prefix the DBAuthParam tablename in config/config.ini:\n  $stmt",
                              E_USER_WARNING);
                $stmt = str_replace(array(" user "," pref "," member "),
                                    array(" ".$prefix."user ",
                                          " ".$prefix."pref ",
                                          " ".$prefix."member "),$stmt);
            }
        }
        // Preparate the SELECT statement, for ADODB and PearDB (MDB not).
        // Simple sprintf-style.
        $new_stmt = str_replace($variables,$new,$stmt);
        if ($new_stmt == $stmt) {
            if ($oldstyle) {
                trigger_error(sprintf("DbAuthParams: Invalid statement in %s",
                                  $stmt), E_USER_WARNING);
            } else {
                trigger_error(sprintf("DbAuthParams: Old statement quoting style in %s",
                                  $stmt), E_USER_WARNING);
                $new_stmt = $this->prepare($stmt, $variables, 'oldstyle');
            }
        }
        return $new_stmt;
    }

    function getPreferences() {
        if (!empty($this->_prefs->_method)) {
            if ($this->_prefs->_method == 'ADODB') {
                _AdoDbPassUser::_AdoDbPassUser($this->_userid,$this->_prefs);
                return _AdoDbPassUser::getPreferences();
            } elseif ($this->_prefs->_method == 'SQL') {
                _PearDbPassUser::_PearDbPassUser($this->_userid,$this->_prefs);
                return _PearDbPassUser::getPreferences();
            }
        }

        // We don't necessarily have to read the cookie first. Since
        // the user has a password, the prefs stored in the homepage
        // cannot be arbitrarily altered by other Bogo users.
        _AnonUser::getPreferences();
        // User may have deleted cookie, retrieve from his
        // PersonalPage if there is one.
        if ($this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve
                ($this->_HomePagehandle->get('pref'))) {
                $updated = $this->_prefs->updatePrefs($restored_from_page,'init');
                //$this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        if (!empty($this->_prefs->_method)) {
            if ($this->_prefs->_method == 'ADODB') {
                _AdoDbPassUser::_AdoDbPassUser($this->_userid,$prefs);
                return _AdoDbPassUser::setPreferences($prefs, $id_only);
            }
            elseif ($this->_prefs->_method == 'SQL') {
                _PearDbPassUser::_PearDbPassUser($this->_userid, $prefs);
                return _PearDbPassUser::setPreferences($prefs, $id_only);
            }
        }
        if (_AnonUser::setPreferences($prefs, $id_only)) {
            // Encode only the _prefs array of the UserPreference object
            if ($this->_HomePagehandle and !$id_only) {
                $this->_HomePagehandle->set('pref', $this->_prefs->store());
            }
        }
        return;
    }

    function mayChangePass() {
        return true;
    }

    //The default method is getting the password from prefs. 
    // child methods obtain $stored_password from external auth.
    function userExists() {
        //if ($this->_HomePagehandle) return true;
        $class = $this->nextClass();
        while ($user = new $class($this->_userid, $this->_prefs)) {
            if (!check_php_version(5))
                eval("\$this = \$user;");
            // /*PHP5 patch*/$this = $user;
            UpgradeUser($this,$user);
            if ($user->userExists()) {
                return true;
            }
            // prevent endless loop. does this work on all PHP's?
            // it just has to set the classname, what it correctly does.
            $class = $user->nextClass();
            if ($class == "_ForbiddenPassUser")
                return false;
        }
        return false;
    }

    //The default method is getting the password from prefs. 
    // child methods obtain $stored_password from external auth.
    function checkPass($submitted_password) {
        $stored_password = $this->_prefs->get('passwd');
        if ($this->_checkPass($submitted_password, $stored_password)) {
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            return $this->_tryNextPass($submitted_password);
        }
    }

    /**
     * The basic password checker for all PassUser objects.
     * Uses global ENCRYPTED_PASSWD and PASSWORD_LENGTH_MINIMUM.
     * Empty passwords are always false!
     * PASSWORD_LENGTH_MINIMUM is enforced here and in the preference set method.
     * @see UserPreferences::set
     *
     * DBPassUser password's have their own crypt definition.
     * That's why DBPassUser::checkPass() doesn't call this method, if 
     * the db password method is 'plain', which means that the DB SQL 
     * statement just returns 1 or 0. To use CRYPT() or PASSWORD() and 
     * don't store plain passwords in the DB.
     * 
     * TODO: remove crypt() function check from config.php:396 ??
     */
    function _checkPass($submitted_password, $stored_password) {
        if(!empty($submitted_password)) {
            //FIXME: This will work only on plaintext passwords.
            if (strlen($stored_password) < PASSWORD_LENGTH_MINIMUM) {
                // With the EditMetaData plugin
                trigger_error(_("The length of the stored password is shorter than the system policy allows. Sorry, you cannot login.\n You have to ask the System Administrator to reset your password."));
                return false;
            }
            if (strlen($submitted_password) < PASSWORD_LENGTH_MINIMUM)
                return false;
            if (ENCRYPTED_PASSWD) {
                // Verify against encrypted password.
                if (function_exists('crypt')) {
                    if (crypt($submitted_password, $stored_password) == $stored_password )
                        return true; // matches encrypted password
                    else
                        return false;
                }
                else {
                    trigger_error(_("The crypt function is not available in this version of PHP.") . " "
                                  . _("Please set ENCRYPTED_PASSWD to false in config/config.ini and probably change ADMIN_PASSWD."),
                                  E_USER_WARNING);
                    return false;
                }
            }
            else {
                // Verify against cleartext password.
                if ($submitted_password == $stored_password)
                    return true;
                else {
                    // Check whether we forgot to enable ENCRYPTED_PASSWD
                    if (function_exists('crypt')) {
                        if (crypt($submitted_password, $stored_password) == $stored_password) {
                            trigger_error(_("Please set ENCRYPTED_PASSWD to true in config/config.ini."),
                                          E_USER_WARNING);
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /** The default method is storing the password in prefs. 
     *  Child methods (DB,File) may store in external auth also, but this 
     *  must be explicitly enabled.
     *  This may be called by plugin/UserPreferences or by ->SetPreferences()
     */
    function changePass($submitted_password) {
        $stored_password = $this->_prefs->get('passwd');
        // check if authenticated
        if ($this->isAuthenticated() and $stored_password != $submitted_password) {
            $this->_prefs->set('passwd',$submitted_password);
            //update the storage (session, homepage, ...)
            $this->SetPreferences($this->_prefs);
            return true;
        }
        //Todo: return an error msg to the caller what failed? 
        // same password or no privilege
        return false;
    }

    function _tryNextPass($submitted_password) {
        if (USER_AUTH_POLICY === 'strict') {
        	$class = $this->nextClass();
            if ($user = new $class($this->_userid,$this->_prefs)) {
                if ($user->userExists()) {
                    return $user->checkPass($submitted_password);
                }
            }
        }
        if (USER_AUTH_POLICY === 'stacked' or USER_AUTH_POLICY === 'old') {
        	$class = $this->nextClass();
            if ($user = new $class($this->_userid,$this->_prefs))
                return $user->checkPass($submitted_password);
        }
        return $this->_level;
    }

    function _tryNextUser() {
        if (USER_AUTH_POLICY === 'strict') {
        	$class = $this->nextClass();
            while ($user = new $class($this->_userid,$this->_prefs)) {
                if (!check_php_version(5))
                    eval("\$this = \$user;");
                // /*PHP5 patch*/$this = $user;
                //$user = UpgradeUser($this, $user);
                if ($user->userExists()) {
                    return true;
                }
                $class = $this->nextClass();
            }
        }
        return false;
    }

}

/** Without stored password. A _BogoLoginPassUser with password 
 *  is automatically upgraded to a PersonalPagePassUser.
 */
class _BogoLoginPassUser
extends _PassUser
{
    var $_authmethod = 'BogoLogin';
    function userExists() {
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
            return true;
        } else {
            $this->_level = WIKIAUTH_ANON;
            return false;
        }
    }

    /** A BogoLoginUser requires no password at all
     *  But if there's one stored, we should prefer PersonalPage instead
     */
    function checkPass($submitted_password) {
        if ($this->_prefs->get('passwd')) {
            if (isset($this->_prefs->_method) and $this->_prefs->_method == 'HomePage') {
                $user = new _PersonalPagePassUser($this->_userid, $this->_prefs);
                if ($user->checkPass($submitted_password)) {
                    if (!check_php_version(5))
                        eval("\$this = \$user;");
                    // /*PHP5 patch*/$this = $user;
                    $user = UpgradeUser($this, $user);
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                } else {
                    $this->_level = WIKIAUTH_ANON;
                    return $this->_level;
                }
            } else {
                $stored_password = $this->_prefs->get('passwd');
                if ($this->_checkPass($submitted_password, $stored_password)) {
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                } else {
                    return $this->_tryNextPass($submitted_password);
                }
            }
        }
        if (isWikiWord($this->_userid)) {
            $this->_level = WIKIAUTH_BOGO;
        } else {
            $this->_level = WIKIAUTH_ANON;
        }
        return $this->_level;
    }
}


/**
 * This class is only to simplify the auth method dispatcher.
 * It inherits almost all all methods from _PassUser.
 */
class _PersonalPagePassUser
extends _PassUser
{
    var $_authmethod = 'PersonalPage';

    function userExists() {
        return $this->_HomePagehandle and $this->_HomePagehandle->exists();
    }

    /** A PersonalPagePassUser requires PASSWORD_LENGTH_MINIMUM.
     *  BUT if the user already has a homepage with an empty password 
     *  stored, allow login but warn him to change it.
     */
    function checkPass($submitted_password) {
        if ($this->userExists()) {
            $stored_password = $this->_prefs->get('passwd');
            if (empty($stored_password)) {
                trigger_error(sprintf(
                _("PersonalPage login method:\n").
                _("You stored an empty password in your '%s' page.\n").
                _("Your access permissions are only for a BogoUser.\n").
                _("Please set your password in UserPreferences."),
                                        $this->_userid), E_USER_WARNING);
                $this->_level = WIKIAUTH_BOGO;
                return $this->_level;
            }
            if ($this->_checkPass($submitted_password, $stored_password))
                return ($this->_level = WIKIAUTH_USER);
            return _PassUser::checkPass($submitted_password);
        }
        return WIKIAUTH_ANON;
    }
}

/**
 * We have two possibilities here.
 * 1) The webserver location is already HTTP protected (usually Basic). Then just 
 *    use the username and do nothing
 * 2) The webserver location is not protected, so we enforce basic HTTP Protection
 *    by sending a 401 error and let the client display the login dialog.
 *    This makes only sense if HttpAuth is the last method in USER_AUTH_ORDER,
 *    since the other methods cannot be transparently called after this enforced 
 *    external dialog.
 *    Try the available auth methods (most likely Bogo) and sent this header back.
 *    header('Authorization: Basic '.base64_encode("$userid:$passwd")."\r\n";
 */
class _HttpAuthPassUser
extends _PassUser
{
    function _HttpAuthPassUser($UserName='',$prefs=false) {
        if ($prefs) $this->_prefs = $prefs;
        if (!isset($this->_prefs->_method))
           _PassUser::_PassUser($UserName);
        if ($UserName) $this->_userid = $UserName;
        $this->_authmethod = 'HttpAuth';
        if ($this->userExists())
            return $this;
        else 
            return $GLOBALS['ForbiddenUser'];
    }

    function _http_username() {
        if (!isset($_SERVER))
            $_SERVER =& $GLOBALS['HTTP_SERVER_VARS'];
	if (!empty($_SERVER['PHP_AUTH_USER']))
	    return $_SERVER['PHP_AUTH_USER'];
	if (!empty($_SERVER['REMOTE_USER']))
	    return $_SERVER['REMOTE_USER'];
        if (!empty($GLOBALS['HTTP_ENV_VARS']['REMOTE_USER']))
	    return $GLOBALS['HTTP_ENV_VARS']['REMOTE_USER'];
	if (!empty($GLOBALS['REMOTE_USER']))
	    return $GLOBALS['REMOTE_USER'];
	return '';
    }
    
    //force http auth authorization
    function userExists() {
        // todo: older php's
        $username = $this->_http_username();
        if (empty($username) or strtolower($username) != strtolower($this->_userid)) {
            header('WWW-Authenticate: Basic realm="'.WIKI_NAME.'"');
            header('HTTP/1.0 401 Unauthorized'); 
            exit;
        }
        $this->_userid = $username;
        // we should check if he is a member of admin, 
        // because HttpAuth has its own logic.
        $this->_level = WIKIAUTH_USER;
        if ($this->isAdmin())
            $this->_level = WIKIAUTH_ADMIN;
        return $this;
    }
        
    function checkPass($submitted_password) {
        return $this->userExists() 
            ? ($this->isAdmin() ? WIKIAUTH_ADMIN : WIKIAUTH_USER)
            : WIKIAUTH_ANON;
    }

    function mayChangePass() {
        return false;
    }

    // hmm... either the server dialog or our own.
    function PrintLoginForm (&$request, $args, $fail_message = false,
                             $seperate_page = true) {
        header('WWW-Authenticate: Basic realm="'.WIKI_NAME.'"');
        header('HTTP/1.0 401 Unauthorized'); 
        exit;
    }

}

/** 
 * Support reuse of existing user session from another application.
 * You have to define which session variable holds the userid, and 
 * at what level is that user then. 1: BogoUser, 2: PassUser
 *   define('AUTH_SESS_USER','userid');
 *   define('AUTH_SESS_LEVEL',2);
 */
class _SessionPassUser
extends _PassUser
{
    function _SessionPassUser($UserName='',$prefs=false) {
        if ($prefs) $this->_prefs = $prefs;
        if (!defined("AUTH_SESS_USER") or !defined("AUTH_SESS_LEVEL")) {
            trigger_error(
                "AUTH_SESS_USER or AUTH_SESS_LEVEL is not defined for the SessionPassUser method",
                E_USER_ERROR);
            exit;
        }
        $sess =& $GLOBALS['HTTP_SESSION_VARS'];
        // user hash: "[user][userid]" or object "user->id"
        if (strstr(AUTH_SESS_USER,"][")) {
            $sess = $GLOBALS['HTTP_SESSION_VARS'];
            // recurse into hashes: "[user][userid]", sess = sess[user] => sess = sess[userid]
            foreach (split("][",AUTH_SESS_USER) as $v) {
                $v = str_replace(array("[","]"),'',$v);
                $sess = $sess[$v];
            }
            $this->_userid = $sess;
        } elseif (strstr(AUTH_SESS_USER,"->")) {
            // object "user->id" (no objects inside hashes supported!)
            list($obj,$key) = split("->",AUTH_SESS_USER);
            $this->_userid = $sess[$obj]->$key;
        } else {
            $this->_userid = $sess[AUTH_SESS_USER];
        }
        if (!isset($this->_prefs->_method))
           _PassUser::_PassUser($this->_userid);
        $this->_level = AUTH_SESS_LEVEL;
        $this->_authmethod = 'Session';
    }
    function userExists() {
        return !empty($this->_userid);
    }
    function checkPass($submitted_password) {
        return $this->userExists() and $this->_level;
    }
    function mayChangePass() {
        return false;
    }
}

/**
 * Baseclass for PearDB and ADODB PassUser's
 * Authenticate against a database, to be able to use shared users.
 *   internal: no different $DbAuthParams['dsn'] defined, or
 *   external: different $DbAuthParams['dsn']
 * The magic is done in the symbolic SQL statements in config/config.ini, similar to
 * libnss-mysql.
 *
 * We support only the SQL and ADODB backends.
 * The other WikiDB backends (flat, cvs, dba, ...) should be used for pages, 
 * not for auth stuff. If one would like to use e.g. dba for auth, he should 
 * use PearDB (SQL) with the right $DBAuthParam['auth_dsn']. 
 * (Not supported yet, since we require SQL. SQLite would make since when 
 * it will come to PHP)
 *
 * @tables: user, pref
 *
 * Preferences are handled in the parent class _PassUser, because the 
 * previous classes may also use DB pref_select and pref_update.
 *
 * Flat files auth is handled by the auth method "File".
 */
class _DbPassUser
extends _PassUser
{
    var $_authselect, $_authupdate, $_authcreate;

    // This can only be called from _PassUser, because the parent class 
    // sets the auth_dbi and pref methods, before this class is initialized.
    function _DbPassUser($UserName='',$prefs=false) {
        if (!$this->_prefs) {
            if ($prefs) $this->_prefs = $prefs;
        }
        if (!isset($this->_prefs->_method))
           _PassUser::_PassUser($UserName);
        elseif (!$this->isValidName($UserName)) {
            trigger_error(_("Invalid username."),E_USER_WARNING);
            return false;
        }
        $this->_authmethod = 'Db';
        //$this->getAuthDbh();
        //$this->_auth_crypt_method = @$GLOBALS['DBAuthParams']['auth_crypt_method'];
        $dbi =& $GLOBALS['request']->_dbi;
        $dbtype = $dbi->getParam('dbtype');
        if ($dbtype == 'ADODB') {
            if (check_php_version(5))
                return new _AdoDbPassUser($UserName,$this->_prefs);
            else {
                $user = new _AdoDbPassUser($UserName,$this->_prefs);
                eval("\$this = \$user;");
                return $user;
            }
        }
        elseif ($dbtype == 'SQL') {
            if (check_php_version(5))
                return new _PearDbPassUser($UserName,$this->_prefs);
            else {
                $user = new _PearDbPassUser($UserName,$this->_prefs);
                eval("\$this = \$user;");
                return $user;
            }
        }
        return false;
    }

    function mayChangePass() {
        return !isset($this->_authupdate);
    }

}

class _PearDbPassUser
extends _DbPassUser
/**
 * Pear DB methods
 * Now optimized not to use prepare, ...query(sprintf($sql,quote())) instead.
 * We use FETCH_MODE_ROW, so we don't need aliases in the auth_* SQL statements.
 *
 * @tables: user
 * @tables: pref
 */
{
    var $_authmethod = 'PearDb';
    function _PearDbPassUser($UserName='',$prefs=false) {
        //global $DBAuthParams;
        if (!$this->_prefs and isa($this,"_PearDbPassUser")) {
            if ($prefs) $this->_prefs = $prefs;
        }
        if (!isset($this->_prefs->_method))
            _PassUser::_PassUser($UserName);
        elseif (!$this->isValidName($UserName)) {
            trigger_error(_("Invalid username."), E_USER_WARNING);
            return false;
        }
        $this->_userid = $UserName;
        // make use of session data. generally we only initialize this every time, 
        // but do auth checks only once
        $this->_auth_crypt_method = $GLOBALS['request']->_dbi->getAuthParam('auth_crypt_method');
        return $this;
    }

    function getPreferences() {
        // override the generic slow method here for efficiency and not to 
        // clutter the homepage metadata with prefs.
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if (isset($this->_prefs->_select)) {
            $dbh = &$this->_auth_dbi;
            $db_result = $dbh->query(sprintf($this->_prefs->_select,$dbh->quote($this->_userid)));
            // patched by frederik@pandora.be
            $prefs = $db_result->fetchRow();
            $prefs_blob = @$prefs["prefs"]; 
            if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                $updated = $this->_prefs->updatePrefs($restored_from_db);
                //$this->_prefs = new UserPreferences($restored_from_db);
                return $this->_prefs;
            }
        }
        if ($this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve
                ($this->_HomePagehandle->get('pref'))) {
                $updated = $this->_prefs->updatePrefs($restored_from_page);
                //$this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        // if the prefs are changed
        if ($count = _AnonUser::setPreferences($prefs, 1)) {
            //global $request;
            //$user = $request->_user;
            //unset($user->_auth_dbi);
            // this must be done in $request->_setUser, not here!
            //$request->setSessionVar('wiki_user', $user);
            $this->getAuthDbh();
            $packed = $this->_prefs->store();
            if (!$id_only and isset($this->_prefs->_update)) {
                $dbh = &$this->_auth_dbi;
                $dbh->simpleQuery(sprintf($this->_prefs->_update,
                                          $dbh->quote($packed),
                                          $dbh->quote($this->_userid)));
                //delete pageprefs:
                if ($this->_HomePagehandle and $this->_HomePagehandle->get('pref'))
                    $this->_HomePagehandle->set('pref', '');
            } else {
                //store prefs in homepage, not in cookie
                if ($this->_HomePagehandle and !$id_only)
                    $this->_HomePagehandle->set('pref', $packed);
            }
            return $count; //count($this->_prefs->unpack($packed));
        }
        return 0;
    }

    function userExists() {
        //global $DBAuthParams;
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        if (!$dbh) { // needed?
            return $this->_tryNextUser();
        }
        if (!$this->isValidName()) {
            return $this->_tryNextUser();
        }
        $dbi =& $GLOBALS['request']->_dbi;
        // Prepare the configured auth statements
        if ($dbi->getAuthParam('auth_check') and empty($this->_authselect)) {
            $this->_authselect = $this->prepare($dbi->getAuthParam('auth_check'), 
                                                array("userid", "password"));
        }
        if (empty($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK', 'SQL'),
                          E_USER_WARNING);
        //NOTE: for auth_crypt_method='crypt' no special auth_user_exists is needed
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->query(sprintf($this->_authselect, $dbh->quote($this->_userid)));
            if ($rs->numRows())
                return true;
        }
        else {
            if (! $dbi->getAuthParam('auth_user_exists'))
                trigger_error(fmt("%s is missing",'DBAUTH_AUTH_USER_EXISTS'),
                              E_USER_WARNING);
            $this->_authcheck = $this->prepare($dbi->getAuthParam('auth_user_exists'),"userid");
            $rs = $dbh->query(sprintf($this->_authcheck, $dbh->quote($this->_userid)));
            if ($rs->numRows())
                return true;
        }
        // maybe the user is allowed to create himself. Generally not wanted in 
        // external databases, but maybe wanted for the wiki database, for performance 
        // reasons
        if (empty($this->_authcreate) and $dbi->getAuthParam('auth_create')) {
            $this->_authcreate = $this->prepare($dbi->getAuthParam('auth_create'),
                                                array("userid", "password"));
        }
        if (!empty($this->_authcreate) and isset($GLOBALS['HTTP_POST_VARS']['auth']['passwd'])) {
            $passwd = $GLOBALS['HTTP_POST_VARS']['auth']['passwd'];
            $dbh->simpleQuery(sprintf($this->_authcreate,
                                      $dbh->quote($passwd),
                                      $dbh->quote($this->_userid)
                                      ));
            return true;
        }
        return $this->_tryNextUser();
    }
 
    function checkPass($submitted_password) {
        //global $DBAuthParams;
        $this->getAuthDbh();
        if (!$this->_auth_dbi) {  // needed?
            return $this->_tryNextPass($submitted_password);
        }
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        if (!isset($this->_authselect))
            $this->userExists();
        if (!isset($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK','SQL'),
                          E_USER_WARNING);

        //NOTE: for auth_crypt_method='crypt'  defined('ENCRYPTED_PASSWD',true) must be set
        $dbh = &$this->_auth_dbi;
        if ($this->_auth_crypt_method == 'crypt') {
            $stored_password = $dbh->getOne(sprintf($this->_authselect, 
                                                    $dbh->quote($this->_userid)));
            $result = $this->_checkPass($submitted_password, $stored_password);
        } else {
            $okay = $dbh->getOne(sprintf($this->_authselect,
                                         $dbh->quote($submitted_password),
                                         $dbh->quote($this->_userid)));
            $result = !empty($okay);
        }

        if ($result) {
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            return $this->_tryNextPass($submitted_password);
        }
    }

    function mayChangePass() {
        return $GLOBALS['request']->_dbi->getAuthParam('auth_update');
    }

    function storePass($submitted_password) {
        if (!$this->isValidName()) {
            return false;
        }
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        $dbi =& $GLOBALS['request']->_dbi;
        if ($dbi->getAuthParam('auth_update') and empty($this->_authupdate)) {
            $this->_authupdate = $this->prepare($dbi->getAuthParam('auth_update'),
                                                array("userid", "password"));
        }
        if (empty($this->_authupdate)) {
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_UPDATE','SQL'),
                          E_USER_WARNING);
            return false;
        }

        if ($this->_auth_crypt_method == 'crypt') {
            if (function_exists('crypt'))
                $submitted_password = crypt($submitted_password);
        }
        $dbh->simpleQuery(sprintf($this->_authupdate,
                                  $dbh->quote($submitted_password),
        			  $dbh->quote($this->_userid)
                                  ));
        return true;
    }
}

class _AdoDbPassUser
extends _DbPassUser
/**
 * ADODB methods
 * Simple sprintf, no prepare.
 *
 * Warning: Since we use FETCH_MODE_ASSOC (string hash) and not the also faster 
 * FETCH_MODE_ROW (numeric), we have to use the correct aliases in auth_* sql statements!
 *
 * TODO: Change FETCH_MODE in adodb WikiDB sublasses.
 *
 * @tables: user
 */
{
    var $_authmethod = 'AdoDb';
    function _AdoDbPassUser($UserName='',$prefs=false) {
        if (!$this->_prefs and isa($this,"_AdoDbPassUser")) {
            if ($prefs) $this->_prefs = $prefs;
            if (!isset($this->_prefs->_method))
              _PassUser::_PassUser($UserName);
        }
        if (!$this->isValidName($UserName)) {
            trigger_error(_("Invalid username."),E_USER_WARNING);
            return false;
        }
        $this->_userid = $UserName;
        $this->getAuthDbh();
        $this->_auth_crypt_method = $GLOBALS['request']->_dbi->getAuthParam('auth_crypt_method');
        // Don't prepare the configured auth statements anymore
        return $this;
    }

    function getPreferences() {
        // override the generic slow method here for efficiency
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if (isset($this->_prefs->_select)) {
            $dbh = & $this->_auth_dbi;
            $rs = $dbh->Execute(sprintf($this->_prefs->_select, $dbh->qstr($this->_userid)));
            if ($rs->EOF) {
                $rs->Close();
            } else {
                $prefs_blob = @$rs->fields['prefs'];
                $rs->Close();
                if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                    $updated = $this->_prefs->updatePrefs($restored_from_db);
                    //$this->_prefs = new UserPreferences($restored_from_db);
                    return $this->_prefs;
                }
            }
        }
        if ($this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve
                ($this->_HomePagehandle->get('pref'))) {
                $updated = $this->_prefs->updatePrefs($restored_from_page);
                //$this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }

    function setPreferences($prefs, $id_only=false) {
        // if the prefs are changed
        if (_AnonUser::setPreferences($prefs, 1)) {
            global $request;
            $packed = $this->_prefs->store();
            //$user = $request->_user;
            //unset($user->_auth_dbi);
            if (!$id_only and isset($this->_prefs->_update)) {
                $this->getAuthDbh();
                $dbh = &$this->_auth_dbi;
                $db_result = $dbh->Execute(sprintf($this->_prefs->_update,
                                                   $dbh->qstr($packed),
                                                   $dbh->qstr($this->_userid)));
                $db_result->Close();
                //delete pageprefs:
                if ($this->_HomePagehandle and $this->_HomePagehandle->get('pref'))
                    $this->_HomePagehandle->set('pref', '');
            } else {
                //store prefs in homepage, not in cookie
                if ($this->_HomePagehandle and !$id_only)
                    $this->_HomePagehandle->set('pref', $packed);
            }
            return count($this->_prefs->unpack($packed));
        }
        return 0;
    }
 
    function userExists() {
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        if (!$dbh) { // needed?
            return $this->_tryNextUser();
        }
        if (!$this->isValidName()) {
            return $this->_tryNextUser();
        }
        $dbi =& $GLOBALS['request']->_dbi;
        if (empty($this->_authselect) and $dbi->getAuthParam('auth_check')) {
            $this->_authselect = $this->prepare($dbi->getAuthParam('auth_check'),
                                                array("userid","password"));
        }
        if (empty($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK', 'ADODB'),
                          E_USER_WARNING);
        //NOTE: for auth_crypt_method='crypt' no special auth_user_exists is needed
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->Execute(sprintf($this->_authselect, $dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $rs->Close();
                return true;
            } else {
                $rs->Close();
            }
        }
        else {
            if (! $dbi->getAuthParam('auth_user_exists'))
                trigger_error(fmt("%s is missing", 'DBAUTH_AUTH_USER_EXISTS'),
                              E_USER_WARNING);
            $this->_authcheck = $this->prepare($dbi->getAuthParam('auth_user_exists'), 
                                               'userid');
            $rs = $dbh->Execute(sprintf($this->_authcheck, $dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $rs->Close();
                return true;
            } else {
                $rs->Close();
            }
        }
        // maybe the user is allowed to create himself. Generally not wanted in 
        // external databases, but maybe wanted for the wiki database, for performance 
        // reasons
        if (empty($this->_authcreate) and $dbi->getAuthParam('auth_create')) {
            $this->_authcreate = $this->prepare($dbi->getAuthParam('auth_create'),
                                                array("userid", "password"));
        }
        if (!empty($this->_authcreate) and 
            isset($GLOBALS['HTTP_POST_VARS']['auth']) and
            isset($GLOBALS['HTTP_POST_VARS']['auth']['passwd'])) 
        {
            $dbh->Execute(sprintf($this->_authcreate,
                                  $dbh->qstr($GLOBALS['HTTP_POST_VARS']['auth']['passwd']),
                                  $dbh->qstr($this->_userid)));
            return true;
        }
        
        return $this->_tryNextUser();
    }

    function checkPass($submitted_password) {
        //global $DBAuthParams;
        $this->getAuthDbh();
        if (!$this->_auth_dbi) {  // needed?
            return $this->_tryNextPass($submitted_password);
        }
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        $dbh =& $this->_auth_dbi;
        $dbi =& $GLOBALS['request']->_dbi;
        if (empty($this->_authselect) and $dbi->getAuthParam('auth_check')) {
            $this->_authselect = $this->prepare($dbi->getAuthParam('auth_check'),
                                                array("userid", "password"));
        }
        if (!isset($this->_authselect))
            $this->userExists();
        if (!isset($this->_authselect))
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_CHECK', 'ADODB'),
                          E_USER_WARNING);
        //NOTE: for auth_crypt_method='crypt'  defined('ENCRYPTED_PASSWD',true) must be set
        if ($this->_auth_crypt_method == 'crypt') {
            $rs = $dbh->Execute(sprintf($this->_authselect, $dbh->qstr($this->_userid)));
            if (!$rs->EOF) {
                $stored_password = $rs->fields['password'];
                $rs->Close();
                $result = $this->_checkPass($submitted_password, $stored_password);
            } else {
                $rs->Close();
                $result = false;
            }
        } else {
            $rs = $dbh->Execute(sprintf($this->_authselect,
                                        $dbh->qstr($submitted_password),
                                        $dbh->qstr($this->_userid)));
            if (isset($rs->fields['ok']))
                $okay = $rs->fields['ok'];
            elseif (isset($rs->fields[1]))
                $okay = $rs->fields[1];
            else {
                $okay = reset($rs->fields);
            }
            $rs->Close();
            $result = !empty($okay);
        }

        if ($result) { 
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            return $this->_tryNextPass($submitted_password);
        }
    }

    function mayChangePass() {
        return $GLOBALS['request']->_dbi->getAuthParam('auth_update');
    }

    function storePass($submitted_password) {
        $this->getAuthDbh();
        $dbh = &$this->_auth_dbi;
        $dbi =& $GLOBALS['request']->_dbi;
        if ($dbi->getAuthParam('auth_update') and empty($this->_authupdate)) {
            $this->_authupdate = $this->prepare($dbi->getAuthParam('auth_update'),
                                                array("userid", "password"));
        }
        if (!isset($this->_authupdate)) {
            trigger_error(fmt("Either %s is missing or DATABASE_TYPE != '%s'",
                              'DBAUTH_AUTH_UPDATE', 'ADODB'),
                          E_USER_WARNING);
            return false;
        }

        if ($this->_auth_crypt_method == 'crypt') {
            if (function_exists('crypt'))
                $submitted_password = crypt($submitted_password);
        }
        $rs = $dbh->Execute(sprintf($this->_authupdate,
                                    $dbh->qstr($submitted_password),
                                    $dbh->qstr($this->_userid)
                                    ));
        $rs->Close();
        return $rs;
    }
}

class _LDAPPassUser
extends _PassUser
/**
 * Define the vars LDAP_AUTH_HOST and LDAP_BASE_DN in config/config.ini
 *
 * Preferences are handled in _PassUser
 */
{
	
    function _init() {
        if ($this->_ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
            global $LDAP_SET_OPTION;
            if (!empty($LDAP_SET_OPTION)) {
                foreach ($LDAP_SET_OPTION as $key => $value) {
                    //if (is_string($key) and defined($key))
                    //    $key = constant($key);
                    ldap_set_option($this->_ldap, $key, $value);
                }
            }
            if (LDAP_AUTH_USER)
                if (LDAP_AUTH_PASSWORD)
                    // Windows Active Directory Server is strict
                    $r = ldap_bind($this->_ldap, LDAP_AUTH_USER, LDAP_AUTH_PASSWORD); 
                else
                    $r = ldap_bind($this->_ldap, LDAP_AUTH_USER); 
            else
                $r = true; // anonymous bind allowed
            if (!$r) {    
                $this->_free();
                trigger_error(sprintf("Unable to bind LDAP server %s", LDAP_AUTH_HOST), 
                              E_USER_WARNING);
                return false;
            }
            return $this->_ldap;
        } else {
            return false;
        }
    }
    
    function _free() {
        if (isset($this->_sr)   and is_resource($this->_sr))   ldap_free_result($this->_sr);
        if (isset($this->_ldap) and is_resource($this->_ldap)) ldap_close($this->_ldap);
        unset($this->_sr);
        unset($this->_ldap);
    }
    
    function checkPass($submitted_password) {

        $this->_authmethod = 'LDAP';
        $userid = $this->_userid;
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        if (strstr($userid,'*')) {
            trigger_error(fmt("Invalid username '%s' for LDAP Auth",$userid), 
                          E_USER_WARNING);
            return WIKIAUTH_FORBIDDEN;
        }

        if ($ldap = $this->_init()) {
            // Need to set the right root search information. See config/config.ini
            $st_search = LDAP_SEARCH_FIELD
                ? LDAP_SEARCH_FIELD."=$userid"
                : "uid=$userid";
            if (!$this->_sr = ldap_search($ldap, LDAP_BASE_DN, $st_search)) {
 		$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            $info = ldap_get_entries($ldap, $this->_sr); 
            if (empty($info["count"])) {
            	$this->_free();
                return $this->_tryNextPass($submitted_password);
            }
            // There may be more hits with this userid.
            // Of course it would be better to narrow down the BASE_DN
            for ($i = 0; $i < $info["count"]; $i++) {
                $dn = $info[$i]["dn"];
                // The password is still plain text.
                // On wrong password the ldap server will return: 
                // "Unable to bind to server: Server is unwilling to perform"
                // The @ catches this error message.
                if ($r = @ldap_bind($ldap, $dn, $submitted_password)) {
                    // ldap_bind will return TRUE if everything matches
            	    $this->_free();
                    $this->_level = WIKIAUTH_USER;
                    return $this->_level;
                }
            }
            $this->_free();
        }

        return $this->_tryNextPass($submitted_password);
    }

    function userExists() {
        $userid = $this->_userid;
        if (strstr($userid,'*')) {
            trigger_error(fmt("Invalid username '%s' for LDAP Auth", $userid),
                          E_USER_WARNING);
            return false;
        }
        if ($ldap = $this->_init()) {
            // Need to set the right root search information. see ../index.php
            $st_search = LDAP_SEARCH_FIELD
                ? LDAP_SEARCH_FIELD."=$userid"
                : "uid=$userid";
            if (!$this->_sr = ldap_search($ldap, LDAP_BASE_DN, $st_search)) {
 		$this->_free();
        	return $this->_tryNextUser();
            }
            $info = ldap_get_entries($ldap, $this->_sr); 

            if ($info["count"] > 0) {
         	$this->_free();
                return true;
            }
        }
 	$this->_free();
        return $this->_tryNextUser();
    }

    function mayChangePass() {
        return false;
    }

}

class _IMAPPassUser
extends _PassUser
/**
 * Define the var IMAP_AUTH_HOST in config/config.ini (with port probably)
 *
 * Preferences are handled in _PassUser
 */
{
    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        $userid = $this->_userid;
        $mbox = @imap_open( "{" . IMAP_AUTH_HOST . "}",
                            $userid, $submitted_password, OP_HALFOPEN );
        if ($mbox) {
            imap_close($mbox);
            $this->_authmethod = 'IMAP';
            $this->_level = WIKIAUTH_USER;
            return $this->_level;
        } else {
            trigger_error(_("Unable to connect to IMAP server "). IMAP_AUTH_HOST, 
                          E_USER_WARNING);
        }

        return $this->_tryNextPass($submitted_password);
    }

    //CHECKME: this will not be okay for the auth policy strict
    function userExists() {
        return true;

        if (checkPass($this->_prefs->get('passwd')))
            return true;
        return $this->_tryNextUser();
    }

    function mayChangePass() {
        return false;
    }
}


class _POP3PassUser
extends _IMAPPassUser {
/**
 * Define the var POP3_AUTH_HOST in config/config.ini
 * Preferences are handled in _PassUser
 */
    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            return $this->_tryNextPass($submitted_password);
        }
        $userid = $this->_userid;
        $pass = $submitted_password;
        $host = defined('POP3_AUTH_HOST') ? POP3_AUTH_HOST : 'localhost:110';
        if (defined('POP3_AUTH_PORT'))
            $port = POP3_AUTH_PORT;
        elseif (strstr($host,':')) {
            list(,$port) = split(':',$host);
        } else {
            $port = 110;
        }
        $retval = false;
        $fp = fsockopen($host, $port, $errno, $errstr, 10);
        if ($fp) {
            // Get welcome string
            $line = fgets($fp, 1024);
            if (! strncmp("+OK ", $line, 4)) {
                // Send user name
                fputs($fp, "user $userid\n");
                // Get response
                $line = fgets($fp, 1024);
                if (! strncmp("+OK ", $line, 4)) {
                    // Send password
                    fputs($fp, "pass $pass\n");
                    // Get response
                    $line = fgets($fp, 1024);
                    if (! strncmp("+OK ", $line, 4)) {
                        $retval = true;
                    }
                }
            }
            // quit the connection
            fputs($fp, "quit\n");
            // Get the sayonara message
            $line = fgets($fp, 1024);
            fclose($fp);
        } else {
            trigger_error(_("Couldn't connect to %s","POP3_AUTH_HOST ".$host.':'.$port),
                          E_USER_WARNING);
        }
        $this->_authmethod = 'POP3';
        if ($retval) {
            $this->_level = WIKIAUTH_USER;
        } else {
            $this->_level = WIKIAUTH_ANON;
        }
        return $this->_level;
    }
}

class _FilePassUser
extends _PassUser
/**
 * Check users defined in a .htaccess style file
 * username:crypt\n...
 *
 * Preferences are handled in _PassUser
 */
{
    var $_file, $_may_change;

    // This can only be called from _PassUser, because the parent class 
    // sets the pref methods, before this class is initialized.
    function _FilePassUser($UserName='', $prefs=false, $file='') {
        if (!$this->_prefs and isa($this, "_FilePassUser")) {
            if ($prefs) $this->_prefs = $prefs;
            if (!isset($this->_prefs->_method))
              _PassUser::_PassUser($UserName);
        }
        $this->_userid = $UserName;
        // read the .htaccess style file. We use our own copy of the standard pear class.
        //include_once 'lib/pear/File_Passwd.php';
        $this->_may_change = defined('AUTH_USER_FILE_STORABLE') && AUTH_USER_FILE_STORABLE;
        if (empty($file) and defined('AUTH_USER_FILE'))
            $file = AUTH_USER_FILE;
        include_once(dirname(__FILE__)."/pear/File_Passwd.php"); // same style as in main.php
        // "__PHP_Incomplete_Class"
        if (!empty($file) or empty($this->_file) or !isa($this->_file,"File_Passwd"))
            $this->_file = new File_Passwd($file, false, $file.'.lock');
        else
            return false;
        return $this;
    }
 
    function mayChangePass() {
        return $this->_may_change;
    }

    function userExists() {
        if (!$this->isValidName()) {
            return $this->_tryNextUser();
        }
        $this->_authmethod = 'File';
        if (isset($this->_file->users[$this->_userid]))
            return true;
            
        return $this->_tryNextUser();
    }

    function checkPass($submitted_password) {
        if (!$this->isValidName()) {
            trigger_error(_("Invalid username"),E_USER_WARNING);
            return $this->_tryNextPass($submitted_password);
        }
        //include_once 'lib/pear/File_Passwd.php';
        if ($this->_file->verifyPassword($this->_userid, $submitted_password)) {
            $this->_authmethod = 'File';
            $this->_level = WIKIAUTH_USER;
            if ($this->isAdmin()) // member of the Administrators group
                $this->_level = WIKIAUTH_ADMIN;
            return $this->_level;
        }
        
        return $this->_tryNextPass($submitted_password);
    }

    function storePass($submitted_password) {
        if (!$this->isValidName()) {
            return false;
        }
        if ($this->_may_change) {
            $this->_file = new File_Passwd($this->_file->_filename, true, 
                                           $this->_file->_filename.'.lock');
            $result = $this->_file->modUser($this->_userid,$submitted_password);
            $this->_file->close();
            $this->_file = new File_Passwd($this->_file->_filename, false);
            return $result;
        }
        return false;
    }

}

/**
 * Insert more auth classes here...
 * For example a customized db class for another db connection 
 * or a socket-based auth server.
 *
 */


/**
 * For security, this class should not be extended. Instead, extend
 * from _PassUser (think of this as unix "root").
 *
 * FIXME: This should be a singleton class. Only ADMIN_USER may be of class AdminUser!
 * Other members of the Administrators group must raise their level otherwise somehow.
 * Currently every member is a AdminUser, which will not work for the various 
 * storage methods.
 */
class _AdminUser
extends _PassUser
{
    function mayChangePass() {
        return false;
    }
    function checkPass($submitted_password) {
    	if ($this->_userid == ADMIN_USER)
            $stored_password = ADMIN_PASSWD;
        else {
            return $this->_tryNextPass($submitted_password);
            // TODO: safety check if really member of the ADMIN group?
            $stored_password = $this->_pref->get('passwd');
        }
        if ($this->_checkPass($submitted_password, $stored_password)) {
            $this->_level = WIKIAUTH_ADMIN;
            return $this->_level;
        } else {
            return $this->_tryNextPass($submitted_password);
            //$this->_level = WIKIAUTH_ANON;
            //return $this->_level;
        }
        
    }
    function storePass($submitted_password) {
        return false;
    }
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/**
 * Various data classes for the preference types, 
 * to support get, set, sanify (range checking, ...)
 * update() will do the neccessary side-effects if a 
 * setting gets changed (theme, language, ...)
*/

class _UserPreference
{
    var $default_value;

    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string)$value;
    }

    function get ($name) {
    	if (isset($this->{$name}))
	    return $this->{$name};
    	else 
            return $this->default_value;
    }

    function getraw ($name) {
    	if (!empty($this->{$name}))
	    return $this->{$name};
    }

    // stores the value as $this->$name, and not as $this->value (clever?)
    function set ($name, $value) {
    	$return = 0;
    	$value = $this->sanify($value);
	if ($this->get($name) != $value) {
	    $this->update($value);
	    $return = 1;
	}
	if ($value != $this->default_value) {
	    $this->{$name} = $value;
        } else {
            unset($this->{$name});
        }
        return $return;
    }

    // default: no side-effects 
    function update ($value) {
    	;
    }
}

class _UserPreference_numeric
extends _UserPreference
{
    function _UserPreference_numeric ($default, $minval = false,
                                      $maxval = false) {
        $this->_UserPreference((double)$default);
        $this->_minval = (double)$minval;
        $this->_maxval = (double)$maxval;
    }

    function sanify ($value) {
        $value = (double)$value;
        if ($this->_minval !== false && $value < $this->_minval)
            $value = $this->_minval;
        if ($this->_maxval !== false && $value > $this->_maxval)
            $value = $this->_maxval;
        return $value;
    }
}

class _UserPreference_int
extends _UserPreference_numeric
{
    function _UserPreference_int ($default, $minval = false, $maxval = false) {
        $this->_UserPreference_numeric((int)$default, (int)$minval, (int)$maxval);
    }

    function sanify ($value) {
        return (int)parent::sanify((int)$value);
    }
}

class _UserPreference_bool
extends _UserPreference
{
    function _UserPreference_bool ($default = false) {
        $this->_UserPreference((bool)$default);
    }

    function sanify ($value) {
        if (is_array($value)) {
            /* This allows for constructs like:
             *
             *   <input type="hidden" name="pref[boolPref][]" value="0" />
             *   <input type="checkbox" name="pref[boolPref][]" value="1" />
             *
             * (If the checkbox is not checked, only the hidden input
             * gets sent. If the checkbox is sent, both inputs get
             * sent.)
             */
            foreach ($value as $val) {
                if ($val)
                    return true;
            }
            return false;
        }
        return (bool) $value;
    }
}

class _UserPreference_language
extends _UserPreference
{
    function _UserPreference_language ($default = DEFAULT_LANGUAGE) {
        $this->_UserPreference($default);
    }

    // FIXME: check for valid locale
    function sanify ($value) {
        // Revert to DEFAULT_LANGUAGE if user does not specify
        // language in UserPreferences or chooses <system language>.
        if ($value == '' or empty($value))
            $value = DEFAULT_LANGUAGE;

        return (string) $value;
    }
    
    function update ($newvalue) {
        if (! $this->_init ) {
            // invalidate etag to force fresh output
            $GLOBALS['request']->setValidators(array('%mtime' => false));
            update_locale($newvalue ? $newvalue : $GLOBALS['LANG']);
        }
    }
}

class _UserPreference_theme
extends _UserPreference
{
    function _UserPreference_theme ($default = THEME) {
        $this->_UserPreference($default);
    }

    function sanify ($value) {
        if (!empty($value) and FindFile($this->_themefile($value)))
            return $value;
        return $this->default_value;
    }

    function update ($newvalue) {
        global $WikiTheme;
        // invalidate etag to force fresh output
        if (! $this->_init )
            $GLOBALS['request']->setValidators(array('%mtime' => false));
        if ($newvalue)
            include_once($this->_themefile($newvalue));
        if (empty($WikiTheme))
            include_once($this->_themefile(THEME));
    }

    function _themefile ($theme) {
        return "themes/$theme/themeinfo.php";
    }
}

class _UserPreference_notify
extends _UserPreference
{
    function sanify ($value) {
    	if (!empty($value))
            return $value;
        else
            return $this->default_value;
    }

    /** update to global user prefs: side-effect on set notify changes
     * use a global_data notify hash:
     * notify = array('pagematch' => array(userid => ('email' => mail, 
     *                                                'verified' => 0|1),
     *                                     ...),
     *                ...);
     */
    function update ($value) {
    	if (!empty($this->_init)) return;
        $dbh = $GLOBALS['request']->getDbh();
        $notify = $dbh->get('notify');
        if (empty($notify))
            $data = array();
        else 
            $data = & $notify;
        // expand to existing pages only or store matches?
        // for now we store (glob-style) matches which is easier for the user
        $pages = $this->_page_split($value);
        // Limitation: only current user.
        $user = $GLOBALS['request']->getUser();
        if (!$user or !method_exists($user,'UserName')) return;
        // This fails with php5 and a WIKI_ID cookie:
        $userid = $user->UserName();
        $email  = $user->_prefs->get('email');
        $verified = $user->_prefs->_prefs['email']->getraw('emailVerified');
        // check existing notify hash and possibly delete pages for email
        if (!empty($data)) {
            foreach ($data as $page => $users) {
                if (isset($data[$page][$userid]) and !in_array($page, $pages)) {
                    unset($data[$page][$userid]);
                }
                if (count($data[$page]) == 0)
                    unset($data[$page]);
            }
        }
        // add the new pages
        if (!empty($pages)) {
            foreach ($pages as $page) {
                if (!isset($data[$page]))
                    $data[$page] = array();
                if (!isset($data[$page][$userid])) {
                    // should we really store the verification notice here or 
                    // check it dynamically at every page->save?
                    if ($verified) {
                        $data[$page][$userid] = array('email' => $email,
                                                      'verified' => $verified);
                    } else {
                        $data[$page][$userid] = array('email' => $email);
                    }
                }
            }
        }
        // store users changes
        $dbh->set('notify',$data);
    }

    /** split the user-given comma or whitespace delimited pagenames
     *  to array
     */
    function _page_split($value) {
        return preg_split('/[\s,]+/',$value,-1,PREG_SPLIT_NO_EMPTY);
    }
}

class _UserPreference_email
extends _UserPreference
{
    function sanify($value) {
        // check for valid email address
        if ($this->get('email') == $value and $this->getraw('emailVerified'))
            return $value;
        // hack!
        if ($value == 1 or $value === true)
            return $value;
        list($ok,$msg) = ValidateMail($value,'noconnect');
        if ($ok) {
            return $value;
        } else {
            trigger_error("E-Mail Validation Error: ".$msg, E_USER_WARNING);
            return $this->default_value;
        }
    }
    
    /** Side-effect on email changes:
     * Send a verification mail or for now just a notification email.
     * For true verification (value = 2), we'd need a mailserver hook.
     */
    function update($value) {
    	if (!empty($this->_init)) return;
        $verified = $this->getraw('emailVerified');
        // hack!
        if (($value == 1 or $value === true) and $verified)
            return;
        if (!empty($value) and !$verified) {
            list($ok,$msg) = ValidateMail($value);
            if ($ok and mail($value,"[".WIKI_NAME ."] "._("Email Verification"),
                     sprintf(_("Welcome to %s!\nYour email account is verified and\nwill be used to send page change notifications.\nSee %s"),
                             WIKI_NAME, WikiURL($GLOBALS['request']->getArg('pagename'),'',true))))
                $this->set('emailVerified',1);
        }
    }
}

/** Check for valid email address
    fixed version from http://www.zend.com/zend/spotlight/ev12apr.php
 */
function ValidateMail($email, $noconnect=false) {
    $HTTP_HOST = $GLOBALS['request']->get('HTTP_HOST');
    $result = array();
    // well, technically ".a.a.@host.com" is also valid
    if (!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email)) {
        $result[0] = false;
        $result[1] = sprintf(_("E-Mail address '%s' is not properly formatted"), $email);
        return $result;
    }
    if ($noconnect)
      return array(true,sprintf(_("E-Mail address '%s' is properly formatted"), $email));

    list ( $Username, $Domain ) = split ("@", $email);
    //Todo: getmxrr workaround on windows or manual input field to verify it manually
    if (!isWindows() and getmxrr($Domain, $MXHost)) { // avoid warning on Windows. 
        $ConnectAddress = $MXHost[0];
    } else {
        $ConnectAddress = $Domain;
    }
    $Connect = @fsockopen ( $ConnectAddress, 25 );
    if ($Connect) {
        if (ereg("^220", $Out = fgets($Connect, 1024))) {
            fputs ($Connect, "HELO $HTTP_HOST\r\n");
            $Out = fgets ( $Connect, 1024 );
            fputs ($Connect, "MAIL FROM: <".$email.">\r\n");
            $From = fgets ( $Connect, 1024 );
            fputs ($Connect, "RCPT TO: <".$email.">\r\n");
            $To = fgets ($Connect, 1024);
            fputs ($Connect, "QUIT\r\n");
            fclose($Connect);
            if (!ereg ("^250", $From)) {
                $result[0]=false;
                $result[1]="Server rejected address: ". $From;
                return $result;
            }
            if (!ereg ( "^250", $To )) {
                $result[0]=false;
                $result[1]="Server rejected address: ". $To;
                return $result;
            }
        } else {
            $result[0] = false;
            $result[1] = "No response from server";
            return $result;
          }
    }  else {
        $result[0]=false;
        $result[1]="Can not connect E-Mail server.";
        return $result;
    }
    $result[0]=true;
    $result[1]="E-Mail address '$email' appears to be valid.";
    return $result;
} // end of function 

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * UserPreferences
 * 
 * This object holds the $request->_prefs subobjects.
 * A simple packed array of non-default values get's stored as cookie,
 * homepage, or database, which are converted to the array of 
 * ->_prefs objects.
 * We don't store the objects, because otherwise we will
 * not be able to upgrade any subobject. And it's a waste of space also.
 *
 */
class UserPreferences
{
    function UserPreferences($saved_prefs = false) {
        // userid stored too, to ensure the prefs are being loaded for
        // the correct (currently signing in) userid if stored in a
        // cookie.
        // Update: for db prefs we disallow passwd. 
        // userid is needed for pref reflexion. current pref must know its username, 
        // if some app needs prefs from different users, different from current user.
        $this->_prefs
            = array(
                    'userid'        => new _UserPreference(''),
                    'passwd'        => new _UserPreference(''),
                    'autologin'     => new _UserPreference_bool(),
                    //'emailVerified' => new _UserPreference_emailVerified(), 
                    //fixed: store emailVerified as email parameter, 1.3.8
                    'email'         => new _UserPreference_email(''),
                    'notifyPages'   => new _UserPreference_notify(''), // 1.3.8
                    'theme'         => new _UserPreference_theme(THEME),
                    'lang'          => new _UserPreference_language(DEFAULT_LANGUAGE),
                    'editWidth'     => new _UserPreference_int(EDITWIDTH_DEFAULT_COLS,
                                                               EDITWIDTH_MIN_COLS,
                                                               EDITWIDTH_MAX_COLS),
                    'noLinkIcons'   => new _UserPreference_bool(),    // 1.3.8 
                    'editHeight'    => new _UserPreference_int(EDITHEIGHT_DEFAULT_ROWS,
                                                               EDITHEIGHT_MIN_ROWS,
                                                               EDITHEIGHT_DEFAULT_ROWS),
                    'timeOffset'    => new _UserPreference_numeric(TIMEOFFSET_DEFAULT_HOURS,
                                                                   TIMEOFFSET_MIN_HOURS,
                                                                   TIMEOFFSET_MAX_HOURS),
                    'relativeDates' => new _UserPreference_bool(),
                    'googleLink'    => new _UserPreference_bool(), // 1.3.10
                    );
        // add custom theme-specific pref types:
        // FIXME: on theme changes the wiki_user session pref object will fail. 
        // We will silently ignore this.
        if (!empty($customUserPreferenceColumns))
            $this->_prefs = array_merge($this->_prefs,$customUserPreferenceColumns);
/*
        if (isset($this->_method) and $this->_method == 'SQL') {
            //unset($this->_prefs['userid']);
            unset($this->_prefs['passwd']);
        }
*/
        if (is_array($saved_prefs)) {
            foreach ($saved_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref($name) {
    	if ($name == 'emailVerified')
    	    $name = 'email';
        if (!isset($this->_prefs[$name])) {
            if ($name == 'passwd2') return false;
            if ($name == 'passwd') return false;
            trigger_error("$name: unknown preference", E_USER_NOTICE);
            return false;
        }
        return $this->_prefs[$name];
    }
    
    // get the value or default_value of the subobject
    function get($name) {
    	if ($_pref = $this->_getPref($name))
    	    if ($name == 'emailVerified')
    	        return $_pref->getraw($name);
    	    else
    	        return $_pref->get($name);
    	else
    	    return false;  
    }

    // check and set the new value in the subobject
    function set($name, $value) {
        $pref = $this->_getPref($name);
        if ($pref === false)
            return false;

        /* do it here or outside? */
        if ($name == 'passwd' and 
            defined('PASSWORD_LENGTH_MINIMUM') and 
            strlen($value) <= PASSWORD_LENGTH_MINIMUM ) {
            //TODO: How to notify the user?
            return false;
        }
        /*
        if ($name == 'theme' and $value == '')
           return true;
        */
        if (!isset($pref->{$value}) or $pref->{$value} != $pref->default_value) {
            if ($name == 'emailVerified') $newvalue = $value;
            else $newvalue = $pref->sanify($value);
	    $pref->set($name,$newvalue);
        }
        $this->_prefs[$name] =& $pref;
        return true;
    }
    /**
     * use init to avoid update on set
     */
    function updatePrefs($prefs, $init = false) {
        $count = 0;
        if ($init) $this->_init = $init;
        if (is_object($prefs)) {
            $type = 'emailVerified'; $obj =& $this->_prefs['email'];
            $obj->_init = $init;
            if ($obj->get($type) !== $prefs->get($type)) {
                if ($obj->set($type,$prefs->get($type)))
                    $count++;
            }
            foreach (array_keys($this->_prefs) as $type) {
            	$obj =& $this->_prefs[$type];
                $obj->_init = $init;
                if ($prefs->get($type) !== $obj->get($type)) {
                    // special systemdefault prefs: (probably not needed)
                    if ($type == 'theme' and $prefs->get($type) == '' and 
                        $obj->get($type) == THEME) continue;
                    if ($type == 'lang' and $prefs->get($type) == '' and 
                        $obj->get($type) == DEFAULT_LANGUAGE) continue;
                    if ($this->_prefs[$type]->set($type,$prefs->get($type)))
                        $count++;
                }
            }
        } elseif (is_array($prefs)) {
            //unset($this->_prefs['userid']);
            /*
	    if (isset($this->_method) and 
	         ($this->_method == 'SQL' or $this->_method == 'ADODB')) {
                unset($this->_prefs['passwd']);
	    }
	    */
	    // emailVerified at first, the rest later
            $type = 'emailVerified'; $obj =& $this->_prefs['email'];
            $obj->_init = $init;
            if (isset($prefs[$type]) and $obj->get($type) !== $prefs[$type]) {
                if ($obj->set($type,$prefs[$type]))
                    $count++;
            }
            foreach (array_keys($this->_prefs) as $type) {
            	$obj =& $this->_prefs[$type];
                $obj->_init = $init;
                if (!isset($prefs[$type]) and isa($obj,"_UserPreference_bool")) 
                    $prefs[$type] = false;
                if (isset($prefs[$type]) and isa($obj,"_UserPreference_int"))
                    $prefs[$type] = (int) $prefs[$type];
                if (isset($prefs[$type]) and $obj->get($type) != $prefs[$type]) {
                    // special systemdefault prefs:
                    if ($type == 'theme' and $prefs[$type] == '' and 
                        $obj->get($type) == THEME) continue;
                    if ($type == 'lang' and $prefs[$type] == '' and 
                        $obj->get($type) == DEFAULT_LANGUAGE) continue;
                    if ($obj->set($type,$prefs[$type]))
                        $count++;
                }
            }
        }
        return $count;
    }

    // For now convert just array of objects => array of values
    // Todo: the specialized subobjects must override this.
    function store() {
        $prefs = array();
        foreach ($this->_prefs as $name => $object) {
            if ($value = $object->getraw($name))
                $prefs[$name] = $value;
            if ($name == 'email' and ($value = $object->getraw('emailVerified')))
                $prefs['emailVerified'] = $value;
            if ($name == 'passwd' and $value and ENCRYPTED_PASSWD) {
            	$prefs['passwd'] = crypt($value);
            }
        }
        return $this->pack($prefs);
    }

    // packed string or array of values => array of values
    // Todo: the specialized subobjects must override this.
    function retrieve($packed) {
        if (is_string($packed) and (substr($packed, 0, 2) == "a:"))
            $packed = unserialize($packed);
        if (!is_array($packed)) return false;
        $prefs = array();
        foreach ($packed as $name => $packed_pref) {
            if (is_string($packed_pref) and substr($packed_pref, 0, 2) == "O:") {
                //legacy: check if it's an old array of objects
                // Looks like a serialized object. 
                // This might fail if the object definition does not exist anymore.
                // object with ->$name and ->default_value vars.
                $pref =  @unserialize($packed_pref);
                if (empty($pref))
                    $pref = @unserialize(base64_decode($packed_pref));
                $prefs[$name] = $pref->get($name);
            // fix old-style prefs
            } elseif (is_numeric($name) and is_array($packed_pref)) {
            	if (count($packed_pref) == 1) {
            	    list($name,$value) = each($packed_pref);
            	    $prefs[$name] = $value;
            	}
            } else {
                $prefs[$name] = @unserialize($packed_pref);
                if (empty($prefs[$name]))
                    $prefs[$name] = @unserialize(base64_decode($packed_pref));
                // patched by frederik@pandora.be
                if (empty($prefs[$name]))
                    $prefs[$name] = $packed_pref;
            }
        }
        return $prefs;
    }

    /**
     * Check if the given prefs object is different from the current prefs object
     */
    function isChanged($other) {
        foreach ($this->_prefs as $type => $obj) {
            if ($obj->get($type) !== $other->get($type))
                return true;
        }
        return false;
    }

    function defaultPreferences() {
    	$prefs = array();
    	foreach ($this->_prefs as $key => $obj) {
    	    $prefs[$key] = $obj->default_value;
    	}
    	return $prefs;
    }
    
    // array of objects
    function getAll() {
        return $this->_prefs;
    }

    function pack($nonpacked) {
        return serialize($nonpacked);
    }

    function unpack($packed) {
        if (!$packed)
            return false;
        //$packed = base64_decode($packed);
        if (substr($packed, 0, 2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        if (substr($packed, 0, 2) == "a:") {
            return unserialize($packed);
        }
        //trigger_error("DEBUG: Can't unpack bad UserPreferences",
        //E_USER_WARNING);
        return false;
    }

    function hash () {
        return hash($this->_prefs);
    }
}

/** TODO: new pref storage classes
 *  These are currently user specific and should be rewritten to be pref specific.
 *  i.e. $this == $user->_prefs
 */
class CookieUserPreferences
extends UserPreferences
{
    function CookieUserPreferences ($saved_prefs = false) {
    	//_AnonUser::_AnonUser('',$saved_prefs);
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class PageUserPreferences
extends UserPreferences
{
    function PageUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class PearDbUserPreferences
extends UserPreferences
{
    function PearDbUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
}

class AdoDbUserPreferences
extends UserPreferences
{
    function AdoDbUserPreferences ($saved_prefs = false) {
        UserPreferences::UserPreferences($saved_prefs);
    }
    function getPreferences() {
        // override the generic slow method here for efficiency
        _AnonUser::getPreferences();
        $this->getAuthDbh();
        if (isset($this->_select)) {
            $dbh = & $this->_auth_dbi;
            $rs = $dbh->Execute(sprintf($this->_select,$dbh->qstr($this->_userid)));
            if ($rs->EOF) {
                $rs->Close();
            } else {
                $prefs_blob = $rs->fields['pref_blob'];
                $rs->Close();
                if ($restored_from_db = $this->_prefs->retrieve($prefs_blob)) {
                    $updated = $this->_prefs->updatePrefs($restored_from_db);
                    //$this->_prefs = new UserPreferences($restored_from_db);
                    return $this->_prefs;
                }
            }
        }
        if (empty($this->_prefs->_prefs) and $this->_HomePagehandle) {
            if ($restored_from_page = $this->_prefs->retrieve
                ($this->_HomePagehandle->get('pref'))) {
                $updated = $this->_prefs->updatePrefs($restored_from_page);
                //$this->_prefs = new UserPreferences($restored_from_page);
                return $this->_prefs;
            }
        }
        return $this->_prefs;
    }
}


// $Log: not supported by cvs2svn $
// Revision 1.108  2004/10/05 17:00:04  rurban
// support paging for simple lists
// fix RatingDb sql backend.
// remove pages from AllPages (this is ListPages then)
//
// Revision 1.107  2004/10/04 23:42:15  rurban
// HttpAuth admin group logic. removed old logs
//
// Revision 1.106  2004/07/01 08:49:38  rurban
// obsolete php5-patch.php: minor php5 login problem though
//
// Revision 1.105  2004/06/29 06:48:03  rurban
// Improve LDAP auth and GROUP_LDAP membership:
//   no error message on false password,
//   added two new config vars: LDAP_OU_USERS and LDAP_OU_GROUP with GROUP_METHOD=LDAP
//   fixed two group queries (this -> user)
// stdlib: ConvertOldMarkup still flawed
//
// Revision 1.104  2004/06/28 15:39:37  rurban
// fixed endless recursion in WikiGroup: isAdmin()
//
// Revision 1.103  2004/06/28 15:01:07  rurban
// fixed LDAP_SET_OPTION handling, LDAP error on connection problem
//
// Revision 1.102  2004/06/27 10:23:48  rurban
// typo detected by Philippe Vanhaesendonck
//
// Revision 1.101  2004/06/25 14:29:19  rurban
// WikiGroup refactoring:
//   global group attached to user, code for not_current user.
//   improved helpers for special groups (avoid double invocations)
// new experimental config option ENABLE_XHTML_XML (fails with IE, and document.write())
// fixed a XHTML validation error on userprefs.tmpl
//
// Revision 1.100  2004/06/21 06:29:35  rurban
// formatting: linewrap only
//
// Revision 1.99  2004/06/20 15:30:05  rurban
// get_class case-sensitivity issues
//
// Revision 1.98  2004/06/16 21:24:31  rurban
// do not display no-connect warning: #2662
//
// Revision 1.97  2004/06/16 13:21:16  rurban
// stabilize on failing ldap queries or bind
//
// Revision 1.96  2004/06/16 12:42:06  rurban
// fix homepage prefs
//
// Revision 1.95  2004/06/16 10:38:58  rurban
// Disallow refernces in calls if the declaration is a reference
// ("allow_call_time_pass_reference clean").
//   PhpWiki is now allow_call_time_pass_reference = Off clean,
//   but several external libraries may not.
//   In detail these libs look to be affected (not tested):
//   * Pear_DB odbc
//   * adodb oracle
//
// Revision 1.94  2004/06/15 10:40:35  rurban
// minor WikiGroup cleanup: no request param, start of current user independency
//
// Revision 1.93  2004/06/15 09:15:52  rurban
// IMPORTANT: fixed passwd handling for passwords stored in prefs:
//   fix encrypted usage, actually store and retrieve them from db
//   fix bogologin with passwd set.
// fix php crashes with call-time pass-by-reference (references wrongly used
//   in declaration AND call). This affected mainly Apache2 and IIS.
//   (Thanks to John Cole to detect this!)
//
// Revision 1.92  2004/06/14 11:31:36  rurban
// renamed global $Theme to $WikiTheme (gforge nameclash)
// inherit PageList default options from PageList
//   default sortby=pagename
// use options in PageList_Selectable (limit, sortby, ...)
// added action revert, with button at action=diff
// added option regex to WikiAdminSearchReplace
//
// Revision 1.91  2004/06/08 14:57:43  rurban
// stupid ldap bug detected by John Cole
//
// Revision 1.90  2004/06/08 09:31:15  rurban
// fixed typo detected by lucidcarbon (line 1663 assertion)
//
// Revision 1.89  2004/06/06 16:58:51  rurban
// added more required ActionPages for foreign languages
// install now english ActionPages if no localized are found. (again)
// fixed default anon user level to be 0, instead of -1
//   (wrong "required administrator to view this page"...)
//
// Revision 1.88  2004/06/04 20:32:53  rurban
// Several locale related improvements suggested by Pierrick Meignen
// LDAP fix by John Cole
// reanable admin check without ENABLE_PAGEPERM in the admin plugins
//
// Revision 1.87  2004/06/04 12:40:21  rurban
// Restrict valid usernames to prevent from attacks against external auth or compromise
// possible holes.
// Fix various WikiUser old issues with default IMAP,LDAP,POP3 configs. Removed these.
// Fxied more warnings
//
// Revision 1.86  2004/06/03 18:06:29  rurban
// fix file locking issues (only needed on write)
// fixed immediate LANG and THEME in-session updates if not stored in prefs
// advanced editpage toolbars (search & replace broken)
//
// Revision 1.85  2004/06/03 12:46:03  rurban
// fix signout, level must be 0 not -1
//
// Revision 1.84  2004/06/03 12:36:03  rurban
// fix eval warning on signin
//
// Revision 1.83  2004/06/03 10:18:19  rurban
// fix User locking issues, new config ENABLE_PAGEPERM
//
// Revision 1.82  2004/06/03 09:39:51  rurban
// fix LDAP injection (wildcard in username) detected by Steve Christey, MITRE
//
// Revision 1.81  2004/06/02 18:01:45  rurban
// init global FileFinder to add proper include paths at startup
//   adds PHPWIKI_DIR if started from another dir, lib/pear also
// fix slashify for Windows
// fix USER_AUTH_POLICY=old, use only USER_AUTH_ORDER methods (besides HttpAuth)
//
// Revision 1.80  2004/06/02 14:20:27  rurban
// fix adodb DbPassUser login
//
// Revision 1.79  2004/06/01 15:27:59  rurban
// AdminUser only ADMIN_USER not member of Administrators
// some RateIt improvements by dfrankow
// edit_toolbar buttons
//
// Revision 1.78  2004/05/27 17:49:06  rurban
// renamed DB_Session to DbSession (in CVS also)
// added WikiDB->getParam and WikiDB->getAuthParam method to get rid of globals
// remove leading slash in error message
// added force_unlock parameter to File_Passwd (no return on stale locks)
// fixed adodb session AffectedRows
// added FileFinder helpers to unify local filenames and DATA_PATH names
// editpage.php: new edit toolbar javascript on ENABLE_EDIT_TOOLBAR
//
// Revision 1.77  2004/05/18 14:49:51  rurban
// Simplified strings for easier translation
//
// Revision 1.76  2004/05/18 13:30:04  rurban
// prevent from endless loop with oldstyle warnings
//
// Revision 1.75  2004/05/16 22:07:35  rurban
// check more config-default and predefined constants
// various PagePerm fixes:
//   fix default PagePerms, esp. edit and view for Bogo and Password users
//   implemented Creator and Owner
//   BOGOUSERS renamed to BOGOUSER
// fixed syntax errors in signin.tmpl
//
// Revision 1.74  2004/05/15 19:48:33  rurban
// fix some too loose PagePerms for signed, but not authenticated users
//  (admin, owner, creator)
// no double login page header, better login msg.
// moved action_pdf to lib/pdf.php
//
// Revision 1.73  2004/05/15 18:31:01  rurban
// some action=pdf Request fixes: With MSIE it works now. Now the work with the page formatting begins.
//
// Revision 1.72  2004/05/12 10:49:55  rurban
// require_once fix for those libs which are loaded before FileFinder and
//   its automatic include_path fix, and where require_once doesn't grok
//   dirname(__FILE__) != './lib'
// upgrade fix with PearDB
// navbar.tmpl: remove spaces for IE &nbsp; button alignment
//
// Revision 1.71  2004/05/10 12:34:47  rurban
// stabilize DbAuthParam statement pre-prozessor:
//   try old-style and new-style (double-)quoting
//   reject unknown $variables
//   use ->prepare() for all calls (again)
//
// Revision 1.70  2004/05/06 19:26:16  rurban
// improve stability, trying to find the InlineParser endless loop on sf.net
//
// remove end-of-zip comments to fix sf.net bug #777278 and probably #859628
//
// Revision 1.69  2004/05/06 13:56:40  rurban
// Enable the Administrators group, and add the WIKIPAGE group default root page.
//
// Revision 1.68  2004/05/05 13:37:54  rurban
// Support to remove all UserPreferences
//
// Revision 1.66  2004/05/03 21:44:24  rurban
// fixed sf,net bug #947264: LDAP options are constants, not strings!
//
// Revision 1.65  2004/05/03 13:16:47  rurban
// fixed UserPreferences update, esp for boolean and int
//
// Revision 1.64  2004/05/02 15:10:06  rurban
// new finally reliable way to detect if /index.php is called directly
//   and if to include lib/main.php
// new global AllActionPages
// SetupWiki now loads all mandatory pages: HOME_PAGE, action pages, and warns if not.
// WikiTranslation what=buttons for Carsten to create the missing MacOSX buttons
// PageGroupTestOne => subpages
// renamed PhpWikiRss to PhpWikiRecentChanges
// more docs, default configs, ...
//
// Revision 1.63  2004/05/01 15:59:29  rurban
// more php-4.0.6 compatibility: superglobals
//
// Revision 1.62  2004/04/29 18:31:24  rurban
// Prevent from warning where no db pref was previously stored.
//
// Revision 1.61  2004/04/29 17:18:19  zorloc
// Fixes permission failure issues.  With PagePermissions and Disabled Actions when user did not have permission WIKIAUTH_FORBIDDEN was returned.  In WikiUser this was ok because WIKIAUTH_FORBIDDEN had a value of 11 -- thus no user could perform that action.  But WikiUserNew has a WIKIAUTH_FORBIDDEN value of -1 -- thus a user without sufficent permission to do anything.  The solution is a new high value permission level (WIKIAUTH_UNOBTAINABLE) to be the default level for access failure.
//
// Revision 1.60  2004/04/27 18:20:54  rurban
// sf.net patch #940359 by rassie
//
// Revision 1.59  2004/04/26 12:35:21  rurban
// POP3_AUTH_PORT deprecated, use "host:port" similar to IMAP
// File_Passwd is already loaded
//
// Revision 1.58  2004/04/20 17:08:28  rurban
// Some IniConfig fixes: prepend our private lib/pear dir
//   switch from " to ' in the auth statements
//   use error handling.
// WikiUserNew changes for the new "'$variable'" syntax
//   in the statements
// TODO: optimization to put config vars into the session.
//
// Revision 1.57  2004/04/19 18:27:45  rurban
// Prevent from some PHP5 warnings (ref args, no :: object init)
//   php5 runs now through, just one wrong XmlElement object init missing
// Removed unneccesary UpgradeUser lines
// Changed WikiLink to omit version if current (RecentChanges)
//
// Revision 1.56  2004/04/19 09:13:24  rurban
// new pref: googleLink
//
// Revision 1.54  2004/04/18 00:24:45  rurban
// re-use our simple prepare: just for table prefix warnings
//
// Revision 1.53  2004/04/12 18:29:15  rurban
// exp. Session auth for already authenticated users from another app
//
// Revision 1.52  2004/04/12 13:04:50  rurban
// added auth_create: self-registering Db users
// fixed IMAP auth
// removed rating recommendations
// ziplib reformatting
//
// Revision 1.51  2004/04/11 10:42:02  rurban
// pgsrc/CreatePagePlugin
//
// Revision 1.50  2004/04/10 05:34:35  rurban
// sf bug#830912
//
// Revision 1.49  2004/04/07 23:13:18  rurban
// fixed pear/File_Passwd for Windows
// fixed FilePassUser sessions (filehandle revive) and password update
//
// Revision 1.48  2004/04/06 20:00:10  rurban
// Cleanup of special PageList column types
// Added support of plugin and theme specific Pagelist Types
// Added support for theme specific UserPreferences
// Added session support for ip-based throttling
//   sql table schema change: ALTER TABLE session ADD sess_ip CHAR(15);
// Enhanced postgres schema
// Added DB_Session_dba support
//
// Revision 1.47  2004/04/02 15:06:55  rurban
// fixed a nasty ADODB_mysql session update bug
// improved UserPreferences layout (tabled hints)
// fixed UserPreferences auth handling
// improved auth stability
// improved old cookie handling: fixed deletion of old cookies with paths
//
// Revision 1.46  2004/04/01 06:29:51  rurban
// better wording
// RateIt also for ADODB
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
