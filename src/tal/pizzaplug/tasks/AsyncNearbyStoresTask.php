<?php

namespace tal\pizzaplug\tasks;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use tal\pizzaplug\User;

class AsyncNearbyStoresTask extends AsyncTask
{

    public function __construct(public string $player, public User $user) {}

    public function onRun(): void
    {
        $result = Internet::getURL("https://order.dominos.com/power/store-locator" . urlencode("?s={$this->user->street}&c={$this->user->region}&type=Delivery"));
        $this->setResult(json_decode($result->getBody(), true));
    }

    public function onCompletion(): void
    {
        $player = Server::getInstance()->getPlayerExact($this->player);
        if ($player !== null && $player->isOnline()) {
            $result = $this->getResult();
            $address = $result["Address"];
            $stores = $result["Stores"];
            if (count($stores) < 1) {
                $player->sendForm(new MenuForm("Nearby Stores", "No nearby stores found. Make sure the address was right.", [new MenuOption("Okay!")], function(): void {}));
                return;
            }

            $this->user->street = $address["Street"];
            $this->user->streetNumber = $address["StreetNumber"];
            $this->user->streetName = $address["StreetName"];
            $this->user->unitType = $address["UnitType"];
            $this->user->unitNumber = $address["UnitNumber"];
            $this->user->city = $address["City"];
            $this->user->region = $address["Region"];
            $this->user->postalCode = $address["PostalCode"];
            $this->user->countyNumber = $address["CountyNumber"];
            $this->user->countyName = $address["CountyName"];

            $options = [];
            foreach ($stores as $store) {
                $street = explode("\n", $store["AddressDescription"])[0];
                $options[] = new MenuOption("{$street}\n{$store["MaxDistance"]} miles away");
            }
            $player->sendForm(new MenuForm("Nearby Stores", "Choose a store from the list below:", $options, function(Player $player, int $selected) use ($result, $stores): void {
                Server::getInstance()->getAsyncPool()->submitTask(new AsyncMenuRequestTask($player->getName(), $stores[$selected]["StoreID"], $this->user));
            }));
        }
    }

}
