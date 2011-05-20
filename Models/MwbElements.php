<?php

/**
 * Table object
 *
 * @author xav
 */
Class MwbTable
{
	public $name;
	
	public $Id;
	
	public $columns;
	
	public $primaryKeys;
	
	public $indices;
	
	public $foreignKeys;
	
}

/**
 * Column object
 *
 * @package default
 * @author xav
 */
class MwbColumn
{
	public $Id;
	
	public $name;
	
	public $autoIncrement;
	
	public $notNull;
	
	public $default;
	
	public $length;
	
	public $nativeType;
	
	public $targetType;
	
	public $precision;
	
	public $scale;
}

/**
 * ForeignKey object
 *
 * @package default
 * @author xav
 */
class MwbForeignKey
{
	public $reftable;
	
	public $refcol;
	
	public $localcol;
	
	public $ondelete;
	
	public $onupdate;
	
	public $name;
}
?>