<?php

/**
 * A predicate condition that enforces a rule that a certain named query
 * parameter must be or must not be present in a URL.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class QueryParamPredicateCondition extends PredicateCondition {

   static $db = array(
      'QueryParamName' => 'VARCHAR(32)',
      'PresenceRequired' => 'BOOLEAN',
   );

   /**
    * @see PredicateCondition#getReadOnlySummary()
    */
   public function getReadOnlySummary($linePrefix = '') {
      return "{$linePrefix}Query parameter '{$this->QueryParamName}' " .
         ($this->PresenceRequired ? "MUST BE" : "MUST NOT BE") .
         " present in URL<br />";
   }

   /**
    * @see PredicateCondition#conditionIsMet()
    */
   public function conditionIsMet() {
      $present = false;
      if (Controller::curr() && Controller::curr()->getRequest()) {
         $present = array_key_exists($this->QueryParamName, Controller::curr()->getRequest()->getVars());

         if ($present) {
            $val = Controller::curr()->getRequest()->getVar($this->QueryParamName);
            $present = !empty($val);
         }
      }

      return $this->PresenceRequired ? $present : !$present;
   }
}

