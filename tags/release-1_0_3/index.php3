<?
   /*
      The main page, i.e. the main loop.
      This file is always called first.
   */

   include "wiki_config.php3";
   include "wiki_stdlib.php3";
   include "wiki_dbmlib.php3";

   // All requests require the database
   if ($copy) {
      // we are editing a copy and want the archive
      $dbi = OpenDataBase($ArchiveDataBase);
      include "wiki_editpage.php3";
      CloseDataBase($dbi);
      exit();
   } else {
      // live database
      $dbi = OpenDataBase($WikiDataBase);
   }


   if ($edit) {
      include "wiki_editpage.php3";
   } elseif ($links) {
      include "wiki_editlinks.php3";
   } elseif ($search) {
      include "wiki_search.php3";
   } elseif ($full) {
      include "wiki_fullsearch.php3";
   } elseif ($post) {
      include "wiki_savepage.php3";
   } else {
      include "wiki_display.php3"; // defaults to FrontPage
   }

   CloseDataBase($dbi);

?>
