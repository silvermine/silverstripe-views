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
   
   const VALUE_SEP = ',';

   static $value_tokens = array();

   static $db = array(
      'Value' => 'VARCHAR(256)',
   );

   static $has_one = array(
      'Predicate' => 'FieldPredicate',
   );

   public static function add_value_token($identifier, $retriever) {
      self::$value_tokens[$identifier] = $retriever;
   }

   public function getSQLValue($translateSQLValues = true) {
      if ($translateSQLValues) {
         $fpv = $this;
         return preg_replace_callback(
            '/\$\$([A-Za-z]+):{0,1}([A-Za-z0-9]*)\$\$/',
            function(&$matches) use (&$fpv)  {
               $tokenName = $matches[1];
               $tokenParam = $matches[2];
               if (!array_key_exists($tokenName, FieldPredicateValue::$value_tokens)) {
                  user_error("FieldPredicateValue found something that appeared to be a token ('{$matches[0]}') but did not have a value token for the token name ('{$tokenName}')", E_USER_WARNING);
                  return $matches[0];
               }

               $func = FieldPredicateValue::$value_tokens[$tokenName];
               $value = $func($fpv, $tokenParam);
               
               if (is_array($value))
                  $value = implode($fpv::VALUE_SEP, $value);
               
               return $value;
            }, $this->Value
         );
      }

      return Convert::raw2sql($this->Value);
   }
}

