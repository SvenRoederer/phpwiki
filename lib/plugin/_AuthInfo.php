<?php // -*-php-*-
rcs_id('$Id: _AuthInfo.php,v 1.3 2004-02-02 05:36:29 rurban Exp $');
/**
 Copyright 2004 $ThePhpWikiProgrammingTeam

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
 */

require_once('lib/Template.php');
/**
 * This may display passwords in cleartext.
 * Used to debug auth problems and settings.
 */
class WikiPlugin__AuthInfo
extends WikiPlugin
{
    function getName () {
        return _("AuthInfo");
    }

    function getDescription () {
        return _("Display general and user specific auth information.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.3 $");
    }

    function getDefaultArguments() {
        return array('userid' => '');
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($userid) or $userid == $request->_user->UserName()) {
            $user = & $request->_user;
            $userid = $user->UserName();
        } else {
            $user = WikiUser($userid);
        }

        $html = HTML(HTML::h3(fmt("General Auth Settings")));
        $table = HTML::table(array('border' => 1,
                                  'cellpadding' => 2,
                                  'cellspacing' => 0));
        $table->pushContent($this->_showhash("AUTH DEFINES", 
                                $this->_buildConstHash(
                                    array("ENABLE_USER_NEW","ALLOW_ANON_USER",
                                          "ALLOW_ANON_EDIT","ALLOW_BOGO_LOGIN",
                                          "REQUIRE_SIGNIN_BEFORE_EDIT","ALLOW_USER_PASSWORDS",
                                          "PASSWORD_LENGTH_MINIMUM","USE_DB_SESSION"))));
        if ((defined('ALLOW_LDAP_LOGIN') && ALLOW_LDAP_LOGIN) or in_array("LDAP",$GLOBALS['USER_AUTH_ORDER']))
            $table->pushContent($this->_showhash("LDAP DEFINES", 
                                                 $this->_buildConstHash(array("LDAP_AUTH_HOST","LDAP_AUTH_SEARCH"))));
        if ((defined('ALLOW_IMAP_LOGIN') && ALLOW_IMAP_LOGIN) or in_array("IMAP",$GLOBALS['USER_AUTH_ORDER']))
            $table->pushContent($this->_showhash("IMAP DEFINES", array("IMAP_AUTH_HOST" => IMAP_AUTH_HOST)));
        if (defined('AUTH_USER_FILE') or in_array("File",$GLOBALS['USER_AUTH_ORDER']))
            $table->pushContent($this->_showhash("AUTH_USER_FILE", 
                                    $this->_buildConstHash(array("AUTH_USER_FILE",
                                                                 "AUTH_USER_FILE_STORABLE"))));
        if (defined('GROUP_METHOD'))
            $table->pushContent($this->_showhash("GROUP_METHOD", 
                                    $this->_buildConstHash(array("GROUP_METHOD","AUTH_GROUP_FILE","GROUP_LDAP_QUERY"))));
        $table->pushContent($this->_showhash("\$USER_AUTH_ORDER[]", $GLOBALS['USER_AUTH_ORDER']));
        $table->pushContent($this->_showhash("USER_AUTH_POLICY", array("USER_AUTH_POLICY"=>USER_AUTH_POLICY)));
        $table->pushContent($this->_showhash("\$DBParams[]", $GLOBALS['DBParams']));
        unset($GLOBALS['DBAuthParams']['dummy']);
        $table->pushContent($this->_showhash("\$DBAuthParams[]", $GLOBALS['DBAuthParams']));
        $html->pushContent($table);
        $html->pushContent(HTML(HTML::h3(fmt("Personal Auth Settings for '%s'",$userid))));
        if (!$user) {
            $html->pushContent(HTML::p(fmt("No userid")));
        }
        else {
            $table = HTML::table(array('border' => 1,
                                       'cellpadding' => 2,
                                       'cellspacing' => 0));
            $table->pushContent(HTML::tr(HTML::td(array('colspan' => 2))));
            $userdata = $this->_obj2hash($user);
            $table->pushContent($this->_showhash("Object of ".get_class($user), $userdata));
            $html->pushContent($table);
        }
        return $html;
    }

    function _obj2hash ($obj, $exclude = false, $fields = false) {
        $a = array();
        if (! $fields ) $fields = get_object_vars($obj);
        foreach ($fields as $key => $val) {
            if (is_array($exclude)) {
                if (in_array($key,$exclude)) continue;
            }
            $a[$key] = $val;
        }
        return $a;
    }

    function _showhash ($heading, $hash) {
    	static $seen = array();
        $rows = array();
        if ($heading)
            $rows[] = HTML::tr(array('bgcolor' => '#ffcccc',
                                     'style' => 'color:#000000'),
                               HTML::td(array('colspan' => 2,
                                              'style' => 'color:#000000'),
                                        $heading));
        if (!empty($hash)) {
            ksort($hash);
            foreach ($hash as $key => $val) {
                if (is_object($val)) {
                    $heading = "Object of ".get_class($val);
                    if ($heading == "Object of wikidb_sql") $val = $heading;
                    elseif (substr($heading,0,13) == "Object of db_") $val = $heading;
                    elseif (!isset($seen[$heading])) {
                        if (empty($seen[$heading])) $seen[$heading] = 1;
                        $val = HTML::table(array('border' => 1,
                                                 'cellpadding' => 2,
                                                 'cellspacing' => 0),
                                           $this->_showhash($heading, $this->_obj2hash($val)));
                    } else {
                        $val = $heading;
                    }
                } elseif (is_array($val)) {
                    $heading = $key."[]";
                    if (!isset($seen[$heading])) {
                        if (empty($seen[$heading])) $seen[$heading] = 1;
                        $val = HTML::table(array('border' => 1,
                                                 'cellpadding' => 2,
                                                 'cellspacing' => 0),
                                           $this->_showhash($heading, $val));
                    } else {
                        $val = $heading;
                    }
                }
                $rows[] = HTML::tr(HTML::td(array('align' => 'right',
                                                  'bgcolor' => '#cccccc',
                                                  'style' => 'color:#000000'),
                                            HTML(HTML::raw('&nbsp;'), $key,
                                                 HTML::raw('&nbsp;'))),
                                   HTML::td(array('bgcolor' => '#ffffff',
                                                  'style' => 'color:#000000'),
                                            $val ? $val : HTML::raw('&nbsp;'))
                                   );
                if (empty($seen[$key])) $seen[$key] = 1;
            }
        }
        return $rows;
    }
    
    function _buildConstHash($constants) {
        $hash = array();
        foreach ($constants as $c) {
            $hash[$c] = defined($c) ? constant($c) : '<empty>';
            if ($hash[$c] === false) $hash[$c] = 'false';
            elseif ($hash[$c] === true) $hash[$c] = 'true';
        }
        return $hash;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.2  2004/02/01 09:14:11  rurban
// Started with Group_Ldap (not yet ready)
// added new _AuthInfo plugin to help in auth problems (warning: may display passwords)
// fixed some configurator vars
// renamed LDAP_AUTH_SEARCH to LDAP_BASE_DN
// changed PHPWIKI_VERSION from 1.3.8a to 1.3.8pre
// USE_DB_SESSION defaults to true on SQL
// changed GROUP_METHOD definition to string, not constants
// changed sample user DBAuthParams from UPDATE to REPLACE to be able to
//   create users. (Not to be used with external databases generally, but
//   with the default internal user table)
//
// fixed the IndexAsConfigProblem logic. this was flawed:
//   scripts which are the same virtual path defined their own lib/main call
//   (hmm, have to test this better, phpwiki.sf.net/demo works again)
//
// Revision 1.1  2004/02/01 01:04:34  rurban
// Used to debug auth problems and settings.
// This may display passwords in cleartext.
// DB Objects are not displayed anymore.
//
// Revision 1.21  2003/02/21 04:22:28  dairiki
// Make this work for array-valued data.  Make display of cached markup
// readable.  Some code cleanups.  (This still needs more work.)
//
// Revision 1.20  2003/01/18 21:19:24  carstenklapp
// Code cleanup:
// Reformatting; added copyleft, getVersion, getDescription
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
