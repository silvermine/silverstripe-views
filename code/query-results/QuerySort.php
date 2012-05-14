<?php

/**
 * Data object that contains information related to how a query's results
 * should be sorted.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class QuerySort extends DataObject {

   static $db = array(
      'FieldName' => 'VARCHAR(64)',
      'IsAscending' => 'BOOLEAN',
   );

   static $has_one = array(
      'ResultsRetriever' => 'QueryResultsRetriever',
   );

   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummary() {
      return $this->FieldName . ' ' . ($this->IsAscending ? 'ASC' : 'DESC');
   }

   public function updateQuery(&$query) {
      $field = ViewsStringTokenizers::tokenize($this->FieldName, $this);
      $query->orderBy($field, $this->IsAscending);
   }
}

