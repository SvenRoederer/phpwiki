<?php // -*-php-*-
rcs_id('$Id: PearDB_pgsql.php,v 1.12 2004-11-26 18:39:02 rurban Exp $');

require_once('lib/ErrorManager.php');
require_once('lib/WikiDB/backend/PearDB.php');

class WikiDB_backend_PearDB_pgsql
extends WikiDB_backend_PearDB
{
    function WikiDB_backend_PearDB_pgsql($dbparams) {
        // The pgsql handler of (at least my version of) the PEAR::DB
        // library generates three warnings when a database is opened:
        //
        //     Undefined index: options
        //     Undefined index: tty
        //     Undefined index: port
        //
        // This stuff is all just to catch and ignore these warnings,
        // so that they don't get reported to the user.  (They are
        // not consequential.)  

        global $ErrorManager;
        $ErrorManager->pushErrorHandler(new WikiMethodCb($this,'_pgsql_open_error'));
        $this->WikiDB_backend_PearDB($dbparams);
        $ErrorManager->popErrorHandler();
    }

    function _pgsql_open_error($error) {
        if (preg_match('/^Undefined\s+index:\s+(options|tty|port)/',
                       $error->errstr))
            return true;        // Ignore error
        return false;
    }
            
    /**
     * Pack tables.
     */
    function optimize() {
        foreach ($this->_table_names as $table) {
            $this->_dbh->query("VACUUM ANALYZE $table");
        }
        return 1;
    }

    /**
     * Lock all tables we might use.
     */
    function _lock_tables($write_lock = true) {
        $this->_dbh->query("BEGIN WORK");
    }

    /**
     * Unlock all tables.
     */
    function _unlock_tables() {
        $this->_dbh->query("COMMIT WORK");
    }

    /**
     * Serialize data
     */
    function _serialize($data) {
        if (empty($data))
            return '';
        assert(is_array($data));
        return base64_encode(serialize($data));
    }

    /**
     * Unserialize data
     */
    function _unserialize($data) {
        if (empty($data))
            return array();
        // Base64 encoded data does not contain colons.
        //  (only alphanumerics and '+' and '/'.)
        if (substr($data,0,2) == 'a:')
            return unserialize($data);
        return unserialize(base64_decode($data));
    }

};

class WikiDB_backend_PearDB_pgsql_search
extends WikiDB_backend_PearDB_search
{
    function _pagename_match_clause($node) { 
        $method = $node->op;
        $word = $this->$method($node->word);
        return $this->_case_exact 
            ? "pagename LIKE '$word'"
            : "pagename ILIKE '$word'";
    }
    function _fulltext_match_clause($node) { 
        $method = $node->op;
        $word = $this->$method($node->word);
        return $this->_case_exact 
            ? "pagename LIKE '$word' OR content LIKE '$word'"
            : "pagename ILIKE '$word' OR content ILIKE '$word'";
    }
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
