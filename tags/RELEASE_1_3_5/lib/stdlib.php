<?php //rcs_id('$Id: stdlib.php,v 1.150 2003-09-13 22:43:00 carstenklapp Exp $');

/*
  Standard functions for Wiki functionality
    WikiURL($pagename, $args, $get_abs_url)
    IconForLink($protocol_or_url)
    LinkURL($url, $linktext)
    LinkImage($url, $alt)

    SplitQueryArgs ($query_args)
    LinkPhpwikiURL($url, $text)
    ConvertOldMarkup($content)
    
    class Stack { push($item), pop(), cnt(), top() }

    split_pagename ($page)
    NoSuchRevision ($request, $page, $version)
    TimezoneOffset ($time, $no_colon)
    Iso8601DateTime ($time)
    Rfc2822DateTime ($time)
    CTime ($time)
    __printf ($fmt)
    __sprintf ($fmt)
    __vsprintf ($fmt, $args)
    better_srand($seed = '')
    count_all($arg)
    isSubPage($pagename)
    subPageSlice($pagename, $pos)
    explodePageList($input, $perm = false)

  function: LinkInterWikiLink($link, $linktext)
  moved to: lib/interwiki.php
  function: linkExistingWikiWord($wikiword, $linktext, $version)
  moved to: lib/Theme.php
  function: LinkUnknownWikiWord($wikiword, $linktext)
  moved to: lib/Theme.php
  function: UpdateRecentChanges($dbi, $pagename, $isnewpage) 
  gone see: lib/plugin/RecentChanges.php
*/

define('MAX_PAGENAME_LENGTH', 100);

            
/**
 * Convert string to a valid XML identifier.
 *
 * XML 1.0 identifiers are of the form: [A-Za-z][A-Za-z0-9:_.-]*
 *
 * We would like to have, e.g. named anchors within wiki pages
 * names like "Table of Contents" --- clearly not a valid XML
 * fragment identifier.
 *
 * This function implements a one-to-one map from {any string}
 * to {valid XML identifiers}.
 *
 * It does this by
 * converting all bytes not in [A-Za-z0-9:_-],
 * and any leading byte not in [A-Za-z] to 'xbb.',
 * where 'bb' is the hexadecimal representation of the
 * character.
 *
 * As a special case, the empty string is converted to 'empty.'
 *
 * @param string $str
 * @return string
 */
function MangleXmlIdentifier($str) 
{
    if (!$str)
        return 'empty.';
    
    return preg_replace('/[^-_:A-Za-z0-9]|(?<=^)[^A-Za-z]/e',
                        "'x' . sprintf('%02x', ord('\\0')) . '.'",
                        $str);
}
    

/**
 * Generates a valid URL for a given Wiki pagename.
 * @param mixed $pagename If a string this will be the name of the Wiki page to link to.
 * 			  If a WikiDB_Page object function will extract the name to link to.
 * 			  If a WikiDB_PageRevision object function will extract the name to link to.
 * @param array $args 
 * @param boolean $get_abs_url Default value is false.
 * @return string The absolute URL to the page passed as $pagename.
 */
function WikiURL($pagename, $args = '', $get_abs_url = false) {
    $anchor = false;
    
    if (is_object($pagename)) {
        if (isa($pagename, 'WikiDB_Page')) {
            $pagename = $pagename->getName();
        }
        elseif (isa($pagename, 'WikiDB_PageRevision')) {
            $page = $pagename->getPage();
            $args['version'] = $pagename->getVersion();
            $pagename = $page->getName();
        }
        elseif (isa($pagename, 'WikiPageName')) {
            $anchor = $pagename->anchor;
            $pagename = $pagename->name;
        }
    }
    
    if (is_array($args)) {
        $enc_args = array();
        foreach  ($args as $key => $val) {
            if (!is_array($val)) // ugly hack for getURLtoSelf() which also takes POST vars
              $enc_args[] = urlencode($key) . '=' . urlencode($val);
        }
        $args = join('&', $enc_args);
    }

    if (USE_PATH_INFO) {
        $url = $get_abs_url ? SERVER_URL . VIRTUAL_PATH . "/" : "";
        $url .= preg_replace('/%2f/i', '/', rawurlencode($pagename));
        if ($args)
            $url .= "?$args";
    }
    else {
        $url = $get_abs_url ? SERVER_URL . SCRIPT_NAME : basename(SCRIPT_NAME);
        $url .= "?pagename=" . rawurlencode($pagename);
        if ($args)
            $url .= "&$args";
    }
    if ($anchor)
        $url .= "#" . MangleXmlIdentifier($anchor);
    return $url;
}

/** Convert relative URL to absolute URL.
 *
 * This converts a relative URL to one of PhpWiki's support files
 * to an absolute one.
 *
 * @param string $url
 * @return string Absolute URL
 */
function AbsoluteURL ($url) {
    if (preg_match('/^https?:/', $url))
        return $url;
    if ($url[0] != '/') {
        $base = USE_PATH_INFO ? VIRTUAL_PATH : dirname(SCRIPT_NAME);
        while ($base != '/' and substr($url, 0, 3) == "../") {
            $url = substr($url, 3);
            $base = dirname($base);
        }
        if ($base != '/')
            $base .= '/';
        $url = $base . $url;
    }
    return SERVER_URL . $url;
}

/**
 * Generates icon in front of links.
 *
 * @param string $protocol_or_url URL or protocol to determine which icon to use.
 *
 * @return HtmlElement HtmlElement object that contains data to create img link to
 * icon for use with url or protocol passed to the function. False if no img to be
 * displayed.
 */
function IconForLink($protocol_or_url) {
    global $Theme;
    if ($filename_suffix = false) {
        // display apache style icon for file type instead of protocol icon
        // - archive: unix:gz,bz2,tgz,tar,z; mac:dmg,dmgz,bin,img,cpt,sit; pc:zip;
        // - document: html, htm, text, txt, rtf, pdf, doc
        // - non-inlined image: jpg,jpeg,png,gif,tiff,tif,swf,pict,psd,eps,ps
        // - audio: mp3,mp2,aiff,aif,au
        // - multimedia: mpeg,mpg,mov,qt
    } else {
        list ($proto) = explode(':', $protocol_or_url, 2);
        $src = $Theme->getLinkIconURL($proto);
        if ($src)
            return HTML::img(array('src' => $src, 'alt' => "", 'class' => 'linkicon', 'border' => 0));
        else
            return false;
    }
}

/**
 * Glue icon in front of text.
 *
 * @param string $protocol_or_url Protocol or URL.  Used to determine the
 * proper icon.
 * @param string $text The text.
 * @return XmlContent.
 */
function PossiblyGlueIconToText($proto_or_url, $text) {
    global $request;
    if (! $request->getPref('noLinkIcons')) {
        $icon = IconForLink($proto_or_url);
        if ($icon) {
            if (!is_object($text)) {
                preg_match('/^\s*(\S*)(.*?)\s*$/', $text, $m);
                list (, $first_word, $tail) = $m;
            }
            else {
                $first_word = $text;
                $tail = false;
            }
            
            $text = HTML::span(array('style' => 'white-space: nowrap'),
                               $icon, $first_word);
            if ($tail)
                $text = HTML($text, $tail);
        }
    }
    return $text;
}

/**
 * Determines if the url passed to function is safe, by detecting if the characters
 * '<', '>', or '"' are present.
 *
 * @param string $url URL to check for unsafe characters.
 * @return boolean True if same, false else.
 */
function IsSafeURL($url) {
    return !ereg('[<>"]', $url);
}

/**
 * Generates an HtmlElement object to store data for a link.
 *
 * @param string $url URL that the link will point to.
 * @param string $linktext Text to be displayed as link.
 * @return HtmlElement HtmlElement object that contains data to construct an html link.
 */
function LinkURL($url, $linktext = '') {
    // FIXME: Is this needed (or sufficient?)
    if(! IsSafeURL($url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (!$linktext)
            $linktext = preg_replace("/mailto:/A", "", $url);
        
        $link = HTML::a(array('href' => $url),
                        PossiblyGlueIconToText($url, $linktext));
        
    }
    $link->setAttr('class', $linktext ? 'namedurl' : 'rawurl');
    return $link;
}


function LinkImage($url, $alt = false) {
    // FIXME: Is this needed (or sufficient?)
    if(! IsSafeURL($url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (empty($alt))
            $alt = $url;
        $link = HTML::img(array('src' => $url, 'alt' => $alt));
    }
    $link->setAttr('class', 'inlineimage');
    return $link;
}



class Stack {
    var $items = array();
    var $size = 0;
    
    function push($item) {
        $this->items[$this->size] = $item;
        $this->size++;
        return true;
    }  
    
    function pop() {
        if ($this->size == 0) {
            return false; // stack is empty
        }  
        $this->size--;
        return $this->items[$this->size];
    }  
    
    function cnt() {
        return $this->size;
    }  
    
    function top() {
        if($this->size)
            return $this->items[$this->size - 1];
        else
            return '';
    }
    
}  
// end class definition

function SplitQueryArgs ($query_args = '') 
{
    $split_args = split('&', $query_args);
    $args = array();
    while (list($key, $val) = each($split_args))
        if (preg_match('/^ ([^=]+) =? (.*) /x', $val, $m))
            $args[$m[1]] = $m[2];
    return $args;
}

function LinkPhpwikiURL($url, $text = '', $basepage) {
    $args = array();
    
    if (!preg_match('/^ phpwiki: ([^?]*) [?]? (.*) $/x', $url, $m)) {
        return HTML::strong(array('class' => 'rawurl'),
                            HTML::u(array('class' => 'baduri'),
                                    _("BAD phpwiki: URL")));
    }

    if ($m[1])
        $pagename = urldecode($m[1]);
    $qargs = $m[2];
    
    if (empty($pagename) &&
        preg_match('/^(diff|edit|links|info)=([^&]+)$/', $qargs, $m)) {
        // Convert old style links (to not break diff links in
        // RecentChanges).
        $pagename = urldecode($m[2]);
        $args = array("action" => $m[1]);
    }
    else {
        $args = SplitQueryArgs($qargs);
    }

    if (empty($pagename))
        $pagename = $GLOBALS['request']->getArg('pagename');

    if (isset($args['action']) && $args['action'] == 'browse')
        unset($args['action']);
    
    /*FIXME:
      if (empty($args['action']))
      $class = 'wikilink';
      else if (is_safe_action($args['action']))
      $class = 'wikiaction';
    */
    if (empty($args['action']) || is_safe_action($args['action']))
        $class = 'wikiaction';
    else {
        // Don't allow administrative links on unlocked pages.
        $dbi = $GLOBALS['request']->getDbh();
        $page = $dbi->getPage($basepage);
        if (!$page->get('locked'))
            return HTML::span(array('class' => 'wikiunsafe'),
                              HTML::u(_("Lock page to enable link")));
        $class = 'wikiadmin';
    }
    
    if (!$text)
        $text = HTML::span(array('class' => 'rawurl'), $url);

    $wikipage = new WikiPageName($pagename);
    if (!$wikipage->isValid()) {
        global $Theme;
        return $Theme->linkBadWikiWord($wikipage, $url);
    }
    
    return HTML::a(array('href'  => WikiURL($pagename, $args),
                         'class' => $class),
                   $text);
}

/**
 * A class to assist in parsing wiki pagenames.
 *
 * Now with subpages and anchors, parsing and passing around
 * pagenames is more complicated.  This should help.
 */
class WikiPagename
{
    /** Short name for page.
     *
     * This is the value of $name passed to the constructor.
     * (For use, e.g. as a default label for links to the page.)
     */
    var $shortName;

    /** The full page name.
     *
     * This is the full name of the page (without anchor).
     */
    var $name;
    
    /** The anchor.
     *
     * This is the referenced anchor within the page, or the empty string.
     */
    var $anchor;
    
    /** Constructor
     *
     * @param mixed $name Page name.
     * WikiDB_Page, WikiDB_PageRevision, or string.
     * This can be a relative subpage name (like '/SubPage'),
     * or can be the empty string to refer to the $basename.
     *
     * @param string $anchor For links to anchors in page.
     *
     * @param mixed $basename Page name from which to interpret
     * relative or other non-fully-specified page names.
     */
    function WikiPageName($name, $basename=false, $anchor=false) {
        if (is_string($name)) {
            $this->shortName = $name;
        
            if (empty($name) or $name[0] == SUBPAGE_SEPARATOR) {
                if ($basename)
                    $name = $this->_pagename($basename) . $name;
                else
                    $name = $this->_normalize_bad_pagename($name);
            }
        }
        else {
            $name = $this->_pagename($name);
            $this->shortName = $name;
        }

        $this->name = $this->_check($name);
        $this->anchor = (string)$anchor;
    }

    function getParent() {
        $name = $this->name;
        if (!($tail = strrchr($name, SUBPAGE_SEPARATOR)))
            return false;
        return substr($name, 0, -strlen($tail));
    }

    function isValid($strict = false) {
        if ($strict)
            return !isset($this->_errors);
        return !empty($this->name);
    }

    function getWarnings() {
        $warnings = array();
        if (isset($this->_warnings))
            $warnings = array_merge($warnings, $this->_warnings);
        if (isset($this->_errors))
            $warnings = array_merge($warnings, $this->_errors);
        if (!$warnings)
            return false;
        
        return sprintf(_("'%s': Bad page name: %s"),
                       $this->shortName, join(', ', $warnings));
    }
    
    function _pagename($page) {
	if (isa($page, 'WikiDB_Page'))
	    return $page->getName();
        elseif (isa($page, 'WikiDB_PageRevision'))
	    return $page->getPageName();
        elseif (isa($page, 'WikiPageName'))
	    return $page->name;
        if (!is_string($page)) {
            trigger_error(sprintf("Non-string pagename '%s' (%s)(%s)",
                                  $page, gettype($page), get_class($page)),
                          E_USER_NOTICE);
        }
	//assert(is_string($page));
	return $page;
    }

    function _normalize_bad_pagename($name) {
        trigger_error("Bad pagename: " . $name, E_USER_WARNING);

        // Punt...  You really shouldn't get here.
        if (empty($name)) {
            global $request;
            return $request->getArg('pagename');
        }
        assert($name[0] == SUBPAGE_SEPARATOR);
        return substr($name, 1);
    }


    function _check($pagename) {
        // Compress internal white-space to single space character.
        $pagename = preg_replace('/[\s\xa0]+/', ' ', $orig = $pagename);
        if ($pagename != $orig)
            $this->_warnings[] = _("White space converted to single space");
    
        // Delete any control characters.
        $pagename = preg_replace('/[\x00-\x1f\x7f\x80-\x9f]/', '', $orig = $pagename);
        if ($pagename != $orig)
            $this->_errors[] = _("Control characters not allowed");

        // Strip leading and trailing white-space.
        $pagename = trim($pagename);

        $orig = $pagename;
        while ($pagename and $pagename[0] == SUBPAGE_SEPARATOR)
            $pagename = substr($pagename, 1);
        if ($pagename != $orig)
            $this->_errors[] = sprintf(_("Leading %s not allowed"), SUBPAGE_SEPARATOR);

        if (preg_match('/[:;]/', $pagename))
            $this->_warnings[] = _("';' and ':' in pagenames are deprecated");
        
        if (strlen($pagename) > MAX_PAGENAME_LENGTH) {
            $pagename = substr($pagename, 0, MAX_PAGENAME_LENGTH);
            $this->_errors[] = _("too long");
        }
        

        if ($pagename == '.' or $pagename == '..') {
            $this->_errors[] = sprintf(_("illegal pagename"), $pagename);
            $pagename = '';
        }
        
        return $pagename;
    }
}

/**
 * Convert old page markup to new-style markup.
 *
 * @param string $text Old-style wiki markup.
 *
 * @param string $markup_type
 * One of: <dl>
 * <dt><code>"block"</code>  <dd>Convert all markup.
 * <dt><code>"inline"</code> <dd>Convert only inline markup.
 * <dt><code>"links"</code>  <dd>Convert only link markup.
 * </dl>
 *
 * @return string New-style wiki markup.
 *
 * @bugs Footnotes don't work quite as before (esp if there are
 *   multiple references to the same footnote.  But close enough,
 *   probably for now....
 */
function ConvertOldMarkup ($text, $markup_type = "block") {

    static $subs;
    static $block_re;
    
    if (empty($subs)) {
        /*****************************************************************
         * Conversions for inline markup:
         */

        // escape tilde's
        $orig[] = '/~/';
        $repl[] = '~~';

        // escape escaped brackets
        $orig[] = '/\[\[/';
        $repl[] = '~[';

        // change ! escapes to ~'s.
        global $AllowedProtocols, $WikiNameRegexp, $request;
        include_once('lib/interwiki.php');
        $map = InterWikiMap::GetMap($request);
        $bang_esc[] = "(?:$AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]";
        $bang_esc[] = $map->getRegexp() . ":[^\\s.,;?()]+"; // FIXME: is this really needed?
        $bang_esc[] = $WikiNameRegexp;
        $orig[] = '/!((?:' . join(')|(', $bang_esc) . '))/';
        $repl[] = '~\\1';

        $subs["links"] = array($orig, $repl);

        // Escape '<'s
        //$orig[] = '/<(?!\?plugin)|(?<!^)</m';
        //$repl[] = '~<';
        
        // Convert footnote references.
        $orig[] = '/(?<=.)(?<!~)\[\s*(\d+)\s*\]/m';
        $repl[] = '#[|ftnt_ref_\\1]<sup>~[[\\1|#ftnt_\\1]~]</sup>';

        // Convert old style emphases to HTML style emphasis.
        $orig[] = '/__(.*?)__/';
        $repl[] = '<strong>\\1</strong>';
        $orig[] = "/''(.*?)''/";
        $repl[] = '<em>\\1</em>';

        // Escape nestled markup.
        $orig[] = '/^(?<=^|\s)[=_](?=\S)|(?<=\S)[=_*](?=\s|$)/m';
        $repl[] = '~\\0';
        
        // in old markup headings only allowed at beginning of line
        $orig[] = '/!/';
        $repl[] = '~!';

        $subs["inline"] = array($orig, $repl);

        /*****************************************************************
         * Patterns which match block markup constructs which take
         * special handling...
         */

        // Indented blocks
        $blockpats[] = '[ \t]+\S(?:.*\s*\n[ \t]+\S)*';

        // Tables
        $blockpats[] = '\|(?:.*\n\|)*';

        // List items
        $blockpats[] = '[#*;]*(?:[*#]|;.*?:)';

        // Footnote definitions
        $blockpats[] = '\[\s*(\d+)\s*\]';

        // Plugins
        $blockpats[] = '<\?plugin(?:-form)?\b.*\?>\s*$';

        // Section Title
        $blockpats[] = '!{1,3}[^!]';

        $block_re = ( '/\A((?:.|\n)*?)(^(?:'
                      . join("|", $blockpats)
                      . ').*$)\n?/m' );
        
    }
    
    if ($markup_type != "block") {
        list ($orig, $repl) = $subs[$markup_type];
        return preg_replace($orig, $repl, $text);
    }
    else {
        list ($orig, $repl) = $subs['inline'];
        $out = '';
        while (preg_match($block_re, $text, $m)) {
            $text = substr($text, strlen($m[0]));
            list (,$leading_text, $block) = $m;
            $suffix = "\n";
            
            if (strchr(" \t", $block[0])) {
                // Indented block
                $prefix = "<pre>\n";
                $suffix = "\n</pre>\n";
            }
            elseif ($block[0] == '|') {
                // Old-style table
                $prefix = "<?plugin OldStyleTable\n";
                $suffix = "\n?>\n";
            }
            elseif (strchr("#*;", $block[0])) {
                // Old-style list item
                preg_match('/^([#*;]*)([*#]|;.*?:) */', $block, $m);
                list (,$ind,$bullet) = $m;
                $block = substr($block, strlen($m[0]));
                
                $indent = str_repeat('     ', strlen($ind));
                if ($bullet[0] == ';') {
                    //$term = ltrim(substr($bullet, 1));
                    //return $indent . $term . "\n" . $indent . '     ';
                    $prefix = $ind . $bullet;
                }
                else
                    $prefix = $indent . $bullet . ' ';
            }
            elseif ($block[0] == '[') {
                // Footnote definition
                preg_match('/^\[\s*(\d+)\s*\]/', $block, $m);
                $footnum = $m[1];
                $block = substr($block, strlen($m[0]));
                $prefix = "#[|ftnt_${footnum}]~[[${footnum}|#ftnt_ref_${footnum}]~] ";
            }
            elseif ($block[0] == '<') {
                // Plugin.
                // HACK: no inline markup...
                $prefix = $block;
                $block = '';
            }
            elseif ($block[0] == '!') {
                // Section heading
                preg_match('/^!{1,3}/', $block, $m);
                $prefix = $m[0];
                $block = substr($block, strlen($m[0]));
            }
            else {
                // AAck!
                assert(0);
            }

            $out .= ( preg_replace($orig, $repl, $leading_text)
                      . $prefix
                      . preg_replace($orig, $repl, $block)
                      . $suffix );
        }
        return $out . preg_replace($orig, $repl, $text);
    }
}


/**
 * Expand tabs in string.
 *
 * Converts all tabs to (the appropriate number of) spaces.
 *
 * @param string $str
 * @param integer $tab_width
 * @return string
 */
function expand_tabs($str, $tab_width = 8) {
    $split = split("\t", $str);
    $tail = array_pop($split);
    $expanded = "\n";
    foreach ($split as $hunk) {
        $expanded .= $hunk;
        $pos = strlen(strrchr($expanded, "\n")) - 1;
        $expanded .= str_repeat(" ", ($tab_width - $pos % $tab_width));
    }
    return substr($expanded, 1) . $tail;
}

/**
 * Split WikiWords in page names.
 *
 * It has been deemed useful to split WikiWords (into "Wiki Words") in
 * places like page titles. This is rumored to help search engines
 * quite a bit.
 *
 * @param $page string The page name.
 *
 * @return string The split name.
 */
function split_pagename ($page) {
    
    if (preg_match("/\s/", $page))
        return $page;           // Already split --- don't split any more.
    
    // FIXME: this algorithm is Anglo-centric.
    static $RE;
    if (!isset($RE)) {
        // This mess splits between a lower-case letter followed by
        // either an upper-case or a numeral; except that it wont
        // split the prefixes 'Mc', 'De', or 'Di' off of their tails.
        $RE[] = '/([[:lower:]])((?<!Mc|De|Di)[[:upper:]]|\d)/';
        // This the single-letter words 'I' and 'A' from any following
        // capitalized words.
	$sep = preg_quote(SUBPAGE_SEPARATOR, '/');
        $RE[] = "/(?<= |${sep}|^)([AI])([[:upper:]][[:lower:]])/";
        // Split numerals from following letters.
        $RE[] = '/(\d)([[:alpha:]])/';
        
        foreach ($RE as $key => $val)
            $RE[$key] = pcre_fix_posix_classes($val);
    }

    foreach ($RE as $regexp) {
	$page = preg_replace($regexp, '\\1 \\2', $page);
    }
    return $page;
}

function NoSuchRevision (&$request, $page, $version) {
    $html = HTML(HTML::h2(_("Revision Not Found")),
                 HTML::p(fmt("I'm sorry.  Version %d of %s is not in the database.",
                             $version, WikiLink($page, 'auto'))));
    include_once('lib/Template.php');
    GeneratePage($html, _("Bad Version"), $page->getCurrentRevision());
    $request->finish();
}


/**
 * Get time offset for local time zone.
 *
 * @param $time time_t Get offset for this time. Default: now.
 * @param $no_colon boolean Don't put colon between hours and minutes.
 * @return string Offset as a string in the format +HH:MM.
 */
function TimezoneOffset ($time = false, $no_colon = false) {
    if ($time === false)
        $time = time();
    $secs = date('Z', $time);

    if ($secs < 0) {
        $sign = '-';
        $secs = -$secs;
    }
    else {
        $sign = '+';
    }
    $colon = $no_colon ? '' : ':';
    $mins = intval(($secs + 30) / 60);
    return sprintf("%s%02d%s%02d",
                   $sign, $mins / 60, $colon, $mins % 60);
}


/**
 * Format time in ISO-8601 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in ISO-8601 format.
 */
function Iso8601DateTime ($time = false) {
    if ($time === false)
        $time = time();
    $tzoff = TimezoneOffset($time);
    $date  = date('Y-m-d', $time);
    $time  = date('H:i:s', $time);
    return $date . 'T' . $time . $tzoff;
}

/**
 * Format time in RFC-2822 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in RFC-2822 format.
 */
function Rfc2822DateTime ($time = false) {
    if ($time === false)
        $time = time();
    return date('D, j M Y H:i:s ', $time) . TimezoneOffset($time, 'no colon');
}

/**
 * Format time in RFC-1123 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in RFC-1123 format.
 */
function Rfc1123DateTime ($time = false) {
    if ($time === false)
        $time = time();
    return gmdate('D, d M Y H:i:s \G\M\T', $time);
}

/** Parse date in RFC-1123 format.
 *
 * According to RFC 1123 we must accept dates in the following
 * formats:
 *
 *   Sun, 06 Nov 1994 08:49:37 GMT  ; RFC 822, updated by RFC 1123
 *   Sunday, 06-Nov-94 08:49:37 GMT ; RFC 850, obsoleted by RFC 1036
 *   Sun Nov  6 08:49:37 1994       ; ANSI C's asctime() format
 *
 * (Though we're only allowed to generate dates in the first format.)
 */
function ParseRfc1123DateTime ($timestr) {
    $timestr = trim($timestr);
    if (preg_match('/^ \w{3},\s* (\d{1,2}) \s* (\w{3}) \s* (\d{4}) \s*'
                   .'(\d\d):(\d\d):(\d\d) \s* GMT $/ix',
                   $timestr, $m)) {
        list(, $mday, $mon, $year, $hh, $mm, $ss) = $m;
    }
    elseif (preg_match('/^ \w+,\s* (\d{1,2})-(\w{3})-(\d{2}|\d{4}) \s*'
                       .'(\d\d):(\d\d):(\d\d) \s* GMT $/ix',
                       $timestr, $m)) {
        list(, $mday, $mon, $year, $hh, $mm, $ss) = $m;
        if ($year < 70) $year += 2000;
        elseif ($year < 100) $year += 1900;
    }
    elseif (preg_match('/^\w+\s* (\w{3}) \s* (\d{1,2}) \s*'
                       .'(\d\d):(\d\d):(\d\d) \s* (\d{4})$/ix',
                       $timestr, $m)) {
        list(, $mon, $mday, $hh, $mm, $ss, $year) = $m;
    }
    else {
        // Parse failed.
        return false;
    }

    $time = strtotime("$mday $mon $year ${hh}:${mm}:${ss} GMT");
    if ($time == -1)
        return false;           // failed
    return $time;
}

/**
 * Format time to standard 'ctime' format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time.
 */
function CTime ($time = false)
{
    if ($time === false)
        $time = time();
    return date("D M j H:i:s Y", $time);
}



/**
 * Internationalized printf.
 *
 * This is essentially the same as PHP's built-in printf
 * with the following exceptions:
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * Example:
 *
 * In php code, use:
 * <pre>
 *    __printf("Differences between versions %s and %s of %s",
 *             $new_link, $old_link, $page_link);
 * </pre>
 *
 * Then in locale/po/de.po, one can reorder the printf arguments:
 *
 * <pre>
 *    msgid "Differences between %s and %s of %s."
 *    msgstr "Der Unterschiedsergebnis von %3$s, zwischen %1$s und %2$s."
 * </pre>
 *
 * (Note that while PHP tries to expand $vars within double-quotes,
 * the values in msgstr undergo no such expansion, so the '$'s
 * okay...)
 *
 * One shouldn't use reordered arguments in the default format string.
 * Backslashes in the default string would be necessary to escape the
 * '$'s, and they'll cause all kinds of trouble....
 */ 
function __printf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    echo __vsprintf($fmt, $args);
}

/**
 * Internationalized sprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __sprintf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    return __vsprintf($fmt, $args);
}

/**
 * Internationalized vsprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __vsprintf ($fmt, $args) {
    $fmt = gettext($fmt);
    // PHP's sprintf doesn't support variable with specifiers,
    // like sprintf("%*s", 10, "x"); --- so we won't either.
    
    if (preg_match_all('/(?<!%)%(\d+)\$/x', $fmt, $m)) {
        // Format string has '%2$s' style argument reordering.
        // PHP doesn't support this.
        if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $fmt))
            // literal variable name substitution only to keep locale
            // strings uncluttered
            trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                  '%1\$s','%s'), E_USER_WARNING); //php+locale error
        
        $fmt = preg_replace('/(?<!%)%\d+\$/x', '%', $fmt);
        $newargs = array();
        
        // Reorder arguments appropriately.
        foreach($m[1] as $argnum) {
            if ($argnum < 1 || $argnum > count($args))
                trigger_error(sprintf(_("%s: argument index out of range"), 
                                      $argnum), E_USER_WARNING);
            $newargs[] = $args[$argnum - 1];
        }
        $args = $newargs;
    }
    
    // Not all PHP's have vsprintf, so...
    array_unshift($args, $fmt);
    return call_user_func_array('sprintf', $args);
}


class fileSet {
    /**
     * Build an array in $this->_fileList of files from $dirname.
     * Subdirectories are not traversed.
     *
     * (This was a function LoadDir in lib/loadsave.php)
     * See also http://www.php.net/manual/en/function.readdir.php
     */
    function getFiles() {
        return $this->_fileList;
    }

    function _filenameSelector($filename) {
        if (! $this->_pattern)
            return true;
        else {
            return glob_match ($this->_pattern, $filename, $this->_case);
        }
    }

    function fileSet($directory, $filepattern = false) {
        $this->_fileList = array();
        $this->_pattern = $filepattern;
        $this->_case = !isWindows();
        $this->_pathsep = '/';

        if (empty($directory)) {
            trigger_error(sprintf(_("%s is empty."), 'directoryname'),
                          E_USER_NOTICE);
            return; // early return
        }

        @ $dir_handle = opendir($dir=$directory);
        if (empty($dir_handle)) {
            trigger_error(sprintf(_("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            return; // early return
        }

        while ($filename = readdir($dir_handle)) {
            if ($filename[0] == '.' || filetype($dir . $this->_pathsep . $filename) != 'file')
                continue;
            if ($this->_filenameSelector($filename)) {
                array_push($this->_fileList, "$filename");
                //trigger_error(sprintf(_("found file %s"), $filename),
                //                      E_USER_NOTICE); //debugging
            }
        }
        closedir($dir_handle);
    }
};

// File globbing

// expands a list containing regex's to its matching entries
class ListRegexExpand {
    var $match, $list, $index, $case_sensitive;
    function ListRegexExpand (&$list, $match, $case_sensitive = true) {
    	$this->match = str_replace('/','\/',$match);
    	$this->list = &$list;
    	$this->case_sensitive = $case_sensitive;	
    }
    function listMatchCallback ($item, $key) {
    	if (preg_match('/' . $this->match . ($this->case_sensitive ? '/' : '/i'), $item)) {
	    unset($this->list[$this->index]);
            $this->list[] = $item;
        }
    }
    function expandRegex ($index, &$pages) {
    	$this->index = $index;
    	array_walk($pages, array($this, 'listMatchCallback'));
        return $this->list;
    }
}

// convert fileglob to regex style
function glob_to_pcre ($glob) {
    $re = preg_replace('/\./', '\\.', $glob);
    $re = preg_replace(array('/\*/','/\?/'), array('.*','.'), $glob);
    if (!preg_match('/^[\?\*]/',$glob))
        $re = '^' . $re;
    if (!preg_match('/[\?\*]$/',$glob))
        $re = $re . '$';
    return $re;
}

function glob_match ($glob, $against, $case_sensitive = true) {
    return preg_match('/' . glob_to_pcre($glob) . ($case_sensitive ? '/' : '/i'), $against);
}

function explodeList($input, $allnames, $glob_style = true, $case_sensitive = true) {
    $list = explode(',',$input);
    // expand wildcards from list of $allnames
    if (preg_match('/[\?\*]/',$input)) {
        for ($i = 0; $i < sizeof($list); $i++) {
            $f = $list[$i];
            if (preg_match('/[\?\*]/',$f)) {
            	reset($allnames);
            	$expand = new ListRegexExpand($list, $glob_style ? glob_to_pcre($f) : $f, $case_sensitive);
            	$expand->expandRegex($i, $allnames);
            }
        }
    }
    return $list;
}

// echo implode(":",explodeList("Test*",array("xx","Test1","Test2")));

function explodePageList($input, $perm = false) {
    // expand wildcards from list of all pages
    if (preg_match('/[\?\*]/',$input)) {
        $dbi = $GLOBALS['request']->_dbi;
        $allPagehandles = $dbi->getAllPages($perm);
        while ($pagehandle = $allPagehandles->next()) {
            $allPages[] = $pagehandle->getName();
        }
        return explodeList($input, $allPages);
    } else {
        return explode(',',$input);
    }
}

// Class introspections

/** Determine whether object is of a specified type.
 *
 * @param $object object An object.
 * @param $class string Class name.
 * @return bool True iff $object is a $class
 * or a sub-type of $class. 
 */
function isa ($object, $class) 
{
    $lclass = strtolower($class);

    return is_object($object)
        && ( get_class($object) == strtolower($lclass)
             || is_subclass_of($object, $lclass) );
}

/** Determine whether (possible) object has method.
 *
 * @param $object mixed Object
 * @param $method string Method name
 * @return bool True iff $object is an object with has method $method.
 */
function can ($object, $method) 
{
    return is_object($object) && method_exists($object, strtolower($method));
}

/** Determine whether a function is okay to use.
 *
 * Some providers (e.g. Lycos) disable some of PHP functions for
 * "security reasons."  This makes those functions, of course,
 * unusable, despite the fact the function_exists() says they
 * exist.
 *
 * This function test to see if a function exists and is not
 * disallowed by PHP's disable_functions config setting.
 *
 * @param string $function_name  Function name
 * @return bool  True iff function can be used.
 */
function function_usable($function_name)
{
    static $disabled;
    if (!is_array($disabled)) {
        $disabled = array();
        // Use get_cfg_var since ini_get() is one of the disabled functions
        // (on Lycos, at least.)
        $split = preg_split('/\s*,\s*/', trim(get_cfg_var('disable_functions')));
        foreach ($split as $f)
            $disabled[strtolower($f)] = true;
    }

    return ( function_exists($function_name)
             and ! isset($disabled[strtolower($function_name)])
             );
}
    
    
/** Hash a value.
 *
 * This is used for generating ETags.
 */
function hash ($x) {
    if (is_scalar($x)) {
        return $x;
    }
    elseif (is_array($x)) {            
        ksort($x);
        return md5(serialize($x));
    }
    elseif (is_object($x)) {
        return $x->hash();
    }
    trigger_error("Can't hash $x", E_USER_ERROR);
}

    
/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE); //debugging
    }
}

/**
 * Recursively count all non-empty elements 
 * in array of any dimension or mixed - i.e. 
 * array('1' => 2, '2' => array('1' => 3, '2' => 4))
 * See http://www.php.net/manual/en/function.count.php
 */
function count_all($arg) {
    // skip if argument is empty
    if ($arg) {
        //print_r($arg); //debugging
        $count = 0;
        // not an array, return 1 (base case) 
        if(!is_array($arg))
            return 1;
        // else call recursively for all elements $arg
        foreach($arg as $key => $val)
            $count += count_all($val);
        return $count;
    }
}

function isSubPage($pagename) {
    return (strstr($pagename, SUBPAGE_SEPARATOR));
}

function subPageSlice($pagename, $pos) {
    $pages = explode(SUBPAGE_SEPARATOR,$pagename);
    $pages = array_slice($pages,$pos,1);
    return $pages[0];
}

/**
 * Alert
 *
 * Class for "popping up" and alert box.  (Except that right now, it doesn't
 * pop up...)
 *
 * FIXME:
 * This is a hackish and needs to be refactored.  However it would be nice to
 * unify all the different methods we use for showing Alerts and Dialogs.
 * (E.g. "Page deleted", login form, ...)
 */
class Alert {
    /** Constructor
     *
     * @param object $request
     * @param mixed $head  Header ("title") for alert box.
     * @param mixed $body  The text in the alert box.
     * @param hash $buttons  An array mapping button labels to URLs.
     *    The default is a single "Okay" button pointing to $request->getURLtoSelf().
     */
    function Alert($head, $body, $buttons=false) {
        if ($buttons === false)
            $buttons = array();

        $this->_tokens = array('HEADER' => $head, 'CONTENT' => $body);
        $this->_buttons = $buttons;
    }

    /**
     * Show the alert box.
     */
    function show(&$request) {
        global $request;

        $tokens = $this->_tokens;
        $tokens['BUTTONS'] = $this->_getButtons();
        
        $request->discardOutput();
        $tmpl = new Template('dialog', $request, $tokens);
        $tmpl->printXML();
        $request->finish();
    }


    function _getButtons() {
        global $request;

        $buttons = $this->_buttons;
        if (!$buttons)
            $buttons = array(_("Okay") => $request->getURLtoSelf());
        
        global $Theme;
        foreach ($buttons as $label => $url)
            print "$label $url\n";
            $out[] = $Theme->makeButton($label, $url, 'wikiaction');
        return new XmlContent($out);
    }
}

                      
        
// $Log: not supported by cvs2svn $
// Revision 1.149  2003/03/26 19:37:08  dairiki
// Fix "object to string conversion" bug with external image links.
//
// Revision 1.148  2003/03/25 21:03:02  dairiki
// Cleanup debugging output.
//
// Revision 1.147  2003/03/13 20:17:05  dairiki
// Bug fix: Fix linking of pages whose names contain a hash ('#').
//
// Revision 1.146  2003/03/07 02:46:24  dairiki
// function_usable(): New function.
//
// Revision 1.145  2003/03/04 01:55:05  dairiki
// Fix to ensure absolute URL for logo in RSS recent changes.
//
// Revision 1.144  2003/02/26 00:39:30  dairiki
// Bug fix: for magic PhpWiki URLs, "lock page to enable link" message was
// being displayed at incorrect times.
//
// Revision 1.143  2003/02/26 00:10:26  dairiki
// More/better/different checks for bad page names.
//
// Revision 1.142  2003/02/25 22:19:46  dairiki
// Add some sanity checking for pagenames.
//
// Revision 1.141  2003/02/22 20:49:55  dairiki
// Fixes for "Call-time pass by reference has been deprecated" errors.
//
// Revision 1.140  2003/02/21 23:33:29  dairiki
// Set alt="" on the link icon image tags.
// (See SF bug #675141.)
//
// Revision 1.139  2003/02/21 22:16:27  dairiki
// Get rid of MakeWikiForm, and form-style MagicPhpWikiURLs.
// These have been obsolete for quite awhile (I hope).
//
// Revision 1.138  2003/02/21 04:12:36  dairiki
// WikiPageName: fixes for new cached links.
//
// Alert: new class for displaying alerts.
//
// ExtractWikiPageLinks and friends are now gone.
//
// LinkBracketLink moved to InlineParser.php
//
// Revision 1.137  2003/02/18 23:13:40  dairiki
// Wups again.  Typo fix.
//
// Revision 1.136  2003/02/18 21:52:07  dairiki
// Fix so that one can still link to wiki pages with # in their names.
// (This was made difficult by the introduction of named tags, since
// '[Page #1]' is now a link to anchor '1' in page 'Page'.
//
// Now the ~ escape for page names should work: [Page ~#1].
//
// Revision 1.135  2003/02/18 19:17:04  dairiki
// split_pagename():
//     Bug fix. 'ThisIsABug' was being split to 'This IsA Bug'.
//     Cleanup up subpage splitting code.
//
// Revision 1.134  2003/02/16 19:44:20  dairiki
// New function hash().  This is a helper, primarily for generating
// HTTP ETags.
//
// Revision 1.133  2003/02/16 04:50:09  dairiki
// New functions:
// Rfc1123DateTime(), ParseRfc1123DateTime()
// for converting unix timestamps to and from strings.
//
// These functions produce and grok the time strings
// in the format specified by RFC 2616 for use in HTTP headers
// (like Last-Modified).
//
// Revision 1.132  2003/01/04 22:19:43  carstenklapp
// Bugfix UnfoldSubpages: "Undefined offset: 1" error when plugin invoked
// on a page with no subpages (explodeList(): array 0-based, sizeof 1-based).
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