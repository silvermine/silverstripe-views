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
 */

// TODO: review all uses of the _t() function in this module
// TODO: convert this to YML config and add README.md as documentation rather
// than just comments here.
DataObject::add_extension('SiteTree', 'ViewHost');
DataObject::add_extension('SiteConfig', 'ViewHost');

/**
 * If you want to enable the RSS functionality for views, you can add this
 * extension as shown here, or to customize the functionality you can create
 * your own subclass of RSSContentControllerExtension and override functions
 * such as createFeed($view, $controller, $response) within it.
 * Object::add_extension('ContentController', 'RSSContentControllerExtension');
 *
 * To enable this you will also need to add the 'rss' action to the
 * allowed_actions array in your Page_Controller class (or on specific pages'
 * controllers if you only want it available on those page types).
 */

