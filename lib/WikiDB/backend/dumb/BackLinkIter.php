<?php

require_once 'lib/WikiDB/backend.php';

/**
 * This backlink iterator will work with any WikiDB_backend
 * which has a working get_links(,'links_from') method.
 *
 * This is mostly here for testing, 'cause it's slow,slow,slow.
 */
class WikiDB_backend_dumb_BackLinkIter
    extends WikiDB_backend_iterator
{
    function __construct($backend, $all_pages, $pagename)
    {
        $this->_pages = $all_pages;
        $this->_backend = &$backend;
        $this->_target = $pagename;
    }

    function next()
    {
        while ($page = $this->_pages->next()) {
            $pagename = $page['pagename'];
            $links = $this->_backend->get_links($pagename, false);
            while ($link = $links->next()) {
                if ($link['pagename'] == $this->_target) {
                    $links->free();
                    return $page;
                }
            }
        }
    }

    function free()
    {
        $this->_pages->free();
    }
}
