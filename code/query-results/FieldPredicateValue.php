<?php

/**
 * A single value used by a FieldPredicate in its "where" clause.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class FieldPredicateValue extends DataObject {

   static $special_values = array();

   static $db = array(
      'Value' => 'VARCHAR(256)',
   );

   static $has_one = array(
      'Predicate' => 'FieldPredicate',
   );

   public static function add_special_value($valueString, $retriever) {
      self::$special_values[$valueString] = $retriever;
   }

   public function getSQLValue($translateSQLValues = true) {
      if ($translateSQLValues && array_key_exists($this->Value, self::$special_values)) {
         $func = self::$special_values[$this->Value];
         return $func($this);
      }

      return Convert::raw2sql($this->Value);
   }
}

