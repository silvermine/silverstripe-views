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

   public function updateQuery(&$query) {
      throw new RuntimeException(get_class($this) . ' needs to implement QueryPredicate->updateQuery(&$query)');
   }
}

