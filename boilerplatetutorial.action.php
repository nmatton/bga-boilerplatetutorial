<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * boilerplatetutorial implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * boilerplatetutorial.action.php
 *
 * boilerplatetutorial main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "actMyAction" here, then you can call it from your javascript code with:
 * this.bgaPerformAction("actMyAction", ...)
 *
 */
declare(strict_types=1);

/**
 * @property boilerplatetutorial $game
 */
class action_boilerplatetutorial extends APP_GameAction
{
    /**
     * This is the constructor. Do not try to implement a `__construct` to bypass this method.
     */
    public function __default()
    {
        if ($this->isArg("notifwindow"))
        {
            $this->view = "common_notifwindow";
            $this->viewArgs["table"] = $this->getArg("table", AT_posint, true);
        }
        else
        {
            $this->view = "boilerplatetutorial_boilerplatetutorial";
            $this->trace("Complete re-initialization of board game.");
        }
    }

    /**
     * This method is called directly from the router. It asserts HTTP arguments and forwards them to the associated
     * table game method.
     *
     * @throws BgaSystemException
     */
    public function actPlayCard(): void
    {
        $this->setAjaxMode();

        // Retrieve arguments.
        // NOTE: These arguments correspond to what has been sent through the JS `bgaPerformAction` method.
        $card_id = (int) $this->getArg("card_id", AT_posint, true);

        // Then, call the appropriate method in your game logic.
        $this->game->actPlayCard($card_id);

        $this->ajaxResponse();
    }

    public function actPass(): void
    {
        $this->setAjaxMode();
        
        // Call the appropriate method in your game logic.
        $this->game->actPass();

        $this->ajaxResponse();
    }
}


