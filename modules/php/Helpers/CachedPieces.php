<?php

namespace BOI\Helpers;

/*
 * This is a generic class to manage game pieces.
 *
 * On DB side this is based on a standard table with the following fields:
 * %prefix_%id (string), %prefix_%location (string), %prefix_%state (int)
 *
 *
 * CREATE TABLE IF NOT EXISTS `token` (
 * `token_id` varchar(32) NOT NULL,
 * `token_location` varchar(32) NOT NULL,
 * `token_state` int(10),
 * PRIMARY KEY (`token_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 * CREATE TABLE IF NOT EXISTS `card` (
 * `card_id` int(32) NOT NULL AUTO_INCREMENT,,
 * `card_location` varchar(32) NOT NULL,
 * `card_state` int(10),
 * PRIMARY KEY (`card_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 */

class CachedPieces extends DB_Manager
{
  protected static $table = null;
  protected static $cast = null;

  protected static $prefix = 'piece_';
  protected static $autoIncrement = true;
  protected static $primary;
  protected static $autoremovePrefix = true;
  protected static $autoreshuffle = false; // If true, a new deck is automatically formed with a reshuffled discard as soon at is needed
  protected static $autoreshuffleListener = null; // Callback to a method called when an autoreshuffle occurs
  // autoreshuffleListener = array( 'obj' => object, 'method' => method_name )
  // If defined, tell the name of the deck and what is the corresponding discard (ex : "mydeck" => "mydiscard")
  protected static $autoreshuffleCustom = [];
  protected static $customFields = [];
  protected static $gIndex = [];
  protected static $datas = null;

  public static function DB($table = null)
  {
    static::$primary = static::$prefix . 'id';
    return parent::DB(static::$table);
  }

  /****
   * Return the basic select query fetching basic fields and custom fields
   */
  final static function getSelectQuery()
  {
    $basic = [
      'id' => static::$prefix . 'id',
      'location' => static::$prefix . 'location',
      'state' => static::$prefix . 'state',
    ];
    if (!static::$autoremovePrefix) {
      $basic = array_values($basic);
    }

    $query = self::DB()->select(array_merge($basic, static::$customFields));
    return $query;
  }

  public static function fetchIfNeeded()
  {
    if (is_null(static::$datas)) {
      static::$datas = static::getSelectQuery()->get();
    }
  }

  public static function invalidate()
  {
    static::$datas = null;
  }

  /************************************
   *************************************
   ********* QUERY BUILDER *************
   *************************************
   ************************************/

  public static function where($field, $value)
  {
    return self::getAll()->where($field, $value);
  }

  /****
   * Append the basic select query with a where clause
   */
  public static function getSelectWhere($id = null, $location = null, $state = null)
  {
    return self::where('id', $id)
      ->where('location', $location)
      ->where('state', $state);
  }

  /**
   * Get all the pieces
   */
  public static function getAll()
  {
    static::fetchIfNeeded();
    return static::$datas;
  }

  /************************************
   *************************************
   ********* SANITY CHECKS *************
   *************************************
   ************************************/

  /*
   * Check that the location only contains alphanum and underscore character
   *  -> if the location is an array, implode it using underscores
   */
  final static function checkLocation(&$location, $like = false)
  {
    if (is_null($location)) {
      throw new \BgaVisibleSystemException('Class Pieces: location cannot be null');
    }

    if (is_array($location)) {
      $location = implode('-', $location);
    }

    $extra = $like ? '%' : '';
    if (preg_match("/^[A-Za-z0-9{$extra}-][A-Za-z_0-9{$extra}-]*$/", $location) == 0) {
      throw new \BgaVisibleSystemException("Class Pieces: location must be alphanum and underscore non empty string '$location'");
    }
  }

  /*
   * Check that the id is alphanum and underscore
   */
  final static function checkId(&$id, $like = false)
  {
    if (is_null($id)) {
      throw new \BgaVisibleSystemException('Class Pieces: id cannot be null');
    }

    $extra = $like ? '%' : '';
    if (preg_match("/^[A-Za-z_0-9{$extra}]+$/", $id) == 0) {
      throw new \BgaVisibleSystemException("Class Pieces: id must be alphanum and underscore non empty string '$id'");
    }
  }

  final static function checkIdArray($arr)
  {
    if (is_null($arr)) {
      throw new \BgaVisibleSystemException('Class Pieces: tokens cannot be null');
    }

    if (!is_array($arr)) {
      throw new \BgaVisibleSystemException('Class Pieces: tokens must be an array');
      foreach ($arr as $id) {
        self::checkId($id);
      }
    }
  }

  /*
   * Check that the state is an integer
   */
  final static function checkState($state, $canBeNull = false)
  {
    if (is_null($state) && !$canBeNull) {
      throw new \BgaVisibleSystemException('Class Pieces: state cannot be null');
    }

    if (!is_null($state) && preg_match('/^-*[0-9]+$/', $state) == 0) {
      throw new \BgaVisibleSystemException('Class Pieces: state must be integer number');
    }
  }

  /*
   * Check that a given variable is a positive integer
   */
  final static function checkPosInt($n)
  {
    if ($n && preg_match('/^[0-9]+$/', $n) == 0) {
      throw new \BgaVisibleSystemException('Class Pieces: number of pieces must be integer number');
    }
  }

  /************************************
   *************************************
   ************** GETTERS **************
   *************************************
   ************************************/

  /**
   * Retrieves a piece by its ID.
   *
   * @param int $id The ID of the piece to retrieve.
   * @param bool $raiseExceptionIfNotEnough (optional) Whether to raise an exception if the piece is not found. Default is true.
   * @return mixed|Collection The retrieved piece if only one is found, or a Collection of pieces if multiple are found.
   */
  public static function get($id, $raiseExceptionIfNotEnough = true)
  {
    $result = self::getMany($id, $raiseExceptionIfNotEnough);
    return $result->count() == 1 ? $result->first() : $result;
  }

  /**
   * Retrieves multiple pieces by their IDs.
   *
   * @param array|int $ids The array of piece IDs to retrieve (single int will be converted to array).
   * @param bool $raiseExceptionIfNotEnough (optional) Whether to raise an exception if not enough pieces are found. Default is true.
   * @return Collection The retrieved pieces.
   */
  public static function getMany($ids, $raiseExceptionIfNotEnough = true)
  {
    if (!is_array($ids)) {
      $ids = [$ids];
    }

    self::checkIdArray($ids);
    static::fetchIfNeeded();
    $result = new Collection([]);
    foreach ($ids as $id) {
      if (static::$datas->has($id)) {
        $result[$id] = static::$datas[$id];
      }
    }

    if (count($result) != count($ids) && $raiseExceptionIfNotEnough) {
      // throw new \feException(print_r(\debug_print_backtrace()));
      throw new \feException('Class Pieces: getMany, some pieces have not been found !' . json_encode($ids));
    }

    return $result;
  }

  /**
   * Retrieves a single piece by its ID.
   * If multiple pieces are found with the same ID, it returns null.
   *
   * @param int $id The ID of the piece to retrieve.
   * @param bool $raiseExceptionIfNotEnough (optional) Whether to raise an exception if the piece is not found. Default is true.
   * @return mixed The retrieved piece if found, or null if not found.
   */
  public static function getSingle($id, $raiseExceptionIfNotEnough = true)
  {
    $result = self::getMany([$id], $raiseExceptionIfNotEnough);
    return $result->count() == 1 ? $result->first() : null;
  }

  /**
   * Retrieves the extreme position based on the given parameters.
   *
   * @param bool $getMax Determines whether to retrieve the maximum position (set $getMax = True) or not (i.e., the minimum position: set $getMax to False).
   * @param string $location The location to search for the extreme position.
   * @param int|null $id (optional) The ID to filter the search by.
   * @return mixed The extreme position value.
   */
  public static function getExtremePosition($getMax, $location)
  {
    $states = self::getInLocation($location)
      ->map(function ($obj) {
        return $obj->getState();
      })
      ->toArray();
    return empty($states) ? 0 : ($getMax ? max($states) : min($states));
  }

  /**
   * Retrieves the top Pieces from a specified location.
   *
   * @param string $location The location to retrieve the rows from.
   * @param int $n The number of rows to retrieve. Default is 1.
   * @param bool $returnValueIfOnlyOneRow Determines whether to return the value if only one row is found. Default is true.
   * @return mixed The top rows from the specified location.
   */
  public static function getTopOf($location, $n = 1)
  {
    self::checkLocation($location);
    self::checkPosInt($n);
    return self::getInLocation($location)
      ->orderBy('state', 'DESC')
      ->limit($n);
  }

  /**
   * Return all pieces in specific location
   * note: if "order by" is used, result object is NOT indexed by ids
   * 
   * @param string $location The location to retrieve the rows from.
   * @param int $state (optional) The state to filter the rows by.
   * @return Collection The pieces in the specified location.
   */
  public static function getInLocation($location, $state = null)
  {
    self::checkLocation($location, true);
    self::checkState($state, true);
    return self::getSelectWhere(null, $location, $state);
  }

  /**
   * Retrieves the items in a specific location in an ascending manner.
   *
   * @param string $location The location to retrieve items from.
   * @param mixed $state (optional) The state of the items to retrieve.
   * 
   * @return Collection The items in the specified location.
   */
  public static function getInLocationOrdered($location, $state = null)
  {
    return self::getInLocation($location, $state)->orderBy('state', 'ASC');
  }

  /**
   * Counts the number of items in a specific location.
   *
   * @param string $location The location to count items in.
   * @param mixed $state (optional) The state of the items to count.
   * 
   * @return int The number of items in the specified location.
   */
  public static function countInLocation($location, $state = null)
  {
    self::checkLocation($location, true);
    self::checkState($state, true);
    return self::getInLocation($location, $state)->count();
  }

  /**
   * Retrieves filtered data based on the provided parameters.
   *
   * @param int $pId The ID of the data to retrieve.
   * @param mixed|null $location The location of the data (optional).
   * @param mixed|null $type The type of the data (optional).
   * 
   * @return Collection The filtered data.
   */
  public static function getFiltered($pId, $location = null, $type = null)
  {
    return self::getSelectWhere(null, $location, null)
      ->where('pId', $pId)
      ->where('type', $type);
  }

  /************************************
   *************************************
   ************** SETTERS **************
   *************************************
   ************************************/
  public static function setState($id, $state)
  {
    self::checkState($state);
    self::checkId($id);
    return self::getSingle($id)->setState($state);
  }

  /*
   * Move one (or many) pieces to given location
   */
  public static function move($ids, $location, $state = 0)
  {
    if (!is_array($ids)) {
      $ids = [$ids];
    }
    if (empty($ids)) {
      return [];
    }

    self::checkLocation($location);
    self::checkState($state);
    self::checkIdArray($ids);
    return self::getMany($ids)
      ->update('location', $location)
      ->update('state', $state);
  }

  /*
   *  Move all tokens from a location to another
   *  !!! state is reset to 0 or specified value !!!
   *  if "fromLocation" and "fromState" are null: move ALL cards to specific location
   */
  public static function moveAllInLocation($fromLocation, $toLocation, $fromState = null, $toState = 0)
  {
    if (!is_null($fromLocation)) {
      self::checkLocation($fromLocation);
    }
    self::checkLocation($toLocation);

    return self::getInLocation($fromLocation, $fromState)
      ->update('location', $toLocation)
      ->update('state', $toState);
  }

  /**
   * Move all pieces from a location to another location arg stays with the same value
   */
  public static function moveAllInLocationKeepState($fromLocation, $toLocation)
  {
    self::checkLocation($fromLocation);
    self::checkLocation($toLocation);
    return self::moveAllInLocation($fromLocation, $toLocation, null, null);
  }

  /*
   * Pick the first "$nbr" pieces on top of specified deck and place it in target location
   * Return pieces infos or void array if no card in the specified location
   */
  public static function pickForLocation($nbr, $fromLocation, $toLocation, $state = 0, $deckReform = true)
  {
    self::checkLocation($fromLocation);
    self::checkLocation($toLocation);
    $pieces = self::getTopOf($fromLocation, $nbr);
    $ids = $pieces->getIds();
    $pieces = self::getMany($ids)
      ->update('location', $toLocation)
      ->update('state', $state);

    // No more pieces in deck & reshuffle is active => form another deck
    if (
      array_key_exists($fromLocation, static::$autoreshuffleCustom) &&
      count($pieces) < $nbr &&
      static::$autoreshuffle &&
      $deckReform
    ) {
      $missing = $nbr - count($pieces);
      self::reformDeckFromDiscard($fromLocation);
      $pieces = $pieces->merge(self::pickForLocation($missing, $fromLocation, $toLocation, $state, false)); // Note: block another deck reform
    }

    return $pieces;
  }

  public static function pickOneForLocation($fromLocation, $toLocation, $state = 0, $deckReform = true)
  {
    return self::pickForLocation(1, $fromLocation, $toLocation, $state, $deckReform)->first();
  }

  /*
   * Reform a location from another location when enmpty
   */
  public static function reformDeckFromDiscard($fromLocation)
  {
    self::checkLocation($fromLocation);
    if (!array_key_exists($fromLocation, static::$autoreshuffleCustom)) {
      throw new \BgaVisibleSystemException("Class Pieces:reformDeckFromDiscard: Unknown discard location for $fromLocation !");
    }

    $discard = static::$autoreshuffleCustom[$fromLocation];
    self::checkLocation($discard);
    self::moveAllInLocation($discard, $fromLocation);
    self::shuffle($fromLocation);
    if (static::$autoreshuffleListener) {
      $obj = static::$autoreshuffleListener['obj'];
      $method = static::$autoreshuffleListener['method'];
      $obj->$method($fromLocation);
    }
  }

  /*
   * Shuffle pieces of a specified location, result of the operation will changes state of the piece to be a position after shuffling
   */
  public static function shuffle($location)
  {
    self::checkLocation($location);
    $pieces = self::getInLocation($location)->getIds();
    shuffle($pieces);
    foreach ($pieces as $state => $id) {
      self::getSingle($id)->setState($state);
    }
  }

  public static function insertOnTop($id, $location)
  {
    $pos = self::getExtremePosition(true, $location);
    self::move($id, $location, $pos + 1);
  }

  public static function insertAtBottom($id, $location)
  {
    $pos = self::getExtremePosition(false, $location);
    self::move($id, $location, $pos - 1);
  }

  public static function destroy($ids)
  {
    if (!is_array($ids)) {
      $ids = [$ids];
    }
    if (empty($ids)) {
      return [];
    }

    foreach (self::getMany($ids) as $pId => $piece) {
      unset(static::$datas[$pId]);
    }
    self::DB()->delete()->whereIn(static::$prefix . 'id', $ids)->run();
  }

  /************************************
   ******** CREATE NEW PIECES **********
   ************************************/

  /* This inserts new records in the database.
   * Generically speaking you should only be calling during setup
   *  with some rare exceptions.
   *
   * Pieces is an array with at least the following fields:
   * [
   *   [
   *     "id" => <unique id>    // This unique alphanum and underscore id, use {INDEX} to replace with index if 'nbr' > 1, i..e "meeple_{INDEX}_red"
   *     "nbr" => <nbr>           // Number of tokens with this id, optional default is 1. If nbr >1 and id does not have {INDEX} it will throw an exception
   *     "nbrStart" => <nbr>           // Optional, if the indexing does not start at 0
   *     "location" => <location>       // Optional argument specifies the location, alphanum and underscore
   *     "state" => <state>             // Optional argument specifies integer state, if not specified and $token_state_global is not specified auto-increment is used
   */

  public static function create($pieces, $globalLocation = null, $globalState = null, $globalId = null)
  {
    $pos = is_null($globalLocation) ? 0 : self::getExtremePosition(true, $globalLocation) + 1;

    $values = [];
    $ids = [];
    foreach ($pieces as $info) {
      $n = $info['nbr'] ?? 1;
      $start = $info['nbrStart'] ?? 0;
      $id = $info['id'] ?? $globalId;
      $location = $info['location'] ?? $globalLocation;
      $state = $info['state'] ?? $globalState;
      if (is_null($state)) {
        $state = $location == $globalLocation ? $pos++ : 0;
      }

      // SANITY
      if (is_null($id) && !static::$autoIncrement) {
        throw new \BgaVisibleSystemException('Class Pieces: create: id cannot be null if not autoincrement');
      }

      if (is_null($location)) {
        throw new \BgaVisibleSystemException(
          'Class Pieces : create location cannot be null (set per token location or location_global'
        );
      }
      self::checkLocation($location);

      for ($i = $start; $i < $n + $start; $i++) {
        $data = [];
        if (static::$autoIncrement) {
          $data = [$location, $state];
        } else {
          $nId = preg_replace('/\{INDEX\}/', $id == $globalId ? count($ids) : $i, $id);
          self::checkId($nId);
          $data = [$nId, $location, $state];
          $ids[] = $nId;
        }

        foreach (static::$customFields as $field) {
          $data[] = $info[$field] ?? null;
        }

        $values[] = $data;
      }
    }

    $p = static::$prefix;
    $fields = static::$autoIncrement ? [$p . 'location', $p . 'state'] : [$p . 'id', $p . 'location', $p . 'state'];
    foreach (static::$customFields as $field) {
      $fields[] = $field;
    }

    // With auto increment, we compute the set of all consecutive ids
    self::fetchIfNeeded();
    $ids = self::DB()
      ->multipleInsert($fields)
      ->values($values);

    foreach (
      static::getSelectQuery()
        ->whereIn(static::$prefix . 'id', $ids)
        ->get()
      as $id => $obj
    ) {
      static::$datas[$id] = $obj;
    }

    return self::getMany($ids);
  }

  /*
   * Create a single token
   */
  public static function singleCreate($token)
  {
    return self::create([$token])->first();
  }
}
