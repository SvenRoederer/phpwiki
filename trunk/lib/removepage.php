<?php
rcs_id('$Id: removepage.php,v 1.20 2004-09-23 13:59:36 rurban Exp $');
require_once('lib/Template.php');

function RemovePage (&$request) {
    global $WikiTheme;

    $page = $request->getPage();
    $pagelink = WikiLink($page);

    if ($request->getArg('cancel')) {
        $request->redirect(WikiURL($page)); // noreturn
    }

    $current = $page->getCurrentRevision();
    $version = $current->getVersion();

    if (!$request->isPost() || !$request->getArg('verify')) {

        // FIXME: button should be class wikiadmin
        // Use the macosx button
        $removeB = Button('submit:verify', _("Remove Page"), 'wikiadmin');
        $cancelB = Button('submit:cancel', _("Cancel"), 'button'); // use generic wiki button look
        $sample = firstNWordsOfContent(100, $current->getPackedContent());

        $html = HTML(HTML::h2(fmt("You are about to remove '%s' permanently!", $pagelink)),
                     HTML::form(array('method' => 'post',
                                      'action' => $request->getPostURL()),
                                HiddenInputs(array('currentversion' => $version,
                                                   'pagename' => $page->getName(),
                                                   'action' => 'remove')),
                                
                                HTML::div(array('class' => 'toolbar'),
                                          $removeB,
                                          $WikiTheme->getButtonSeparator(),
                                          $cancelB)),
                     HTML::hr(),
                     HTML::div(array('class' => 'transclusion'), $sample)
                     );
    }
    elseif ($request->getArg('currentversion') != $version) {
        $html = HTML(HTML::h2(_("Someone has edited the page!")),
                     HTML::p(fmt("Since you started the deletion process, someone has saved a new version of %s.  Please check to make sure you still want to permanently remove the page from the database.", $pagelink)));
    }
    else {
        // Real delete.
        $pagename = $page->getName();
        $dbi = $request->getDbh();
        $dbi->deletePage($pagename);
        $dbi->touch();
        $html = HTML(HTML::h2(fmt("Removed page '%s' successfully.", $pagename)));
    }

    GeneratePage($html, _("Remove page"));
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
