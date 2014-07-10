<?php

/**
 * This extension is designed to be used on ContentController to intercept
 * the initialization of the controller to catch RSS actions and serve RSS
 * feeds based on views.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class RSSContentControllerExtension extends Extension {

   private static $allowed_actions = array('rss');


   /**
    * Create the RSS feed for a given view.  This function is designed to be
    * overridden by any client code that wants to customize the behavior of the
    * feed for a view.
    *
    * @param View &$view the view to create a feed from
    * @param ContentController the controller that owns this extension and is currently responding to this request
    * @param the SS_HTTPResponse that will be returned with the feed
    * @return RSSFeed the RSS feed for the view
    */
   protected function createFeed(&$view, $controller, $response) {
      $items = $view->Results();
      return new RSSFeed($items, $controller->request->getURL(), _t('Views.' . $view->Name . 'RSSTitle'));
   }


   /**
    * This is registered as an allowed_action on controllers that have the
    * extension. SS ContentController will call this directly to allow
    * processing of the request.
    *
    * The URL pattern that will invoke us it: $URLSegment/$Action/$ID/$OtherID
    *
    * - URLSegment is the URLSegment of the page that is hosting the feed
    * - Action is "rss"
    * - ID is the view name that we will use to look up the view on the page
    * - OtherID is "feed" from our "feed.xml" extension in our links
    */
   public function rss() {
      $controller = $this->owner;
      $page = Director::get_current_page();
      $response = $controller->getResponse() ?: new SS_HTTPResponse();

      $params = $controller->request->allParams();
      $viewName = isset($params['ID']) ? $params['ID']: null;
      $view = $page && $viewName && $page->hasExtension('ViewHost') ?
         $page->GetView($viewName) :
         false;

      if ($view) {
         $view->setTransientPaginationConfig($view->RSSItems, 'startItem');
         $rss = $this->createFeed($view, $controller, $response);
         $body = $rss->outputToBrowser();
         $response->setBody($body);
         $response->addHeader('Content-Type', 'application/rss+xml');
         $response->setStatusCode(200);
      } else {
         $response->setStatusCode(404);
      }

      return $response;
   }

}
