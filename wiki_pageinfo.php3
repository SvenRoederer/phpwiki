<!-- $Id: wiki_pageinfo.php3,v 1.3 2000-06-26 20:10:05 ahollosi Exp $ -->
<!-- Display the internal structure of a page. Steve Wainstead, June 2000 -->
<?
   $encname = htmlspecialchars($info);
   $html = "<form action=\"$ScriptUrl\" METHOD=GET>\n" .
	   "<input name=\"info\" value=\"$encname\">" .
	   " Enter a page name\n" .
	   "<input type=submit value=Go><br>\n" .
	   "<input type=checkbox name=showpagesource";

   if ($showpagesource == "on") {
      $html .= " checked";
   }
   $html .= "> Show the page source and references\n</form>\n";

   // don't bother unless we were asked
   if (! $info) {
      GeneratePage('MESSAGE', $html, "PageInfo", 0);
      exit;
   }

   function ViewpageProps($name)
   {
      global $dbi, $showpagesource, $datetimeformat;

      $pagehash = RetrievePage($dbi, $name);
      if ($pagehash == -1) {
         $table = "Page name '$name' is not in the database<br>\n";
      }
      else {
	 $table = "<table border=1 bgcolor=white>\n";

	 while (list($key, $val) = each($pagehash)) {
	    if ($key > 0 || !$key) #key is an array index
	       continue;
            if ((gettype($val) == "array") && ($showpagesource == "on")) {
               $val = implode($val, "$FieldSeparator#BR#$FieldSeparator\n");
	       $val = htmlspecialchars($val);
	       $val = str_replace("$FieldSeparator#BR#$FieldSeparator", "<br>", $val);
            }
	    elseif (($key == 'lastmodified') || ($key == 'created'))
	       $val = date($datetimeformat, $val);
	    else
	       $val = htmlspecialchars($val);

            $table .= "<tr><td>$key</td><td>$val</td></tr>\n";
	 }

	 $table .= "</table>";
      }
      return $table;
   }

   $html .= "<P><B>Current version</B></p>";
   // $dbi = OpenDataBase($WikiDataBase);   --- done by index.php3
   $html .= ViewPageProps($info);

   $html .= "<P><B>Archived version</B></p>";
   $dbi = OpenDataBase($ArchiveDataBase);
   $html .= ViewPageProps($info);

   GeneratePage('MESSAGE', $html, "PageInfo: '$info'", 0);
?>
