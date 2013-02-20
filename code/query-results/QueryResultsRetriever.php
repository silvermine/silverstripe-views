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
   protected function resultsImpl($maxResults = 0) {
      $root = $this->RootPredicate();
      
      // If no filters exist, don't return any results.
      if ($root instanceof CompoundPredicate && count($root->Predicates()) == 0) {
         return null;
      }
      
      $query = new QueryBuilder();
      $query->selectObjects('SiteTree');
      $root->updateQuery($query, true);

      $sorts = $this->Sorts();
      foreach ($sorts as $sort) {
         $sort->updateQuery($query);
      }

      Translatable::disable_locale_filter();
      $results = null;
      try {
         $results = $query->execute();
      } catch (Exception $ex) {}
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

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);
      
      $editor = new QueryBuilderField(
         'QueryResultsRetriever',
         _t('Views.QueryBuilder.Label', 'QueryBuilder'),
         $this
      );
      
      $fields->addFieldToTab('Root.QueryEditor', $editor);
   }
}

