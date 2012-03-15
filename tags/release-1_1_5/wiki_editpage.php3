<!-- $Id: wiki_editpage.php3,v 1.5 2000-06-07 11:07:23 ahollosi Exp $ -->
<?

/*
   This page is for editing a Wiki page.
   It relies on $pagename and $ScriptUrl;
   it will set $pagehash["text"]. 
*/

   if ($edit) {
      $pagename = rawurldecode($edit);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars($pagename);
   } elseif ($copy) {
      $pagename = rawurldecode($copy);
      if (get_magic_quotes_gpc()) {
         $pagename = stripslashes($pagename);
      }
      $banner = htmlspecialchars("Copy of $pagename");
   } else {
      echo "No page name passed into editpage!<br>\n";
      exit();
   }

   $pagehash = RetrievePage($dbi, $pagename);

   if (is_array($pagehash)) {
      $textarea = implode($pagehash["text"], "\n");
      $version = $copy ? $pagehash["version"]+1 : $pagehash["version"];
      if (($pagehash["version"] > 1) &&
          ($pagehash["author"] != $remoteuser)) {
         $lastcopy = $pagename;
      } else {
         $lastcopy = false;
      }
   } else {
      $textarea = "Describe " . htmlspecialchars($pagename) . " here.";
      $lastcopy = false;
      $version = 0;
   }
   
   echo WikiHeader($pagename); 
?>

<form method="POST" action="<? echo "$ScriptUrl"; ?>">
<h1><? echo $banner, " "; ?>
<input type="submit" value=" Save ">
</h1>

<textarea name="text" ROWS="22" COLS="80" wrap="virtual"><?
echo $textarea ?></textarea>
<br>

<input type="checkbox" name="convert" value="tabs" >
I can't type tabs.   Please
<a href="<? echo "$ScriptUrl"; ?>?ConvertSpacesToTabs">ConvertSpacesToTabs</a>
for me when I save.

<p>

<a href="<? echo "$ScriptUrl"; ?>?GoodStyle">GoodStyle</a>
tips for editing.

<br>

<a href="<? echo "$ScriptUrl"; ?>?links=<? echo rawurlencode($pagename); ?>">EditLinks</a>
to other web servers.

<br>

<?
   if ($lastcopy) {
      $enc_name = rawurlencode($lastcopy);
      echo "<a href='$ScriptUrl?copy=$enc_copy'>EditCopy</a>";
      echo " from previous author";
   }

?>
<input type="hidden" name="post" value="<? echo rawurlencode($pagename); ?>">
<input type="hidden" name="editversion" value="<? echo $version+0 ?>">
</form>

<? echo WikiFooter(); ?>