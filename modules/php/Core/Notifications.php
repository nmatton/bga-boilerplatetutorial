<?php

namespace BOI\Core;

use BOI\Managers\Players;
use BOI\Helpers\Utils;
use BOI\Core\Globals;

class Notifications
{
  protected static $listeners = [
    [
      'name' => 'scores',
      'method' => ['BOI\Managers\Cards', 'getAllScores'],
    ],
  ];

  protected static $cachedValues = [];
  public static function resetCache()
  {
    foreach (self::$listeners as $listener) {
      $method = $listener['method'];
      self::$cachedValues[$listener['name']] = call_user_func($method);
    }
  }

  public static function updateIfNeeded()
  {
    foreach (self::$listeners as $listener) {
      $name = $listener['name'];
      $method = $listener['method'];
      $val = call_user_func($method);
      if ($val !== self::$cachedValues[$name]) {
        self::$cachedValues[$name] = $val;
        Game::get()->notifyAllPlayers('updateInformations', '', [
          $name => $val,
        ]);
      }
    }
  }



  /*****************************
   **** GAME SPECIFIC METHODS ****
   ******************************/

  /* Example */
  public static function playerAction($currentPlayer, $task)
  {
    $data = array_merge($task, [
      'player' => $currentPlayer,
      'task' => $task->getId(),
      'task_name' => $task->getTitle(),
    ]);
    $data['i18n'] = ['task_name'];
    $msg = clienttranslate('${player_name} did the task ${task_name}');
    self::notifyAll('playerAction', $msg, $data);
  }

  public static function cardPlaced($player, $card, $side, $silent = false)
  {
    $card_name = $card->getName();
    $data = [
      'i18n' => ['card_name'],
      'player' => $player,
      'card' => $card,
      'card_name' => $card_name,
      'side' => $side
    ];
    $msg = $silent ? '' : clienttranslate('${player_name} placed ${card_name}');
    self::notifyAll('cardPlaced', $msg, $data);
  }


  /*************************
   **** GENERIC METHODS ****
   *************************/
  protected static function notifyAll($name, $msg, $data)
  {
    self::updateArgs($data);
    Game::get()->notifyAllPlayers($name, $msg, $data);
    self::updateIfNeeded();
  }

  protected static function notify($player, $name, $msg, $data)
  {
    $pId = is_int($player) ? $player : $player->getId();
    self::updateArgs($data);
    Game::get()->notifyPlayer($pId, $name, $msg, $data);
  }

  public static function message($txt, $args = [])
  {
    self::notifyAll('mediumMessage', $txt, $args);
  }
  public static function longMessage($txt, $args = [])
  {
    self::notifyAll('longMessage', $txt, $args);
  }

  public static function messageTo($player, $txt, $args = [])
  {
    $pId = is_int($player) ? $player : $player->getId();
    self::notify($pId, 'mediumMessage', $txt, $args);
  }

  public static function newUndoableStep($player, $stepId)
  {
    self::notify($player, 'newUndoableStep', clienttranslate('Undo here'), [
      'stepId' => $stepId,
      'preserve' => ['stepId'],
    ]);
  }

  public static function clearTurn($player, $notifIds)
  {
    self::notifyAll('clearTurn', clienttranslate('${player_name} restarts their turn'), [
      'player' => $player,
      'notifIds' => $notifIds,
    ]);
  }

  // Remove extra information from cards
  protected static function filterCardDatas($card)
  {
    return [
      'id' => $card['id'],
      'location' => $card['location'],
      'pId' => $card['pId'],
    ];
  }
  public static function refreshUI($datas)
  {
    // // Keep only the thing that matters
    $fDatas = [
      'players' => $datas['players'],
      'factions' => $datas['factions'],
      'meeples' => $datas['meeples'],
      'controlledHexes' => $datas['controlledHexes'],
      'controlledResources' => $datas['controlledResources'],
      'scores' => $datas['scores'],
      'combatHex' => $datas['combatHex'],
    ];

    self::notifyAll('refreshUI', '', [
      'datas' => $fDatas,
    ]);
  }

  public static function refreshHand($player, $hand)
  {
    foreach ($hand as &$card) {
      $card = self::filterCardDatas($card);
    }
    self::notify($player, 'refreshHand', '', [
      'player' => $player,
      'hand' => $hand,
    ]);
  }

  ///////////////////////////////////////////////////////////////
  //  _   _           _       _            _
  // | | | |_ __   __| | __ _| |_ ___     / \   _ __ __ _ ___
  // | | | | '_ \ / _` |/ _` | __/ _ \   / _ \ | '__/ _` / __|
  // | |_| | |_) | (_| | (_| | ||  __/  / ___ \| | | (_| \__ \
  //  \___/| .__/ \__,_|\__,_|\__\___| /_/   \_\_|  \__, |___/
  //       |_|                                      |___/
  ///////////////////////////////////////////////////////////////
  /*
   * Automatically adds some standard field about player and/or card to the args
   * (to avoid having to do it manually in each notification)
   * example : 
   * when you want to use the ${player_name} variable in a notification, you can just pass the player object in the args and use it in the message like this :
   * 
   * $data = ['player' => $currentPlayer,...]
   * $msg = clienttranslate('${player_name} do action Y');
   * self::notifyAll('playerAction', $msg, $data);
   * 
   * You can do the same for many other purposes and update the args in this function (examples with card and task are commented)
   * 
   */
  protected static function updateArgs(&$args)
  {
    if (isset($args['player'])) {
      $args['player_name'] = $args['player']->getName();
      $args['player_id'] = $args['player']->getId();
      unset($args['player']);
    }
    // if (isset($args['card'])) {
    //   $c = isset($args['card']) ? $args['card'] : $args['task'];
    //
    //   $args['value'] = $c['value'];
    //   $args['value_symbol'] = $c['value']; // The substitution will be done in JS format_string_recursive function
    //   $args['color'] = $c['color'];
    //   $args['color_symbol'] = $c['color']; // The substitution will be done in JS format_string_recursive function
    // }

    // if (isset($args['task'])) {
    //   $c = $args['task'];
    //   $args['task_desc'] = $c->getText();
    //   $args['i18n'][] = 'task_desc';
    //
    //   if (isset($args['player_id'])) {
    //     $args['task'] = $args['task']->jsonSerialize($args['task']->getPId() == $args['player_id']);
    //   }
    // }

    foreach (['cost', 'resources', 'resources2', 'bonuses', 'bonuses2'] as $key) {
      if (isset($data[$key])) {
        if (empty($data[$key])) {
          $data["{$key}_desc"] = '';
          continue;
        }
        $t = $data[$key];
        if (in_array($key, ['resources', 'resources2'])) {
          $t = Utils::reduceResources($t);
        }
        $resNames = [
          'GOLD' => clienttranslate('gold'),
          'WOOD' => clienttranslate('wood'),
          'STONE' => clienttranslate('stone'),
        ];

        $args = [];
        $i = 0;
        foreach ($t as $type => $n) {
          if ($n == 0 || $type == 'fId') {
            continue;
          }
          $args['i18n'][] = 'res_' . $i;
          $args["res_$i"] = [
            'log' => '${res}${res_icon} ${res_name}',
            'args' => [
              'i18n' => ['res_name'],
              'res_name' => $resNames[$type],
              'res_icon' => '',
              'res' => $n > 0 ? $n : -$n,
            ],
          ];
          $i++;
        }
        if ($i == 0) {
          $data["{$key}_desc"] = '';
        } else {
          $logs = [
            1 => '${res_0}',
            2 => clienttranslate('${res_0} and ${res_1}'),
            3 => clienttranslate('${res_0}, ${res_1} and ${res_2}'),
            4 => clienttranslate('${res_0}, ${res_1}, ${res_2} and ${res_3}'),
          ];
          $data["{$key}_desc"] = [
            'log' => $logs[$i],
            'args' => $args,
          ];
          $data['i18n'][] = "{$key}_desc";
        }
      }
    }
  }
}
