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
    * All subclasses must implement this function, which is the primary
    * interface to the outside world.  When a view is requested, this function
    * will be called and expected to return a DataObjectSet of results or null
    * if no results could be retrieved.
    *
    * @param int $maxResults the maximum number of results to return
    * @return DataObjectSet|null the results or null if none found
    */
   public function Results($maxResults = 0) {
      throw new UnsupportedOperationException('The ' . get_class($this) . ' class needs to implement Results(int).');
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
   public function TranslatedResults($maxResults = 0) {
      Translatable::disable_locale_filter();
      $results = $this->Results($maxResults);
      Translatable::enable_locale_filter();

      if (empty($results)) {
         return null;
      }

      $currentPage = Director::get_current_page();
      if ($currentPage == null || !$currentPage->hasExtension('Translatable')) {
         return $results;
      }

      $locale = $currentPage->Locale;
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
      // no default operation
   }

}
