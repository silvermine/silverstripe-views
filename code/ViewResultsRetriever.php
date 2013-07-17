<?php

/**
 * Base class for all classes which provide results to a View.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewResultsRetriever extends DataObject {

   const TRANSFORMATION_NONE = 'None';
   const TRANSFORMATION_TRANSLATE_PAGE_LOCALE = 'TranslatePageLocale';
   const TRANSFORMATION_TRANSLATE_QUERY_PARAM_LOCALE = 'TranslateQueryLocale';

   static $db = array(
      'Transformation' => "ENUM('None,TranslatePageLocale,TranslateQueryLocale')",
      'QueryParamName' => 'VARCHAR(32)',
   );

   static $defaults = array(
      'Transformation' => 'None',
   );
   
   /**
    * Return the max number of results possible
    * 
    * @return integer
    */
   public function count() {
      throw new RuntimeException('The ' . get_class($this) . ' class needs to implement count().');
   }
   
   /**
    * Used to dump fields the need to be preserved by, but
    * not modified by QueryBuilderField upon a save action.
    * 
    * @return array
    */
   public function dumpPreservedFields() {
      return array();
   }

   /**
    * Get the locale of the current page
    * 
    * @return string
    */
   protected function getCurrentPageLocale() {
      $currentPage = Director::get_current_page();
      if ($currentPage == null || !$currentPage->hasExtension('Translatable')) {
         return false;
      }

      return $currentPage->Locale;
   }

   /**
    * Get the locale from the pre-defined query param
    * 
    * @return string
    */
   protected function getQueryParamLocale() {
      if ($this->QueryParamName) {
         $locale = QueryParamTokenizer::get_value($this->QueryParamName);
      }
      if ($locale !== null && i18n::validate_locale($locale)) {
         return $locale;
      }

      return $this->getCurrentPageLocale();
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

   protected function getTransformedResultsLocale() {
      switch ($this->Transformation) {
         case self::TRANSFORMATION_TRANSLATE_PAGE_LOCALE:
            return $this->getCurrentPageLocale();
         
         case self::TRANSFORMATION_TRANSLATE_QUERY_PARAM_LOCALE:
            return $this->getQueryParamLocale();
      }
   }

   /**
    * Used to load fields the need to be preserved by, but
    * not modified by QueryBuilderField upon a save action.
    * 
    * @param array
    */
   public function loadPreservedFields($data) {
   }

   /**
    * Return the results
    * 
    * @param integer $offset
    * @param integer $limit
    * @return SS_List
    */
   public function results($offset = 0, $limit = 1000) {
      $results = $this->resultsImpl($offset, $limit);
      // this is basically just here in case any results retrievers have a faulty implementation
      // of resultsImpl that doesn't return a list of some sort:
      $results = (!$results || empty($results)) ? new ArrayList(array()) : $results;
      return $results;
   }

   /**
    * All subclasses must implement this function, which is their way of
    * performing their one job - retrieving results.  This function is called
    * by the Results function, which is the primary interface to the outside
    * world.  When a view is requested, the Results function will be called and
    * expected to return an SS_List of results or null if no results could
    * be retrieved.  This impl function is expected to follow the same contract.
    *
    * @param int $maxResults the maximum number of results to return
    * @return SS_List|null the results or null if none found
    */
   protected function resultsImpl($offset, $limit) {
      throw new RuntimeException('The ' . get_class($this) . ' class needs to implement resultsImpl(int, int).');
   }

   /**
    * All subclasses should implement this function, which provides them a way
    * of adding fields to the "add/edit view" CMS form.  These fields will be
    * what the user uses to modify this results retriever.
    *
    * @param View reference to the view that contains this results retriever
    * @param FieldSet the fields for this view form
    */
   public function updateCMSFields(&$view, &$fields) {
      $editor = new QueryBuilderField(
         __CLASS__,
         _t('Views.QueryBuilder.Label', 'QueryBuilder'),
         $this
      );
      
      $fields->addFieldToTab('Root.QueryEditor', $editor);
   }

}
