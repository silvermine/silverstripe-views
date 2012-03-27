<?php

/**
 * An advanced type of results retriever, this class allows a content manager
 * to write query criteria and sort clauses that will be used in a query to
 * obtain results based on the query they wrote.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class QueryResultsRetriever extends ViewResultsRetriever {

   static $db = array();

   static $has_one = array(
      'RootPredicate' => 'QueryPredicate',
   );

   static $has_many = array(
      'Sorts' => 'QuerySort',
   );

   /**
    * @see ViewResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummary() {
      $html = '';
      $html .= $this->RootPredicate()->getReadOnlySummary() . '<br />';
      $html .= 'ORDER BY<br />';
      $prefix = '';
      foreach ($this->Sorts() as $sort) {
         $html .= $prefix . $sort->getReadOnlySummary();
         $prefix = ', ';
      }
      return $html;
   }

   /**
    * Deletes all related objects that have a one-to-one relationship with this
    * instance.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      // TODO: delete all child objects (sorts and criteria)
   }

   /**
    * @see ViewResultsRetriever->Results()
    */
   public function Results($maxResults = 0) {
      $query = new QueryBuilder();
      $query->selectObjects('SiteTree');

      $root = $this->RootPredicate();
      $root->updateQuery($query, true);

      $sorts = $this->Sorts();
      foreach ($sorts as $sort) {
         $sort->updateQuery($query);
      }

      return $query->execute();
   }

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      // TODO: implement
   }
}

