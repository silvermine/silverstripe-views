<?php

/**
 * There are many times where a string must be stored in a static storage like
 * a database row, but where that string can not perform its function without
 * some value that is supplied at runtime.  For instance, in building queries,
 * where queries are built and stored in the database, there are many times
 * where there is a need to use a dynamic, run-time, per-request variable
 * within a query.  This may, for instance, by a query parameter that needs to
 * be injected into the query.  By storing tokens in the configuration of query
 * that is stored in the database (as part of a QueryResultsRetriever's object
 * graph), you provide placeholders that can be replaced at runtime with
 * dynamic values.  Those dynamic values can come from anywhere - another query
 * or a request paramater (query, form post, cookie, etc).
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage tokenizers
 */
class ViewsStringTokenizers {

   static $tokenizers = false;
   static $token_regex = '/\$\$([A-Za-z]+):{0,1}([A-Za-z0-9:]*)\$\$/';
   static $token_param_separator = ':';

   /**
    * Retrieves a tokenizer by its configured name.
    *
    * @param string $tokenName the name of the token to look up
    * @return ViewsStringTokenizer|false false if none found, otherwise instance of ViewsStringTokenizer
    */
   public static function get_tokenizer($tokenName) {
      self::init();

      return array_key_exists($tokenName, self::$tokenizers) ? self::$tokenizers[$tokenName] : false;
   }

   /**
    * Used internally to get the default tokenizer classes.
    *
    * @return array of strings - all subclasses of ViewsStringTokenizer
    */
   private static function get_tokenizer_classes() {
      $classes = ClassInfo::subclassesFor('ViewsStringTokenizer');
      array_shift($classes); // remove ViewsStringTokenizer itself (first element)
      return $classes;
   }

   /**
    * Initializes self::$tokenizers if it has not been done already.
    */
   private static function init() {
      if (self::$tokenizers === false) {
         self::$tokenizers = array();
         $classes = self::get_tokenizer_classes();
         foreach ($classes as $class) {
            self::register_tokenizer($class, new $class());
         }
      }
   }

   /**
    * Registers a tokenizer to respond to tokens of a given name.  Note that if
    * you call this from your configuration code you will also need to register
    * all of the built-in tokenizers.  By default, if register_tokenizer has
    * not been called, ViewsStringTokenizers will initialize and register a
    * single instance of every subclass of ViewsStringTokenizer that it can
    * find, using the class name as the token name.  Therefore, if you simply
    * create a custom tokenizer in your code and do not need any custom
    * configuration you should simply let it be automatically found and
    * registered.
    *
    * NOTE: token names can only contain letters [A-Za-z], thus most subclasses
    * of ViewsStringTokenizer should be named with only A-Z and a-z.  If you
    * really need something different you will need to override $token_regex.
    * This is not recommended because it more tightly couples your code and
    * makes future maintenance more difficult.
    *
    * @param string $token the name of the token this tokenizer should work for
    * @param ViewsStringTokenizer $instance the instance of the tokenizer to register
    */
   public static function register_tokenizer($token, $instance) {
      if (!($instance instanceof ViewsStringTokenizer)) {
         user_error("\$instance must be an instance of ViewsStringTokenizer", E_USER_ERROR);
         return;
      }

      if (!is_array(self::$tokenizers)) {
         self::$tokenizers = array();
      }

      self::$tokenizers[$token] = $instance;
   }

   /**
    * Tokenizes a string by replacing tokens that it finds with values that it
    * looks up from the registered tokenizers.
    *
    * @param string $string the string to tokenize
    * @param string the modified string
    * @param mixed &$owner the thing that owns the string being tokenized
    */
   public static function tokenize($string, &$owner) {
      return preg_replace_callback(
         self::$token_regex,
         function($matches) use (&$owner) {
            $tokenName = $matches[1];
            $tokenParams = explode(ViewsStringTokenizers::$token_param_separator, $matches[2]);
            $tokenizer = ViewsStringTokenizers::get_tokenizer($tokenName);
            // also allow for more verbose names that end with "Tokenizer" but use the shorter
            // form within the string itself:
            $tokenizer = $tokenizer ? $tokenizer : ViewsStringTokenizers::get_tokenizer($tokenName . 'Tokenizer');

            if (!$tokenizer) {
               user_error("ViewsStringTokenizers found something that appeared to be a token ('{$matches[0]}') but did not have a value tokenizer for the token name ('{$tokenName}')", E_USER_WARNING);
               return $matches[0];
            }

            return $tokenizer->getValueFor($tokenName, $tokenParams, $owner);
         }, $string);
   }
}
