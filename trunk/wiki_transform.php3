<?
   $enc_name = rawurlencode($pagename);
   $html = "";

   $html .= WikiHeader($pagename);

   $html .= "<h1>$LogoImage ";
   $html .= "<a href=\"$ScriptUrl?full=$enc_name\">$pagename</a></h1>\n";

   $pagehash = RetrievePage($dbi, $pagename);
   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write
      // one.

      // Set up inline links and images
      for ($i = 1; $i < (NUM_LINKS + 1); $i++) {
         $thiskey = "r" . $i;
         if (! empty($pagehash[$thiskey])) {
            if (preg_match("/png$/i", $pagehash[$thiskey])) {
               // embed PNG images
               $embedded[$i] = "<img src='" . $pagehash[$thiskey] . "'>";
            } else {
               // ordinary embedded link
               $embedded[$i] = "<a href='" . $pagehash[$thiskey] . "'>[$i]</a>";
            }
         }
      }

      $numlines = count($pagehash["text"]);

      // Loop over all lines of the page and apply transformation rules
      for ($index = 0; $index < $numlines; $index++) {
         $tmpline = $pagehash["text"][$index];

         if (!strlen($tmpline) || $tmpline == "\r") {
            // this is a blank line, send <p>
            $html .= SetHTMLOutputMode("p", ZERO_DEPTH, 0);
            continue;
         }

/* If your web server is not accessble to the general public, you may
allow this code below, which allows embedded HTML. If just anyone can reach
your web server it is highly advised that you do not allow this.

         elseif (preg_match("/(^\|)(.*)/", $tmpline, $matches)) {
            // HTML mode
            $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
            $html .= $matches[2];
            continue;
         }
*/

         
         //////////////////////////////////////////////////////////
         // New linking scheme: links are in brackets. This will
         // emulate typical HTML linking as well as Wiki linking.

         // match anything between brackets except only numbers
         // trying: 
         $numBracketLinks = preg_match_all("/\[.+?\]/", $tmpline, $brktlinks);
         rsort($brktlinks[0]);
         reset($brktlinks[0]);
         

         for ($i = 0; $i < $numBracketLinks; $i++) {
            $brktlink = preg_quote($brktlinks[0][$i]);
            $linktoken = "${FieldSeparator}brkt${i}brkt${FieldSeparator}";
            $tmpline = preg_replace("|$brktlink|",
                                    $linktoken,
                                    $tmpline);
         }
         //////////////////////////////////////////////////////////


         // replace all URL's with tokens, so we don't confuse them
         // with Wiki words later. Wiki words in URL's break things.
         // This used to follow the HTML escaping and dashes code 
         // that follows.

         $hasURLs = preg_match_all("/\b((http)|(ftp)|(mailto)|(news)|(file)|(gopher)):[^\s\<\>\[\]\"'\(\)]*[^\s\<\>\[\]\"'\(\)\,\.\?]/", $tmpline, $urls);

         // workaround: php can only do global search and replace which
         // renders wrong when the domain appears in two consecutive URL's 
         // on the same line, but the second is longer i.e. 
         // http://c2.com followed by http://c2.com/wiki 
         rsort($urls[0]);
         reset($urls[0]);

         for ($i = 0; $i < $hasURLs; $i++) {
            $inplaceURL = preg_quote($urls[0][$i]);
            $URLtoken = "${FieldSeparator}${i}${FieldSeparator}";
            $tmpline = preg_replace("|$inplaceURL|",
                                    $URLtoken,
                                    $tmpline);
         }

         // escape HTML metachars
         $tmpline = ereg_replace("[&]", "&amp;", $tmpline);
         $tmpline = ereg_replace("[>]", "&gt;", $tmpline);
         $tmpline = ereg_replace("[<]", "&lt;", $tmpline);

         // four or more dashes to <hr>
         $tmpline = ereg_replace("^-{4,}", "<hr>", $tmpline);


         // bold italics
         $tmpline = preg_replace("|(''''')(.*?)(''''')|",
                                 "<strong><em>\\2</em></strong>",
                                 $tmpline);

         // bold
         $tmpline = preg_replace("|(''')(.*?)(''')|",
                                 "<strong>\\2</strong>",
                                 $tmpline);

         // italics
         $tmpline = preg_replace("|('')(.*?)('')|",
                                 "<em>\\2</em>",
                                 $tmpline);

         // Link Wiki words
         if (preg_match_all("#\b(([A-Z][a-z]+){2,})\b#",
                            $tmpline, 
                            $link)) {

            // uniq the list of matches
            $hash = "";
            for ($i = 0; $link[0][$i]; $i++) {
               // $realfile = $link[0][$i];
               $hash[$link[0][$i]]++;
            }

            reset($hash);
            while (list($realfile, $val) = each($hash)) {
               if (IsWikiPage($dbi, $realfile)) {
                  $tmpline = preg_replace("|\b$realfile\b|",
                              LinkExistingWikiWord($realfile),
                              $tmpline);
               } else {
                  $tmpline = preg_replace("|\b$realfile\b|",
                              LinkUnknownWikiWord($realfile),
                              $tmpline);
               }
            }

         }

         ///////////////////////////////////////////////////////
         // put bracketed links back, linked
         for ($i = 0; $i < $numBracketLinks; $i++) {
            // pass this link to a function for hyperlinking
            // forms: [free style text link]
            //        [Named link to site|http://c2.com/]
            //        [mailto:anystylelink@somewhere.com]
            $brktlink = ParseAndLink($brktlinks[0][$i]);
            $linktoken = "${FieldSeparator}brkt${i}brkt${FieldSeparator}";
            $tmpline = preg_replace("|$linktoken|", 
                                    $brktlink,
                                    $tmpline);
         }
         ///////////////////////////////////////////////////////


         // put URLs back, linked
         for ($i = 0; $i < $hasURLs; $i++) {
            $inplaceURL = LinkURL($urls[0][$i]);
            $URLtoken = "${FieldSeparator}${i}${FieldSeparator}";
            $tmpline = preg_replace("|$URLtoken|", 
                                    $inplaceURL,
                                    $tmpline);
         }


         // Insert search boxes, if needed
         $tmpline = ereg_replace("\[Search]", RenderQuickSearch(), $tmpline);
         $tmpline = ereg_replace("\[Fullsearch]", RenderFullSearch(), $tmpline);

         // match and replace all user-defined links ([1], [2], [3]...)
         preg_match_all("|\[(\d+)\]|", $tmpline, $match);
         if (count($match[0])) {
            for ($k = 0; $k < count($match[0]); $k++) {
               if (! empty($embedded[$match[1][$k]])) {
                  $linkpattern = preg_quote($match[0][$k]);
                  $tmpline = preg_replace("|$linkpattern|",
                                          $embedded[$match[1][$k]],
                                          $tmpline);
               }
            }
         }

         // HTML modes: pre, unordered/ordered lists, term/def
         if (preg_match("/(^\t)(.*?)(:\t)(.*$)/", $tmpline, $matches)) {
            // this is a dictionary list item
            $html .= SetHTMLOutputMode("dl", SINGLE_DEPTH, 1);
            $tmpline = "<dt>" . $matches[2] . "<dd>" . $matches[4];

         // oops, the \d needed to be \d+, thanks alister@minotaur.nu
         } elseif (preg_match("/(^\t+)(\*|\d+|#)/", $tmpline, $matches)) {
            // this is part of a list
            $numtabs = strlen($matches[1]);
            if ($matches[2] == "*") {
               $listtag = "ul";
            } else {
               $listtag = "ol"; // a rather tacit assumption. oh well.
            }
            $tmpline = preg_replace("/^(\t+)(\*|\d+|#)/", "", $tmpline);
            $html .= SetHTMLOutputMode($listtag, SINGLE_DEPTH, $numtabs);
            $html .= "<li>";

         } elseif (preg_match("/^\s+/", $tmpline)) {
            // this is preformatted text, i.e. <pre>
            $html .= SetHTMLOutputMode("pre", ZERO_DEPTH, 0);

         } else {
            // it's ordinary output if nothing else
            $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
         }

         $html .= "$tmpline"; // at last, emit the code
      }

   } else {
      $html .= "Describe $pagename<a href='$ScriptUrl?edit=$enc_name'>?</a> here.\n";
   }

   $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
   $html .= WikiToolBar();
   $html .= WikiFooter();
?>

