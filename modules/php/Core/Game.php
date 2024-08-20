<?php
namespace BOI\Core;
use boilerplatetutorial;

/*
 * Game: a wrapper over table object to allow more generic modules
 */
class Game
{
  public static function get()
  {
    return boilerplatetutorial::get();
  }
}
