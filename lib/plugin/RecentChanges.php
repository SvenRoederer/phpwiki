<?php // -*-php-*-
rcs_id('$Id: RecentChanges.php,v 1.2 2001-12-07 22:15:43 dairiki Exp $');
/**
 */
define('RSS_ENCODING', 'ISO-8859-1');

class WikiPlugin_RecentChanges
extends WikiPlugin
{
    var $name = 'RecentChanges';
    
    function getDefaultArguments() {
        return array('days'		=> 2,
                     'show_minor'	=> false,
                     'show_major'	=> true,
                     'show_all'		=> false,
                     'limit'		=> false,
                     'rss'		=> false);
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        $params = array('include_minor_revisions' => $show_minor,
                        'exclude_major_revisions' => !$show_major,
                        'include_all_revisions' => $show_all);
        if ($days > 0.0) {
            $params['since'] = time() - 24 * 3600 * $days;
            $title = sprintf(_("RecentChanges in the past %.1f days"), $args['days']);
        }
        else {
            $title = _("RecentChanges");
        }
        
        $changes = $dbi->mostRecent($params);

        if ($rss && $request->getArg('action') == 'browse') {
            header("Content-Type: application/xml; charset=" . RSS_ENCODING);
            
            $xml =  $this->__format_as_rss($changes, $title, $args, $request);
            printf("<?xml version=\"1.0\" encoding=\"%s\"?>\n", RSS_ENCODING);
            printf("<!-- Generated by PhpWiki:\n%s-->\n", $GLOBALS['RCS_IDS']);
            echo $xml;
            ExitWiki();
        }
        else {
            return $this->__format_as_html($changes, $title, $args);
        }
    }

    function __format_as_html($changes, $title, $args) {

        global $dateformat;
        global $WikiNameRegexp;
        
        $last_date = '';
        $lines = array();

        $diffargs = array('action' => 'diff');

        // FIXME: add XML icon (and link) to title?
        $html = QElement('h3', $title);

        $limit = $args['limit'];
        while ($rev = $changes->next()) {
            $created = $rev->get('mtime');
            $date = strftime($dateformat, $created);
            $time = strftime("%l:%M %P", $created); // Make configurable.
            if ($date != $last_date) {
                if ($lines) {
                    $html .= Element('ul', join("\n", $lines));
                    $lines = array();
                }
                $html .= Element('p',QElement('b', $date));
                $last_date = $date;
            }
            
            $page = $rev->getPage();
            $pagename = $page->getName();

            if ($args['show_all']) {
                // FIXME: should set previous, too, if showing only minor or major revs.
                //  or maybe difftype.
                $diffargs['version'] = $rev->getVersion();
            }
            
            $diff = QElement('a',
                             array('href' => WikiURL($pagename, $diffargs)),
                             "(diff)");
            
            $wikipage = LinkWikiWord($page->getName());

            $author = $rev->get('author');
            if (preg_match("/^$WikiNameRegexp\$/", $author))
                $author = LinkWikiWord($author);
            else
                $author = htmlspecialchars($author);

            $summary = $rev->get('summary');
            if ($summary)
                $summary = QElement('b', "[$summary]");
            
            $lines[] = Element('li',
                               "$diff $wikipage $time $summary ... $author");

            if ($limit && --$limit <= 0)
                break;
        }
        if ($lines)
            $html .= Element('ul', join("\n", $lines));
        
        return $html;
    }

    function __format_as_rss($changes, $title, $args, $request) {
        include_once('lib/RssWriter.php');
        $rss = new RssWriter;
        $rc_url = WikiURL($request->getArg('pagename'), false, 'absurl');
        
        $chan = array('title' => 'PhpWiki', // FIXME: this should be a config define
                      'dc:description' => $title,
                      'link' => $rc_url,
                      'dc:date' => Iso8601DateTime(time()));

        /* FIXME: other things one might like in <channel>:                   
         * sy:updateFrequency
         * sy:updatePeriod
         * sy:updateBase
         * dc:subject
         * dc:publisher
         * dc:language
         * dc:rights
         * rss091:language
         * rss091:managingEditor
         * rss091:webmaster
         * rss091:lastBuildDate
         * rss091:copyright
         */

        $rss->channel($chan, $rc_url);

        $rss->image(array('title' => 'PhpWiki', // FIXME: this should be a config define
                          'link' => WikiURL(_("HomePage"), false, 'absurl'),
                          'url' => DataURL($GLOBALS['logo'])));
        
        $rss->textinput(array('title' => _("Search"),
                              'description' => _("Title Search"),
                              'name' => 's',
                              'link' => WikiURL(_("TitleSearch"), false, 'absurl')));

        $limit = $args['limit'];
        if (! $limit)
            $limit = 15;

        while ($limit-- > 0 && $rev = $changes->next()) {
            $page = $rev->getPage();

            $urlargs = array();
            if ($args['show_all']) {
                // FIXME: should set previous, too, if showing only minor or major revs.
                //  or maybe difftype.
                $urlargs['version'] = $rev->getVersion();
            }
            
            $pagename = $page->getName();
            
            $item = array('title' => split_pagename($pagename),
                          'description' => $rev->get('summary'),
                          'link' => WikiURL($pagename, $urlargs, 'absurl'),
                          'dc:date' => Iso8601DateTime($rev->get('mtime')),
                          'dc:contributor' => $rev->get('author'),
                          'wiki:version' => $rev->getVersion(),
                          'wiki:importance' => $rev->get('is_minor_edit') ? 'minor' : 'major',
                          // wiki:status = 'new' | 'updated' | 'deleted'
                          'wiki:diff' => WikiURL($pagename,
                                                 array_merge($urlargs,
                                                             array('action' => 'diff',
                                                                   'previous' => 'major')),
                                                 'absurl'),
                          'wiki:history' => WikiURL($pagename,
                                                    array('action' => 'info'),
                                                    'absurl')
                          );


            $uri = WikiURL($pagename, array('version' => $rev->getVersion()), 'absurl');
            $rss->addItem($item, $uri);
        }

        return $rss->asXML();
    }
};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
