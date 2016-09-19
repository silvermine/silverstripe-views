<?php

/**
 * A ViewHost is a DataExtension that can be added to DataObjects to
 * allow them to have view definitions added to them.  With the default module
 * configuration all SiteTree nodes have the ViewHost DOD added to them.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewHost extends DataExtension {

   const TRAVERSAL_LEVEL_OWNER = 1;
   const TRAVERSAL_LEVEL_OWNER_DEFAULT_LOCALE = 2;
   const TRAVERSAL_LEVEL_PARENT = 3;
   const TRAVERSAL_LEVEL_PARENT_DEFAULT_LOCALE = 4;
   const TRAVERSAL_LEVEL_ANCESTORS_START = 5;
   const TRAVERSAL_LEVEL_SITE_CONFIG = 20000;
   const TRAVERSAL_LEVEL_DEFAULT_LOCALE_SITE_CONFIG = 20001;

   public static $has_one = array(
      'ViewCollection' => 'ViewCollection',
   );


   /**
    * This function is called by ContentController while it is initalizing
    * itself and is used to include the RSS links in the head of the page for
    * those views that should be automatically included in the page as links.
    */
   public function contentcontrollerInit($controller) {
      $this->includeRSSAutoLinkTags();
   }


   public function onTranslatableCreate($writeToDB) {
      $this->owner->ViewCollectionID = 0;

      if ($writeToDB) {
         $this->owner->write();
      }
   }


   /**
    * Given a ViewHost this will take all views on that host and pass each to
    * the callback so that it has the opportunity to add it to the list of
    * allViews if it meets whatever criteria the callback contains.
    *
    * The callback should take the following parameters:
    *   $host - the ViewHost passed in here
    *   $view - a single view from this host
    *   &$allViews - the reference to the array of views that is being build
    *   $traversalLevel - the current level of traversal
    *
    * The callback will contain the logic to see if a view should be added to
    * the array of views that is being built.  If it should, it is the
    * responsibility of the callback to add it to the array (allViews).
    *
    * The callback should return boolean - true if traversal should continue,
    * or false otherwise.  If it returns false not only will traversal through
    * the hierarchy stop, but the looping over the views on this host will also
    * immediately stop.
    *
    * @param ViewHost $host the host that contains views
    * @param function $calback the callback to pass the views to
    * @param array &$allViews the array of views that is being built by the callbacks
    * @param int $traversalLevel the current level we are at in the traversal
    * @return boolean if traversal should continue, false otherwise
    */
   private function filterViews($host, $callback, &$allViews, $traversalLevel) {
      if (!$host) {
         return true;
      }

      $views = $host->Views();
      if ($views) {
         foreach ($views as $view) {
            if (!$callback($host, $view, $allViews, $traversalLevel)) {
               return false;
            }
         }
      }

      return true;
   }


   /**
    * Internal function used to get a SiteConfig object.
    *
    * @param string $locale the locale, or null if none, to lookup a SiteConfig for
    * @return SiteConfig the config for the locale, or null if none
    */
   private function getSiteConfig($locale) {
      $config = ($locale == null) ?
         SiteConfig::get_one('SiteConfig') :
         SiteConfig::get_one('SiteConfig', sprintf('"Locale" = \'%s\'', Convert::raw2sql($locale)));

      return $config;
   }


   /**
    * Used by templates in a control block to retrieve a view by name.
    * Additionally, a boolean can be passed in to indicate whether or not the
    * hierarchy should be traversed to find the view on translations and
    * parents (default: true).
    *
    * @param string $name the name of the view to find
    * @param int $resultsPerPage (optional, default 0) - zero for unlimited results, otherwise how many to show per page
    * @param string $paginationURLParam the query string key to use for pagination (default: start)
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function GetView($name, $resultsPerPage = 0, $paginationURLParam = 'start', $traverse = true) {
      $view = null;

      $callback = function($host, $view, &$allViews, $traversalLevel) use(&$name, $traverse) {
         if (!$traverse && $traversalLevel > ViewHost::TRAVERSAL_LEVEL_OWNER_DEFAULT_LOCALE) {
            // without traversing we only look to the owner and owner in default locale
            return false;
         }

         if ($view->Name == $name) {
            array_push($allViews, $view);
            return false; // found it, don't continue traversal
         }
         return true; // still need to find it, continue traversal
      };

      $views = $this->traverseViews($callback);
      $view = count($views) ? $views[0] : null;

      if (is_object($view)) {
         $view->setTransientPaginationConfig($resultsPerPage, $paginationURLParam);
      }

      return $view;
   }


   /**
    * Used by templates in a conditional block to see if there is a view with a
    * given name defined on this page (or, if traversing, a translation or
    * parent)
    *
    * @param string $name the name of the view to find
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function HasView($name, $traverse = true) {
      return ($this->GetView($name, $resultsPerPage = 0, $paginationURLParam = 'start', $traverse) != null);
   }


   /**
    * Used by templates in a conditional block to see if there is a view with a
    * given name defined on this page (or, if traversing, a translation or
    * parent) AND the view has results.
    *
    * @param string $name the name of the view to find
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function HasViewWithResults($name, $traverse = true) {
      $view = $this->GetView($name, $resultsPerPage = 0, $paginationURLParam = 'start', $traverse);
      if ($view == null) {
         return false;
      }

      $results = $view->Results();
      return $results->exists();
   }


   /**
    * Just a helper function for templates because SS template parsing doesn't
    * allow multiple parameters to a function call in an if statement.
    * Calls HasViewWithResults and passes false as second arg.
    *
    * @see HasViewWithResults()
    * @param string $name the name of the view to find
    * @return View the found view or null if not found
    */
   public function HasViewWithResultsWithoutTraversal($name) {
      return $this->HasViewWithResults($name, false);
   }

   /**
    * Used by templates to add the automatically linked RSS links to the head of
    * a page for views that are automatically added to a page.
    */
   public function includeRSSAutoLinkTags($includeDefaultLocale = true) {
      $callback = function($host, $view, &$allViews, $traversalLevel) use (&$includeDefaultLocale) {
         if ($view->RSSEnabled && $view->RSSAutoLink != View::RSS_AUTO_LINK_NONE) {
            $add = false;
            switch ($view->RSSAutoLink) {
               case View::RSS_AUTO_LINK_PAGE_ONLY:
                  $add = in_array($traversalLevel, array(ViewHost::TRAVERSAL_LEVEL_OWNER, ViewHost::TRAVERSAL_LEVEL_OWNER_DEFAULT_LOCALE));
                  break;
               case View::RSS_AUTO_LINK_PAGE_AND_CHILDREN:
                  $add = in_array($traversalLevel, array(ViewHost::TRAVERSAL_LEVEL_OWNER, ViewHost::TRAVERSAL_LEVEL_OWNER_DEFAULT_LOCALE, ViewHost::TRAVERSAL_LEVEL_PARENT, ViewHost::TRAVERSAL_LEVEL_PARENT_DEFAULT_LOCALE));
                  break;
               case View::RSS_AUTO_LINK_CHILDREN:
                  $add = in_array($traversalLevel, array(ViewHost::TRAVERSAL_LEVEL_PARENT, ViewHost::TRAVERSAL_LEVEL_PARENT_DEFAULT_LOCALE));
                  break;
               case View::RSS_AUTO_LINK_PAGE_AND_DESCENDANTS:
                  $add = true;
                  break;
               case View::RSS_AUTO_LINK_DESCENDANTS:
                  $add = (false === in_array($traversalLevel, array(ViewHost::TRAVERSAL_LEVEL_OWNER, ViewHost::TRAVERSAL_LEVEL_OWNER_DEFAULT_LOCALE)));
                  break;
            }
            if ($add) {
               array_push($allViews, $view);
            }
         }
         return true;
      };

      $views = $this->traverseViews($callback, $includeDefaultLocale);

      foreach ($views as $view) {
         $url = Director::absoluteURL($view->Link());
         RSSFeed::linkToFeed($url);
      }
   }


   /**
    * Used internally to traverse views through the hierarchy of the site to
    * find one or more views.  Uses a callback to determine which views should
    * be included in the return array.
    *
    * See filterViews() for a description of the callback function that must be
    * passed into this function.
    *
    * @see filterViews()
    * @param function $callback the function that is used to filter which views are added
    * @param boolean $includeDefaultLocale (optional: default true) true if you want traversal to check the default locale for a view after checking a translated page
    * @param boolean $includeSiteconfig used internally for recursive calls - true if you want to look at SiteConfig for views too
    * @param array &$views used internally for recursive calls - the array of all views found in traversal
    * @param int &$level used internally for recursive calls - the current traversal level
    * @param boolean &$continue used internally for recursive calls - whether traversal should continue
    */
   private function traverseViews($callback, $includeDefaultLocale = true, $includeSiteConfig = true, &$views = array(), &$level = 1, &$continue = true) {
      // ATTEMPT 1: look on the owner of this object itself
      $continue = $this->filterViews($this->owner, $callback, $views, $level);

      // ATTEMPT 2: look on default locale translation (if possible and requested)
      $incremented = false;
      if ($continue && $includeDefaultLocale && class_exists('Translatable')) {
         $defaultLocale = Translatable::default_locale();
         if ($this->owner->hasExtension('Translatable') && $this->owner->Locale != $defaultLocale) {
            $master = $this->owner->getTranslation($defaultLocale);
            if ($master && $master->hasExtension('ViewHost')) {
               $incremented = true;
               $level++;
               $continue = $this->filterViews($master, $callback, $views, $level);
            }
         }
      }

      if (!$incremented) {
         $level++; // so that we'll still increment for the default locale traversal level
      }

      // ATTEMPT 3: go to my parent page and continue traversal
      if ($continue && $this->owner->hasExtension('Hierarchy') && $this->owner->ParentID && ($parent = $this->owner->Parent()) && $parent->hasExtension('ViewHost')) {
         $ext = $parent->getExtensionInstance('ViewHost');
         $ext->setOwner($parent);
         $level++;
         $ext->traverseViews($callback, $includeDefaultLocale, false, $views, $level, $continue);
      }

      // ATTEMPT 4: try to get global view from the SiteConfig object
      if ($continue && $includeSiteConfig && singleton('SiteConfig')->hasExtension('ViewHost')) {
         if (singleton('SiteConfig')->hasExtension('Translatable') && $this->owner->hasExtension('Translatable')) {
            $config = $this->getSiteConfig($this->owner->Locale);
            if ($config) {
               $continue = $this->filterViews($config, $callback, $views, ViewHost::TRAVERSAL_LEVEL_SITE_CONFIG);
            }
            if ($continue && $includeDefaultLocale && (!$config || $config->Locale != Translatable::default_locale())) {
               $config = $this->getSiteConfig(Translatable::default_locale());
               if ($config) {
                  $continue = $this->filterViews($config, $callback, $views, ViewHost::TRAVERSAL_LEVEL_DEFAULT_LOCALE_SITE_CONFIG);
               }
            }
         }
      }

      // We say that the current host is the owner because it is the reference point
      // where the view was found.  It may not actually be the "owner" in the sense
      // that it belongs to this host's view collection.  However, if it was found
      // based on this host then it should be able to be found the same way in the
      // future.  Or, this host could potentially get its own view that it actually
      // owns (in the sense that it belongs to this host's view collection) by the
      // same name later on, in which case that new view would usurp this view, which
      // could potentially have come from this host's master translation, parent, etc.
      // The owner of a view is used to compute owner-relative info like RSS links.
      foreach ($views as $view) {
         $view->setOwner($this->owner);
      }
      return $views;
   }


   /**
    * @see DataExtension->updateCMSFields()
    */
   public function updateCMSFields(FieldList $fields) {
      // TODO: this code should likely live somewhere else, we just need
      // to be sure that our owner has a view collection before we can proceed
      // otherwise we might try adding a view to an UnsavedRelationList
      if (!$this->owner->ViewCollection()->ID) {
         $vcID = $this->owner->ViewCollection()->write();
         $this->owner->ViewCollectionID = $vcID;

         // If the current_stage is Live, the record isn't published, and we run write(),
         // Silverstripe will INSERT a bad row into SiteTree_Live. Therefore, we insure we're
         // writing to the unpublished stage.
         $stage = Versioned::current_stage();
         Versioned::reading_stage('Stage');
         $this->owner->write();
         Versioned::reading_stage($stage);
      }
      $config = GridFieldConfig_RecordEditor::create($itemsPerPage = 20);
      $viewsGrid = GridField::create(
         'Views',
         _t('Views.ViewsLabel', 'Views'),
         $this->owner->ViewCollection()->Views(),
         $config
      );
      $fields->addFieldToTab('Root.Views', $viewsGrid);
   }


   /**
    * Accessor for retrieving all views attached to the owning data object.
    */
   public function Views() {
      $coll = $this->owner->ViewCollection();
      if (is_null($coll)) {
         return new ArrayList(array());
      }

      $views = $coll->Views();
      foreach ($views as $view) {
         $view->setOwner($this->owner);
      }
      return $views;
   }

}
