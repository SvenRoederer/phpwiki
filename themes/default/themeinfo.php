<?php

rcs_id('$Id: themeinfo.php,v 1.12 2002-01-17 05:46:19 carstenklapp Exp $');

/**
 * You can use this as a template to copy for designing new
 * themes.
 */

// To activate this theme, specify this setting in index.php:
$theme="default";
// To deactivate themes, comment out all the $theme=lines in index.php.

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.
/*
$CSS_DEFAULT = "PhpWiki";

$CSS_URLS = array_merge($CSS_URLS,
                        array("$CSS_DEFAULT" => "themes/$theme/${CSS_DEFAULT}.css"));
*/

// Logo image appears on every page and links to the HomePage.
$logo = "themes/$theme/images/logo.png";

// RSS logo icon (path relative to index.php)
// If this is left blank (or unset), the default "images/rss.png"
// will be used.
$rssicon = "themes/$theme/images/RSS.png";

// Signature image which is shown after saving an edited page.  If
// this is left blank, any signature defined in index.php will be
// used. If it is not defined by index.php or in here then the "Thank
// you for editing..." screen will be omitted.
$SignatureImg = "themes/$theme/images/signature.png";

// This defines separators used in RecentChanges and RecentEdits lists.
// If undefined, defaults to '' (nothing) and '...' (three periods).
//define("RC_SEPARATOR_A", ' . . . ');
//define("RC_SEPARATOR_B", ' --');

// Controls whether the '?' appears before or after UnknownWikiWords.
// The PhpWiki default is for the '?' to appear before.
//define('WIKIMARK_AFTER', true);

// If this theme defines any templates, they will completely override
// whatever templates have been defined in index.php.
/*
$templates = array(
                   'BROWSE'   => "themes/$theme/templates/browse.html",
                   'EDITPAGE' => "themes/$theme/templates/editpage.html",
                   'MESSAGE'  => "themes/$theme/templates/message.html"
                   );
*/

// If this theme defines any custom link icons, they will completely
// override any link icon settings defined in index.php.
/*
$URL_LINK_ICONS = array(
                        'http'      => "themes/$theme/images/http.png",
                        'https'     => "themes/$theme/images/https.png",
                        'ftp'       => "themes/$theme/images/ftp.png",
                        'mailto'    => "themes/$theme/images/mailto.png",
                        'interwiki' => "themes/$theme/images/interwiki.png",
                        '*'         => "themes/$theme/images/url.png"
                        );
*/

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
