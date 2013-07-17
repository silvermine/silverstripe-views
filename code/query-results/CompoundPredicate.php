<?php

/**
 * A predicate that joins multiple other predicates into one compound statement
 * using either "AND" (conjunctive) or "OR" (disjunctive).
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class CompoundPredicate extends QueryPredicate {

   static $db = array(
      'IsConjunctive' => 'BOOLEAN',
   );

   static $has_many = array(
      'Predicates' => 'QueryPredicate',
   );

   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummaryImpl($linePrefix = '') {
      $end = '<br />' . $linePrefix . ')';
      $linePrefix = $linePrefix . '&nbsp;&nbsp&nbsp;';
      $html = '';
      $html .= '(<br />';
      $prefix = $linePrefix;
      foreach ($this->Predicates() as $pred) {
         $html .= $prefix . $pred->getReadOnlySummary($linePrefix);
         $prefix = '<br />' . $linePrefix . ($this->IsConjunctive ? 'AND ' : 'OR ');
      }
      $html .= $end;
      return $html;
   }

   /**
    * Deletes the associated child objects before deleting this object.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $predicates = $this->Predicates();
      foreach ($predicates as $predicate) {
         $predicate->delete();
      }
   }

   public function updateQueryImpl(&$query, $conjunctive) {
      $preds = $this->Predicates();
      $updated = false;
      $query->startCompoundWhere();
      foreach ($preds as $pred) {
         $updated |= $pred->updateQuery($query, $this->IsConjunctive);
      }
      $query->endCompoundWhere();

      return $updated;
   }
}

