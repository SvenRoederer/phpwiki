<?php // -*-php-*-
rcs_id('$Id: AllUsers.php,v 1.2 2002-08-27 21:51:31 rurban Exp $');
/*
 Copyright 2002 $ThePhpWikiProgrammingTeam

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

require_once('lib/PageList.php');

/**
 * Based on AllPages.
 * We currently don't get externally authenticated users which didn't store their Preferences.
 */
class WikiPlugin_AllUsers
extends WikiPlugin
{
    function getName () {
        return _("AllUsers");
    }

    function getDescription () {
        return _("With external authentication all users which stored their Preferences. Without external authentication all once signed-in users (from version 1.3.4 on).");
    }

    function getDefaultArguments() {
        return array('noheader'	     => false,
                     'include_empty' => true,
                     'exclude'       => '',
                     'info'          => '',
                     'debug'         => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=WikiAdmin,.SecretUser
    // include_empty shows also users which stored their preferences, but never saved their homepage

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Authenticated users on this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        if (defined('DEBUG'))
            $debug = true;

        if ($debug) $time_start = $this->getmicrotime();
        $page_iter = $dbi->getAllPages($include_empty);
        while ($page = $page_iter->next()) {
            if ($page->isUserPage($include_empty))
                $pagelist->addPage($page);
        }

        if ($debug) $time_end = $this->getmicrotime();

        if ($debug) {
            $time = round($time_end - $time_start, 3);
            return HTML($pagelist,HTML::p(fmt("Elapsed time: %s s", $time)));
        } else {
            return $pagelist;
        }
    }

    function getmicrotime(){
        list($usec, $sec) = explode(" ",microtime());
        return (float)$usec + (float)$sec;
    }
};

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
