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

   static $traverse_has_one = true;

   /**
    * Augment the QueryBuilderField type description so that it includes an
    * entry for the View class. Includes a multiple-choice view choosing dropdown.
    * Called by {@link QueryBuilderField::build_core_type_structures()}
    *
    * @param array &$structure
    */
   public static function augment_types(&$structure) {
      $options = array();

      // TODO: when doing aggregation, you should have the ability to sort child views
      // instead of sorting them by name
      // That will give you the ability to say "show all results from this view before
      // any results from the other view" if you configure each view with its own sorting
      // and don't configure a sorter.
      $views = View::get()
         ->innerJoin('ViewCollection', '"ViewCollection".ID = "View".ViewCollectionID')
         ->innerJoin('SiteTree', '"SiteTree".ViewCollectionID = "ViewCollection".ID')
         ->sort('"View".Name ASC')
      ;

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
    * @see ViewResultsRetriever->count()
    */
   public function count() {
      $count = 0;
      foreach ($this->Views() as $view) {
         $count += $$view->Results()->Count();
      }
      return $count;
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
      $html .= 'Sorts by: ' . ($this->SorterID ? $this->Sorter()->getReadOnlySummary() : 'N/A');
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
    * Return the DataObject for a view defined in the given representation.
    * Called by {@link QueryBuilderField::save_object()}
    *
    * Input is the same as the output of {@link ViewAggregatingResultsRetriever::getViewsStructure()}
    *
    * @param array
    * @return View
    */
   public function resolveViewsStructure($view) {
      $viewID = $view['fields']['ID'];
      $view = View::get_one('View', 'ID = ' . Convert::raw2sql($viewID));
      if (empty($view))
         return;

      return $view;
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl() {
      $all = new ArrayList(array());
      foreach ($this->Views() as $view) {
         $all->merge($view->Results());
      }

      if ($this->DeDupeFieldName) {
         $all->removeDuplicates($this->DeDupeFieldName);
      }

      if ($this->SorterID) {
         $all = $this->Sorter()->sort($all);
      }

      return $all;
   }

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);
   }

   protected function shouldAddQueryBuilder() {
      return true;
   }
}
