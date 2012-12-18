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

   protected function getCurrentPageLocale() {
      $currentPage = Director::get_current_page();
      if ($currentPage == null || !$currentPage->hasExtension('Translatable')) {
         return false;
      }

      return $currentPage->Locale;
   }

   protected function getHiddenFormFieldValue() {
      return md5($this->Transformation . '--' . $this->QueryParamName);
   }

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

   /**
    * Since we add fields to the View's edit form, there isn't a way for those
    * fields to automatically set values on our object.  Thus, we must marshal
    * that data ourself.
    *
    * @see View->onBeforeWrite() for more information.
    */
   public function onBeforeWrite() {
      parent::onBeforeWrite();
      $req = Controller::curr()->getRequest();
      if ($req->postVar('ResultsRetrieverSubmit') == $this->getHiddenFormFieldValue()) {
         $this->Transformation = $req->postVar('Transformation');
         $this->QueryParamName = $req->postVar('QueryParamName');
      }
   }

   public function Results($maxResults = 0) {
      Translatable::disable_locale_filter();
      $results = $this->resultsImpl();
      Translatable::enable_locale_filter();
      if (!$results || empty($results)) {
         return null;
      }

      // if at some point more transformations are added, these transformation
      // implementations should likely be injected as some sort of interface
      // implementation instead of just being a big switch statement.
      switch ($this->Transformation) {
         case self::TRANSFORMATION_TRANSLATE_PAGE_LOCALE:
            return $this->translateResults($results, $this->getCurrentPageLocale());
         case self::TRANSFORMATION_TRANSLATE_QUERY_PARAM_LOCALE:
            return $this->translateResults($results, $this->getQueryParamLocale());
      }

      return $results;
   }

   /**
    * All subclasses must implement this function, which is their way of
    * performing their one job - retrieving results.  This function is called
    * by the Results function, which is the primary interface to the outside
    * world.  When a view is requested, the Results function will be called and
    * expected to return a DataObjectSet of results or null if no results could
    * be retrieved.  This impl function is expected to follow the same contract.
    *
    * @param int $maxResults the maximum number of results to return
    * @return DataObjectSet|null the results or null if none found
    */
   protected function resultsImpl($maxResults = 0) {
      throw new RuntimeException('The ' . get_class($this) . ' class needs to implement resultsImpl(int).');
   }

   /**
    * This function disables the Translatable locale filter and then takes the
    * results returned by the Results() function and checks each node returned
    * to see if there are equivalent translations in the language of the
    * current page.  This allows you to create a view on one page (in the
    * master/default language/locale) and have all translations of that page
    * use the same view.  Thus you don't need to create the view on every
    * translation of the page, saving you considerable time.
    *
    * NOTE: if the current page can not be found or is not translatable this
    * function will simply return the results that were returned by Results()
    *
    * @todo fix $maxResults functionality... by passing it to the results
    *            retriever we are really breaking this.  The results retriever
    *            might return 5 of 10 actual results (if we pass 5), and we
    *            might only have three translations of those five results.  But
    *            if we retrieved all results and then checked for translations
    *            we might be able to get up to our real max.
    *
    * @param int $maxResults maximum number of results to retriever, or 0 for infinite (default 0)
    * @return DataObjectSet the results in the current locale or null if none found
    */
   protected function translateResults(&$results, $locale) {
      if (empty($results)) {
         return null;
      }

      if ($locale === false) {
         return $results;
      }

      $translatedResults = array();
      foreach ($results as $result) {
         if (!$result->hasExtension('Translatable')) {
            continue;
         }

         if ($result->Locale == $locale) {
            // no need to translate - our results retriever retrieved the result
            // in the correct locale already
            array_push($translatedResults, $result);
            continue;
         } elseif(($translatedResult = $result->getTranslation($locale)) != null) {
            array_push($translatedResults, $translatedResult);
         }
      }

      return empty($translatedResults) ? null : new DataObjectset($translatedResults);
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
      $transformation = new DropDownField(
         'Transformation',
         _t('Views.Transformation.Label', 'Results Transformation'),
         array(
            self::TRANSFORMATION_NONE => _t('Views.Transformation.None.Label', 'None'),
            self::TRANSFORMATION_TRANSLATE_PAGE_LOCALE  => _t('Views.Transformation.TranslatePageLocale.Label', 'Translate into locale of page'),
            self::TRANSFORMATION_TRANSLATE_QUERY_PARAM_LOCALE => _t('Views.Transformation.TranslateQueryLocale.Label', 'Translate into locale of query param (or page if no param)'),
         ),
         $this->Transformation
      );
      $paramName = new TextField(
         'QueryParamName',
         _t('Views.QueryParamName.Label', 'Query Param Name (only applicable for some transformations)'),
         $this->QueryParamName
      );
      $fields->addFieldToTab('Root.Main', $transformation);
      $fields->addFieldToTab('Root.Main', $paramName);
      $fields->addFieldToTab('Root.Main', new HiddenField('ResultsRetrieverSubmit', null, $this->getHiddenFormFieldValue()));
   }

}
