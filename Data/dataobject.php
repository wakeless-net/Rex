<?php 

namespace Rex\Data;

interface ErrorStore {
	function storeError($field, $error);
	function clearErrors();
	function getError($field);
  function allErrors();
}

class ErrorArray extends \ArrayObject {
  function __toString() {
    return "ERRORS".print_r($this, true);
  }
  
  function clear() {
    $this->exchangeArray(array());
  }
}

class BaseDataObject {
  private $deferred = array();
  
  function isDeferred($method) {
    return isset($this->deferred[$method]);
  }
  
  function __call($name, $args) {
    if($this->isDeferred($name)) {
      $object = $this->deferred[$name];
      return call_user_func_array(array($this->$object, $name), $args);
    } else if(substr($name, 0, 3) == "isA") {
      $class = substr($name, 3);
      return ($this instanceof $class);
    } else {
      throw new \Exception(get_class($this)."::$name not found.");
    }
  }
  
  /**
   * @param string $object Name of attribute to defer to
   * @param array $methods Array of methods to defer
   */
  function defer($object, $methods) {
    foreach($methods as $method) {
      $this->deferred["$method"] = $object;
    }
  }


  function transaction($closure) {
    return Database::getDB()->transaction($closure);
  }

//	public static function __callStatic($function, $args) {
//		if($this) {
//			die("WWWW");
//		}
//		$matches = array();
//		preg_match("/find_by_(.*)/", $function, $matches);
//		return call_user_func_array(array(DataObjectSearcher::getSearcher(), $function), $args);
//	}
}
class DataObject extends BaseDataObject implements \ArrayAccess, ErrorStore {

  static $primaryKey = "ID";

	protected $has_many = array();
	protected $has_one = array();
	
	var $data = array();
	private $oldData = array();
	public $fields = array();
  public $errors = null;

  public $update_blacklist = null;
  public $update_whitelist = null;

	
	
	function __construct($data = array()) {
	  foreach($data as $key => $val) {
	    $this[$key] = $val;
	  }

		$this->afterConstruct();
		$this->oldData = $this->data;
		$this->errors = new ErrorArray;
	}

  static function primaryKey() {
    return static::$primaryKey;
  }

  function getID() {
    if(static::primaryKey() == "ID") parent::getID();
    else {
      return $this[static::primaryKey()];
    }
  }

  function setID($id) {
    if(static::primaryKey() == "ID") {
      parent::setID($id);
    } else {
      $this[static::primaryKey()] = $id;
    }
  }
	
	static function VirtualFields() {
	  $ref = new \ReflectionClass(get_called_class());
	  
	  $fields = array();
	  foreach($ref->getMethods() as $method) {
	    if(substr($method->name, 0, 3) == "get") {
	      $fields[] = substr($method->name, 3);
	    }
	  }
	  
	  return array_diff($fields, array("Table", "Error", "MySQLDataHandler"));
	}
	
	function afterConstruct() {}
	
	public function hasRelation($relation) {
		if(isset($this->has_many[$relation])) {
			return "has_many";
		} else if(isset($this->has_one[$relation])) {
			return "has_one";
		} else if(isset($this->belongs_to[$relation])) {
			return "belongs_to";
		}
		return false;
	} 
	
	/**
	 * Grabs a has_many relationship for this DataObject
	 * @param string $relation
	 * @return Result
	 */
	public function has_many($relation) {
		if(isset($this->has_many[$relation]) ) {
			if(!is_array($this->has_many[$relation])) {
				$this->has_many[$relation] = array("table" => $this->has_many[$relation]);
			}
			
			$rel = $this->has_many[$relation];
			
			$primaryKey = isset($rel["primaryKey"]) ? $rel["primaryKey"] : static::primaryKey();
			$table = isset($rel["table"]) ? $rel["table"] : $relation;
			$foreignKey = isset($rel["foreignKey"]) ? $rel["foreignKey"] : null; 
			$class = isset($rel["class"]) ? $rel["class"] : $table;
			$conditions = isset($rel["conditions"]) ? $rel["conditions"] : array();
			$order = isset($rel["orderBy"]) ? $rel["orderBy"] : null;
			
      $collectionClass = $class."Collection";
      if(class_exists($collectionClass)) {
        $handler = new $collectionClass;
      } else {
        $collectionClass = 'Rex\Data\MySQLDataHandler';
        $handler = new $collectionClass($table, $class);
      }
			
			foreach($conditions as $column => $value) {
				$handler = $handler->filterOnColumn($column, $value);
			}

      if($order) $handler = $handler->orderOnly($order);

      if(isset($rel["polymorphic"]) && $rel["polymorphic"]) {
        $queryKey = $rel["polymorphic"];
        $handler->filterOnColumn($rel["polymorphic"]."Type", $this->getTable());
      } else {
        $queryKey = $foreignKey ?: $this->getTable();
      }

      if(stripos($queryKey, ".") === false) {
        $queryKey = "$table.$queryKey";
      }
        
        
      
			if(get_class($handler) != "MySQLDataHandler") {
        $findBy = "find_by_".$this->getTable();
        if(is_null($foreignKey) && method_exists($handler, $findBy)) {
          \Log::debug("$findBy(".$this[$primaryKey].")");
          return $handler->$findBy($this[$primaryKey]);
        } else {
          \Log::debug("filterOnColumn('$queryKey', {$this[$primaryKey]})");
          return $handler->filterOnColumn("$queryKey", $this[$primaryKey], $relation);
        }
//				return $handler->filter(array($relation => "$table.$foreignKey = '?'"), $this->{"get$primaryKey"}());
			} else {
				$result = $handler->select("$table.*")->type($class)->from($table)->filterOnColumn("$queryKey", $this[$primaryKey]);
				return $result;
			}
			//grab the relation. Maybe cache. 
			//TODO: Allow for handler to override
		} else {
			return null;
		}
	}
	
	/**
	 * 
	 * @param string $relation
	 * @return DataObject
	 */
	public function has_one($relation) {
		if(isset($this->has_one[$relation])) {
			$rel = $this->has_one[$relation];
			$foreignKey = isset($rel["foreignKey"]) ? $rel["foreignKey"] : "ID";
			$primaryKey = isset($rel["primaryKey"]) ? $rel["primaryKey"] : $relation;
			
			if(isset($rel["polymorphic"]) && $rel["polymorphic"]) {
				$table = $this[$primaryKey."Type"];
			} else {
				$table = isset($rel["table"]) ? $rel["table"] : $relation;
			}
			
			
			$class = isset($rel["class"]) ? $rel["class"] : $table;
			
			return $this->formFullTable($rel, $table, $primaryKey, $foreignKey, $class);
		} else {
			return null;
		}
	}
	
	public function belongs_to($relation) {
		if(isset($this->belongs_to[$relation])) {
			$rel = $this->belongs_to[$relation];
			$table = isset($rel["table"]) ? $rel["table"] : $relation;
			$primaryKey = isset($rel["primaryKey"]) ? $rel["primaryKey"] : "ID";
			$foreignKey = isset($rel["foreignKey"]) ? $rel["foreignKey"] : $this->getTable();
			$class = isset($rel["class"]) ? $rel["class"] : $table;
			
			$polymorphic = null;
			
			if(isset($rel["polymorphic"])) {
				if($rel["polymorphic"] === true) {
					$polymorphic = $this->getTable();
				} else {
					$polymorphic = $rel["polymorphic"];
				}
			}
			
			return $this->formFullTable($rel, $table, $primaryKey, $foreignKey, $class, $polymorphic);
		} else {
			return null;
		}
	} 
	
	function formFullTable($rel, $table, $primaryKey, $foreignKey, $class, $polymorphic=null) {
		
		$id = $this[$primaryKey];
		
		if($id instanceof DataObject) $id = $id->getID();
			$collection = $class."Collection";
			if(class_exists($collection)) {
				$handler = new $collection;
				$result = $handler->filterOnColumn("$table.$foreignKey", $id);
				if($polymorphic) {
					$result = $result->filterOnColumn("$table.{$foreignKey}Type", $polymorphic);
				}
				return $result;
			} else {
				$handler = new MySQLDataHandler($table, $class);
				$result = $handler->select("*")->from($table)->filterOnColumn($foreignKey, $id);
				if($polymorphic) {
					$result = $result->filterOnColumn("$table.{$foreignKey}Type", $polymorphic);
				}
				return $result;
			}
	}
	
	function buildRelation($rel, $data=array()) {
		$type = $this->hasRelation($rel);
		
		return $this->{$type}($rel)->build($data);
	}
	
	function __call($method, $params) {
	  if($this->isDeferred($method)) {
			return parent::__call($method, $params);
	  } else if (substr($method,0,3) == 'get'){
			$name = substr($method,3);
			if(isset($this->data[$name])) return $this->data[$name];
			return null;
				
		} else if (substr($method,0,3) == 'set'){
			$name = substr($method,3);
			$this->data[$name] = $params[0];
		} elseif(substr($method, 0, 5) == "unset") {
		  $name = substr($method, 5);
		  unset($this->data[$name]);
		} else if (substr($method,0,2) == 'db') {
			$name = substr($method, 2);
      $db = $this->{"get$name"}();

			return is_null($db) ? "" : $db;
		} elseif (substr($method,0,3) == 'has' && $relationType = $this->hasRelation(substr($method, 3))) {
      $rel = substr($method, 3);

      if(in_array($relationType, ["has_one", "belongs_to"])) {
        return !!$this->$rel();
      } else {
        return !!count($this->$rel());
      }
        

		} elseif (substr($method,0,5) == 'build' && $this->hasRelation(substr($method, 5))) {
			array_unshift($params, substr($method, 5));
			return call_user_func_array(array($this, "buildRelation"), $params); 	
		} else if($relationType = $this->hasRelation($method)){
			if(in_array($relationType, array("has_one", "belongs_to"))) {
				return $this->{$relationType}($method)->first();
			} else {
				return $this->{$relationType}($method);
			}	
		} else {
		  return parent::__call($method, $params);
		}
	}
	
	function __toString() {
	  $out = array();
	  foreach($this->data as $key => $value) {
	    if($value instanceof DataObject) {
	      $out[] = "$key: <".get_class($value)." #{$value['ID']}>";
      } elseif($value instanceof \DateTime) {
        $out[] = "$key: ".$value->format("Y-m-d H:i:s");
	    } elseif(is_array($value)) {
        $out[] = "$key: ".http_build_query($value);
      } else {
  	    $out[] = "$key: ".trim($value);
	    }
	  }

    $errors = [];
    foreach($this->errors as $key => $value) {
      $errors[] = "$key: '". trim($value)."'";
    }
	  
	  $out = implode(", ", $out);

    $errors = implode(", ", $errors);
	  
	  $out = "<{$this->getTable()} $out \n\r Errors: $errors> \n\r";
	  if(php_sapi_name() == "cli") $out = $out."\n\r";
	  else $out = htmlentities($out)."<br />";
	  
	  return $out; 
	}
	
	function Fields(){
		$values = array_merge(array_keys($this->data), $this->VirtualFields());
    return array_combine($values, $values);
	}
  
  static function hasFields($fields) {
    $class = get_called_class();
    $tmp = new $class();
    return $fields == array_intersect(array_values($tmp->fields), $fields);
  }
	
	function update($data, $extraValidator=null) {
		$oldData = $this->data;
    
    if(is_array($this->update_whitelist)) {
      $data = array_intersect_key($data, array_flip($this->update_whitelist));
    }
    if(is_array($this->update_blacklist)) {
      $data = array_diff_key($data, array_flip($this->update_blacklist));
    }

		foreach($data as $key => $value) {
			$method = "set$key";
			$this->$method($value);
		}
		
		$this->clearErrors();
    
    if(!$this->validate($extraValidator)) {
      \Log::info("Error validating: ".$this->__toString());
      return false;
    } else {
  		return $this;
    }
	}
	
	protected function storeDate($field, $date) {
	  if(!$date || $date == "0000-00-00" || $date == "0000-00-00 00:00:00" || $date == "0000-00-00 00:00") {
	    $this->data[$field] = null;
	  } else {
  		$this->data[$field] = \Rex\Helper\DateHelper::objectify($date);
	  }
	}
	
	function old($field) {
	  if($this->isNew()) {
	    return $this->data[$field];
	  } else {
  		return $this->oldData[$field];
	  }
	}
	
	function isDirty($field=null) {
		if(!$field) {
			return $this->data !== $this->oldData;
		} else {
      $data = @$this->data[$field] instanceof DataObject ? $this->data[$field]["ID"] : @$this->data[$field];
      $oldData = @$this->oldData[$field] instanceof DataObject ? $this->oldData[$field]["ID"] : @$this->oldData[$field];
			return $data != $oldData;
		}
	}
	
	function isEmpty() {
		return empty($this->data);
	}


  private $validations = [];
	
	function validate(\Validator $validator = null) {
	  $valid = true;
    
	  if($validator) {
	    $valid = $validator->validate($this->data, $this);
	  }
    
		return $valid && $this->Validator()->validate($this->data, $this) && $this->processValidations();
	}


  function processValidations() {
    foreach($this->validations as $validation) {
      $valid = call_user_func($validation, $this);
      if(!$valid) return false;
    }
    return true;
  }

  function registerValidation($callback) {
    $this->validations[] = $callback;
  }
	
	function Validator() {
		return new Validator(array());
	}


  function validateUniqueness($field, $scope = null) {
    $unique = $this->checkUniqueness($field, $scope);
    if($unique) { 
      $this->clearError($field);
      return true;
    } else { 
      if(!$scope) {
        $error = "The $field field must be unique.";
      } else {
        $error = "The $field field must be unique with it's $scope";
      }
      $this->storeError($field, $error);
      return false;
    }
  }

  function checkUniqueness($field, $scope = null) {
    if($scope) {
      $scopeColumn = $this->getTable().".". $scope;
    }

    $numbers = (new MySQLDataHandler($this->getTable()))->select("$field as UniqCheck")->from($this->getTable());
    $numbers = $numbers->where($this->getTable().".".$field." = '?'", $this[$field]);
    if(!$this->isNew()) {
      $numbers = $numbers->where($this->getTable().".ID <> ?", $this["ID"]);
    }
    if($scope) {
      $numbers = $numbers->filterOnColumn($scopeColumn, $this[$scope]);
    }

    return count($numbers) == 0;
  }

	
	function getTable() {
		return get_class($this);
	}
	
	function beforeCreate() {}
	function beforeUpdate() {}
	
	function afterCreate() {}
	function afterUpdate() {}
	
	protected function getMySQLDataHandler() {
    return new MySQLDataHandler($this->getTable());
	}
	
	protected function doSave($fields) {
		return $this->getMySQLDataHandler()->save($this, $fields);
	}
	
	function touch() {
	  return $this->forceSave();
	}
	
	function updateTimestamps() {
    
	  if($this->isNew()) {
			if(in_array("DateAdded", $this->fields)) $this->setDateAdded(date("Y-m-d H:i:s"));
		}
		if(in_array("DateModified", $this->fields)) $this->setDateModified(date("Y-m-d H:i:s"));
		
	}
	
	function forceSave($fields = null, $skipTimestamps = false) {
		if(is_null($fields)) $fields = $this->fields;
		if(empty($fields)) { throw new \Exception("The \$fields array is empty in the ".get_class($this)." class."); }
		$new = $this->isNew();
		
		if(!$skipTimestamps) $this->updateTimestamps();

    Database::getDB()->transaction(function() use ($new, $fields) {
      try {
        if($new) {
          $this->beforeCreate();
        } else {
          $this->beforeUpdate();
        }
        
        $this->beforeSave();
        $this->doSave($fields);
        $this->afterSave();
        
        if($new) {
          $this->afterCreate();
        } else {
          $this->afterUpdate();
        }
      } catch(\Exception $e) {
        if($new) $this[static::id()] = null;
        throw $e;
      }
        
      $this->oldData = $this->data;
      return true;
    });
    return $this;
	}

  function doValidation($fields) {
    $valid = $this->validate();

    if(is_null($fields)) {
      return $valid;
    } else {
      $errors = $this->errors->getArrayCopy();
      return count(array_intersect_key($errors, array_flip($fields))) == 0;
    }
  }


  function save($fields = null, $skipTimestamps = false, $skipValidate = false) {
    if(!$skipValidate && !$this->doValidation($fields)) {
      throw new \Exception(get_class($this)." ID: #".$this["ID"]." is invalid. " . $this->errors);
    }
		
    return $this->forceSave($fields, $skipTimestamps);
  }
		
	
	function delete() {
	  if($this->isNew()) return true;
	  
    Database::getDB()->transaction(function() {
  		$this->beforeDelete();
  		$return = $this->doDelete();
  		$this->afterDelete();
      $this["ID"] = null;
      return true;
    });
    return true;
	}
	
	protected function doDelete() {
		return $this->getMySQLDataHandler()->delete()->where("ID = ?", $this->getID())->go();
	}
	
	function beforeSave() {}
	function afterSave() {}
	
	function beforeDelete() {
	  foreach($this->has_many as $relationship => $details) {
	    if(isset($details["delete"]) && $details["delete"] == false) {
	      if(count($this->$relationship()) > 0) throw new DeletionException("Can't delete if $relationship has any members.");
	    }
  	  if(isset($details["delete"]) && $details["delete"] == true) {
  	    foreach($this->$relationship() as $relative) {
  	      $relative->delete();
  	    }
  	  }
	  }
	}
  
  function afterDelete() {}
  
	function isNew() {
		return (true != $this->getID());
	}
	
	function refresh() {
	  return static::find_by_id($this["ID"]);
	}
	
	function onChange($key) {
		//do nothing
	}

  function toArray($keys=null) {
    $data = array();
    
    if (!$keys) {
      $keys = array_keys($this->data);
    }
    
    foreach($keys as $key) {
      if (is_object($this[$key])) {
        if($this[$key] instanceof DateTime) {
          $this[$key] = $this[$key]->format("r");
        } else {
          $this[$key] = $this[$key]["ID"];
        }
      }
      $data[$key] = $this[$key];    
    } 

    return $data;
  }


  function toJson($keys=null) {
    return json_encode($this->toArray($keys) + array("errors" => $this->errors));
  }
	
	public function offsetSet($offset, $value) {
		$this->onChange($offset);
  	$this->{"set$offset"}($value);
  }
  
	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}
	
	public function offsetUnset($offset) {
	  $this->{"unset$offset"}();
	}
	
	public function offsetGet($offset) {
		return $this->{"get$offset"}();
	}
	
	public function attrs() {
		$keys = array_keys($this->data);
		
		$reflection = new ReflectionClass(get_class($this));
		$doReflection = new ReflectionClass("DataObject");
		
		foreach($reflection->getMethods() as $method) {
			if(substr($method->name, 0, 3) == "get") {
				if(!$doReflection->hasMethod($method)) {
					$keys[] = substr($method->name, 3);
				}
			}	
		}
		
		return $keys;
	}
	
	static function all() {
		$class = get_called_class();
		$collection = $class."Collection";
		return new $collection;
	}
	
	static function find_by_id($id) {
		return DataObjectSearcher::find_by_id(get_called_class(), $id);
	}


  function duplicate() {
    $class = get_class($this);
    return new $class(array_diff_key($this->data, array_flip(["ID"])));
  }

	
	function reload() {
		return static::find_by_id($this["ID"]);
	}
	
	function storeError($field, $error) {
    \FormHelper::setError($field, $error);
		$this->errors[$field] = $error;
	}

  function clearError($field) {
    if(isset($this->errors[$field])) unset($this->errors[$field]);
  }
	
	function clearErrors() {
	  $this->errors->clear();
	}
  
  function allErrors() {
    return $this->errors;
  }
	
	function getError($field) {
		return isset($this->errors[$field]) ? $this->errors[$field] : null;
	}
	
	public static function __callStatic($function, $args) {
		$matches = array();
		preg_match("/find_by_(.*)/", $function, $matches);
		
		$collection = get_called_class()."Collection";
		
		if(!class_exists($collection)) throw new Exception("$collection does not exist!");
		
		return call_user_func_array(array(new $collection, $function), $args);
	}
}

class DataObjectSearcher {
	static $searcher;
	
	static function find_by_id($table, $id) {
		return self::getSearcher()->find_by_id($table, $id);
	}
	
	static function setSearcher(BaseDataObjectSearcher $searcher) {
		self::$searcher = $searcher;
	}
	
	static function getSearcher() {
		if(self::$searcher) {
			return self::$searcher;
		} else {
			return new BaseDataObjectSearcher;
		}
	}
	
	
	function parseFunction($function) {
		$args = func_get_args();
		$matches = array();
		if(preg_match("/find_by_(.*)/", $function, $matches)) {
			$columns = array();
			if(preg_match('/^([^_]*)_(and|or)_([^_]*)$/', $matches[1], $columns)) {
				if($columns[2] == "or") {
					//Incomplete
					throw new Exception("incomplete");
				} else {
					return array($columns[1] => $args[1], $columns[3] => $args[2]);
				}
			} elseif (preg_match('/^([^_]*)$/', $matches[1], $columns)) {
				return array($columns[1] => $args[1]);
			}
		}
	}
}

class BaseDataObjectSearcher {
	
  /**
   * @return Collection
   */
	function getCollection($table) {
		$collectionType = "{$table}Collection";
    $collection = null;
		if(class_exists($collectionType)) {
			$collection = new $collectionType;
		}
		return $collection;
	}

	function find_by_id($table, $id) {
		return $this->getCollection($table)->find_by_id($id);
	}
}

class DataObjectCache { 
	private static $cache = array();
	protected static function key($arg1, $arg2=null) {
		if($arg1 instanceof DataObject) {
			return get_class($arg1).$arg1["ID"];
		} else {
			return $arg1.$arg2;
		}
	}
	
	static function set($object) {
		self::$cache[self::key($object)] = $object;
		return $object;
	}
	
	static function get($class, $id) {
		return isset(self::$cache[self::key($class, $id)]) ? self::$cache[self::key($class, $id)] : null;
	}
	
	static function flush() {
		self::$cache = array();
	}
}

