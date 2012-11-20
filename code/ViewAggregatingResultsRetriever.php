<?php

/**
 * Takes the results of more than one view and aggregates them into a single
 * view result.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewAggregatingResultsRetriever extends ViewResultsRetriever {

   static $db = array(
      'DeDupeFieldName' => 'VARCHAR(64)',
   );

   static $many_many = array(
      'Views' => 'View',
   );

   static $has_one = array(
      'Sorter' => 'ViewResultsSorter',
   );

   /**
    * @see ViewResultsRetriever->getReadOnlySummary()
    */
   public function getReadOnlySummary() {
      $html = '<b>Aggregates the following views:</b>';
      $html .= '<div style="padding-left: 4em">';
      foreach ($this->Views() as $view) {
         $html .= $view->getReadOnlySummary();
         $html .= '<hr />';
      }
      $html .= '</div>';
      $html .= 'Sorts by: ' . ($this->Sorter() ? $this->Sorter()->getReadOnlySummary() : 'N/A');
      return $html;
   }

   /**
    * Deletes the associated many_many rows for hand-picked pages before
    * deleting this results retriever.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      parent::Views()->removeAll();
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl($maxResults = 0) {
      $all = new DataObjectSet(array());
      foreach ($this->Views() as $view) {
         $results = $view->Results();
         if ($results) {
            $all->merge($results);
         }
      }
      $all->removeDuplicates($this->DeDupeFieldName);
      if ($this->Sorter()) {
         $all = $this->Sorter()->sort($all);
      }
      if ($maxResults > 0 && $all->TotalItems() > $maxResults) {
         $all = new DataObjectSet(array_slice($all->toArray(), 0, $maxResults));
      }
      return $all;
   }

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);
      // TODO: implement
   }
}
