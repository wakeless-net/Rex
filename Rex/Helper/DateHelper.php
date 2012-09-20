<?php

namespace Rex\Helper;
use Rex\Date\DateTime;


class DateHelper {
	static function days_between($startDate, $endDate) {
		return new Date_Iterator("1 day", $startDate, $endDate);
	}
  
  static function objectify($date = "") {
    if($date instanceof \DateTime) {
      return $date;
    } else {
      return new \Rex\Date\DateTime(date("Y-m-d H:i:s", self::parseUKDateToTime($date)));
    }
  }
  
  static function format($format, $date) {
    if(!$date) {
      return "";      
    } else {
      return self::objectify($date)->format($format);
    }
  }
	
	static function last_day($month = '', $year = '') {
		if (empty($month)) {
			$month = date('m');
		}
		if (empty($year)) {
   		$year = date('Y');
		}
    
    $result = DateHelper::objectify("$year $month");
    $result->modify("last day of this month");
		return $result;
	}
	
	static function previousMonth($month, $currentTime = null) {
		if(is_null($currentTime)) $currentTime = DateHelper::objectify("today");
    else $currentTime = DateHelper::objectify($currentTime);
		
		$year = $currentTime->format("Y");
		
    
		$time = self::last_day($month, $year);
		if($time < $currentTime) {
			return $time;
		} else {
			$time = self::last_day($month, $year - 1);
			return $time;
		}
	}
	
	static function parseDate($date) {
		return date("Y-m-d", self::parseUKDateToTime($date));
	}
	
	static function parseDateFullText($date) {
	  return date("l F j, Y", self::parseUKDateToTime($date));
	}
  
  static function parseTime($datetime) {
    return date("g:i a", self::parseUKDateToTime($datetime));
  }
	
	static function parseUKDateToTime($date) {
		return \FormHelper::parseUKDateToTime($date);
	}
	
	static function isDatePast($data, $on = "now") {
		if(!$data) {
			return false;
		} else {
		  $comp = DateHelper::objectify($data);
		}
    
    $comp = $comp->add(new \DateInterval("P1D"));
    
		return $comp <= new DateTime($on);
	}
}

class Date_Iterator implements \Iterator {
	private $increment = "1 day";
	private $startDate;
	private $endDate;
	
	private $format = "d-m-Y";

	private $currentDate;

	private $iterations = 0;

	/**
	 *
	 * @param string $increment Anything that strtotime can understand. eg. day, week, month, year
	 * @param int|string $startDate
	 * @param int|string $endDate
	 * @return
	 */
	function __construct($increment, $startDate, $endDate) {
		$this->increment = $increment;


		if(is_int($startDate)) {
			$this->startDate = $startDate;
		} else {
			$this->startDate = clone DateHelper::objectify($startDate);
		}

		if(is_int($endDate)) {
			$this->endDate = $endDate;
		} else {
			$this->endDate = clone DateHelper::objectify($endDate);
		}
		$this->currentDate = clone $this->startDate;
	}
	
	function setFormat($format) {
		$this->format = $format;
		return $this;
	}

	function current() {
	  return clone $this->currentDate;
		return DateHelper::format($this->format, $this->currentDate);
	}

	function next() {
		$this->currentDate = $this->currentDate->add(\DateInterval::createFromDateString($this->increment));
		$this->iterations ++;
		return $this->current();
	}

	function valid() {
		return $this->currentDate <= $this->endDate;
	}

	function rewind() {
		$this->currentDate = $this->startDate;
		$this->iterations = 0;
		return $this->current();
	}
	function key() {
		return $this->iterations;
	}
	
	function toArray($format = null) {
		$array = array();
		foreach($this as $date) {
      if(!$format) {
        $array[] = $date;
      } else {
        $array[] = $date->format($format);
      }
		}
		return $array;
	}
}
