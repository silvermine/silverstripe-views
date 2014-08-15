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

   public static $value_tokens = array();

   public static $db = array(
      'Value' => 'VARCHAR(256)',
   );

   public static $has_one = array(
      'Predicate' => 'FieldPredicate',
   );


   public static function add_value_token($identifier, $retriever) {
      self::$value_tokens[$identifier] = $retriever;
   }


   public function getSQLValue($translateSQLValues = true) {
      if ($translateSQLValues) {
         return ViewsStringTokenizers::tokenize($this->Value, $this);
      }

      return $this->Value;
   }
}

