<?php

namespace BOI;

use BOI\Core\Globals;
use BOI\Core\Notifications;
use BOI\Core\Preferences;
use BOI\Helpers\Utils;
use BOI\Managers\Cards;
use BOI\Managers\Players;

trait DebugTrait
{
  function test() {}

  public function loadBugReportSQL(int $reportId, array $studioPlayers): void
  {
    $prodPlayers = $this->getObjectListFromDb("SELECT `player_id` FROM `player`", true);
    $prodCount = count($prodPlayers);
    $studioCount = count($studioPlayers);
    if ($prodCount != $studioCount) {
      throw new BgaVisibleSystemException("Incorrect player count (bug report has $prodCount players, studio table has $studioCount players)");
    }

    // SQL specific to your game
    // For example, reset the current state if it's already game over
    $sql = [
      "UPDATE `global` SET `global_value` = 10 WHERE `global_id` = 1 AND `global_value` = 99"
    ];
    foreach ($prodPlayers as $index => $prodId) {
      $studioId = $studioPlayers[$index];
      // SQL common to all games
      $sql[] = "UPDATE `player` SET `player_id` = $studioId WHERE `player_id` = $prodId";
      $sql[] = "UPDATE `global` SET `global_value` = $studioId WHERE `global_value` = $prodId";
      $sql[] = "UPDATE `stats` SET `stats_player_id` = $studioId WHERE `stats_player_id` = $prodId";

      // SQL specific to your game
      // TODO: update the sql below according to changes specific to your game
      $sql[] = "UPDATE cards SET card_location='hand_" . $studioId . "' WHERE card_location='hand_" . $prodId . "'";
      $sql[] = "UPDATE user_preferences SET player_id=$studioId WHERE player_id=$prodId";
    }
    foreach ($sql as $q) {
      $this->DbQuery($q);
    }
    $this->reloadPlayersBasicInfos();
  }
}
