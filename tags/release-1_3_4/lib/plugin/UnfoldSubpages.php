<?php // -*-php-*-
rcs_id('$Id: UnfoldSubpages.php,v 1.2 2002-09-09 08:38:19 rurban Exp $');
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
 * UnfoldSubpages:  Lists the content of all SubPages of the current page. 
 *   This is e.g. useful for the CalendarPlugin, to see all entries at once. 
 * Usage:   <?plugin UnfoldSubpages words=50 ?>
 * Author:  Reini Urban <rurban@x-ray.at>
 */

class WikiPlugin_UnfoldSubpages
extends WikiPlugin
{
    function getName() {
        return _("UnfoldSubpages");
    }

    function getDefaultArguments() {
        return array( //'header'  => '',  // expandable string
                      'quiet'   => false, // no header
                      'sort'    => 'asc',
                      'sortby'  => 'pagename',
                      'pages'   => '',      // maximum number of pages to include
                      'sections' => false,  // maximum number of sections per page to include
                      'words'   => false,   // maximum number of words per page to include
                      'lines'   => false,   // maximum number of lines per page to include
                      'bytes'   => false,   // maximum number of bytes per page to include
                      'section' => false,   // named section per page only
                      'sectionhead' => false // when including a named section show the heading
                      );
    }

    // from IncludePage
    function firstNWordsOfContent( $n, $content ) {
        $wordcount = 0;
        $new = array( );
        foreach ($content as $line) {
            $words = explode(' ', $line);
            if ($wordcount + count($words) > $n) {
                $new[] = implode(' ', array_slice($words, 0, $n - $wordcount))
                         . sprintf(_("... first %d words"), $n);
                return $new;
            } else {
                $wordcount += count($words);
                $new[] = $line;
            }
        }
        return $new;
    }

    function extractSection ($section, $content, $page, $quiet, $sectionhead) {
        $qsection = preg_replace('/\s+/', '\s+', preg_quote($section, '/'));

        if (preg_match("/ ^(!{1,})\\s*$qsection" // section header
                       . "  \\s*$\\n?"           // possible blank lines
                       . "  ( (?: ^.*\\n? )*? )" // some lines
                       . "  (?= ^\\1 | \\Z)/xm", // sec header (same or higher level) (or EOF)
                       implode("\n", $content),
                       $match)) {
            // Strip trailing blanks lines and ---- <hr>s
            $text = preg_replace("/\\s*^-{4,}\\s*$/m", "", $match[2]);
            if ($sectionhead)
                $text = $match[1] . $section ."\n". $text;
            return explode("\n", $text);
        }
        if ($quiet)
            $mesg = $page ." ". $section;
        else
            $mesg = $section;
        return array(sprintf(_("<%s: no such section>"), $mesg));
    }

    function run($dbi, $argstr, $request) {
        $pagename = $request->getArg('pagename');
        $subpages = explodePageList($pagename . SUBPAGE_SEPARATOR . '*');
        if (! $subpages) {
            return $this->error(_("The current page has no subpages defined."));
        }
        include_once('lib/BlockParser.php');
        extract($this->getArgs($argstr, $request));
        $content = HTML();
        foreach ($subpages as $page) {
            // A page cannot include itself. Avoid doublettes.
            static $included_pages = array();
            if (in_array($page, $included_pages)) {
                $content->pushContent(HTML::p(sprintf(_("recursive inclusion of page %s ignored"), $page)));
                continue;
            }
            
            $p = $dbi->getPage($page);
            $r = $p->getCurrentRevision();
            $c = $r->getContent();

            if ($section)
                $c = $this->extractSection($section, $c, $page, $quiet, $sectionhead);
            if ($lines)
                $c = array_slice($c, 0, $lines) . sprintf(_(" ... first %d lines"), $bytes);
            if ($words)
                $c = $this->firstNWordsOfContent($words, $c);
            if ($bytes) {
                if (strlen($c) > $bytes)
                    $c = substr($c,0,$bytes) . sprintf(_(" ... first %d bytes"), $bytes);
            }

            array_push($included_pages, $page);
            $ct = TransformText(implode("\n", $c), $r->get('markup'));
            array_pop($included_pages);
            $content->pushContent(HTML(HTML::p(array('class' => $quiet ? '' : 'transclusion-title'),
                                               fmt("Included from %s:",WikiLink($page))),
                                       HTML::div(array('class' => $quiet ? '' : 'transclusion'),
                                                 false, $ct)));
        }
        return $content;
    }
};

// KNOWN ISSUES:
// - line & word limit doesn't work if the included page itself
//   includes a plugin

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>