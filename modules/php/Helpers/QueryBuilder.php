<?php

namespace BOI\Helpers;

class QueryBuilder extends \APP_DbObject
{
  private $table,
    $cast,
    $primary,
    $associative,
    $columns,
    $sql,
    $bindValues,
    $where,
    $orWhere,
    $whereCount = 0,
    $isOrWhere = false,
    $limit,
    $orderBy,
    $log,
    $insertPrimaryIndex,
    $operation,
    $operationDatas;

  /**
   * Constructor for the QueryBuilder class.
   *
   * @param string $table The name of the table to query.
   * @param mixed|null $cast The type to cast the query results to. Default is null.
   * @param string|null $primary The name of the primary key column. Default is 'id'.
   * @param bool|null $log Whether to enable logging. Default is false.
   */
  public function __construct($table, $cast = null, $primary = 'id', $log = false)
  {
    $this->table = $table;
    $this->cast = $cast;
    $this->primary = $primary;
    $this->log = $log;
    $this->columns = null;
    $this->sql = null;
    $this->limit = null;
    $this->orderBy = null;
    $this->where = null;
    $this->orWhere = null;
    $this->isOrWhere = false;
  }

  /*************************
   ********* INSERT *********
   *************************/
  /**
   * Single insert, array syntax is [ 'name_of_field' => $value, ... ]
   * 
   * @param array $fields The fields to insert.
   * @param bool $overwriteIfExists Whether to overwrite the row if it already exists. Default is false.
   * @return int The ID of the inserted row.
   */
  public function insert($fields = [], $overwriteIfExists = false)
  {
    $this->multipleInsert(array_keys($fields), $overwriteIfExists)->values([array_values($fields)]);
    return self::DbGetLastId();
  }

  /**
   * Multiple insert, syntax is : ->multipleInsert(['field1', 'field2'])->values([ [1, 'test'], [2, 'tester'], ....])
   *   !!!! each values must have the content in same order as the fields
   * 
   * @param array $fields The fields to insert.
   * @param bool $overwriteIfExists Whether to overwrite the row if it already exists. Default is false.
   * @return QueryBuilder The QueryBuilder object.
   */
  public function multipleInsert($fields = [], $overwriteIfExists = false)
  {
    $keys = implode('`, `', array_values($fields));
    $this->sql = ($overwriteIfExists ? 'REPLACE' : 'INSERT') . " INTO `{$this->table}` (`{$keys}`) VALUES";
    $this->insertPrimaryIndex = array_search($this->primary, $fields);
    return $this;
  }

  /**
   * Set the values for the query.
   *
   * @param array $rows The rows to be inserted or updated.
   * @return array The IDs of the inserted or updated rows.
   */
  public function values($rows = [])
  {
    // Fetch starting index if not provided
    $startingId = null;
    if ($this->insertPrimaryIndex === false) {
      $startingId = (int) self::getUniqueValueFromDB(
        "SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$this->table}';"
      );
    }

    $ids = [];
    $vals = [];
    foreach ($rows as $row) {
      $rowValues = [];
      foreach ($row as $val) {
        $rowValues[] = $val === null ? 'NULL' : "'" . mysql_escape_string($val) . "'";
      }
      $vals[] = '(' . implode(',', $rowValues) . ')';
      $ids[] =
        $row[$this->primary] ?? ($this->insertPrimaryIndex === false ? $startingId++ : $row[$this->insertPrimaryIndex]);
    }

    $this->sql .= implode(',', $vals);
    self::DbQuery($this->sql);
    if ($this->log) {
      Log::addEntry([
        'table' => $this->table,
        'primary' => $this->primary,
        'type' => 'create',
        'affected' => $ids,
      ]);
    }
    return $ids;
  }

  /********************************
   ********* BASIC QUERIES *********
   ********************************/

  // Delete : optional parameter $id which add a where clause on primary key
  /**
   * 
   * Delete a row from the table.
   * @param int|null $id The ID of the row to delete (optional).
   * @return QueryBuilder The QueryBuilder object.
   */
  public function delete($id = null)
  {
    $this->sql = "DELETE FROM `{$this->table}`";
    $this->operation = 'delete';
    return isset($id) ? $this->run($id) : $this;
  }

  /**
   * Update: $fields array structure is the same as the one for insert
   *    optional parameter $id adds a where clause on primary key
   * 
   * @param array $fields The fields to update.
   * @param int|null $id The ID of the row to update (optional).
   * @return QueryBuilder The QueryBuilder object.
   */
  public function update($fields = [], $id = null)
  {
    $values = [];
    foreach ($fields as $column => $field) {
      $values[] = "`$column` = " . (is_null($field) ? 'NULL' : "'$field'");
    }

    $this->operation = 'update';
    $this->operationDatas = array_keys($fields);
    $this->sql = "UPDATE `{$this->table}` SET " . implode(',', $values);
    return isset($id) ? $this->run($id) : $this;
  }

  /**
   * Inc: $fields array structure is the same as the one for insert, but instead of value to be set,
   *    the array contains the offset
   * 
   * @param array $fields The fields to increment.
   * @param int|null $id The ID of the row to increment (optional).
   * @return QueryBuilder The QueryBuilder object.
   */
  public function inc($fields = [], $id = null)
  {
    $values = [];
    foreach ($fields as $column => $field) {
      $values[] = "`$column` = `$column` + $field";
    }

    $this->operation = 'update';
    $this->operationDatas = array_keys($fields);
    $this->sql = "UPDATE `{$this->table}` SET " . implode(',', $values);
    return isset($id) ? $this->run($id) : $this;
  }

  /*
   * Run a query
   */
  /**
   * Run the query.
   *
   * @param int|null $id The ID parameter (optional).
   * @return array The ids affected rows.
   */
  public function run($id = null)
  {
    if (isset($id)) {
      $this->computeWhereClause([[$id]]);
    }

    if ($this->log) {
      // Log module is on
      $tmp = $this->sql;

      if ($this->operation == 'delete') {
        $this->sql = "SELECT * FROM `{$this->table}`";
      } elseif ($this->operation == 'update') {
        if (!\in_array($this->primary, $this->operationDatas)) {
          $this->operationDatas[] = $this->primary;
        }
        $columns = implode(',', $this->operationDatas);
        $this->sql = "SELECT {$columns} FROM `{$this->table}`";
      }

      $this->assembleQueryClauses();
      $objList = self::getObjectListFromDB($this->sql);
      Log::addEntry([
        'table' => $this->table,
        'primary' => $this->primary,
        'type' => $this->operation,
        'affected' => $objList,
      ]);
      $this->sql = $tmp;
    }

    $this->assembleQueryClauses();
    self::DbQuery($this->sql);
    return self::DbAffectedRow();
  }

  /*********************************
   ********* SELECT QUERIES *********
   *********************************/

  /**
   * Select: fetch rows. Structure is columns is either an array with the name of columns you want to fetch,
   *    or an associative array [ 'alias' => 'fieldname'] if you want to use "AS"
   * 
   * @param mixed $columns The columns to fetch.
   * @return QueryBuilder The QueryBuilder object.
   */
  public function select($columns)
  {
    $cols = ["{$this->primary} AS `result_associative_index`"];

    if (!is_array($columns)) {
      $cols = [$columns];
    } else {
      foreach ($columns as $alias => $col) {
        $cols[] = is_numeric($alias) ? "`$col`" : "`$col` AS `$alias`";
      }
    }

    $this->columns = implode(' , ', $cols);
    return $this;
  }

  /**
   * Run a select query and fetch values
   * 
   * @param bool $returnValueIfOnlyOneRow Whether to return the value if only one row is found. Default is false.
   * @param bool $debug Whether to enable debug mode to print SQL queries. Default is false.
   * @return Collection|array The fetched values.
   */
  public function get($returnValueIfOnlyOneRow = false, $debug = false)
  {
    $select = $this->columns ?? "*, {$this->primary} AS `result_associative_index`";
    $this->sql = "SELECT $select FROM `$this->table`";
    $this->assembleQueryClauses();

    if ($debug) {
      throw new \feException($this->sql);
    }
    $res = self::getObjectListFromDB($this->sql);
    $oRes = [];
    foreach ($res as $row) {
      $id = $row['result_associative_index'];
      unset($row['result_associative_index']);

      $val = $row;
      if (is_callable($this->cast)) {
        $val = forward_static_call($this->cast, $row);
      } elseif (is_string($this->cast)) {
        $val = $this->cast == 'object' ? ((object) $row) : new $this->cast($row);
      }

      $oRes[$id] = $val;
    }

    if ($returnValueIfOnlyOneRow && count($oRes) <= 1) {
      return count($oRes) == 1 ? reset($oRes) : null;
    } else {
      return new Collection($oRes);
    }
  }

  /**
   * Retrieves a single record from the database.
   *
   * @return mixed The retrieved record.
   */
  public function getSingle()
  {
    return $this->limit(1)->get(true);
  }

  /**
   * Retrieves a single value from the database (e.g. COUNT, MAX, MIN).
   * 
   * ONLY for unary function : COUNT, MAX, MIN
   * 
   * @param string $func The function to apply.
   * @param string|null $field The field to apply the function to (optional).
   * @return int The result of the function.
   */
  public function func($func, $field = null)
  {
    if (!in_array($func, ['COUNT', 'MAX', 'MIN'])) {
      throw new \BgaVisibleSystemException('QueryBuilder: func is called with unknown function');
    }

    $field = is_null($field) ? '*' : "`$field`";
    $this->sql = "SELECT $func($field) FROM `$this->table`";
    $this->assembleQueryClauses();
    return (int) self::getUniqueValueFromDB($this->sql);
  }

  /**
   * Counts the number of records in the database table.
   *
   * @param string|null $field The field to count. If null, counts all records.
   * @return int The number of records in the database table.
   */
  public function count($field = null)
  {
    return self::func('COUNT', $field);
  }

  /**
   * Calculates the minimum value of a specified field.
   *
   * @param string $field The field to calculate the minimum value for.
   * @return mixed The minimum value of the specified field.
   */
  public function min($field)
  {
    return self::func('MIN', $field);
  }

  /**
   * Get the maximum value of a specific field in the database.
   *
   * @param string $field The field to get the maximum value from.
   * @return mixed The maximum value of the specified field.
   */
  public function max($field)
  {
    return self::func('MAX', $field);
  }

  /****************************
   ********* MODIFIERS *********
   ****************************/
  /**
   * Append all the modifiers to a query in the right order
   * 
   * @return void
   */
  private function assembleQueryClauses()
  {
    $this->sql .= $this->where ?? '';
    $this->sql .= $this->orderBy ?? '';
    $this->sql .= $this->limit ?? '';
  }

  /**
   * Protects the given argument to be used in a query.
   *
   * @param mixed $arg The argument to be protected.
   * @return mixed The protected argument.
   */
  private function protect($arg)
  {
    return is_string($arg) ? "'" . mysql_escape_string($arg) . "'" : $arg;
  }

  /**
   * Computes the WHERE clause for the query.
   *
   * @param mixed $arg The argument used to compute the WHERE clause.
   * @return void
   */
  protected function computeWhereClause($arg)
  {
    $this->where = is_null($this->where) ? ' WHERE ' : $this->where . ($this->isOrWhere ? ' OR ' : ' AND ');

    if (!is_array($arg)) {
      $arg = [$arg];
    }

    $param = array_pop($arg);
    $n = count($param);
    // Only one param => use primary field
    if ($n == 1) {
      $this->where .= " `{$this->primary}` = " . $this->protect($param[0]);
    }
    // Three params : WHERE $1 OP2 $3
    elseif ($n == 3) {
      $this->where .= '`' . trim($param[0]) . '` ' . $param[1] . ' ' . $this->protect($param[2]);
    }
    // Two params : $1 = $2
    elseif ($n == 2) {
      $this->where .= '`' . trim($param[0]) . '` = ' . $this->protect($param[1]);
    }

    if (!empty($arg)) {
      self::computeWhereClause($arg);
    }
  }

  /**
   * Adds a WHERE clause to the query.
   *
   * @return $this
   */
  public function where()
  {
    $this->isOrWhere = false;
    $num_args = func_num_args();
    $args = func_get_args();
    $this->computeWhereClause($num_args == 1 && is_array($args[0]) ? $args[0] : [$args]);
    return $this;
  }

  /**
   * Add a WHERE IN clause to the query.
   *
   * @return $this
   */
  public function whereIn()
  {
    $this->where = is_null($this->where) ? ' WHERE ' : $this->where . ($this->isOrWhere ? ' OR ' : ' AND ');

    $num_args = func_num_args();
    $args = func_get_args();
    $field = $num_args == 1 ? $this->primary : $args[0];
    $values = $num_args == 1 ? $args[0] : $args[1];
    if (is_null($values)) {
      return $this;
    }

    $this->where .= "`$field` IN ('" . implode("','", $values) . "')";
    return $this;
  }

  /**
   * Set the "where not in" clause for the query.
   *
   * @return $this
   */
  public function whereNotIn()
  {
    $this->where = is_null($this->where) ? ' WHERE ' : $this->where . ($this->isOrWhere ? ' OR ' : ' AND ');

    $num_args = func_num_args();
    $args = func_get_args();
    $field = $num_args == 1 ? $this->primary : $args[0];
    $values = $num_args == 1 ? $args[0] : $args[1];
    if (is_null($values)) {
      return $this;
    }

    $this->where .= "`$field` NOT IN ('" . implode("','", $values) . "')";
    return $this;
  }

  /**
   * Set a "where null" clause for the query.
   *
   * @param string $field The field to check for null.
   * @return $this
   */
  public function whereNull($field)
  {
    $this->where = is_null($this->where) ? ' WHERE ' : $this->where . ($this->isOrWhere ? ' OR ' : ' AND ');
    $this->where .= "`$field` IS NULL";
    return $this;
  }

  /**
   * Adds a WHERE condition to the query to filter out rows where the specified field is not null.
   *
   * @param string $field The name of the field to check for null values.
   * @return $this The QueryBuilder instance for method chaining.
   */
  public function whereNotNull($field)
  {
    $this->where = is_null($this->where) ? ' WHERE ' : $this->where . ($this->isOrWhere ? ' OR ' : ' AND ');
    $this->where .= "`$field` IS NOT NULL";
    return $this;
  }

  /**
   * Adds an "OR" condition to the query's WHERE clause.
   *
   * @return $this
   */
  public function orWhere()
  {
    $this->isOrWhere = true;
    $num_args = func_num_args();
    $args = func_get_args();
    $this->computeWhereClause($num_args == 1 ? $args[0] : [$args]);
    return $this;
  }

  /**
   * Set a WHERE condition to filter results by player ID.
   * (Syntaxic sugar)
   *
   * @param int $pId The player ID to filter by.
   * @return $this The QueryBuilder instance.
   */
  public function wherePlayer($pId)
  {
    return $pId == null ? $this : $this->where('player_id', $pId);
  }

  /*
   * Limit
   */
  /**
   * Set the limit and offset for the query.
   *
   * @param int $limit The maximum number of rows to return.
   * @param int|null $offset The number of rows to skip before starting to return rows.
   * @return $this The QueryBuilder instance.
   */
  public function limit($limit, $offset = null)
  {
    $this->limit = " LIMIT {$limit}" . (is_null($offset) ? '' : " OFFSET {$offset}");
    return $this;
  }

  /**
   * Set the order by clause for the query.
   *
   * @return $this
   */
  public function orderBy()
  {
    $num_args = func_num_args();
    $args = func_get_args();

    $field_name = '';
    $order = 'ASC';
    if ($num_args == 1) {
      if (is_array($args[0])) {
        $field_name = trim($args[0][0]);
        $order = trim(strtoupper($args[0][1]));
      } else {
        $field_name = trim($args[0]);
      }
    } else {
      $field_name = trim($args[0]);
      $order = trim(strtoupper($args[1]));
    }

    // validate it's not empty and have a proper valuse
    if ($field_name !== null && ($order == 'ASC' || $order == 'DESC')) {
      if ($this->orderBy == null) {
        $this->orderBy = " ORDER BY $field_name $order";
      } else {
        $this->orderBy .= ", $field_name $order";
      }
    }

    return $this;
  }
}
