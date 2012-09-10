<?php


namespace Rex\Data;

class PseudoArray extends \ArrayIterator {
	function __construct($data = array()) {
		if(!$data) $data = array();
		parent::__construct($data);
	}
  
	public function get($index){
		if(isset($this[$index])) return $this[$index];
	}
	
	public function first() {
		return $this->get(0);
	}
	
	public function last() {
		return $this->get(count($this)-1);
	}
  
  public function isEmpty() {
    return count($this) == 0;
  }

  public function merge(PseudoArray $merge) {
		$data = array_merge($this->getArrayCopy(), $merge->getArrayCopy());
		$class = get_class($this);
		return new $class($data);
	}
	
	public function filter($function) {
		$filter = new FunctionFilterIterator(clone $this, $function);
		$return = array();
		foreach($filter as $i) {
			$return[] = $i;
		}
		
		$class = get_class($this);
		return new $class($return);
	}


  public function map($function) {
    return array_map($function, $this->getArrayCopy());
  }


  public function each($function) {
    foreach($this as $i => $object) {
      $function($object, $i);
    }
    return $this;
  }
}


namespace Rex\Data;
class Result extends PseudoArray {
	private $position = 0;
	
  function Fields() {
    return $this->get(0)->Fields();
  }
	
	public function toArray($value="Name", $key="ID") {
		$data = array();
		foreach($this as $item) {
			$aKey = @$item[$key];
			
			if(!$value instanceof \Closure) {
				$addValue = $item[$value];
			} else {
				$addValue = $value($item);
			}
			
			if(!is_null($aKey)) {
				$data[$aKey]= $addValue;
			} else {
				$data[] = $addValue;
			}
		}
		return $data;
	}
	
	
	private function searcher($column, $value) {
		return function($item) use ($column, $value) {
			return $item[$column] == $value;
		};
	}
	
	public function filterOnColumn($column, $value) {
		return parent::filter($this->searcher($column, $value));
	}
	
	function find($column, $value, $remove=false) {
		$clone = clone $this;
		$searcher = $this->searcher($column, $value);
		foreach($clone as $i => $item) {
			if($searcher($item) == true) {
				if($remove) unset($this[$i]);
				return $item;
			}
		}
		return null;
	}
	
	function contains(DataObject $object) {
		$result = $this->filter(function($item) use ($object) {
			return (get_class($object) == get_class($item)) && ($item["ID"] == $object["ID"]);
		});
		
		return count($result) > 0;
	}
	
	public function split($column) {
		$result = array();
		foreach($this as $item) {
			$val = $item[$column];
			if(!isset($result[$val])) $result[$val] = new Result();
			$result[$val][] = $item;
		}
		return $result;
		$return = array();
		foreach($result as $i => $res) {
			$return[$i] = new Result($res);
		}
		return $return;
	}
	
	function delete() {
	  foreach($this as $i) {
	    $i->delete();
	  }
	  return count($this);
	}
}

class FunctionFilterIterator extends \FilterIterator {
	private $userFilter;
   
	public function __construct(\Iterator $iterator , $filter ) {
		parent::__construct($iterator);
		$this->userFilter = $filter;
	}
   
	public function accept() {
		$item = $this->getInnerIterator()->current();
		
		return call_user_func($this->userFilter, $item);
	}
}
