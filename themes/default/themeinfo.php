<?php

rcs_id('$Id: themeinfo.php,v 1.8 2002-01-05 06:17:02 carstenklapp Exp $');

/**
 * This is really just a template to copy for designing new
 * themes. The real "default" theme is built into index.php, none of
 * the variables here point to any actual files.
 * */

// To activate this theme, specify this setting in index.php:
//$theme="default";
// To deactivate themes, comment out all the $theme=lines in index.php.

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.
$CSS_DEFAULT = "default";

$CSS_URLS = array_merge($CSS_URLS,
                        array("$CSS_DEFAULT" => "themes/$theme/${CSS_DEFAULT}.css"));

// Logo image appears on every page and links to the HomePage.
$logo = "themes/$theme/wikibase.png";

// Signature image which is shown after saving an edited page.  If
// this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the "Thank
// you for editing..." screen will be omitted.
$SignatureImg = "themes/$theme/signature.png";

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
$templates = array(
                   'BROWSE'   => "themes/$theme/templates/browse.html",
                   'EDITPAGE' => "themes/$theme/templates/editpage.html",
                   'MESSAGE'  => "themes/$theme/templates/message.html"
                   );

// If this theme defines any custom link icons, they will completely
// override any link icon settings defined in index.php.
$URL_LINK_ICONS = array(
                        'http'      => "themes/$theme/icons/http.png",
                        'https'     => "themes/$theme/icons/https.png",
                        'ftp'       => "themes/$theme/icons/ftp.png",
                        'mailto'    => "themes/$theme/icons/mailto.png",
                        'interwiki' => "themes/$theme/icons/interwiki.png",
                        '*'         => "themes/$theme/icons/zapg.png"
                        );

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
