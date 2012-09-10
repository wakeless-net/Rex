<?php

namespace Rex\Data;

class TransactionPDO extends \PDO {
  // Database drivers that support SAVEPOINTs.
  protected static $savepointTransactions = array("pgsql", "mysql");

  // The current transaction level.
  protected $transLevel = 0;

  protected function nestable() {
    return in_array($this->getAttribute(PDO::ATTR_DRIVER_NAME),
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

