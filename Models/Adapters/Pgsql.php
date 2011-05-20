<?php
require_once dirname(__FILE__) . '/IAdapter.php';


class Adapters_Pgsql implements IAdapter
{
	private $tables;
	
	private $outstring = '';
	
	public function getSql(MwbParser $parser)
	{
		$this->tables = $parser->getTables();
;
		foreach($this->tables as $t)
		{
			$this->buildTable($t);
		}
		
		$this->addConstraints();
		
		return $this->outstring;
	}
	
	private function buildTable($tbl)
	{
		$tstr  = "--------------------------------------------------\n";
		$tstr .= "--Definition for Table: " . $tbl->name . " \n";
		$tstr .= "--------------------------------------------------\n";
		$tstr .= "CREATE TABLE {$tbl->name} \n";
		$tstr .= "(\n";

		foreach($tbl->columns as $cidx =>  $col){
			$tstr .= self::indent() . $col->name . " " . $col->targetType;
			if($col->notNull) $tstr .= " NOT NULL";
			if(!empty($col->default)) $tstr .= " DEFAULT " . $col->default;
			$tstr .= ",\n";
		}

		$pk = false;

		if(is_array($tbl->primaryKeys) && sizeof($tbl->primaryKeys)){
			$pk = true;
			$tstr .= self::indent() . "CONSTRAINT " . $tbl->name . "_pk PRIMARY KEY (";
			$kstr = '';
			foreach($tbl->primaryKeys as $k){
				$kstr .= $this->getColName($tbl->Id, $k) . ',';
			}
			$kstr = substr($kstr, 0,-1);
			$tstr .= $kstr . "),";
		}

		$tstr = substr(trim($tstr), 0, -1);
		$tstr .= "\n)\nWITH (\n    OIDS=FALSE\n);\n\n";
		
		$this->outstring .= $tstr;
	}
	
	private function addConstraints()
	{
		foreach($this->tables as $tbl){
			if(isset($tbl->foreignKeys) && sizeof($tbl->foreignKeys)){
				foreach($tbl->foreignKeys as $fk){
					$str = "ALTER TABLE " . $this->getTableName($tbl->Id). " ADD CONSTRAINT " . $fk->name . " FOREIGN KEY (" . $this->getColName($tbl->Id,$fk->localcol) . ")\n";
					$str .= self::indent(8). "REFERENCES " . $this->getTableName($fk->reftable) . " (" . $this->getColName($fk->reftable, $fk->refcol) . ") MATCH SIMPLE ";
					$str .=  "ON UPDATE " . $fk->onupdate . " ON DELETE " . $fk->ondelete . ";\n";
					
					$this->outstring .= $str;
				}
			}
		}
	}


	private function getColName($t,$k)
	{
		return $this->tables[$t]->columns[$k]->name;
	}

	private function getTableName($t)
	{
		return $this->tables[$t]->name;
	}
	
	
	public function getType(MwbColumn $col)
	{
		switch($col->nativeType){

			//Integers
			case "com.mysql.rdbms.mysql.datatype.int":
			case "com.mysql.rdbms.mysql.datatype.tinyint":
			case "com.mysql.rdbms.mysql.userdatatype.int2":
			case "com.mysql.rdbms.mysql.userdatatype.int4":
			case "com.mysql.rdbms.mysql.userdatatype.int8":
				$type = 'integer';
				if($col->autoIncrement){
					$type = 'serial';
				}
				break;

			case "com.mysql.rdbms.mysql.datatype.bigint":
				$type ="bigint";
				if($col->autoIncrement){
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
				$type = "numeric({$col->precision},{$col->scale})";
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
				$type = "character varying({$col->length})";
				break;

			//Char
			case "com.mysql.rdbms.mysql.datatype.char":
				$type = "char";

				break;

			default:
				die('unknown type: ' . $col->nativeType);
		}

		return $type;
	}
	
	
	public static function indent($size = 4)
	{
		return str_repeat(' ', $size);
	}
}

?>