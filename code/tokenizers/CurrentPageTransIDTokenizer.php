<?php

/**
 * Obtains the current page if it can be found and checks if the page also has
 * a translation in the locale that is passed as a query string parameter.
 * Returns the ID of the current page as a default, or null if the current page
 * can not be determined.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class CurrentPageTransIDTokenizer extends ViewsStringTokenizer {

   /**
    * @see ViewsStringTokenizer::getValueFor($tokenName, $params)
    */
   public function getValueFor($tokenName, $params) {
      $page = Director::get_current_page();

      $locale = '';
      if (count($params) >= 1 && Controller::curr() && Controller::curr()->getRequest()) {
         $locale = Controller::curr()->getRequest()->getVar($params[0]);
      }

      if (!empty($locale) && $page instanceof SiteTree && $page->hasExtension('Translatable')) {
         $translatedPage = $page->getTranslation($locale);
         return ($translatedPage instanceof SiteTree) ? $translatedPage->ID : $page->ID;
      }

      return ($page instanceof SiteTree) ? $page->ID : null;
   }

}
