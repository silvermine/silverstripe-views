<?php

/**
 * This class is a {@link GridField} component that adds an action for adding either
 * an up or down arrow to the "Actions" column to change the sort order if you, for
 * instance, have a SortOrder column in your many_many_extraFields for some relationship.
 *
 * Use the {@link $directionIsUp} property set in the constructor for up or down selection.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2013 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class GridFieldUpDownSortAction implements GridField_ColumnProvider, GridField_ActionProvider {

   /**
    * If this is set to true, this {@link GridField_ActionProvider} will
    * move the item up, otherwise down.
    *
    * @var boolean
    */
   private $directionIsUp = true;

   /**
    * The name of the column in the extraFields for this many-many that handles
    * sort order.
    *
    * @var string
    */
   private $sortColumn = null;

   /**
    *
    * @param boolean $directionIsUp - true if moving up, otherwise down
    */
   public function __construct($sortColumn, $directionIsUp = true) {
      $this->sortColumn = $sortColumn;
      $this->directionIsUp = $directionIsUp;
   }

   /**
    * Add a column 'Actions' if nothing else has
    *
    * @param type $gridField
    * @param array $columns
    */
   public function augmentColumns($gridField, &$columns) {
      if(!in_array('Actions', $columns)) {
         $columns[] = 'Actions';
      }
   }

   /**
    * Return any special attributes that will be used for FormField::create_tag()
    *
    * @param GridField $gridField
    * @param DataObject $record
    * @param string $columnName
    * @return array
    */
   public function getColumnAttributes($gridField, $record, $columnName) {
      return array('class' => 'col-buttons');
   }

   /**
    * Add the title
    *
    * @param GridField $gridField
    * @param string $columnName
    * @return array
    */
   public function getColumnMetadata($gridField, $columnName) {
      if($columnName == 'Actions') {
         return array('title' => '');
      }
   }

   /**
    * Which columns are handled by this component
    *
    * @param type $gridField
    * @return type
    */
   public function getColumnsHandled($gridField) {
      return array('Actions');
   }

   /**
    * Which GridField actions are this component handling
    *
    * @param GridField $gridField
    * @return array
    */
   public function getActions($gridField) {
      return array('moveitemup', 'moveitemdown');
   }

   /**
    *
    * @param GridField $gridField
    * @param DataObject $record
    * @param string $columnName
    * @return string - the HTML for the column
    */
   public function getColumnContent($gridField, $record, $columnName) {
      $list  = $gridField->getList()->sort($this->sortColumn . ' ASC');
      $ids   = $list->column('ID');
      $pos   = array_search($record->ID, $ids);

      if($this->directionIsUp) {
         if (!$record->canEdit()) return;

         $field = GridField_FormAction::create(
                     $gridField,
                     'MoveItemUp' . $record->ID,
                     false,
                     'moveitemup',
                     array('RecordID' => $record->ID)
                  )
            ->addExtraClass('gridfield-button-moveitemup')
            ->setAttribute('title', _t('GridAction.MoveItemUp', 'Move Up'))
            ->setAttribute('data-icon', 'arrow-up')
         ;

         if ($pos === false || $pos == 0) {
            $field->setReadonly(true);
         }
      } else {
         if (!$record->canEdit()) return;

         $field = GridField_FormAction::create(
                     $gridField,
                     'MoveItemDown' . $record->ID,
                     false,
                     'moveitemdown',
                     array('RecordID' => $record->ID)
                  )
            ->addExtraClass('gridfield-button-moveitemdown')
            ->setAttribute('title', _t('GridAction.MoveItemDown', 'Move Down'))
            ->setAttribute('data-icon', 'arrow-down')
         ;

         if ($pos === false || $pos == (count($ids) - 1)) {
            $field->setReadonly(true);
         }
      }
      return $field->Field();
   }

   /**
    * Handle the actions and apply any changes to the GridField
    *
    * @param GridField $gridField
    * @param string $actionName
    * @param mixed $arguments
    * @param array $data - form data
    * @return void
    */
   public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
      if($actionName == 'moveitemup' || $actionName == 'moveitemdown') {
         $list = $gridField->getList()->sort($this->sortColumn . ' ASC');
         $item = $list->byID($arguments['RecordID']);

         if(!$item) {
            return;
         }

         if(!$item->canEdit()) {
            throw new ValidationException(_t('Views.MoveItemSort', 'You do not have permission to move this item.'));
         }

         $ids   = $list->column('ID');
         $pos   = array_search($item->ID, $ids);

         if ($pos === false) {
            throw new ValidationException(_t('Views.InvalidID', 'Could not find ID\'s position in the array.'));
         }

         if($actionName == 'moveitemup') {
            if ($pos == 0) {
               return;
            }
            $ids[$pos] = $ids[$pos - 1];
            $ids[$pos - 1] = $item->ID;
         } else {
            if ($pos == (count($ids) - 1)) {
               return;
            }
            $ids[$pos] = $ids[$pos + 1];
            $ids[$pos + 1] = $item->ID;
         }

         $this->updatePositions($list, $ids);
      }
   }

   protected function updatePositions($dataList, $ids) {
      $val = 1;
      foreach ($ids as $id) {
         $data = $dataList->getExtraData($this->sortColumn, $id);
         $current = $data[$this->sortColumn];
         if ($current != $val) {
            $this->updatePersistedSortValue($dataList, $id, $val);
         }
         $val++;
      }
   }


   protected function getSortTable($dataList) {
      if ($dataList instanceof ManyManyList) {
         if (array_key_exists($this->sortColumn, $dataList->getExtraFields())) {
            return $dataList->getJoinTable();
         }
      }

      $dataClasses = ClassInfo::dataClassesFor($dataList->dataClass());
      foreach ($dataClasses as $class) {
         if (singleton($class)->hasOwnTableDatabaseField($this->sortColumn)) {
            return $class;
         }
      }
      throw new Exception(sprintf('Could not find the table for sort field %s.', $this->sortColumn));
   }

   protected function updatePersistedSortValue($dataList, $id, $val) {
      $table = $this->getSortTable($dataList);

      $where = 'ID = ' . Convert::raw2sql($id);
      if ($dataList instanceof ManyManyList) {
         $where = sprintf(
            '"%s"."%s" = %d AND "%s"."%s" = %d',
            $table, $dataList->getForeignKey(), $dataList->getForeignID(),
            $table, $dataList->getLocalKey(), $id
         );
      }

      $sql = sprintf(
         'UPDATE "%s" SET "%s" = %d WHERE %s',
         $table,
         $this->sortColumn,
         Convert::raw2sql($val),
         $where
      );

      DB::query($sql);
   }
}

