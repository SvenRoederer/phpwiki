-- $Id: psql-destroy.sql,v 1.2 2005-02-27 09:33:05 rurban Exp $

\set QUIET


--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specify for
-- $DBParams['prefix'] in index.php.

\set prefix 	''

--================================================================
-- Which postgres user gets access to the tables?
--
-- You should set this to the name of the postgres
-- user who will be accessing the tables.
--
-- Commonly, connections from php are made under
-- the user name of 'nobody', 'apache' or 'www'.

\set httpd_user	'www'

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

\set qprefix '\'' :prefix '\''
\set qhttp_user '\'' :httpd_user '\''
\echo Initializing PhpWiki tables with:
\echo '       prefix = ' :qprefix
\echo '   httpd_user = ' :qhttp_user
\echo
\echo 'Expect some \'Relation \'*\' does not exists\' errors unless you are'
\echo 'overwriting existing tables.'

\set page_tbl		:prefix 'page'
\set version_tbl	:prefix 'version'
\set recent_tbl		:prefix 'recent'
\set nonempty_tbl	:prefix 'nonempty'
\set link_tbl		:prefix 'link'
\set session_tbl	:prefix 'session'
\set pref_tbl		:prefix 'pref'
-- \set user_tbl	:prefix 'user'
-- \set member_tbl	:prefix 'member'
\set rating_tbl		:prefix 'rating'
\set accesslog_tbl	:prefix 'accesslog'

\echo Dropping :page_tbl
DROP TABLE :page_tbl;

\echo Dropping :version_tbl
DROP TABLE :version_tbl;

\echo Dropping :recent_tbl
DROP TABLE :recent_tbl;

\echo Dropping :nonempty_tbl
DROP TABLE :nonempty_tbl;

\echo Dropping :link_tbl
DROP TABLE :link_tbl;

\echo Dropping :session_tbl
DROP TABLE :session_tbl;

\echo Dropping :pref_tbl
DROP TABLE :pref_tbl;

-- DROP TABLE :user_tbl;
-- DROP TABLE :member_tbl;

\echo Dropping :rating_tbl
DROP TABLE :rating_tbl;

\echo Dropping :accesslog_tbl
DROP TABLE :accesslog_tbl;

