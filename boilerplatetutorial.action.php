<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * boilerplatetutorial implementation : ©  Timothée Pecatte <tim.pecatte@gmail.com>, Vincent Toper <vincent.toper@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * boilerplatetutorial.action.php
 *
 * boilerplatetutorial main action entry point
 *
 */

class action_boilerplatetutorial extends APP_GameAction
{
  /**
   * This is the constructor. Do not try to implement a `__construct` to bypass this method.
   */
  public function __default()
  {
    if ($this->isArg("notifwindow")) {
      $this->view = "common_notifwindow";
      $this->viewArgs["table"] = $this->getArg("table", AT_posint, true);
    } else {
      $this->view = "boilerplatetutorial_boilerplatetutorial";
      $this->trace("Complete re-initialization of board game.");
    }
  }

  public function actSkip()
  {
    $this->setAjaxMode();
    $this->game->actSkip();
    $this->ajaxResponse();
  }
  ///////////////////
  /////  PREFS  /////
  ///////////////////

  public function actChangePref()
  {
    $this->setAjaxMode();
    $pref = $this->getArg('pref', AT_posint, false);
    $value = $this->getArg('value', AT_posint, false);
    $this->game->actChangePreference($pref, $value);
    $this->ajaxResponse();
  }


  //////////////////
  ///// UTILS  /////
  //////////////////
  public function validateJSonAlphaNum($value, $argName = 'unknown')
  {
    if (is_array($value)) {
      foreach ($value as $key => $v) {
        $this->validateJSonAlphaNum($key, $argName);
        $this->validateJSonAlphaNum($v, $argName);
      }
      return true;
    }
    if (is_int($value)) {
      return true;
    }
    $bValid = preg_match('/^[_0-9a-zA-Z- ]*$/', $value) === 1;
    if (!$bValid) {
      throw new feException("Bad value for: $argName", true, true, FEX_bad_input_argument);
    }
    return true;
  }
}
