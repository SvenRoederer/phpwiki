<?php

require_once 'lib/WikiDB.php';
require_once 'lib/WikiDB/backend/dba.php';

class WikiDB_dba extends WikiDB
{
    function __construct($dbparams)
    {
        $backend = new WikiDB_backend_dba($dbparams);
        parent::__construct($backend, $dbparams);

        if (empty($dbparams['directory'])
            || preg_match('@^/tmp\b@', $dbparams['directory'])
        )
            trigger_error(sprintf(_("The %s files are in the %s directory. Please read the INSTALL file and move the database to a permanent location or risk losing all the pages!"),
                "DBA", "/tmp"), E_USER_WARNING);
    }
}
