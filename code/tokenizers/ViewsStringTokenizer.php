<?php

/**
 * Base class for all string tokenizers.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class ViewsStringTokenizer {

   /**
    * Given a certain token name and set of configuration params, return a
    * value that can be substituted into the string being tokenized.
    *
    * NOTE: values returned by this should be SQL-safe, that is to say that if
    * they are accepting data that might break a SQL string or data that is
    * from a user, Convert::raw2sql should be called on it before returning it.
    *
    * @param string $tokenName the name of the token that caused this tokenizer to get invoked
    * @param string $params the parameters that were configured for this tokenizer when the string was stored
    */
   public function getValueFor($tokenName, $params) {
      throw new Exception(get_class($this) . " must implement the getValueFor(\$tokenName, \$params) function");
   }

}
