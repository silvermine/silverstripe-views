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

   public static $db = array(
      'DeDupeFieldName' => 'VARCHAR(64)',
   );

   public static $many_many = array(
      'Views' => 'View',
   );

   public static $many_many_extraFields = array(
      'Views' => array(
         'SortOrder' => 'Int',
      ),
   );

   public static $has_one = array(
      'Sorter' => 'ViewResultsSorter',
   );

   public static $traverse_has_one = true;


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
    * @see ViewResultsRetriever->dumpPreservedFields()
    */
   public function dumpPreservedFields() {
      $views = array();
      foreach($this->Views() as $view) {
         $views[] = $view;
      }

      return array(
         'Views' => $views
      );
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
    * @see ViewResultsRetriever->loadPreservedFields()
    */
   public function loadPreservedFields($data) {
      $views = array_key_exists('Views', $data) ? $data['Views'] : array();
      $i = 0;
      $this->Views()->removeAll();
      foreach($views as $view)
         $this->Views()->add($view, array('SortOrder' => ++$i));
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

      if ($this->SorterID) {
         $this->Sorter()->delete();
      }
   }


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


   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);

      $config = GridFieldConfig_RelationEditor::create($itemsPerPage = 20)
         ->removeComponentsByType('GridFieldEditButton')
         ->removeComponentsByType('GridFieldDeleteAction')
         ->removeComponentsByType('GridFieldAddNewButton')
         ->removeComponentsByType('GridFieldAddExistingAutocompleter')
         ->addComponent(new AddPageToSortedManyManyAutocompleter('ViewAggregatingResultsRetrieverID', 'SortOrder', 'buttons-before-left'))
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->toTop())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->up())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->down())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->toBottom())
         ->addComponent(new GridFieldDeleteAction($removeRelation = true));

      $autocompleter = $config->getComponentByType('GridFieldAddExistingAutocompleter');
      $autocompleter->setSearchList(View::get());
      $autocompleter->setResultsFormat('$Summary');
      $autocompleter->setSearchFields(array('Name'));

      $fields->addFieldToTab('Root.QueryEditor', new GridField(
         'Views',
         _t('Views.AggViews.Label', 'Views'),
         $this->Views(),
         $config
      ));
   }


   /**
    * Sort the Views relationship correctly
    *
    * @return SS_List or null the pages associated with this results retriever
    */
   public function Views() {
      return parent::Views()->sort('SortOrder ASC');
   }
}
