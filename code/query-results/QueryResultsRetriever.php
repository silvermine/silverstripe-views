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
   
   static $traverse_has_one = true;
   
   /**
    * {@link ViewResultsRetriever::count}
    */
   public function count() {
      $root = $this->RootPredicate();
      if ($root instanceof CompoundPredicate && count($root->Predicates()) == 0) {
         return 0;
      }
      
      $qb = $this->getQuery($root);
      
      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();

      return $results->count();
   }

   /**
    * @see ViewResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummary() {
      Requirements::css('views/code/css/views.css');
      
      $html = '<span class="viewsReadOnlyQuerySummary">';
      $html .= $this->RootPredicate()->getReadOnlySummary() . '<br />';
      $html .= 'ORDER BY<br />';
      $prefix = '';
      foreach ($this->Sorts() as $sort) {
         $html .= $prefix . $sort->getReadOnlySummary();
         $prefix = ', ';
      }
      $html .= '</span>';
      return $html;
   }
   
   /**
    * Return an instance of QueryBuilder set up using the given query predicate
    * 
    * @param QueryPredicate $queryPredicate
    * @return QueryBuilder
    */
   private function &getQuery($queryPredicate) {
      $qb = new QueryBuilder();

      $vernSiteTree = $qb->selectObjects('SiteTree');

      $locale = $this->getTransformedResultsLocale();
      if($this->isTranslatable() && $locale) {
         $qb->translateResults($locale);
      }

      $queryPredicate->updateQuery($qb, true);

      $sorts = $this->Sorts();
      foreach ($sorts as $sort) {
         $sort->updateQuery($qb);
      }

      return $qb;
   }
   
   /**
    * Return true if SiteTree is translatable
    * 
    * @return bool
    */
   private function isTranslatable() {
      return SiteTree::has_extension('Translatable');
   }
   
   /**
    * Deletes all related objects that have a one-to-one relationship with this
    * instance.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $this->RootPredicate()->delete();
      $sorts = $this->Sorts();
      foreach ($sorts as $sort) {
         $sort->delete();
      }
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl() {
      $root = $this->RootPredicate();
      
      // If no filters exist, don't return any results.
      if ($root instanceof CompoundPredicate && count($root->Predicates()) == 0) {
         return new ArrayList(array());
      }
      
      $qb = $this->getQuery($root);

      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();

      return $results;
   }

   public function Sorts() {
      return parent::Sorts()->sort('ID');
   }
}

