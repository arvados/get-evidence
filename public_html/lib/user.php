<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

class user {

    protected static $pool = array();
    protected $row = false;

    protected function __construct ($oid)
    {
	$this->row =& theDb()->getRow ("SELECT * FROM eb_users WHERE oid=?",
					array ($oid));
    }

    public static function &lookup ($oid)
    {
	if (!array_key_exists ($oid, self::$pool))
	    $pool[$oid] = new user ($oid);
	return $pool[$oid];
    }

    public function get ($key)
    {
	return $this->row[$key];
    }

}
