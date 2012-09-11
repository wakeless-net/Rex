<?php 

namespace Rex\View;

use Rex\Data\DataObject;
use Rex\Data\Validator;

/**
 * @group RexTest
 */
class TableTest extends \PHPUnit_Framework_TestCase {
	function testAddConditionalTaskNotExecuted() {
		$mock = $this->getMock('Rex\Data\DataObject', array("isActive"));
		$mock->expects($this->once())->method("isActive")->will($this->returnValue(false));
		
		$table = new Table(array("Name"), array($mock));
		$table->addConditionalAction("Name", "/Link/To/Whatever", "isActive");
		
		$this->assertNotContains("/Link/To/Whatever", $table->body());
		
	}	
	
	function testAddConditionalTaskExecuted() {
		$mock = $this->getMock('Rex\Data\DataObject', array("isActive"));
		$mock->expects($this->once())->method("isActive")->will($this->returnValue(true));
		
		$table = new Table(array("Name"), array($mock));
		$table->addConditionalAction("Name", "/Link/To/Whatever", "isActive");
		
		$this->assertContains("/Link/To/Whatever", $table->body());
		
	}
	
	function testAddConditionalTaskNegative() {
		$mock = $this->getMock('Rex\Data\DataObject', array("isActive"));
		$mock->expects($this->once())->method("isActive")->will($this->returnValue(false));
		
		$table = new Table(array("Name"), array($mock));
		$table->addConditionalAction("Name", "/Link/To/Whatever", "!isActive");
		
		$this->assertContains("/Link/To/Whatever", $table->body());
	}


  function testShowArray() {
    $mock = $this->getMock('Rex\Data\DataObject', array("getArray"));
    $mock->expects($this->any())->method("getArray")->will($this->returnValue(["aaa", "bbb", "ccc"]));

    $table = new Table(["Array"], [$mock]);
    $this->assertTag(["tag" => "td", "content" => "aaa,bbb,ccc"], $table->body());
  }
	
	function testConditionalRowClass() {
	  $mock = $this->getMock('Rex\Data\DataObject', array("isActive"));
	  $mock->expects($this->any())->method("isActive")->will($this->returnValue(false));
	  
	  $table = new Table(array(), array($mock));
	  $table->addConditionalRowClass("!isActive", "inactive");
	  $this->assertTag(array("tag" => "tr", "class" => "inactive"), $table->body());
	}
  
  function testAddColumn() {
    $mock = $this->getMock('Rex\Data\DataObject', array());
    
    $table = new Table(array(), array($mock));
    $table->addColumn("Fresh", "name");
    $table->addColumn("LastName");
    
    $this->assertTag(array("tag" => "th", "class" => "fresh", "content" => "name"), $table->header());
    $this->assertTag(array("tag" => "th", "class" => "lastname", "content" => "Last Name"), $table->header());
  }
  
  function testAddColumns() {
    $mock = $this->getMock('Rex\Data\DataObject', array());
    
    $table = new Table(array(), array($mock));
    $table->addColumns(array("Name" => "test"));
    $table->addColumns(array("FirstName"));
    
    
    $this->assertTag(array("tag" => "th", "class" => "name", "content" => "test"), $table->header());
    $this->assertTag(array("tag" => "th", "class" => "firstname", "content" => "First Name"), $table->header());
  }
	
	function testRowId() {
		$table = new Table(array());
		
		$user = new \User(array("ID"=>333));
		
		$this->assertTag(array(
			"tag" => "tr",
			"id" => "row_user_333"
		), $table->row($user), "Row should have id='row_user_333'");
	}
	
	function testTableID() {
	  $table = new Table(array());
    $table->id = "table_id";
    $this->assertTag(array(
      "tag" => "table",
      "id" => "table_id"
    ), $table->__toString(), "Table should have an id");
	}
  
  function testMaxLengthOfTableEntry() {
   $mock = $this->getMock('Rex\Data\DataObject', array("isActive", "getName"));
   
    $truncate = "";
    foreach(range(1, 100) as $c) {
      $truncate .= $c;
    }

   $mock->expects($this->any())->method("getName")->will($this->returnValue($truncate));
   
   $table = new Table(array("Name"), array($mock));
   $table->addClass("Name", "MaxLength");
   
   $this->assertTag(array("tag" => "tr"), $table->body());
   $this->assertTag(array("tag" => "td", "content" => substr($truncate, 0, 65). "..."), $table->body());
  }
  
  function testSortable() {
    $table = new SortableTable(array());
    
    $this->assertTag(array(
      "tag" => "table",
      "id" => "sortable"
    ), $table->__toString(), "Table should have the id 'sortable' on it's tbody tag");
    
  }
  
  function testDataAttributes() {
    $table = new Table(array("Test", "Other"));
    $table->addAttribute("test", 123);
    $table->addAttribute("another", 456);
    
    $expected = array(
      "tag" => "table",
      "attributes" => array("test" => '123', "another" => '456'),
    );
    
    $this->assertTag($expected, $table->__toString(), "Should print the attributes in the table tag");
  }
  
  function testAddTasks() {
    $table = $this->getMock('Rex\View\Table', array("url"), array(array("Test", "Other"), array()));
    $table->expects($this->any())->method("url")->with()->will($this->returnValue("dummy/url"));
    $table->addTasks(array("Task" => "FakeTask"), "a/fake/url");
    
    $expectedInput = array(
      "tag" => "input",
      "attributes" => array("value" => 'dummy/url', "name" => 'back'),
    );
    
    $expectedOption = array(
      "tag" => "option",
      "attributes" => array("value" => 'Task'),
      "content" => "FakeTask",
    );
    
    $this->assertTag($expectedOption, $table->__toString(), "Should contain name and id attr = FakeTask");
    $this->assertTag($expectedInput, $table->__toString(), "Should only contain the back url and task type");
  }
}

eval("class User extends Rex\\Data\\DataObject {}");
