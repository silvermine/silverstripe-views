<?php

/**
 * A QueryPredicate that allows you to require that the selected nodes be
 * tagged (inclusive) or not tagged (exclusive) with a particular vocabulary
 * term.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class TaxonomyTermPredicate extends QueryPredicate {

   static $db = array(
      'Inclusive' => 'BOOLEAN',
   );

   static $has_one = array(
      'Term' => 'VocabularyTerm',
   );

   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummaryImpl() {
      $term = $this->Term();
      return "Has vocabulary term '{$term->Term}' from vocabulary '{$term->Vocabulary()->Name}'";
   }

   public function updateQueryImpl(&$query, $conjunctive) {
      // TODO: this is hard-coded to use SiteTree, but should be more flexible
      // this is really a problem throughout query results retriever et al right now
      // in general, query results retrievers should have a 'type' parameter
      // that specifies what type of object they are querying for
      $mainTable = $query->getPrimaryTableAlias();
      $stvt = $query->getTableAlias('SiteTree_VocabularyTerms');
      $vt = $query->getTableAlias('VocabularyTerm');
      if ($this->Inclusive) {
         $query->innerJoin($stvt, "{$mainTable}.ID = {$stvt}.SiteTreeID");
         $query->innerJoin($vt, "{$stvt}.VocabularyTermID = {$vt}.ID AND {$vt}.ID = {$this->Term()->ID}");
      } else {
         $query->leftJoin($stvt, "{$mainTable}.ID = {$stvt}.SiteTreeID");
         $query->leftJoin($vt, "{$stvt}.VocabularyTermID = {$vt}.ID AND {$vt}.ID = {$this->Term()->ID}");
         $query->where("{$vt}.ID IS NULL", $conjunctive);
      }

      return true;
   }
}

