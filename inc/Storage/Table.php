<?php
	/**
	* Database Table Object.
	* 
	* @package Leviathan
	* @subpackage database
	* @version 2.0.0
	* @copyright 2004-2012 Philipe Rubio / Koppin22 Media DA
	* @author Philipe Rubio
	* @link http://leviathan.koppin22.com
	*
	* Class Public Functions:
	*	check()
	*	get()
	*	set()
	*	reset()
	*	delete()
	*	load()
	*	save()
	*	count()
	*	getProperties()
	*/
	
class Table{
	protected $_db 		= null;
	protected $_tbl 	= '';
	protected $_tbl_key = '';
	
	/**
	 * Object constructor to set table and key field
	 *
	 * Can be overloaded/supplemented by the child class
	 * @param string $table name of the table in the db schema relating to child class
	 * @param string $key name of the primary key field in the table
	 * @param array $properties an array containing the properties you want to add to the object
	 */
	public function __construct($table, $key, $properties=null){
		$this->_tbl 	= $table;
		$this->_tbl_key = $key;
		$this->_db 		=& Factory::getDBO();
		
		if(is_array($properties)){
			foreach($properties as $k => $v){
				$this->set($k, $v);
			}
		}
	}
	
	/**
	 * This function should be overloaded to check/verify data before a save.
	 *
	 * @return unknown
	 */
	public function check(){
		return true;
	}
	
	/**
	 * Return a previously set property.
	 *
	 * @return mixed return the requested property or null if it does not exsist.
	 */
	public function get($_property){
		if(isset($this->$_property)){
			return $this->$_property;
		}else{
			return null;
		}
	}
	
	/**
	 * Sets a property/field
	 */
	public function set($_property, $_value){
		$this->$_property = $_value;
	}
	
	/**
	 * Sets all class vars to null
	 */
	public function reset(){
		$p = $this->getProperties();
		foreach($p as $k){
			$this->$k = null;
		}
	}

	/**
	 * Default delete method
	 *
	 * Can be overloaded/supplemented by the child class
	 * @return true if successful otherwise return false
	 */
	public function delete($oid=null){
		$k = $this->_tbl_key;
		if($oid){
			$this->$k = (int)$oid;
		}

		if($this->_db->query("DELETE FROM $this->_tbl" . "\n WHERE $this->_tbl_key='". $this->$k ."'")){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Loads an object from database and adds its properties to this object.
	 *
	 * @param int $oid optional argument, if not specifed then the value of current key is used
	 * @return any result from the database operation
	 */
	public function load($oid=null){
		$k = $this->_tbl_key;

		if($oid !== null){
			$this->$k = $oid;
		}

		$oid = $this->$k;

		if($oid === null){
			return false;
		}

		// Reset the object so we don't end up with mixed data sources.
		$this->reset();

		$this->_db->setQuery("SELECT *"."\n FROM $this->_tbl"."\n WHERE $this->_tbl_key = '$oid'");

		return $this->_db->loadObject($this);
	}
	
	/**
	 * Inserts a new row if id is zero or updates an existing row in the database table
	 *
	 * @param boolean If false, null object variables are not updated
	 * @returns TRUE if completely successful, FALSE if partially or not succesful
	 */
	public function save($updateNulls=false){
		// Check/Verify data before save.
		if(!$this->check()){
			return false;
		}
		
		$k = $this->_tbl_key;

		if($this->$k){
			$ret = $this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		}else{
			$ret = $this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}
		if(!$ret){
			return false;
		}else{
			return true;
		}
	}
	
	/**
	 * Get the total number of entries in this table.
	 *
	 * @return int Number of entries in table.
	 */
	public function count(){
		$this->_db->setQuery("SELECT COUNT(".$this->_tbl_key.")" . "\n FROM " . $this->_tbl);
		return (int)$this->_db->loadResult();
	}
	
	/**
	 * Returns an array of public properties
	 * @return array
	 */
	public function getProperties(){
		$cache = array();
		foreach(get_class_vars(get_class($this)) as $key => $val){
			// We do not want to return _tbl, _tbl_key and _db.
			if(substr($key, 0, 1) != '_'){
				$cache[] = $key;
			}
		}
		return $cache;
	}
}
?>