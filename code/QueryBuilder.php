<?php

/**
 * Query builder is a class that can be used to create dynamic SQL queries.
 * It provides an API for programmatically building up fragments of a query
 * into a single query.  It automatically handles choosing the correct tables
 * depending on your environment (i.e. draft or live).
 *
 * Its primary purpose here is for the query results retriever, which uses it
 * to allow disjointed pieces of code to add their own criteria to the where
 * clause for a query.
 *
 * Example usage:
 *
 *    $qb = new QueryBuilder();
 *    $mainTable = $qb->selectObjects('SiteTree');
 *
 *    // Or, the above could be this if you only need certain columns:
 *    $qb->selectColumns('SiteTree');
 *    $qb->addColumns(array("{$mainTable}.ID", "{$mainTable}.Title", "{$mainTable}.ClassName"));
 *
 *    // This is an example of how you can join to a many_many relationship and only return
 *    // SiteTree objects that have a relationship with a particular object on the other end
 *    // of the many_many relationship (here if the object has "SomeField = $someUserInput")
 *    // Of course, you join through two tables to get to the object on the other side of the
 *    // many_many relationship - the joining table and then the actual object table.
 *    $stso = $qb->getTableAlias('SiteTree_SomeObjects');
 *    $qb->innerJoin($stso, "{$mainTable}.ID = {$stso}.SiteTreeID");
 *
 *    $so = $qb->getTableAlias('SomeObject');
 *    $qb->innerJoin($so, sprintf("{$stso}.SomeObjectID = {$so}.ID AND {$so}.SomeField = '%s'", Convert::raw2sql($someUserInput)));
 *
 *    // This is the inverse of the previous example: joining through a many_many relationship
 *    // where the SiteTree object does *not* have a relationship with some object on the other
 *    // end of the relationship.
 *    // Of course, you join through two tables to get to the object on the other side of the
 *    // many_many relationship - the joining table and then the actual object table.
 *    $stso = $qb->getTableAlias('SiteTree_SomeObjects');
 *    $qb->leftJoin($stso, "{$mainTable}.ID = {$stso}.SiteTreeID");
 *
 *    $so = $qb->getTableAlias('SomeObject');
 *    $qb->leftJoin($so, sprintf("{$stso}.SomeObjectID = {$so}.ID AND {$so}.SomeField = '%s'", Convert::raw2sql($someUserInput)));
 *    $qb->where("{$so}.ID IS NULL");
 *
 *    // limit it to certain classes of SiteTree objects:
 *    $qb->where("{$mainTable}.ClassName IN ('NewsPage', 'BlogPage')");
 *
 *    // limit to children of the page we are on
 *    $page = Director::get_current_page();
 *    $qb->where(sprintf("{$mainTable}.ParentID = %d", Convert::raw2sql($page->ID)));
 *
 *    // sort appropriately
 *    $qb->orderBy("{$mainTable}.Sort");
 *
 *    // or, sort by some other fields:
 *    $qb->orderBy("{$mainTable}.LastEdited", alse);
 *    $qb->orderBy("{$mainTable}.Title");
 *
 *    $result = $qb->execute();
 *
 * NOTE: We could not use the SS built-in SQLQuery object because it had a bug
 * with using table aliases, which are critical to allowing disjointed objects
 * create pieces of a query (without table name collision).  Additionally I am
 * not very comfortable with the API of SQLQuery and how much it uses string
 * replacement as away of accomplishing things like table name substitution.
 * This API avoids doing string substitution on user input.  For more info on
 * the aforementioned bug see https://github.com/silverstripe/sapphire/pull/213
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class QueryBuilder {

   const ACTION_ADD_COLUMN = 'add-column';
   const ACTION_SELECT_OBJ = 'select-obj';
   const ACTION_SELECT_COLS = 'select-cols';
   const ACTION_ADD_ANYTHING = 'add-any';
   const ACTION_MAKE_SQL = 'make-sql';

   const MODE_SELECT_OBJECTS = 'select-objects';
   const MODE_SELECT_COLUMNS = 'select-columns';

   private $mode = null;
   private $columns = null;
   private $objectName = null;
   private $tableName = null;
   private $tableNameAlias = null;
   private $aliases = array();
   private $joins = array();
   private $sorts = array();
   private $wheres = array();

   private $tableAliasCount = 0;

   /**
    * If $tableName is a class that has the Versioned extension, this will
    * return the appropriate table name for the class based on the current
    * stage (i.e. "Stage" or "Live").  Primarily used within the QB class and
    * not generally needed from outside his clas.
    *
    * @param string $tableName the name of the table (or class name)
    * @return string either the original $tableName or an alternate table for the object (see above)
    */
   public function get_table_name($tableName) {
      // TODO: this function will break if you have abnormal stages
      // this means that it may not work with some plugins if they modify
      // the stages that are normally used by SS.
      // We can't really fix this unless some extra variables are exposed
      // by the Versioned class (defaultStage, liveStage, stages)
      // NOTE: Versioned::get_live_stage is broken in this same scenario - it
      //       hard-codes the "live" stage.  This makes me think that it's
      //       unlikely that this function will break in very many scenarios
      //       (since it seems Versioned will also break in the same scenarios)

      $defaultStage = 'Stage';
      $suffix = '';
      $versioned = ClassInfo::exists($tableName) &&
         ClassInfo::is_subclass_of($tableName, 'DataObject') &&
         singleton($tableName)->hasExtension('Versioned');

      if ($versioned && Versioned::current_stage() && (Versioned::current_stage() == Versioned::get_live_stage())) {
         $suffix = '_' . Versioned::current_stage();
      }
      return $tableName . $suffix;
   }

   /**
    * If using QB to select columns, use this function to add a column to the
    * list of those to be returned in the results.  Columns should always be
    * prefixed with the proper table alias (i.e. '{$alias}.ID').
    *
    * @param string $column the column name - see note above about prefix
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function addColumn($column) {
      $this->verifyConfiguredFor(ACTION_ADD_COLUMN);
      array_push($this->columns, $column);
      return $this;
   }

   /**
    * Just a simple wrapper around addColumn() to add multiple columns in a
    * single function call.  You must pass this function an array.
    *
    * @see addColumn()
    * @param array $columns array of string column names - see addColumn() note
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function addColumns($columns) {
      foreach ($columns as $column) {
         $this->addColumn($column);
      }
      return $this;
   }

   /**
    * Internal implementation function for adding a join of any type to the
    * internal joins data structure.
    *
    * @param string $type the type of join (i.e. 'INNER', 'LEFT OUTER', etc)
    * @param string $tableAlias the alias of the table that is being joined to
    * @param string $joinClause the clause used to join the table to others
    */
   private function addJoin($type, $tableAlias, $joinClause) {
      if (array_key_exists($tableName, $this->joins)) {
         user_error("Join already existed for $tableAlias", E_USER_WARNING);
      }
      $this->joins[$tableAlias] = array(
         'type'   => $type,
         'alias'  => $tableAlias,
         'clause' => $joinClause,
      );
   }

   /**
    * Private implementation function that converts a SS_Query to a
    * DataObjectSet.  Uses the columns in $this->columns to determine what
    * columns to add to each row of results passed to the DOS.
    *
    * @param SS_Query $query the (executed) query to convert
    * @return DataObjectSet the converted results of the query
    */
   private function convertQueryToDataObjectSet(SS_Query $query) {
      $cols = array();
      foreach ($this->columns as $col) {
         array_push($cols, array_pop(explode('.', $col)));
      }

      $rows = array();
      foreach ($query as $result) {
         $row = array();
         foreach ($cols as $col) {
            $row[$col] = $result[$col];
         }
         array_push($rows, $row);
      }
      return new DataObjectSet($rows);
   }

   /**
    * Execute the query that you have built and return the results as a
    * DataObjectSet.  A DOS is returned regardless of whether you are selecting
    * columns or objects.
    *
    * @return DataObjectSet the results of your query
    */
   public function execute() {
      $sql = $this->getSQLParts();
      if ($this->mode == MODE_SELECT_COLUMNS) {
         return $this->convertQueryToDataObjectSet(DB::query($sql['complete']));
      }
      return DataObject::get($this->objectName, $sql['wheres'], $sql['sorts'], $sql['joins']);
   }

   /**
    * Builds the various pieces of a SQL query based on the current QB
    * configuration.  Also builds a complete SQL query - even if objects are
    * being selected (in which case execute() will only use the parts and not
    * the complete query), which can be helpful for debugging.
    *
    * Array fields (all values are strings that can be used in a SQL query)
    *   - 'columns' - the list of columns to be selected
    *   - 'joins' - the list of joins and their criteria
    *   - 'wheres' - the criteria to follow "WHERE"
    *   - 'sorts' - the fields to follow "ORDER BY"
    *   - 'complete' - a complete SQL query
    *
    * @return array the array of SQL parts.  See above.
    */
   public function getSQLParts() {
      $this->verifyConfiguredFor(ACTION_MAKE_SQL);
      $parts = array();

      // Build $parts['columns']
      $parts['columns'] = "";
      $prefix = "";
      $columns = $this->columns;
      if (empty($columns)) {
         $columns = array("{$this->tableNameAlias}.ID");
      }
      foreach ($columns as $column) {
         $parts['columns'] .= "{$prefix}{$column}";
         $prefix = ",\n       ";
      }
      $parts['columns'] .= "\n";

      // Build $parts['joins']
      $parts['joins'] = "";
      foreach ($this->joins as $join) {
         $table = self::get_table_name($this->aliases[$join['alias']]);
         $parts['joins'] .= "{$join['type']} JOIN {$table} {$join['alias']}\n";
         $parts['joins'] .= "    ON {$join['clause']}\n";
      }

      // Build $parts['wheres']
      $parts['wheres'] = "";
      foreach ($this->wheres as $where) {
         $parts['wheres'] .= "{$where}\n";
      }

      // Build $parts['sorts']
      $prefix = "";
      $parts['sorts'] = "";
      foreach ($this->sorts as $sort) {
         $parts['sorts'] .= "{$prefix}{$sort}";
         $prefix = ", ";
      }

      // Build $parts['complete']
      $sql  = "SELECT {$parts['columns']}";
      $sql .= "  FROM {$this->tableName} AS {$this->tableNameAlias}\n";
      $sql .= "{$parts['joins']}";
      if (!empty($this->wheres)) {
         $sql .= " WHERE {$parts['wheres']}";
      }
      if (!empty($this->sorts)) {
         $sql .= " ORDER BY {$parts['sorts']}";
      }
      $parts['complete'] = $sql;

      return $parts;
   }

   /**
    * Creates a unique alias for a table.  This alias can be used as the first
    * parameter in calls to *join (i.e. innerJoin) to join to the table.  It
    * should also be used to qualify all columns in join clause criteria, where
    * clause criteria, and sort field names.
    *
    * @param string $tableName the name of the table that an alias is needed for
    * @return string an alias that is unique among others in this QB
    */
   public function getTableAlias($tableName) {
      $prefix = preg_replace('/[^A-Z]/', '', $tableName);
      $alias = $prefix . ++$this->tableAliasCount;
      $this->aliases[$alias] = $tableName;
      return $alias;
   }

   /**
    * Adds an inner join to another table.  $tableAlias should always be an
    * alias retrieved from getTableAlias().  All columns used in $joinClause
    * should be qualified with valid aliases retrieved from getTableAlias() or
    * the initial call to either selectObjects or selectColumns.  Additionally
    * the $joinClause should not start with the word "ON".  It should just be
    * whatever needs to come after the "ON" keyword.
    *
    * @param string $tableAlias the alias of the table being joined to (see above)
    * @param string $joinClause the "ON" clause to use in the JOIN (without "ON" - see above).
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function innerJoin($tableAlias, $joinClause) {
      $this->verifyConfiguredFor(ACTION_ADD_ANYTHING);
      $this->addJoin(' INNER', $tableAlias, $joinClause);
      return $this;
   }

   /**
    * Adds a left outer join to another table.  $tableAlias should always be an
    * alias retrieved from getTableAlias().  All columns used in $joinClause
    * should be qualified with valid aliases retrieved from getTableAlias() or
    * the initial call to either selectObjects or selectColumns.  Additionally
    * the $joinClause should not start with the word "ON".  It should just be
    * whatever needs to come after the "ON" keyword.
    *
    * @param string $tableAlias the alias of the table being joined to (see above)
    * @param string $joinClause the "ON" clause to use in the JOIN (without "ON" - see above).
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function leftJoin($tableAlias, $joinClause) {
      $this->verifyConfiguredFor(ACTION_ADD_ANYTHING);
      $this->addJoin('  LEFT OUTER', $tableAlias, $joinClause);
      return $this;
   }

   /**
    * Add a sort ("ORDER BY") clause to the query.  The order by clauses are
    * added to the SQL in the order that they are added to QueryBuilder (by
    * subsequent orderBy() calls).
    *
    * @param string $field the name of the field.  Fields should contain alias names (i.e. "{$alias}.Title")
    * @param boolean $ascending (optional - default true) true for ascending, false for descending ordering
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function orderBy($field, $ascending = true) {
      $this->verifyConfiguredFor(ACTION_ADD_ANYTHING);
      array_push($this->sorts, "{$field} " . ($ascending ? 'ASC' : 'DESC'));
      return $this;
   }

   /**
    * Initializes the QueryBuilder to select columns from the table specified
    * in $from.  If $from is an object class name and $resolveTableName is true
    * (which is the default), this function will look up the appropriate table
    * name for the object based on the current stage.
    *
    * NOTE that this function returns a table alias for this primary table.
    * This alias should be used to prefix column names that reference columns
    * on this table in all calls to where(), innerJoin(), orderBy(), etc.
    *
    * @see getTableName()
    * @param string $from the table or object name to select from
    * @param boolean $resolveTableName (optional - default true) - see above
    * @return string the alias that should be used for this primary table in all join, where, and sort column references
    */
   public function selectColumns($from, $resolveTableName = true) {
      $this->verifyConfiguredFor(ACTION_SELECT_COLS);
      $this->mode = MODE_SELECT_COLUMNS;
      $this->tableName = $resolveTableName ? self::get_table_name($from) : $from;
      $this->tableNameAlias = $this->getTableAlias($from);
      $this->columns = array();
      return $this->tableNameAlias;
   }

   /**
    * Initializes the QueryBuilder to select object instances using
    * DataObject::get.  $objectName should be the name of a class that extends
    * from DataObject.  This function will use getTableName() to look up the
    * appropriate table name for this object based on the current reading stage
    * (i.e. "Stage" or "Live").
    *
    * NOTE that this function returns a table alias for this primary table.
    * This alias should be used to prefix column names that reference columns
    * on this table in all calls to where(), innerJoin(), orderBy(), etc.
    *
    * @see getTableName()
    * @param string $objectName the class name of a class that extends from DataObject (or its descendants)
    * @return string the alias that should be used for this primary table in all join, where, and sort column references
    */
   public function selectObjects($objectName) {
      $this->verifyConfiguredFor(ACTION_SELECT_OBJ);
      $this->mode = MODE_SELECT_OBJECTS;
      $this->objectName = $objectName;
      $this->tableNameAlias = self::get_table_name($objectName);
      // tableName isn't *really* used by selectObjects queries, but
      // we still set it here so that $sql['complete'] is correct when
      // returned from getSQLParts();
      $this->tableName = $this->tableNameAlias;
      return $this->tableNameAlias;
   }

   /**
    * Internal helper function to make sure that the QueryBuilder functions
    * have been called in the proper order.
    *
    * @param string $action the action that is about to happen
    */
   private function verifyConfiguredFor($action) {
      switch ($action) {
         case ACTION_SELECT_COLS:
            if ($this->mode == MODE_SELECT_OBJECTS) {
               user_error("You can not call selectColumns after calling selectObjects", E_USER_ERROR);
            } elseif ($this->mode == MODE_SELECT_COLUMNS) {
               user_error("selectColumns can only be called once on each QueryBuilder", E_USER_ERROR);
            }
            break;
         case ACTION_SELECT_OBJ:
            if ($this->mode == MODE_SELECT_OBJECTS) {
               user_error("selectObjects can only be called once on each QueryBuilder", E_USER_ERROR);
            } elseif ($this->mode == MODE_SELECT_COLUMNS) {
               user_error("You can not call selectObjects after calling selectColumns", E_USER_ERROR);
            }
            break;
         case ACTION_ADD_COLUMN:
            if ($this->mode == null) {
               user_error("Can not add a column when you have not called selectColumns first", E_USER_ERROR);
            } elseif ($this->mode == MODE_SELECT_OBJECTS) {
               user_error("Can not add a column when you are selecting objects (must use selectColumns instead of selectObjects)", E_USER_ERROR);
            }
            break;
         case ACTION_ADD_ANYTHING:
            if ($this->mode == null) {
               user_error("You must call either selectObjects or selectColumns before calling any other modifying methods", E_USER_ERROR);
            }
            break;
         case ACTION_MAKE_SQL:
            if ($this->mode == null) {
               user_error("You must call either selectObjects or selectColumns before creating SQL with QueryBuilder", E_USER_ERROR);
            }
            break;
      }
   }

   /**
    * Add a condition that is used in the "WHERE" clause of the query.
    * All column names in the clause should be prefixed with a valid table
    * alias obtained from selectObjects(), selectColumns(), or getTableAlias().
    *
    * Also note that all input used in your where clause should be properly
    * escaped with the SS Convert class before being appended to your where
    * clause string.
    *
    * @param string $clause the condition to add to the where clause
    * @param boolean $conjunctive (optional - default true) true if the clause should have "AND" before it, false for "OR"
    * @return QueryBuilder this instance for chaining function calls together
    */
   public function where($clause, $conjunctive = true) {
      $this->verifyConfiguredFor(ACTION_ADD_ANYTHING);
      $join = $conjunctive ? '   AND ' : '    OR ';
      array_push($this->wheres, (empty($this->wheres) ? '' : $join) . $clause);
      return $this;
   }

}
