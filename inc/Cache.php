<?php
// No direct access allowed.
defined('_KO22_VALID') or die();

class Cache{
	private static $instance = __CLASS__;

	private function __construct(){}
	private function __clone(){}

	public function set($key,$data,$expire = 3600){
		file_put_contents($key, $data, LOCK_EX);
		touch($key,time() + $expire);
	}

	public function get($key){
		if(filemtime($key) > time()){
			return file_get_contents($key);
		}
		return false;
	}

	public function del($key){
		if(unlink($key)){
			return true;
		}
		return false;
	}

	public static function getInstance(){
		if(!isset(self::$instance)){
			self::$instance = new Cache();           
		}
		return self::$instance;
	}
}
?>