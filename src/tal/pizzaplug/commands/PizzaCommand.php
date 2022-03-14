<?php

namespace tal\pizzaplug\commands;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use tal\pizzaplug\tasks\AsyncNearbyStoresTask;

class PizzaCommand extends Command
{

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "This command can only be ran by players.");
            return;
        }
        $sender->sendForm(new CustomForm("Delivery Info", [
            new Label("description", "Enter your delivery info below so your order can be delivered:"),
            new Input("firstName", "First Name"),
            new Input("lastName", "Last Name"),
            new Input("email", "Email Address"),
            new Input("phone", "Callback Phone #"),
            new Input("address", "Street Address"),
            new Input("region", "City and State or Postal Code"),
        ], function(Player $player, CustomFormResponse $data): void {
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncNearbyStoresTask(
                $player->getName(),
                new User(
                    $data->getString('firstName'),
                    $data->getString('lastName'),
                    $data->getString('email'),
                    $data->getString('phone'),
                    $data->getString('address'),
                    $data->getString('region'),
                ))
            );
        }));
    }

}
