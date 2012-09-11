<?php 

namespace Rex\View;

use Rex\Data\MySQLDataHandler;
use Rex\Helper\FormHelper;

class Table extends Tag {
  public $data;
  public $name;
  
	protected $display = array();
	protected $classes= array();
	private $sort = array();
	private $actions = array();
  
  private $progress = array();
  
	private $conditionalActions = array();
  
  private $attributes;

	private $conditionalRowClasses = array();
	
	public $fieldEdit = true;
  private $fields = null;
	
	private $formUrl = "";
	
	private $check = array();
	private $tasks = array();
  
	
	private $limit = 0;
	
	public $title = "";
  public $id = "";
	
	function __construct($display, $data = null) {
    parent::__construct();
		$this->addColumns($display);

    $this->name = get_class($this);
    if ($data != NULL){
      $this->data = $data;
    }
	}
	
	function descriptionise($header) {
		return $this->from_camel_case($header);
	}
	
	function addColumn($column, $desc="") {
		if(!$desc) $desc = $this->descriptionise($column);
		$this->display[$column] = $desc;
	}
	
	function addColumns($columns) {
		foreach($columns as $column => $header) {
			if(is_numeric($column)) {
			  $this->addColumn($header);
			} else {
			  $this->addColumn($column, $header);
			}
		}
	}
	
	function setLimit($limit) {
		$this->limit = $limit;
	}
	
	function getPage() {
		if(isset($_GET["page"])) {
			return $_GET["page"];
		} else {
			return 0;
		}
	}
	
	function addCheckBox($name, $attr) {
		$this->check = array();
		
		$this->check[$name] = $attr;
	}
	
	function getColumnHeading($key) {
	  $display = $this->displayColumns();
	  return $display[$key];
	}
	
	function header() {
		$out = "";
		if($this->title) {
			$out .= "<h4>".$this->title."</h4>";
		}
		$out .= "<tr class='itemlistheader'>";
		foreach($this->displayColumns() as $key => $header) {
			if(is_numeric($key)) {
			  $key = $header;
  			$header = $this->descriptionise($header);
			} else {
			  $header = $this->getColumnHeading($key);
			}
			if($column = $this->getSort($key)) {
        $link = SortHelper::link($this, $column);
				$header = "<a href='$link'>$header</a>";
			}
			
			$out .= "<th class='".strtolower($key)."'>$header</th>";
		}
		if($this->check) {
			$out.= "<th width='12px'><input id='SelectAll1' type='checkbox' name='SelectAll' value='1' /></th>";
		}
		
    $fieldOut = "";
		if($this->fieldEdit && $this->data instanceof MySQLDataHandler) {
	    $fieldOut = FieldEdit::show($this->data->Fields());
		}
		
		if($this->actions || $this->fieldEdit) {
  		$out .= "<th class='actions'>$fieldOut</th>";	
		}
		
		$out .= "</tr>\n\r";
		return $out;
	}
  
	function row($row) {
	  $rowClasses = $this->getRowClasses($row);
	  $rowClass = is_array($rowClasses) ? implode(" ", $rowClasses) : "";
    
		$out = "<tr id='row_".self::dom_id($row)."' class='item ";
    $out .= " $rowClass'>";

		foreach($this->displayColumns() as $key => $header) {
			if(is_numeric($key)) $key = $header;
			$class = $this->getClasses($key);
      
			$data = $this->getData($key, $row, $class);
			
			if($link = $this->getLink($key, $row)) {
				$data = "<a href='$link'>$data</a>";
			}
	
			$out .= "<td class='$class'>";
      $out .= $data;
      $out .= "</td>";
		}
		$actions = $this->actions($row);
    
		
		if(count($this->check) > 0) {
			foreach($this->check as $name => $attr) {
				$out .= "<td><input type='checkbox' name='{$name}[]' value='{$row[$attr]}' /></td>"; 	
				
			}
		}
		
		if($actions || $this->fieldEdit) {
			$out.= "<td class='actions'><div class='actions'>$actions</div></td>";
		}
			
			
		$out .= "</tr>\n\r";
		return $out;
	}
	
	function addConditionalRowClass($condition, $class) {
	  $this->conditionalRowClasses[$class] = $condition;
	}
	
	function getRowClasses($row) {
	  $classes = array();
	  foreach($this->conditionalRowClasses as $class => $condition) {
	    if($this->testCondition($row, $condition)) $classes[] = $class;
	  }
	  return $classes;
	}
	
	function addSort($column, $key = null) {
		if(is_array($column)) {
			$this->sort = array_merge($this->sort, $column);
		} else {
			if($key) {
				$this->sort[$key] = $column;
			} else {
				$this->sort[] = $column;
			}
		}
		return $this;
	}
	
	function addAction($name, $url) {
		$this->actions[$name] = $url;
	}

	function addConditionalAction($name, $url, $conditional) {
		$this->addAction($name, $url);
		$this->conditionalActions[$name] = $conditional;
	}
	
	function actions($row) {
		$out = "";
		foreach($this->actions as $name => $url) {
			$out .= $this->getAction($name, $url, $row);
		}
		return $out;
	}
	
	function testCondition($row, $condition) {
    if(is_string($condition) ) {

      $not = ($condition[0] == "!");
      
      if($not) $condition = substr($condition, 1);
      
      return ($not != $row->$condition());
    } elseif(is_callable($condition)) {
      return $condition($row);
    } else {
      return false;
    }
  }


	
	function getAction($name, $url, $row) {
		$condition = @$this->conditionalActions[$name];
		
		if(is_null($condition) || $this->testCondition($row, $condition)) {
			$class = strtolower($name);
			if(file_exists("im/icons/".strtolower($name).".gif")) $name = "<img src='/im/icons/".strtolower($name).".gif' title='$name' alt='$name' />";
			return "<a class='$class' href='".$this->evalLink($row, $url)."'>$name</a>";
		}
	}
	
	function getSorts() {
		if(empty($this->sort) && $this->data instanceof MySQLDataHandler) {
  	  return $this->sort = $this->data->Fields()->toArray("Field", "FullName");
	  } else {
	    return $this->sort;
	  }
	}
	
	function getSort($key) {

    if(substr($key, -1) == "?") $key = substr($key, 0, -1);

		$sorts = array_flip($this->getSorts());
		if(isset($sorts[$key])) {
			$column = $sorts[$key];
			if(is_numeric($column)) {
				return $key;
			} else {
				return $column;
			}
		}
		return false;
	}
	
	function addClass($key, $class) {
		if(is_array($key)) foreach($key as $column) {
			$this->classes[$column][] = $class;
		} else {
			$this->classes[$key][] = $class;
		}
		return $this;
	}
	
	function getClasses($key) {
		if(isset($this->classes[$key])) {
			return implode($this->classes[$key], " ");
		} else {
			return "";
		}
	}
	
	/**
	 * Adds a link to that column provide $columnName and evalable $link.
	 * 
	 * @param $key
	 * @param $link
	 */
	function addLink($key, $link) {
		if(is_array($key) ) foreach($key as $k) {
			$this->links[$k] = $link;
		} else {
			$this->links[$key] = $link;
		}
	}
	
	private function evalLink($row, $link) {
	  if(is_string($link)) {
  		if($row && $row instanceof Rex\Data\DataObject) extract($row->data);
  		
  		$ret = eval('return "'.$link.'";');
      return $ret;

	  } elseif(is_callable($link)) {
	    return $link($this, $row);
	  }
	}
	
	function getLink($key, $row) {
		if(isset($this->links[$key])) {
			return $this->evalLink($row, $this->links[$key]);
		} else {
			return false;
		}
		return isset($this->links[$key]) ? $this->links[$key] : false;
	}
	
	function getData($key, $row, $classes="") {

    $bool = false;
    $extra = "";
    if(substr($key, -1) == "?") {
      $bool = true;

      $key = substr($key, 0, -1);

      if(method_exists($row, "is$key") ) {
        $func = "is$key";
        $data = $row->$func();
      } elseif(method_exists($row, "has$key")) {
        $func = "has$key";
        $data = $row->$func();
      } else {
        $data = !!$row[$key];
      }
      $extra = $row[$key];
    } else {
      $data = $row->{"get$key"}();
    }
    
    $classes = explode(" ", $classes);
    
		if(method_exists($this, "format$key")) {
			$data = $this->{"format$key"}($data, $row);
		} else if(is_array($data)) {
      $data = implode($data, ",");
    }

    if($bool) {
      $data = $this->boolFormat($data, $extra);
    } elseif(in_array("time", $classes)) {
      $data = $this->dateFormat($data, "G:i");
    } elseif(in_array("date", $classes) || $data instanceof DateTime) {
			$data = $this->dateFormat($data);	
		}
		
    if(in_array("MaxLength", $classes)) {
        $data = $this->truncate_string(strip_tags($data), 65);
    }
    
		return $data;
	}

        function hasTasks() {
          return count($this->tasks) > 0;
        }
	
	function footer() {
		$out = "<tr class='footer'>";
		$count = ($this->data instanceof MySQLDataHandler ? $this->data->getLastTotal() : count($this->data));
		$tasks = "";
		if($this->hasTasks()) {
			ob_start();
			Fieldset::select("Task", "", array("options" => $this->tasks));
			Fieldset::submit("Go", "Go");
			$tasks = ob_get_contents();
			ob_end_clean();
		}
		
		if($tasks) $tasks = "<div class='tasks'>$tasks</div>";
		
		$out .= "<td colspan='100'>".$this->footerText($count)." $tasks</td>";
		$out .= $this->getPagination();
			
		$out .= "</tr>\n\r";
		return $out;
	}
	
	function footerText($count) {
		if($count > 0) {
			return "There are $count item(s) in total.";
		} else {
			return "There are no items in this list.";
		}
	}
	function getPagination() {
		$total = $this->data instanceof MySQLDataHandler ? $this->data->getLastTotal() : count($this->data);
		if(count($this->data) < $total) {
			$out = "";
			if($this->getPage() != 0) {
				$out .= "<a class='page' href='".$this->url(array("page" => $this->getPage()-1))."'>Previous</a> ";
			}
			
			$i = 0;
			while($i*$this->limit < $total) {
				$out .= "<a class='page ".($this->getPage() == $i ? "selectedpage" : "")."' href='".$this->url(array("page" => $i))."'>".($i+1) . "</a> ";
				
				$i++;
				if($i > 10) {
					$last = $total / $this->limit;
					
					$out .= " ... ";
					$out .= "<a class='page' href='".$this->url(array("page" => $last))."'>".(int)($last+1) ."</a>";
					break;
				}
			}
			
			if($this->getPage() != $i-1) {
				$out .= "<a class='page' href='".$this->url(array("page" => $this->getPage()+1))."'>Next</a> ";
			}
			
			return "<tr><td colspan='100'><div class='paging'>$out</div></td></tr>";
		} else {
			return "";
		}
	}
	
	
	function addTasks($tasks, $url="") {
		$this->tasks = $tasks;
		$this->formUrl = $url;
	}

  function processSorts($action = null) {
		if($this->data instanceof MySQLDataHandler) {
		  $sortCols = SortHelper::userColumns($this->link($action, null));
		   
      $accept= array();
      foreach(array_keys_or_values($this->getSorts()) as $sort) {
        $accept[] = $sort;
        foreach(array("ASC", "DESC") as $dir) {
          $accept[] = "$sort $dir";
        }
      }
			$this->data = $this->data->order(array_map(function($i) { return "$i"; }, array_intersect($sortCols, $accept)));

			if($this->limit) {
				$this->data = $this->data->limit($this->limit * $this->getPage(), $this->limit);
			}
    }
		
  }
	
	function body() {
		$out = "";
    $this->processSorts();
		if($this->data) foreach($this->data as $row) {
			$out .= $this->row($row);
		}
		return $out;
	}
	
	function displayColumns() {
	  if($this->fieldEdit && FieldEdit::getFields()) {
      $fields = $this->Fields();
      
      if($fields instanceof Database_FieldSet) {
  	    $this->addSort($fields->toArray("Field", "FullName"));
        return array_intersect($fields->toArray("Field", "Field"), FieldEdit::getFields());
      } else {
        $this->addSort($fields);
        return array_intersect($fields, FieldEdit::getFields());
      }
	  } else {
	    return $this->display;
	  }
	  
	}
  
  function setFields($fields) {
    $this->fields = $fields;
  }
	
	function Fields() {
	  if ($this->fields) {
	    return $this->fields;
	  } else {
	    return $this->data->Fields();
	  }
	}
	
	function __toString() {
	  $collection = strtolower(get_class($this->data));
	  
	  $output = "<table width='100%' cellspacing='0' cellpadding='0' border='0' class='itemlist $collection' id='$this->id' ";
    $output .= $this->attributes();
	  $output .= "><thead>".$this->header()."</thead><tbody>".$this->body()."</tbody><tfoot>".$this->footer()."</tfoot></table>";
		if($this->formUrl) {
		  if(isset($this->App->Controller->ID)) {
  		  $back = Form::hidden("back", $this->url().'/'.$this->App->Controller->ID);
		  } else {
		    $back = Form::hidden("back", $this->url());
		  }
			$output = "<form method='POST' action='".$this->evalLink(array(), $this->formUrl)."'>$back $output</form>";
		}
		
		return $output;
	}
	
	function exportToExcel(Excel_XML $excel, $worksheet=0, $name='') {
		if(!$name) {
			if($this->title) {
				$excel->setWorksheetTitle($this->title, $worksheet);
			} 
		} else {
			$excel->setWorksheetTitle($name, $worksheet);
		}
		$excel->addArray(array($this->displayColumns()), $worksheet);
		foreach($this->data as $row) {
			$data = array();
			foreach(array_keys($this->displayColumns()) as $column) {
				$data[] = $this->getData($column, $row);
			}
			$excel->addArray(array($data), $worksheet);
		}
	}
	
	
	function export($name) {
		require_once "php-excel.class.php";
		$excel = new Excel_XML;
		$this->exportToExcel($excel, 0, $name);
		$excel->generateXML($name);
		die();
	}
  
  function sort($col) {
    return SortHelper::link($this, $col);
  }
  
  function sortLink($col, $text) {
    return "<a href='".$this->sort($col)."' title='Sort on $col'>$text</a>";
  }
}

class FieldEdit {
  static function show($fields) {
    if($fields instanceof Database_FieldSet) {
      $fields = $fields->toArray("Field", "Field");
    }
    
    return "<div class='fieldEdit'><a class='fields' title='Select different fields'><img src='/im/icons/fields.png' /></a><fieldset>" .
  		"<ol><li>".
  		"<label>Fields to include in table:</label>". Form::checkList("fieldEdit", self::getFields(), array("options" => $fields)).
  		"</li><li>". Form::input("Update", "Update", "button") .
  		"</li></ol>".
  		"</fieldset>".
  		"</div>";
  }
  
  static function getFields() {
    $fields = @array_filter($_GET["fieldEdit"]);
    return empty($fields) ? null : $fields;
  }
}
