<?php 

/* 

  RichTablePlugin
  A PhpWiki plugin that allows insertion of tables using a richer syntax
  http://www.it.iitb.ac.in/~sameerds/phpwiki/index.php/RichTablePlugin
 
  Copyright (C) 2003 Sameer D. Sahasrabuddhe
  
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

*/

rcs_id('$Id: RichTable.php,v 1.1 2004-01-27 12:23:28 rurban Exp $');

error_reporting (E_ALL & ~E_NOTICE);

class WikiPlugin_RichTable
extends WikiPlugin
{
    function getName() {
        return _("RichTable");
    }

    function getDescription() {
      return _("Layout tables using a very rich markup style.");
    }

    function getDefaultArguments() {
        return array();
    }

    function getVersion() {
        return preg_replace("/[Revision: $]/", '',
                            "\$Revision: 1.1 $");
    }

	function run($dbi, $argstr, $request) {

    	global $Theme;

        $lines = preg_split('/\n/', $argstr);
        $table = HTML::table();
 
		if ($lines[0][0] == '*') {
			$line = substr(array_shift($lines),1);
			$attrs = $this->_parse_attr($line);
			foreach ($attrs as $key => $value) {
				if (in_array ($key, array("id", "class", "title", "style",
								"bgcolor", "frame", "rules", "border",
								"cellspacing", "cellpadding",
								"summary", "align", "width"))) {
					$table->setAttr($key, $value);
				}
			}
		}

		foreach ($lines as $line){
			if ($line[0] == "-") {
				if (isset($row)) {
					if (isset($cell)) {
						if (isset($content)) {
							$cell->pushContent(TransformText($content));
							unset($content);
						}
						$row->pushContent($cell);
						unset($cell);
					}
					$table->pushContent($row);
				}	
				$row = HTML::tr();
				$attrs = $this->_parse_attr(substr($line,1));
				foreach ($attrs as $key => $value) {
					if (in_array ($key, array("id", "class", "title", "style",
										"bgcolor", "align", "valign"))) {
						$row->setAttr($key, $value);
					}
				}
				continue;
			}
			if ($line[0] == "|" and isset($row)) {
				if (isset($cell)) {
					if (isset ($content)) {
						$cell->pushContent(TransformText($content));
						unset($content);
					}
					$row->pushContent($cell);
				}
				$cell = HTML::td();
				$line = substr($line, 1);
				if ($line[0] == "*" ) {
					$attrs = $this->_parse_attr(substr($line,1));
					foreach ($attrs as $key => $value) {
						if (in_array ($key, array("id", "class", "title", "style",
											"colspan", "rowspan", "width", "height",
											"bgcolor", "align", "valign"))) {
							$cell->setAttr($key, $value);
						}
					}
					continue;
				} 
			} 
			if (isset($row) and isset($cell)) {
				$line = str_replace("?\>", "?>", $line);
				$line = str_replace("\~", "~", $line);
				$content .= $line . "\n";
			}
		}
		if (isset($row)) {
			if (isset($cell)) {
				if (isset($content))
					$cell->pushContent(TransformText($content));
				$row->pushContent($cell);
			}
			$table->pushContent($row);
		}

		return $table;
	}

	function _parse_attr($line) {

		$attr_chunks = preg_split("/\s*,\s*/", strtolower($line));
		foreach ($attr_chunks as $attr_pair) {
			$key_val = preg_split("/\s*=\s*/", $attr_pair);
			$options[trim($key_val[0])] = trim($key_val[1]);
		}
		return $options;
	}
};

?>
