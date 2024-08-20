<?php

namespace BOI\Helpers;


class CachedDB_Manager extends DB_Manager
{
  protected static $table = null;
  protected static $primary = null;
  protected static $log = null;
  protected static $datas = null;
  protected static function cast($row)
  {
    return $row;
  }

  public static function fetchIfNeeded()
  {
    if (is_null(static::$datas)) {
      static::$datas = static::DB()->get();
    }
  }

  public static function invalidate()
  {
    static::$datas = null;
  }

  public static function getAll()
  {
    self::fetchIfNeeded();
    return static::$datas;
  }

  public static function get($id)
  {
    return self::getAll()
      ->filter(function ($obj) use ($id) {
        return $obj->getId() == $id;
      })
      ->first();
  }

  public static function getMany($ids)
  {
    $tmp = [];
    foreach (self::getAll() as $obj) {
      $id = $obj->getId();
      if (in_array($id, $ids)) {
        $tmp[$id] = $obj;
      }
    }

    // Make sure to preserve ordering
    $result = new Collection([]);
    foreach ($ids as $id) {
      if (isset($tmp[$id])) {
        $result[$id] = $tmp[$id];
      }
    }
    return $result;
  }
}
