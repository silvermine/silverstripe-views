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
    * Augment the QueryBuilderField type description so that it includes an 
    * entry for the View class. Includes a multiple-choice view choosing dropdown.
    * Called by {@link QueryBuilderField::build_core_type_structures()}
    * 
    * @param array &$structure
    */
   public static function augment_types(&$structure) {
      $options = array();
      
      $views = View::get(
         'View', 
         '', 
         $sort = '"View".Name ASC', 
         $join = 'JOIN "ViewCollection" ON "ViewCollection".ID = "View".ViewCollectionID JOIN "SiteTree" ON "SiteTree".ViewCollectionID = "ViewCollection".ID');
      
      foreach ($views as $view)
         $options[$view->ID] = "{$view->Name} &ndash; {$view->getPage()->Summary()}";
      
      $viewStructure = array(
         'base' => null,
         'fields' => array(
            'ID' => array(
               'type' => 'select',
               'default' => '',
               'options' => $options
            )
         )
      );
      
      $structure['View'] = $viewStructure;
   }

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
    * Return an array representation of the Views relationship.
    * Called by {@link QueryBuilderField::buildObjectStructure()}
    * 
    * @return array
    */
   public function getViewsStructure() {
      $viewIDs = array();
      foreach ($this->Views() as $view) {
         $viewIDs[] = array(
            'type' => get_class($view),
            'fields' => array('ID' => $view->ID)
         );
      }
      
      return $viewIDs;
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
    * Return the DataObject for a view defined in the given representation.
    * Called by {@link QueryBuilderField::save_object()}
    * 
    * @param array
    * @return View
    */
   public function saveViews($view) {
      $viewID = $view['fields']['ID'];
      $view = View::get_one('View', 'ID = ' . Convert::raw2sql($viewID));
      if (empty($view))
         return;
      
      return $view;
   }

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);
      // TODO: implement
   }
}
