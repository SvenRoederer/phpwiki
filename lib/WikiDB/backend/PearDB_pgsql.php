<?php // -*-php-*-
rcs_id('$Id: PearDB_pgsql.php,v 1.18 2005-09-28 19:26:05 rurban Exp $');

require_once('lib/ErrorManager.php');
require_once('lib/WikiDB/backend/PearDB.php');

if (!defined("USE_BYTEA")) // see schemas/psql-initialize.sql
    define("USE_BYTEA", true);
    //define("USE_BYTEA", false);
/*
 * Since 1.3.12 changed to use Foreign Keys and ON DELETE CASCADE
 */

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

    function _quote($s) {
        if (USE_BYTEA)
            return pg_escape_bytea($s);
	if (function_exists('pg_escape_string'))
	    return pg_escape_string($s);
	else
	    return base64_encode($s);
    }

    function _unquote($s) {
        if (USE_BYTEA)
            return pg_unescape_bytea($s);
	if (function_exists('pg_escape_string'))
	    return $s;
	else
	    return base64_decode($s);
    }

    // Until the binary escape problems on pear pgsql are solved */
    function get_cached_html($pagename) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        $data = $dbh->GetOne(sprintf("SELECT cached_html FROM $page_tbl WHERE pagename='%s'",
                                     $dbh->escapeSimple($pagename)));
        if ($data) return $this->_unquote($data);
        else return '';
    }

    function set_cached_html($pagename, $data) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        if (USE_BYTEA)
            $sth = $dbh->query(sprintf("UPDATE $page_tbl"
                                       . " SET cached_html='%s'"
                                       . " WHERE pagename='%s'",
                                       $this->_quote($data), 
                                       $dbh->escapeSimple($pagename)));
        else
            $sth = $dbh->query("UPDATE $page_tbl"
                                       . " SET cached_html=?"
                                       . " WHERE pagename=?",
                                       // PearDB does NOT use pg_escape_string()! Oh dear.
                                       array($this->_quote($data), $pagename));
    }

    /**
     * Lock all tables we might use.
     */
    function _lock_tables($write_lock = true) {
        $this->_dbh->query("BEGIN");
    }

    /**
     * Unlock all tables.
     */
    function _unlock_tables() {
        $this->_dbh->query("COMMIT");
    }

    /**
     * Serialize data
     */
    function _serialize($data) {
        if (empty($data))
            return '';
        assert(is_array($data));
        return $this->_quote(serialize($data));
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
        return unserialize($this->_unquote($data));
    }
};

class WikiDB_backend_PearDB_pgsql_search
extends WikiDB_backend_PearDB_search
{
    function _pagename_match_clause($node) {
        $word = $node->sql();
        if ($node->op == 'REGEX') { // posix regex extensions
            return ($this->_case_exact 
                    ? "pagename ~* '$word'"
                    : "pagename ~ '$word'");
        } else {
            return ($this->_case_exact 
                    ? "pagename LIKE '$word'" 
                    : "pagename ILIKE '$word'");
        }
    }

    // TODO: use tsearch2. For now the same as parent
    /*
    function _fulltext_match_clause($node) { 
        $word = $node->sql();
        if ($word == '%')
            return "1=1";
        // Eliminate stoplist words
        if (preg_match("/^%".$this->_stoplist."%/i", $word) 
            or preg_match("/^".$this->_stoplist."$/i", $word))
            return $this->_pagename_match_clause($node);
        else
            return $this->_pagename_match_clause($node) 
                . ($this->_case_exact
                   ? " OR content LIKE '$word'"
                   : " OR content ILIKE '$word'");
    }
    */
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
