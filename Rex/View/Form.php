<?php 

namespace Rex\View;

class Form extends Fieldset {
	
  private $hasFieldset = false;
  public $style = '';
  private $size = '';
  
	function __construct($action="", $method="", $app=null, $id="") {
		parent::__construct("", $app);
		$this->action = $action;
		$this->method = $method;
    $this->id = $id;    
	}
  
  function setSize($size) {
    if ($size == 'large') {
      $this->size = 'large';
    }
  }
  
	function __toString() {
    if(isset($this->extras["class"]) && $this->extras["class"] != null) {
      $class = $this->extras["class"];
    } else {
      $class = $this->class;
    }
    
		$output = "<form accept-charset='utf-8' ";
		if ($this->id != "") $output .= "id='".$this->id."' ";
		$output .= "action='".$this->link($this->action)."' method='".$this->method."' ".($this->isMultipart() ? 'enctype="multipart/form-data"' : ""). " class='$class'>";
    
    if(count($this->fields) != 0) {
      $output .= parent::__toString();
    }
    
    
    $output .= $this->buttons();
		$output .= "</form>";	
		
		return $output;
	}
  
  function buttons() {
    $output = "<fieldset class='buttons'><ol>";
    foreach($this->submits as $name => $value) {
      $output .= '<li class="'. $name .'">';
      $extras = isset($this->extras[$name]) ? $this->extras[$name] : array();
      
      $type = isset($extras["type"]) ? $extras["type"] : "submit";
      
      $output .= $this->$type($name, $value, $extras);
      
      $output .= '</li>';
    }
    return $output."</ol></fieldset>";
    
  }
  
  static function addPrefix($name, $prefix="") {
    if(substr($name, -2, 2) == "[]") {
      $name = substr($name, 0, strlen($name)-2);
      return $prefix."[$name][]";
    } else {
      return $prefix."[$name]";
    } 
  }
	
}

class MultipleDescriptionForm extends Form {
  function selecttext($key, $value, $extras=array()) {
    $default = @$extras["default"] ?: "";
    return $this->select($key, $this->getValue($key), array('options' => $extras['options'], 'id' => $key.'Select')).
      $this->text($key, $this->getValue($key), array('id' => $key.'Text', "value" => $default));
  }
}
