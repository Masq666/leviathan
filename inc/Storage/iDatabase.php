<?php
interface iDatabase{
	public function getPrefix();
	public function getNullDate();
	public function getErrorNum();
	public function getErrorMsg();
	public function getVersion();
	public function getEscaped($text);
	public function getQuote($text);
	public function getNameQuote($s);
	public function getQuery();
	public function getAffectedRows();
	public function getNumRows($cur=NULL);
	public function setDebug($level);
	public function setPrefix($tp);
	public function setQuery($sql, $offset = 0, $limit = 0, $prefix='#__');
	public function loadResult();
	public function loadResultArray($numinarray = 0);
	public function loadAssocList($key='');
	public function loadObject(&$object);
	public function loadObjectList($key='');
	public function loadRow();
	public function loadRowList($key=null);
	public function stderr($showSQL = false);
	public function insertid();
	public function query();
	public function next();
	public function prev();
	public function close();
}
?>