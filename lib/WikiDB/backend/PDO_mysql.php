<?php
/**
 * Copyright © 2005 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0+
 *
 */

/**
 * @author: Reini Urban
 */
require_once 'lib/WikiDB/backend/PDO.php';

class WikiDB_backend_PDO_mysql
    extends WikiDB_backend_PDO
{
    function __construct($dbparams)
    {
        parent::__construct($dbparams);

        if (!empty($this->_serverinfo['version'])) {
            $arr = explode('.', $this->_serverinfo['version']);
            $this->_serverinfo['version'] = (string)(($arr[0] * 100) + $arr[1]) . "." . (integer)$arr[2];
        }
        if ($this->_serverinfo['version'] < 323.0) {
            // Older MySQL's don't have CASE WHEN ... END
            $this->_expressions['maxmajor'] = "MAX(IF(minor_edit=0,version,0))";
            $this->_expressions['maxminor'] = "MAX(IF(minor_edit<>0,version,0))";
        }

        if ($this->_serverinfo['version'] > 401.0) {
            $this->_dbh->query("SET NAMES 'UTF-8'");
        }
    }

    function backendType()
    {
        return 'mysql';
    }

    /**
     * Kill timed out processes. ( so far only called on about every 50-th save. )
     */
    private function _timeout()
    {
        if (empty($this->_dbparams['timeout'])) return;
        $sth = $this->_dbh->prepare("SHOW processlist");
        if ($sth->execute())
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                if ($row["db"] == $this->_dsn['database']
                    and $row["User"] == $this->_dsn['username']
                        and $row["Time"] > $this->_dbparams['timeout']
                            and $row["Command"] == "Sleep"
                ) {
                    $process_id = $row["Id"];
                    $this->query("KILL $process_id");
                }
            }
    }

    /**
     * Pack tables.
     */
    function optimize()
    {
        $this->_timeout();
        foreach ($this->_table_names as $table) {
            $this->query("OPTIMIZE TABLE $table");
        }
        return true;
    }

    function listOfTables()
    {
        $sth = $this->_dbh->prepare("SHOW TABLES");
        $sth->execute();
        $tables = array();
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    function listOfFields($database, $table)
    {
        $old_db = $this->database();
        if ($database != $old_db) {
            try {
                $dsn = preg_replace("/dbname=\w+;/", "dbname=" . $database, $this->_dsn);
                $dsn = preg_replace("/database=\w+;/", "database=" . $database, $dsn);
                $conn = new PDO($dsn,
                    DBADMIN_USER ? DBADMIN_USER : $this->_parsedDSN['username'],
                    DBADMIN_PASSWD ? DBADMIN_PASSWD : $this->_parsedDSN['password']);
            } catch (PDOException $e) {
                echo "<br>\nDB Connection failed: " . $e->getMessage();
                echo "<br>\nDSN: '", $this->_dsn, "'";
                echo "<br>\n_parsedDSN: '", print_r($this->_parsedDSN), "'";
                $conn = $this->_dbh;
            }
        } else {
            $conn = $this->_dbh;
        }
        $sth = $conn->prepare("SHOW COLUMNS FROM $table");
        $sth->execute();
        $field_list = array();
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            $field_list[] = $row[0];
        }
        if ($database != $old_db) {
            unset($conn);
        }
        return $field_list;
    }

    /*
     * offset specific syntax within mysql
     * convert from,count to SQL "LIMIT $offset, $count"
     */
    function _limit_sql($limit = false)
    {
        if ($limit) {
            list($offset, $count) = $this->limit($limit);
            if ($offset)
                // pgsql needs "LIMIT $count OFFSET $from"
                $limit = " LIMIT $offset, $count";
            else
                $limit = " LIMIT $count";
        } else
            $limit = '';
        return $limit;
    }

}
