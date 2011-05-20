<?php

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the Lesser GNU General Public License as published by
the Free Software Foundation, either version 2.1 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
Lesser GNU General Public License for more details.

You should have received a copy of the Lesser GNU General Public License
along with this program. If not, see < http://www.gnu.org/licenses/>.

@author: spotkine@hotmail.com
@since: 2011/05/17

*/
require_once dirname(__FILE__) . "/Models/MwbParser.php";
require_once dirname(__FILE__) . "/Models/Adapters/Pgsql.php";

array_shift($argv);
$file = array_shift($argv);

$z = new ZipArchive();
$res = $z->open($file);
if($res == true){
	$content = $z->getFromName('document.mwb.xml');
} else {
	die('unable to open mwb archive');
}
$xml = simplexml_load_string($content);

$adapter = new Adapters_Pgsql();
$p = new MwbParser($xml, $adapter);
$p->parse();
echo $p->getSqlDefinition();

?>