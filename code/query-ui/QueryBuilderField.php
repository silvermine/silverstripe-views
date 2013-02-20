<?php


/**
 * An advanced form field used for editing and saving queries for
 * the QueryResultsRetiever
 *
 * @author Craig Weber <craig@crgwbr.com>
 * @copyright (c) 2013 Craig Weber <craig@crgwbr.com>
 * @package silverstripe-views
 * @subpackage query-ui
 */
class QueryBuilderField extends FormField {
   
   protected $readonly = false;
   protected $disabled = false;
   private $resultsRetriever = null;
   
   
   /**
    * Convenience function for getting a value from an array where
    * the key might not exist.
    * 
    * @param array $arr
    * @param string $key
    * @param mixed $default Optional. Defaults to null
    * @return mixed
    */
   private static function get_value($arr, $key, $default = null) {
      return array_key_exists($key, $arr) ? $arr[$key] : $default;
   }
   
   
   /**
    * Take a JSON Data representation from a form submit and save
    * it to the given results retriever
    * 
    * @param string $json
    * @param object $resultsReriever
    */
public static function save($json, QueryResultsRetiever &$resultsRetriever) {
      // Delete Old Nodes
      $root = $resultsRetriever->RootPredicate();
      if ($root)
         $root->delete();
      
      $sorts = $resultsRetriever->Sorts();
      foreach ($sorts as $sort)
         $sort->delete();
      
      // Create New Nodes
      $repr = json_decode($json, $assoc = true);
      $root = self::save_predicate(self::get_value($repr, 'RootPredicate'));
      $resultsRetriever->RootPredicateID = $root->ID;
      
      foreach (self::get_value($repr, 'Sorts', array()) as $sortRepr) {
         $sort = self::save_sort($sortRepr);
         $sort->ResultsRetrieverID = $resultsRetriever->ID;
         $sort->write();
      }
      
      $resultsRetriever->write();
   }
   
   
   /**
    * Create a PredicateCondition structure from it's serializable representation
    * 
    * @param array $repr
    * @return PredicateCondition
    */
   private static function save_condition($repr) {
      $type = self::get_value($repr, 'Type');
      if (!in_array($type, array('PredicateCondition', 'CompoundPredicateCondition', 'QueryParamPredicateCondition')))
         return;
      
      $instance = new $type();
      $instance->write();
      
      if ($instance instanceof CompoundPredicateCondition) {
         $instance->IsConjunctive = !!self::get_value($repr, 'IsConjunctive');
         
         foreach (self::get_value($repr, 'Conditions', array()) as $conditionRepr) {
            $condition = self::save_condition($conditionRepr);
            $condition->CompoundParentID = $instance->ID;
            $condition->write();
         }
      }
      
      if ($instance instanceof QueryParamPredicateCondition) {
         $instance->QueryParamName = self::get_value($repr, 'QueryParamName');
         $instance->PresenceRequired = !!self::get_value($repr, 'PresenceRequired');
      }
      
      $instance->write();
      return $instance;
   }
   
   
   /**
    * Create a QueryPredicate structure from it's serializable representation
    * 
    * @param array $repr
    * @return QueryPredicate
    */
   private static function save_predicate($repr) {
      $type = self::get_value($repr, 'Type');
      if (!in_array($type, array('QueryPredicate', 'CompoundPredicate', 'TaxonomyTermPredicate', 'FieldPredicate')))
         return;
      
      $instance = new $type();
      $instance->write();
      
      foreach (self::get_value($repr, 'PredicateConditions', array()) as $conditionRepr) {
         $condition = self::save_condition($conditionRepr);
         $condition->QueryPredicateID = $instance->ID;
         $condition->write();
      }
      
      if ($instance instanceof CompoundPredicate) {
         $instance->IsConjunctive = !!self::get_value($repr, 'IsConjunctive');
         
         foreach (self::get_value($repr, 'Predicates', array()) as $predicateRepr) {
            $predicate = self::save_predicate($predicateRepr);
            $predicate->CompoundParentID = $instance->ID;
            $predicate->write();
         }
      }
      
      if ($instance instanceof FieldPredicate) {
         $instance->FieldName = self::get_value($repr, 'FieldName');
         $instance->Qualifier = self::get_value($repr, 'Qualifier');
         $instance->IsRawSQL = self::get_value($repr, 'IsRawSQL');
         
         foreach (self::get_value($repr, 'Values', array()) as $valueRepr) {
            $value = self::save_value($valueRepr);
            $value->PredicateID = $instance->ID;
            $value->write();
         }
      }
      
      if ($instance instanceof TaxonomyTermPredicate) {
         $instance->Inclusive = !!self::get_value($repr, 'Inclusive');
         $instance->TermID = 0;
         
         $term = explode('.', $repr);
         if (count($term) < 2)
            break;
         
         $term = VocabularyTerm::find_by_machine_names($term[0], $term[1]);
         if (!$term)
            break;
         
         $instance->TermID = $term->ID;
      }
      
      $instance->write();
      return $instance;
   }
   
   
   /**
    * Create a QuerySort structure from it's serializable representation
    * 
    * @param array $repr
    * @return QuerySort
    */
   private static function save_sort($repr) {
      $instance = new QuerySort();
      
      $instance->FieldName = self::get_value($repr, 'FieldName');
      $instance->IsAscending = self::get_value($repr, 'IsAscending');
      
      $instance->write();
      return $instance;
   }
   
   
   /**
    * Create a FieldPredicateValue structure from it's serializable representation
    * 
    * @param array $repr
    * @return FieldPredicateValue
    */
   private static function save_value($repr) {
      $instance = new FieldPredicateValue();
      $instance->Value = self::get_value($repr, 'Value');
      return $instance;
   }
   
   
   /**
    * Object Consturctor. Create a new form field.
    * 
    * @param string $name
    * @param string $title
    * @param QueryResultsRetriever $resultsRetriever
    * @param object $form Optional
    */
   public function __construct($name, $title, QueryResultsRetiever $resultsRetriever, $form = null) {
      $this->resultsRetriever = $resultsRetriever;
      $structure = $this->buildQueryStructure($resultsRetriever);
      
      parent::__construct($name, $title, json_encode($structure), $form);
   }
   
   
   /**
    * Build a JSON serializable representation of a PredicateCondtion object
    * 
    * @param PredicateCondition $condition
    * @return array
    */
   private function buildConditionStructure(PredicateCondition $condition) {
      $type = get_class($condition);
      $structure = array('Type' => $type);
      
      if ($condition instanceof CompoundPredicateCondition) {
         $structure['IsConjunctive'] = !!$condition->IsConjunctive;
         
         $structure['Conditions'] = array();
         foreach ($condition->Conditions() as $childConditions) {
            $structure['Conditions'][] = $this->buildConditionStructure($childPredicate);
         }
      }
      
      if ($condition instanceof QueryParamPredicateCondition) {
         $structure['QueryParamName'] = $condition->QueryParamName;
         $structure['PresenceRequired'] = !!$condition->PresenceRequired;
      }
      
      return $structure;
   }
   
   
   /**
    * Build a JSON serializable representation of a QueryPredicate object
    * 
    * @param QueryPredicate $condition
    * @return array
    */
   private function buildPredicateStructure(QueryPredicate $predicate) {
      $type = get_class($predicate);
      $structure = array('Type' => $type);
      
      $structure['PredicateConditions'] = array();
      foreach ($predicate->PredicateConditions() as $condition) {
         $structure['PredicateConditions'][] = $this->buildConditionStructure($condition);
      }
      
      if ($predicate instanceof CompoundPredicate) {
         $structure['IsConjunctive'] = !!$predicate->IsConjunctive;
         
         $structure['Predicates'] = array();
         foreach ($predicate->Predicates() as $childPredicate) {
            $structure['Predicates'][] = $this->buildPredicateStructure($childPredicate);
         }
      }
      
      if ($predicate instanceof FieldPredicate) {
         $structure['FieldName'] = $predicate->FieldName;
         $structure['Qualifier'] = $predicate->Qualifier;
         $structure['IsRawSQL'] = !!$predicate->IsRawSQL;
         
         $structure['Values'] = array();
         foreach ($predicate->Values() as $childPredicate) {
            $structure['Values'][] = $this->buildValueStructure($childPredicate);
         }
      }
      
      if ($predicate instanceof TaxonomyTermPredicate) {
         $structure['Inclusive'] = !!$predicate->Inclusive;
         
         $term = $predicate->Term();
         $structure['VocabTerm'] = $term ? "{$term->Vocabulary()->MachineName}.{$term->MachineName}" : null;
      }
      
      return $structure;
   }
   
   
   /**
    * Build a JSON serializable representation of a QueryResultsRetriever object
    * 
    * @param QueryResultsRetriever $condition
    * @return array
    */
   private function buildQueryStructure(QueryResultsRetriever $query) {
      $structure = array(
         'RootPredicate' => $this->buildPredicateStructure($query->RootPredicate()),
         'Sorts' => array());
      
      foreach($query->Sorts() as $sort) {
         $structure['Sorts'][] = array(
            'Type' => get_class($sort),
            'FieldName' => $sort->FieldName,
            'IsAscending' => !!$sort->IsAscending);
      }
      
      return $structure;
   }
   
   
   /**
    * Build a JSON serializable representation of a FieldPredicateValue object
    * 
    * @param FieldPredicateValue $condition
    * @return array
    */
   private function buildValueStructure(FieldPredicateValue $value) {
      $type = get_class($value);
      $structure = array(
         'Type' => $type,
         'Value' => $value->Value);
      
      return $structure;
   }
   
   
   /**
    * Build a new <input /> tag
    * 
    * @return string
    */
   private function getInputTag() {
      $hiddenAttributes = array(
         'type' => 'hidden',
         'class' => 'viewsQueryBuilderRepr',
         'name' => $this->name,
         'value' => $this->value,
         'tabindex' => $this->getTabIndex()
      );
      
      return $this->createTag('input', $hiddenAttributes);
   }
   
   
   /**
    * Get a Read Only summary of the Query
    * 
    * @return string
    */
   private function getReadOnlySummary() {
      $value = $this->resultsRetriever->getReadOnlySummary();
      
      $attributes = array(
         'id' => $this->id(),
         'class' => 'readonly' . ($this->extraClass() ? $this->extraClass() : '')
      );
      
      $containerSpan = $this->createTag('span', $attributes, $value);
      $hiddenInput = $this->getInputTag();
      return $containerSpan . "\n" . $hiddenInput;
   }
   
   
   /**
    * {@link FormField::performReadonlyTransformation()}
    */
   public function performReadonlyTransformation() {
      $read = clone $this;
      $read->setReadonly(true);
      return $read;
   }
   
   
   /**
    * {@link FormField::Field()}
    */
   public function Field() {
      if ($this->readonly) {
         return $this->getReadOnlySummary();
      }
      
      Requirements::javascript('views/code/query-ui/QueryBuilderField.js');
      Requirements::css('views/code/css/views.css');
      
      $html = "<div class='viewsQueryBuilder'></div>\n" . $this->getInputTag();
      return $html;
   }
}
