<?php // -*-php-*-
rcs_id('$Id: PluginManager.php,v 1.2 2002-12-30 23:49:35 carstenklapp Exp $');
/**
 */

class WikiPlugin_PluginManager
extends WikiPlugin
{
    function getName () {
        return _("PluginManager");
    }

    function getDescription () {
        return _("Overview of the available PhpWikiPlugins");
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.2 $");
    }

    function getDefaultArguments() {
        return array();
    }

    function run($dbi, $argstr, $request) {
        //extract($this->getArgs($argstr, $request));

	$msg = HTML::p("PluginManager provides the WikiAdmin the list of PhpWikiPlugin~s on this wiki.");

        if (! $request->_user->isadmin()) {
            return $msg; // early return
        }


        $pd = new fileSet(PHPWIKI_DIR . '/lib/plugin', '*.php');
        $plugins = $pd->getFiles();

        $h = HTML();
        $h->pushContent($msg);
        $h->pushContent(HTML::h2(_("Plugins")));
        $row_no = 0;

        $table = HTML::table(array('class' => "pagelist"));
        global $WikiNameRegexp;
        foreach($plugins as $pname) {
            $pname = str_replace(".php", "", $pname);
            $temppluginclass = "<? plugin " . /*"WikiPlugin_" .*/ $pname . " ?>";
            $w = new WikiPluginLoader;
            $p = $w->getPlugin($pname);
            $desc = $p->getDescription();
            if (method_exists($p, 'getVersion')) {
                $ver = $p->getVersion();
            }
            else {
                $ver = "--";
            }

            $pnamelink = $pname;
            $plink = false;
            if (preg_match("/^$WikiNameRegexp\$/", $pname) && $dbi->isWikiPage($pname))
                $pnamelink = WikiLink($pname);

            $ppname = $pname . "Plugin";
            if (preg_match("/^$WikiNameRegexp\$/", $ppname) && $dbi->isWikiPage($ppname))
                $plink = WikiLink($ppname);
            else {
                // exclude actionpages and plugins starting with _ from page list
                if ( !preg_match("/^_/", $pname) && !(@$request->isActionPage($pname))) //FIXME
                    $plink = WikiLink($ppname, 'unknown');
                else
                    $plink = false;
            }
            $row_no++;
            $group = (int)($row_no / 2); //_group_rows
            $class = ($group % 2) ? 'oddrow' : 'evenrow';

            $tr = HTML::tr(array('class' => $class));
            if ($plink) {
                $tr->pushContent(HTML::td($plink), HTML::td($ver), HTML::td($desc));
                $tr2 = HTML::tr(array('class' => $class));
                $tr2->pushContent(HTML::td($pnamelink), HTML::td(" "), HTML::td(" "));
                $plink = false;
                $table->pushContent($tr, $tr2);
                $row_no++;
            }
            else {
                $tr->pushContent(HTML::td($pnamelink), HTML::td($ver), HTML::td($desc));
                $table->pushContent($tr);
            }
        }
        $h->pushContent($table);

        //$h->pushContent(HTML::h2(_("Disabled Plugins")));

        return $h;
    }
};

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
