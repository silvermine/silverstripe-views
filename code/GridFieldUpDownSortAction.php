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

   const MODE_TOP    = 1;
   const MODE_UP     = 2;
   const MODE_DOWN   = 3;
   const MODE_BOTTOM = 4;

   /**
    * The name of the column in the extraFields for this many-many that handles
    * sort order.
    *
    * @var string
    */
   private $sortColumn = null;

   /**
    * By default this assumes that the SortOrder is ascending. Enable inverted mode
    * to support descending-sorted lists.
    *
    * @var boolean
    */
   private $inverted = false;

   private $mode = self::MODE_TOP;

   /**
    * @param string $sortColumn the name of the column that holds sort integer
    */
   public function __construct($sortColumn, $inverted = false) {
      $this->sortColumn = $sortColumn;
      $this->inverted = $inverted;
   }

   public static function create($sortColumn, $inverted = false) {
      return new self($sortColumn, $inverted);
   }

   public function setMode($mode) {
      $this->mode = $mode;
      return $this;
   }

   public function toTop() {
      return $this->setMode(self::MODE_TOP);
   }

   public function up() {
      return $this->setMode(self::MODE_UP);
   }

   public function down() {
      return $this->setMode(self::MODE_DOWN);
   }

   public function toBottom() {
      return $this->setMode(self::MODE_BOTTOM);
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
      return array('moveitem');
   }


   /**
    *
    * @param GridField $gridField
    * @param DataObject $record
    * @param string $columnName
    * @return string - the HTML for the column
    */
   public function getColumnContent($gridField, $record, $columnName) {
      $dir = $this->inverted ? ' DESC' : ' ASC';
      $list = $gridField->getList()->sort($this->sortColumn . $dir);
      $ids = $list->column('ID');
      $pos = array_search($record->ID, $ids);

      if (!$record->canEdit()) return;

      $field = GridField_FormAction::create(
         $gridField,
         'moveitem' . $record->ID,
         false,
         'moveitem',
         array('RecordID' => $record->ID, 'mode' => $this->mode)
      );

      if ($pos === false) {
         $field->setReadonly(true);
      }

      switch ($this->mode) {
         case self::MODE_TOP:
            $field
               ->addExtraClass('gridfield-button-moveitemtop')
               ->setAttribute('title', _t('GridAction.MoveItemTop', 'Move Top'))
               ->setAttribute('data-icon', 'arrow-up')
            ;
            if ($pos == 0) {
               $field->setReadonly(true);
            }
            break;
         case self::MODE_UP:
            $field
               ->addExtraClass('gridfield-button-moveitemup')
               ->setAttribute('title', _t('GridAction.MoveItemUp', 'Move Up'))
               ->setAttribute('data-icon', 'arrow-up')
            ;
            if ($pos == 0) {
               $field->setReadonly(true);
            }
            break;
         case self::MODE_DOWN:
            $field
               ->addExtraClass('gridfield-button-moveitemdown')
               ->setAttribute('title', _t('GridAction.MoveItemDown', 'Move Down'))
               ->setAttribute('data-icon', 'arrow-down')
            ;
            if ($pos == (count($ids) - 1)) {
               $field->setReadonly(true);
            }
            break;
         case self::MODE_BOTTOM:
            $field
               ->addExtraClass('gridfield-button-moveitembottom')
               ->setAttribute('title', _t('GridAction.MoveItemBottom', 'Move Bottom'))
               ->setAttribute('data-icon', 'arrow-down')
            ;
            if ($pos == (count($ids) - 1)) {
               $field->setReadonly(true);
            }
            break;

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
      if($actionName != 'moveitem') {
         return;
      }
      $list = $gridField->getList()->sort($this->sortColumn . ' ASC');
      $item = $list->byID($arguments['RecordID']);
      $mode = $arguments['mode'];

      // Rewrite mode to account for inverted behavior
      if ($this->inverted) {
         $mode = $this->invertMode($mode);
      }

      if(!$item) {
         return;
      }

      if(!$item->canEdit()) {
         throw new ValidationException(_t('Views.MoveItemSort', 'You do not have permission to move this item.'));
      }

      $ids = $list->column('ID');
      $pos = array_search($item->ID, $ids);

      if ($pos === false) {
         throw new ValidationException(_t('Views.InvalidID', 'Could not find ID\'s position in the array.'));
      }

      $oldIDs = $ids;
      switch ($mode) {
         case self::MODE_TOP:
            if ($pos == 0) {
               return;
            }
            $ids = array($item->ID);
            foreach ($oldIDs as $id) {
               if ($id != $item->ID) {
                  $ids[] = $id;
               }
            }
            break;
         case self::MODE_UP:
            if ($pos == 0) {
               return;
            }
            $ids[$pos] = $ids[$pos - 1];
            $ids[$pos - 1] = $item->ID;
            break;
         case self::MODE_DOWN:
            if ($pos == (count($ids) - 1)) {
               return;
            }
            $ids[$pos] = $ids[$pos + 1];
            $ids[$pos + 1] = $item->ID;
            break;
         case self::MODE_BOTTOM:
            if ($pos == (count($ids) - 1)) {
               return;
            }
            $ids = array();
            foreach ($oldIDs as $id) {
               if ($id != $item->ID) {
                  $ids[] = $id;
               }
            }
            $ids[] = $item->ID;
            break;
      }

      $this->updatePositions($list, $ids);
   }


   protected function invertMode($mode) {
      switch ($mode) {
         case self::MODE_TOP:
            return self::MODE_BOTTOM;
         case self::MODE_UP:
            return self::MODE_DOWN;
         case self::MODE_DOWN:
            return self::MODE_UP;
         case self::MODE_BOTTOM:
            return self::MODE_TOP;
      }
   }


   protected function updatePositions($dataList, $ids) {
      $val = 1;
      foreach ($ids as $id) {
         $current = $this->getCurrentSortOrder($dataList, $id);
         if ($current != $val) {
            $this->updatePersistedSortValue($dataList, $id, $val);
         }
         $val++;
      }
   }


   protected function getCurrentSortOrder($dataList, $id) {
      // Use "extra data" for many-to-many relationships
      if ($dataList instanceof ManyManyList) {
         $data = $dataList->getExtraData($this->sortColumn, $id);
         return $data[$this->sortColumn];
      }
      // Normal DataLists
      $obj = $dataList->byID($id);
      return $obj->getField($this->sortColumn);
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

