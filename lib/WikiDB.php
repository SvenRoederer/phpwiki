<?php //-*-php-*-
rcs_id('$Id: WikiDB.php,v 1.19 2003-02-16 19:43:10 dairiki Exp $');

//FIXME: arg on get*Revision to hint that content is wanted.

/**
 * The classes in the file define the interface to the
 * page database.
 *
 * @package WikiDB
 * @author Geoffrey T. Dairiki <dairiki@dairiki.org>
 */

/**
 * Force the creation of a new revision.
 * @see WikiDB_Page::createRevision()
 */
define('WIKIDB_FORCE_CREATE', -1);

// FIXME:  used for debugging only.  Comment out if cache does not work
define('USECACHE', 1);

/** 
 * Abstract base class for the database used by PhpWiki.
 *
 * A <tt>WikiDB</tt> is a container for <tt>WikiDB_Page</tt>s which in
 * turn contain <tt>WikiDB_PageRevision</tt>s.
 *
 * Conceptually a <tt>WikiDB</tt> contains all possible
 * <tt>WikiDB_Page</tt>s, whether they have been initialized or not.
 * Since all possible pages are already contained in a WikiDB, a call
 * to WikiDB::getPage() will never fail (barring bugs and
 * e.g. filesystem or SQL database problems.)
 *
 * Also each <tt>WikiDB_Page</tt> always contains at least one
 * <tt>WikiDB_PageRevision</tt>: the default content (e.g. "Describe
 * [PageName] here.").  This default content has a version number of
 * zero.
 *
 * <tt>WikiDB_PageRevision</tt>s have read-only semantics. One can
 * only create new revisions or delete old ones --- one can not modify
 * an existing revision.
 */
class WikiDB {
    /**
     * Open a WikiDB database.
     *
     * This is a static member function. This function inspects its
     * arguments to determine the proper subclass of WikiDB to
     * instantiate, and then it instantiates it.
     *
     * @access public
     *
     * @param hash $dbparams Database configuration parameters.
     * Some pertinent paramters are:
     * <dl>
     * <dt> dbtype
     * <dd> The back-end type.  Current supported types are:
     *   <dl>
     *   <dt> SQL
     *   <dd> Generic SQL backend based on the PEAR/DB database abstraction
     *       library.
     *   <dt> dba
     *   <dd> Dba based backend.
     *   </dl>
     *
     * <dt> dsn
     * <dd> (Used by the SQL backend.)
     *      The DSN specifying which database to connect to.
     *
     * <dt> prefix
     * <dd> Prefix to be prepended to database table (and file names).
     *
     * <dt> directory
     * <dd> (Used by the dba backend.)
     *      Which directory db files reside in.
     *
     * <dt> timeout
     * <dd> (Used by the dba backend.)
     *      Timeout in seconds for opening (and obtaining lock) on the
     *      db files.
     *
     * <dt> dba_handler
     * <dd> (Used by the dba backend.)
     *
     *      Which dba handler to use. Good choices are probably either
     *      'gdbm' or 'db2'.
     * </dl>
     *
     * @return WikiDB A WikiDB object.
     **/
    function open ($dbparams) {
        $dbtype = $dbparams{'dbtype'};
        include_once("lib/WikiDB/$dbtype.php");
				
        $class = 'WikiDB_' . $dbtype;
        return new $class ($dbparams);
    }


    /**
     * Constructor.
     *
     * @access private
     * @see open()
     */
    function WikiDB ($backend, $dbparams) {
        $this->_backend = &$backend;
        $this->_cache = new WikiDB_cache($backend);

        // If the database doesn't yet have a timestamp, initialize it now.
        if ($this->get('_timestamp') === false)
            $this->touch();
        
        //FIXME: devel checking.
        //$this->_backend->check();
    }
    
    /**
     * Get any user-level warnings about this WikiDB.
     *
     * Some back-ends, e.g. by default create there data files in the
     * global /tmp directory. We would like to warn the user when this
     * happens (since /tmp files tend to get wiped periodically.)
     * Warnings such as these may be communicated from specific
     * back-ends through this method.
     *
     * @access public
     *
     * @return string A warning message (or <tt>false</tt> if there is
     * none.)
     */
    function genericWarnings() {
        return false;
    }
     
    /**
     * Close database connection.
     *
     * The database may no longer be used after it is closed.
     *
     * Closing a WikiDB invalidates all <tt>WikiDB_Page</tt>s,
     * <tt>WikiDB_PageRevision</tt>s and <tt>WikiDB_PageIterator</tt>s
     * which have been obtained from it.
     *
     * @access public
     */
    function close () {
        $this->_backend->close();
        $this->_cache->close();
    }
    
    /**
     * Get a WikiDB_Page from a WikiDB.
     *
     * A {@link WikiDB} consists of the (infinite) set of all possible pages,
     * therefore this method never fails.
     *
     * @access public
     * @param string $pagename Which page to get.
     * @return WikiDB_Page The requested WikiDB_Page.
     */
    function getPage($pagename) {
        assert(is_string($pagename) && $pagename);
        return new WikiDB_Page($this, $pagename);
    }

        
    // Do we need this?
    //function nPages() { 
    //}


    /**
     * Determine whether page exists (in non-default form).
     *
     * <pre>
     *   $is_page = $dbi->isWikiPage($pagename);
     * </pre>
     * is equivalent to
     * <pre>
     *   $page = $dbi->getPage($pagename);
     *   $current = $page->getCurrentRevision();
     *   $is_page = ! $current->hasDefaultContents();
     * </pre>
     * however isWikiPage may be implemented in a more efficient
     * manner in certain back-ends.
     *
     * @access public
     *
     * @param string $pagename string Which page to check.
     *
     * @return boolean True if the page actually exists with
     * non-default contents in the WikiDataBase.
     */
    function isWikiPage ($pagename) {
        $page = $this->getPage($pagename);
        $current = $page->getCurrentRevision();
        return ! $current->hasDefaultContents();
    }

    /**
     * Delete page from the WikiDB. 
     *
     * Deletes all revisions of the page from the WikiDB. Also resets
     * all page meta-data to the default values.
     *
     * @access public
     *
     * @param string $pagename Name of page to delete.
     */
    function deletePage($pagename) {
        $this->_cache->delete_page($pagename);
        $this->_backend->set_links($pagename, false);
    }

    /**
     * Retrieve all pages.
     *
     * Gets the set of all pages with non-default contents.
     *
     * FIXME: do we need this?  I think so.  The simple searches
     *        need this stuff.
     *
     * @access public
     *
     * @param boolean $include_defaulted Normally pages whose most
     * recent revision has empty content are considered to be
     * non-existant. Unless $include_defaulted is set to true, those
     * pages will not be returned.
     *
     * @return WikiDB_PageIterator A WikiDB_PageIterator which contains all pages
     *     in the WikiDB which have non-default contents.
     */
    function getAllPages($include_defaulted = false) {
        $result = $this->_backend->get_all_pages($include_defaulted);
        return new WikiDB_PageIterator($this, $result);
    }

    /**
     * Title search.
     *
     * Search for pages containing (or not containing) certain words
     * in their names.
     *
     * Pages are returned in alphabetical order whenever it is
     * practical to do so.
     *
     * FIXME: should titleSearch and fullSearch be combined?  I think so.
     *
     * @access public
     * @param TextSearchQuery $search A TextSearchQuery object
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching pages.
     * @see TextSearchQuery
     */
    function titleSearch($search) {
        $result = $this->_backend->text_search($search);
        return new WikiDB_PageIterator($this, $result);
    }

    /**
     * Full text search.
     *
     * Search for pages containing (or not containing) certain words
     * in their entire text (this includes the page content and the
     * page name).
     *
     * Pages are returned in alphabetical order whenever it is
     * practical to do so.
     *
     * @access public
     *
     * @param TextSearchQuery $search A TextSearchQuery object.
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching pages.
     * @see TextSearchQuery
     */
    function fullSearch($search) {
        $result = $this->_backend->text_search($search, 'full_text');
        return new WikiDB_PageIterator($this, $result);
    }

    /**
     * Find the pages with the greatest hit counts.
     *
     * Pages are returned in reverse order by hit count.
     *
     * @access public
     *
     * @param integer $limit The maximum number of pages to return.
     * Set $limit to zero to return all pages.  If $limit < 0, pages will
     * be sorted in decreasing order of popularity.
     *
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the matching
     * pages.
     */
    function mostPopular($limit = 20) {
        $result = $this->_backend->most_popular($limit);
        return new WikiDB_PageIterator($this, $result);
    }

    /**
     * Find recent page revisions.
     *
     * Revisions are returned in reverse order by creation time.
     *
     * @access public
     *
     * @param hash $params This hash is used to specify various optional
     *   parameters:
     * <dl>
     * <dt> limit 
     *    <dd> (integer) At most this many revisions will be returned.
     * <dt> since
     *    <dd> (integer) Only revisions since this time (unix-timestamp) will be returned. 
     * <dt> include_minor_revisions
     *    <dd> (boolean) Also include minor revisions.  (Default is not to.)
     * <dt> exclude_major_revisions
     *    <dd> (boolean) Don't include non-minor revisions.
     *         (Exclude_major_revisions implies include_minor_revisions.)
     * <dt> include_all_revisions
     *    <dd> (boolean) Return all matching revisions for each page.
     *         Normally only the most recent matching revision is returned
     *         for each page.
     * </dl>
     *
     * @return WikiDB_PageRevisionIterator A WikiDB_PageRevisionIterator containing the
     * matching revisions.
     */
    function mostRecent($params = false) {
        $result = $this->_backend->most_recent($params);
        return new WikiDB_PageRevisionIterator($this, $result);
    }

   /**
     * Blog search. (experimental)
     *
     * Search for blog entries related to a certain page.
     *
     * FIXME: with pagetype support and perhaps a RegexpSearchQuery
     * we can make sure we are returning *ONLY* blog pages to the
     * main routine.  Currently, we just use titleSearch which requires
     * some furher checking in lib/plugin/WikiBlog.php (BAD).
     *
     * @access public
     *
     * @param string $order  'normal' (chronological) or 'reverse'
     * @param string $page   Find blog entries related to this page.
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing the relevant pages.
     */
    function blogSearch($page, $order) {
      //FIXME: implement ordering

      require_once('lib/TextSearchQuery.php');
      $query = new TextSearchQuery ($page . SUBPAGE_SEPARATOR);

      return $this->titleSearch($query);
    }

    /** Get timestamp when database was last modified.
     *
     * @return string A string consisting of two integers,
     * separated by a space.  The first is the time in
     * unix timestamp format, the second is a modification
     * count for the database.
     *
     * The idea is that you can cast the return value to an
     * int to get a timestamp, or you can use the string value
     * as a good hash for the entire database.
     */
    function getTimestamp() {
        $ts = $this->get('_timestamp');
        return sprintf("%d %d", $ts[0], $ts[1]);
    }
    
    /**
     * Update the database timestamp.
     *
     */
    function touch() {
        $ts = $this->get('_timestamp');
        $this->set('_timestamp', array(time(), $ts[1] + 1));
    }

        
    /**
     * Access WikiDB global meta-data.
     *
     * NOTE: this is currently implemented in a hackish and
     * not very efficient manner.
     *
     * @access public
     *
     * @param string $key Which meta data to get.
     * Some reserved meta-data keys are:
     * <dl>
     * <dt>'_timestamp' <dd> Data used by getTimestamp().
     * </dl>
     *
     * @return scalar The requested value, or false if the requested data
     * is not set.
     */
    function get($key) {
        if (!$key || $key[0] == '%')
            return false;
        /*
         * Hack Alert: We can use any page (existing or not) to store
         * this data (as long as we always use the same one.)
         */
        $gd = $this->getPage('global_data');
        $data = $gd->get('__global');

        if ($data && isset($data[$key]))
            return $data[$key];
        else
            return false;
    }

    /**
     * Set global meta-data.
     *
     * NOTE: this is currently implemented in a hackish and
     * not very efficient manner.
     *
     * @see get
     * @access public
     *
     * @param string $key  Meta-data key to set.
     * @param string $newval  New value.
     */
    function set($key, $newval) {
        if (!$key || $key[0] == '%')
            return;
        
        $gd = $this->getPage('global_data');
        
        $data = $gd->get('__global');
        if ($data === false)
            $data = array();

        if (empty($newval))
            unset($data[$key]);
        else
            $data[$key] = $newval;

        $gd->set('__global', $data);
    }
};


/**
 * An abstract base class which representing a wiki-page within a
 * WikiDB.
 *
 * A WikiDB_Page contains a number (at least one) of
 * WikiDB_PageRevisions.
 */
class WikiDB_Page 
{
    function WikiDB_Page(&$wikidb, $pagename) {
        $this->_wikidb = &$wikidb;
        $this->_pagename = $pagename;
        assert(!empty($this->_pagename));
    }

    /**
     * Get the name of the wiki page.
     *
     * @access public
     *
     * @return string The page name.
     */
    function getName() {
        return $this->_pagename;
    }


    /**
     * Delete an old revision of a WikiDB_Page.
     *
     * Deletes the specified revision of the page.
     * It is a fatal error to attempt to delete the current revision.
     *
     * @access public
     *
     * @param integer $version Which revision to delete.  (You can also
     *  use a WikiDB_PageRevision object here.)
     */
    function deleteRevision($version) {
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        $version = $this->_coerce_to_version($version);
        if ($version == 0)
            return;

        $backend->lock();
        $latestversion = $cache->get_latest_version($pagename);
        if ($latestversion && $version == $latestversion) {
            $backend->unlock();
            trigger_error(sprintf("Attempt to delete most recent revision of '%s'",
                                  $pagename), E_USER_ERROR);
            return;
        }

        $cache->delete_versiondata($pagename, $version);
		
        $backend->unlock();
    }

    /*
     * Delete a revision, or possibly merge it with a previous
     * revision.
     *
     * The idea is this:
     * Suppose an author make a (major) edit to a page.  Shortly
     * after that the same author makes a minor edit (e.g. to fix
     * spelling mistakes he just made.)
     *
     * Now some time later, where cleaning out old saved revisions,
     * and would like to delete his minor revision (since there's
     * really no point in keeping minor revisions around for a long
     * time.)
     *
     * Note that the text after the minor revision probably represents
     * what the author intended to write better than the text after
     * the preceding major edit.
     *
     * So what we really want to do is merge the minor edit with the
     * preceding edit.
     *
     * We will only do this when:
     * <ul>
     * <li>The revision being deleted is a minor one, and
     * <li>It has the same author as the immediately preceding revision.
     * </ul>
     */
    function mergeRevision($version) {
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        $version = $this->_coerce_to_version($version);
        if ($version == 0)
            return;

        $backend->lock();
        $latestversion = $backend->get_latest_version($pagename);
        if ($latestversion && $version == $latestversion) {
            $backend->unlock();
            trigger_error(sprintf("Attempt to merge most recent revision of '%s'",
                                  $pagename), E_USER_ERROR);
            return;
        }

        $versiondata = $cache->get_versiondata($pagename, $version, true);
        if (!$versiondata) {
            // Not there? ... we're done!
            $backend->unlock();
            return;
        }

        if ($versiondata['is_minor_edit']) {
            $previous = $backend->get_previous_version($pagename, $version);
            if ($previous) {
                $prevdata = $cache->get_versiondata($pagename, $previous);
                if ($prevdata['author_id'] == $versiondata['author_id']) {
                    // This is a minor revision, previous version is
                    // by the same author. We will merge the
                    // revisions.
                    $cache->update_versiondata($pagename, $previous,
                                               array('%content' => $versiondata['%content'],
                                                     '_supplanted' => $versiondata['_supplanted']));
                }
            }
        }

        $cache->delete_versiondata($pagename, $version);
        $backend->unlock();
    }

    
    /**
     * Create a new revision of a {@link WikiDB_Page}.
     *
     * @access public
     *
     * @param int $version Version number for new revision.  
     * To ensure proper serialization of edits, $version must be
     * exactly one higher than the current latest version.
     * (You can defeat this check by setting $version to
     * {@link WIKIDB_FORCE_CREATE} --- not usually recommended.)
     *
     * @param string $content Contents of new revision.
     *
     * @param hash $metadata Metadata for new revision.
     * All values in the hash should be scalars (strings or integers).
     *
     * @param array $links List of pagenames which this page links to.
     *
     * @return WikiDB_PageRevision  Returns the new WikiDB_PageRevision object. If
     * $version was incorrect, returns false
     */
    function createRevision($version, &$content, $metadata, $links) {
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;
                
        $backend->lock();

        $latestversion = $backend->get_latest_version($pagename);
        $newversion = $latestversion + 1;
        assert($newversion >= 1);

        if ($version != WIKIDB_FORCE_CREATE && $version != $newversion) {
            $backend->unlock();
            return false;
        }

        $data = $metadata;
        
        foreach ($data as $key => $val) {
            if (empty($val) || $key[0] == '_' || $key[0] == '%')
                unset($data[$key]);
        }
			
        assert(!empty($data['author_id']));
        if (empty($data['author_id']))
            @$data['author_id'] = $data['author'];
		
        if (empty($data['mtime']))
            $data['mtime'] = time();

        if ($latestversion) {
            // Ensure mtimes are monotonic.
            $pdata = $cache->get_versiondata($pagename, $latestversion);
            if ($data['mtime'] < $pdata['mtime']) {
                trigger_error(sprintf(_("%s: Date of new revision is %s"),
                                      $pagename,"'non-monotonic'"),
                              E_USER_NOTICE);
                $data['orig_mtime'] = $data['mtime'];
                $data['mtime'] = $pdata['mtime'];
            }
            
	    // FIXME: use (possibly user specified) 'mtime' time or
	    // time()?
            $cache->update_versiondata($pagename, $latestversion,
                                       array('_supplanted' => $data['mtime']));
        }

        $data['%content'] = &$content;

        $cache->set_versiondata($pagename, $newversion, $data);

        //$cache->update_pagedata($pagename, array(':latestversion' => $newversion,
        //':deleted' => empty($content)));
        
        $backend->set_links($pagename, $links);

        $backend->unlock();

	// FIXME: probably should have some global state information
	// in the backend to control when to optimize.
        if (time() % 50 == 0) {
            trigger_error(sprintf(_("Optimizing %s"),'backend'), E_USER_NOTICE);
            $backend->optimize();
        }

        return new WikiDB_PageRevision($this->_wikidb, $pagename, $newversion,
                                       $data);
    }

    /**
     * Get the most recent revision of a page.
     *
     * @access public
     *
     * @return WikiDB_PageRevision The current WikiDB_PageRevision object. 
     */
    function getCurrentRevision() {
        $backend = &$this->_wikidb->_backend;
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;

        $backend->lock();
        $version = $cache->get_latest_version($pagename);
        $revision = $this->getRevision($version);
        $backend->unlock();
        assert($revision);
        return $revision;
    }

    /**
     * Get a specific revision of a WikiDB_Page.
     *
     * @access public
     *
     * @param integer $version  Which revision to get.
     *
     * @return WikiDB_PageRevision The requested WikiDB_PageRevision object, or
     * false if the requested revision does not exist in the {@link WikiDB}.
     * Note that version zero of any page always exists.
     */
    function getRevision($version) {
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;
        
        if ($version == 0)
            return new WikiDB_PageRevision($this->_wikidb, $pagename, 0);

        assert($version > 0);
        $vdata = $cache->get_versiondata($pagename, $version);
        if (!$vdata)
            return false;
        return new WikiDB_PageRevision($this->_wikidb, $pagename, $version,
                                       $vdata);
    }

    /**
     * Get previous page revision.
     *
     * This method find the most recent revision before a specified
     * version.
     *
     * @access public
     *
     * @param integer $version  Find most recent revision before this version.
     *  You can also use a WikiDB_PageRevision object to specify the $version.
     *
     * @return WikiDB_PageRevision The requested WikiDB_PageRevision object, or false if the
     * requested revision does not exist in the {@link WikiDB}.  Note that
     * unless $version is greater than zero, a revision (perhaps version zero,
     * the default revision) will always be found.
     */
    function getRevisionBefore($version) {
        $backend = &$this->_wikidb->_backend;
        $pagename = &$this->_pagename;

        $version = $this->_coerce_to_version($version);

        if ($version == 0)
            return false;
        $backend->lock();
        $previous = $backend->get_previous_version($pagename, $version);
        $revision = $this->getRevision($previous);
        $backend->unlock();
        assert($revision);
        return $revision;
    }

    /**
     * Get all revisions of the WikiDB_Page.
     *
     * This does not include the version zero (default) revision in the
     * returned revision set.
     *
     * @return WikiDB_PageRevisionIterator A
     * WikiDB_PageRevisionIterator containing all revisions of this
     * WikiDB_Page in reverse order by version number.
     */
    function getAllRevisions() {
        $backend = &$this->_wikidb->_backend;
        $revs = $backend->get_all_revisions($this->_pagename);
        return new WikiDB_PageRevisionIterator($this->_wikidb, $revs);
    }
    
    /**
     * Find pages which link to or are linked from a page.
     *
     * @access public
     *
     * @param boolean $reversed Which links to find: true for backlinks (default).
     *
     * @return WikiDB_PageIterator A WikiDB_PageIterator containing
     * all matching pages.
     */
    function getLinks($reversed = true) {
        $backend = &$this->_wikidb->_backend;
        $result =  $backend->get_links($this->_pagename, $reversed);
        return new WikiDB_PageIterator($this->_wikidb, $result);
    }
            
    /**
     * Access WikiDB_Page meta-data.
     *
     * @access public
     *
     * @param string $key Which meta data to get.
     * Some reserved meta-data keys are:
     * <dl>
     * <dt>'locked'<dd> Is page locked?
     * <dt>'hits'  <dd> Page hit counter.
     * <dt>'pref'  <dd> Users preferences, stored in homepages.
     * <dt>'owner' <dd> Default: first author_id. We might add a group with a dot here:
     *                  E.g. "owner.users"
     * <dt>'perm'  <dd> Permission flag to authorize read/write/execution of 
     *                  page-headers and content.
     * <dt>'score' <dd> Page score (not yet implement, do we need?)
     * </dl>
     *
     * @return scalar The requested value, or false if the requested data
     * is not set.
     */
    function get($key) {
        $cache = &$this->_wikidb->_cache;
        if (!$key || $key[0] == '%')
            return false;
        $data = $cache->get_pagedata($this->_pagename);
        return isset($data[$key]) ? $data[$key] : false;
    }

    /**
     * Get all the page meta-data as a hash.
     *
     * @return hash The page meta-data.
     */
    function getMetaData() {
        $cache = &$this->_wikidb->_cache;
        $data = $cache->get_pagedata($this->_pagename);
        $meta = array();
        foreach ($data as $key => $val) {
            if (!empty($val) && $key[0] != '%')
                $meta[$key] = $val;
        }
        return $meta;
    }

    /**
     * Set page meta-data.
     *
     * @see get
     * @access public
     *
     * @param string $key  Meta-data key to set.
     * @param string $newval  New value.
     */
    function set($key, $newval) {
        $cache = &$this->_wikidb->_cache;
        $pagename = &$this->_pagename;
        
        assert($key && $key[0] != '%');

        $data = $cache->get_pagedata($pagename);

        if (!empty($newval)) {
            if (!empty($data[$key]) && $data[$key] == $newval)
                return;         // values identical, skip update.
        }
        else {
            if (empty($data[$key]))
                return;         // values identical, skip update.
        }

        $cache->update_pagedata($pagename, array($key => $newval));
    }

    /**
     * Increase page hit count.
     *
     * FIXME: IS this needed?  Probably not.
     *
     * This is a convenience function.
     * <pre> $page->increaseHitCount(); </pre>
     * is functionally identical to
     * <pre> $page->set('hits',$page->get('hits')+1); </pre>
     *
     * Note that this method may be implemented in more efficient ways
     * in certain backends.
     *
     * @access public
     */
    function increaseHitCount() {
        @$newhits = $this->get('hits') + 1;
        $this->set('hits', $newhits);
    }

    /**
     * Return a string representation of the WikiDB_Page
     *
     * This is really only for debugging.
     *
     * @access public
     *
     * @return string Printable representation of the WikiDB_Page.
     */
    function asString () {
        ob_start();
        printf("[%s:%s\n", get_class($this), $this->getName());
        print_r($this->getMetaData());
        echo "]\n";
        $strval = ob_get_contents();
        ob_end_clean();
        return $strval;
    }


    /**
     * @access private
     * @param integer_or_object $version_or_pagerevision
     * Takes either the version number (and int) or a WikiDB_PageRevision
     * object.
     * @return integer The version number.
     */
    function _coerce_to_version($version_or_pagerevision) {
        if (method_exists($version_or_pagerevision, "getContent"))
            $version = $version_or_pagerevision->getVersion();
        else
            $version = (int) $version_or_pagerevision;

        assert($version >= 0);
        return $version;
    }

    function isUserPage ($include_empty = true) {
        return $this->get('pref') ? true : false;
        if ($include_empty)
            return true;
        $current = $this->getCurrentRevision();
        return ! $current->hasDefaultContents();
    }

};

/**
 * This class represents a specific revision of a WikiDB_Page within
 * a WikiDB.
 *
 * A WikiDB_PageRevision has read-only semantics. You may only create
 * new revisions (and delete old ones) --- you cannot modify existing
 * revisions.
 */
class WikiDB_PageRevision
{
    function WikiDB_PageRevision(&$wikidb, $pagename, $version,
                                 $versiondata = false)
        {
            $this->_wikidb = &$wikidb;
            $this->_pagename = $pagename;
            $this->_version = $version;
            $this->_data = $versiondata ? $versiondata : array();
        }
    
    /**
     * Get the WikiDB_Page which this revision belongs to.
     *
     * @access public
     *
     * @return WikiDB_Page The WikiDB_Page which this revision belongs to.
     */
    function getPage() {
        return new WikiDB_Page($this->_wikidb, $this->_pagename);
    }

    /**
     * Get the version number of this revision.
     *
     * @access public
     *
     * @return integer The version number of this revision.
     */
    function getVersion() {
        return $this->_version;
    }
    
    /**
     * Determine whether this revision has defaulted content.
     *
     * The default revision (version 0) of each page, as well as any
     * pages which are created with empty content have their content
     * defaulted to something like:
     * <pre>
     *   Describe [ThisPage] here.
     * </pre>
     *
     * @access public
     *
     * @return boolean Returns true if the page has default content.
     */
    function hasDefaultContents() {
        $data = &$this->_data;
        return empty($data['%content']);
    }

    /**
     * Get the content as an array of lines.
     *
     * @access public
     *
     * @return array An array of lines.
     * The lines should contain no trailing white space.
     */
    function getContent() {
        return explode("\n", $this->getPackedContent());
    }
	
	/**
     * Get the pagename of the revision.
     *
     * @access public
     *
     * @return string pagename.
     */
    function getPageName() {
        return $this->_pagename;
    }

    /**
     * Determine whether revision is the latest.
     *
     * @access public
     *
     * @return boolean True iff the revision is the latest (most recent) one.
     */
    function isCurrent() {
        if (!isset($this->_iscurrent)) {
            $page = $this->getPage();
            $current = $page->getCurrentRevision();
            $this->_iscurrent = $this->getVersion() == $current->getVersion();
        }
        return $this->_iscurrent;
    }
    
    /**
     * Get the content as a string.
     *
     * @access public
     *
     * @return string The page content.
     * Lines are separated by new-lines.
     */
    function getPackedContent() {
        $data = &$this->_data;

        
        if (empty($data['%content'])) {
            // Replace empty content with default value.
            return sprintf(_("Describe %s here."),
                           "[". $this->_pagename ."]");
        }

        // There is (non-default) content.
        assert($this->_version > 0);
        
        if (!is_string($data['%content'])) {
            // Content was not provided to us at init time.
            // (This is allowed because for some backends, fetching
            // the content may be expensive, and often is not wanted
            // by the user.)
            //
            // In any case, now we need to get it.
            $data['%content'] = $this->_get_content();
            assert(is_string($data['%content']));
        }
        
        return $data['%content'];
    }

    function _get_content() {
        $cache = &$this->_wikidb->_cache;
        $pagename = $this->_pagename;
        $version = $this->_version;

        assert($version > 0);
        
        $newdata = $cache->get_versiondata($pagename, $version, true);
        if ($newdata) {
            assert(is_string($newdata['%content']));
            return $newdata['%content'];
        }
        else {
            // else revision has been deleted... What to do?
            return __sprintf("Acck! Revision %s of %s seems to have been deleted!",
                             $version, $pagename);
        }
    }

    /**
     * Get meta-data for this revision.
     *
     *
     * @access public
     *
     * @param string $key Which meta-data to access.
     *
     * Some reserved revision meta-data keys are:
     * <dl>
     * <dt> 'mtime' <dd> Time this revision was created (seconds since midnight Jan 1, 1970.)
     *        The 'mtime' meta-value is normally set automatically by the database
     *        backend, but it may be specified explicitly when creating a new revision.
     * <dt> orig_mtime
     *  <dd> To ensure consistency of RecentChanges, the mtimes of the versions
     *       of a page must be monotonically increasing.  If an attempt is
     *       made to create a new revision with an mtime less than that of
     *       the preceeding revision, the new revisions timestamp is force
     *       to be equal to that of the preceeding revision.  In that case,
     *       the originally requested mtime is preserved in 'orig_mtime'.
     * <dt> '_supplanted' <dd> Time this revision ceased to be the most recent.
     *        This meta-value is <em>always</em> automatically maintained by the database
     *        backend.  (It is set from the 'mtime' meta-value of the superceding
     *        revision.)  '_supplanted' has a value of 'false' for the current revision.
     *
     * FIXME: this could be refactored:
     * <dt> author
     *  <dd> Author of the page (as he should be reported in, e.g. RecentChanges.)
     * <dt> author_id
     *  <dd> Authenticated author of a page.  This is used to identify
     *       the distinctness of authors when cleaning old revisions from
     *       the database.
     * <dt> 'is_minor_edit' <dd> Set if change was marked as a minor revision by the author.
     * <dt> 'summary' <dd> Short change summary entered by page author.
     * </dl>
     *
     * Meta-data keys must be valid C identifers (they have to start with a letter
     * or underscore, and can contain only alphanumerics and underscores.)
     *
     * @return string The requested value, or false if the requested value
     * is not defined.
     */
    function get($key) {
        if (!$key || $key[0] == '%')
            return false;
        $data = &$this->_data;
        return isset($data[$key]) ? $data[$key] : false;
    }

    /**
     * Get all the revision page meta-data as a hash.
     *
     * @return hash The revision meta-data.
     */
    function getMetaData() {
        $meta = array();
        foreach ($this->_data as $key => $val) {
            if (!empty($val) && $key[0] != '%')
                $meta[$key] = $val;
        }
        return $meta;
    }
    
            
    /**
     * Return a string representation of the revision.
     *
     * This is really only for debugging.
     *
     * @access public
     *
     * @return string Printable representation of the WikiDB_Page.
     */
    function asString () {
        ob_start();
        printf("[%s:%d\n", get_class($this), $this->get('version'));
        print_r($this->_data);
        echo $this->getPackedContent() . "\n]\n";
        $strval = ob_get_contents();
        ob_end_clean();
        return $strval;
    }
};


/**
 * A class which represents a sequence of WikiDB_Pages.
 */
class WikiDB_PageIterator
{
    function WikiDB_PageIterator(&$wikidb, &$pages) {
        $this->_pages = $pages;
        $this->_wikidb = &$wikidb;
    }
    
    /**
     * Get next WikiDB_Page in sequence.
     *
     * @access public
     *
     * @return WikiDB_Page The next WikiDB_Page in the sequence.
     */
    function next () {
        if ( ! ($next = $this->_pages->next()) )
            return false;

        $pagename = &$next['pagename'];
        if (isset($next['pagedata']))
            $this->_wikidb->_cache->cache_data($next);

        return new WikiDB_Page($this->_wikidb, $pagename);
    }

    /**
     * Release resources held by this iterator.
     *
     * The iterator may not be used after free() is called.
     *
     * There is no need to call free(), if next() has returned false.
     * (I.e. if you iterate through all the pages in the sequence,
     * you do not need to call free() --- you only need to call it
     * if you stop before the end of the iterator is reached.)
     *
     * @access public
     */
    function free() {
        $this->_pages->free();
    }

    // Not yet used.
    function setSortby ($arg = false) {
        if (!$arg) {
            $arg = @$_GET['sortby'];
            if ($arg) {
                $sortby = substr($arg,1);
                $order  = substr($arg,0,1)=='+' ? 'ASC' : 'DESC';
            }
        }
        if (is_array($arg)) { // array('mtime' => 'desc')
            $sortby = $arg[0];
            $order = $arg[1];
        } else {
            $sortby = $arg;
            $order  = 'ASC';
        }
        // available column types to sort by:
        // todo: we must provide access methods for the generic dumb/iterator
        $this->_types = explode(',','pagename,mtime,hits,version,author,locked,minor,markup');
        if (in_array($sortby,$this->_types))
            $this->_options['sortby'] = $sortby;
        else
            trigger_error(fmt("Argument %s '%s' ignored",'sortby',$sortby), E_USER_WARNING);
        if (in_array(strtoupper($order),'ASC','DESC')) 
            $this->_options['order'] = strtoupper($order);
        else
            trigger_error(fmt("Argument %s '%s' ignored",'order',$order), E_USER_WARNING);
    }

};

/**
 * A class which represents a sequence of WikiDB_PageRevisions.
 */
class WikiDB_PageRevisionIterator
{
    function WikiDB_PageRevisionIterator(&$wikidb, &$revisions) {
        $this->_revisions = $revisions;
        $this->_wikidb = &$wikidb;
    }
    
    /**
     * Get next WikiDB_PageRevision in sequence.
     *
     * @access public
     *
     * @return WikiDB_PageRevision
     * The next WikiDB_PageRevision in the sequence.
     */
    function next () {
        if ( ! ($next = $this->_revisions->next()) )
            return false;

        $this->_wikidb->_cache->cache_data($next);

        $pagename = $next['pagename'];
        $version = $next['version'];
        $versiondata = $next['versiondata'];
        assert(!empty($pagename));
        assert(is_array($versiondata));
        assert($version > 0);

        return new WikiDB_PageRevision($this->_wikidb, $pagename, $version,
                                       $versiondata);
    }

    /**
     * Release resources held by this iterator.
     *
     * The iterator may not be used after free() is called.
     *
     * There is no need to call free(), if next() has returned false.
     * (I.e. if you iterate through all the revisions in the sequence,
     * you do not need to call free() --- you only need to call it
     * if you stop before the end of the iterator is reached.)
     *
     * @access public
     */
    function free() { 
        $this->_revisions->free();
    }
};


/**
 * Data cache used by WikiDB.
 *
 * FIXME: Maybe rename this to caching_backend (or some such).
 *
 * @access private
 */
class WikiDB_cache 
{
    // FIXME: beautify versiondata cache.  Cache only limited data?

    function WikiDB_cache (&$backend) {
        $this->_backend = &$backend;

        $this->_pagedata_cache = array();
        $this->_versiondata_cache = array();
        array_push ($this->_versiondata_cache, array());
        $this->_glv_cache = array();
    }
    
    function close() {
        $this->_pagedata_cache = false;
		$this->_versiondata_cache = false;
		$this->_glv_cache = false;
    }

    function get_pagedata($pagename) {
        assert(is_string($pagename) && $pagename);
        $cache = &$this->_pagedata_cache;

        if (!isset($cache[$pagename]) || !is_array($cache[$pagename])) {
            $cache[$pagename] = $this->_backend->get_pagedata($pagename);
            if (empty($cache[$pagename]))
                $cache[$pagename] = array();
        }

        return $cache[$pagename];
    }
    
    function update_pagedata($pagename, $newdata) {
        assert(is_string($pagename) && $pagename);

        $this->_backend->update_pagedata($pagename, $newdata);

        if (is_array($this->_pagedata_cache[$pagename])) {
            $cachedata = &$this->_pagedata_cache[$pagename];
            foreach($newdata as $key => $val)
                $cachedata[$key] = $val;
        }
    }

    function invalidate_cache($pagename) {
        unset ($this->_pagedata_cache[$pagename]);
		unset ($this->_versiondata_cache[$pagename]);
		unset ($this->_glv_cache[$pagename]);
    }
    
    function delete_page($pagename) {
        $this->_backend->delete_page($pagename);
        unset ($this->_pagedata_cache[$pagename]);
		unset ($this->_glv_cache[$pagename]);
    }

    // FIXME: ugly
    function cache_data($data) {
        if (isset($data['pagedata']))
            $this->_pagedata_cache[$data['pagename']] = $data['pagedata'];
    }
    
    function get_versiondata($pagename, $version, $need_content = false) {
		//  FIXME: Seriously ugly hackage
	if (defined ('USECACHE')){   //temporary - for debugging
        assert(is_string($pagename) && $pagename);
		// there is a bug here somewhere which results in an assertion failure at line 105
		// of ArchiveCleaner.php  It goes away if we use the next line.
		$need_content = true;
		$nc = $need_content ? '1':'0';
        $cache = &$this->_versiondata_cache;
        if (!isset($cache[$pagename][$version][$nc])||
				!(is_array ($cache[$pagename])) || !(is_array ($cache[$pagename][$version]))) {
            $cache[$pagename][$version][$nc] = 
				$this->_backend->get_versiondata($pagename,$version, $need_content);
			// If we have retrieved all data, we may as well set the cache for $need_content = false
			if($need_content){
				$cache[$pagename][$version]['0'] = $cache[$pagename][$version]['1'];
			}
		}
        $vdata = $cache[$pagename][$version][$nc];
	}
	else
	{
    $vdata = $this->_backend->get_versiondata($pagename, $version, $need_content);
	}
        // FIXME: ugly
        if ($vdata && !empty($vdata['%pagedata']))
            $this->_pagedata_cache[$pagename] = $vdata['%pagedata'];
        return $vdata;
    }

    function set_versiondata($pagename, $version, $data) {
        $new = $this->_backend->
             set_versiondata($pagename, $version, $data);
		// Update the cache
		$this->_versiondata_cache[$pagename][$version]['1'] = $data;
		// FIXME: hack
		$this->_versiondata_cache[$pagename][$version]['0'] = $data;
		// Is this necessary?
		unset($this->_glv_cache[$pagename]);
		
    }

    function update_versiondata($pagename, $version, $data) {
        $new = $this->_backend->
             update_versiondata($pagename, $version, $data);
		// Update the cache
		$this->_versiondata_cache[$pagename][$version]['1'] = $data;
		// FIXME: hack
		$this->_versiondata_cache[$pagename][$version]['0'] = $data;
		// Is this necessary?
		unset($this->_glv_cache[$pagename]);

    }

    function delete_versiondata($pagename, $version) {
        $new = $this->_backend->
            delete_versiondata($pagename, $version);
        unset ($this->_versiondata_cache[$pagename][$version]['1']);
        unset ($this->_versiondata_cache[$pagename][$version]['0']);
        unset ($this->_glv_cache[$pagename]);
    }
	
    function get_latest_version($pagename)  {
	if(defined('USECACHE')){
            assert (is_string($pagename) && $pagename);
            $cache = &$this->_glv_cache;	
            if (!isset($cache[$pagename])) {
                $cache[$pagename] = $this->_backend->get_latest_version($pagename);
                if (empty($cache[$pagename]))
                    $cache[$pagename] = 0;
            } 
            return $cache[$pagename];}
	else {
            return $this->_backend->get_latest_version($pagename); 
        }
    }

};

/**
 * FIXME! Class for externally authenticated users.
 *
 * We might have read-only access to the password and/or group membership,
 * or we might even be able to update the entries.
 *
 * FIXME: This was written before we stored prefs as %pagedata, so
 *
 * FIXME: I believe this is not currently used.
 */
//  class WikiDB_User
//  extends WikiUser
//  {
//      var $_authdb;

//      function WikiDB_User($userid, $authlevel = false) {
//          global $request;
//          $this->_authdb = new WikiAuthDB($GLOBALS['DBAuthParams']);
//          $this->_authmethod = 'AuthDB';
//          WikiUser::WikiUser($request, $userid, $authlevel);
//      }

//      /*
//      function getPreferences() {
//          // external prefs override internal ones?
//          if (! $this->_authdb->getPrefs() )
//              if ($pref = WikiUser::getPreferences())
//                  return $prefs;
//          return false;
//      }

//      function setPreferences($prefs) {
//          if (! $this->_authdb->setPrefs($prefs) )
//              return WikiUser::setPreferences();
//      }
//      */

//      function exists() {
//          return $this->_authdb->exists($this->_userid);
//      }

//      // create user and default user homepage
//      function createUser ($pref) {
//          if ($this->exists()) return;
//          if (! $this->_authdb->createUser($pref)) {
//              // external auth doesn't allow this.
//              // do our policies allow local users instead?
//              return WikiUser::createUser($pref);
//          }
//      }

//      function checkPassword($passwd) {
//          return $this->_authdb->pwcheck($this->userid, $passwd);
//      }

//      function changePassword($passwd) {
//          if (! $this->mayChangePassword() ) {
//              trigger_error(sprintf("Attempt to change an external password for '%s'",
//                                    $this->_userid), E_USER_ERROR);
//              return;
//          }
//          return $this->_authdb->changePass($this->userid, $passwd);
//      }

//      function mayChangePassword() {
//          return $this->_authdb->auth_update;
//      }
//  }

/*
 * FIXME: I believe this is not currently used.
 */
//  class WikiAuthDB
//  extends WikiDB
//  {
//      var $auth_dsn = false, $auth_check = false;
//      var $auth_crypt_method = 'crypt', $auth_update = false;
//      var $group_members = false, $user_groups = false;
//      var $pref_update = false, $pref_select = false;
//      var $_dbh;

//      function WikiAuthDB($DBAuthParams) {
//          foreach ($DBAuthParams as $key => $value) {
//              $this->$key = $value;
//          }
//          if (!$this->auth_dsn) {
//              trigger_error(_("no \$DBAuthParams['dsn'] provided"), E_USER_ERROR);
//              return false;
//          }
//          // compare auth DB to the existing page DB. reuse if it's on the same database.
//          if (isa($this->_backend, 'WikiDB_backend_PearDB') and 
//              $this->_backend->_dsn == $this->auth_dsn) {
//              $this->_dbh = &$this->_backend->_dbh;
//              return $this->_backend;
//          }
//          include_once("lib/WikiDB/SQL.php");
//          return new WikiDB_SQL($DBAuthParams);
//      }

//      function param_missing ($param) {
//          trigger_error(sprintf(_("No \$DBAuthParams['%s'] provided."), $param), E_USER_ERROR);
//          return;
//      }

//      function getPrefs($prefs) {
//          if ($this->pref_select) {
//              $statement = $this->_backend->Prepare($this->pref_select);
//              return unserialize($this->_backend->Execute($statement, 
//                                                          $prefs->get('userid')));
//          } else {
//              param_missing('pref_select');
//              return false;
//          }
//      }

//      function setPrefs($prefs) {
//          if ($this->pref_write) {
//              $statement = $this->_backend->Prepare($this->pref_write);
//              return $this->_backend->Execute($statement, 
//                                              $prefs->get('userid'), serialize($prefs->_prefs));
//          } else {
//              param_missing('pref_write');
//              return false;
//          }
//      }

//      function createUser ($pref) {
//          if ($this->user_create) {
//              $statement = $this->_backend->Prepare($this->user_create);
//              return $this->_backend->Execute($statement, 
//                                          $prefs->get('userid'), serialize($prefs->_prefs));
//          } else {
//              param_missing('user_create');
//              return false;
//          }
//      }

//      function exists($userid) {
//          if ($this->user_check) {
//              $statement = $this->_backend->Prepare($this->user_check);
//              return $this->_backend->Execute($statement, $prefs->get('userid'));
//          } else {
//              param_missing('user_check');
//              return false;
//          }
//      }

//      function pwcheck($userid, $pass) {
//          if ($this->auth_check) {
//              $statement = $this->_backend->Prepare($this->auth_check);
//              return $this->_backend->Execute($statement, $userid, $pass);
//          } else {
//              param_missing('auth_check');
//              return false;
//          }
//      }
//  }

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
