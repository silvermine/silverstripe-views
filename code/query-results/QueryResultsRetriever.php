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
      
      $columns = array("COUNT(*)");
      $qb = $this->getQuery($root, $columns);
      
      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();
      
      if (empty($results))
         return 0;
      
      $count = (int)$results->First()->getField("COUNT(*)");
      return $count;
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
    * @param array $columns Optional. Columns to retrieve from the Query
    * @return QueryBuilder
    */
   private function &getQuery($queryPredicate, $columns = null) {
      $qb = new QueryBuilder();
      
      if (is_array($columns)) {
         $vernSiteTree = $qb->selectColumns('SiteTree');
         $qb->addColumns($columns);
      } else {
         $vernSiteTree = $qb->selectObjects('SiteTree');
      }
      
      $locale = $this->getTransformedResultsLocale();
      if($this->isTranslatable() && $locale) {
         $masterSiteTree = $qb->translateResults($locale);
         $qb->joinSubclassTables(self::$many_many['Pages'], $masterSiteTree);
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
      return Object::has_extension('SiteTree', 'Translatable');
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
      if ($sorts) {
         foreach ($sorts as $sort) {
            $sort->delete();
         }
      }
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl($offset, $limit) {
      $root = $this->RootPredicate();
      
      // If no filters exist, don't return any results.
      if ($root instanceof CompoundPredicate && count($root->Predicates()) == 0) {
         return null;
      }
      
      $qb = $this->getQuery($root);
      $qb->limit($limit);
      $qb->offset($offset);
      
      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();
      
      return $results;
   }

   public function Sorts() {
      $sorts = parent::Sorts();
      if ($sorts) {
         $sorts->sort('ID');
      }
      return $sorts;
   }
}

