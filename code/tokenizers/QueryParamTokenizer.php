<?php

/**
 * Returns the value of a query string parameter so that it can be used inside
 * a string at runtime.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class QueryParamTokenizer extends ViewsStringTokenizer {

   /**
    * @see ViewsStringTokenizer::getValueFor($tokenName, $params)
    */
   public function getValueFor($tokenName, $params) {
      if (count($params) >= 1 && Controller::curr() && Controller::curr()->getRequest()) {
         return Convert::raw2sql(Controller::curr()->getRequest()->getVar($params[0]));
      }

      return null;
   }

}
