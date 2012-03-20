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
   public function getReadOnlySummary($linePrefix = '') {
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

   public function updateQuery(&$query) {
      $internalQuery = new QueryBuilder();
      $internalQuery->selectObjects('SiteTree');

      $preds = $this->Predicates();
      foreach ($preds as $pred) {
         $pred->updateQuery($internalQuery, $this->IsConjunctive);
      }

      $sqlParts = $internalQuery->getSQLParts();
      $query->where("(" . $sqlParts['wheres'] . ")");
   }
}

