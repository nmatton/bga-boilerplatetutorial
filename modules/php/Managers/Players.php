<?php

namespace BOI\Managers;

use BOI\Core\Game;
use BOI\Core\Globals;
use BOI\Core\Preferences;
use BOI\Helpers\Collection;

/*
 * Players manager : allows to easily access players ...
 *  a player is an instance of Player class
 */

class Players extends \BOI\Helpers\CachedDB_Manager
{
  protected static $table = 'player';
  protected static $primary = 'player_id';
  protected static $datas = null;
  protected static function cast($row)
  {
    return new \BOI\Models\Player($row);
  }

  /**
   * Call this method at game setup from boilerplatetutorial.game.php
   *
   * @param array $players An array of players participating in the game.
   * @param array $options An array of options for the game.
   * @return void
   */
  public static function setupNewGame($players, $options)
  {
    // Create players
    $gameInfos = Game::get()->getGameinfos();
    $colors = $gameInfos['player_colors'];
    $query = self::DB()->multipleInsert([
      'player_id',
      'player_color',
      'player_canal',
      'player_name',
      'player_avatar',
      'player_score',
    ]);

    $values = [];
    foreach ($players as $pId => $player) {
      $color = array_shift($colors);
      $values[] = [$pId, $color, $player['player_canal'], $player['player_name'], $player['player_avatar'], 1];
    }
    $query->values($values);

    Game::get()->reattributeColorsBasedOnPreferences($players, $gameInfos['player_colors']);
    Game::get()->reloadPlayersBasicInfos();
    self::invalidate();
  }

  /**
   * Retrieves the active ID of the player.
   *
   * @return int The active ID of the player.
   */
  public static function getActiveId()
  {
    return (int) Game::get()->getActivePlayerId();
  }

  /**
   * Retrieves the **current** ID of the player.
   * Remind to not use this method in multiactive state.
   *
   * @return int The current ID of the player.
   */
  public static function getCurrentId()
  {
    return Game::get()->getCurrentPId();
  }


  /**
   * Returns the Player object for the given player ID
   *
   * @param int $id The ID of the player to retrieve.
   * @return \BOI\Models\Player The player object.
   */
  public static function get($id = null)
  {
    return parent::get($id ?? self::getActiveId());
  }

  /**
   * Returns the Player object for the active player
   *
   * @return \BOI\Models\Player The player object.
   */
  public static function getActive()
  {
    return self::get();
  }

  /**
   * Returns the Player object for the current player
   * Remind to not use this method in multiactive state.
   *
   * @return \BOI\Models\Player The player object.
   */
  public static function getCurrent()
  {
    return self::get(self::getCurrentId());
  }

  /**
   * Retrieves the next ID for a player.
   *
   * @param \BOI\Models\Player|int $player The player objecto or ID.
   * @return int The ID for the next player.
   */
  public static function getNextId($player)
  {
    $pId = is_int($player) ? $player : $player->getId();

    $table = Game::get()->getNextPlayerTable();
    return (int) $table[$pId];
  }

  /**
   * Retrieves the previous ID for a player.
   *
   * @param \BOI\Models\Player|int $player The player objecto or ID.
   * @return int The ID for the previous player.
   */
  public static function getPrevId($player)
  {
    $pId = is_int($player) ? $player : $player->getId();

    $table = Game::get()->getPrevPlayerTable();
    $pId = (int) $table[$pId];

    return $pId;
  }

  /**
   * Return the number of players
   * 
   * @return int The number of players
   */
  public static function count()
  {
    return self::getAll()->count();
  }

  /**
   * getUiData : get all ui data of all players
   * 
   * @param int $pId The player ID
   * @return Collection The ui data of all players as a collection
   */
  public static function getUiData($pId)
  {
    return self::getAll()->map(function ($player) use ($pId) {
      return $player->jsonSerialize($pId);
    });
  }

  /**
   * This activate next player
   * 
   * @return int The ID of the next player
   */
  public static function activeNext()
  {
    $pId = self::getActiveId();
    $nextPlayer = self::getNextId((int) $pId);

    Game::get()->gamestate->changeActivePlayer($nextPlayer);
    return $nextPlayer;
  }

  /**
   * This allow to change active player
   * 
   * @param int $pId The ID of the player to activate
   * @return void
   */
  public static function changeActive($pId)
  {
    Game::get()->gamestate->changeActivePlayer($pId);
  }
}
