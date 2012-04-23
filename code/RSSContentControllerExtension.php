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

   private static $results_function = '';

   /**
    * The results function is the function that will be called on the View to
    * get results.  Typically this should be either 'Results' or
    * 'TranslatedResults'.  If you always want a single function to be used you
    * can call this function to set this extension to always use that function.
    * Otherwise getResultsFunction() will make a best guess for the particular
    * type of results retriever.
    *
    * @param string the name of the results function to always use for all views
    */
   public static function set_results_function($function) {
      self::$results_function = $function;
   }

   /**
    * Returns the name of the results function that should be used to retrieve
    * results from a view.  See set_results_function() for more details.
    *
    * This is designed to be overridden if you need custom logic.  Of course,
    * if you do this you'll need to add your custom class as an extension of
    * ContentController rather than this class.
    *
    * @param View $view the view to determine the results function for
    * @return string the name of the function to use (typically 'Results' or 'TranslatedResults')
    */
   protected function getResultsFunction($view) {
      if (self::$results_function != '') {
         return self::$results_function;
      }

      $rr = $view->ResultsRetriever();
      if ($rr instanceof HandPickedResultsRetriever) {
         return 'TranslatedResults';
      }

      return 'Results';
   }

   /**
    * Handles a request that is for a view's RSS feed.
    */
   public function interceptRequest($controller, $viewName) {
      $page = Director::get_current_page();
      $view = $page && $page->hasExtension('ViewHost') ?
         $page->GetView($viewName) :
         false;

      if (!$page || !$view || !$view->RSSEnabled) {
         $controller->popCurrent();
         $controller->httpError(404);
      }

      $function = $this->getResultsFunction($view);
      $view->setTransientPaginationConfig($view->RSSItems, 'startItem');
      $items = $view->$function();
      $rss = new RSSFeed($items, $controller->request->getURL(), _t('Views.' . $view->Name . 'RSSTitle'));
      $rss->outputToBrowser();

      // TODO: (review) this is a bit of a hack to get ContentController to stop
      // processing after our onAfterInit method has finished
      $controller->popCurrent();
      throw new SS_HTTPResponse_Exception("", 200);
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
