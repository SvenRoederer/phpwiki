<?php rcs_id('$Id: HtmlElement.php,v 1.12 2002-01-26 01:51:13 dairiki Exp $');
/*
 * Code for writing XML.
 */
require_once("lib/XmlElement.php");
/**
 * An XML element.
 */

class HtmlElement extends XmlElement
{
    //function HtmlElement ($tagname /* , $attr_or_content , ...*/) {
    //    $this->_init(func_get_args());
    //    $this->_properties = HTML::getTagProperties($tagname);
    //} 
    

    function _init ($args) {
        XmlElement::_init($args);
        $this->_properties = HTML::getTagProperties($this->_tag);
    }
        
    /**
     * @access protected
     * This is used by the static factory methods is class HTML.
     */
    function _init2 ($args) {
        if ($args && is_array($args[0]))
            $this->_attr = array_shift($args);
        elseif (count($args) > 1 && ! $args[0])
            array_shift($args);

        if (count($args) == 1 && is_array($args[0]))
            $args = $args[0];
        $this->_content = $args;
        return $this;
    }
        
    /** Add a "tooltip" to an element.
     *
     * @param $tooltip_text string The tooltip text.
     */
    function addTooltip ($tooltip_text) {
        $this->setAttr('title', $tooltip_text);

        // FIXME: this should be initialized from title by an onLoad() function.
        //        (though, that may not be possible.)
        $qtooltip = str_replace("'", "\\'", $tooltip_text);
        $this->setAttr('onmouseover',
                       sprintf('window.status="%s"; return true;',
                               addslashes($tooltip_text)));
        $this->setAttr('onmouseout', "window.status='';return true;");
    }

    function _emptyTag () {
        if (!$this->_tag)
            return '';
        elseif (($this->_properties & HTMLTAG_EMPTY) == 0)
            return $this->_startTag() . "</$this->_tag>";

        return substr($this->_startTag(), 0, -1) . " />";
    }

    function hasInlineContent () {
        return ($this->_properties & HTMLTAG_ACCEPTS_INLINE) != 0;
    }

    function isInlineElement () {
        return ($this->_properties & HTMLTAG_INLINE) != 0;
    }
};

function HTML ($tag /* , ... */) {
    $el = new HtmlElement($tag);
    if (func_num_args() > 1)
        $el->_init(func_get_args());
    return $el;
}

define('NBSP', "\xA0");         // iso-8859-x non-breaking space.

class HTML extends HtmlElement {
    function raw ($html_text) {
        return new RawXML($html_text);
    }

    function getTagProperties($tag) {
        $props = &$GLOBALS['HTML_TagProperties'];
        return isset($props[$tag]) ? $props[$tag] : 0;
    }
    
    function _setTagProperty($prop_flag, $tags) {
        $props = &$GLOBALS['HTML_TagProperties'];
        if (is_string($tags))
            $tags = preg_split('/\s+/', $tags);
        foreach ($tags as $tag) {
            if (isset($props[$tag]))
                $props[$tag] |= $prop_flag;
            else
                $props[$tag] = $prop_flag;
        }
    }

    //
    // Shell script to generate the following static methods:
    //
    // function mkfuncs () {
    //     for tag in "$@"
    //     do
    //         echo "    function $tag (/*...*/) {"
    //         echo "        \$el = new HtmlElement('$tag');"
    //         echo "        return \$el->_init2(func_get_args());"
    //         echo "    }"
    //     done
    // }
    // mkfuncs link style script noscript
    // mkfuncs a img br span
    // mkfuncs h1 h2 h3 h4 h5 h6
    // mkfuncs hr div p pre blockquote
    // mkfuncs em strong small
    // mkfuncs tt u sup sub
    // mkfuncs ul ol dl li dt dd
    // mkfuncs table caption thead tbody tfoot tr td th
    // mkfuncs form input
    function link (/*...*/) {
        $el = new HtmlElement('link');
        return $el->_init2(func_get_args());
    }
    function style (/*...*/) {
        $el = new HtmlElement('style');
        return $el->_init2(func_get_args());
    }
    function script (/*...*/) {
        $el = new HtmlElement('script');
        return $el->_init2(func_get_args());
    }
    function noscript (/*...*/) {
        $el = new HtmlElement('noscript');
        return $el->_init2(func_get_args());
    }
    function a (/*...*/) {
        $el = new HtmlElement('a');
        return $el->_init2(func_get_args());
    }
    function img (/*...*/) {
        $el = new HtmlElement('img');
        return $el->_init2(func_get_args());
    }
    function br (/*...*/) {
        $el = new HtmlElement('br');
        return $el->_init2(func_get_args());
    }
    function span (/*...*/) {
        $el = new HtmlElement('span');
        return $el->_init2(func_get_args());
    }
    function h1 (/*...*/) {
        $el = new HtmlElement('h1');
        return $el->_init2(func_get_args());
    }
    function h2 (/*...*/) {
        $el = new HtmlElement('h2');
        return $el->_init2(func_get_args());
    }
    function h3 (/*...*/) {
        $el = new HtmlElement('h3');
        return $el->_init2(func_get_args());
    }
    function h4 (/*...*/) {
        $el = new HtmlElement('h4');
        return $el->_init2(func_get_args());
    }
    function h5 (/*...*/) {
        $el = new HtmlElement('h5');
        return $el->_init2(func_get_args());
    }
    function h6 (/*...*/) {
        $el = new HtmlElement('h6');
        return $el->_init2(func_get_args());
    }
    function hr (/*...*/) {
        $el = new HtmlElement('hr');
        return $el->_init2(func_get_args());
    }
    function div (/*...*/) {
        $el = new HtmlElement('div');
        return $el->_init2(func_get_args());
    }
    function p (/*...*/) {
        $el = new HtmlElement('p');
        return $el->_init2(func_get_args());
    }
    function pre (/*...*/) {
        $el = new HtmlElement('pre');
        return $el->_init2(func_get_args());
    }
    function blockquote (/*...*/) {
        $el = new HtmlElement('blockquote');
        return $el->_init2(func_get_args());
    }
    function em (/*...*/) {
        $el = new HtmlElement('em');
        return $el->_init2(func_get_args());
    }
    function strong (/*...*/) {
        $el = new HtmlElement('strong');
        return $el->_init2(func_get_args());
    }
    function small (/*...*/) {
        $el = new HtmlElement('small');
        return $el->_init2(func_get_args());
    }
    function tt (/*...*/) {
        $el = new HtmlElement('tt');
        return $el->_init2(func_get_args());
    }
    function u (/*...*/) {
        $el = new HtmlElement('u');
        return $el->_init2(func_get_args());
    }
    function sup (/*...*/) {
        $el = new HtmlElement('sup');
        return $el->_init2(func_get_args());
    }
    function sub (/*...*/) {
        $el = new HtmlElement('sub');
        return $el->_init2(func_get_args());
    }
    function ul (/*...*/) {
        $el = new HtmlElement('ul');
        return $el->_init2(func_get_args());
    }
    function ol (/*...*/) {
        $el = new HtmlElement('ol');
        return $el->_init2(func_get_args());
    }
    function dl (/*...*/) {
        $el = new HtmlElement('dl');
        return $el->_init2(func_get_args());
    }
    function li (/*...*/) {
        $el = new HtmlElement('li');
        return $el->_init2(func_get_args());
    }
    function dt (/*...*/) {
        $el = new HtmlElement('dt');
        return $el->_init2(func_get_args());
    }
    function dd (/*...*/) {
        $el = new HtmlElement('dd');
        return $el->_init2(func_get_args());
    }
    function table (/*...*/) {
        $el = new HtmlElement('table');
        return $el->_init2(func_get_args());
    }
    function caption (/*...*/) {
        $el = new HtmlElement('caption');
        return $el->_init2(func_get_args());
    }
    function thead (/*...*/) {
        $el = new HtmlElement('thead');
        return $el->_init2(func_get_args());
    }
    function tbody (/*...*/) {
        $el = new HtmlElement('tbody');
        return $el->_init2(func_get_args());
    }
    function tfoot (/*...*/) {
        $el = new HtmlElement('tfoot');
        return $el->_init2(func_get_args());
    }
    function tr (/*...*/) {
        $el = new HtmlElement('tr');
        return $el->_init2(func_get_args());
    }
    function td (/*...*/) {
        $el = new HtmlElement('td');
        return $el->_init2(func_get_args());
    }
    function th (/*...*/) {
        $el = new HtmlElement('th');
        return $el->_init2(func_get_args());
    }
    function form (/*...*/) {
        $el = new HtmlElement('form');
        return $el->_init2(func_get_args());
    }
    function input (/*...*/) {
        $el = new HtmlElement('input');
        return $el->_init2(func_get_args());
    }
}

define('HTMLTAG_EMPTY', 1);
define('HTMLTAG_INLINE', 2);
define('HTMLTAG_ACCEPTS_INLINE', 4);


HTML::_setTagProperty(HTMLTAG_EMPTY,
                      'area base basefont br col frame hr img input isindex link meta param');
HTML::_setTagProperty(HTMLTAG_ACCEPTS_INLINE,
                      // %inline elements:
                      'b big i small tt ' // %fontstyle
                      . 's strike u ' // (deprecated)
                      . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
                      . 'a img object br script map q sub sup span bdo '//%special
                      . 'button input label select textarea ' //%formctl

                      // %block elements which contain inline content
                      . 'address h1 h2 h3 h4 h5 h6 p pre '
                      // %block elements which contain either block or inline content
                      . 'div fieldset '

                      // other with inline content
                      . 'caption dt label legend '
                      // other with either inline or block
                      . 'dd del ins li td th ');

HTML::_setTagProperty(HTMLTAG_INLINE,
                      // %inline elements:
                      'b big i small tt ' // %fontstyle
                      . 's strike u ' // (deprecated)
                      . 'abbr acronym cite code dfn em kbd samp strong var ' //%phrase
                      . 'a img object br script map q sub sup span bdo '//%special
                      . 'button input label select textarea ' //%formctl
                      );

      
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
