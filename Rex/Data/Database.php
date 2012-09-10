<?php


namespace Rex\Data;

use \PDO as PDO;

class Database {
	static private $db = null;
	
	/**
	 * @return PDO
	 */
	static function getDB() {
		if(self::$db) {
			return self::$db;
		} else {
			global $database;
			extract($database,EXTR_PREFIX_ALL,'');
		
			try {
				self::$db = new TransactionPDO("mysql:dbname=$_Database;host=$_Server", $_Username, $_Password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8" ));
			} catch (PDOException $e) {
				throw new MySQLException("Couldn't connect to database: ".$_Server.". Message: ".$e->getMessage());
				return false;
	  	}
		
			return self::$db;
			
		}
	}
  
  static function setDB($db) {
    self::$db = $db;
  }
	
}

class Database_FieldSet extends Result {
  function add($field, $table = "") {
    $type = "";
    $length = "";
    if(isset($field["Type"])) {
      if(preg_match('/(\w+)\((\d+)\)/', $field["Type"], $typeSplit)) {
        $type = $typeSplit[1];
        $length = $typeSplit[2];
      } else { 
        $type = $field["Type"];
      }
    }
    
    if(!isset($field["FullName"])) {
      $fullName = ($table) ? "$table.".$field["Field"] : $field["Field"];
    } else {
      $fullName = $field["FullName"];
    }
    
    
    $this->append(array(
    	"FullName" => $fullName,
      "Table" => $table,
      "Field" => $field["Field"],
      "Type" => $type,
      "Length" => $length,
    ));
  }
  
  function Fields() {
    return $this->toArray("Field", "Field");
  }
}

