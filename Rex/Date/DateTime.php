<?php

namespace Rex\Date;

class DateTime extends \DateTime {
  function __toString() {
    return $this->format("d/m/Y");
  }
  

  function same_day(DateTime $other_time) {
    $this_time = getdate($this->getTimestamp());
    $other_time = getdate($other_time->getTimestamp());
    
    return $this_time["mday"] == $other_time["mday"] 
      && $this_time["mon"] == $other_time["mon"] 
      && $this_time["year"] == $other_time["year"];
  }


  function parent_add($interval) {
    return parent::add($interval);
  }
  
  function add($interval) {
    $newTime = clone $this;
    return $newTime->parent_add($interval);
  }
  
  function parent_sub($interval) {
    return parent::sub($interval);
  }
  
  function sub($interval) {
    $newTime = clone $this;
    return $newTime->parent_sub($interval);
  }
  
}
