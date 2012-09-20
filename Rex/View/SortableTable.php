<?php

namespace Rex\View;

class SortableTable extends Table {
      
  function __construct($display, $data = null, $app = null) {
    $this->id = "sortable";
    $this->addColumn("DragIcon");
    
    parent::__construct($display, $data, $app);
  }
  
  function getColumnHeading($key) {
    if($key == "DragIcon") {
      return "";
    }
    
    return parent::getColumnHeading($key);
  }
  
  function getData($key, $row, $classes="") {
    if($key == "DragIcon") {
      return '<img height="16" width="16" class="handle" src="/im/new-icons/resize-vertical.png" title="Click and drag" />';
    }
    
    return parent::getData($key, $row, $classes);
  }
  
}
