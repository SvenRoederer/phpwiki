<?php rcs_id('$Id: ErrorManager.php,v 1.29 2004-06-20 15:30:04 rurban Exp $');

require_once(dirname(__FILE__).'/HtmlElement.php');
if (isset($GLOBALS['ErrorManager'])) return;

define ('EM_FATAL_ERRORS',
	E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
define ('EM_WARNING_ERRORS',
	E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING);
define ('EM_NOTICE_ERRORS', E_NOTICE | E_USER_NOTICE);

/* It is recommended to leave assertions on. 
   You can simply comment the two lines below to leave them on.
   Only where absolute speed is necessary you might want to turn 
   them off.
*/
if (defined('DEBUG') and DEBUG)
    assert_options (ASSERT_ACTIVE, 1);
else
    assert_options (ASSERT_ACTIVE, 0);
assert_options (ASSERT_CALLBACK, 'wiki_assert_handler');

function wiki_assert_handler ($file, $line, $code) {
    ErrorManager_errorHandler( $code, sprintf("<br />%s:%s: %s: Assertion failed <br />", $file, $line, $code), $file, $line);
}

/**
 * A class which allows custom handling of PHP errors.
 *
 * This is a singleton class. There should only be one instance
 * of it --- you can access the one instance via $GLOBALS['ErrorManager'].
 *
 * FIXME: more docs.
 */ 
class ErrorManager 
{
    /**
     * Constructor.
     *
     * As this is a singleton class, you should never call this.
     * @access private
     */
    function ErrorManager() {
        $this->_handlers = array();
        $this->_fatal_handler = false;
        $this->_postpone_mask = 0;
        $this->_postponed_errors = array();

        set_error_handler('ErrorManager_errorHandler');
    }

    /**
     * Get mask indicating which errors are currently being postponed.
     * @access public
     * @return int The current postponed error mask.
     */
    function getPostponedErrorMask() {
        return $this->_postpone_mask;
    }

    /**
     * Set mask indicating which errors to postpone.
     *
     * The default value of the postpone mask is zero (no errors postponed.)
     *
     * When you set this mask, any queue errors which do not match the new
     * mask are reported.
     *
     * @access public
     * @param $newmask int The new value for the mask.
     */
    function setPostponedErrorMask($newmask) {
        $this->_postpone_mask = $newmask;
        if (function_exists('PrintXML'))
            PrintXML($this->_flush_errors($newmask));
        else
            echo($this->_flush_errors($newmask));

    }

    /**
     * Report any queued error messages.
     * @access public
     */
    function flushPostponedErrors() {
        if (function_exists('PrintXML'))
            PrintXML($this->_flush_errors());
        else
            echo $this->_flush_errors();
    }

    /**
     * Get postponed errors, formatted as HTML.
     *
     * This also flushes the postponed error queue.
     *
     * @return object HTML describing any queued errors (or false, if none). 
     */
    function getPostponedErrorsAsHTML() {
        $flushed = $this->_flush_errors();
        if (!$flushed)
            return false;
        if ($flushed->isEmpty())
            return false;
        $html = HTML::div(array('class' => 'errors'),
                          HTML::h4("PHP Warnings"));
        $html->pushContent($flushed);
        return $html;
    }
    
    /**
     * Push a custom error handler on the handler stack.
     *
     * Sometimes one is performing an operation where one expects
     * certain errors or warnings. In this case, one might not want
     * these errors reported in the normal manner. Installing a custom
     * error handler via this method allows one to intercept such
     * errors.
     *
     * An error handler installed via this method should be either a
     * function or an object method taking one argument: a PhpError
     * object.
     *
     * The error handler should return either:
     * <dl>
     * <dt> False <dd> If it has not handled the error. In this case,
     *                 error processing will proceed as if the handler
     *                 had never been called: the error will be passed
     *                 to the next handler in the stack, or the
     *                 default handler, if there are no more handlers
     *                 in the stack.
     *
     * <dt> True <dd> If the handler has handled the error. If the
     *                error was a non-fatal one, no further processing
     *                will be done. If it was a fatal error, the
     *                ErrorManager will still terminate the PHP
     *                process (see setFatalHandler.)
     *
     * <dt> A PhpError object <dd> The error is not considered
     *                             handled, and will be passed on to
     *                             the next handler(s) in the stack
     *                             (or the default handler). The
     *                             returned PhpError need not be the
     *                             same as the one passed to the
     *                             handler. This allows the handler to
     *                             "adjust" the error message.
     * </dl>
     * @access public
     * @param $handler WikiCallback  Handler to call.
     */
    function pushErrorHandler($handler) {
        array_unshift($this->_handlers, $handler);
    }

    /**
     * Pop an error handler off the handler stack.
     * @access public
     */
    function popErrorHandler() {
        return array_shift($this->_handlers);
    }

    /**
     * Set a termination handler.
     *
     * This handler will be called upon fatal errors. The handler
     * gets passed one argument: a PhpError object describing the
     * fatal error.
     *
     * @access public
     * @param $handler WikiCallback  Callback to call on fatal errors.
     */
    function setFatalHandler($handler) {
        $this->_fatal_handler = $handler;
    }

    /**
     * Handle an error.
     *
     * The error is passed through any registered error handlers, and
     * then either reported or postponed.
     *
     * @access public
     * @param $error object A PhpError object.
     */
    function handleError($error) {
        static $in_handler;

        if (!empty($in_handler)) {
            $msg = $error->_getDetail();
            $msg->unshiftContent(HTML::h2(fmt("%s: error while handling error:",
                                              "ErrorManager")));
            $msg->printXML();
            return;
        }
        $in_handler = true;

        foreach ($this->_handlers as $handler) {
            if (!$handler) continue;
            $result = $handler->call($error);
            if (!$result) {
                continue;       // Handler did not handle error.
            }
            elseif (is_object($result)) {
                // handler filtered the result. Still should pass to
                // the rest of the chain.
                if ($error->isFatal()) {
                    // Don't let handlers make fatal errors non-fatal.
                    $result->errno = $error->errno;
                }
                $error = $result;
            }
            else {
                // Handler handled error.
                if (!$error->isFatal()) {
                    $in_handler = false;
                    return;
                }
                break;
            }
        }

        // Error was either fatal, or was not handled by a handler.
        // Handle it ourself.
        if ($error->isFatal()) {
            $this->_die($error);
        }
        else if (($error->errno & error_reporting()) != 0) {
            if  (($error->errno & $this->_postpone_mask) != 0) {
                if ((function_exists('is_a') and is_a($error,'PhpErrorOnce'))
                    or (!function_exists('is_a') and 
                    (
                     // stdlib independent isa()
                     (strtolower(get_class($error)) == 'phperroronce')
                     or (is_subclass_of($error, 'PhpErrorOnce'))))) {
                    $error->removeDoublettes($this->_postponed_errors);
                    if ( $error->_count < 2 )
                        $this->_postponed_errors[] = $error;
                } else {
                    $this->_postponed_errors[] = $error;
                }
            }
            else {
                $error->printXML();
            }
        }
        $in_handler = false;
    }

    function warning($msg, $errno=E_USER_NOTICE) {
        $this->handleError(new PhpWikiError($errno, $msg));
    }
    
    /**
     * @access private
     */
    function _die($error) {
        $error->printXML();
        PrintXML($this->_flush_errors());
        if ($this->_fatal_handler)
            $this->_fatal_handler->call($error);
        exit -1;
    }

    /**
     * @access private
     */
    function _flush_errors($keep_mask = 0) {
        $errors = &$this->_postponed_errors;
        if (empty($errors)) return '';
        $flushed = HTML();
        for ($i=0; $i<count($errors); $i++) {
            $error =& $errors[$i];
            if (($error->errno & $keep_mask) != 0)
                continue;
            unset($errors[$i]);
            $flushed->pushContent($error);
        }
        return $flushed;
    }
}

/**
 * Global error handler for class ErrorManager.
 *
 * This is necessary since PHP's set_error_handler() does not allow
 * one to set an object method as a handler.
 * 
 * @access private
 */
function ErrorManager_errorHandler($errno, $errstr, $errfile, $errline) 
{
    if (!isset($GLOBALS['ErrorManager'])) {
      $GLOBALS['ErrorManager'] = new ErrorManager;
    }
	
    $error = new PhpErrorOnce($errno, $errstr, $errfile, $errline);
    $GLOBALS['ErrorManager']->handleError($error);
}


/**
 * A class representing a PHP error report.
 *
 * @see The PHP documentation for set_error_handler at
 *      http://php.net/manual/en/function.set-error-handler.php .
 */
class PhpError {
    /**
     * The PHP errno
     */
    var $errno;

    /**
     * The PHP error message.
     */
    var $errstr;

    /**
     * The source file where the error occurred.
     */
    var $errfile;

    /**
     * The line number (in $this->errfile) where the error occured.
     */
    var $errline;

    /**
     * Construct a new PhpError.
     * @param $errno   int
     * @param $errstr  string
     * @param $errfile string
     * @param $errline int
     */
    function PhpError($errno, $errstr, $errfile, $errline) {
        $this->errno   = $errno;
        $this->errstr  = $errstr;
        $this->errfile = $errfile;
        $this->errline = $errline;
    }

    /**
     * Determine whether this is a fatal error.
     * @return boolean True if this is a fatal error.
     */
    function isFatal() {
        return ($this->errno & (EM_WARNING_ERRORS|EM_NOTICE_ERRORS)) == 0;
    }

    /**
     * Determine whether this is a warning level error.
     * @return boolean
     */
    function isWarning() {
        return ($this->errno & EM_WARNING_ERRORS) != 0;
    }

    /**
     * Determine whether this is a notice level error.
     * @return boolean
     */
    function isNotice() {
        return ($this->errno & EM_NOTICE_ERRORS) != 0;
    }

    /**
     * Get a printable, HTML, message detailing this error.
     * @return object The detailed error message.
     */
    function _getDetail() {
        if ($this->isNotice())
            $what = 'Notice';
        else if ($this->isWarning())
            $what = 'Warning';
        else
            $what = 'Fatal';

	$dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR : substr(dirname(__FILE__),0,-4);
        if (substr(PHP_OS,0,3) == 'WIN') {
           $dir = str_replace('/','\\',$dir);
           $this->errfile = str_replace('/','\\',$this->errfile);
           $dir .= "\\";
        } else 
           $dir .= '/';
        $errfile = preg_replace('|^' . preg_quote($dir) . '|', '', $this->errfile);
        $lines = explode("\n", $this->errstr);

        $msg = sprintf("%s:%d: %s[%d]: %s",
                       $errfile, $this->errline,
                       $what, $this->errno,
                       array_shift($lines));
        
        $html = HTML::div(array('class' => 'error'), HTML::p($msg));
        
        if ($lines) {
            $list = HTML::ul();
            foreach ($lines as $line)
                $list->pushContent(HTML::li($line));
            $html->pushContent($list);
        }
        
        return $html;
    }

    /**
     * Print an HTMLified version of this error.
     * @see asXML()
     */
    function printXML() {
        PrintXML($this->_getDetail());
    }

    /**
     * Return an HTMLified version of this error.
     */
    function asXML() {
        return AsXML($this->_getDetail());
    }

    /**
     * Return a plain-text version of this error.
     */
    function asString() {
        return AsString($this->_getDetail());
    }
}

/**
 * A class representing a PhpWiki warning.
 *
 * This is essentially the same as a PhpError, except that the
 * error message is quieter: no source line, etc...
 */
class PhpWikiError extends PhpError {
    /**
     * Construct a new PhpError.
     * @param $errno   int
     * @param $errstr  string
     */
    function PhpWikiError($errno, $errstr) {
        $this->PhpError($errno, $errstr, '?', '?');
    }

    function _getDetail() {
        if ($this->isNotice())
            $what = 'Notice';
        else if ($this->isWarning())
            $what = 'Warning';
        else
            $what = 'Fatal';

        return HTML::div(array('class' => 'error'), HTML::p("$what: $this->errstr"));
    }
}

/**
 * A class representing a Php warning, printed only the first time.
 *
 * Similar to PhpError, except only the first same error message is printed, 
 * with number of occurences.
 */
class PhpErrorOnce extends PhpError {

    function PhpErrorOnce($errno, $errstr, $errfile, $errline) {
        $this->_count = 1;
        $this->PhpError($errno, $errstr, $errfile, $errline);
    }

    function _sameError($error) {
        if (!$error) return false;
        return ($this->errno == $error->errno and
                $this->errfile == $error->errfile and
                $this->errline == $error->errline);
    }

    // count similar handlers, increase _count and remove the rest
    function removeDoublettes(&$errors) {
        for ($i=0; $i < count($errors); $i++) {
            if (!isset($errors[$i])) continue;
            if ($this->_sameError($errors[$i])) {
                $errors[$i]->_count++;
                $this->_count++;
                if ($i) unset($errors[$i]);
            }
        }
        return $this->_count;
    }
    
    function _getDetail($count=0) {
    	if (!$count) $count = $this->_count;
        if ($this->isNotice())
            $what = 'Notice';
        else if ($this->isWarning())
            $what = 'Warning';
        else
            $what = 'Fatal';
	$dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR : substr(dirname(__FILE__),0,-4);
        if (substr(PHP_OS,0,3) == 'WIN') {
           $dir = str_replace('/','\\',$dir);
           $this->errfile = str_replace('/','\\',$this->errfile);
           $dir .= "\\";
        } else 
           $dir .= '/';
        $errfile = preg_replace('|^' . preg_quote($dir) . '|', '', $this->errfile);
        $lines = explode("\n", $this->errstr);
        $msg = sprintf("%s:%d: %s[%d]: %s %s",
                       $errfile, $this->errline,
                       $what, $this->errno,
                       array_shift($lines),
                       $count > 1 ? sprintf(" (...repeated %d times)",$count) : ""
                       );
                       
        $html = HTML::div(array('class' => 'error'), HTML::p($msg));
        if ($lines) {
            $list = HTML::ul();
            foreach ($lines as $line)
                $list->pushContent(HTML::li($line));
            $html->pushContent($list);
        }
        
        return $html;
    }
}

if (!isset($GLOBALS['ErrorManager'])) {
    $GLOBALS['ErrorManager'] = new ErrorManager;
}

// $Log: not supported by cvs2svn $
// Revision 1.28  2004/06/16 11:51:04  rurban
// fixed typo: undefined object #235
//
// Revision 1.27  2004/06/13 09:38:20  rurban
// isa() workaround, if stdlib.php is not loaded
//
// Revision 1.26  2004/06/02 18:01:45  rurban
// init global FileFinder to add proper include paths at startup
//   adds PHPWIKI_DIR if started from another dir, lib/pear also
// fix slashify for Windows
// fix USER_AUTH_POLICY=old, use only USER_AUTH_ORDER methods (besides HttpAuth)
//
// Revision 1.25  2004/06/02 10:18:36  rurban
// assert only if DEBUG is non-false
//
// Revision 1.24  2004/05/27 17:49:05  rurban
// renamed DB_Session to DbSession (in CVS also)
// added WikiDB->getParam and WikiDB->getAuthParam method to get rid of globals
// remove leading slash in error message
// added force_unlock parameter to File_Passwd (no return on stale locks)
// fixed adodb session AffectedRows
// added FileFinder helpers to unify local filenames and DATA_PATH names
// editpage.php: new edit toolbar javascript on ENABLE_EDIT_TOOLBAR
//
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