<?php

namespace tal\pizzaplug;

use pocketmine\plugin\PluginBase;
use tal\pizzaplug\commands\PizzaCommand;

class Main extends PluginBase
{

    public static self $instance;

    protected function onEnable(): void
    {
        self::$instance = $this;
        $this->getServer()->getCommandMap()->register("pizza", new PizzaCommand("pizza", "Buy a pizza from Domino's Pizza!", "/pizza"));
    }

}