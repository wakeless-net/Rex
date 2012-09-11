<?php

namespace Rex\View;

class Tag extends BaseFragment {
  
  private $attributes = array();
  
  function __construct($app=null) {
    parent::__construct($app);
  }
  
  function addAttribute($attr, $value) {
    $this->attributes[$attr] = $value;
  }
  
  function getAttributes() {
    return $this->attributes;
  }
  
  function attributes() {
    $output = self::attrs($this->attributes);
    
    return $output;
  }
  
}
