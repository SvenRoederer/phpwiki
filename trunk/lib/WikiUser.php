<?php rcs_id('$Id: WikiUser.php,v 1.26 2002-09-18 22:11:21 dairiki Exp $');

// It is anticipated that when userid support is added to phpwiki,
// this object will hold much more information (e-mail, home(wiki)page,
// etc.) about the user.
   
// There seems to be no clean way to "log out" a user when using
// HTTP authentication.
// So we'll hack around this by storing the currently logged
// in username and other state information in a cookie.

// 2002-09-08 11:44:04 rurban
// Todo: Fix prefs cookie/session handling:
//       _userid and _homepage cookie/session vars still hold the serialized string.
//       If no homepage, fallback to prefs in cookie as in 1.3.3.


define('WIKIAUTH_ANON', 0);
define('WIKIAUTH_BOGO', 1);     // any valid WikiWord is enough
define('WIKIAUTH_USER', 2);     // real auth from a database/file/server.

define('WIKIAUTH_ADMIN', 10);
define('WIKIAUTH_FORBIDDEN', 11); // Completely not allowed.

$UserPreferences = array(
                         'userid'        => new _UserPreference(''), // really store this also?
                         'passwd'        => new _UserPreference(''),
                         'email'         => new _UserPreference(''),
                         'emailVerified' => new _UserPreference_bool(),
                         'notifyPages'   => new _UserPreference(''),
                         'theme'         => new _UserPreference_theme(THEME),
                         'lang'          => new _UserPreference_language(DEFAULT_LANGUAGE),
                         'editWidth'     => new _UserPreference_int(80, 30, 150),
                         'editHeight'    => new _UserPreference_int(22, 5, 80),
                         'timeOffset'    => new _UserPreference_numeric(0, -26, 26),
                         'relativeDates' => new _UserPreference_bool()
                         );

class WikiUser {
    var $_userid = false;
    var $_level  = false;
    var $_request, $_dbi, $_authdbi, $_homepage;
    var $_authmethod = '', $_authhow = '';

    /**
     * Constructor.
     */
    function WikiUser ($userid = false, $authlevel = false) {
        $this->_request = &$GLOBALS['request'];
        $this->_dbi = &$this->_request->getDbh();

        if (isa($userid, 'WikiUser')) {
            $this->_userid   = $userid->_userid;
            $this->_level    = $userid->_level;
        }
        else {
            $this->_userid = $userid;
            $this->_level = $authlevel;
        }
	if ($this->_userid)
    	    $this->_homepage = $this->_dbi->getPage($this->_userid);
        if (!$this->_ok()) {
            // Paranoia: if state is at all inconsistent, log out...
            $this->_userid = false;
            $this->_level = false;
            $this->_homepage = false;
            $this->_authhow .= ' paranoia logout';
        }
    }

    function auth_how() {
        return $this->_authhow;
    }

    /** Invariant
     */
    function _ok () {
        if (empty($this->_userid) || empty($this->_level)) {
            // This is okay if truly logged out.
            return $this->_userid === false && $this->_level === false;
        }
        // User is logged in...
        
        // Check for valid authlevel.
        if (!in_array($this->_level, array(WIKIAUTH_BOGO, WIKIAUTH_USER, WIKIAUTH_ADMIN)))
            return false;

        // Check for valid userid.
        if (!is_string($this->_userid))
            return false;
        return true;
    }

    function getId () {
        return ( $this->isSignedIn()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : $this->_request->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function isSignedIn () {
        return $this->_level >= WIKIAUTH_BOGO;
    }
        
    function isAuthenticated () {
        return $this->_level >= WIKIAUTH_USER;
    }
	 
    function isAdmin () {
        return $this->_level == WIKIAUTH_ADMIN;
    }

    function hasAuthority ($require_level) {
        return $this->_level >= $require_level;
    }

    function AuthCheck ($postargs) {
        // Normalize args, and extract.
        $keys = array('userid', 'passwd', 'require_level', 'login', 'logout', 'cancel');
        foreach ($keys as $key) 
            $args[$key] = isset($postargs[$key]) ? $postargs[$key] : false;
        extract($args);
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int) $require_level));

        if ($logout)
            return new WikiUser; // Log out
        elseif ($cancel)
            return false;        // User hit cancel button.
        elseif (!$login && !$userid)
            return false;       // Nothing to do?

        $authlevel = $this->_pwcheck($userid, $passwd);
        if (!$authlevel)
            return _("Invalid password or userid.");
        elseif ($authlevel < $require_level)
            return _("Insufficient permissions.");

        // Successful login.
        $user = new WikiUser;
        $user->_userid = $userid;
        $user->_level = $authlevel;
        return $user;
    }
    
    function PrintLoginForm (&$request, $args, $fail_message = false, $seperate_page = true) {
        include_once('lib/Template.php');
        
        $userid = '';
        $require_level = 0;
        extract($args); // fixme
        
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int) $require_level));
        
	$pagename = $request->getArg('pagename');
	$login = new Template('login', $request,
                              compact('pagename', 'userid', 'require_level', 'fail_message', 'pass_required'));
	if ($seperate_page) {
	    $top = new Template('html', $request, array('TITLE' =>  _("Sign In")));
	    return $top->printExpansion($login);
	} else {
	    return $login;
	}
    }
        
    /**
     * Check password.
     */
    function _pwcheck ($userid, $passwd) {
        global $WikiNameRegexp;
        
	if (!empty($userid) && $userid == ADMIN_USER) {
            // $this->_authmethod = 'pagedata';
            if (defined('ENCRYPTED_PASSWD') && ENCRYPTED_PASSWD)
                if (!empty($passwd) && crypt($passwd, ADMIN_PASSWD) == ADMIN_PASSWD)
                    return WIKIAUTH_ADMIN;
            if (!empty($passwd)) {
                if ($passwd == ADMIN_PASSWD)
                  return WIKIAUTH_ADMIN;
                else {
                    // maybe we forgot to enable ENCRYPTED_PASSWD?
                    if (function_exists('crypt') and crypt($passwd, ADMIN_PASSWD) == ADMIN_PASSWD) {
                        trigger_error(_("You forgot to set ENCRYPTED_PASSWD to true. Please update your /index.php"), E_USER_WARNING);
                        return WIKIAUTH_ADMIN;
                    }
                }
            }
            return false;
        }
	// HTTP Authentification
        elseif (ALLOW_HTTP_AUTH_LOGIN and !empty($PHP_AUTH_USER)) {
	    // if he ignored the password field, because he is already authentificated
	    // try the previously given password.
	    if (empty($passwd)) $passwd = $PHP_AUTH_PW;
	}

	// WikiDB_User DB/File Authentification from $DBAuthParams 
        // Check if we have the user. If not try other methods.
        if (ALLOW_USER_LOGIN) { // and !empty($passwd)) {
	    $request = $this->_request;
	    // first check if the user is known
	    if ($this->exists($userid)) {
                $this->_authmethod = 'pagedata';
		return ($this->checkPassword($passwd)) ? WIKIAUTH_USER : false;
	    } else {
		// else try others such as LDAP authentication:
		if (ALLOW_LDAP_LOGIN and !empty($passwd)) {
		    if ($ldap = ldap_connect(LDAP_AUTH_HOST)) { // must be a valid LDAP server!
			$r = @ldap_bind($ldap); // this is an anonymous bind
			$st_search = "uid=$userid";
			// Need to set the right root search information. see ../index.php
			$sr = ldap_search($ldap, LDAP_AUTH_SEARCH, "$st_search");  
			$info = ldap_get_entries($ldap, $sr); // there may be more hits with this userid. try every
			for ($i=0; $i<$info["count"]; $i++) {
			    $dn = $info[$i]["dn"];
			    // The password is still plain text.
			    if ($r = @ldap_bind($ldap, $dn, $passwd)) {
				// ldap_bind will return TRUE if everything matches
				ldap_close($ldap);
                                $this->_authmethod = 'LDAP';
				return WIKIAUTH_USER;
			    }
			}
		    } else {
			trigger_error("Unable to connect to LDAP server " . LDAP_AUTH_HOST, E_USER_WARNING);
		    }
		}
		// imap authentication. added by limako
		if (ALLOW_IMAP_LOGIN and !empty($passwd)) {
		    $mbox = @imap_open( "{" . IMAP_AUTH_HOST . ":143}", $userid, $passwd, OP_HALFOPEN );
		    if( $mbox ) {
			imap_close( $mbox );
                        $this->_authmethod = 'IMAP';
			return WIKIAUTH_USER;
		    }
		}
	    }
	}
        if (ALLOW_BOGO_LOGIN
                && preg_match('/\A' . $WikiNameRegexp . '\z/', $userid)) {
            $this->_authmethod = 'BOGO';
            return WIKIAUTH_BOGO;
        }
        return false;
    }

    // Todo: try our WikiDB backends.
    function getPreferences() {
        // Restore saved preferences.
        // I'd rather prefer only to store the UserId in the cookie or session,
        // and get the preferences from the db or page.
        if (!($prefs = $this->_request->getCookieVar('WIKI_PREFS2')))
            $prefs = $this->_request->getSessionVar('wiki_prefs');

        //if (!$this->_userid and !empty($GLOBALS['HTTP_COOKIE_VARS']['WIKI_ID'])) {
        //    $this->_userid = $GLOBALS['HTTP_COOKIE_VARS']['WIKI_ID'];
        //}

        // before we get his prefs we should check if he is signed in
        if (!$prefs->_prefs and USE_PREFS_IN_PAGE and $this->homePage()) { // in page metadata
            if ($pref = $this->_homepage->get('pref'))
                $prefs = unserialize($pref);
        }
        return new UserPreferences($prefs);
    }

    // No cookies anymore for all prefs, only the userid.
    // PHP creates a session cookie in memory, which is much more efficient.
    //
    // Return the number of changed entries?
    function setPreferences($prefs, $id_only = false) {
        // update the id
        $this->_request->setSessionVar('wiki_prefs', $prefs);
        // $this->_request->setCookieVar('WIKI_PREFS2', $this->_prefs, 365);
        // simple unpacked cookie
        if ($this->_userid) setcookie('WIKI_ID', $this->_userid, 365, '/');

        // We must ensure that any password is encrypted. 
        // We don't need any plaintext password.
        if (! $id_only ) {
            if ($this->isSignedIn()) {
                if ($this->isAdmin()) $prefs->set('passwd',''); // this is already stored in index.php, 
                // and it might be plaintext! well oh well
                if ($homepage = $this->homePage()) {
                    $homepage->set('pref',serialize($prefs->_prefs));
                    return sizeof($prefs->_prefs);
                } else {
                    trigger_error('No homepage for user found. Creating one...', E_USER_WARNING);
                    $this->createHomepage($prefs);
                    //$homepage->set('pref',serialize($prefs->_prefs));
                    return sizeof($prefs->_prefs);
                }
            } else {
                trigger_error('you must be signed in',E_USER_WARNING);
            }
        }
        return 0;
    }

    // check for homepage with user flag.
    // can be overriden from the auth backends
    function exists() {
        $homepage = $this->homePage();
        return ($this->_userid and $homepage and $homepage->get('pref'));
    }

    // doesn't check for existance!!! hmm. 
    // how to store metadata in not existing pages? how about versions?
    function homePage() {
        if (!$this->_userid) return false;
        if (!empty($this->_homepage)) {
            return $this->_homepage;
        } else {
            $this->_homepage = $this->_dbi->getPage($this->_userid);
            return $this->_homepage;
        }
    }

    // create user by checking his homepage
    function createUser ($pref, $createDefaultHomepage = true) {
        if ($this->exists()) return;
        if ($createDefaultHomepage) {
            $this->createHomepage ($pref);
        } else {
            // empty page
            include "lib/loadsave.php";
            $pageinfo = array('pagedata' => array('pref' => serialize($pref->_pref)),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $this->_userid,
                              'content' => _('CategoryHomepage'));
            SavePage (&$this->_request, $pageinfo, false, false);
        }
        $this->setPreferences($pref);
    }

    // create user and default user homepage
    function createHomepage ($pref) {
        $pagename = $this->_userid;
        include "lib/loadsave.php";

        // create default homepage:
        //  properly expanded template and the pref metadata
        $template = Template('homepage.tmpl',$this->_request);
        $text  = $template->getExpansion();
        $pageinfo = array('pagedata' => array('pref' => serialize($pref->_pref)),
                          'versiondata' => array('author' => $this->_userid),
                          'pagename' => $pagename,
                          'content' => $text);
        SavePage (&$this->_request, $pageinfo, false, false);
            
        // create Calender
        $pagename = $this->_userid . SUBPAGE_SEPARATOR . _('Preferences');
        if (! isWikiPage($pagename)) {
            $pageinfo = array('pagedata' => array(),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $pagename,
                              'content' => "<?plugin Calender ?>\n");
            SavePage (&$this->_request, $pageinfo, false, false);
        }

        // create Preferences
        $pagename = $this->_userid . SUBPAGE_SEPARATOR . _('Preferences');
        if (! isWikiPage($pagename)) {
            $pageinfo = array('pagedata' => array(),
                              'versiondata' => array('author' => $this->_userid),
                              'pagename' => $pagename,
                              'content' => "<?plugin UserPreferences ?>\n");
            SavePage (&$this->_request, $pageinfo, false, false);
        }
    }

    function tryAuthBackends() {
        return ''; // crypt('') will never be ''
    }

    // Auth backends must store the crypted password where?
    // Not in the preferences.
    function checkPassword($passwd) {
        $prefs = $this->getPreferences();
        $stored_passwd = $prefs->get('passwd'); // crypted
        if (empty($prefs->_prefs['passwd']))    // not stored in the page
            // allow empty passwords? At least store a '*' then.
            // try other backend. hmm.
            $stored_passwd = $this->tryAuthBackends($this->_userid);
        if (empty($stored_passwd)) {
            trigger_error(sprintf(_("Old UserPage %s without stored password updated with empty password. Set a password in your UserPreferences."), $this->_userid), E_USER_NOTICE);
            $prefs->set('passwd','*'); 
            return true;
        }
        if ($stored_passwd == '*')
            return true;
        if (!empty($passwd) && crypt($passwd, $stored_passwd) == $stored_passwd)
            return true;
        else         
            return false;
    }

    function changePassword($newpasswd, $passwd2 = false) {
        if (! $this->mayChangePassword() ) {
            trigger_error(sprintf("Attempt to change an external password for '%s'. Not allowed!",
                                  $this->_userid), E_USER_ERROR);
            return;
        }
        if ($passwd2 and $passwd2 != $newpasswd) {
            trigger_error("The second passwort must be the same as the first to change it", E_USER_ERROR);
            return;
        }
        $prefs = $this->getPreferences();
        //$oldpasswd = $prefs->get('passwd');
        $prefs->set('passwd', crypt($newpasswd));
        $this->setPreferences($prefs);
    }

    function mayChangePassword() {
        // on external DBAuth maybe. on IMAP or LDAP not
        // on internal DBAuth yes
        if (in_array($this->_authmethod, array('IMAP', 'LDAP'))) 
            return false;
        if ($this->isAdmin()) 
            return false;
        if ($this->_authmethod == 'pagedata')
            return true;
        if ($this->_authmethod == 'authdb')
            return true;
    }
}

// create user and default user homepage
function createUser ($userid, $pref) {
    $user = new WikiUser ($userid);
    $user->createUser($pref);
}

class _UserPreference 
{
    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string) $value;
    }

    function update ($value) {
    }
}

class _UserPreference_numeric extends _UserPreference
{
    function _UserPreference_numeric ($default, $minval = false, $maxval = false) {
        $this->_UserPreference((double) $default);
        $this->_minval = (double) $minval;
        $this->_maxval = (double) $maxval;
    }

    function sanify ($value) {
        $value = (double) $value;
        if ($this->_minval !== false && $value < $this->_minval)
            $value = $this->_minval;
        if ($this->_maxval !== false && $value > $this->_maxval)
            $value = $this->_maxval;
        return $value;
    }
}

class _UserPreference_int extends _UserPreference_numeric
{
    function _UserPreference_int ($default, $minval = false, $maxval = false) {
        $this->_UserPreference_numeric((int) $default, (int)$minval, (int)$maxval);
    }

    function sanify ($value) {
        return (int) parent::sanify((int)$value);
    }
}

class _UserPreference_bool extends _UserPreference
{
    function _UserPreference_bool ($default = false) {
        $this->_UserPreference((bool) $default);
    }

    function sanify ($value) {
        if (is_array($value)) {
            /* This allows for constructs like:
             *
             *   <input type="hidden" name="pref[boolPref][]" value="0" />
             *   <input type="checkbox" name="pref[boolPref][]" value="1" />
             *
             * (If the checkbox is not checked, only the hidden input gets sent.
             * If the checkbox is sent, both inputs get sent.)
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


class _UserPreference_language extends _UserPreference
{
    function _UserPreference_language ($default = 'en') {
        $this->_UserPreference($default);
    }

    function sanify ($value) {
        // FIXME: check for valid locale
    }

    function update ($newvalue) {
        update_locale ($newvalue);
    }
}

class _UserPreference_theme extends _UserPreference
{
    function _UserPreference_theme ($default = 'default') {
        $this->_UserPreference($default);
    }

    function sanify ($value) {
        if (file_exists($this->_themefile($value)))
            return $value;
        return $this->default;
    }

    function update ($newvalue) {
        global $Theme;
        include($this->_themefile($value));
        if (empty($Theme))
            include($this->_themefile('default'));
    }

    function _themefile ($theme) {
        return "themes/$theme/themeinfo.php"; 
    }
}

// don't save default preferences for efficiency.
class UserPreferences {
    function UserPreferences ($saved_prefs = false) {
        $this->_prefs = array();

        if (isa($saved_prefs, 'UserPreferences') and $saved_prefs->_prefs) {
            foreach ($saved_prefs->_prefs as $name => $value)
                $this->set($name, $value);
        } elseif (is_array($saved_prefs)) {
            foreach ($saved_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref ($name) {
        global $UserPreferences;
        if (!isset($UserPreferences[$name])) {
            if ($name == 'passwd2') return false;
            trigger_error("$name: unknown preference", E_USER_NOTICE);
            return false;
        }
        return $UserPreferences[$name];
    }

    function get ($name) {
        if (isset($this->_prefs[$name]))
            return $this->_prefs[$name];
        if (!($pref = $this->_getPref($name)))
            return false;
        return $pref->default_value;
    }

    function set ($name, $value) {
        if (!($pref = $this->_getPref($name)))
            return false;

        $newvalue = $pref->sanify($value);
        $oldvalue = $this->get($name);

        // update on changes
        if ($newvalue != $oldvalue)
            $pref->update($newvalue);
        
	// don't set default values to save space (in cookies, db and sesssion)
	if ($value == $pref->default_value)
	    unset($this->_prefs[$name]);
	else
            $this->_prefs[$name] = $newvalue;
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>