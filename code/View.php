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

   /**
    * RSS auto-link settings control which pages an RSS feed is automatically
    * added to as a <link> tag in the header.
    */
   // never automatically appears
   const RSS_AUTO_LINK_NONE = 'None';

   // automatically appears only on the host page
   const RSS_AUTO_LINK_PAGE_ONLY = 'PageOnly';

   // automatically appears on page and direct children of the host page
   const RSS_AUTO_LINK_PAGE_AND_CHILDREN = 'PageAndChildren';

   // automatically appears on direct children of the host page (only - not on the host page itself)
   const RSS_AUTO_LINK_CHILDREN = 'Children';

   // automatically appears on page and all descendants of the host page - no matter their depth
   const RSS_AUTO_LINK_PAGE_AND_DESCENDANTS = 'PageAndDescendants';

   // automatically appears on all descendants of the host page - no matter their depth (but not on the host page itself)
   const RSS_AUTO_LINK_DESCENDANTS = 'Descendants';

   static $db = array(
      'Name'        => 'VARCHAR(32)',
      'RSSEnabled'  => 'BOOLEAN',
      'RSSAutoLink' => "ENUM('None,PageOnly,PageAndChildren,Children,PageAndDescendants,Descendants')",
      'RSSItems'    => 'Int',
   );

   static $defaults = array(
      'RSSItems'    => 20,
   );

   static $has_one = array(
      'ResultsRetriever' => 'ViewResultsRetriever',
      'ViewCollection'   => 'ViewCollection',
   );

   static $default_sort = 'Name';

   static $reset_pagination_for_bad_value = true;

   // these are transient - set by the template when using the view
   private $owner;
   private $resultsPerPage = 0;
   private $paginationURLParam = 'start';

   /**
    * @see DataObject->getCMSFields()
    */
   function getCMSFields() {
      $fields = new FieldSet(
         new TabSet('Root',
            new Tab('Main',
               new TextField('Name', _t('Views.Name.Label', 'Name')),
               new CheckboxField('RSSEnabled', _t('Views.RSSEnabled.Label', 'RSS Enabled')),
               new TextField('RSSItems', _t('Views.RSSItems.Label', 'RSS Number of Items')),
               new DropDownField(
                  'RSSAutoLink',
                  _t('Views.RSSAutoLink.Label', 'RSS Auto Link'),
                  array(
                     self::RSS_AUTO_LINK_NONE => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_NONE . '.Label', 'None'),
                     self::RSS_AUTO_LINK_PAGE_ONLY => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_PAGE_ONLY . '.Label', 'Page only'),
                     self::RSS_AUTO_LINK_PAGE_AND_CHILDREN => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_PAGE_AND_CHILDREN . '.Label', 'Page and children'),
                     self::RSS_AUTO_LINK_CHILDREN => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_CHILDREN . '.Label', 'Direct children only'),
                     self::RSS_AUTO_LINK_PAGE_AND_DESCENDANTS => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_PAGE_AND_DESCENDANTS . '.Label', 'Page and descendants'),
                     self::RSS_AUTO_LINK_DESCENDANTS => _t('Views.RSSAutoLink.' . self::RSS_AUTO_LINK_DESCENDANTS . '.Label', 'All descendants'),
                  )
               )
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
    * Return the SiteTree node that this view is attached to.
    * 
    * @return SiteTree
    */
   public function getPage() {
      return SiteTree::get_one('SiteTree', '"ViewCollectionID" = ' . Convert::raw2sql($this->ViewCollection()->ID));
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
    * Return the max number of results to get
    * 
    * @return integer
    */
   private function getResultsLimit() {
      return $this->resultsPerPage;
   }
   
   /**
    * Return the results offset
    * 
    * @return integer
    */
   private function getResultsOffset() {
      $offset = 0;

      $controller = Controller::curr();
      if (!$controller)
         return $offset;

      $request = $controller->getRequest();
      if (!$request)
         return $offset;

      $startParam = $request->getVar($this->paginationURLParam);
      if(!$startParam)
         return $offset;

      // use max(0, $offset) to avoid potential for negative numbers
      $offset = max(0, (is_numeric($startParam) ? ((int)$startParam) : $offset));
      return $offset;
   }

   /**
    * Used by ComplexTableField to validate objects added in the CMS UI
    *
    * @todo add a unique-per-hosting-object validation rule to "Name"
    *       (can probably use UniqueTextField for this)
    * @todo "Name" should also be only alphanumeric characters because of the
    *       way it is used in templates as well as by RSS feeds with i18n to
    *       get the title of a feed
    */
   public function getValidator() {
      return new RequiredFields('Name', 'ResultsRetrieverID');
   }

   /**
    * Returns a URL relative to the owner.  The owner must have been set (this
    * is generally done already by ViewHost) and it must have a Link function
    * itself for this to work.
    *
    * @todo support varying URL formats - but these will also need to be
    * supported by the RSS serving code in RSSContentControllerExtension
    *
    * @return string URL to this View as an RSS feed
    */
   public function Link() {
      if (!$this->owner || !$this->owner->hasMethod('Link')) {
         throw new Exception("can not make link to a view if we don't have an owner with a Link function");
      }
      $url = $this->owner->Link();
      if ($this->RSSEnabled) {
         $url .= (substr($url, -1) == '/' ? '' : '/');
         $url .= 'rss/';
         $url .= urlencode($this->Name);
         $url .= '/feed.xml';
      }
      return $url;
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
    * Since SS form fields do not currently allow dot notation, child objects
    * such as the ResultsRetriever have no way of really adding fields to the
    * form that edits the view.  The only way they can do it is to add the
    * field and then check for the value in the request manually at some point
    * in the form submission process.  By calling ResultsRetriever->write() we
    * give it the ability to use onBeforeWrite to get fields from the submitted
    * form and set those values on itself and then write the updates.
    */
   public function onBeforeWrite() {
      parent::onBeforeWrite();
      $this->ResultsRetriever()->write();
   }

   /**
    * Helper function for templates so they can call the Results function from
    * the view itself without having to get the results retriever as well.
    *
    * @return DataObjectSet the results in the current locale or null if none found
    */
   public function Results() {
      $offset = $this->getResultsOffset();
      $limit = $this->getResultsLimit();
      
      $retreiver = $this->ResultsRetriever();
      $results = $retreiver->results($offset, $limit);
      
      if ($results) {
         $results->setPaginationGetVar($this->paginationURLParam);
         $results->setPageLimits($offset, $limit, $retreiver->count());
      }
      
      return $results;
   }
   
   /**
    * Harness QueryBuilderField to deconstruct the JSON from a 
    * saved ViewResultsRetreiver and save it to the DB.
    * 
    * @param JSON Data
    */
   public function saveViewResultsRetriever($data) {
      $oldRetriever = $this->ResultsRetriever();
      $preservedData = $oldRetriever->dumpPreservedFields();
      
      $resultsRetriever = QueryBuilderField::save($data);
      if (!is_object($resultsRetriever))
         return;
      
      if ($oldRetriever)
         $oldRetriever->delete();
      
      $resultsRetriever->loadPreservedFields($preservedData);
      $this->ResultsRetrieverID = $resultsRetriever->ID;
      $this->write();
   }

   /**
    * When a ViewHost retrieves a view from the database it should call
    * setOwner on the view and pass its own owner in so that the View can use
    * this to create links.
    *
    * @param DataObject (typically Page) the owner of this view
    */
   public function setOwner($owner) {
      $this->owner = $owner;
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
}
