<?php // -*-php-*-
rcs_id('$Id: CalendarList.php,v 1.3 2004-09-22 13:36:45 rurban Exp $');

if (!defined('SECONDS_PER_DAY'))
define('SECONDS_PER_DAY', 24 * 3600);

/**
 * This is a list of calendar appoinments. 
 * Same arguments as Calendar, so no one is confused
 * Uses <dl><dd>DATE<dt>page contents...
 * Derived from Calendar.php by Martin Norb�ck <martin@safelogic.se>
 *
 * Insert this plugin into your Calendar page, for example in:
 *     WikiUser/Calendar
 * Add the line: <?plugin CalendarList ?>
 *
 */
class WikiPlugin_CalendarList
extends WikiPlugin
{
    function getName () {
        return _("CalendarList");
    }

    function getDescription () {
        return _("CalendarList");
    }

    function getDefaultArguments() {
        return array('prefix'       => '[pagename]',
                     'date_format'  => '%Y-%m-%d',
                     'year'         => '',
                     'month'        => '',
                     'month_offset' => 0,
                     //support ranges, based on a simple patch by JoshWand
                     'next_n_days'  => '',
                     'last_n_days'  => '',
                     // next or last n entries:
                     'next_n'  => '',
                     //'last_n'  => '', // not yet

                     'month_format' => '%B, %Y',
                     'wday_format'  => '%a',
                     'start_wday'   => '0');
    }

    function __date($dbi, $time) {
        $args = &$this->args;
        $date_string = strftime($args['date_format'], $time);

        $page_for_date = $args['prefix'] . SUBPAGE_SEPARATOR . $date_string;
        $t = localtime($time, 1);

        $td = HTML::td(array('align' => 'center'));

        if ($dbi->isWikiPage($page_for_date)) {
            // Extract the page contents for this date
            $p = $dbi->getPage($page_for_date);
            $r = $p->getCurrentRevision();
            $c = $r->getContent();
            include_once('lib/BlockParser.php');
            $content = TransformText(implode("\n", $c), $r->get('markup'));
            $link = HTML::a(array('class' => 'cal-hide',
                                  'href'  => WikiURL($page_for_date,
                                                     array('action' => 'edit')),
                                  'title' => sprintf(_("Edit %s"), $page_for_date)),
                            $date_string);
            $a = array(HTML::dt($link), HTML::dd($content));
        } else {
          $a = array();
        }
        return $a;
    }

    function run($dbi, $argstr, &$request, $basepage) {
        $this->args = $this->getArgs($argstr, $request);
        $args       = &$this->args;

        $now = localtime(time() + 3600 * $request->getPref('timeOffset'), 1);
        foreach ( array('month' => $now['tm_mon'] + 1,
                        'year'  => $now['tm_year'] + 1900)
                  as $param => $dflt ) {

            if (!($args[$param] = intval($args[$param])))
                $args[$param]   = $dflt;
        }
        if ($args['last_n_days']) {
            $time = mktime(0, 0, 0,                            // hh, mm, ss,
                           $args['month'] + $args['month_offset'], // month (1-12)
                           $now['tm_mday'] - $args['last_n_days'],
                           $args['year']);
        } elseif ($args['next_n_days'] || $args['next_n']/* || $args['last_n']*/) {
            $time = mktime(0, 0, 0,                            // hh, mm, ss,
                           $args['month'] + $args['month_offset'], // month (1-12)
                           $now['tm_mday'] ,                   // starting today
                           $args['year']);
        } else {
            $time = mktime(12, 0, 0,                           // hh, mm, ss,
                           $args['month'] + $args['month_offset'], // month (1-12)
                           1,                                   // starting at monday
                           $args['year']);
        } 
        $t = localtime($time, 1);

        if ($now['tm_year'] == $t['tm_year'] && $now['tm_mon'] == $t['tm_mon'])
            $this->_today = $now['tm_mday'];
        else
            $this->_today = false;

        $cal = HTML::dl();

        $done = false;
        $n = 0; $max = (180 * SECONDS_PER_DAY) + $time;
        while (!$done) {
            $success = $cal->pushContent($this->__date($dbi, $time));
            $time += SECONDS_PER_DAY;
            if ($time >= $max) return $cal;

            $t     = localtime($time, 1);
            if ($args['next_n_days']) {
                if ($n == $args['next_n_days']) return $cal;
                $n++;
            } elseif ($args['next_n']) {
                if ($n == $args['next_n']) return $cal;
                if (!empty($success))
                    $n++;
            } elseif ($args['last_n_days']) {
                $done = ($t['tm_mday'] == $now['tm_mday']);
            } else {
                $done = ($t['tm_mday'] == 1);
            }
        }
        return $cal;
    }
};


// $Log: not supported by cvs2svn $
// Revision 1.2  2004/02/17 12:11:36  rurban
// added missing 4th basepage arg at plugin->run() to almost all plugins. This caused no harm so far, because it was silently dropped on normal usage. However on plugin internal ->run invocations it failed. (InterWikiSearch, IncludeSiteMap, ...)
//
// Revision 1.1  2003/11/18 19:06:03  carstenklapp
// New plugin to be used in conjunction with the Calendar plugin.
// Upgraded to use SUBPAGE_SEPARATOR for subpages. SF patch tracker
// submission 565369.
//


// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
