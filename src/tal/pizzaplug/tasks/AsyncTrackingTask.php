<?php

namespace tal\pizzaplug\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat as C;

class AsyncTrackingTask extends AsyncTask {

    public function __construct(
        public string $player,
        public string $url,
        public $callback,
    ){}

    public function onRun(): void
    {
        $response = Internet::getURL($this->url, 10, [
            "DPZ-Language: en",
            "DPZ-Market: UNITED_STATES",
        ]);
        $this->setResult($response->getBody());
    }

    public function onCompletion() : void
    {
        $result = json_decode($this->getResult(), true);
        $player = Server::getInstance()->getPlayerExact($this->player);
        if (is_null($player)) {
            ($this->callback)(false);
            return;
        }

        $status = $result["OrderStatus"];
        if ($status === "Complete") {
            $player->sendMessage(C::GREEN . "Thanks for choosing Domino's! Your order has been completed - enjoy!");
            ($this->callback)(false);
            return;
        }
        if ($status === "Void") {
            $player->sendMessage(C::RED . "The store cancelled your order, try again later?");
            ($this->callback)(false);
            return;
        }

        $driver = $result["DriverName"] ?? "Not Assigned";
        $manager = $result["ManagerName"];

        $eta = $result["EstimatedWaitMinutes"] ?? "To Be Decided";
        if ($eta != "To Be Decided") {
            $eta .= " minutes";
        }

        $status = $result["DeliveryStatus"] ?? $result['OrderStatus'];

        $line2 = C::RED . 'Driver: ' . C::AQUA . $driver . C::WHITE . ' | ' . C::RED . 'Manager: ' . C::AQUA . $manager;
        $line1 = C::RED . 'Status: ' . C::AQUA . $status . C::WHITE . ' | ' . C::RED . 'ETA: ' . C::AQUA . $eta;
        $title = C::AQUA . "Domino's Pizza Tracker";
        $player->sendTip($title . C::EOL . $line1 . C::EOL . $line2);

        ($this->callback)(true);
    }

}
