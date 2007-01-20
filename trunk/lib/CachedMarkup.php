<?php 
rcs_id('$Id: CachedMarkup.php,v 1.51 2007-01-20 11:24:53 rurban Exp $');
/* Copyright (C) 2002 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright (C) 2004,2005,2006,2007 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 * 
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

include_once("lib/Units.php");

class CacheableMarkup extends XmlContent {

    function CacheableMarkup($content, $basepage) {
        $this->_basepage = $basepage;
	$this->_buf = '';
	$this->_content = array();
	$this->_append($content);
	if ($this->_buf != '')
	    $this->_content[] = $this->_buf;
	unset($this->_buf);
    }

    function pack() {
        if (function_exists('gzcompress'))
            return gzcompress(serialize($this), 9);
        return serialize($this);

        // FIXME: probably should implement some sort of "compression"
        //   when no gzcompress is available.
    }

    function unpack($packed) {
        if (!$packed)
            return false;

        // ZLIB format has a five bit checksum in it's header.
        // Lets check for sanity.
        if (((ord($packed[0]) * 256 + ord($packed[1])) % 31 == 0)
             and (substr($packed,0,2) == "\037\213") 
                  or (substr($packed,0,2) == "x\332"))   // 120, 218
        {
            if (function_exists('gzuncompress')) {
                // Looks like ZLIB.
                $data = gzuncompress($packed);
                return unserialize($data);
            } else {
                // user our php lib. TESTME
                include_once("ziplib.php");
                $zip = new ZipReader($packed);
                list(,$data,$attrib) = $zip->readFile();
                return unserialize($data);
            }
        }
        if (substr($packed,0,2) == "O:") {
            // Looks like a serialized object
            return unserialize($packed);
        }
        if (preg_match("/^\w+$/", $packed))
            return $packed;
        // happened with _BackendInfo problem also.
        trigger_error("Can't unpack bad cached markup. Probably php_zlib extension not loaded.", 
                      E_USER_WARNING);
        return false;
    }
    
    /** Get names of wikipages linked to.
     *
     * @return array of hashes { linkto=>pagename, relation=>pagename }
     */
    function getWikiPageLinks() {
	$links = array();
	foreach ($this->_content as $item) {
	    if (!isa($item, 'Cached_DynamicContent'))
                continue;
            if (!($item_links = $item->getWikiPageLinks($this->_basepage)))
                continue;
            $links = array_merge($links, $item_links);
        }
        // array_unique has a bug with hashes! 
        // set_links checks for duplicates, array_merge does not
	//return array_unique($links);
	return $links;
    }

    /** Get link info.
     *
     * This is here to support the XML-RPC listLinks() method.
     *
     * @return array
     * Returns an array of hashes.
     */
    function getLinkInfo() {
	$link = array();
	foreach ($this->_content as $link) {
	    if (! isa($link, 'Cached_Link'))
		continue;
	    $info = $link->getLinkInfo($this->_basepage);
	    $links[$info->href] = $info;
	}
	return array_values($links);
    }

    function _append($item) {
	if (is_array($item)) {
	    foreach ($item as $subitem)
		$this->_append($subitem);
	}
	elseif (!is_object($item)) {
	    $this->_buf .= $this->_quote((string) $item);
	}
	elseif (isa($item, 'Cached_DynamicContent')) {
	    if ($this->_buf) {
		$this->_content[] = $this->_buf;
		$this->_buf = '';
	    }
	    $this->_content[] = $item;
	}
	elseif (isa($item, 'XmlElement')) {
	    if ($item->isEmpty()) {
		$this->_buf .= $item->emptyTag();
	    }
	    else {
		$this->_buf .= $item->startTag();
		foreach ($item->getContent() as $subitem)
		    $this->_append($subitem);
		$this->_buf .= "</$item->_tag>";

                if (!isset($this->_description) and $item->getTag() == 'p')
                    $this->_glean_description($item->asString());
	    }
	    if (!$item->isInlineElement())
		$this->_buf .= "\n";
	}
	elseif (isa($item, 'XmlContent')) {
	    foreach ($item->getContent() as $item)
		$this->_append($item);
	}
	elseif (method_exists($item, 'asXML')) {
	    $this->_buf .= $item->asXML();
	}
	elseif (method_exists($item, 'asString')) {
	    $this->_buf .= $this->_quote($item->asString());
	}
	else {
	    $this->_buf .= sprintf("==Object(%s)==", get_class($item));
	}
    }

    function _glean_description($text) {
        static $two_sentences;
        if (!$two_sentences) {
            $two_sentences = pcre_fix_posix_classes("[.?!][\")]*\s+[\"(]*[[:upper:])]"
                                                    . ".*"
                                                    . "[.?!][\")]*\s*[\"(]*([[:upper:])]|$)");
        }
        
        if (!isset($this->_description) and preg_match("/$two_sentences/sx", $text))
            $this->_description = preg_replace("/\s*\n\s*/", " ", trim($text));
    }

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
     *
     * @return string
     */
    function getDescription () {
        return isset($this->_description) ? $this->_description : '';
    }
    
    function asXML () {
	$xml = '';
        $basepage = $this->_basepage;
        
	foreach ($this->_content as $item) {
            if (is_string($item)) {
                $xml .= $item;
            }
            elseif (is_subclass_of($item, 
                                   check_php_version(5) 
                                     ? 'Cached_DynamicContent' 
                                     : 'cached_dynamiccontent'))
            {
                $val = $item->expand($basepage, $this);
                $xml .= $val->asXML();
            }
            else {
                $xml .= $item->asXML();
            }
	}
	return $xml;
    }

    function printXML () {
        $basepage = $this->_basepage;
        // _content might be changed from a plugin (CreateToc)
	for ($i=0; $i < count($this->_content); $i++) {
	    $item = $this->_content[$i];
            if (is_string($item)) {
                print $item;
            }
            elseif (is_subclass_of($item, 
                                   check_php_version(5) 
                                     ? 'Cached_DynamicContent' 
                                     : 'cached_dynamiccontent')) 
            {  	// give the content the chance to know about itself or even 
            	// to change itself
                $val = $item->expand($basepage, $this);
                $val->printXML();
            }
            else {
                $item->printXML();
            }
	}
    }
}	

/**
 * The base class for all dynamic content.
 *
 * Dynamic content is anything that can change even when the original
 * wiki-text from which it was parsed is unchanged.
 */
class Cached_DynamicContent {

    function cache(&$cache) {
	$cache[] = $this;
    }

    function expand($basepage, &$obj) {
        trigger_error("Pure virtual", E_USER_ERROR);
    }

    function getWikiPageLinks($basepage) {
        return false;
    }
}

class XmlRpc_LinkInfo {
    function XmlRpc_LinkInfo($page, $type, $href, $relation = '') {
	$this->page = $page;
	$this->type = $type;
	$this->href = $href;
	$this->relation = $relation;
	//$this->pageref = str_replace("/RPC2.php", "/index.php", $href);
    }
}

class Cached_Link extends Cached_DynamicContent {

    function isInlineElement() {
	return true;
    }

    /** Get link info (for XML-RPC support)
     *
     * This is here to support the XML-RPC listLinks method.
     * (See http://www.ecyrd.com/JSPWiki/Wiki.jsp?page=WikiRPCInterface)
     */
    function getLinkInfo($basepage) {
	return new XmlRpc_LinkInfo($this->_getName($basepage),
                                   $this->_getType(),
                                   $this->_getURL($basepage),
                                   $this->_getRelation($basepage));
    }
    
    function _getURL($basepage) {
	return $this->_url;
    }
    function __getRelation($basepage) {
	return $this->_relation;
    }
}

class Cached_WikiLink extends Cached_Link {

    function Cached_WikiLink ($page, $label = false, $anchor = false) {
	$this->_page = $page;
        if ($anchor)
            $this->_anchor = $anchor;
        if ($label and $label != $page)
            $this->_label = $label;
        $this->_basepage = false;    
    }

    function _getType() {
        return 'internal';
    }
    
    function getPagename($basepage) {
	$page = new WikiPageName($this->_page, $basepage);
	if ($page->isValid()) return $page->name;
	else return false;
    }

    function getWikiPageLinks($basepage) {
        if ($basepage == '') return false;
        if ($link = $this->getPagename($basepage)) 
            return array(array('linkto' => $link, 'relation' => 0));
        else return false;
    }

    function _getName($basepage) {
	return $this->getPagename($basepage);
    }

    function _getURL($basepage) {
	return WikiURL($this->getPagename($basepage));
	//return WikiURL($this->getPagename($basepage), false, 'abs_url');
    }

    function expand($basepage, &$markup) {
    	$this->_basepage = $basepage;
	$label = isset($this->_label) ? $this->_label : false;
	$anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        $page = new WikiPageName($this->_page, $basepage, $anchor);
        if ($page->isValid()) return WikiLink($page, 'auto', $label);
	else return HTML($label);
    }

    function asXML() {
	$label = isset($this->_label) ? $this->_label : false;
	$anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
	//TODO: need basepage for subpages like /Remove (within CreateTOC)
        $page = new WikiPageName($this->_page, $this->_basepage, $anchor);
	$link = WikiLink($page, 'auto', $label);
        return $link->asXML();
    }

    function asString() {
        if (isset($this->_label))
            return $this->_label;
        return $this->_page;
    }
}

class Cached_WikiLinkIfKnown extends Cached_WikiLink
{
    function Cached_WikiLinkIfKnown ($moniker) {
	$this->_page = $moniker;
    }

    function expand($basepage, &$markup) {
        return WikiLink($this->_page, 'if_known');
    }
}    

class Cached_SpellCheck extends Cached_WikiLink
{
    function Cached_SpellCheck ($word, $suggs) {
	$this->_page = $word;
	$this->suggestions = $suggs;
    }

    function expand($basepage, &$markup) {
        $link = HTML::a(array('class' => 'spell-wrong', 
			      'title' => 'SpellCheck: '.join(', ', $this->suggestions),
			      'name' => $this->_page), 
			$this->_page);
        return $link;
    }
}    
    
class Cached_PhpwikiURL extends Cached_DynamicContent
{
    function Cached_PhpwikiURL ($url, $label) {
	$this->_url = $url;
        if ($label)
            $this->_label = $label;
    }

    function isInlineElement() {
	return true;
    }

    function expand($basepage, &$markup) {
        $label = isset($this->_label) ? $this->_label : false;
        return LinkPhpwikiURL($this->_url, $label, $basepage);
    }

    function asXML() {
        $label = isset($this->_label) ? $this->_label : false;
        $link = LinkPhpwikiURL($this->_url, $label);
        return $link->asXML();
    }

    function asString() {
        if (isset($this->_label))
            return $this->_label;
        return $this->_url;
    }
}    

/*
 * Relations (::) are named links to pages.
 * Attributes (:=) are named metadata per page, "named links to numbers with units". 
 * We don't want to exhaust the linktable with numbers,
 * since this would create empty pages per each value, 
 * so we don't store the attributes as full relationlink. 
 * But we do store the attribute name as relation with an empty pagename 
 * to denote that this is an attribute, 
 * and to enable a fast listRelations mode=attributes
 */
class Cached_SemanticLink extends Cached_WikiLink {

    function Cached_SemanticLink ($url, $label=false) {
	$this->_url = $url;
        if ($label && $label != $url)
            $this->_label = $label;
        $this->_expandurl($this->_url);
    }

    function isInlineElement() {
	return true;
    }

    function getPagename($basepage) {
	if (!isset($this->_page)) return false;
	$page = new WikiPageName($this->_page, $basepage);
	if ($page->isValid()) return $page->name;
	else return false;
    }

    /* add relation to the link table.
     * attributes have the _relation, but not the _page set. 
     */
    function getWikiPageLinks($basepage) {
        if ($basepage == '') return false;
	if (!isset($this->_page) and isset($this->_attribute)) {
            // an attribute, we store it in the basepage now.
            $page = $GLOBALS['request']->getPage($basepage);	
            $page->setAttribute($this->_relation, $this->_attribute);
            return array(array('linkto' => '', 'relation' => $this->_relation));
	    //return false;
	}
        if ($link = $this->getPagename($basepage)) 
            return array(array('linkto' => $link, 'relation' => $this->_relation));
        else
            return false;
    }

    function _expandurl($url) {
        $m = array();
        if (!preg_match('/^ ([^:]+) (:[:=]) (.+) $/x', $url, $m)) {
            return HTML::strong(array('class' => 'rawurl'),
                                HTML::u(array('class' => 'baduri'),
                                        _("BAD semantic relation link")));
        }
	$this->_relation = urldecode($m[1]);
        $is_attribute = ($m[2] == ':=');
        if ($is_attribute) {
            $this->_attribute = urldecode($m[3]);
	    // since this stored in the markup cache, we are extra sensible 
	    // not to store false empty stuff.
	    $units = new Units();
            if (!DISABLE_UNITS and !$units->errcode) 
	    {
		$this->_attribute_base = $units->Definition($this->_attribute);
		$this->_unit = $units->baseunit($this->_attribute);
	    }
        } else {
	    $this->_page = urldecode($m[3]);
        }
	return $m;
    }

    function _expand($url, $label = false) {
	$m = $this->_expandurl($url);
        $class = 'wiki';
        // do not link to the attribute value, but to the attribute
        $is_attribute = ($m[2] == ':=');
        if ($label) {
            return HTML::span
		(
		 HTML::a(array('href'  => WikiURL($is_attribute ? $this->_relation : $this->_page),
			       'class' => "wiki ".($is_attribute ? "attribute" : "relation"),
			       'title' => $is_attribute 
			           ? (isset($this->_attribute_base) ? ("Attribute base value: ").$this->_attribute_base 
				      				    : ("Attribute value: ").$this->_attribute)
			       	   : sprintf(_("Relation %s to page %s"), $this->_relation, $this->_page)),
			 $label)
		 );
        } elseif ($is_attribute) {
            return HTML::span
		(
		 HTML::a(array('href'  => WikiURL($this->_relation),
			       'class' => "wiki attribute",
			       'title' => "Attribute base value: " .$this->_attribute_base),
			 $url)
		 );
        } else {
            return HTML::span
		(
		 HTML::a(array('href'  => WikiURL($this->_relation),
			       'class' => "wiki relation"),
			 $this->_relation),
		 HTML::strong($m[2]),
		 HTML::a(array('href'  => WikiURL($this->_page),
			       'class' => "wiki"),
			 $this->_page)
		 );
        }
    }

    function expand($basepage, &$markup) {
        $label = isset($this->_label) ? $this->_label : false;
        return $this->_expand($this->_url, $label);
    }

    function asXML() {
        $label = isset($this->_label) ? $this->_label : false;
        $link = $this->_expand($this->_url, $label);
        return $link->asXML();
    }

    function asString() {
        if (isset($this->_label))
            return $this->_label;
        return $this->_url;
    }
}
    
class Cached_ExternalLink extends Cached_Link {

    function Cached_ExternalLink($url, $label=false) {
	$this->_url = $url;
        if ($label && $label != $url)
            $this->_label = $label;
    }

    function _getType() {
        return 'external';
    }
    
    function _getName($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	return ($label and is_string($label)) ? $label : $this->_url;
    }

    function expand($basepage, &$markup) {
        global $request;

	$label = isset($this->_label) ? $this->_label : false;
	$link = LinkURL($this->_url, $label);

        if (GOOGLE_LINKS_NOFOLLOW) {
            // Ignores nofollow when the user who saved the page was authenticated. 
            $page = $request->getPage($basepage);
            $current = $page->getCurrentRevision(false);
            if (!$current->get('author_id'))
                $link->setAttr('rel', 'nofollow');
        }
        return $link;
    }

    function asString() {
        if (isset($this->_label))
            return $this->_label;
        return $this->_url;
    }
}

class Cached_InterwikiLink extends Cached_ExternalLink {
    
    function Cached_InterwikiLink($link, $label=false) {
	$this->_link = $link;
        if ($label)
            $this->_label = $label;
    }

    function getPagename($basepage) {
        list ($moniker, $page) = split (":", $this->_link, 2);
	$page = new WikiPageName($page, $basepage);
	if ($page->isValid()) return $page->name;
	else return false;
    }

    function getWikiPageLinks($basepage) {
        if ($basepage == '') return false;
	/* ":DontStoreLink" */
	if (substr($this->_link,0,1) == ':') return false;
	/* store only links to valid pagenames */
        if ($link = $this->getPagename($basepage)) 
            return array(array('linkto' => $link, 'relation' => 0));
        else return false; // dont store external links
    }

    function _getName($basepage) {
	$label = isset($this->_label) ? $this->_label : false;
	return ($label and is_string($label)) ? $label : $this->_link;
    }
    
    /* there may be internal interwiki links also */
    function _getType() {
        return $this->getPagename(false) ? 'internal' : 'external';
    }

    function _getURL($basepage) {
	$link = $this->expand($basepage, $this);
	return $link->getAttr('href');
    }

    function expand($basepage, &$markup) {
	$intermap = getInterwikiMap();
	$label = isset($this->_label) ? $this->_label : false;
	return $intermap->link($this->_link, $label);
    }

    function asString() {
        if (isset($this->_label))
            return $this->_label;
        return $this->_link;
    }
}

// Needed to put UserPages to backlinks. Special method to markup userpages with icons
// Thanks to PhpWiki:DanFr for finding this bug. 
// Fixed since 1.3.8, prev. versions had no userpages in backlinks
class Cached_UserLink extends Cached_WikiLink {
    function expand($basepage, &$markup) {
        $label = isset($this->_label) ? $this->_label : false;
	$anchor = isset($this->_anchor) ? (string)$this->_anchor : '';
        $page = new WikiPageName($this->_page, $basepage, $anchor);
	$link = WikiLink($page, 'auto', $label);
        // $link = HTML::a(array('href' => $PageName));
        $link->setContent(PossiblyGlueIconToText('wikiuser', $this->_page));
        $link->setAttr('class', 'wikiuser');
        return $link;
    }
}

/**
 * 1.3.13: Previously stored was only _pi. 
 * A fresh generated cache has now ->name and ->args also.
 * main::isActionPage only checks the raw content.
 */
class Cached_PluginInvocation extends Cached_DynamicContent {

    function Cached_PluginInvocation ($pi) {
	$this->_pi = $pi;
	$loader = $this->_getLoader();
        if (is_array($plugin_cmdline = $loader->parsePI($pi)) and $plugin_cmdline[1]) {
            $this->pi_name = $plugin_cmdline[0]; // plugin, plugin-form, plugin-list, plugin-link
            $this->name = $plugin_cmdline[1]->getName();
            $this->args = $plugin_cmdline[2];
        }
    }

    function setTightness($top, $bottom) {
        $this->_tightenable = 0;
        if ($top) $this->_tightenable |= 1;
        if ($bottom) $this->_tightenable |= 2;
    }
    
    function isInlineElement() {
	return false;
    }

    function expand($basepage, &$markup) {
        $loader = $this->_getLoader();

        $xml = $loader->expandPI($this->_pi, $GLOBALS['request'], $markup, $basepage);
        $div = HTML::div(array('class' => 'plugin'));
        if (isset($this->name))
            $id = GenerateId($this->name . 'Plugin');
   
	if (isset($this->_tightenable)) {
	    if ($this->_tightenable == 3) {
                $span = HTML::span(array('class' => 'plugin'), $xml);
                if (!empty($id))
                    $span->setAttr('id', $id);
	        return $span;
            }
	    $div->setInClass('tightenable');
	    $div->setInClass('top', ($this->_tightenable & 1) != 0);
	    $div->setInClass('bottom', ($this->_tightenable & 2) != 0);
	}
        if (!empty($id))
            $div->setAttr('id', $id);
	$div->pushContent($xml);
	return $div;
    }

    function asString() {
        return $this->_pi;
    }


    function getWikiPageLinks($basepage) {
        $loader = $this->_getLoader();

        return $loader->getWikiPageLinks($this->_pi, $basepage);
    }

    function & _getLoader() {
        static $loader = false;

	if (!$loader) {
            include_once('lib/WikiPlugin.php');
	    $loader = new WikiPluginLoader;
        }
        return $loader;
    }
}

// $Log: not supported by cvs2svn $
// Revision 1.50  2007/01/07 18:41:51  rurban
// Fix fallback ZipReader syntax error. Use label=false. Add parsed plugin names to the stored tree.
//
// Revision 1.49  2007/01/04 16:40:35  rurban
// Remove units object from CachedMarkup links, Store parsed linkinfo only: basevalue, baseunit.
//
// Revision 1.48  2007/01/03 21:22:08  rurban
// Use Units for attributes. Store the unified base value as Cached_SemanticLink->_attribute_base in the wikimarkup and display it as title.
//
// Revision 1.47  2007/01/02 13:17:57  rurban
// fix semantic page links and attributes, esp. attributes. they get stored as link to empty page also. tighten semantic url expander regex, omit want_content if not necessary
//
// Revision 1.46  2006/12/22 00:11:38  rurban
// add seperate expandurl method, to simplify pagename parsing
//
// Revision 1.45  2006/10/12 06:33:50  rurban
// decide later with which class to render this link (fixes interwiki link layout)

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
