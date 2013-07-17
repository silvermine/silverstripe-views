<?php

/**
 * The simplest type of results retriever, this class allows a content manager
 * to manually select pages that should appear within the result set and order
 * them as they wish.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class HandPickedResultsRetriever extends ViewResultsRetriever {

   static $db = array();

   static $defaults = array(
      'Transformation' => 'TranslatePageLocale',
   );

   static $many_many = array(
      'Pages' => 'SiteTree',
   );

   static $many_many_extraFields = array(
      'Pages' => array(
         'SortOrder' => 'Int',
      ),
   );
   
   /**
    * {@link ViewResultsRetriever::count}
    */
   public function count() {
      $qb = $this->getQuery();

      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();

      return $results->count();
   }
   
   /**
    * {@link ViewResultsRetriever::dumpPreservedFields}
    */
   public function dumpPreservedFields() {
      $pages = array();
      foreach($this->Pages() as $page)
         $pages[] = $page;
      
      return array(
         'Pages' => $pages
      );
   }
   
   /**
    * Return a QueryBuilder instance set up to query for objects
    * in $this->Pages()
    * 
    * @return QueryBuilder
    */
   private function &getQuery() {
      $qb = new QueryBuilder();

      $vernSiteTree = $qb->selectObjects(self::$many_many['Pages']);

      // If Translatable isn't loaded, just return the basic results
      $locale = $this->getTransformedResultsLocale();
      $masterSiteTree = $vernSiteTree;
      if ($this->isTranslatable() && $locale) {
         $masterSiteTree = $qb->translateResults($locale);
      }

      $pages = $qb->getTableAlias('HandPickedResultsRetriever_Pages');
      $id = Convert::raw2sql($this->ID);
      $join = sprintf("{$pages}.HandPickedResultsRetrieverID = %d AND {$pages}.SiteTreeID = {$masterSiteTree}.ID", $id);
      $qb->innerJoin($pages, $join);

      $qb->orderby("{$pages}.SortOrder", $ascending = true);

      return $qb;
   }

   /**
    * @see ViewResultsRetriever->getReadOnlySummary()
    */
   public function getReadOnlySummary() {
      $html = '';
      $results = $this->Results();
      foreach($results as $page) {
         $html .= '&nbsp;&nbsp;&nbsp;&nbsp;' . _t('Views.PageRef', 'Page reference') . ': [' . $page->ID . '] ' . $page->Title . '<br />';
      }
      return $html;
   }
   
   /**
    * Returns true if Pages can be translated
    */
   private function isTranslatable() {
      return call_user_func(self::$many_many['Pages'] . '::has_extension', 'Translatable');
   }
   
   /**
    * {@link ViewResultsRetriever::loadPreservedFields}
    */
   public function loadPreservedFields($data) {
      $pages = array_key_exists('Pages', $data) ? $data['Pages'] : array();
      $this->Pages()->removeAll();
      foreach($pages as $page)
         $this->Pages()->add($page);
   }

   /**
    * Deletes the associated many_many rows for hand-picked pages before
    * deleting this results retriever.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      parent::Pages()->removeAll();
   }

   /**
    * Override the default Pages implementation to sort the pages in the
    * correct sort order (based on the many_many_extraFields column).
    *
    * @return SS_List or null the pages associated with this results retriever
    */
   public function Pages() {
      return parent::Pages(null, 'SortOrder ASC');
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl($offset, $limit) {
      // Build a query to retrieve translations of the selected pages
      $qb = $this->getQuery();
      $qb->limit($limit);
      $qb->offset($offset);
      
      Translatable::disable_locale_filter();
      $results = $qb->execute();
      Translatable::enable_locale_filter();
      
      return $results;
   }

   /**
    * @see ViewResultsRetriever->updateCMSFields()
    */
   public function updateCMSFields(&$view, &$fields) {
      parent::updateCMSFields($view, $fields);
      $picker = new ManyManyPickerField(
         $view,
         'ResultsRetriever.Pages',
         _t('Views.Pages.Label', 'Pages'),
         array(
            'ShowPickedInSearch' => false,
            'Sortable'           => true,
            'SortableField'      => 'SortOrder',
         )
      );
      $fields->addFieldToTab('Root.Main', $picker);
   }
}

