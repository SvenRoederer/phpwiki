<?php
// display.php: fetch page or get default content
rcs_id('$Id: display.php,v 1.25 2002-02-08 03:01:11 dairiki Exp $');

require_once('lib/Template.php');
require_once('lib/BlockParser.php');

/**
 * Guess a short description of the page.
 *
 * Algorithm:
 *
 * This algorithm was suggested on MeatballWiki by
 * Alex Schroeder <kensanata@yahoo.com>.
 *
 * Use the first paragraph in the page which contains at least two
 * sentences.
 *
 * @see http://www.usemod.com/cgi-bin/mb.pl?MeatballWikiSuggestions
 */
function GleanDescription ($rev) {
    $two_sentences
        = pcre_fix_posix_classes("/[.?!]\s+[[:upper:])]"
                                 . ".*"
                                 . "[.?!]\s*([[:upper:])]|$)/sx");
        
    $content = $rev->getPackedContent();

    // Iterate through paragraphs.
    while (preg_match('/(?: ^ \w .* $ \n? )+/mx', $content, $m)) {
        $paragraph = $m[0];
        
        // Return paragraph if it contains at least two sentences.
        if (preg_match($two_sentences, $paragraph)) {
            return preg_replace("/\s*\n\s*/", " ", trim($paragraph));
        }

        $content = substr(strstr($content, $paragraph), strlen($paragraph));
    }
    return '';
}


function actionPage(&$request, $action) {
    global $Theme;
    
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $request->getPage();
    $revision = $page->getCurrentRevision();

    $dbi = $request->getDbh();
    $actionpage = $dbi->getPage($action);
    $actionrev = $actionpage->getCurrentRevision();

    $splitname = split_pagename($pagename);
    $pagetitle = HTML($actionpage->getName(), ": ",
                      $Theme->linkExistingWikiWord($pagename, false, $version));

    $template = Template('browse', array('CONTENT' => TransformText($actionrev)));
    
    GeneratePage($template, $pagetitle, $revision);
    flush();
}

function displayPage(&$request, $tmpl = 'browse') {
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');
    $page = $request->getPage();
    if ($version) {
        $page = $request->getPage();
        $revision = $page->getRevision($version);
        if (!$revision)
            NoSuchRevision($request, $page, $version);
    }
    else {
        $revision = $page->getCurrentRevision();
    }

    $splitname = split_pagename($pagename);
    $pagetitle = HTML::a(array('href' => WikiURL($pagename,
                                                 array('action' => _("BackLinks"))),
                               'class' => 'backlinks'),
                         $splitname);
    $pagetitle->addTooltip(sprintf(_("BackLinks for %s"), $pagename));

    include_once('lib/BlockParser.php');
    $template = Template($tmpl, array('CONTENT' => TransformText($revision)));

    GeneratePage($template, $pagetitle, $revision,
                 array('ROBOTS_META'	=> 'index,follow',
                       'PAGE_DESCRIPTION' => GleanDescription($revision)));
    flush();

    $page->increaseHitCount();
}

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:

?>
