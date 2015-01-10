<?php

/**
 * Returns the ID of the parent of the current page, if it
 * can be determined, or null if not.
 *
 * @author Craig Weber <craig@crgwbr.com>
 * @copyright (c) 2014 Craig Weber <craig@crgwbr.com>
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
