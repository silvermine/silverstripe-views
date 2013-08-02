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

   private static $autocomplete_format = '$Title';

   private static $autocomplete_search_fields = array(
      'Title',
      'URLSegment',
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
      $html = '<ul>';
      $results = $this->Results();
      foreach($results as $page) {
         $html .= '<li>' . _t('Views.AdminHPRRPage', 'Page') . ': [' . $page->ID . '] ' . $page->Title . '</li>';
      }
      $html .= '</ul>';
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
      return parent::Pages()->sort('SortOrder ASC');
   }

   /**
    * @see ViewResultsRetriever->resultsImpl()
    */
   protected function resultsImpl() {
      // Build a query to retrieve translations of the selected pages
      $qb = $this->getQuery();

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

      $config = GridFieldConfig_RelationEditor::create($itemsPerPage = 20)
         ->removeComponentsByType('GridFieldEditButton')
         ->removeComponentsByType('GridFieldDeleteAction')
         ->removeComponentsByType('GridFieldAddNewButton')
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->toTop())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->up())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->down())
         ->addComponent(GridFieldUpDownSortAction::create('SortOrder')->toBottom())
         ->addComponent(new GridFieldDeleteAction($removeRelation = true))
      ;
      $autocompleter = $config->getComponentByType('GridFieldAddExistingAutocompleter');
      $autocompleter->setSearchList($this->createSearchDataList());
      $autocompleter->setResultsFormat($this->config()->get('autocomplete_format'));
      $autocompleter->setSearchFields($this->config()->get('autocomplete_search_fields'));

      $picker = new GridField(
         'Pages',
         _t('Views.HandPickedPagesLabel', 'Pages'),
         $this->Pages(),
         $config
      );
      $fields->addFieldToTab('Root.Main', $picker);
   }

   protected function createSearchDataList() {
      $list = DataList::create('SiteTree');
      $classes = ClassInfo::dataClassesFor('SiteTree');
      $baseClass = array_shift($classes);

      foreach($classes as $class) {
         $list = $list->leftJoin($class, sprintf('"SiteTree"."ID" = "%s"."ID"', $class));
      }

      // needed this because of the problem described in
      // https://github.com/silverstripe/silverstripe-framework/pull/2267
      $list = $list->where(sprintf('"SiteTree".Locale = \'%s\'', Translatable::get_current_locale()));
      return $list;
   }
}

