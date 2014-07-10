<?php

/**
 * Simplest results sorter, uses a field name and order to
 * sort the results.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class SimpleViewResultsSorter extends ViewResultsSorter {

   public static $db = array(
      'SortFieldName'   => 'VARCHAR(64)',
      'SortIsAscending' => 'BOOLEAN',
   );


   /**
    * @see ViewResultsSorter->sort(SS_List)
    */
   public function sort(SS_List &$results) {
      $results->sort($this->SortFieldName, ($this->SortIsAscending ? 'ASC' : 'DESC'));
      return $results;
   }

   /**
    * @see ViewResultsSorter->getReadOnlySummar()
    */
   public function getReadOnlySummary() {
      return 'Field: ' . $this->SortFieldName . ' ' . ($this->SortIsAscending ? 'ASC' : 'DESC');
   }
}
