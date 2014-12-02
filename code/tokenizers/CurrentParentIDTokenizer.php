<?php

/**
 * Returns the ID of the current page, if it can be determined, or null if not.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class CurrentParentIDTokenizer extends ViewsStringTokenizer {

   /**
    * @see ViewsStringTokenizer::getValueFor($tokenName, $params, $owner)
    */
   public function getValueFor($tokenName, $params, &$owner) {
      $page = Director::get_current_page();
      $page = ($page instanceof DataObject && $page->hasExtension('Hierarchy')) ? $page->Parent() : null;
      return ($page instanceof DataObject) ? $page->ID : null;
   }

}
