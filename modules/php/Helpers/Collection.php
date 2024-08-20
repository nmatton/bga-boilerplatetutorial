<?php

namespace BOI\Helpers;

/**
 * Represents a collection of elements.
 *
 * @package Helpers
 */
class Collection extends \ArrayObject
{
  /**
   * Retrieves the IDs from the collection.
   *
   * @return array The array of IDs.
   */
  public function getIds()
  {
    return array_keys($this->getArrayCopy());
  }

  /**
   * Check if the collection is empty.
   *
   * @return bool True if the collection is empty, false otherwise.
   */
  public function empty()
  {
    return empty($this->getArrayCopy());
  }

  /**
   * Retrieve the first element from the collection, or null if the collection is empty.
   *
   * @return mixed|null The first element of the collection, or null if the collection is empty.
   */
  public function first()
  {
    $arr = $this->toArray();
    return isset($arr[0]) ? $arr[0] : null;
  }

  /**
   * Retrieve the last element from the collection, or null if the collection is empty.
   *
   * @return mixed|null The last element of the collection, or null if the collection is empty.
   */
  public function last()
  {
    $arr = $this->toArray();
    return empty($arr) ? null : $arr[count($arr) - 1];
  }

  /**
   * Check if the collection has a specific key.
   *
   * @param mixed $key The key to check for.
   * @return bool Returns true if the collection has the key, false otherwise.
   */
  public function has($key)
  {
    return array_key_exists($key, $this->getArrayCopy());
  }


  /**
   * Returns a random element from the collection.
   *
   * @return mixed The randomly selected element.
   */
  public function rand()
  {
    $arr = $this->getArrayCopy();
    $key = array_rand($arr, 1);
    return $arr[$key];
  }

  /**
   * Returns the values of the collection as a numerically indexed array.
   *
   * @return array The values of the collection as a numerically indexed array.
   */
  public function toArray()
  {
    return array_values($this->getArrayCopy());
  }

  /**
   * Returns the collection as an associative array.
   *
   * @return array The collection as an associative array.
   */
  public function toAssoc()
  {
    return $this->getArrayCopy();
  }

  /**
   * Applies a callback function to each element of the collection and returns a new collection with the results.
   *
   * @param callable $func The callback function to apply to each element.
   * @return Collection A new collection with the results of the callback function applied to each element.
   */
  public function map($func): Collection
  {
    return new Collection(array_map($func, $this->toAssoc()));
  }

  /**
   * Returns a new collection that is the result of merging the original collection with the given collection.
   *
   * @param Collection $collect The collection to merge with the original collection.
   * @return Collection
   */
  public function merge($collect): Collection
  {
    return new Collection($this->toAssoc() + $collect->toAssoc());
  }

  /**
   * Reduces the collection to a single value using a callback function.
   *
   * @param callable $func The callback function to apply to each element in the collection.
   *                       The function should accept two arguments: the accumulated value and the current element.
   *                       It should return the updated accumulated value.
   * @param mixed $init The initial value for the reduction.
   * @return mixed The reduced value.
   */
  public function reduce($func, $init)
  {
    return array_reduce($this->toArray(), $func, $init);
  }

  /**
   * Filters the collection using the given callback function.
   *
   * @param callable $func The callback function used to filter the collection.
   * @return Collection The filtered collection.
   */
  public function filter($func): Collection
  {
    return new Collection(array_filter($this->toAssoc(), $func));
  }

  /**
   * Limits the collection to a specified number of items.
   *
   * @param int $n The maximum number of items to include in the collection.
   * @return Collection The collection with the limited number of items.
   */
  public function limit($n): Collection
  {
    return new Collection(array_slice($this->toAssoc(), 0, $n, true));
  }

  /**
   * Check if the collection includes a specific value.
   *
   * @param mixed $t The value to check for inclusion.
   * @return bool Returns true if the collection includes the value, false otherwise.
   */
  public function includes($t): bool
  {
    return in_array($t, $this->getArrayCopy());
  }

  /**
   * This method returns a numerically indexed array with the UI data of each element in the collection.
   * Each element in the collection must implement the getUiData method.
   *
   * @return array An array of UI data of each elements in the collection.
   * 
   * see also : uiAssoc() method for an associative array
   */
  public function ui(): array
  {
    return $this->map(function ($elem) {
      return $elem->getUiData();
    })->toArray();
  }

  /**
   * This method returns an associative array with the UI data of each element in the collection.
   * Each element in the collection must implement the getUiData method.
   *
   * @return array An array of UI data of each elements in the collection.
   * 
   * see also : ui() method for a numerically indexed array
   */
  public function uiAssoc(): array
  {
    return $this->map(function ($elem) {
      return $elem->getUiData();
    })->toAssoc();
  }

  /**
   * Orders the collection using the given callback.
   *
   * @param callable $callback The callback function used for ordering the collection.
   * @return Collection The ordered collection.
   */
  public function order($callback): Collection
  {
    $t = $this->getArrayCopy();
    \uasort($t, $callback);
    return new Collection($t);
  }

  /*****
   * Methods for collection of object
   */

  /**
   * Filters the collection based on the specified field and value.
   *
   * This method filters the collection of objects, returning only those where the specified field matches the given value.
   * If the value is an array, it checks if the field's value is in the array. If the value contains a wildcard ('%'),
   * it performs a "like" match. If the value is null, the original collection is returned unfiltered.
   *
   * @param string $field The name of the field to filter by.
   * @param mixed $value The value to compare against the field's value. Can be a scalar, array, or string with wildcards.
   * 
   * @return Collection A filtered collection of objects based on the field and value criteria.
   */
  public function where($field, $value)
  {
    return is_null($value)
      ? $this
      : $this->filter(function ($obj) use ($field, $value) {
        $method = 'get' . ucfirst($field);
        $objValue = $obj->$method();
        return is_array($value)
          ? in_array($objValue, $value)
          : (strpos($value, '%') !== false
            ? like_match($value, $objValue)
            : $objValue == $value);
      });
  }

  /**
   * Filters the collection, returning objects where the specified field is null.
   *
   * This method filters the collection of objects, returning only those where the specified field has a null value.
   *
   * @param string $field The name of the field to check for null values.
   * 
   * @return Collection A filtered collection of objects where the specified field is null.
   */
  public function whereNull($field)
  {
    return $this->filter(function ($obj) use ($field) {
      $method = 'get' . ucfirst($field);
      $objValue = $obj->$method();
      return is_null($objValue);
    });
  }

  /**
   * Sorts the collection by the specified field in ascending or descending order.
   *
   * @param string $field The field to sort the collection by.
   * @param string $asc The sorting order. Defaults to 'ASC'.
   * @return Collection The sorted collection.
   */
  public function orderBy($field, $asc = 'ASC')
  {
    return $this->order(function ($a, $b) use ($field, $asc) {
      $method = 'get' . ucfirst($field);
      return $asc == 'ASC' ? $a->$method() - $b->$method() : $b->$method() - $a->$method();
    });
  }

  /**
   * Updates the specified field of all objects in the collection with the given value.
   *
   * This method iterates over the collection and updates the specified field of each object with the provided value.
   *
   * @param string $field The name of the field to update.
   * @param mixed $value The new value to assign to the field.
   * 
   * @return Collection The collection of objects after the field has been updated.
   */
  public function update($field, $value)
  {
    $method = 'set' . ucfirst($field);
    foreach ($this->getArrayCopy() as $obj) {
      $obj->$method($value);
    }
    return $this;
  }
}

/**
 * Perform a pattern match on a subject string.
 *
 * @param string $pattern The pattern to match.
 * @param string $subject The subject string to match against.
 * @return bool Returns true if the pattern matches the subject, false otherwise.
 */
function like_match($pattern, $subject)
{
  $pattern = str_replace('%', '.*', preg_quote($pattern, '/'));
  return (bool) preg_match("/^{$pattern}$/i", $subject);
}
