<?php // -*-php-*-
rcs_id('$Id: Transclude.php,v 1.1 2002-09-17 02:26:20 dairiki Exp $');
/**
 * Transclude:  Include an external web page within the body of a wiki page.
 * 
 * Usage:   
 *  <?plugin Transclude src=http://www.internet-technology.de/fourwins_de.htm ?>
 *
 * @author Geoffrey T. Dairiki
 *
 * @see http://www.cs.tut.fi/~jkorpela/html/iframe.html
 *
 * KNOWN ISSUES
 *  Will only work if the browser supports <iframe>s (which is a recent,
 *  but standard tag)
 *
 *  The auto-vertical resize javascript code only works if the transcluded
 *  page comes from the PhpWiki server.  Otherwise (due to "tainting"
 *  security checks in JavaScript) I can't figure out how to deduce the
 *  height of the transcluded page via JavaScript... :-/
 */

class WikiPlugin_Transclude
extends WikiPlugin
{
    function getName() {
        return _("Transclude");
    }

    function getDescription() {
      return _("Include an external web page within the body of a wiki page.");
    }

    function getDefaultArguments() {
        return array( 'src'	=> false, // the src url to include
                      'height'	=> 450 // height of the iframe
                    );
    }

    function run($dbi, $argstr, $request) {
    	global $Theme;

        $args = ($this->getArgs($argstr, $request));
        extract($args);

        if (!$src) {
            return $this->error(fmt("%s parameter missing", "'src'"));
        }
        // FIXME: Better recursion detection.
        // FIXME: Currently this doesnt work at all.
        if ($src == $request->getURLtoSelf() ) {
            return $this->error(fmt("recursive inclusion of url %s", $src));
        }

        if (! IsSafeURL($src)) {
            return $this->error(_("Bad url in src: remove all of <, >, \""));
        }

        $params = array('src' => $src,
                        'width' => "100%",
                        'height' => $height,
                        'marginwidth' => 0,
                        'marginheight' => 0,
                        'scrolling' => 'no',
                        'class' => 'transclude',
                        "onload" => "adjust_iframe_height(this);");

        $noframe_msg[]
            = _("Cannot transclude document since your browser does not support <iframe>s.)");
        $noframe_msg[] = '  ';
        $noframe_msg[] = fmt("Click %s to view the transcluded page",
                             HTML::a(array('href' => $src), _("here")));
        
        $noframe_msg = HTML::div(array('class' => 'transclusion'),
                                 HTML::p(array(), $noframe_msg));

        $iframe = HTML::div(HTML::iframe($params, $noframe_msg));

        /* This doesn't work very well...  maybe because CSS screws up NS4 anyway...
        $iframe = new HtmlElement('ilayer', array('src' => $src), $iframe);
        */
        
        return HTML(HTML::p(array('class' => 'transclusion-title'),
                            fmt("Transcluded from %s", LinkURL($src))),
                    $this->_js(), $iframe);
    }

    /**
     * Produce our javascript.
     *
     * This is used to resize the iframe to fit the content.
     * Currently it only works if the transcluded document comes
     * from the same server as the wiki server.
     *
     * @access private
     */
    function _js() {
        static $seen = false;

        if ($seen)
            return '';
        $seen = true;
        
        $script = '
          function adjust_iframe_height(frame) {
            var content = frame.contentDocument;
            try {
	      frame.height = content.height + 2 * frame.marginHeight;
            }
            catch (e) {
              // Can not get content.height unless transcluded doc
              // is from the same server...
              return;
            }
            frame.scrolling = "no"; // This does nothing in Galeon
          }';

        return  HTML::script(array('language' => 'JavaScript',
                                   'type'     => 'text/javascript'),
                             new RawXml("<!-- //\n$script\n// -->"));
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
