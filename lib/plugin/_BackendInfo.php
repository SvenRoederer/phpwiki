<?php // -*-php-*-
rcs_id('$Id: _BackendInfo.php,v 1.21 2003-02-21 04:22:28 dairiki Exp $');
/**
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

require_once('lib/Template.php');
/**
 */
class WikiPlugin__BackendInfo
extends WikiPlugin
{
    function getName () {
        return _("DebugInfo");
    }

    function getDescription () {
        return sprintf(_("Get debugging information for %s."), '[pagename]');
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.21 $");
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]');
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';

        $backend = &$dbi->_backend;

        $html = HTML(HTML::h3(fmt("Querying backend directly for '%s'",
                                  $page)));


        $table = HTML::table(array('border' => 1,
                                   'cellpadding' => 2,
                                   'cellspacing' => 0));
        $pagedata = $backend->get_pagedata($page);
        if (!$pagedata) {
            // FIXME: invalid HTML
            $html->pushContent(HTML::p(fmt("No pagedata for %s", $page)));
        }
        else {
            $this->_fixupData($pagedata);
            $table->pushContent($this->_showhash("get_pagedata('$page')", $pagedata));
        }

        for ($version = $backend->get_latest_version($page);
             $version;
             $version = $backend->get_previous_version($page, $version))
            {
                $vdata = $backend->get_versiondata($page, $version, true);
                $this->_fixupData($vdata);
                $table->pushContent(HTML::tr(HTML::td(array('colspan' => 2))));
                $table->pushContent($this->_showhash("get_versiondata('$page',$version)",
                                                     $vdata));
            }

        $html->pushContent($table);
        return $html;
    }

    /**
     * Really should have a _fixupPagedata and _fixupVersiondata, but this works.
     */
    function _fixupData(&$data) {
        global $request;
        $user = $request->getUser();

        foreach ($data as $key => $val) {
            if ($key == 'passwd' and !$user->isAdmin())
                $data[$key] = $val ? _("<not displayed>") : _("<empty>");
            elseif ($key == '_cached_html') {
                $val = TransformedText::unpack($val);
                ob_start();
                print_r($val);
                $data[$key] = HTML::pre(ob_get_contents());
                ob_end_clean();
            }
            elseif (is_string($val) && (substr($val, 0, 2) == 'a:')) {
                // how to indent this table?
                $val = unserialize($val);
                $this->_fixupData($val);
                $data[$key] = HTML::table(array('border' => 1,
                                                'cellpadding' => 2,
                                                'cellspacing' => 0),
                                          $this->_showhash(false, $val));
            }
            elseif (is_array($val)) {
                // how to indent this table?
                $this->_fixupData($val);
                $data[$key] = HTML::table(array('border' => 1,
                                                'cellpadding' => 2,
                                                'cellspacing' => 0),
                                          $this->_showhash(false, $val));
            }
            elseif ($key == '%content') {
                if ($val === true)
                    $val = '<true>';
                elseif (strlen($val) > 40)
                    $val = substr($val,0,40) . " ...";
                $data[$key] = $val;
            }
        }
        unset($data['%pagedata']); // problem in backend
    }

            
    function _showhash ($heading, $hash, $pagename = '') {
        $rows = array();
        if ($heading)
            $rows[] = HTML::tr(array('bgcolor' => '#ffcccc',
                                     'style' => 'color:#000000'),
                               HTML::td(array('colspan' => 2,
                                              'style' => 'color:#000000'),
                                        $heading));
        ksort($hash);
        foreach ($hash as $key => $val) {
            $rows[] = HTML::tr(HTML::td(array('align' => 'right',
                                              'bgcolor' => '#cccccc',
                                              'style' => 'color:#000000'),
                                        HTML(HTML::raw('&nbsp;'), $key,
                                             HTML::raw('&nbsp;'))),
                               HTML::td(array('bgcolor' => '#ffffff',
                                              'style' => 'color:#000000'),
                                        $val ? $val : HTML::raw('&nbsp;'))
                               );
        }
        return $rows;
    }
};

// $Log: not supported by cvs2svn $
// Revision 1.20  2003/01/18 21:19:24  carstenklapp
// Code cleanup:
// Reformatting; added copyleft, getVersion, getDescription
//

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
