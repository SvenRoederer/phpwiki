<?php // -*-php-*-
rcs_id('$Id: OrphanedPages.php,v 1.6 2004-04-18 01:44:02 rurban Exp $');
/**
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

/**
 * A plugin which returns a list of pages which are not linked to by
 * any other page
 *
 * Initial version by Lawrence Akka
 *
 **/
require_once('lib/PageList.php');

/**
 */
class WikiPlugin_OrphanedPages
extends WikiPlugin
{
    function getName () {
        return _("OrphanedPages");
    }

    function getDescription () {
        return _("List pages which are not linked to by any other page.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.6 $");
    }

    function getDefaultArguments() {
        return array('noheader'      => false,
                     'include_empty' => false,
                     'exclude'       => '',
                     'info'          => '',
                     'sortby'        => false,
                     'limit'         => false,
                     );
    }
    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, &$request, $basepage) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList($info, $exclude, $this->getArgs($argstr, $request));

        if (!$noheader)
            $pagelist->setCaption(_("Orphaned Pages in this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        // There's probably a more efficient way to do this (eg a
        // tailored SQL query via the backend, but this does the job

        $allpages_iter = $dbi->getAllPages($include_empty,$sortby,$limit);

        while ($page = $allpages_iter->next()) {
            $links_iter = $page->getLinks();
            // test for absence of backlinks. If a page is linked to
            // only by itself, it is still an orphan
            $parent = $links_iter->next();
            if (!$parent ||               // page has no parents
                (($parent->getName() == $page->getName())
                 && !$links_iter->next()) // page has only itself as a parent
                )
                $pagelist->addPage($page);
        }
        return $pagelist;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.5  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.4  2003/01/18 21:49:00  carstenklapp
// Code cleanup:
// Reformatting & tabs to spaces;
// Added copyleft, getVersion, getDescription, rcs_id.
//

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
