<?php 

namespace Rex\Data;

class Collection extends MySQLDataHandler {
  function __toString() {
    $ret = "";
    foreach($this as $i) $ret .= $i->__toString();
    return $ret;
  }

  function deleteEach() {
    
    Database::getDB()->transaction(function() {
      foreach($this as $object) {
        $object->delete();
      }
      return true;
    });
    
  }
  
	function update($values) {
		$uH = \HandlerFactory::generate('Rex\Data\MySQLUpdateHandler');
		$uH->from = $this->from;
		$uH->joins = $this->joins;
		
		$uH->where = $this->where;
		
		$uH->values = $values;
		return $uH;
	}
	
	function firstOrBuild() {
	  $object = $this->first();
	  if(!$object) {
	    return $this->build();
	  } else {
	    return $object;
	  }
	}
  
  function find_or_build_by_id($id) {
    $ret = $this->find_by_id($id);
    
    if(!$ret) return $this->build();
    else return $ret;
  }
	
	function defaultScope() {
	  
	}
	
	function __construct($table = '', $type = '') {
	  parent::__construct($table, $type);
	  $this->defaultScope();
	}
  
  function newest() {
    return $this->order("$this->from.DateAdded", "DESC")->first();
  }
}

class SortableCollection extends Collection {
  var $sortableCol = "Sort";
  
  function build($data = array()) {
    $split = explode(".", $this->sortableCol);
    $sortableCol = array_pop($split);
    return parent::build($data + array($sortableCol => $this->getNextSort()));
  }
  
  function defaultScope() {
    parent::defaultScope();
    $this->order($this->sortableCol, "ASC");
  }
  
  function sort($order) {
    Database::getDB()->transaction(function() use ($order) {
      foreach($order as $pos => $id) {
        $object = $this->find_by_id($id);
        if($object[$this->sortableCol] != $pos) {
          $object[$this->sortableCol] = $pos;
          $object->save([$this->sortableCol], false, true); //Be wary of skipping validations, only do it if the array is constrained
        }
      }

      $this->afterSort();
      return true;
    });
    
    return true;
  }

  function afterSort() {}

  function MaxSort() {
    return $this->aggregate("MAX", $this->sortableCol);
  }
  
  function getNextSort() {
    $max = $this->MaxSort();
    if($max === "") return 0;
    else return (int)$max + 1;
  }
}
