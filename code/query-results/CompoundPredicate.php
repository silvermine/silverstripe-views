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

