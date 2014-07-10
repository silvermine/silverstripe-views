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

   public static $db = array(
      'Inclusive' => 'BOOLEAN',
   );

   public static $has_one = array(
      'Term' => 'VocabularyTerm',
   );

   public static $traverse_has_one = true;


   /**
    * Modifies TaxonomyTerm input to use a multiple choice select
    * {@link QueryBuilderField::get_input_type()}
    *
    * @return array
    */
   public static function get_term_input_type() {
      $options = array();

      $terms = VocabularyTerm::get()
         ->innerJoin('Vocabulary', '"Vocabulary".ID = "VocabularyTerm".VocabularyID')
         ->sort('"Vocabulary".MachineName ASC, "VocabularyTerm".MachineName ASC')
      ;

      foreach ($terms as $term) {
         $repr = "{$term->Vocabulary()->MachineName}.{$term->MachineName}";
         $options[$repr] = $repr;
      }

      return array(
         'type' => 'select',
         'default' => '',
         'options' => $options
      );
   }


   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummaryImpl($linePrefix = '') {
      $term = $this->Term();

      if($this->Inclusive) {
         return "Has vocabulary term '{$term->Term}' from vocabulary '{$term->Vocabulary()->Name}'";
      } else {
         return "Does not have vocabulary term '{$term->Term}' from vocabulary '{$term->Vocabulary()->Name}'";
      }
   }


   /**
    * Returns the representation of the current taxonomy term.
    * {@link QueryBuilderField::buildObjectStructure()}
    *
    * @return string
    */
   public function getTermStructure() {
      if (!$this->TermID)
         return "";

      $term = $this->Term();
      return "{$term->Vocabulary()->MachineName}.{$term->MachineName}";
   }


   /**
    * Return the DataObject for a term defined in the given representation.
    * Called by {@link QueryBuilderField::save_object()}
    *
    * Input is the same as the output of {@link TaxonomyTermPredicate::getTermStructure()}
    *
    * @param string
    * @return VocabularTerm
    */
   public function resolveTermStructure($term) {
      $term = explode(".", $term);
      if (count($term) < 2)
         return;

      $term = VocabularyTerm::find_by_machine_names($term[0], $term[1]);
      if (empty($term))
         return;

      return $term;
   }


   public function updateQueryImpl(&$query, $conjunctive) {
      $mainTable = $query->getPrimaryTableAlias();
      $stvt = $query->getTableAlias('SiteTree_VocabularyTerms');

      $query->leftJoin($stvt, "{$mainTable}.ID = {$stvt}.SiteTreeID AND {$stvt}.VocabularyTermID = {$this->Term()->ID}");
      if ($this->Inclusive) {
         $query->where("{$stvt}.ID IS NOT NULL", $conjunctive);
      } else {
         $query->where("{$stvt}.ID IS NULL", $conjunctive);
      }

      return true;
   }
}

