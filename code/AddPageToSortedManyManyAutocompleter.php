<?php

/**
 * Autocompleter that updates the sort order value for pages added to a hand picked
 * results retriever.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2013 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class AddPageToSortedManyManyAutocompleter extends GridFieldAddExistingAutocompleter {
   private $foreignColumn = '';
   private $sortColumn = '';


   public function __construct($foreignColumn, $sortColumn, $targetFragment = 'before', $searchFields = null) {
      $this->foreignColumn = $foreignColumn;
      $this->sortColumn = $sortColumn;

      parent::__construct($targetFragment, $searchFields);
   }


   /**
    * Add the page, but also fix the sort order.
    *
    * @see GridFieldAddExistingAutocompleter->getManipulatedData(GridField, SS_List)
    */
   public function getManipulatedData(GridField $gridField, SS_List $dataList) {
      $origID = $gridField->State->GridFieldAddRelation(null);
      $origCount = $dataList->count();

      $manipulatedDataList = parent::getManipulatedData($gridField, $dataList);

      if ($origID && ($manipulatedDataList->count() > $origCount)) {
         // new pages are automatically added with a "zero" SortOrder
         // we simply need to increment the SortOrder for all pages in
         // this results retriever so that a second addition would not
         // result in two pages having a zero value for SortOrder
         $sql = sprintf(
            'UPDATE %s SET %s = %s + 1 WHERE %s = %d',
            $dataList->getJoinTable(),
            $this->sortColumn,
            $this->sortColumn,
            $this->foreignColumn,
            $dataList->getForeignID()
         );
         DB::query($sql);
      }
      return $manipulatedDataList;
   }
}
