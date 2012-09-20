<?php

namespace Rex\Data;

class Validator {

	//array of arrays of rules, being:
	//Required - cannot be empty
	//Match - must be the same
	//Length - min length field (keys hold length required)
	var $rules;

	function __construct($rules = array()){
		$this->rules = $rules;
	}
	
	function addRules($rules = array()) {
	  $this->rules += $rules;
	  return $this;
	}
	
	function requireField($form, $field, ErrorStore $store = null) {
		if (!isset($form[$field]) || $form[$field] === "" || (is_array($form[$field]) && empty($form[$field]))) {
			if($store) $store->storeError($field, ERROR_EMPTY);
					
			FormHelper::setData($field,'Error',ERROR_EMPTY); 
			return false;		
		}
		return true;
	}
	
	function validate($form, \Rex\Data\ErrorStore $store = null){
		$rules = $this->rules;
		
		$fine = true;
		
		if (isset($rules["Required"]) && is_array($rules['Required'])){
			foreach ($rules['Required'] as $field){
				$ret = $this->requireField($form, $field, $store);
				
				if($ret == false){
					$fine = false;
				}
			}
		}
		
		
		$lastField = NULL;
		$lastFieldCount = 1;
		
		if (isset($rules['Match']) && is_array($rules['Match'])){
			foreach ($rules['Match'] as $field){
				
				if ($lastFieldCount % 2 == 0 && (isset($form[$field]) && isset($form[$lastField]) && $form[$field] != $form[$lastField])){
					$fine = false;
					if($store) $store->storeError($field, "These fields do not match.");
					FormHelper::setData($field,'Error','These fields do not match.'); 
				}
				$lastField = $field;
				$lastFieldCount++;
			}
		}
		
		if(isset($rules['Pattern']) && is_array($rules['Pattern'])) {
			foreach($rules["Pattern"] as $field => $pattern) {
				$val = isset($form[$field]) ? $form[$field] : "";
				if($val && !preg_match($pattern, $val) ) {
					if($store) $store->storeError($field, "Format is invalid.");
					FormHelper::setData($field, "Error", "Format is invalid.");
					$fine = false;
				}
			}
		}
		
		if(isset($rules["Function"]) && is_array($rules["Function"])) {
			foreach($rules["Function"] as $field => $function) {
				if(!$function(@$form[$field],$store)) {
					$fine = false;
				}
			}
		}
    
    if (isset($rules['Time']) && is_array($rules['Time'])){
      foreach ($rules['Time'] as $field){
        
         if(@$form[$field] instanceof DateTime) break;
        
        if(!strtotime($form[$field])) {
          if($store) $store->storeError($field, "Time format is invalid.");
          FormHelper::setData($field, "Error", "Time format is invalid.");
          $fine = false;
        }
      }
    }
		
		if (isset($rules['Date']) && is_array($rules['Date'])){
			foreach ($rules['Date'] as $field){
				$aFormat    = array('y','m','d') ;
				$sSeparator = '/'; 
			  if (is_array($field)) {
					$field = $field['field'];
					$aFormat = (isset($field['format'])) ? $field['format'] : $aFormat ;
					$sSeparator = (isset($field['separator'])) ? $field['separator'] : $aFormat ;
				}
        
        if(@$form[$field] instanceof DateTime) break;
        
				$fieldValid = $this->_validDate(@$form[$field],$sSeparator,$aFormat);
				if (!$fieldValid)	{
					$fine = false;
					if($store) $store->storeError($field, "Date is invalid.");
					FormHelper::setData($field,'Error','Date is invalid.'); 
				}
			}
		}
    
    if(isset($rules["Numeric"]) && is_array($rules["Numeric"])) {
			foreach($rules["Numeric"] as $fieldName) {
        $field = @$form[$fieldName];

        if($field && !is_numeric($field)) {
          $fine = false;
          if($store) $store->storeError($fieldName, ERROR_NOTNUM);
          FormHelper::setData($fieldName, "Error", ERROR_NOTNUM);
        }
      }
    }
		
		if(isset($rules["MaxLength"]) && is_array($rules["MaxLength"])) {
			foreach($rules["MaxLength"] as $fieldName => $length) {
				$field = @$form[$fieldName];
				if(is_array($field) && count($field) > $length) {
					$fine = false;
					if($store) $store->storeError($fieldName, "The maximum number of answers is $length.");
					FormHelper::setData($fieldName, "Error", "The maximum number of answers is $length.");
				} elseif(is_string($field) && strlen($field) > $length) {
					$fine = false;
					if($store) $store->storeError($fieldName, "The maximum number of this answer is $length.");
					FormHelper::setData($fieldName, "Error", "The maximum length of this answer is $length");					
				}
			}
		}
		
		if(isset($rules["CreditCard"]) && is_array($rules["CreditCard"])) {
			$ret = $this->checkCreditCardDetails($rules["CreditCard"], $form, $store);

			if ($ret == false) $fine = false;
		}
    
    if(isset($rules["WordLimit"]) && is_array($rules["WordLimit"])) {
      foreach($rules["WordLimit"] as $fieldName => $length) {
        if(ContentHelper::getWordCount(@$form[$fieldName]) > $length) {
          $fine = false;
          if($store) $store->storeError($fieldName, "Word count exceeded.");
          FormHelper::setData($fieldName, "Error", "Word count exceeded.");
        }
      }  
    }

    if(isset($rules["Unique"]) && is_array($rules["Unique"])) {
      foreach($rules["Unique"] as $field => $scope) {
        $store = $store ?: null;
        if(!$store->validateUniqueness($field, $scope)) {
          $fine = false;
        }
      }
    }
		
		if(!$this->process($form, $store)) {
			$fine = false;
		}
		return $fine;
	}
	
	function checkCreditCardDetails($rule, $form, $store){
		$fine = true;
		
		foreach ($rule as $field) {
			$ret = $this->requireField($form, $field, $store);
			
			if($ret == false){
				$fine = false;
			}
		}
		if($fine == false) return false; 
		
		$cardMonth = $rule["Month"];
		$cardYear = $rule["Year"];
		$cardNumber = $rule["Number"];
		$cardType = $rule["Type"];
		
		
		$month = (int)$form[$cardMonth];
		if($month < 1 || $month > 12) {
		  if($store) $store->storeError($cardMonth, "That is not a valid expiry month.");
			FormHelper::setError($cardMonth, "That is not a valid expiry month.");
			return false;
		}
		
		$year = $form[$cardYear];
		
		if(preg_match("/^\d{2}$/", $year)) $year = "20".$year;
		
		if(!preg_match("/^\d{4}$/", $year) && ($year < 2000 || $year > 3000) ) {
		  if($store) $store->storeError($cardYear, "That is not a valid expiry year.");
			FormHelper::setError($cardYear, "That is not a valid expiry year.");
			return false;
		}
		
		if(!$this->validCCExpiry($form[$cardMonth], $year)) {
			if($store) $store->storeError($cardYear, "This credit card has expired.");
			FormHelper::setError($cardYear, "This credit card has expired.");
			return false;
		}
		
		
		if(!checkCreditCard($form[$cardNumber], $form[$cardType], $sErrorNumber, $sErrorText)){
			if($store) $store->storeError($cardNumber, "The credit card number is not valid.");
			FormHelper::setError($cardNumber, "The credit card number is not valid.");
			return false;
		}
		
		return true;
		
	}
	
	function process($form, $store=null) {
		$ok = true;
  	foreach($this->rules as $type => $ruleValues) {
			if(method_exists($this, "check$type")) {
				foreach($ruleValues as $fieldName => $expectation) {
					$value = $form[$fieldName];
					if(!$this->{"check$type"}($value, $expectation)) {
					  if($store) $store->storeError($fieldName, $this->{"error$type"}($expectation));
						FormHelper::setError($fieldName, $this->{"error$type"}($expectation));
						$ok = false;
					}
				}
			}
		}
		return $ok;
	}
	
	protected function checkDateAfter($value, $expectation) {
	  return (strtotime($value) > strtotime($expectation));
	}
  
  protected function errorDateAfter($expectation) {
    return "The end time must be after the start time.";
  } 
	
	protected function checkGreaterThan($value, $expectation) {
		return ($value > $expectation);
	}
	
	protected function errorGreaterThan($expectation) {
		return "This has to be greater than $expectation.";
	}
	
	private function _validDate($sDate,$sSeparator='/',$aFormat=array('y','m','d')) {
		$aDate = preg_split('#[/-]#',$sDate);
		if (count($aDate) == 3)
		{
			$aFormat = array_flip($aFormat);
			return checkdate($aDate[$aFormat['m']],$aDate[$aFormat['d']],$aDate[$aFormat['y']]);
		}	
	
		return false;
	}
	
  function validCCExpiry($cardMonth, $cardYear, $time = null) {
    if(!$time) $time = strtotime("today");
    $cardExpiry = mktime(0,0,0,$cardMonth+1,0,$cardYear);
    return $cardExpiry >= $time;
  }

  function isRequired($field) {
    return in_array($field, $this->rules["Required"]);
  }

  function hasValidation($type, $field, $variable = null) {
    if($variable) {
      return isset($this->rules[$type][$field]) && $this->rules[$type][$field] == $variable;
    } else {
      return isset($this->rules[$type]) && isset($this->rules[$type][$field]);
    }
  }
}

