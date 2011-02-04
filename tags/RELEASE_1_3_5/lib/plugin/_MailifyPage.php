<?php // -*-php-*-
rcs_id('$Id: _MailifyPage.php,v 1.1 2003-02-20 18:03:04 carstenklapp Exp $');
/**
 * An experimental WikiPlugin for internal use only by PhpWiki developers.
 *
 *
 * This is hackish and may not work as expected every time, so ALWAYS verify the output!
 *
 *
 * Usage:
 * <?plugin _MailifyPage ?>
 * <?plugin _MailifyPage page=HomePage ?>
 */

require_once("lib/loadsave.php");

class WikiPlugin__MailifyPage
extends WikiPlugin
{
    function getName() {
        return "_MailifyPage";
    }
    function getDescription() {
        return "View a single page dump online.";
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]');
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $mailified = MailifyPage($dbi->getPage($page));

        $this->fixup_headers($mailified);

        // wrap the text if it is too long
        trigger_error("Wordwrap doesn't take PhpWiki list-indenting etc. into consideration! Double-check the code before using it in any pgsrc!!");
        $mailified = wordwrap($mailified, 70);

        return HTML::pre($mailified);
    }

    // function handle_plugin_args_cruft(&$argstr, &$args) {
    // }

    function fixup_headers(&$mailified) {
        $array = explode("\n", $mailified);

        // add headers to prepare for checkin to CVS
        $item_to_insert = "X-Rcs-Id: \$Id\$";
        $insert_into_key_position = 2;
        $returnval_ignored = array_splice($array, $insert_into_key_position, 0, $item_to_insert);

        $item_to_insert = "  pgsrc_version=\"2 \$Revision\$\";";
        $insert_into_key_position = 5;
        $returnval_ignored = array_splice($array, $insert_into_key_position, 0, $item_to_insert);

        /*
            Strip out all this junk:
            author=MeMe;
            version=74;
            lastmodified=1041561552;
            author_id=127.0.0.1;
            hits=146;
        */
        // delete unwanted lines from array
        $killme = array("author", "version", "lastmodified", "author_id", "hits");
        foreach ($killme as $pattern) {
            $array = preg_replace("/^\s\s$pattern\=.*;/", $replacement = "zzzjunk", $array); //nasty, fixme
        }

        // remove deleted values from array
        for ($i = 0; $i < count($array); $i++ ) {
            if(trim($array[$i]) != "zzzjunk") { //nasty, fixme
            //trigger_error("'$array[$i]'");//debugging
                $return[] =$array[$i];
            }
        }

        $mailified = implode("\n", $return);
    }
};

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