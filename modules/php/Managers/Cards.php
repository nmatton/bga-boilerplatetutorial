<?php

namespace BOI\Managers;

use BOI\Core\Globals;
use BOI\Core\Notifications;
use BOI\Managers\Players;
use BOI\Helpers\Utils;

/**
 * Cards: id, value, color
 *  pId is stored as second part of the location, eg : table_2322020
 */
class Cards extends \BOI\Helpers\Pieces
{
  protected static $table = 'cards';
  protected static $prefix = 'card_';
  protected static $customFields = ['value', 'color'];
  protected static $autoreshuffle = false;
  protected static function cast($card)
  {
    $locations = explode('_', $card['location']);
    return [
      'id' => $card['id'],
      'location' => $locations[0],
      'value' => $card['value'],
      'color' => $card['color'],
      'pId' => $locations[1] ?? null,
    ];
  }

  //////////////////////////////////
  //////////////////////////////////
  //////////// GETTERS //////////////
  //////////////////////////////////
  //////////////////////////////////

  /**
   * getOfPlayer: return the cards in the hand of given player
   */
  public static function getOfPlayer($pId)
  {
    return self::getInLocation(['hand', $pId]);
  }

  /**
   * Returns the card with the given ID
   */
  public static function getById($id)
  {
    return  self::getSingle($id);
  }

  //////////////////////////////////
  //////////////////////////////////
  ///////////// SETTERS //////////////
  //////////////////////////////////
  //////////////////////////////////

  /**
   * setupNewGame: create the deck of cards
   */
  public static function setupNewGame($players, $options)
  {
    $colors = [
      CARD_BLUE => 9,
      CARD_GREEN => 9,
      CARD_PINK => 9,
      CARD_YELLOW => 9,
      CARD_SUBMARINE => 4,
    ];

    $cards = [];
    foreach ($colors as $cId => $maxValue) {
      for ($value = 1; $value <= $maxValue; $value++) {
        $cards[] = [
          'value' => $value,
          'color' => $cId,
          //          'player_id' => null,
        ];
      }
    }

    self::create($cards, 'deck');
    self::shuffle('deck');

    // Draw each player 5 cards
    foreach ($players as $pId => $player) {
      self::pickForLocation(4, 'deck', ['hand', $pId]);
    }
  }
}
