<?php

namespace Rex\View;

class BaseFragment {
	static function currencyFormat($number, $currency = null) {
		$formatted = number_format($number, 2);
		if($currency) {
			$formatted = $currency . $formatted;
		}
		return $formatted;
	}
	
	static function dom_id($object) {
	  return strToLower(get_class($object))."_".$object["ID"];
	}
  
  static function truncate_string($string, $length = 40) {
    if(strlen($string) > $length) {
      $string = substr($string, 0, $length) . '...';
    }
    
    return $string;
  }
	
	static function to_sentence($result, $attribute = "Name") {
		
		$items = array();
		foreach($result as $item) {
			if(is_string($item)) {
				$items[] = $item;
			} else {
			  $items[] = $item->{"get$attribute"}();
			}
		}
		$items[] = implode(" and ", array_splice($items, -2));
		
		return implode(", ", $items);
	}
  
  static function from_camel_case($str) {
    $str = preg_replace("/([A-Z\d]+)([A-Z][a-z])/",'\1 \2', $str);
    $str = preg_replace("/([a-z\d])([A-Z])/", '\1 \2', $str);
    return trim($str);
    
  }
	
	static function pluralise($num, $subject) {
		if($num == 1) {
			return $subject;
		} else {
			return $subject."s";
		}
	} 
	
	static function xmlEscape($text) {
	  if(mb_detect_encoding($text) != "UTF-8") {
  		$text = mb_convert_encoding($text, 'UTF-8');
	  }
    
    return htmlspecialchars($text);
	}
	
	static function htmlescape($value) {
		return htmlspecialchars($value, ENT_QUOTES);
	}

	static function dateFormat($date, $format= "d/m/Y") {
		return Rex\Helper\DateHelper::format($format, $date);
	}


  static function boolFormat($data, $extra = "") {
    if($data) {
      return '<img height="16" width="16" src="/im/icons/complete.jpg" title="'.$extra.'" alt="" />';
    } else {
      return '<img height="16" width="16" src="/im/icons/delete.gif"  title="'.$extra.'" alt=""  />';
    }
  }
  
  static function link_to($text, $url, $extra = array()) {
    $extras =  self::attrs(["href" => $url] + $extra);
    
    return "<a$extras>$text</a>";
  }
	
  function buffer($func) {
    ob_start();
    call_user_func($func);
    return ob_get_clean();
  }


  function isFragment($fragment) {
    return $this->App->isFragment($fragment);
  }

  function renderPartial($fragment, $data = array(), $return = false) {
    $this->renderFragment($fragment, $data, $return);
  }
  
  function renderFragment($fragment, $data = array(), $return = false) {
    return $this->App->renderPartial($fragment, $data, $return);
  }
  
  static function attrs($attrs, $ignore=array()) {
    $output = "";
    foreach($ignore as $i) {
      unset($attrs[$i]);
    }
    
    if($attrs) foreach($attrs as $key => $value) {
      if(in_array($key, array("checked")) && !$value) {
      } else { 
        $output .= " $key='".htmlspecialchars($value, ENT_QUOTES)."' ";
      }
    }
    return $output;
  }
	
}


