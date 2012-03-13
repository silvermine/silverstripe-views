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

   static $db = array(
      'Value' => 'VARCHAR(256)',
   );

   static $has_one = array(
      'Predicate' => 'FieldPredicate',
   );

   public function getSQLValue() {
      // TODO: this is not a good way of providing for custom value meanings
      //       these need to be driven by a dynamic list of custom values that
      //       can be added at configuration-time
      // OR: are they not even needed and instead we have custom subclasses of
      //       FieldPredicateValue that have their own special meanings?
      if ($this->Value == '%%CurrentPageID%%') {
         $page = Director::currentPage();
         return $page ? $page->ID : 0;
      }

      return Convert::raw2sql($this->Value);
   }
}

