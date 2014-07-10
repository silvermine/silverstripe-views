<?php

/**
 * Drop down field used when a view is a new, transient instance to allow choosing
 * the type of results retriever to be used for the view.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2013 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewResultsRetrieverTypeDropDownField extends DropDownField {

   /**
    * Returns an input field, class="text" and type="text" with an optional
    * maxlength
    */
   public function __construct() {
      parent::__construct('ResultsRetrieverType', _t('Views.ResultsRetrieverTypeLabel', 'Results Retriever Type'), $this->validTypes());
      $this->setEmptyString(_t('Views.ViewResultsRetrieverTypeChooseOne', 'Choose One'));
   }


   public function validate($validator) {
      $class = $this->dataValue();
      if (!in_array($class, $this->validTypes())) {
         $validator->validationError(
            $this->name,
            _t('Views.InvalidResultsRetrieverClass', 'Invalid results retriever class: {class}', array('class' => (empty($class) ? 'null' : $class))),
            'validation'
         );
         return false;
      }
   }


   public function saveInto(DataObjectInterface $record) {
      $validation = $record->validate();
      if (!$validation->valid()) {
         // can't create a new retriever because of other validation errors
         return;
      }

      $class = $this->dataValue();
      $rr = new $class();
      $rr->write();
      $record->ResultsRetrieverID = $rr->ID;
   }


   public function validTypes() {
      $types = ClassInfo::subclassesFor('ViewResultsRetriever');
      array_shift($types); // remove ViewResultsRetriever (first element)
      return $types;
   }
}

