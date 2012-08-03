<?php
	/**
	* SQLite Database Abstraction Layer
	* 
	* @package Wyrm
	* @subpackage Database
	* @version 1.0.0
	* @copyright 2004-2012 Philipe Rubio / Koppin22 Media DA
	* @author Philipe Rubio
	* @link http://wyrm.koppin22.com
	*/

	// No direct access allowed.
	defined('_KO22_VALID') or die('Restricted access');
	// Include the Interface/API.
	require_once('iDatabase.php');

class Database implements iDatabase{
	private $_sql			= '';			// @var string Internal variable to hold the query sql
	private $_errorNum		= 0;			// @var int Internal variable to hold the database error number
	private $_errorMsg		= '';			// @var string Internal variable to hold the database error message
	private $_table_prefix	= '';			// @var string Internal variable to hold the prefix used on all database tables
	private $_resource		= '';			// @var Internal variable to hold the connector resource
	private $_cursor		= NULL;			// @var Internal variable to hold the last query cursor
	private $_debug			= 0;			// @var boolean Debug option
	private $_limit			= 0;			// @var int The limit for the query
	private $_offset		= 0;			// @var int The for offset for the limit
	private $_ticker		= 0;			// @var int A counter for the number of queries performed by the object instance
	private $_log			= array();		// @var array A log of queries
	private $_nullDate		= '0000-00-00 00:00:00'; // @var string The null/zero date string
	private $_nameQuote		= '`';			// @var string Quote for named objects

	/**
	 * Database object constructor
	 * 
	 * @param array $cfg Array containing config variables.
	 * @param bool Show error messages.
	 */
	function __construct($cfg, $goOffline=true){		
		// Perform a few fatality checks, then let dbOffline() handle the error.
		if(!function_exists('sqlite_open')){
			if ($goOffline){
				$this->dbOffline('Selected DB Interface not supported.');
			}
		}

		if(file_exists($cfg['db_name'])){
			$file = $cfg['db_name'];
		}else{
			$file = P_DATA.DS.$cfg['db_name'];
		}

		if(!($this->_resource = @sqlite_open($file, 0666, $sqliteError))){
			if($goOffline){
				$this->dbOffline($sqliteError);
			}
		}
		$this->_table_prefix = $cfg['db_prefix'];
	}
	
	/**
	 * If DB is offline, alert user, then die.
	 * 
	 * @param string Error message
	 */
	private function dbOffline($msg='Could not connect to db server'){
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
	 * @return string Returns the version of the linked SQLite library
	 */
	function getVersion(){
		return sqlite_libversion();
	}

	/**
	 * Get a propper database escaped string
	 * 
	 * @param string String to be escaped
	 * @return string Database escaped string
	 */
	function getEscaped($text){
		return sqlite_escape_string($text);
	}

	/**
	 * Get a quoted database escaped string
	 * 
	 * @param string String to be escaped and quoted
	 * @return string Quoted database escaped string
	 */
	function getQuote($text){
		return '\'' . sqlite_escape_string($text) . '\'';
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
		return sqlite_changes($this->_resource);
	}

	/**
	 * Get number of rows in result
	 * 
	 * @return int Returns the number of rows in a buffered result set
	 */
	function getNumRows($cur=NULL){
		return sqlite_num_rows($this->_cursor);
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 * 
	 * @return int Returns the ID generated from the previous INSERT operation
	 */
	function insertid(){
		return sqlite_last_insert_rowid($this->_resource);
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
	private function replacePrefix($sql, $prefix='#__'){
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
				$j = $k;
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
		}else if ($this->_limit > 0 || $this->_offset > 0){
			$this->_sql .= "\nLIMIT $this->_offset, $this->_limit";
		}

		$this->_errorNum = 0;
		$this->_errorMsg = '';

		$this->_cursor = sqlite_query($this->_resource, $this->_sql);
		if(!$this->_cursor){
			$this->_errorNum = sqlite_last_error($this->_resource);
			$this->_errorMsg = sqlite_error_string($this->_errorNum)." SQL=$this->_sql";
			if($this->_debug){
				trigger_error(sqlite_error_string($this->_errorNum), E_USER_NOTICE);
				if(function_exists('debug_backtrace')){
					foreach(debug_backtrace() as $back){
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

	/**
	* This method loads the first field of the first row returned by the query.
	*
	* @param mixed A default value if query fails.
	* @return The value returned in the query or null if the query failed.
	*/
	function loadResult($def=null){
		if(!($cur = $this->query())){
			return null;
		}
		$ret = null;
		if($row = sqlite_fetch_array($cur)){
			$ret = $row[0];
		}
		if(($def !== null) && ($ret === null)){
			return $def;
		}
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
		while($row = sqlite_fetch_array($cur)){
			$array[] = $row[$numinarray];
		}
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
		/**
		 *	We need to use _cleanArrName to get rid of names like a.key
		 *	the function removes everything before and including the dot.
		*/
		while($row = $this->_cleanArrName(sqlite_fetch_array($cur, SQLITE_ASSOC))){
			if($key){
				$array[$row[$key]] = $row;
			}else{
				$array[] = $row;
			}
		}
		return $array;
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
			if($array = sqlite_fetch_array($cur, SQLITE_ASSOC)){
				mosBindArrayToObject($array, $object, null, null, false);
				return true;
			}else{
				return false;
			}
		}else{
			if($cur = $this->query()){
				if($object = sqlite_fetch_object($cur)){
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
		while($row = sqlite_fetch_object($cur)){
			if($key){
				$array[$row->$key] = $row;
			}else{
				$array[] = $row;
			}
		}
		return $array;
	}

	/**
	* @return The first row of the query.
	*/
	function loadRow(){
		if(!($cur = $this->query())){
			return null;
		}
		$ret = null;
		if($row = sqlite_fetch_array($cur, SQLITE_ASSOC)){
			$ret = $row;
		}
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
		if(!($cur = $this->query())){
			return null;
		}
		$array = array();
		while($row = sqlite_fetch_array($cur, SQLITE_ASSOC)){
			if(!is_null($key)){
				$array[$row[$key]] = $row;
			}else{
				$array[] = $row;
			}
		}
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
	
	/**
	* Advances the result handle to the next row.
	* @return bool
	*/
	function next(){
		if(sqlite_has_more($this->_cursor)){
			return sqlite_next($this->_cursor);
		}
		return false;
	}

	/**
	* Seeks back the result handle to the previous row. 
	* @return bool
	*/
	function prev(){
		if(sqlite_has_prev($this->_cursor)){
			return sqlite_prev($this->_cursor);
		}
		return false;
	}
	
	function close(){
		sqlite_close($this->_resource);
	}
	
	function current(){
		return sqlite_current($this->_cursor,SQLITE_ASSOC);
	}
	
	function valid(){
		return sqlite_has_more($this->_cursor);
	}
	
	function seek($num){
		return sqlite_seek($this->_cursor,$num);
	}
	
	function dbg(){
		dbg($this->_cursor);
	}
	
	function _cleanArrName($array){
		if(is_array($array)){
			foreach($array as $key => $value){
				unset($array[$key]);
				if(strstr($key,'.')){
					$key = substr($key, strpos($key, '.')+1);
				}else{
					$key = substr($key, strpos($key, '.'));
				}
				$array[$key] = $value;
			}
			return $array;
		}
	}
} // end database class
?>