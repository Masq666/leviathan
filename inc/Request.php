<?php
// No direct access allowed.
defined('_KO22_VALID') or die();

// Create the request global object
$GLOBALS['_WYRM_REQUEST'] = array();

class Request{

	/**
	 * Gets the request method
	 */
	public static function getMethod(){
		return strtoupper($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Gets a variable from an input source and optionaly returns a default value.
	 */
	public static function getVar($var, $def_val = 0, $from = 'default', $type = 'none'){
		$from = strtoupper($from);
		if($from === 'METHOD'){
			$from = strtoupper($_SERVER['REQUEST_METHOD']);
		}
		$sig = $from.$type;

		switch($from){
			case 'GET':
				$input = &$_GET;
			break;
			case 'POST':
				$input = &$_POST;
			break;
			case 'FILES':
				$input = &$_FILES;
			break;
			case 'COOKIE':
				$input = &$_COOKIE;
			break;
			case 'ENV':
				$input = &$_ENV;
			break;
			case 'SERVER':
				$input = &$_SERVER;
			break;
			default:
				$input = &$_REQUEST;
				$from = 'REQUEST';
			break;
		}

		// Check if we allready have a filtered version cached.
		if(isset($GLOBALS['_WYRM_REQUEST'][$var][$sig])){
			$var = $GLOBALS['_WYRM_REQUEST'][$var][$sig];
		}else{
			if(isset($input[$var]) && $input[$var] !== null){
				$var = Request::_cleanVar($input[$var], $type);
				// Cache a filtered version of this variable request.
				$GLOBALS['_WYRM_REQUEST'][$var][$sig] = $var;
			}else{
				return $def_val;
			}
		}
		return $var;
	}

	/**
	 * Return the current users IP addr.
	 */
	public static function getIP(){
		if(filter_has_var(INPUT_SERVER,'REMOTE_ADDR')){
			$ip = $_SERVER['REMOTE_ADDR'];
		}else{
			$ip = "not detected";
		}
		return $ip;
	}

	/**
	 * Clean up an input variable.
	 */	
	protected static function _cleanVar($src, $type=null){
		switch(strtoupper($type)){
			case 'IBOOL':
				// Return boolean as integer, 1 or 0.
				$result = (int)filter_var($src, FILTER_VALIDATE_BOOLEAN);
			break;
			case 'BOOL':
				$result = filter_var($src, FILTER_VALIDATE_BOOLEAN);
			break;
			case 'INT':
			case 'INTEGER':
				$result = (int)filter_var($src, FILTER_SANITIZE_NUMBER_INT);
			break;
			case 'FLOAT':
			case 'DOUBLE':
				$result = (float)filter_var($src, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			break;
			case 'STRING':
				$result = (string)filter_var($src, FILTER_SANITIZE_STRING);
			break;
			case 'EMAIL':
				$result = (string)filter_var($src, FILTER_SANITIZE_EMAIL);
			break;
			case 'ALNUM':
				// Only run the regex if needed.
				$result = (string)ctype_alnum($src) ? $src : preg_replace('/[^A-Z0-9]/i', '', $src);
			break;
			default:
				$result = $src;//(string)filter_var($src, FILTER_SANITIZE_SPECIAL_CHARS);
			break;
		}
		return $result;
	}
}
?>