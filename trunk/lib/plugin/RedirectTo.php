<?php // -*-php-*-
rcs_id('$Id: RedirectTo.php,v 1.4 2002-09-27 10:55:45 rurban Exp $');
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

/**
 * Redirect to another page or external uri. Kind of PageAlias.
 * Usage:   <?plugin-head RedirectTo href="http://www.internet-technology.de/fourwins_de.htm" ?>
 *      or  <?plugin-head RedirectTo page=AnotherPage ?>
 *          at the VERY FIRST LINE in the content! Otherwise it will be ignored.
 * Author:  Reini Urban <rurban@x-ray.at>
 *
 * BUGS/COMMENTS:
 *
 * This plugin could probably result in a lot of confusion, especially when
 * redirecting to external sites.  (Perhaps it can even be used for dastardly
 * purposes?)  Maybe it should be disabled by default.
 *
 * It would be nice, when redirecting to another wiki page, to (as
 * UseModWiki does) add a note to the top of the target page saying
 * something like "(Redirected from SomeRedirectingPage)".
 */
class WikiPlugin_RedirectTo
extends WikiPlugin
{
    function getName() {
        return _("RedirectTo");
    }

    function getDescription() {
        return _("Redirects to another url or page.");
    }

    function getDefaultArguments() {
        return array( 'href' => '',
                      // 'type' => 'Temp' // or 'Permanent' // so far ignored
                      'page' => false,
                      'args' => false,  // pass more args to the page. TestMe!
                      );
    }

    function run($dbi, $argstr, $request) {
        $args = ($this->getArgs($argstr, $request));
        $href = $args['href'];
        $page = $args['page'];
        if (!$href and !$page)
            return $this->error(sprintf(_("%s or %s parameter missing"), 'href', 'page'));
        if ($href) {
            /*
             * Use quotes on the href argument value, like:
             *   <?plugin RedirectTo href="http://funky.com/a b \" c.htm" ?>
             *
             * Do we want some checking on href to avoid malicious
             * uses of the plugin? Like stripping tags or hexcode.
             */
            $url = preg_replace('/%\d\d/','',strip_tags($href));
        }
        else {
            $url = $request->getURLtoSelf(array_merge(array('pagename' => $page,
                                                            'redirectfrom' => $request->getArg('pagename')),
                                                      SplitQueryArgs($args['args'])));
        }
        if ($page == $request->getArg('pagename')) {
            return $this->error(sprintf(_("Recursive redirect to self: '%s'"), $url));
        }
        return $request->redirect($url);
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
