<?php

/**
 * Base class for any type of predicate that can be used to add to the "where"
 * clause of a query.  Predicates can also add joins to a query but should
 * typically not add order by fields since these are handled directly by
 * QuerySort objects added to the QueryResultsRetriever.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class QueryPredicate extends DataObject {

   static $db = array(
   );

   static $has_one = array(
      'CompoundParent' => 'CompoundPredicate',
   );

   static $has_many = array(
      'PredicateConditions' => 'PredicateCondition',
   );

   protected function getConditionsReadOnlySummary($linePrefix = '') {
      $conditions = $this->PredicateConditions();
      if ($conditions->exists()) {
         $html = '<em>This predicate is conditional:<br />';
         foreach ($conditions as $cond) {
            $html .= $cond->getReadOnlySummary($linePrefix);
         }
         return $html . '</em>' . $linePrefix;
      }
      return '';
   }

   public function getReadOnlySummary($linePrefix = '') {
      $html = $this->getConditionsReadOnlySummary($linePrefix);
      $html .= $this->getReadOnlySummaryImpl($linePrefix);
      return $html;
   }

   public function updateQuery(&$query, $conjunctive) {
      foreach ($this->PredicateConditions() as $cond) {
         if (!$cond->conditionIsMet()) {
            return false;
         }
      }

      return $this->updateQueryImpl($query, $conjunctive);
   }

   public function getReadOnlySummaryImpl($linePrefix = '') {
      throw new RuntimeException(get_class($this) . ' needs to implement QueryPredicate->getReadOnlySummaryImpl($linePrefix = \'\')');
   }

   /**
    * Deletes the associated child objects before deleting this object.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $conditions = $this->PredicateConditions();
      if ($conditions) {
         foreach ($conditions as $condition) {
            $condition->delete();
         }
      }
   }

   public function updateQueryImpl(&$query, $conjunctive) {
      throw new RuntimeException(get_class($this) . ' needs to implement QueryPredicate->updateQueryImpl(&$query, $conjunctive)');
   }
}

