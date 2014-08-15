<?php

/**
 * Base class for a results sorter, used by ViewAggregatingResultsRetriever.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewResultsSorter extends DataObject {

   /**
    * All subclasses should implement this function.
    * Sorts the given SS_List of results, returning that DOS
    * or a new one if necessary.
    *
    * @param SS_List &$results the results to sort
    * @return SS_List the sorted results
    */
   public function sort(SS_List &$results) {
      throw new Exception(get_class($this) . ' must implement ViewResultsSorter->sort(SS_List)');
   }


   /**
    * All subclasses should implement this function, which provides a read-only
    * summary of the results retriever in an HTML format.  This can be used to
    * display to the user when describing the View that uses this
    * ResultsRetriever.
    *
    * @return string HTML string describing this results retriever.
    */
   public function getReadOnlySummary() {
      return 'The ' . get_class($this) . ' class needs to implement getReadOnlySummary().';
   }

}
