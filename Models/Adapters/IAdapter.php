<?php

interface IAdapter
{
	public function getType(MwbColumn $c);
	
	public function getSql(MwbParser $p);
}
?>