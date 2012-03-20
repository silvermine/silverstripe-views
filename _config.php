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

// add built-in special values that can be used by FieldPredicate objects
FieldPredicateValue::add_special_value('%%CurrentPageLocale%%', function($fpv) {
   $page = Director::currentPage();
   return $page ? $page->Locale : null;
});

FieldPredicateValue::add_special_value('%%CurrentPageID%%', function($fpv) {
   $page = Director::currentPage();
   return $page ? $page->ID : 0;
});
