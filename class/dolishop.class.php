<?php

if (!class_exists('SeedObject'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class dolishop extends SeedObject
{
	public $table_element = 'dolishop';

	public $element = 'dolishop';
	
	public function __construct($db)
	{
		global $conf,$langs;
		
		$this->db = $db;
		
//		$this->fields=array(
//				'ref'=>array('type'=>'string','length'=>50,'index'=>true)
//				,'label'=>array('type'=>'string')
//				,'status'=>array('type'=>'integer','index'=>true) // date, integer, string, float, array, text
//				,'entity'=>array('type'=>'integer','index'=>true)
//		);
		
		$this->init();
	}
}