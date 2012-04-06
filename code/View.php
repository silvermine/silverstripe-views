<?php

/**
 * A view is a definition of an object that retrieves pages from the CMS.  It
 * can also be conceptualized as a placeholder in a template where one or more
 * pages/nodes are referenced.  The actual content that appears in these place-
 * holders is defined in a view that is added to a SiteTree node through the
 * UI.  This gives your content managers the ability to dynamically change the
 * content that is featured in your templates.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class View extends DataObject {

   static $db = array(
      'Name'        => 'VARCHAR(32)',
   );

   static $has_one = array(
      'ResultsRetriever' => 'ViewResultsRetriever',
      'ViewCollection'   => 'ViewCollection',
   );

   static $default_sort = 'Name';

   // these are transient - set by the template when using the view
   private $resultsPerPage = 0;
   private $paginationURLParam = 'start';

   /**
    * @see DataObject->getCMSFields()
    */
   function getCMSFields() {
      $fields = new FieldSet(
         new TabSet('Root',
            new Tab('Main',
               new TextField('Name', _t('Views.Name.Label', 'Name'))
            )
         )
      );

      $rr = $this->ResultsRetriever();
      if ($this->ID && $rr != null && get_class($rr) != 'ViewResultsRetriever') {
         // only allow editing of actual results retriever on non-transient views
         $rr->updateCMSFields($this, $fields);
      }

      return $fields;
   }

   /**
    * Used in the current configuration of the views UI
    */
   public function getReadOnlySummary() {
      $html = '<strong style="font-size: 1.1em;">' . $this->Name . '</strong> <em>(' . get_class($this->ResultsRetriever()) . ')</em><br />';
      $html .= '<span style="font-size: 0.9em;">' . $this->ResultsRetriever()->getReadOnlySummary() . '</span>';
      return $html;
   }

   /**
    * Used by ComplexTableField to validate objects added in the CMS UI
    *
    * @todo add a unique-per-hosting-object validation rule to "Name"
    *       (can probably use UniqueTextField for this)
    */
   public function getValidator() {
      return new RequiredFields('Name', 'ResultsRetrieverID');
   }

   /**
    * Deletes the associated results retriever before deleting this view.
    *
    * @see DataObject#onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();

      $this->ResultsRetriever()->delete();
   }

   /**
    * Paginates a DataObjectSet according to the current transient pagination
    * config.
    *
    * @todo - obviously loading all results just to paginate them and return a
    * a portion of them is not a great architectural decision.  This needs to
    * be revisited to see how this can be improved without making the API for
    * results retrievers too complex.  Part of the complexity comes from the
    * TranslatedResults function.  If the translated results function only
    * loads one page of results and then translates them (finding equivalents
    * in the current locale), it can end up with less results.  Thus, it must
    * really load all results to even know which one to start from (each page
    * could have less results).  Because of this we will just load all results
    * for now and come back to visit this problem later.
    *
    * @param DataObjectSet $all a set containing all possible results to be paginated
    * @return null|DataObjectSet null if null is passed in, otherwise an appropriately paginated DataObjectSet
    */
   private function paginate($all) {
      if (is_null($all)) {
         return null;
      }

      if ($this->resultsPerPage <= 0) {
         $all->setPageLimits(0, PHP_INT_MAX, $all->Count());
         return $all;
      }

      $start = 0;
      if (Controller::curr() && Controller::curr()->getRequest() && Controller::curr()->getRequest()->getVar($this->paginationURLParam)) {
         $startVal = Controller::curr()->getRequest()->getVar($this->paginationURLParam);
         $start = is_numeric($startVal) ? ((int) $startVal) : $start;
      }

      $results = new DataObjectSet(array_slice($all->toArray(), $start, $this->resultsPerPage));
      $results->setPaginationGetVar($this->paginationURLParam);
      $results->setPageLimits($start, $this->resultsPerPage, $all->Count());
      return $results;
   }

   /**
    * Helper function for templates so they can call the Results function from
    * the view itself without having to get the results retriever as well.
    *
    * @return DataObjectSet the results in the current locale or null if none found
    */
   public function Results() {
      return $this->paginate($this->ResultsRetriever()->Results());
   }

   /**
    * When a view is retrieved by a template, the template can specify
    * pagination configuration like how many results to show on each page and
    * what URL parameter to use for pagination.  The view host then calls this
    * function to set that transient config on this view so it can be used in
    * the results in the template.
    *
    * @param int $resultsPerPage number of results per page (zero means unlimited)
    * @param string $paginationURLParam the URL parameter to use for pagination
    * @return this view for chaining function calls
    */
   public function setTransientPaginationConfig($resultsPerPage, $paginationURLParam) {
      $this->resultsPerPage = $resultsPerPage;
      $this->paginationURLParam = $paginationURLParam;
      return $this;
   }

   /**
    * Helper function for templates so they can call the TranslatedResults
    * function from the view itself without having to get the results retriever
    * as well.
    *
    * @return DataObjectSet the results in the current locale or null if none found
    */
   public function TranslatedResults() {
      return $this->paginate($this->ResultsRetriever()->TranslatedResults());
   }

}
