<?php

namespace Rex\Data;

class UniquenessException extends \PDOException {}

class MySQLDataHandler implements \IteratorAggregate, \Countable, \ArrayAccess {
	static $profile = array();
	
	var $lastTotal = 0;
	var $select = array();
	var $from = "";
	var $distinct = false;
	var $joins = array();
	var $where = array();
	var $orders = array();
	var $groupby = array();
	var $having = array();
	var $limits;
	var $type = "";
	
	var $calc_found_rows = true;
	protected $result = null;
	private $db;
	
	function __construct($table = '', $type=''){
		if ($table != ''){
			$this->from = $table;
		}
		
		if($type) {
			$this->type = $type;
		} elseif(!$this->type) {
			$this->type = $table;
		}
	}

  static function id() {
    $type = $this->type;
    return $type::primaryKey();
  }
	
	function klone() {
		return clone $this;
	}
	
	/**
	 * @return MySQLDataHandler 
	 */
	function type($type) {
		$this->result = null;
		
		$this->type = $type;
		return $this;
	}
  
  function getType() {
    return $this->type ? $this->type : $this->from;
  }

	/**
	 * @return MySQLDataHandler 
	 */
	function distinct($distinct = true) {
		$this->result = null;
		
		$this->distinct = $distinct;
		return $this;
	}
		
	/**
	 * @return PDO
	 */
	function connectToDB() {
		return Database::getDB();
	}
	
	/**
	 * @return MySQLDataHandler 
	 */
	function reset() {
		$this->result = null;
		
		$this->lastTotal = 0;
		$this->select = array();
		$this->joins = array();
		$this->where = array();
		$this->orders = array();
		$this->limits = '';
		$this->groupby = array();
		$this->having = array();
		return $this;
	}
	
	function mergeQuery($query) {
	  $this->select += $query->select;
	  $this->joins += $query->joins;
	  $this->where += $query->where;
	  $this->orders += $query->orders;
	  $this->groupby += $query->groupby;
	  $this->having += $query->having;
	}
	
	/**
	 * @return MySQLDataHandler 
	 */
	function all(){
		$this->reset();
	
		$this->select[] = $this->from . ".*";
		
		return $this;
	}
	
	function requery() {
	  $this->result = null;
	  return $this;
	}
	
	/**
	 * 
	 * @param object $from
	 * @return MySQLDataHandler
	 */
	function from($from){
		$this->result = null;
		$this->from = $from;
		
		return $this;
	}
	
	function start(){
		$this->reset();

		return $this;
	}
	
	/**
	 * 
	 * @param string $fields
	 * @return MySQLDataHandler 
	 */
	function select($fields){
		$this->result = null;
		//use INSTEAD of all
		
    $args = func_get_args();
    array_shift($args);

		if(is_array($fields)) {
      foreach($fields as $key => $field) {
        $this->select[$key] = $this->escapeArray($field, $args);
      }
		} else {
		  
			$this->select[] = $this->escapeArray($fields, $args);
		}
		
		return $this;
	}
	
	/**
	 * This resets the select array and starts it afresh with the arguments.
	 * @param array $fields
	 * @return MySQLDataHandler
	 */
	function selectOnly($fields) {
	  $this->result = null;
    
	  if(is_array($fields)) {
	    $this->select = $fields;
	  } else {
	    $args = func_get_args();
		  array_shift($args);
		  
	    $this->select = array();
			$this->select[] = $this->escapeArray($fields, $args);
	  }
	  return $this; 
	}
	
	function subquery() {
		$this->result = null;
		
		$args = func_get_args();
		$this->select[] = call_user_func_array(array($this, "escape"), $args);
		return $this;
	}
	
	/**
	 * 
	 * @param string $condition
	 * @return MySQLDataHandler
	 */
	function filter($condition){
		$this->result = null;
		$args = func_get_args();
		$condition = call_user_func_array(array($this, "processFilter"), $args);

		if(is_array($condition)) {
			list($key, $condition) = each($condition);
			
			$this->where[$key] = $condition;
		} else {
			$this->where[] = $condition;
		}
		
		return $this;
	}
	
	protected function processFilter($condition) {
		$args = func_get_args();
		//remove query string
		array_shift($args);
		
		if(is_array($condition)) {
			if(count($condition) > 1) {
				throw new MySQLException("Array must only have 1 element and 1 key.");
			}
			list($key, $condition) = each($condition);
			
			if(is_numeric($key)) {
				throw new MySQLException("Array must be associative.");
			}
			return array($key => $this->escapeArray($condition, $args));
		}
		return $this->escapeArray($condition, $args);
	} 
	
	function getFilter($key) {
		if(isset($this->where[$key])) {
			return $this->where[$key];
		} else {
			return null;
		}
	}
	
	/**
	 * @return MySQLDataHandler
	 */
	function where() {
		$args = func_get_args();
		return call_user_func_array(array($this, "filter"), $args);
	}
	/**
	 * 
	 * @param string $table
	 * @param string $on
	 * @return MySQLDataHandler
	 */
	function leftjoin($table,$on){
		$args = func_get_args();
		array_unshift($args, "LEFT");
		return call_user_func_array(array($this, "addJoin"), $args);
	}

	/**
	 * 
	 * @param string $table
	 * @param string $on
	 * @return MySQLDataHandler
	 */
	function rightjoin($table,$on){
		$args = func_get_args();
		array_unshift($args, "RIGHT");
		return call_user_func_array(array($this, "addJoin"), $args);
	}
	
	/**
	 * 
	 * @param string $table
	 * @param string $on
	 * @return MySQLDataHandler
	 */
	function innerjoin($table, $on) {
		$args = func_get_args();
		array_unshift($args, "INNER");
		return call_user_func_array(array($this, "addJoin"), $args);
	}
	
	function addJoin($type, $table, $on) {
		$this->result = null;
		
		$values = func_get_args();
		array_shift($values);
		array_shift($values);
		array_shift($values);
    if(isset($this->joins[$table])) unset($this->joins[$table]);
		$this->joins[$table] = "$type JOIN $table ON ". $this->escapeArray($on, $values);
		return $this;
	}
	
	function having($condition) {
		$this->result = null;
		
		$args = func_get_args();
		
		$condition = call_user_func_array(array($this, "processFilter"), $args);
		
		if(is_array($condition)) {
			list($key, $condition) = each($condition);
			
			$this->having[$key] = $condition;
		} else {
			$this->having[] = $condition;
		}
		return $this;
	}

	/**
	 * 
	 * @param string $from
	 * @param string $amount [optional]
	 * @return MySQLDataHandler
	 */	
	function limit($from,$amount = ''){
		$this->result = null;
		
		if ($amount == ''){
			$this->limits = $this->escape("LIMIT ?",$from);
		} else {
			$this->limits = $this->escape("LIMIT ?, ?",$from,$amount);
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param string $field
	 * @param string $direction [optional] Normally ASC or DESC
	 * @return MySQLDataHandler
           */
          function order($field,$direction = 'ASC'){
                  $this->result = null;
                  
                  if(is_array($field)) {
                          foreach($field as $order => $direction) {
                            if(is_numeric($order)) {
                              $order = $direction;
                              $break = explode(" ", $order);
                              call_user_func_array(array($this, "order"), $break);
                            } else {
                              $this->order($order, $direction);

                            }
                          }
                  } else {
                          $this->orders[] = $this->escape("? ?",$field,$direction);
		}
		return $this;
	}


  function orderOnly($field, $direction="ASC") {
    $this->orders = array();
    return $this->order($field, $direction);
  }

  /**
   * @param $set An array of ids (or $field) to order by
   * @param $field The name of a field to order by
   */
  function orderInSet($set, $field = null) {
    if(is_null($field)) $field = "$this->from.ID";
    $this->orders = array("find_in_set($field, '".$this->escape("?", implode($set, ","))."')");
    return $this;
  }
        
	
	/**
	 * @param string $field
	 * return MySQLDataHandler
	 */
	function groupby($field) {
		$this->result = null;
		
		$this->groupby[] = $this->escape("?", $field);
		return $this;
	}



  private function combineFiltersWithAnd($filters) {
    $filters = array_filter($filters);
		if(count($filters) > 0) {
			return "(".implode(') AND (', array_unique(array_filter($filters))).")";
		} else {
			return "";
		}
    
  }

  
  protected function getHaving() {
    return $this->combineFiltersWithAnd($this->having);
  }
	

	protected function getWhere() {
    return $this->combineFiltersWithAnd($this->where);
	}

	
	function getOrderBy() {
		return implode(', ', $this->orders);
	}
	
	function getQuery() {
		$distinct = $this->distinct ? "DISTINCT" : "";
		$group = implode(', ', $this->groupby);		
		$orders = $this->getOrderBy();
		$having = implode(' AND ', $this->having);
		
		$where = $this->getWhere();
		$from = $this->getFrom();
		$select = $this->getSelect();
		
		if(!$from) {
			throw new Exception("FROM table on ".get_class($this)." is not set.");
		}
		
		if(!$select) {
			throw new Exception("SELECT on ".get_class($this)." is not set.");
		}
		
						  
		
		if ($orders != ''){
			$orders = 'ORDER BY ' . $orders;
		}
		
		if ($where != ''){
			$where = 'WHERE ' . $where;
		}
		
		if($group) {
			$group = "GROUP BY $group";
		}
		if($having) {
			$having = "HAVING $having";
		}
		
		$foundRows = $this->calc_found_rows ? "SQL_CALC_FOUND_ROWS" : "";
		
		return sprintf("SELECT %s $foundRows %s\n\r FROM %s\n\r %s\n\r %s\n\r %s %s %s %s",
                          $distinct,
						  $select,
						  $from,
						  $this->getJoins(),
						  $where,
						  $group,
						  $having,
						  $orders,
						  $this->limits);
	}
	
	function getSubquery() {
	  $rows = $this->calc_found_rows;
	  $this->calc_found_rows = false;
	  $query = $this->getQuery();
	  $this->calc_found_rows = $rows;
	  return $query;
	}
	
	function getSelect() {
		if(count($this->select) > 0) {
			return implode(', ', array_unique($this->select));
		} else {
			return $this->from.".*";
		}
	}
						  
	function getFrom() {
		return $this->from;
	}
	
	function getJoins() {
		return implode(" " ,$this->joins);
	}
	
	/**
	 * Executes the current query.
	 * 
	 * @param string $type [optional] Class to return
	 * @return Result 
	 */
	function go($type = ''){
		$query = $this->getQuery();
		return $this->find_by_sql($query, $type);
	}
	
	function find_by_sql($query, $type = '') {
		if(!$type) {
			$type = $type = $this->type ? $this->type : $this->from;
		}
		$queryResult = $this->executeQuery($query);
		
		$this->result = new Result();
		
		if($queryResult) while($row = $queryResult->fetch(\PDO::FETCH_ASSOC)) {

			if(!class_exists($type))
      throw new \Exception("$type doesn't exist.");
			$this->result[] = new $type($row);
		}
		return $this->result;
	}
	
	function executeQuery($query) {
		$query = trim($query);
		
		if(isset(self::$profile[$query])) self::$profile[$query]++;
		else self::$profile[$query] = 1;
		
		$db = $this->connectToDB();
		//echo $query;
	  $result = $db->query($query);
		if(stripos($query, "UPDATE") === 0 || stripos($query, "DELETE") === 0 || stripos($query, "INSERT") === 0) {
			$this->lastTotal = $result->rowCount();
		} else {
			$this->setLastTotal();
		}
		return $result;
	}
	
	function save($obj){
		$args = func_get_args();
		
		if (isset($args[1]) && is_array($args[1])){
			//if passing array, use those fields for update/insert
			$fields = $args[1];
		} else if ($obj->fields != ''){
			//otherwise use object's built-in reference
			$fields = $obj->fields;
		} else {
			//we can't work without FIELDS!!! so return
			throw new Exception("Object of type ".get_class($obj)." requires a fields property to be saved.");
			return FALSE;
		}
		$db = $this->connectToDB();
		
		$fieldnames = implode(', ',$fields);
		$values = array();
		
		foreach ($fields as $f) {
			$func = 'db' . $f;
			$wildcard = '?';
      
      $data = $obj->$func();

      if(is_null($data)) {
        $values[] = "null";
      } else {
        $values[] = $this->escape("'$wildcard'",$data);
      }

		}
		
		$valstring = implode(', ',$values);
    try {
      if (!$obj->getID()){
        //insert
        $query = $this->escape("INSERT INTO ? ($fieldnames) VALUES ($valstring)",$this->from);
        $result = $this->executeQuery($query);
        
        $obj->setID($db->lastInsertID());
        return true;
      } else {
        $pairs = array();
        //update
        //setup string of names and values
        for ($i = 0; $i < count($fields); $i++){
          $func = 'db' . $fields[$i];

          $data = $obj->$func();
          if(is_null($data)) {
            $pairs[] = $this->escape("? = null", $fields[$i]);
          } else {
            $pairs[] = $this->escape("? = '?'",$fields[$i],$obj->$func());
          }
        }
        
        $valstring = implode(', ',$pairs);
        $query = $this->escape("UPDATE ? SET ", $this->from).$valstring.$this->escape(" WHERE ID = '?' LIMIT 1",$obj->getID());
        $result = $this->executeQuery($query);
        return true;
      }
    } catch(PDOException $e) {
      if($e->getCode() == 23000) {
        throw new UniquenessException($e->getMessage(), $e->getCode(), $e);
      } else {
        throw $e;
      }
    }
  }
	
	/**
	 * Either deletes an object @deprecated or returns a fluid delete handler
	 * @param $obj
	 * @return MySQLDeleteHandler
	 */
	
	function delete($obj = null){
		if(is_null($obj)) {
			$handler = new MySQLDeleteHandler($this->from);
			$handler->where = $this->where;
			return $handler;
		}
		
		$db = $this->connectToDB();
		
		$query = $this->escape("DELETE FROM ? WHERE ID = '?'",$this->from,$obj->getID());
		return $db->exec($query);
	}
	
	function deleteWithCriteria($obj,$fields){
		$db = $this->connectToDB();
		
		$criteria = array();
		
		foreach ($fields as $field){
			$func = 'get' . $field;
			$criteria[] = $this->escape("$field = '?'",$obj->$func());
		}
		
		$criterias = implode(' AND ',$criteria);
		
		$query = $this->escape("DELETE FROM ? WHERE $criterias",$this->from,$obj->getID());
		return $db->exec($query);
	}


  function escapeArg($arg) {
    if($arg instanceof DataObject) $arg = $arg->getID();
    if($arg instanceof \DateTime) $arg = $arg->format("Y-m-d H:i:s");
    if($arg instanceof MySQLDataHandler) {
      if($arg->countSelects() == 1) {
        return "(".$arg->getSubquery() .")";
      } else {
        $arg = $arg->toArray("ID");
      }
    }
    if(is_array($arg)) {
      $db = $this->connectToDB();
      $handler = $this;
      
      $arg = array_map(function($i) use ($db, $handler) { return "'".$handler->escapeArg($i)."'"; }, $arg);
      $arg = implode(",", $arg);
      
    } else {
    
      if (get_magic_quotes_gpc() == 1) {
        $arg = stripslashes($arg);
      }
      
      if(is_array($arg)) {
        throw new Exception(print_r($arg, true)." should be a string not a ". gettype($arg)." \n\r Query: $query");
      }
      $arg = addslashes($arg); //MYSQL

      //TODO: Change this to $db->quote();
    }

    return $arg;
			
  }
	
	function escapeArray($query,$values){
		$lookFrom = 0;
		
		for ($i = 0; $i < count($values);$i++) {
			
			$arg = $this->escapeArg($values[$i]);
			//$arg = str_replace("'","''",$arg); //squirrel
			
			$pos = (strpos($query,'?',$lookFrom) >= 0) ? strpos($query,'?',$lookFrom) :NULL;
			$amnt = 1;
			
			if (strpos($query,'!',$lookFrom) >= 0
				&& (strpos($query,'!',$lookFrom) < $pos || $pos == NULL)
				&& is_int(strpos($query,'!',$lookFrom))) {
			
				$pos = strpos($query,'!',$lookFrom);
				if ($arg == '') {
					$arg = 'NULL';
					$pos--;
					$amnt = 3;
				}
			
			}
			$query = substr_replace($query,$arg,$pos,$amnt);
			$lookFrom = $pos + strlen($arg);
		}
		
		return $query;
	}
	
	function escape() {
		$args = func_get_args();
		$query = array_shift($args);
		
		return $this->escapeArray($query, $args);
	}
	
	function setLastTotal() {
		$query = "SELECT FOUND_ROWS() AS Total";
		$countResult = $this->connectToDB()->query($query);
		$this->lastTotal = $countResult->fetchColumn(0);
	}
	
	function getLastTotal(){
		$this->getIterator();
		return $this->lastTotal;
	}
	
	protected $columnFilters = array();
	
  function filterNotOnColumn($column, $values, $key="", $clone=false) {
    if(!$clone) {
      $handler = $this;
    } else {
      $handler = $this->klone();
    }

    if(!$key) $key = $column;
    
    if($values instanceof MySQLDataHandler || is_array($values)){
      $where = "$column NOT IN (".$this->escapeArg($values).")";
    } else {
      $handler->columnFilters[$column] = $values;
			$where = $handler->escape("$column <> '?'", $values);
    }
		return $handler->filter(array($key => $where));


  }
  /**
   * @return MySQLDataHandler
   */
	function filterOnColumn($column, $values, $key="", $clone=false) {
		if(!$clone) {
			$handler = $this;
		} else {
			$handler = $this->klone();
		}
		
		if(!$key) $key = $column;
    
    if($values instanceof MySQLDataHandler) {
      if($values->countSelects() == 1) {
        $where = "$column IN (".$values->getSubquery().")";
      } else {
        $where = "$column IN ('".implode("','",$values->toArray("ID"))."')";
      }
    } elseif(is_array($values)) {

      if(empty($values)) {
        $where = "$column in ('')";

      } else {
        $valString = $this->escapeArg($values);

        $where = "$column IN ($valString)";
      }

      if(in_array(null, $values, true)) {
        if(isset($where)) {
          $where .= " OR $column IS NULL";
        } else {
          $where = "$column IS NULL";
        }
      }
    } elseif(is_null($values)) {
      $where = "$column IS NULL";
    } elseif($values instanceof MySQLColumn) {
      $where = "$column = ". $values;
		} else {
			$handler->columnFilters[$column] = $values;
			$where = $handler->escape("$column = '?'", $values);
		}
    
		return $handler->filter(array($key => $where));
	}
  
  function countSelects() {
    return count($this->select);
  }
	
	function build($args = array()) {
		$data = array();
    $this->type = $this->getType();

		foreach($this->columnFilters as $column => $value) {
      $ex = explode(".", $column);
			$column = array_pop($ex);
			$data[$column] = $value;
		}
                unset($data["ID"]);
		unset($args["ID"]);
		$item = new $this->type($data + $args);
		return $item;
	}
	
	function create($args=array()) {
		$item = $this->build($args);
		$item->save();
		return $item;
	}
	
	function clearIterator() {
		$this->result = null;
		return $this;
	
	}
  
	function getIterator() {
		$type = $this->getType();
		
		if($this->result) {
			return $this->result;
		} else {
			return $this->go($type);
		}
	}
	
	function count() {
		return $this->getIterator()->count();
	}
  
  public function isEmpty() {
    return $this->getIterator()->isEmpty();
  }
	
	function toArray() {
		$args = func_get_args();
		return call_user_func_array(array($this->getIterator(), "toArray"), $args);
	}
	
	public function offsetSet($offset, $value) {
		throw new Exception("MYSqlDataHandler is immutable.");
	}
	
  public function offsetExists($offset) {
		return $this->getIterator()->offsetExists($offset);
  }

  public function offsetUnset($offset) {
    throw new Exception("MYSqlDataHandler is immutable.");
  }
  
  public function offsetGet($offset) {
    return $this->getIterator()->offsetGet($offset);
  }
    
	function get($value) {
		return $this->getIterator()->get($value);
	}
    
	function find($column, $value) {
		return $this->getIterator()->find($column, $value);
	}

  function each($iterator) {
    return $this->getIterator()->each($iterator);
  }
	
	function contains(DataObject $object) {
		return $this->getIterator()->contains($object);
	}
    
  function split($column) {
		return $this->getIterator()->split($column);
	}
    
	function merge(MySQLDataHandler $merge) {
		return new MySQLMergeHandler($this, $merge);
	}
    
	function first() {
		return $this->getIterator()->first();
	}
    
	function last() {
		return $this->getIterator()->last();
	}
    
    
	function get_by_id($id) {
	  if(is_null($id)) {
	    return null;
	  }
	  
		$handler = clone $this;
		$ret = $handler->filterOnColumn($this->from.".".static::$id, $id);
		if(!is_array($id) && count($ret) == 1) {
			return $ret->first();
		} else if(!is_array($id) && count($ret) == 0){
			return null;
		} else {
			return $ret;
		}
	}

	function find_by_id($id) {
		return $this->get_by_id($id);
	}

	function __call($function, $args) {
		if(in_array($function, array("max", "min", "sum"))) {
			return $this->aggregate($function, $args[0]);
		} else {
			throw new \Exception("Method ".get_class($this)."::$function is not found.");
		}
	}
    
	function aggregate($function, $column) {
		$handler = clone $this;
		$handler->selectOnly(array("$function($column) as Agr"));
    
    if(!$handler->first()) {
      return null;
      throw new Exception("No rows have been returned for aggregate function. \n\r\n\r ". $handler->getQuery());
    }
    
		return $handler->get(0)->getAgr();
	}
	
	static function columnTitle($string) {
		return preg_replace("/[^\w\d]+/", "", ucwords($string));
	}
	
	private $fields = null;
	
	function fields() {
	  if($this->fields) return $this->fields;

	  $fields = new Database_FieldSet();
	  
	  foreach($this->select as $select) {
	    foreach(explode(",", $select) as $field) {
	      $field = trim($field);
	      
  	    if(preg_match("/(\w+|\w+.\w+)\s+as\s+(\w+)/i", $field, $match)) {
  	      $fields->add(array("Field" => $match[2], "FullName" => $match[1]));
  	      
  	    } elseif(preg_match('/^(\w*)\.\*$/', $field, $match)) {
  	      $fields = $fields->merge($this->TableFields($match[1]));
  	      
  	    } elseif(preg_match('/^(\w+)\.(\w+)$/', $field, $match)) {
  	      $fields->add(array("Field" => $match[2]), $match[1]);
  	      
  	    } elseif(preg_match('/^\w+$/', $field)) {
  	      $fields->add(array("Field" => $field));
  	    } elseif(preg_match('/^.* as (\w+)$/i', $field, $match)) {
  	      $fields->add(array("Field" => $match[1])); 
  	      
  	    } else {
  	      //throw new Exception("Not matched: $field");
  	    }
	    }
	  }
	  return $this->fields = $fields;
	}
	
	protected function TableFields($table) {
	  return Database::getDB()->TableFields($table);
	}

  function col($name) {
    if(stripos(".", $name) === false) {
      $name = $this->from.".$name";
    }
    return new MySQLColumn($name);
  }
	
	
}

class MySQLColumn {
  private $name;
  
  function __construct($name) {
    $this->name = $name;
  }

  function __toString() {
    return $this->name;
  }
}

class MySQLDeleteHandler extends MySQLDataHandler {
  function getQuery() {
		return $this->escape("DELETE FROM ? WHERE ". $this->getWhere(). " $this->limits", $this->getFrom());
  }
  
	function go($type = '') {
		$query = $this->getQuery();		
		$this->result = $this->executeQuery($query);
		return $this->getLastTotal();
	}
  
}

class MySQLUpdateHandler extends MySQLDataHandler {
	public $values = array();
	
	function set($column, $value) {
		$this->values[$column] = $value;
		
		return $this;
	}
	
	function values($values) {
		$this->values = $values;
		return $this;
	}
	
	function getQuery() {
		$joins = $this->getJoins();
		$limit = $this->limits ? "LIMIT $this->limits" : "";
		$query = $this->escape("UPDATE $this->from $joins SET ". $this->getUpdates(). " WHERE ". $this->getWhere()." $limit");
		return $query;
	}
	
	
	
	function go($ignore ='') {
		$query = $this->getQuery();		
		$this->result = $this->executeQuery($query);
		return $this->getLastTotal();
	}
	
	function getLastTotal(){
		return $this->lastTotal;
	}
	
	function getUpdates() {
		$sql = array();
		foreach($this->values as $column => $val) {
			if(stripos($column, ".") === false) {
				$column = $this->from.".$column";
			}
      if(is_null($val)) {
        $sql[] = "$column = null";
      } elseif($val instanceof MySQLFunctionValue) {
				$sql[] = "$column = ".$val->toSQL();
			} else {
				$sql[] = $this->escape("$column = '?'", $val);
			}
		}
		
		return implode(", ", $sql);
	}
	

}

class MySQLMergeHandler extends MySQLDataHandler {
	public $handlers = array();

	function __construct() {
		$args = func_get_args();
		
		parent::__construct($args[0]->from);
		
		
		$this->type = $args[0]->type;
		$this->handlers = $args;
	}
	
	function handlers() {
		$return = array();
		foreach($this->handlers as $handler) {
			if($handler instanceof MySQLMergeHandler) {
				$return = $return + $handler->handlers();
			} else {
				$return[] = $handler;
			}
		}
		return $return;
	}
	
	function getQuery() {
		$queries = array();
		$handlers = $this->handlers();
		
		foreach($handlers as $handler) {
			
			$query = $handler->getQuery();
			if(count($queries) > 0) $query = str_ireplace("SQL_CALC_FOUND_ROWS", "", $query);
			$queries[] = $query;
		}
		
		$query =  "(".implode(") \n\rUNION (", $queries).")";
		if($this->getOrderBy()) $query = "$query ORDER BY ".$this->getOrderBy();
		
		return $query;
	}
	
}

class MySQLFunctionValue {
	private $value;
	
	function __construct($value) {
		$this->value = $value;
	}
	
	function toSQL() {
		return $this->value;
	}
}

class MySQLException extends \Exception {
}

?>
