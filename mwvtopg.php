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

*/


array_shift($argv);

$file = array_shift($argv);

$z = new ZipArchive();
$res = $z->open($file);
if($res == true){
	$content = $z->getFromName('document.mwb.xml');
} else {
	die('unable to open mwb archive');
}


$alltables = array();

$doc = simplexml_load_string($content);

$tables = $doc->xpath('//value[@struct-name="db.mysql.Table"]');
$alltype = array();

foreach($tables as $table){
	$currtable = array();
	
	//Name And ID
	$tid = (string)$table['id'];
	$tname = $table->xpath('value[@key="name"]');
	$currtable['name'] = (string)$tname[0];
	$currtable['Id'] = $tid;
	
	//Columns
	$columns = $table->xpath('value/value[@struct-name="db.mysql.Column"]');
	foreach($columns as $col){
		$currcol = array();
		$id = (string)$col['id'];
		
		$cname = $col->xpath('value[@key="name"]');
		$currcol['name'] = (string)$cname[0];
		
		$ctype = $col->xpath('link[@key="simpleType" or @key="userType"]');
		$alltype[] = $currcol['originaltype'] = (string)$ctype[0];
		
		$cauto = $col->xpath('value[@key="autoIncrement"]');
		$currcol['autoincrement'] = (int)$cauto[0];
		
		$cnotnull = $col->xpath('value[@key="isNotNull"]');
		$currcol['notnull'] = (int)$cnotnull[0];
		
		$cdefault = $col->xpath('value[@key="defaultValue"]');
		$currcol["default"] = (string)$cdefault[0];
		
		$currcol['pgtype'] = getPgType($col);
		
		$currtable['cols'][$id] = $currcol;
	}
	
	
	//Indices
	$indices = $table->xpath('value/value[@struct-name="db.mysql.Index"]');
	foreach($indices as $idx){
		$refcols = array();
		$citype = $idx->xpath('value[@key="indexType"]');
		$itype = (string)$citype[0];
		
		$crefcol = $idx->xpath('value/value/link[@key="referencedColumn"]');
		foreach($crefcol as $ccol){
			$refcols[] = (string)$ccol;
		}
	
		if($itype == 'PRIMARY'){
			$currtable['pk']= $refcols;
 		} else {
			foreach($refcols as $rfc){
				$currtable['indices'][] = $rfc;
			}
		}
	}
	
	
	//ForeignKeys
	$fks = $table->xpath('value/value[@struct-name="db.mysql.ForeignKey"]');
	foreach($fks as $fk){
		
		$currfk = array();
		
		$creftable = $fk->xpath('link[@key="referencedTable"]');
		$currfk['reftable'] = (string)$creftable[0];
		
		$crefcol = $fk->xpath('value[@key="referencedColumns"]/link');
		$currfk['refcol'] = (string)$crefcol[0];
		
		$clocalcol = $fk->xpath('value[@key="columns"]/link');
		$currfk['localcol'] = (string)$clocalcol[0];
		
		$condelete = $fk->xpath('value[@key="deleteRule"]');
		$currfk['ondelete']  = (string)$condelete[0];
		
		$conupdate = $fk->xpath('value[@key="updateRule"]');
		$currfk['onupdate']  = (string)$conupdate[0];
		
		$cfkname = $fk->xpath('value[@key="name"]');
		$currfk['name'] = (string) $cfkname[0];
		
		$currtable['fks'][] = $currfk;
	}
	
	$alltables[$tid] = $currtable;
}

//print_r($alltables);
//natsort($alltype);
//print_r(array_unique($alltype));


$outstring = '';
foreach($alltables as $tbl){
	$outstring .= buildTable($tbl);
}

//now adding constraints 
foreach( $alltables as $tbl){
	$outstring .= addConstraints($tbl);
}

echo $outstring;

function buildTable($tbl)
{
	$tstr  = "--------------------------------------------------\n";
	$tstr .= "--Definition for Table: " . $tbl['name'] . " \n";
	$tstr .= "--------------------------------------------------\n";
	$tstr .= "CREATE TABLE {$tbl['name']} \n";
	$tstr .= "(\n";
	
	foreach($tbl['cols'] as $cidx =>  $col){
		$tstr .= indent() . $col['name'] . " " . $col['pgtype'];
		if($col['notnull']) $tstr .= " NOT NULL";
		if(!empty($col['default'])) $tstr .= " DEFAULT " . $col['default'];
		$tstr .= ",\n";
	}
	
	$pk = false;
	
	if(isset($tbl['pk']) && sizeof($tbl['pk'])){
		$pk = true;
		$tstr .= indent() . "CONSTRAINT " . $tbl['name'] . "_pk PRIMARY KEY (";
		$kstr = '';
		foreach($tbl['pk'] as $k){
			$kstr .= getColName($tbl['Id'], $k) . ',';
		}
		$kstr = substr($kstr, 0,-1);
		$tstr .= $kstr . "),";
	}
	
	$tstr = substr(trim($tstr), 0, -1);
	$tstr .= "\n)\nWITH (\n    OIDS=FALSE\n);\n\n";
	return $tstr;
	
}

function addConstraints($tbl)
{
	$fkstr = '';
	if(isset($tbl['fks']) && sizeof($tbl['fks'])){
		foreach($tbl['fks'] as $f){
			$fkstr .= buildFk($tbl['Id'], $f);
		}
	}
	return $fkstr;
	
}

function buildFk($tid, $fk)
{
	$str = "ALTER TABLE " . getTableName($tid). " ADD CONSTRAINT " . $fk['name'] . " FOREIGN KEY (" . getColName($tid,$fk['localcol']) . ")\n";
	$str .= indent().indent(). "REFERENCES " . getTableName($fk['reftable']) . " (" . getColName($fk['reftable'], $fk['refcol']) . ") MATCH SIMPLE ";
	$str .=  "ON UPDATE " . $fk['onupdate'] . " ON DELETE " . $fk['ondelete'] . ";\n";
	return $str;
}

function getColName($t,$k)
{
	global $alltables;
	return $alltables[$t]['cols'][$k]['name'];
}

function getTableName($t)
{
	global $alltables;
	return $alltables[$t]['name'];
}

function indent($size=4)
{
	return str_repeat(' ', $size);
}

function getPgType($xmlcol)
{
	$cauto = $xmlcol->xpath('value[@key="autoIncrement"]');
	$autoincrement = (int)$cauto[0];
	
	$ctype = $xmlcol->xpath('link[@key="simpleType" or @key="userType"]');
	$mtype = (string)$ctype[0];
	
	$clength = $xmlcol->xpath('value[@key="length"]');
	$length  = (int)$clength[0];
	
	switch($mtype){
		
		//Integers
		case "com.mysql.rdbms.mysql.datatype.int":
		case "com.mysql.rdbms.mysql.datatype.tinyint":
		case "com.mysql.rdbms.mysql.userdatatype.int2":
		case "com.mysql.rdbms.mysql.userdatatype.int4":
		case "com.mysql.rdbms.mysql.userdatatype.int8":
			$type = 'integer';
			if($autoincrement){
				$type = 'serial';
			}
			break;
		
		case "com.mysql.rdbms.mysql.datatype.bigint":
			$type ="bigint";
			if($autoincrement){
				$type = 'bigserial';
			}
			break;

		//Timestamps
		case "com.mysql.rdbms.mysql.datatype.datetime":
		case "com.mysql.rdbms.mysql.datatype.timestamp":
			$type = "timestamp without time zone";
			break;

		//Date
		case "com.mysql.rdbms.mysql.datatype.date":
			$type = "date";
			break;
		//Time
		case "com.mysql.rdbms.mysql.datatype.time":
			$type = "time without time zone";
			break;
			
		//Blobs
		case "com.mysql.rdbms.mysql.datatype.blob":
		case "com.mysql.rdbms.mysql.datatype.longblob":
		case "com.mysql.rdbms.mysql.datatype.mediumblob":
		case "com.mysql.rdbms.mysql.datatype.tinyblob":
		case "com.mysql.rdbms.mysql.datatype.binary":
			$type = "bytea";
			break;
			
		
		//Booleans
		case "com.mysql.rdbms.mysql.userdatatype.bool":
		case "com.mysql.rdbms.mysql.userdatatype.boolean":
			$type = "boolean";
			break;
		
		//Numerics
		case "com.mysql.rdbms.mysql.userdatatype.numeric":
		case "com.mysql.rdbms.mysql.datatype.decimal":
			
			$cprecision = $xmlcol->xpath('value[@key="precision"]');
			$precision  = (int)$cprecision[0];
			$cscale 	= $xmlcol->xpath('value[@key="scale"]');
			$scale 		= (int)$cscale[0];
			$type = "numeric($precision,$scale)";
			break;
			
		//Text
		case "com.mysql.rdbms.mysql.datatype.text":
		case "com.mysql.rdbms.mysql.datatype.tinytext":
		case "com.mysql.rdbms.mysql.datatype.mediumtext":
		case "com.mysql.rdbms.mysql.datatype.longtext":
			$type = "text";
			break;
			
		//Varchar
		case "com.mysql.rdbms.mysql.datatype.varchar":
			$type = "character varying($length)";
			break;
			
		//Char
		case "com.mysql.rdbms.mysql.datatype.char":
			$type = "char";
			
			break;
			
		default:
			die('unknown type: ' . $mtype);
	}
	
	return $type;
	
}

?>