<?php

/**
 * A predicate that adds a "where" clause criteria based on a field of the
 * SiteTree table or another table related to it.  Uses the field name and a
 * qualifier (i.e. "equals", "like", "in", etc) and one or more values (stored
 * in FieldPredicateValue objects) to build the query clause.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class FieldPredicate extends QueryPredicate {

   static $db = array(
      'FieldName' => 'VARCHAR(64)',
      'Qualifier' => "ENUM('equals,notequal,like,in,notin', 'equals')",
   );

   static $has_many = array(
      'Values' => 'FieldPredicateValue',
   );

   public function updateQuery(&$query) {
      // TODO: prototype only implements a couple types.  This function
      //       needs to be re-worked to implement all types
      if ($this->Qualifier == 'equals') {
         $value = $this->Values()->first();
         $query->where(sprintf("%s = '%s'", Convert::raw2sql($this->FieldName), $value->getSQLValue()));
      } elseif ($this->Qualifier == 'in') {
         $sqlValues = array();
         foreach ($this->Values() as $value) {
            array_push($sqlValues, $value->getSQLValue());
         }
         $query->where(sprintf("%s IN ('%s')", Convert::raw2sql($this->FieldName), implode("', '", $sqlValues)));
      } else {
         throw new RuntimeException("TODO: implement FieldPredicate->updateQuery for '{$this->Qualifier}' qualifier types");
      }
   }
}

