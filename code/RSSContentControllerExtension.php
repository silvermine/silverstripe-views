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

   /**
    * Create the RSS feed for a given view.  This function is designed to be
    * overridden by any client code that wants to customize the behavior of the
    * feed for a view.
    *
    * @param View &$view the view to create a feed from
    * @param ContentController the controller that owns this extension and is currently responding to this request
    * @return RSSFeed the RSS feed for the view
    */
   protected function createFeed(&$view, $controller) {
      $items = $view->Results();
      return new RSSFeed($items, $controller->request->getURL(), _t('Views.' . $view->Name . 'RSSTitle'));
   }

   /**
    * Handles a request that is for a view's RSS feed.
    */
   public function interceptRequest($controller, $viewName) {
      $page = Director::get_current_page();
      $view = $page && $page->hasExtension('ViewHost') ?
         $page->GetView($viewName) :
         false;

      $response = Controller::curr()->getResponse();
      if (!$response) {
         $response = new SS_HTTPResponse();
      }
      if ($page && $view && $view->RSSEnabled) {
         $view->setTransientPaginationConfig($view->RSSItems, 'startItem');
         $rss = $this->createFeed($view, $controller);
         $body = $rss->outputToBrowser();
         $response->setBody($body);
         $response->setStatusCode(200);
         $code = 200;
      } else {
         $response->setStatusCode(404);
      }

      throw new SS_HTTPResponse_Exception($response);
   }

   /**
    * This method is called by ContentController after it has initialized itself.
    * We use it to intercept request handling if the action for the page is "rss".
    */
   public function onAfterInit() {
      $controller = $this->owner;
      $params = $controller->request->allParams();
      if (array_key_exists('Action', $params) && $params['Action'] == 'rss' && array_key_exists('ID', $params)) {
         $this->interceptRequest($controller, $params['ID']);
      }
   }

}
