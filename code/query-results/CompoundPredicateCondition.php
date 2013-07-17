<?php

/**
 * A compound predicate condition joins more than one predicate condition
 * either conjunctively (all must be true) or disjunctively (only one must be
 * true).
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class CompoundPredicateCondition extends PredicateCondition {

   static $db = array(
      'IsConjunctive' => 'BOOLEAN',
   );

   static $has_many = array(
      'Conditions' => 'PredicateCondition',
   );

   /**
    * @see PredicateCondition#getReadOnlySummary()
    */
   public function getReadOnlySummary($linePrefix = '') {
      $end = '<br />' . $linePrefix . ')';
      $linePrefix = $linePrefix . '&nbsp;&nbsp&nbsp;';
      $html = '';
      $html .= '(<br />';
      $prefix = $linePrefix;
      foreach ($this->Conditions() as $cond) {
         $html .= $prefix . $cond->getReadOnlySummary($linePrefix);
         $prefix = '<br />' . $linePrefix . ($this->IsConjunctive ? 'AND ' : 'OR ');
      }
      $html .= $end;
      return $html;
   }

   /**
    * @see PredicateCondition#conditionIsMet()
    */
   public function conditionIsMet() {
      if ($this->IsConjunctive) {
         foreach ($this->Conditions() as $cond) {
            if (!$cond->conditionIsMet()) {
               return false;
            }
         }
         return true;
      }

      // disjunctive:
      foreach ($this->Conditions() as $cond) {
         if ($cond->conditionIsMet()) {
            return true;
         }
      }
      return false;
   }

   /**
    * Deletes the associated child objects before deleting this object.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $conditions = $this->Conditions();
      foreach ($conditions as $condition) {
         $condition->delete();
      }
   }
}

