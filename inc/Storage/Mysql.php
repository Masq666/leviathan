<?php

	/**
	* Leviathan: The Ultra Speedy Content Management System
	*
	* MySQL Database Abstraction Layer
	*
	* Copyright (c) 2008-2009 by Philipe Rubio / Koppin22 Media DA
	* All rights reserved.
	*
	* This program may not be redistributed, reproduced or modified
	* without the written permission from Philipe Rubio.
	*
	* @package Leviathan
	* @subpackage Database
	* @version 2.0.0
	* @copyright 2008-2009 Philipe Rubio / Koppin22 Media DA
	* @author Philipe Rubio
	* @link http://koppin22.com
	*/

defined('_KO22_VALID') or die('Restricted access');
//require_once(P_INCLUDE.'/Interfaces/iDatabase.php');
require_once('iDatabase.php');

class Database implements iDatabase{
	public 	$_sql			= '';			// @var string Internal variable to hold the query sql
	public 	$_errorNum		= 0;			// @var int Internal variable to hold the database error number
	public 	$_errorMsg		= '';			// @var string Internal variable to hold the database error message
	public 	$_table_prefix	= '';			// @var string Internal variable to hold the prefix used on all database tables
	private $_resource		= '';			// @var Internal variable to hold the connector resource
	private $_cursor		= NULL;			// @var Internal variable to hold the last query cursor
	public 	$_debug			= 1;			// @var boolean Debug option
	public 	$_limit			= 0;			// @var int The limit for the query
	public 	$_offset		= 0;			// @var int The for offset for the limit
	public 	$_ticker		= 0;			// @var int A counter for the number of queries performed by the object instance
	public 	$_log			= array();		// @var array A log of queries
	public 	$_nullDate		= '0000-00-00 00:00:00'; // @var string The null/zero date string
	public 	$_nameQuote		= '`';			// @var string Quote for named objects

	/**
	 * Database object constructor
	 *
	 * @param array $cfg Array containing config variables.
	 * @param bool Show error messages.
	 */
	function __construct($cfg, $goOffline=true){
		// Use Persistent Connection to the DB?
		if($cfg['db_persistent'] != 0){
			if(!($this->_resource = @mysql_pconnect($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], true))){
				if($goOffline){
					$this->dbOffline('Failed to create a persistent Connection.');
				}
			}
		}else{
			if(!($this->_resource = mysql_connect($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], true))){
				if($goOffline){
					$this->dbOffline();
				}
			}
		}

		if($cfg['db_name'] != '' && !mysql_select_db($cfg['db_name'] , $this->_resource)){
			if($goOffline){
				$this->dbOffline('Failed to set database name!');
			}
		}
		$this->_table_prefix = $cfg['db_prefix'];
	}

	/**
	 * If DB is offline, alert user, then die.
	 *
	 * @param string Error message
	 */
	private function dbOffline($msg='Could not connect to database server'){
		die($msg);
	}

	/**
	 * Get table prefix
	 *
	 * @return string Table prefix
	 */
	function getPrefix(){
		return $this->_table_prefix;
	}

	/**
	 * Get Null date
	 *
	 * @return string Return Null date (0000-00-00 00:00:00)
	 */
	function getNullDate(){
		return $this->_nullDate;
	}

	/**
	 * Get SQL error number
	 *
	 * @return int Error number
	 */
	function getErrorNum(){
		return $this->_errorNum;
	}

	/**
	 * Get SQL error message
	 *
	 * @return string Error message
	 */
	function getErrorMsg(){
		return str_replace(array("\n", "'"), array('\n', "\'"), $this->_errorMsg);
	}

	/**
	 * Get SQL version
	 *
	 * @return string Returns the version of the MySQL Server
	 */
	function getVersion(){
		return mysql_get_server_info($this->_resource);
	}

	/**
	 * Get a propper database escaped string
	 *
	 * @param string String to be escaped
	 * @return string Database escaped string
	 */
	function getEscaped($text){
		return mysql_real_escape_string($text, $this->_resource);
	}

	/**
	 * Get a quoted database escaped string
	 *
	 * @param string String to be escaped and quoted
	 * @return string Quoted database escaped string
	 */
	function getQuote($text){
		return '\'' . mysql_real_escape_string($text, $this->_resource) . '\'';
	}

	/**
	 * Quote an identifier name (field, table, etc)
	 * @param string The name
	 * @return string The quoted name
	 */
	function getNameQuote($s){
		$q = $this->_nameQuote;
		if(strlen($q) == 1){
			return $q . $s . $q;
		}else{
			return $q{0} . $s . $q{1};
		}
	}

	/**
	 * Returns a string with the current value of the internal SQL variable
	 *
	 * @return string string with the current value of the internal SQL variable
	 */
	function getQuery(){
		return '<pre>'.htmlspecialchars($this->_sql).'</pre>';
	}

	// Returns the number of affected rows in the previous operation
	function getAffectedRows(){
		return mysql_affected_rows($this->_resource);
	}

	/**
	 * Get number of rows in result
	 *
	 * @return int Returns the number of rows in a buffered result set
	 */
	function getNumRows($cur=NULL){
		//return mysql_num_rows($cur ? $cur : $this->_cursor);
		//dbg($this);
		
		return mysql_num_rows($this->_cursor);
		
		
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @return int Returns the ID generated from the previous INSERT operation
	 */
	function insertid(){
		return mysql_insert_id($this->_resource);
	}

	// Set debug level
	// 0 = debug off
	// 1 = debug on (full)
	function setDebug($level){
		$this->_debug = intval($level);
	}

	// Set table prefix
	function setPrefix($tp){
		$this->_table_prefix = $tp;
	}

	/**
	* Sets the SQL query string for later execution.
	*
	* This function replaces a string identifier <var>$prefix</var> with the
	* string held in the <var>_table_prefix</var> class variable.
	*
	* @param string The SQL query
	* @param string The offset to start selection
	* @param string The number of results to return
	* @param string The common table prefix
	*/
	function setQuery($sql, $offset = 0, $limit = 0, $prefix='#__'){
		$this->_sql = $this->replacePrefix($sql, $prefix);
		$this->_limit = intval($limit);
		$this->_offset = intval($offset);
	}

	// This function can be seriously optimized.
	function replacePrefix($sql, $prefix='#__'){
		$sql = trim($sql);

		$escaped = false;
		$quoteChar = '';

		$n = strlen($sql);

		$startPos = 0;
		$literal = '';
		while($startPos < $n){
			$ip = strpos($sql, $prefix, $startPos);
			if($ip === false){
				break;
			}

			$j = strpos($sql, "'", $startPos);
			$k = strpos($sql, '"', $startPos);
			if(($k !== false) && (($k < $j) || ($j === false))){
				$quoteChar	= '"';
				$j			= $k;
			}else{
				$quoteChar	= "'";
			}

			if($j === false){
				$j = $n;
			}

			$literal .= str_replace($prefix, $this->_table_prefix, substr($sql, $startPos, $j - $startPos));
			$startPos = $j;

			$j = $startPos + 1;

			if($j >= $n) {
				break;
			}

			// quote comes first, find end of quote
			while(true){
				$k = strpos($sql, $quoteChar, $j);
				$escaped = false;
				if($k === false){
					break;
				}
				$l = $k - 1;
				while($l >= 0 && $sql{$l} == '\\'){
					$l--;
					$escaped = !$escaped;
				}
				if($escaped){
					$j	= $k+1;
					continue;
				}
				break;
			}
			if($k === false){
				// error in the query - no end quote; ignore it
				break;
			}
			$literal .= substr($sql, $startPos, $k - $startPos + 1);
			$startPos = $k+1;
		}
		if($startPos < $n){
			$literal .= substr($sql, $startPos, $n - $startPos);
		}
		return $literal;
	}

	/**
	* Execute the query
	* @return mixed A database resource if successful, FALSE if not.
	*/
	function query($sql=false){
		if($sql){
			$this->setQuery($sql);
		}
		
		if($this->_limit > 0 && $this->_offset == 0){
			$this->_sql .= "\nLIMIT $this->_limit";
		}else if($this->_limit > 0 || $this->_offset > 0){
			$this->_sql .= "\nLIMIT $this->_offset, $this->_limit";
		}

		$this->_errorNum = 0;
		$this->_errorMsg = '';
		$this->_cursor = mysql_query($this->_sql, $this->_resource);

		if(!$this->_cursor){
			$this->_errorNum = mysql_errno($this->_resource);
			$this->_errorMsg = mysql_error($this->_resource)." SQL=$this->_sql";

			if($this->_debug){
				trigger_error(mysql_error($this->_resource), E_USER_NOTICE);
				if (function_exists('debug_backtrace')){
					foreach( debug_backtrace() as $back){
						if (@$back['file']){
							echo '<br />'.$back['file'].':'.$back['line'];
						}
					}
				}
			}
			return false;
		}

		if($this->_debug){
			$this->_ticker++;
	  		$this->_log[] = $this->_sql;
		}

		return $this->_cursor;
	}
	function updateObject($table, &$object, $keyName, $updateNulls=true){
		$fmtsql = "UPDATE $table SET %s WHERE %s";
		$tmp = array();
		foreach (get_object_vars( $object ) as $k => $v) {
			if(is_array($v) or is_object($v) or $k[0] == '_' ) { // internal or NA field
				continue;
			}
			
			if($k == $keyName){ // PK not to be updated
				$where = $keyName . '=' . $this->getQuote( $v );
				continue;
			}
			if($v === NULL && !$updateNulls){
				continue;
			}
			if($v == ''){
				$val = "''";
			}else{
				$val = $this->getQuote( $v );
			}
			$tmp[] = $this->getNameQuote( $k ) . '=' . $val;
		}
		$this->setQuery(sprintf($fmtsql,implode(",", $tmp),$where));
		return $this->query();
	}
	/**
	* This method loads the first field of the first row returned by the query.
	*
	* @return The value returned in the query or null if the query failed.
	*/
	function loadResult(){
		if(!($cur = $this->query())){
			return null;
		}

		$ret = null;
		if($row = mysql_fetch_row($cur)){
			$ret = $row[0];
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	* Load an array of single field results into an array
	*/
	function loadResultArray($numinarray = 0){
		if(!($cur = $this->query())){
			return null;
		}

		$array = array();
		while($row = mysql_fetch_row($cur)){
			$array[] = $row[$numinarray];
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	* Load a assoc list of database rows
	* @param string The field name of a primary key
	* @return array If <var>key</var> is empty as sequential list of returned records.
	*/
	function loadAssocList($key=''){
		if(!($cur = $this->query())){
			return null;
		}
		$array = array();
		while($row = mysql_fetch_assoc($cur)){
			if($key){
				$array[$row[$key]] = $row;
			}else{
				$array[] = $row;
			}
		}
		mysql_free_result($cur);
		return $array;
	}
	/**
	* Document::db_insertObject()
	*
	* { Description }
	*
	* @param string $table This is expected to be a valid (and safe!) table name
	* @param [type] $keyName
	* @param [type] $verbose
	*/
	function insertObject($table, &$object, $keyName = null, $verbose=false){
		$fmtsql = "INSERT INTO $table (%s) VALUES (%s) ";
		$fields = array();
		foreach(get_object_vars( $object ) as $k => $v){
			if(is_array($v) or is_object($v) or $v === null){
				continue;
			}
			if($k[0] == '_'){ // internal field
				continue;
			}
			$fields[] = $this->getNameQuote($k);
			$values[] = $this->getQuote($v);
		}
		$this->setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
		($verbose) && print "$sql<br />\n";
		if(!$this->query()){
			return false;
		}
		$id = mysql_insert_id($this->_resource);
		($verbose) && print "id=[$id]<br />\n";
		if($keyName && $id){
			$object->$keyName = $id;
		}
		return true;
	}
	/**
	* This global function loads the first row of a query into an object
	*
	* If an object is passed to this function, the returned row is bound to the existing elements of <var>object</var>.
	* If <var>object</var> has a value of null, then all of the returned query fields returned in the object.
	* @param string The SQL query
	* @param object The address of variable
	*/
	function loadObject(&$object){
		if($object != null){
			if(!($cur = $this->query())){
				return false;
			}
			if($array = mysql_fetch_assoc($cur)){
				mysql_free_result($cur);
				$this->BindArrToObj($array, $object, null, null, false);
				return true;
			}else{
				return false;
			}
		}else{
			if($cur = $this->query()){
				if($object = mysql_fetch_object($cur)){
					mysql_free_result($cur);
					return true;
				}else{
					$object = null;
					return false;
				}
			}else{
				return false;
			}
		}
	}

	/**
	* Load a list of database objects
	* @param string The field name of a primary key
	* @return array If <var>key</var> is empty as sequential list of returned records.
	* If <var>key</var> is not empty then the returned array is indexed by the value
	* the database key.  Returns <var>null</var> if the query fails.
	*/
	function loadObjectList($key=''){
		if(!($cur = $this->query())){
			return null;
		}
		$array = array();
		while($row = mysql_fetch_object($cur)){
			if($key){
				$array[$row->$key] = $row;
			}else{
				$array[] = $row;
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	* @return The first row of the query.
	*/
	function loadRow(){
		if (!($cur = $this->query())){
			return null;
		}
		$ret = null;
		if ($row = mysql_fetch_assoc( $cur )){
			$ret = $row;
		}
		mysql_free_result($cur);
		return $ret;
	}

	/**
	* Load a list of database rows (numeric column indexing)
	* @param int Value of the primary key
	* @return array If <var>key</var> is empty as sequential list of returned records.
	* If <var>key</var> is not empty then the returned array is indexed by the value
	* the database key.  Returns <var>null</var> if the query fails.
	*/
	function loadRowList($key=null){
		if (!($cur = $this->query())){
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_row($cur)){
			if (!is_null($key)){
				$array[$row[$key]] = $row;
			} else {
				$array[] = $row;
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	 * Loads data into an array with key as array key and val as the value
	 *
	 * @param mixed $key Table data to turn into array key.
	 * @param mixed $val The value of the one dimentional array.
	 * @return array Array containing data.
	 */
	function loadKeyValueList($key=null,$val=null){
		if (!($cur = $this->query())){
			return null;
		}
		$array = array();
		while ($row = mysql_fetch_assoc($cur)){
			if (!is_null($key) && !is_null($val)){
				$array[$row[$key]] = $row[$val];
			}
		}
		mysql_free_result($cur);
		return $array;
	}

	/**
	* @param boolean If TRUE, displays the last SQL statement sent to the database
	* @return string A standised error message
	*/
	function stderr($showSQL = false){
		return "DB function failed with error number $this->_errorNum"
		."<br /><font color=\"red\">$this->_errorMsg</font>"
		.($showSQL ? "<br />SQL = <pre>$this->_sql</pre>" : '');
	}

	
	public function next(){}
	public function prev(){}
	public function close(){}

	private function BindArrToObj($array, &$obj, $ignore='', $prefix=NULL, $checkSlashes=true){
		if(!is_array($array) || !is_object($obj)){
			return (false);
		}

		foreach(get_object_vars($obj) as $k => $v){
			if(substr($k, 0, 1) != '_'){			// internal attributes of an object are ignored
				if (strpos($ignore, $k) === false){
					if($prefix){
						$ak = $prefix . $k;
					}else{
						$ak = $k;
					}
					if(isset($array[$ak])){
						$obj->$k = ($checkSlashes && get_magic_quotes_gpc()) ? $this->Stripslashes($array[$ak]) : $array[$ak];
					}
				}
			}
		}
		return true;
	}

	private function Stripslashes(&$value){
		$ret = '';
		if(is_string($value)){
			$ret = stripslashes($value);
		}else{
			if(is_array($value)){
				$ret = array();
				foreach ($value as $key => $val){
					$ret[$key] = $this->Stripslashes($val);
				}
			}else{
				$ret = $value;
			}
		}
		return $ret;
	}
} // end database class


  // This function should be in a DB helper class of some kind.
  /**
   * Bind Array To Object.
   * @param $array
   * @param $obj
   * @param $ignore
   * @param $prefix
   * @param $checkSlashes
   * @return unknown_type
   */

  function BindArrToObj($array, &$obj, $ignore='', $prefix=NULL, $checkSlashes=true){
	if(!is_array($array) || !is_object($obj)){
		return (false);
	}

    foreach (get_object_vars($obj) as $k => $v){
      if(substr($k, 0, 1) != '_'){      // internal attributes of an object are ignored
        if (strpos($ignore, $k) === false){
          if($prefix){
            $ak = $prefix . $k;
          }else{
            $ak = $k;
          }
          if(isset($array[$ak])){
            $obj->$k = ($checkSlashes && get_magic_quotes_gpc()) ? $this->Stripslashes($array[$ak]) : $array[$ak];
          }
        }
      }
    }
    return true;
  }
?>