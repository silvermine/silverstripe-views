<?php

/**
 * The silverstripe-views module provides a way for UI (theme) designers and
 * content organizers to define dynamic "views", or queries into the content
 * system, to utilize in their templates.  These views can then power any
 * number of interface features and widgets without a developer needing to
 * write custom query functions that can be called from the SilverStripe
 * templates (in control tags).
 *
 * This configuration file adds the ViewHost extension to all SiteTree nodes to
 * make this a "plug-and-play" module.  You simply drop it in your SilverStripe
 * web root and it will be enabled on your SiteTree.
 *
 * NOTE: this module requires a fork of ajshort's silverstripe-itemsetfield
 * module available at https://github.com/jthomerson/silverstripe-itemsetfield
 * This is because there are additional features in jthomerson's itemsetfield
 * that have not yet been merged to ajshort's original version.
 */

// TODO: review all uses of the _t() function in this module

DataObject::add_extension('SiteTree', 'ViewHost');
DataObject::add_extension('SiteConfig', 'ViewHost');

// add built-in special tokens that can be used by FieldPredicate objects
// TODO: document these and their potential uses
// TODO: also look into moving them out into classes or something rather than having
//       them all here in the _config file
FieldPredicateValue::add_value_token('ContentLocale', function(&$fpv, $tokenParam) {
   $page = Director::get_current_page();
   if (!$page instanceof SiteTree || !$page->hasExtension('Translatable'))
      return null;
   
   if (Controller::curr() && Controller::curr()->getRequest() && Controller::curr()->getRequest()->getVar($tokenParam))
      return Convert::raw2sql(Controller::curr()->getRequest()->getVar($tokenParam));
   return $page->Locale;
});

FieldPredicateValue::add_value_token('CurrentPageLocale', function(&$fpv) {
   $page = Director::get_current_page();
   return ($page instanceof SiteTree && $page->hasExtension('Translatable')) ? $page->Locale : null;
});

FieldPredicateValue::add_value_token('CurrentPageID', function(&$fpv) {
   $page = Director::get_current_page();
   return ($page instanceof SiteTree) ? $page->ID : null;
});

FieldPredicateValue::add_value_token('CurrentPageTransID', function(&$fpv, $tokenParam) {
   $page = Director::get_current_page();

   $locale = '';
   if (Controller::curr() && Controller::curr()->getRequest()) {
      $locale = Controller::curr()->getRequest()->getVar($tokenParam);
   }

   if (!empty($locale) && $page instanceof SiteTree && $page->hasExtension('Translatable')) {
      $translatedPage = $page->getTranslation($locale);
      return ($translatedPage instanceof SiteTree) ? $translatedPage->ID : $page->ID;
   }

   return ($page instanceof SiteTree) ? $page->ID : null;
});

FieldPredicateValue::add_value_token('QueryParam', function(&$fpv, $tokenParam) {
   if (Controller::curr() && Controller::curr()->getRequest()) {
      return Convert::raw2sql(Controller::curr()->getRequest()->getVar($tokenParam));
   }

   return null;
});

