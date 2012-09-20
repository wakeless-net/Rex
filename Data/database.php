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

class TransactionPDO extends \PDO {
  // Database drivers that support SAVEPOINTs.
  protected static $savepointTransactions = array("pgsql", "mysql");

  // The current transaction level.
  protected $transLevel = 0;

  protected function nestable() {
    return in_array($this->getAttribute(\PDO::ATTR_DRIVER_NAME),
      self::$savepointTransactions);
  }

  function log($statement) {
    \Log::debug(preg_replace('/(?>\r\n|\n|\r)+/m', ' ', $statement)."\n\r");
  }
  
  function exec($statement) {
    $this->log($statement);
    return parent::exec($statement);
  }
  
  function query($statement) {
    try {
      $timer = microtime(true);

      $this->log($statement);
      $result = parent::query($statement);

      $timer = microtime(true) - $timer;

      if($timer > 5) Log::warn("Slow query {$timer}s: ".$statement);
      return $result;
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage()."\n\r\n\r".$statement, (int)$e->getCode(), $e);
    }
  }


  function transaction($call) {
    $this->beginTransaction();

    try {
      $ret = call_user_func($call);
    } catch(\Exception $e) {
      \Log::debug($e);
      $this->rollBack();
      throw $e;
    }

    if($ret) {
      $this->commit();
    } else {
      $this->rollBack();
    }

    return $ret;
  }

  public function beginTransaction() {
    $trace = debug_backtrace();
    \Log::debug($trace[1]["file"].":L".$trace[1]["line"], true);
    \Log::debug(@$trace[2]["file"].":L".@$trace[2]["line"], true);
    \Log::debug(@$trace[3]["file"].":L".@$trace[3]["line"], true);
    \Log::debug("beginTransaction $this->transLevel");
    
    if(!$this->nestable() || $this->transLevel == 0) {
      parent::beginTransaction();
    } else {
      $this->exec("SAVEPOINT LEVEL{$this->transLevel}");
    }

    $this->transLevel++;
  }

  public function commit() {
    $this->transLevel--;
    \Log::debug("commitTransaction $this->transLevel");
    if(!$this->nestable() || $this->transLevel == 0) {
      parent::commit();
    } else {
      $this->exec("RELEASE SAVEPOINT LEVEL{$this->transLevel}");
    }
  }

  public function rollBack() {
    $this->transLevel--;

    \Log::debug("rollbackTransaction $this->transLevel");
    if(!$this->nestable() || $this->transLevel == 0) {
      parent::rollBack();
    } else {
      $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transLevel}");
    }
  }
  
  function TableFields($table) {
    $fields = new Database_FieldSet();
    try {
      foreach($this->query("DESCRIBE $table") as $item) {
        $fields->add($item, $table);
      }
    } catch (PDOException $e) {
      if($e->getCode() == "42S02") {
        //do Nothing
      } else {
        throw $e;
      }
    }
    return $fields;
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

