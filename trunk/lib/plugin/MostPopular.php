<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.13 2002-01-22 03:17:47 dairiki Exp $');
/**
 */

require_once('lib/PageList.php');

class WikiPlugin_MostPopular
extends WikiPlugin
{
    function getName () {
        return _("MostPopular");
    }

    function getDescription () {
        return _("List the most popular pages");
    }

    function getDefaultArguments() {
        return array('limit'	=> 20,
                     'noheader'	=> 0);
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pages = $dbi->mostPopular($limit);

        $pagelist = new PageList();
        $pagelist->insertColumn(_("Hits"));
        //$pagelist->addcolumn(_("Last Modified"));

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0)
                break;
            $pagelist->addPage($page);
        }
        $pages->free();
        
        if (! $noheader) {
            if ($limit > 0) {
                $pagelist->setCaption(_("The %d most popular pages of this wiki:"));
            } else {
                $pagelist->setCaption(_("Visited pages on this wiki, ordered by popularity:"));
            }
        }

        return $pagelist;
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
