<?php rcs_id('$Id: FileFinder.php,v 1.8 2002-08-27 21:51:31 rurban Exp $');

// FIXME: make this work with non-unix (e.g. DOS) filenames.

/**
 * A class for finding files.
 * 
 * This should really provided by pear. We don't want really to mess around 
 * with all the lousy systems. (WindowsNT, Win95, Mac, VMS, ...)
 * But pear has only System and File, which do nothing.
 * Anyway, in good PHP style we ignore the rest of the world and try to behave 
 * as on unix only. That means we use / as pathsep in all our constants.
 */
class FileFinder
{
    var $_pathsep, $_path;

    /**
     * Constructor.
     *
     * @param $path array A list of directories in which to search for files.
     */
    function FileFinder ($path = false) {
        $this->_pathsep = $this->_get_syspath_separator();
        if ($path === false)
            $path = $this->_get_include_path();
        $this->_path = $path;
    }

    /**
     * Find file.
     *
     * @param $file string File to search for.
     * @return string The filename (including path), if found, otherwise false.
     */
    function findFile ($file, $missing_okay = false) {
        if ($this->_is_abs($file)) {
            if (file_exists($file))
                return $file;
        }
        elseif ( ($dir = $this->_search_path($file)) ) {
            return $dir . $this->_use_path_separator($dir) . $file;
        }

        return $missing_okay ? false : $this->_not_found($file);
    }

    /**
     * Try to include file.
     *
     * If file is found in the path, then the files directory is added
     * to PHP's include_path (if it's not already there.) Then the
     * file is include_once()'d.
     *
     * @param $file string File to include.
     * @return bool True if file was successfully included.
     */
    function includeOnce ($file) {
        if ( ($ret = @include_once($file)) )
            return $ret;

        if (!$this->_is_abs($file)) {
            if ( ($dir = $this->_search_path($file)) && is_file("$dir/$file")) {
                $this->_append_to_include_path($dir);
                return include_once($file);
            }
        }

        return $this->_not_found($file);
    }

    /**
     * The system-dependent path-separator character. 
     * UNIX:    /
     * Windows: \
     * Mac:     :
     *
     * @access private
     * @return string path_separator.
     */
    function _get_syspath_separator () {
        if (isWindows()) return '\\';  // anyway: we support only WinNT and use /
        elseif (isMac()) return ':';   // MacOsX is /
        // VMS or LispM is really weird, we ignore it.
        else return '/';
    }

    /**
     * The path-separator character of the given path. 
     * Windows accepts / also, but gets confused with mixed path_separators,
     * e.g "C:\Apache\phpwiki/locale/button"
     * > dir C:\Apache\phpwiki/locale/button => 
     *       Parameterformat nicht korrekt - "locale"
     * So if there's any \\ in the path, either fix them to / (not in Win95!) 
     * or use \\ for ours.
     *
     * @access private
     * @return string path_separator.
     */
    function _use_path_separator ($path) {
        if (isWindows()) {
            return (strstr('\\',$path)) ? '\\' : '/';
        } else {
            return $this->_get_syspath_separator();
        }
    }

    /**
     * Determine if path is absolute.
     *
     * @access private
     * @param $path string Path.
     * @return bool True if path is absolute. 
     */
    function _is_abs($path) {
        if (ereg('^/', $path)) return true;
        elseif (isWindows() and (eregi('^[a-z]:[/\\]', $path))) return true;
        else return false;
    }

    /**
     * Report a "file not found" error.
     *
     * @access private
     * @param $file string Name of missing file.
     * @return bool false.
     */
    function _not_found($file) {
        trigger_error(sprintf(_("%s: file not found"),$file), E_USER_ERROR);
        return false;
    }


    /**
     * Search our path for a file.
     *
     * @access private
     * @param $file string File to find.
     * @return string Directory which contains $file, or false.
     */
    function _search_path ($file) {
        foreach ($this->_path as $dir) {
            if (file_exists($dir . $this->_use_path_separator($dir) . $file))
                return $dir;
        }
        return false;
    }

    /**
     * The system-dependent path-separator character. On UNIX systems,
     * this character is ':'; on Win32 systems it is ';'.
     * Fixme:
     * On Mac it cannot be : because this is the seperator there!
     *
     * @access private
     * @return string path_separator.
     */
    function _get_ini_separator () {
        return isWindows() ? ';' : ':';
        // return preg_match('/^Windows/', php_uname()) 
    }

    /**
     * Get the value of PHP's include_path.
     *
     * @access private
     * @return array Include path.
     */
    function _get_include_path() {
        $path = ini_get('include_path');
        if (empty($path))
            $path = '.';
        return explode($this->_get_ini_separator(), $path);
    }

    /**
     * Add a directory to the end of PHP's include_path.
     *
     * The directory is appended only if it is not already listed in
     * the include_path.
     *
     * @access private
     * @param $dir string Directory to add.
     */
    function _append_to_include_path ($dir) {
        $path = $this->_get_include_path();
        if (!in_array($dir, $path)) {
            $path[] = $dir;
            //ini_set('include_path', implode(':', $path));
        }
        /*
         * Some (buggy) PHP's (notable SourceForge's PHP 4.0.6)
         * sometimes don't seem to heed their include_path.
         * I.e. sometimes a file is not found even though it seems to
         * be in the current include_path. A simple
         * ini_set('include_path', ini_get('include_path')) seems to
         * be enough to fix the problem
         *
         * This following line should be in the above if-block, but we
         * put it here, as it seems to work-around the bug.
         */
        ini_set('include_path', implode($this->_get_ini_separator(), $path));
    }

    // Return all the possible shortened locale specifiers for the given locale.
    // Most specific first.
    // de_DE.iso8859-1@euro => de_DE.iso8859-1, de_DE, de
    // This code might needed somewhere else also.
    function locale_versions ($lang) {
        // Try less specific versions of the locale
        $langs[] = $lang;
        foreach (array('@', '.', '_') as $sep) {
            if ( ($tail = strchr($lang, $sep)) )
                $langs[] = substr($lang, 0, -strlen($tail));
        }
    }

    /**
     * Try to figure out the appropriate value for $LANG.
     *
     *@access private
     *@return string The value of $LANG.
     */
    function _get_lang() {
        if (!empty($GLOBALS['LANG']))
            return $GLOBALS['LANG'];

        foreach (array('LC_ALL', 'LC_MESSAGES', 'LC_RESPONSES', 'LANG') as $var) {
            $lang = getenv($var);
            if (!empty($lang))
                return $lang;
        }

        return "C";
    }
}

/**
 * A class for finding PEAR code.
 *
 * This is a subclass of FileFinder which searches a standard list of
 * directories where PEAR code is likely to be installed.
 *
 * Example usage:
 *
 * <pre>
 *   $pearFinder = new PearFileFinder;
 *   $pearFinder->includeOnce('DB.php');
 * </pre>
 *
 * The above code will look for 'DB.php', if found, the directory in
 * which it was found will be added to PHP's include_path, and the
 * file will be included. (If the file is not found, and E_USER_ERROR
 * will be thrown.)
 */
class PearFileFinder
    extends FileFinder
{
    /**
     * Constructor.
     *
     * @param $path array Where to look for PEAR library code.
     * A good set of defaults is provided, so you can probably leave
     * this parameter blank.
     */
    function PearFileFinder ($path = array()) {
        $this->FileFinder(array_merge(
                          $path,
                          array('/usr/share/php4',
                                '/usr/share/php',
                                '/usr/lib/php4',
                                '/usr/lib/php',
                                '/usr/local/share/php4',
                                '/usr/local/share/php',
                                '/usr/local/lib/php4',
                                '/usr/local/lib/php',
                                '/System/Library/PHP',
                                '/Apache/pear'        // Windows
                                )));
    }
}

/**
 * Find PhpWiki localized files.
 *
 * This is a subclass of FileFinder which searches PHP's include_path
 * for files. It looks first for "locale/$LANG/$file", then for
 * "$file".
 *
 * If $LANG is something like "de_DE.iso8859-1@euro", this class will
 * also search under various less specific variations like
 * "de_DE.iso8859-1", "de_DE" and "de".
 */
class LocalizedFileFinder
    extends FileFinder
{
    /**
     * Constructor.
     */
    function LocalizedFileFinder () {
        $include_path = $this->_get_include_path();
        $path = array();

        $lang = $this->_get_lang();
        assert(!empty($lang));

        if ($locales = $this->locale_versions($lang))
          foreach ($locales as $lang) {
            if ($lang == 'C') $lang='en';
            foreach ($include_path as $dir) {
                $path[] = "$dir/locale/$lang";
            }
          }
        $this->FileFinder(array_merge($path, $include_path));
    }
}

/**
 * Find PhpWiki localized theme buttons.
 *
 * This is a subclass of FileFinder which searches PHP's include_path
 * for files. It looks first for "buttons/$LANG/$file", then for
 * "$file".
 *
 * If $LANG is something like "de_DE.iso8859-1@euro", this class will
 * also search under various less specific variations like
 * "de_DE.iso8859-1", "de_DE" and "de".
 */
class LocalizedButtonFinder
    extends FileFinder
{
    /**
     * Constructor.
     */
    function LocalizedButtonFinder () {
        global $Theme;
        $include_path = $this->_get_include_path();
        $path = array();

        $lang = $this->_get_lang();
        assert(!empty($lang));
        assert(!empty($Theme));

        $langs = $this->locale_versions($lang);

        foreach ($langs as $lang) {
            if ($lang == 'C') $lang='en';
            foreach ($include_path as $dir) {
                $path[] = $Theme->file("buttons/$lang");
            }
        }

        $this->FileFinder(array_merge($path, $include_path));
    }

}

function isWindows() {
    //return preg_match('/^Windows/', php_uname());
    return (substr(PHP_OS,0,3) == 'WIN');
}

// So far not supported. This has really ugly pathname semantics
// :path is relative, Desktop:path (I think) is absolute. Please fix this someone
function isMac() {
    return (substr(PHP_OS,0,3) == 'MAC'); // not tested!
}

// probably not needed, same behaviour as on unix.
function isCygwin() {
    return (substr(PHP_OS,0,6) == 'CYGWIN');
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
