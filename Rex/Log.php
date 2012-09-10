<?php

namespace Rex;

class Log {
  private static $logger;
  static function setLogger($logger) {
    self::$logger = $logger;
  }


  static function getLogger() {
   return self::$logger;
  }

  public static function __callStatic($func, $args) {
    if($logger = self::getLogger()) {
      return call_user_func_array(array(self::getLogger(), $func), $args);
    }
  }

}
