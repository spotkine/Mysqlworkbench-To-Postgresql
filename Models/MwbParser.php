<?php

require_once dirname(__FILE__) . '/MwbElements.php';
require_once dirname(__FILE__) . '/Adapters/IAdapter.php';

Class MwbParser
{
	private $xml;
	
	private $tables = array();
	
	private $adapter;
	
	public function __construct( SimpleXMLElement $xml, IAdapter $adapter)
	{
		$this->xml = $xml;	
		$this->adapter = $adapter;
	}
	
	/**
	 * Main Job
	 *
	 * @return void
	 * @author xav
	 */
	public function parse()
	{
		$tables = $this->xml->xpath('//value[@struct-name="db.mysql.Table"]');
		if(!is_array($tables)){
			throw new Exception('Unable to extact tables informations from Xml');
		}
		
		foreach($tables as $tbl){
			$t =  $this->parseTable($tbl);
			$this->tables[$t->Id] = $t;
		}
	}
	
	
	public function getSqlDefinition()
	{
		return $this->adapter->getSql($this);
	}
	
	
	public function getTables()
	{
		return $this->tables;
	}
	
	
	/**
	 * Parsing an indiv table
	 *
	 * @param string $tbl 
	 * @return void
	 * @author xav
	 */
	private function parseTable(SimpleXMLElement $tbl)
	{
		$table = new MwbTable();
		$table->Id = (string)$tbl['id'];
		$table->name = self::getSingleElementValue($tbl, 'value[@key="name"]');
		
		//Buiding Columns
		$colElements = $tbl->xpath('value/value[@struct-name="db.mysql.Column"]');
		if(!is_array($colElements)){
			throw new Exception('Unable to find Columns definitions for table: ' . $table->name);
		}
		foreach($colElements as $col){
			$parsedColumn = $this->parseColumn($col);
			$table->columns[$parsedColumn->Id] = $parsedColumn;
		}
		
		//Building indices
		$indices = $tbl->xpath('value/value[@struct-name="db.mysql.Index"]');
		if(is_array($indices)){
			foreach($indices as $idx){
				$itype = self::getSingleElementValue($idx, 'value[@key="indexType"]');
				
				$crefcol = $idx->xpath('value/value/link[@key="referencedColumn"]');
				foreach($crefcol as $ccol){
					$refcols[] = (string)$ccol;
				}

				if($itype == 'PRIMARY'){
					$table->primaryKeys = $refcols;
		 		} else {
					foreach($refcols as $rfc){
						$table->indices[] = $rfc;
					}
				}
			}
		}
		
		
		//Building foreignKeys
		$fks = $tbl->xpath('value/value[@struct-name="db.mysql.ForeignKey"]');
		if(is_array($fks)){
			foreach($fks as $fk){
				$table->foreignKeys[] = $this->parseFk($fk);
			}
		}
		
		return $table;
	}
	
	private function parseFk(SimpleXMLElement $f)
	{
		$fk = new MwbForeignKey();
		$fk->name = self::getSingleElementValue($f, 'value[@key="name"]');
		$fk->reftable = self::getSingleElementValue($f, 'link[@key="referencedTable"]');
		$fk->refcol = self::getSingleElementValue($f, 'value[@key="referencedColumns"]/link');
		$fk->localcol = self::getSingleElementValue($f, 'value[@key="columns"]/link');
		$fk->ondelete = self::getSingleElementValue($f, 'value[@key="deleteRule"]');
		$fk->onupdate = self::getSingleElementValue($f, 'value[@key="updateRule"]');
		
		return $fk;
	}
	
	/**
	 * Creating an indiv column
	 *
	 * @param SimpleXMLElement $c 
	 * @return MwbColumn $col
	 * @author xav
	 */
	private function parseColumn(SimpleXMLElement $c)
	{
		$col = new MwbColumn();
		$col->Id = (string)$c['id'];
		$col->name = self::getSingleElementValue($c, 'value[@key="name"]');
		$col->nativeType = self::getSingleElementValue($c, 'link[@key="simpleType" or @key="userType"]');
		$col->autoIncrement = self::getSingleElementValue($c, 'value[@key="autoIncrement"]');
		$col->notNull = self::getSingleElementValue($c, 'value[@key="isNotNull"]');
		$col->default = self::getSingleElementValue($c, 'value[@key="defaultValue"]');
		$col->length   = self::getSingleElementValue($c, 'value[@key="length"]', 'int');
		$col->precision= self::getSingleElementValue($c,'value[@key="precision"]', 'int');
		$col->scale = self::getSingleElementValue($c, 'value[@key="scale"]', 'int');
		
		$col->targetType = $this->getTargetType($col);
		
		return $col;
	}
	
	
	/**
	 * Native MysqlWorkBench type parser
	 *
	 * @param MwbColumn $col 
	 * @return string $type
	 * @author xav
	 */
	private function getTargetType(MwbColumn $col)
	{
		return $this->adapter->getType($col);
	}
	
	
	/**
	 * Helper function to get back an xpath filtered value
	 *
	 * @param SimpleXMLElement $e 
	 * @param string $xpath 
	 * @param string $type 
	 * @return mixed
	 * @author xav
	 */
	public static function getSingleElementValue(SimpleXMLElement $e, $xpath, $type = 'string')
	{
		$tmp = $e->xpath($xpath);
		if(!is_array($tmp)){
			throw new Exception('Unable to find a valid xpath result for element');
		}
		
		$val = $tmp[0];
		switch($type){
			
			case 'int':
				return (int)$val;
				break;
				
			case 'bool':
				return (bool)$val;
				break;
				
			default:
				return (string)$val;
				
		}
	}
	
}
?>