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
      'FieldName' => 'VARCHAR(128)',
      'Qualifier' => "ENUM('equals,notequal,like,in,notin', 'equals')",
   );

   static $has_many = array(
      'Values' => 'FieldPredicateValue',
   );

   static $qualifier_symbols = array(
      'equals'   => '=',
      'notequal' => '<>',
      'like'     => 'LIKE',
      'in'       => 'IN',
      'notin'    => 'NOT IN',
   );

   private function buildWhere($translateSQLValues = true) {
      if (!array_key_exists($this->Qualifier, self::$qualifier_symbols)) {
         throw new RuntimeException("FieldPredicate does not have a qualifier symbol for qualifier '{$this->Qualifier}'");
      }

      $values = '';

      switch ($this->Qualifier) {
         case 'equals':
         case 'notequal':
         case 'like':
            $values = "'" . $this->Values()->first()->getSQLValue($translateSQLValues) . "'";
            break;
         case 'in':
         case 'notin':
            $sqlValues = array();
            foreach ($this->Values() as $value) {
               $sql = $value->getSQLValue($translateSQLValues);
               $sqlValues = array_merge($sqlValues, explode(FieldPredicateValue::VALUE_SEP, $sql));
            }
            $values = "('" . implode("', '", $sqlValues) . "')";
            break;
         default:
            throw new RuntimeException("FieldPredicate->buildWhere does not implement a qualifier '{$this->Qualifier}'");
      }

      return sprintf("%s %s %s", $this->FieldName, self::$qualifier_symbols[$this->Qualifier], $values);
   }

   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummaryImpl() {
      return $this->buildWhere(false);
   }

   /**
    * Deletes the associated child objects before deleting this object.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $values = $this->Values();
      if ($values) {
         foreach ($values as $value) {
            $value->delete();
         }
      }
   }

   public function updateQueryImpl(&$query, $conjunctive) {
      $query->where($this->buildWhere(), $conjunctive);
      return true;
   }
}

