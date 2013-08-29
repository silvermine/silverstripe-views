<?php

/**
 * Returns the value of a query string parameter so that it can be used inside
 * a string at runtime.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class QueryParamTokenizer extends ViewsStringTokenizer {

   private static $values = array();

   public static function get_value($queryParamName) {
      if (array_key_exists($queryParamName, self::$values)) {
         $value = self::$values[$queryParamName];
         return empty($value) ? null : Convert::raw2sql($value);
      }

      if (Controller::curr() && Controller::curr()->getRequest()) {
         return Convert::raw2sql(Controller::curr()->getRequest()->getVar($queryParamName));
      }

      return null;
   }

   public static function set_value($queryParamName, $value) {
      self::$values[$queryParamName] = $value;
   }

   /**
    * @see ViewsStringTokenizer::getValueFor($tokenName, $params, $owner)
    */
   public function getValueFor($tokenName, $params, &$owner) {
      return count($params) ? self::get_value($params[0]) : null;
   }

}
