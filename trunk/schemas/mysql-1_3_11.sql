-- $Id: mysql-1_3_11.sql,v 1.4 2005-02-27 09:33:05 rurban Exp $
-- phpwiki 1.3.11 upgrade from 1.3.10

-- if ACCESS_LOG_SQL > 0
-- only if you need fast log-analysis (spam prevention, recent referrers)
-- see http://www.outoforder.cc/projects/apache/mod_log_sql/docs-2.0/#id2756178
CREATE TABLE accesslog (
        time_stamp    int unsigned,
	remote_host   varchar(50),
	remote_user   varchar(50),
        request_method varchar(10),
	request_line  varchar(255),
	request_args  varchar(255),
	request_file  varchar(255),
	request_uri   varchar(255),
	request_time  char(28),
	status 	      smallint unsigned,
	bytes_sent    smallint unsigned,
        referer       varchar(255), 
	agent         varchar(255),
	request_duration float
);
CREATE INDEX log_time ON accesslog (time_stamp);
CREATE INDEX log_host ON accesslog (remote_host);

