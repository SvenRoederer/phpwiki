-- $Id: oci8-initialize.sql,v 1.1 2004-07-22 16:50:07 dfrankow Exp $

set verify off
set feedback off

--================================================================
-- Prefix for table names.
--
-- You should set this to the same value you specify for
-- $DBParams['prefix'] in index.php.
--
-- You have to use a prefix, because some phpWiki tablenames are 
-- Oracle reserved words!

define prefix=phpwiki_

--================================================================
--
-- Don't modify below this point unless you know what you are doing.
--
--================================================================

--================================================================
-- Note on Oracle datatypes...
-- 
-- Most of the 'NOT NULL' constraints on the character columns have been 
-- 	dropped since they can contain empty strings which are seen by 
--	Oracle as NULL.
-- Oracle CLOBs are used for TEXTs/MEDUIMTEXTs columns.


prompt Initializing PhpWiki tables with:
prompt        prefix =  &prefix
prompt 
prompt Expect some 'ORA-00942: table or view does not exist' unless you are
prompt overwriting existing tables.
prompt 

define page_tbl=&prefix.page
define page_id=&prefix.page_id
define page_nm=&prefix.page_nm

define version_tbl=&prefix.version
define vers_id=&prefix.vers_id
define vers_mtime=&prefix.vers_mtime

define recent_tbl=&prefix.recent
define recent_id=&prefix.recent_id

define nonempty_tbl=&prefix.nonempty
define nonmt_id=&prefix.nonmt_id

define link_tbl=&prefix.link
define link_from=&prefix.link_from
define link_to=&prefix.link_to

define session_tbl=&prefix.session
define sess_id=&prefix.sess_id
define sess_date=&prefix.sess_date
define sess_ip=&prefix.sess_ip

define pref_tbl=&prefix.pref
define pref_id=&prefix.pref_id

define user_tbl=&prefix.user
define user_id=&prefix.user_id

define member_tbl=&prefix.member
define member_userid=&prefix.member_userid
define member_groupname=&prefix.member_groupname

define rating_tbl=&prefix.rating
define rating_id=&prefix.rating_id


prompt Creating &page_tbl
CREATE TABLE &page_tbl (
	id		INT NOT NULL,
        pagename	VARCHAR(100) NOT NULL,
	hits		INT DEFAULT 0 NOT NULL,
        pagedata	CLOB DEFAULT '',
	CONSTRAINT &page_id PRIMARY KEY (id),
	CONSTRAINT &page_nm UNIQUE (pagename)
);

prompt Creating &version_tbl
CREATE TABLE &version_tbl (
	id		INT NOT NULL,
        version		INT NOT NULL,
	mtime		INT NOT NULL,
	minor_edit	INT DEFAULT 0,
        content		CLOB DEFAULT '',
        versiondata	CLOB DEFAULT '',
	CONSTRAINT &vers_id PRIMARY KEY (id,version)
);
CREATE INDEX &vers_mtime ON &version_tbl (mtime);

prompt Creating &recent_tbl
CREATE TABLE &recent_tbl (
	id		INT NOT NULL,
	latestversion	INT,
	latestmajor	INT,
	latestminor	INT,
	CONSTRAINT &recent_id PRIMARY KEY (id)
);

prompt Creating &nonempty_tbl
CREATE TABLE &nonempty_tbl (
	id		INT NOT NULL,
	CONSTRAINT &nonempty_tbl PRIMARY KEY (id)
);

prompt Creating &link_tbl
CREATE TABLE &link_tbl (
        linkfrom	INT NOT NULL,
        linkto		INT NOT NULL
);
CREATE INDEX &link_from ON &link_tbl (linkfrom);
CREATE INDEX &link_to   ON &link_tbl (linkto);

prompt Creating &session_tbl
CREATE TABLE &session_tbl (
	sess_id 	CHAR(32) DEFAULT '',
    	sess_data 	CLOB,
    	sess_date 	INT,
    	sess_ip 	CHAR(15) NOT NULL,
	CONSTRAINT &sess_id PRIMARY KEY (sess_id)
);
CREATE INDEX &sess_date ON &session_tbl (sess_date);
CREATE INDEX &sess_ip   ON &session_tbl (sess_ip);

-- Optional DB Auth and Prefs
-- For these tables below the default table prefix must be used 
-- in the DBAuthParam SQL statements also.

prompt Creating &pref_tbl
CREATE TABLE &pref_tbl (
  	userid 	CHAR(48) NOT NULL,
  	prefs  	CLOB DEFAULT '',
	CONSTRAINT &pref_id PRIMARY KEY (userid)
);

-- better use the extra pref table where such users can be created easily 
-- without password.

prompt Creating &user_tbl
CREATE TABLE &user_tbl (
  	userid 	CHAR(48) NOT NULL,
  	passwd 	CHAR(48) DEFAULT '',
--	prefs  	CLOB DEFAULT '',
--	groupname CHAR(48) DEFAULT 'users',
  	CONSTRAINT &user_id PRIMARY KEY (userid)
);

prompt Creating &member_tbl
CREATE TABLE &member_tbl (
	userid    CHAR(48) NOT NULL,
   	groupname CHAR(48) DEFAULT 'users' NOT NULL
);
CREATE INDEX &member_userid ON &member_tbl (userid);
CREATE INDEX &member_groupname ON &member_tbl (groupname);

-- if you plan to use the wikilens theme
prompt Creating &rating_tbl
CREATE TABLE &rating_tbl (
        dimension NUMBER(4) NOT NULL,
        raterpage NUMBER(11) NOT NULL,
        rateepage NUMBER(11) NOT NULL,
        ratingvalue FLOAT NOT NULL,
        rateeversion NUMBER(11) NOT NULL,
        tstamp TIMESTAMP NOT NULL,
        CONSTRAINT &rating_id PRIMARY KEY (dimension, raterpage, rateepage)
);
