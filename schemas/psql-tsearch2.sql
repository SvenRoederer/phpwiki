-- $Id: psql-tsearch2.sql,v 1.2 2007-01-28 23:35:59 rurban Exp $
-- Add the tsearch2 fulltextsearch extension to an existing phpwiki database. Recommended.
-- Tested with 7.4, 8.0, 8.1, 8.2

\set QUIET
\set prefix 	''
\set httpd_user	'wikiuser'

\echo 'You have to initialize the tsearch2 module first:'
\echo '  psql ' :prefix 'phpwiki < /usr/share/postgresql/contrib/tsearch2.sql'

\echo 'Did you customize the ispell and synonym dictionaries?'
-- remove the quit line below
\quit
--example of ISpell dictionary:
-- UPDATE pg_ts_dict SET dict_initoption='DictFile="/usr/local/share/ispell/russian.dict" ,AffFile ="/usr/local/share/ispell/russian.aff", StopFile="/usr/local/share/ispell/russian.stop"' WHERE dict_name='ispell_template';
--example of synonym dict:
-- UPDATE pg_ts_dict SET dict_initoption='/usr/local/share/ispell/english.syn' WHERE dict_id=5; 

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

\set qprefix '\'' :prefix '\''
\set qhttp_user '\'' :httpd_user '\''
\echo '       prefix = ' :qprefix
\echo '   httpd_user = ' :qhttp_user

\set version_tbl 	:prefix 'version'

GRANT SELECT ON pg_ts_dict, pg_ts_parser, pg_ts_cfg, pg_ts_cfgmap TO :httpd_user;
ALTER TABLE :version_tbl ADD COLUMN idxFTI tsvector;
UPDATE :version_tbl SET idxFTI=to_tsvector('default', content);
VACUUM FULL ANALYZE;
CREATE INDEX idxFTI_idx ON :version_tbl USING gist(idxFTI);
VACUUM FULL ANALYZE;
CREATE TRIGGER tsvectorupdate BEFORE UPDATE OR INSERT ON :version_tbl
   FOR EACH ROW EXECUTE PROCEDURE tsearch2(idxFTI, content);
