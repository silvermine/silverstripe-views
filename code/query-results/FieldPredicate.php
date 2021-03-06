<?php

/**
 * A predicate that adds a "where" clause criteria based on a field of the
 * SiteTree table or another table related to it.  Uses the field name and a
 * qualifier (i.e. "equals", "like", "in", etc) and one or more values (stored
 * in FieldPredicateValue objects) to build the query clause.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage query-results
 */
class FieldPredicate extends QueryPredicate {

   public static $db = array(
      'FieldName' => 'VARCHAR(128)',
      'Qualifier' => "ENUM('equals,notequal,like,in,notin,gt,gte,lt,lte,is,isnot', 'equals')",
      'IsRawSQL'  => 'BOOLEAN',
   );

   public static $defaults = array(
      'IsRawSQL' => false,
   );

   public static $has_many = array(
      'Values' => 'FieldPredicateValue',
   );

   public static $qualifier_symbols = array(
      'equals'   => '=',
      'notequal' => '<>',
      'like'     => 'LIKE',
      'in'       => 'IN',
      'notin'    => 'NOT IN',
      'gt'       => '>',
      'gte'      => '>=',
      'lt'       => '<',
      'lte'      => '<=',
      'is'       => 'IS',
      'isnot'    => 'IS NOT',
   );


   private function buildWhere($translateSQLValues = true) {
      if (!array_key_exists($this->Qualifier, self::$qualifier_symbols)) {
         $this->Qualifier = 'equals';
         //throw new RuntimeException("FieldPredicate does not have a qualifier symbol for qualifier '{$this->Qualifier}'");
      }

      $values = '';

      switch ($this->Qualifier) {
         case 'gt':
         case 'gte':
         case 'lt':
         case 'lte':
         case 'equals':
         case 'notequal':
         case 'like':
         case 'is':
         case 'isnot':
            $value = $this->Values()->first()->getSQLValue($translateSQLValues);
            $values = $this->IsRawSQL ? $value : sprintf("'%s'", $value);
            break;
         case 'in':
         case 'notin':
            $sqlValues = array();
            foreach ($this->Values() as $value) {
               array_push($sqlValues, $value->getSQLValue($translateSQLValues));
            }
            $delim  = $this->IsRawSQL ? ", " : "', '";
            $values = implode($delim, $sqlValues);
            $values = $this->IsRawSQL ?
               ('(' . $values . ')') :
               ("('" . $values . "')");
            break;
         default:
            throw new RuntimeException("FieldPredicate->buildWhere does not implement a qualifier '{$this->Qualifier}'");
      }

      return sprintf("%s %s %s", $this->FieldName, self::$qualifier_symbols[$this->Qualifier], $values);
   }


   /**
    * @see QueryResultsRetriever#getReadOnlySummary
    */
   public function getReadOnlySummaryImpl($linePrefix = '') {
      return htmlentities($this->buildWhere(false));
   }


   /**
    * Deletes the associated child objects before deleting this object.
    *
    * @see DataObject->onBeforeDelete()
    */
   protected function onBeforeDelete() {
      parent::onBeforeDelete();
      $values = $this->Values();
      foreach ($values as $value) {
         $value->delete();
      }
   }


   public function updateQueryImpl(&$query, $conjunctive) {
      $query->where($this->buildWhere(), $conjunctive);
      return true;
   }
}

