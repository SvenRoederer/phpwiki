<?php // -*-php-*-
rcs_id('$Id: RecentChangesCached.php,v 1.2 2003-01-18 22:01:44 carstenklapp Exp $');
/**
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

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

// +---------------------------------------------------------------------+
// | WikiPluginCached.php                                                |
// +---------------------------------------------------------------------+
// | Copyright (C) 2002 Johannes Gro�e (Johannes Gro&szlig;e)            |
// | You may copy this code freely under the conditions of the GPL       |
// +---------------------------------------------------------------------+

/* There is a bug in it:
   When the cache is empty and you safe the wikipages,
   an immediately created cached output of
   RecentChanges will at the rss-image-link include
   an action=edit
*/


require_once "lib/WikiPluginCached.php";
require_once "lib/plugin/RecentChanges.php";

class WikiPlugin_RecentChangesCached
extends WikiPluginCached
{
    /* --------- overwrite virtual or abstract methods ---------------- */
    function getPluginType() {
        return PLUGIN_CACHED_HTML;
    }

    function getName() {
        return "RecentChangesCached";
    }

    function getDescription() {
        return 'Caches output of RecentChanges called with default arguments.';
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }

    function getDefaultArguments() {
        return WikiPlugin_RecentChanges::getDefaultArguments();
    }

    function getExpire($dbi, $argarray, $request) {
        return '+900'; // 15 minutes
    }

    function getHtml($dbi, $argarray, $request) {
        $loader = new WikiPluginLoader;
        return $loader->expandPI('<?plugin RecentChanges '
            . WikiPluginCached::glueArgs($argarray)
            . ' ?>', $request);
    }

} // WikiPlugin_TexToPng

// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 4
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
