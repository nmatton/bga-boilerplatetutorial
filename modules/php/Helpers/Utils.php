<?php

namespace BOI\Helpers;

abstract class Utils extends \APP_DbObject
{

  /**
   * Throws an exception for an invalid user action.
   * Typically used when a user tries to perform an action they are not allowed to.
   *
   * @param string $msg The error message.
   * @return void
   */
  public static function throwInvalidUserAction($msg)
  {
    throw new \BgaVisibleSystemException("$msg. Should not happen");
  }

  /**
   * This method is used to terminate the game execution with optional args to display in the message. (useful for debugging)
   *
   * @param mixed $args Optional. Additional arguments to be displayed in the message.
   * @return void
   */
  public static function die($args = null)
  {
    throw new \BgaVisibleSystemException(json_encode($args));
  }

  /**
   * Filters the given data based on the provided filter.
   *
   * @param mixed $data The data to be filtered (reference).
   * @param mixed $filter The filter to be applied.
   * @return void
   */
  public static function filter(&$data, $filter)
  {
    $data = array_values(array_filter($data, $filter));
  }

  /**
   * Generates a random element(s) from the given array.
   *
   * @param array $array The array to generate random element(s) from.
   * @param int $n The number of random elements to generate. Default is 1.
   * @return mixed|array|null The random element(s) from the array, or null if the array is empty.
   */
  public static function rand($array, $n = 1)
  {
    $keys = array_rand($array, $n);
    if ($n == 1) {
      $keys = [$keys];
    }
    $entries = [];
    foreach ($keys as $key) {
      $entries[] = $array[$key];
    }
    shuffle($entries);
    return $entries;
  }

  /**
   * Searches for a specific value in an array.
   *
   * @param array $array The array to search in.
   * @param mixed $test The value to search for.
   * @return mixed|false The key of the found element, or false if not found.
   */
  function search($array, $test)
  {
    $found = false;
    $iterator = new \ArrayIterator($array);

    while ($found === false && $iterator->valid()) {
      if ($test($iterator->current())) {
        $found = $iterator->key();
      }
      $iterator->next();
    }

    return $found;
  }

  /**
   * Performs a topological sort on a set of nodes using the given edges.
   *
   * @param array $nodeids An array of node IDs.
   * @param array $edges An array of edges connecting the nodes.
   * @return array The sorted array of node IDs.
   */
  public static function topological_sort($nodeids, $edges)
  {
    $L = $S = $nodes = [];
    foreach ($nodeids as $id) {
      $nodes[$id] = ['in' => [], 'out' => []];
      foreach ($edges as $e) {
        if ($id == $e[0]) {
          $nodes[$id]['out'][] = $e[1];
        }
        if ($id == $e[1]) {
          $nodes[$id]['in'][] = $e[0];
        }
      }
    }
    foreach ($nodes as $id => $n) {
      if (empty($n['in'])) {
        $S[] = $id;
      }
    }
    while (!empty($S)) {
      $L[] = $id = array_shift($S);
      foreach ($nodes[$id]['out'] as $m) {
        $nodes[$m]['in'] = array_diff($nodes[$m]['in'], [$id]);
        if (empty($nodes[$m]['in'])) {
          $S[] = $m;
        }
      }
      $nodes[$id]['out'] = [];
    }
    foreach ($nodes as $n) {
      if (!empty($n['in']) or !empty($n['out'])) {
        return null; // not sortable as graph is cyclic
      }
    }
    return $L;
  }

  /**
   * Converts an array of resources to a string representation to be displayed in the UI.
   * 
   * The format of the string is as follows:
   * - If the resource is in the RESOURCES array, the format is <RESOURCE:AMOUNT>.
   * - If the resource is not in the RESOURCES array, the format is AMOUNT<RESOURCE>.
   * 
   * Example usage:
   *  Utils::resourcesToStr(["GOLD" => 1]) // Output: "<GOLD:1>"
   *
   * @param array $resources The array of resources to convert.
   * @param bool $keepZero (Optional) Whether to keep zero values in the resulting string. Default is false.
   * @return string The string representation of the resources.
   */
  public static function resourcesToStr($resources, $keepZero = false)
  {
    $descs = [];
    $keysToIgnore = ['sources', 'sourcesDesc', 'cardId', 'cId', 'pId']; // set here the keys to ignore
    foreach ($resources as $resource => $amount) {
      if (in_array($resource, $keysToIgnore)) {
        continue;
      }

      if ($amount == 0 && !$keepZero) {
        continue;
      }

      if (in_array($resource, RESOURCES)) {
        $descs[] = '<' . strtoupper($resource) . ':' . $amount . '>';
      } else {
        $descs[] = $amount . '<' . strtoupper($resource) . '>';
      }
    }
    return implode(',', $descs);
  }

  public static function reduceResources($meeples)
  {
    $allResources = RESOURCES;
    $t = [];
    foreach ($allResources as $resource) {
      $t[$resource] = 0;
    }

    foreach ($meeples as $meeple) {
      $t[$meeple->getType()]++;
    }

    return $t;
  }

  /**
   * Calculates the difference between the values of an array and the values of another array.
   *
   * @param array $data The array to calculate the difference on. This array will be modified.
   * @param array $arr The array to calculate the difference with.
   * @return void
   */
  public static function diff(&$data, $arr)
  {
    $data = array_values(array_diff($data, $arr));
  }

  /**
   * Shuffles the elements of an associative array.
   *
   * @param array $array The array to be shuffled.
   * @return bool True on success, void on failure.
   */
  public static function shuffle_assoc(&$array)
  {
    $keys = array_keys($array);
    shuffle($keys);

    foreach ($keys as $key) {
      $new[$key] = $array[$key];
    }

    $array = $new;
    return true;
  }
  /**
   * Removes duplicate values from an array using a custom comparator function.
   *
   * @param array $array The array to remove duplicate values from.
   * @param callable $comparator The function used to compare the values in the array.
   * @return array The array with duplicate values removed.
   */
  function array_unique($array, $comparator)
  {
    $unique_array = [];
    do {
      $element = array_shift($array);
      $unique_array[] = $element;

      $array = array_udiff($array, [$element], $comparator);
    } while (count($array) > 0);

    return $unique_array;
  }

  /**
   * Splits an array of values into positive and negative arrays.
   *
   * @param array $inputArray The input array to be split.
   * @return array An array containing two arrays: the positive values and the negative values.
   */
  public static function splitPositiveNegativeValues($inputArray)
  {
    $positiveArray = [];
    $negativeArray = [];

    foreach ($inputArray as $key => $value) {
      if ($value > 0) {
        // If the value is positive, add it to the positiveArray
        $positiveArray[$key] = $value;
      } else {
        // If the value is negative, add it to the negativeArray
        $negativeArray[$key] = $value;
      }
    }
    return [$positiveArray, $negativeArray];
  }
}
