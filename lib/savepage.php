<?php rcs_id('$Id: savepage.php,v 1.23 2002-01-08 00:12:26 carstenklapp Exp $');
require_once('lib/Template.php');
require_once('lib/transform.php');
require_once('lib/ArchiveCleaner.php');

/*
   All page saving events take place here.
   All page info is also taken care of here.
   This is klugey. But it works. There's probably a slicker way of
   coding it.
*/


// Changed ConcurrentUpdates--it works ok, but not sure if I like this
// though. Please try it out, is it too confusing?
//
// Moved the Thank You... message into lib/Toolbar.php. I'm thinking
// these kind of static (non-clickable) messages should be moved out
// of lib/Toolbar.php into a page all their own. --Carsten


// FIXME: some links so that it's easy to get back to someplace useful
// from these error pages.

function ConcurrentUpdates($pagename) {
   /* xgettext only knows about c/c++ line-continuation strings
     it does not know about php's dot operator.
     We want to translate this entire paragraph as one string, of course.
   */
    $html = "<p>";
    $html .= "<h1>" .sprintf (_("Problem while updating %s"), $pagename) ."</h1>";
    $html .= _("PhpWiki is unable to save your changes, because another user edited and saved the page while you were editing the page too. If saving proceeded now changes from the previous author would be lost.");
    $html .= "</p>\n<p>";
    $html .= _("In order to recover from this situation follow these steps:");
    $html .= "\n<ol><li>";
    $html .= _("Use your browser's <b>Back</b> button to go back to the edit page.");
    $html .= "</li>\n<li>";
    $html .= _("Copy your changes to the clipboard or to another temporary place (e.g. text editor).");
    $html .= "</li>\n<li>";
    $html .= _("<b>Reload</b> the page. You should now see the most current version of the page. Your changes are no longer there.");
    $html .= "</li>\n<li>";
    $html .= _("Make changes to the file again. Paste your additions from the clipboard (or text editor).");
    $html .= "</li>\n<li>";
    $html .= _("Press <b>Save</b> again.");
    $html .= "</li></ol></p>\n";
    $html .= QElement('p', _("Sorry for the inconvenience."));

    return $html;
}

function PageIsLocked($pagename) {
    $html = QElement('p',
                     _("This page has been locked by the administrator and cannot be edited."));
    $html .= QElement('p',
                      _("Sorry for the inconvenience."));

    echo GeneratePage('MESSAGE', $html,
                      sprintf (_("Problem while editing %s"), $pagename));
    ExitWiki ("");
}

function NoChangesMade($pagename) {
    $html = QElement('p', _("You have not made any changes."));
    $html .= QElement('p', _("New version not saved."));
    echo GeneratePage('MESSAGE', $html,
                      sprintf(_("Edit aborted: %s"), $pagename));
    ExitWiki ("");
}

function BadFormVars($pagename) {
    $html = QElement('p', _("Bad form submission"));
    $html .= QElement('p', _("Required form variables are missing."));
    echo GeneratePage('MESSAGE', $html,
                      sprintf(_("Edit aborted: %s"), $pagename));
    ExitWiki ("");
}
    
function savePreview($dbi, $request) {
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $dbi->getPage($pagename);
    $selected = $page->getRevision($version);

    // FIXME: sanity checking about posted variables
    // FIXME: check for simultaneous edits.
    foreach (array('minor_edit', 'convert') as $key)
        $formvars[$key] = $request->getArg($key) ? 'checked' : '';
    foreach (array('content', 'editversion', 'summary', 'pagename',
                   'version') as $key)
        @$formvars[$key] = htmlspecialchars($request->getArg($key));

    $template = new WikiTemplate('EDITPAGE');
    $template->setPageRevisionTokens($selected);
    $template->replace('FORMVARS', $formvars);

    $PREVIEW_CONTENT= do_transform($request->getArg('content'));
    $template->replace('PREVIEW_CONTENT',$PREVIEW_CONTENT);
    $template->replace('EDIT_WARNINGS',
                       toolbar_Warnings_Edit(!empty($PREVIEW_CONTENT),
                                             $version == $request->getArg('editversion')));

    echo $template->getExpansion();
}

function savePage ($dbi, $request) {
    global $user;

    // FIXME: fail if this check fails?
    assert($request->get('REQUEST_METHOD') == 'POST');
    
    if ($request->getArg('preview'))
        return savePreview($dbi, $request);
    
    $pagename = $request->getArg('pagename');
    $version = $request->getArg('version');

    $page = $dbi->getPage($pagename);
    $current = $page->getCurrentRevision();

    $content = $request->getArg('content');
    $editversion = $request->getArg('editversion');
    
    if ( $content === false || $editversion === false )
        BadFormVars($pagename); // noreturn

    if ($page->get('locked') && !$user->is_admin())
        PageIsLocked($args->pagename); // noreturn.

    $meta['author'] = $user->id();
    $meta['author_id'] = $user->authenticated_id();
    $meta['is_minor_edit'] = (bool) $request->getArg('minor_edit');
    $meta['summary'] = trim($request->getArg('summary'));

    $content = preg_replace('/[ \t\r]+\n/', "\n", chop($content));
    if ($request->getArg('convert'))
        $content = CookSpaces($content);

    if ($content == $current->getPackedContent()) {
        NoChangesMade($pagename); // noreturn
    }

    ////////////////////////////////////////////////////////////////
    //
    // From here on, we're actually saving.
    //
    $newrevision = $page->createRevision($editversion + 1,
                                         $content, $meta,
                                         ExtractWikiPageLinks($content));

    // This should be refactored to avoid a global, for now it allows
    // the actual messages to be moved away from this page. $thankyou is
    // required in the GeneratePage() function.
    global $thankyou;

    if (!is_object($newrevision)) {
        // Save failed.
        $thankyou = ConcurrentUpdates($pagename);
        $thankyou .= "<hr noshade=\"noshade\" />";
        $html = do_transform($current->getContent());
        echo GeneratePage('BROWSE', $html, $pagename, $current);

    } else {
    
        // Clean out archived versions of this page.
        $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
        $cleaner->cleanPageRevisions($page);
        
        $warnings = $dbi->GenericWarnings();
        global $SignatureImg;
        if (empty($warnings)) {
            if (empty($SignatureImg)){
                // Do redirect to browse page if no signature has been defined.
                // In this case, the user will most likely not see the rest of
                // the HTML we generate (below).
                $request->redirect(WikiURL($pagename, false, 'absolute_url'));
            }
        }
        $thankyou = toolbar_Info_ThankYou($pagename, $warnings);
        $html = do_transform($newrevision->getContent());
        echo GeneratePage('BROWSE', $html, $pagename, $newrevision);
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
