<?php

/**
 * A predicate condition is a rule that is used to determine if a given query
 * predicate should be used for a query.  For instance, if you have a field
 * predicate that uses a query string parameter in its value, you can define a
 * condition that the query string parameter must be present for that predicate
 * to be used for the query.  If the query string parameter is not present the
 * predicate will not modify the query at all.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class PredicateCondition extends DataObject {

   public static $has_one = array(
      'CompoundParent' => 'PredicateCondition',
      'QueryPredicate' => 'QueryPredicate',
   );


   public function getReadOnlySummary($linePrefix = '') {
      throw new RuntimeException(get_class($this) . ' needs to implement PredicateCondition->getReadOnlySummary($linePrefix = \'\')');
   }


   public function conditionIsMet() {
      throw new RuntimeException(get_class($this) . ' needs to implement PredicateCondition->conditionIsMet()');
   }
}

