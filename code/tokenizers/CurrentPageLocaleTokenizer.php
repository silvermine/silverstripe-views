<?php

/**
 * Returns the locale of the current page, if it can be determined, or null if
 * not.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class CurrentPageLocaleTokenizer extends ViewsStringTokenizer {

   /**
    * @see ViewsStringTokenizer::getValueFor($tokenName, $params)
    */
   public function getValueFor($tokenName, $params) {
      $page = Director::get_current_page();
      return ($page instanceof SiteTree && $page->hasExtension('Translatable')) ? $page->Locale : null;
   }

}
