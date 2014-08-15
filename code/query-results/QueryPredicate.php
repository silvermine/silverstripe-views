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

   public static $db = array();

   public static $has_one = array(
      'CompoundParent' => 'CompoundPredicate',
   );

   public static $has_many = array(
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

   /**
    * Updates the QueryBuilder object that is passed in with whatever query requirements
    * this predicate has.
    *
    * NOTE: This method should be used by callers of this class, although it delegates
    * implementation to updateQueryImpl which is what should be overridden by subclasses.
    *
    * The updateQueryImpl method will not be called on subclasses if the predicate
    * conditions are not met. Subclasses should not need to worry about predicate conditions
    * at all since they are handled here.
    *
    * @param QueryBuilder $query the query that is being built for this results retriever
    * @param boolean $conjunctive true if this is part of a conjunctive ("AND") predicate, false for disjunctive ("OR")
    * @return boolean true if you made a modification to the query
    */
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
      foreach ($conditions as $condition) {
         $condition->delete();
      }
   }


   /**
    * Updates the QueryBuilder object that is passed in with whatever query requirements
    * this predicate has.
    *
    * NOTE: Callers of this class should call updateQuery instead. Subclasses of QueryPredicate
    * should override this updateQueryImpl method (and not override updateQuery).
    *
    * This method will not be called if predicate conditions were not met.
    *
    * @see QueryPredicate->updateQuery($query, $conjunctive)
    * @param QueryBuilder $query the query that is being built for this results retriever
    * @param boolean $conjunctive true if this is part of a conjunctive ("AND") predicate, false for disjunctive ("OR")
    * @return boolean true if you made a modification to the query
    */
   public function updateQueryImpl(&$query, $conjunctive) {
      throw new RuntimeException(get_class($this) . ' needs to implement QueryPredicate->updateQueryImpl(&$query, $conjunctive)');
   }
}
