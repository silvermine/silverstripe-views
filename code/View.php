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
      'Host'             => 'DataObject',
   );

   static $default_sort = 'Name';

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
    * Helper function for templates so they can call the Results function from
    * the view itself without having to get the results retriever as well.
    *
    * @param int $maxResults maximum number of results to retriever, or 0 for infinite (default 0)
    * @return DataObjectSet the results in the current locale or null if none found
    */
   public function Results($maxResults = 0) {
      return $this->ResultsRetriever()->Results($maxResults);
   }

   /**
    * Helper function for templates so they can call the TranslatedResults
    * function from the view itself without having to get the results retriever
    * as well.
    *
    * @param int $maxResults maximum number of results to retriever, or 0 for infinite (default 0)
    * @return DataObjectSet the results in the current locale or null if none found
    */
   public function TranslatedResults($maxResults = 0) {
      return $this->ResultsRetriever()->TranslatedResults($maxResults);
   }

}
