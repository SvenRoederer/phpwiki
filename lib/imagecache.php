<?php // -*-php-*-
// +---------------------------------------------------------------------+
// | imagecache.php                                                      |
// +---------------------------------------------------------------------+
// | Copyright (C) 2002 Johannes Gro�e (Johannes Gro&szlig;e)            |
// | You may copy this code freely under the conditions of the GPL       |
// +---------------------------------------------------------------------+

/**
 * Gets an image from the cache and prints it to the browser.
 * This file belongs to WikiPluginCached.
 * @author  Johannes Gro�e
 * @version 0.8
 */

include "lib/config.php";
include "lib/stdlib.php";
//include "lib/logger.php";
require_once('lib/Request.php');
require_once("lib/WikiUser.php");
require_once('lib/WikiDB.php');

require_once "lib/WikiPluginCached.php";

// -----------------------------------------------------------------------

// FIXME: do I need this? What the hell does it? 

function deduce_pagename ($request) {
    if ($request->getArg('pagename'))
        return $request->getArg('pagename');

    if (USE_PATH_INFO) {
        $pathinfo = $request->get('PATH_INFO');
        if (ereg('^' . PATH_INFO_PREFIX . '(..*)$', $pathinfo, $m))
            return $m[1];
    }

    $query_string = $request->get('QUERY_STRING');
    if (preg_match('/^[^&=]+$/', $query_string))
        return urldecode($query_string);
    
    return gettext("HomePage");
}

/**
 * Initializes PhpWiki and calls the plugin specified in the url to
 * produce an image. Furthermore, allow the usage of Apache's
 * ErrorDocument mechanism in order to make this file only called when 
 * image could not be found in the cache.
 * (see PHPWIKI-CACHE.README for further information).
 */
function mainImageCache() {
    $request = new Request;   
    //$request->setArg('pagename', deduce_pagename($request));
    //$pagename = $request->getArg('pagename');

    // assume that every user may use the cache    
    global $user; // FIXME: necessary ?
    $user = new WikiUser($request, 'ANON_OK'); 

    $dbi = WikiDB::open($GLOBALS['DBParams']);
    
    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files and setpref though.
    // They will also screw up my images... But I think 
    // we should keep them.
    global $ErrorManager;
    $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);

    $id = $request->getArg('id');
    $args = $request->getArg('args');
    $request->setArg('action', 'imagecache');

    if ($id) {
        // this indicates a direct call (script wasn't called as
        // 404 ErrorDocument)
    } else {
        // deduce image id or image args (plugincall) from
        // refering URL

        $uri = $request->get('REDIRECT_URL');
        $query = $request->get('REDIRECT_QUERY_STRING');
        $uri .= $query ? '?'.$query : '';        

        if (!$uri) {
            $uri = $request->get('REQUEST_URI');
        }
        if (!uri) {
            WikiPluginCached::printError( 'png', 
                'Could not deduce image identifier or creation'
                . ' parameters. (Neither REQUEST nor REDIRECT'
                . ' obtained.)' ); 
            return;
        }    
        $cacheparams = $GLOBALS['CacheParams'];
        if (!preg_match(':^(.*/)?'.$cacheparams['filename_prefix'].'([^\?/]+)\.img(\?args=([^\?&]*))?$:', $uri, $matches)) {
            WikiPluginCached::printError('png', "I do not understand this URL: $uri");
            return;
        }        
        
        $request->setArg('id',$matches[2]);
        if ($matches[4]) {
           $request->setArg('args',rawurldecode($matches[4]));
        }
        $request->setStatus(200); // No, we do _not_ have an Error 404 :->
    } 

    WikiPluginCached::fetchImageFromCache($dbi,$request,'png');
}


mainImageCache();


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
