<?php
/* lib/prepend.php
 *
 * Things which must be done and defined before anything else.
 */

// see lib/stdlib.php: phpwiki_version()
define('PHPWIKI_VERSION', '1.5.1');

/**
 * Returns true if current php version is at mimimum a.b.c
 * Called: check_php_version(5,3)
 */
function check_php_version($a = '0', $b = '0', $c = '0')
{
    static $PHP_VERSION;
    if (!isset($PHP_VERSION))
        $PHP_VERSION = substr(str_pad(preg_replace('/\D/', '', PHP_VERSION), 3, '0'), 0, 3);
    return ($PHP_VERSION >= ($a . $b . $c));
}

/** PHP5 deprecated old-style globals if !(bool)ini_get('register_long_arrays').
 *  See Bug #1180115
 * We want to work with those old ones instead of the new superglobals,
 * for easier coding.
 */
/*
foreach (array('SERVER','REQUEST','GET','POST','SESSION','ENV','COOKIE') as $k) {
    if (!isset($GLOBALS['HTTP_'.$k.'_VARS']) and isset($GLOBALS['_'.$k])) {
        $GLOBALS['HTTP_'.$k.'_VARS'] =& $GLOBALS['_'.$k];
    }
}
*/
// A new php-5.1.x feature: Turn off php-5.1.x auto_globals_jit = On, or use this mess below.
if (empty($GLOBALS['HTTP_SERVER_VARS'])) {
    $GLOBALS['HTTP_SERVER_VARS'] =& $_SERVER;
    $GLOBALS['HTTP_ENV_VARS'] =& $_ENV;
    $GLOBALS['HTTP_GET_VARS'] =& $_GET;
    $GLOBALS['HTTP_POST_VARS'] =& $_POST;
    $GLOBALS['HTTP_SESSION_VARS'] =& $_SESSION;
    $GLOBALS['HTTP_COOKIE_VARS'] =& $_COOKIE;
    $GLOBALS['HTTP_REQUEST_VARS'] =& $_REQUEST;
}
unset($k);
// catch connection failures on upgrade
if (isset($GLOBALS['HTTP_GET_VARS']['action'])
    and $GLOBALS['HTTP_GET_VARS']['action'] == 'upgrade'
)
    define('ADODB_ERROR_HANDLER_TYPE', E_USER_WARNING);

// If your php was compiled with --enable-trans-sid it tries to
// add a PHPSESSID query argument to all URL strings when cookie
// support isn't detected in the client browser.  For reasons
// which aren't entirely clear (PHP bug) this screws up the URLs
// generated by PhpWiki.  Therefore, transparent session ids
// should be disabled.  This next line does that.
//
// (At the present time, you will not be able to log-in to PhpWiki,
// unless your browser supports cookies.)
@ini_set('session.use_trans_sid', 0);

if (defined('DEBUG') and (DEBUG & 8) and extension_loaded("xdebug")) {
    xdebug_start_trace("trace"); // on Dbgp protocol add 2
    xdebug_enable();
}
if (defined('DEBUG') and (DEBUG & 32) and extension_loaded("apd")) {
    apd_set_pprof_trace();
    /*    FUNCTION_TRACE      1
        ARGS_TRACE          2
        ASSIGNMENT_TRACE    4
        STATEMENT_TRACE     8
        MEMORY_TRACE        16
        TIMING_TRACE        32
        SUMMARY_TRACE       64 */
    //apd_set_session_trace(99);
}

// Used for debugging purposes
class DebugTimer
{
    function DebugTimer()
    {
        $this->_start = $this->microtime();
        $this->_times = posix_times();
    }

    /**
     * @param  string $which One of 'real', 'utime', 'stime', 'cutime', 'sutime'
     * @param bool $now
     * @return float  Seconds.
     */
    function getTime($which = 'real', $now = false)
    {
        if ($which == 'real')
            return $this->microtime() - $this->_start;

        if (isset($this->_times)) {
            if (!$now) $now = posix_times();
            $ticks = $now[$which] - $this->_times[$which];
            return $ticks / $this->_CLK_TCK();
        }

        return 0.0; // Not available.
    }

    function getStats()
    {
        $now = posix_times();
        return sprintf("real: %.3f, user: %.3f, sys: %.3f",
            $this->getTime('real'),
            $this->getTime('utime', $now),
            $this->getTime('stime', $now));
    }

    function _CLK_TCK()
    {
        // FIXME: this is clearly not always right.
        // But how to figure out the right value?
        return 100.0;
    }

    function microtime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}

$RUNTIMER = new DebugTimer;
require_once(dirname(__FILE__) . '/ErrorManager.php');
require_once(dirname(__FILE__) . '/WikiCallback.php');

// FIXME: deprecated
function ExitWiki($errormsg = false)
{
    global $request;
    static $in_exit = 0;

    if (is_object($request) and method_exists($request, "finish"))
        $request->finish($errormsg); // NORETURN

    if ($in_exit)
        exit;

    $in_exit = true;

    global $ErrorManager;
    $ErrorManager->flushPostponedErrors();

    if (!empty($errormsg)) {
        PrintXML(HTML::br(), $errormsg);
        print "\n</body></html>";
    }
    exit;
}

if (!defined('DEBUG') or (defined('DEBUG') and DEBUG > 2)) {
    $ErrorManager->setPostponedErrorMask(E_ALL); // ignore all errors
    $ErrorManager->setFatalHandler(new WikiFunctionCb('ExitWiki'));
} else {
    $ErrorManager->setPostponedErrorMask(E_USER_NOTICE | E_NOTICE);
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
