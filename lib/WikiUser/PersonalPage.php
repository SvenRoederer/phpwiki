<?php //-*-php-*-
// rcs_id('$Id$');
/* Copyright (C) 2004 ReiniUrban
 * This file is part of PhpWiki. Terms and Conditions see LICENSE. (GPL2)
 */

/**
 * This class is only to simplify the auth method dispatcher.
 * It inherits almost all all methods from _PassUser.
 */
class _PersonalPagePassUser
extends _PassUser
{
    var $_authmethod = 'PersonalPage';

    /* Very loose checking, since we properly quote the PageName.
       Just trim spaces, ... See lib/stdlib.php
    */
    function isValidName ($userid = false) {
        if (!$userid) $userid = $this->_userid;
        $WikiPageName = new WikiPageName($userid);
        return $WikiPageName->isValid() and ($userid === $WikiPageName->name);
    }

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
                    if (PASSWORD_LENGTH_MINIMUM > 0) {
                  trigger_error(sprintf(
                    _("PersonalPage login method:")."\n".
                    _("You stored an empty password in your '%s' page.")."\n".
                    _("Your access permissions are only for a BogoUser.")."\n".
                    _("Please set a password in UserPreferences."),
                                        $this->_userid), E_USER_WARNING);
                  $this->_level = WIKIAUTH_BOGO;
                    } else {
                      if (!empty($submitted_password))
                    trigger_error(sprintf(
                      _("PersonalPage login method:")."\n".
                      _("You stored an empty password in your '%s' page.")."\n".
                      _("Given password ignored.")."\n".
                      _("Please set a password in UserPreferences."),
                                        $this->_userid), E_USER_WARNING);
                  $this->_level = WIKIAUTH_USER;
                    }
                return $this->_level;
            }
            if ($this->_checkPass($submitted_password, $stored_password))
                return ($this->_level = WIKIAUTH_USER);
            return _PassUser::checkPass($submitted_password);
        } else {
            return WIKIAUTH_ANON;
        }
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
