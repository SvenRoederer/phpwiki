<?php // -*-php-*-
rcs_id('$Id: IncludePage.php,v 1.20 2003-01-18 21:41:02 carstenklapp Exp $');
/*
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


/**
 * IncludePage:  include text from another wiki page in this one
 * usage:   <?plugin IncludePage page=OtherPage rev=6 quiet=1 words=50 lines=6?>
 * author:  Joe Edelman <joe@orbis-tertius.net>
 */

class WikiPlugin_IncludePage
extends WikiPlugin
{
    function getName() {
        return _("IncludePage");
    }

    function getDescription() {
        return _("Include text from another wiki page.");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.20 $");
    }

    function getDefaultArguments() {
        return array( 'page'    => false, // the page to include
                      'rev'     => false, // the revision (defaults to most recent)
                      'quiet'   => false, // if set, inclusion appears as normal content
                      'words'   => false, // maximum number of words to include
                      'lines'   => false, // maximum number of lines to include
                      'section' => false, // include a named section
                      'sectionhead' => false // when including a named section show the heading
                      );
    }

    function firstNWordsOfContent( $n, $content ) {
        $wordcount = 0;
        $new = array( );
        foreach ($content as $line) {
            $words = explode(' ', $line);
            if ($wordcount + count($words) > $n) {
                $new[] = implode(' ', array_slice($words, 0, $n - $wordcount))
                    . "... (first $n words)";
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

        extract($this->getArgs($argstr, $request));

        if (!$page) {
            return $this->error(_("no page specified"));
        }

        // A page can include itself once (this is needed, e.g.,  when editing
        // TextFormattingRules).
        static $included_pages = array();
        if (in_array($page, $included_pages)) {
            return $this->error(sprintf(_("recursive inclusion of page %s"),
                                        $page));
        }

        $p = $dbi->getPage($page);

        if ($rev) {
            $r = $p->getRevision($rev);
            if (!$r) {
                return $this->error(sprintf(_("%s(%d): no such revision"),
                                            $page, $rev));
            }
        } else {
            $r = $p->getCurrentRevision();
        }

        $c = $r->getContent();

        if ($section)
            $c = $this->extractSection($section, $c, $page, $quiet,
                                       $sectionhead);
        if ($lines)
            $c = array_slice($c, 0, $lines);
        if ($words)
            $c = $this->firstNWordsOfContent($words, $c);


        array_push($included_pages, $page);

        include_once('lib/BlockParser.php');
        $content = TransformText(implode("\n", $c), $r->get('markup'));

        array_pop($included_pages);

        if ($quiet)
            return $content;

        return HTML(HTML::p(array('class' => 'transclusion-title'),
                            fmt("Included from %s", WikiLink($page))),

                    HTML::div(array('class' => 'transclusion'),
                              false, $content));
    }
};

// This is an excerpt from the css file I use:
//
// .transclusion-title {
//   font-style: oblique;
//   font-size: 0.75em;
//   text-decoration: underline;
//   text-align: right;
// }
//
// DIV.transclusion {
//   background: lightgreen;
//   border: thin;
//   border-style: solid;
//   padding-left: 0.8em;
//   padding-right: 0.8em;
//   padding-top: 0px;
//   padding-bottom: 0px;
//   margin: 0.5ex 0px;
// }

// KNOWN ISSUES:
// - line & word limit doesn't work if the included page itself
//   includes a plugin


// $Log: not supported by cvs2svn $

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
