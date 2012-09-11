<?php

namespace Rex\View;

use Rex\Data\DataObject;
use Rex\Helper\DateHelper;

class Fieldset extends BaseFragment {
  
  protected $extras = array();
  protected $fields = array();
  protected $labels = array();
  protected $values = array();
  protected $submits = array();
  protected $errors = array();
  
  public $class = 'actionForm';
  public $id;
  public $title = "";
  
  private $name = "";
  
  function __construct($name="", $app= null) {
    parent::__construct($app);
    $this->name = $name;    
  }
  
  function addHintText($text) {
    return '<br /><span class="information">' . $text . '</span>';
  }
    
  function row($field, $extras = array()) {
    if($this->fields[$field] == "hidden") return $this->field($field, $extras);
    
    $class = @$extras["class"];
    
    $fieldName = strtolower($field."Item");
    $id = isset($extras["id"]) ? $extras["id"] : "";
    $class = isset($extras["class"]) ? $extras["class"] : "";
    $output = "<li id='$id' class='$fieldName $class'>";
    
    if(!(isset($extras["headings"]) && !$extras["headings"]) && $this->fields[$field] != "button") {
      $output .= "<label>" . $this->label($field) . "</label>";
    }
    
    $output .= $this->field($field, $extras);
    if (isset($extras["help"])) {
      $output .= $this->addHintText($extras["help"]); //'<br /><span class="information">' . $extras["help"] . '</span>';
    }
    
    $output .= $this->errorPlaceholder($field);

    if (isset($extras["description"])) {
      $output .= '<span class="description">' . $extras["description"] . '</span>';
    }
    
    $output .= "</li>";
    
    return $output;
  }
  
  function fields() {
    $output = "";
    foreach($this->fields as $field => $type) {
      $output .= $this->row($field, @$this->extras[$field]);
    }
    return $output;
  }
  
  function __toString() {
    if(get_class($this) == __CLASS__) {
      $id = self::parseID($this->name);
      $class = $this->class;
    } else {
      $id = "";
      $class = "";
    }
    
    
    $output = "\n\r<fieldset id='$id' class='$class'>";
    if($this->title) {
      $output .= "<legend>".$this->title."</legend>";
    }
    
    $output.="<ol>";
    $output .= $this->fields();
    
    $output .= "</ol></fieldset>\n\r";
    
    return $output;
  }
  
  function addHeading($key, $value, $type='h3') {
    $this->fields[$key] = $type;
    $this->values[$key] = $value;
  }

  function hasField($key, $type = "") {
    return isset($this->fields[$key]) && (!$type || $this->fields[$key] == $type);
  }
  
  function addField($key, $type="text", $extra = array()) {
    $this->fields[$key] = $type;
    if(!empty($extra)) {
      $this->addExtra($key, $extra);
    }
  }
  
  function addExtra($key, $extra) {
    $this->extras[$key] = array_merge($extra, isset($this->extras[$key]) ? $this->extras[$key] : array());
  }
  
  function addLabel($key, $label) {
    $this->labels[$key] = $label;
  }
  
  function addValue($key, $value) {
    $this->values[$key] = $value;
  }
  
  function importValidator(Validator $val) {
    foreach ($val->rules["Required"] as $rule) {
      $this->addExtra($rule, array("class" => "required"));
    }
  }
  
  function import(DataObject $obj = null) {
    if(!$obj) return;
    $this->importValues($obj->data);
    $this->importErrors($obj->errors);
  }
  
  function importValues($values) {
    $this->values = array_merge($this->values, $values);
  }
  
  function importErrors($errors) {
    foreach($errors as $k => $error) {
      $this->errors[$k] = $error;
    }
  }
  
  function addSubmit($name, $value, $extras = array()) {
    $this->submits[$name] = $value;
    $this->addExtra($name, $extras);
  }
  
  function label($field) {
    if(isset($this->labels[$field])) {
      return $this->labels[$field];
    } else {
      return ucfirst($this->from_camel_case($field));
    }
  }
  
  function field($key, $extras = array()) {
    $fieldtype = $this->fields[$key];
    
    if ($fieldtype instanceof Fieldset) {
      return $fieldtype->__toString();
    } else {
      if (get_class($this) == "Fieldset" && $this->name) {
        $name = isset($extras["name"]) ? $extras["name"] : $key;
        
        $extras["name"] = Form::addPrefix($name, $this->name);
      }
      return $this->$fieldtype($key, $this->getValue($key), @$extras);      
    }
  }
  
  function getError($key) {
    if(isset($this->errors[$key])) {
      return $this->errors[$key];
    } else {
      return "";
    }
  }
  
  function errorPlaceholder($key) {
    $error = $this->getError($key);
    if($error) {
     return '<span class="fielderror">'.$error.'</span>';
    } else {
      return "";
    }
  }
  
  function isMultipart() {
    return in_array("file", $this->fields) || in_array("image", $this->fields);
  }
  
  function parseKey($key) {
    return strtr($key, array("[]" => ""));
  }
  
  function getValue($key) {
    $key = $this->parseKey($key);

    if(preg_match("#^([^\[]*)\[([^\]]*)\]$#", $key, $matches)) {
			return @$this->values[$matches[1]][$matches[2]];
    } elseif(isset($this->values[$key])) {
      return $this->values[$key];
    }
    return null;
  }
  
  static function input($name, $value, $type, $extras=array()) {
    if(!$extras) $extras = array();
    
    $name = isset($extras["name"]) ? $extras["name"] : $name;
    $id = self::parseID(isset($extras["id"]) ? $extras["id"] : $name);
    
    $value = !is_null($value) ? $value : @$extras["value"];
    
    return "<input ".self::attrs(["name" => $name, "type" => $type, "id" => $id, "value" => $value]).self::attrs($extras, array("name", "type", "id", "value"))." />";
  }
  
  static function text($name, $value, $extras=array()) {
    return self::input($name, $value, "text", $extras);
  }
  
  static function radioSet($name, $value, $extras=array()) {
    $output = "";
    if (isset($extras["name"]) && $extras["name"]) {
      $name = $extras["name"];
    }
    
    if (isset($extras["id"])) {
      $id = $extras["id"];
    } else {
      $id = $name;
    }

    $output = "<fieldset class='checkList'>";
    $output .= "<ol>";

    $class = "";
    
    if(isset($extras["class"]) && $extras["class"]) {
      $class = $extras["class"];
    }

    $classes = explode(" ", $class);
    
    if(@$extras["options"]) foreach($extras["options"] as $key => $label) {
      $checked = $key == $value;
      $output .= "<li>";  
      $output .= "<label>";
      $extras = array("value" => $key, "checked" => $checked, "id" => $id."[".$key."]");
      if(isset($class) && $class) {
        $extras["class"] = $class;
      }
      $output .= Form::radio($name, $key, $extras);
      
      if(!in_array("hover-star", $classes)) { //This is horrible
        $output .= "$label";
      }
      $output .= "</label>";
      $output .= "</li>";
    }
    
    $output .= "</ol></fieldset>";
    
    return $output;
  }
  
  static function radio($name, $value, $extras=array()) {
    return self::input($name, $value, "radio", $extras);
  }
  
  static function image($name, $value="", $extras=[]) {
    $preview = '';
    if(isset($extras["ImageLoc"]) && $extras["ImageLoc"]) {
      $preview = $extras["ImageLoc"]->img(["height" => 100]) . "<a class='btn btn-danger remove-upload'><i class='icon-remove icon-white'></i></a>";
    }
    return self::file($name, $value, $extras). self::hidden('remove'.$name, false) . "<div class='preview-upload clearfix'>$preview</div>";
  }
  
  static function file($name, $value = "", $extras = array()) {
    return self::input($name, $value, "file", $extras);
  }
  
  static function select($name, $value, $extras=array()) {
    if(!@$extras["options"]) {
      $options = array();
    } else {
      $options = @$extras["options"];
    }
    unset($extras["options"]);
    
    if(!@$extras["attributes"]) {
      $attributes = array();
    } else {
      $attributes = @$extras["attributes"];
    }
    unset($extras["attributes"]);
    
    if (isset($extras["name"])) {
      $name = $extras["name"];
    }
    
    if (isset($extras["id"])) {
      $id = $extras["id"];
    } else {
      $id = self::parseID($name);
    }
    
    $attributes = array("name" => $name, "id" => $id) + $attributes;
    
    if(isset($extras["multiple"]) && $extras["multiple"]) {
      $attributes["name"] .= '[]';
      $attributes["multiple"] = "multiple";
    }
    
    return self::tag("select", $attributes, self::options($options, $value, @$extras["numkeys"]));
  }
  
  static function textarea($name, $value, $extras = array()) {
    $extras = (is_array($extras) ? $extras : array()) + array("name" => $name, "rows" => 8, "cols" => 60);
    if(!isset($extras["id"])) { $extras["id"] = $name; }
    return self::tag("textarea", $extras, $value, true);
  }
  
  static public function date($name, $value, $extras = array()) {
    if($time > 200) {
      $value = DateHelper::format("d/m/Y", $value);
    } else {
      $value = "";
    }
    
    return self::input($name, $value, $extras = array());
  }
  
  function fragment($name, $value, $extras = array()) {
    return $this->renderFragment($extras["fragment"], array("Form" => $this) + $extras, true);
  }
  
  static function tag($type, $attributes= array(), $contents=null, $close=false) {
    $output = "<$type ".self::attrs($attributes);
    if(is_null($contents) && !$close) {
      $output .= " />";
    } else {
      $output .= ">$contents</$type>";
    }
    return $output;
  }
  
  static function options($values, $selected=null, $numKeys = false) {
    $output = "";
    $useVal = false;
    
    if ((range(0,count($values)-1) === array_keys($values)) && !$numKeys) $useVal = true;
          
    foreach ($values as $key => $value){
      $index = $useVal ? $value : $key;
      
      $attributes = array("value" => $index);
      
      if ((is_array($selected) && in_array($index, $selected)) || ((!is_null($selected) && $selected == $index))) {
        $attributes["selected"] = "selected";
      }
      
      $output .= self::tag("option", $attributes, $value);
    }
    return $output;
    
  }
  
  static function parseID($id) {
    return trim(strtr($id, array("[" => "_", "]" => "", " " => "_")), "_");
  }
  
  
  static function submit($name, $value, $extras = array()) {
    $value = $value ? $value : ucfirst($name);
    $class = " btn btn-primary";

    if(isset($extras["class"])) {
      $extras["class"] .= $class;
    }
    
    return self::input($name, $value, "submit", $extras ? $extras : array() + array("class" => $class));
  }
  
  static function button($name, $value, $extras = array()) {
    return self::input($name, $value, "button", $extras);
  }

  static function a($name, $value, $extras = array()) {
    return self::tag("a", $extras, $value);
  }
  
  static function checkbox($name, $value, $extras=array()) {
    if(!isset($extras["value"])) {
      $extras["value"] = 1;
    }
    
    if($extras["value"] == $value) $extras["checked"] = "checked";
    $out = "";
    
    if(!isset($extras["null"]) || (isset($extras["null"]) && !$extras["null"])) {
      $out .= self::hidden($name, "", array("id" => ""));
    }
    
    return $out.self::input($name, $extras["value"], "checkbox", $extras);
  }
  
  static public function checkList($name, $value, $extras = array()) {
    
    if (isset($extras["name"]) && $extras["name"]) {
      $name = $extras["name"];
    }

    $output = "<fieldset class='checkList'>";
    $output .= self::hidden($name, "");
    //echo self::errorPlaceholder($name);
    //self::setError($name, "");
    $output .= "<ol>";
    
    foreach($extras["options"] as $key => $label) {
      $output .= "<li><label>";
      
      $checked = is_array($value) && (in_array($key, $value) || isset($value[$key]));
      
      $output .= self::checkBox($name."[]", "", array("value" => $key, "checked" => $checked, "id" => $name."[".$key."]", "null" => true));
      $output .= " $label</label></li>";
    }
    
    $output .= "</ol></fieldset>";
    return $output;
  }
  
  static public function nestedCheckList($name, $value, $extras = array()) {
    // requires the format array("Object" => array(ObjectClass => array(Nested Items)));
    // If there are no nested items, the array is replaced by the ObjectClass
    
    $error = false;
    
    if (isset($extras["name"]) && $extras["name"]) {
      $name = $extras["name"];
    }
    
    $output = "<fieldset class='nestedCheckList'><ol>";
    
    if(count($extras["options"]) == 1) {
      foreach($extras["options"] as $type => $children) {
        if(is_string($type) && is_array($children)) {
          foreach($children as $child) {
            if(is_object($child) || (isset($child["ID"]) && isset($child["Name"]))) {
              $object = $child;
              $nest = null;
            } else {
              $object = $child[0];
              $nest = $child[1];
            }
            
            $label = $object["Name"];
            
            $output .= "<li>";
            
            if(isset($value[$type]) && in_array($object["ID"], $value[$type])) {
              $checked = 1;
            } else {
              $checked = 0;
            }
            
            $for = self::parseID($name."[".$type."[".$object["ID"]."]]");
            
            $output .= self::checkBox($name.'['.$type."][]", "", array("class" => $type, "value" => $object["ID"], "checked" => $checked, "id" => $name."[".$type."[".$object["ID"]."]]", "null" => true));
            $output .= "<label for=$for>$label</label>";
            
            if($nest) {
              $output .= self::nestedCheckList($name, $value, array("options" => $nest));
            }
            $output .= "</li>";
          }
        } else {
          $error = true;
        }
      }
    } else {
      $error = true;
    } 
    
    
    if($error){
      user_error(INVALID_NESTED_CHECKLIST_FORMAT, E_USER_ERROR);
    }
    
    $output .= "</ol></fieldset>";
    
    return $output;
  }
  
  static function hidden($name, $value, $extras= array()) {
    return self::input($name, $value, "hidden", $extras);
  }
  
  static function h3($name, $value, $extras = array()) {
    return self::tag("h3", array("class" => $name), $value);
  }
  
  
}
