<?php

namespace tal\pizzaplug\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use stdClass;
use tal\pizzaplug\User;

class AsyncOrderCreationTask extends AsyncTask
{

    public string $items;

    public function __construct(
        public string $player,
        public int    $store,
        public User   $user,
        array  $items,
    ){
        $this->items = serialize($items);
    }

    public function onRun(): void
    {
        $products = [];
        $items = unserialize($this->items);
        foreach ($items as $item) {
            $products[] = [
                'Code' => $item["Code"],
                'Qty' => 1,
                'isNew' => true,
                'Options' => new stdClass(),
            ];
        }

        $order = [
            "Order" => [
                'Address' => [
                    'Street' => $this->user->street,
                    'StreetNumber' => $this->user->streetNumber,
                    'StreetName' => $this->user->streetName,
                    'UnitType' => $this->user->unitType,
                    'UnitNumber' => $this->user->unitNumber,
                    'City' => $this->user->city,
                    'Region' => $this->user->region,
                    'PostalCode' => $this->user->postalCode,
                    'CountyNumber' => $this->user->countyNumber,
                    'CountyName' => $this->user->countyName,
                ],
                'Coupons' => [],
                'Email' => $this->user->email,
                'FirstName' => $this->user->firstName,
                'LastName' => $this->user->lastName,
                'LanguageCode' => 'en',
                'OrderChannel' => 'OLO',
                'OrderMethod' => 'Web',
                'OrderTaker' => null,
                'Payments' => [],
                'Phone' => $this->user->phoneNumber,
                'PhonePrefix' => '',
                'Products' => $products,
                'ServiceMethod' => 'Delivery',
                'SourceOrganizationURI' => 'order.dominos.com',
                'StoreID' => $this->store,
                'Tags' => new stdClass(),
                'Version' => '1.0',
                'NoCombine' => true,
                'Partners' => new stdClass(),
                'OrderInfoCollection' => [],
            ]
        ];

        $result = Internet::postURL("https://order.dominos.com/power/validate-order", json_encode($order));
        $order["Order"]["OrderID"] = json_decode($result->getBody(), true)["Order"]["OrderID"];

        $result = Internet::postURL("https://order.dominos.com/power/price-order", json_encode($order));
        $cost = json_decode($result->getBody(), true)["Order"]["Amounts"]["Customer"];
        $order["Order"]["Payments"][] = [
            'Amount' => $cost,
            'Type' => 'Cash',
        ];

        Internet::postURL("https://order.dominos.com/power/place-order", json_encode($order));
        $this->setResult($cost);
    }

    public function onCompletion(): void
    {
        $player = Server::getInstance()->getPlayerExact($this->player);
        if ($player !== null && $player->isOnline()) {
            $player->sendMessage(TextFormat::GREEN . "Your order was completed! A tracking indicator will be shown on top of your hotbar in a few seconds.");
            $player->sendMessage(TextFormat::GREEN . "The total was \${$this->getResult()}, make sure you have the cash ready before the driver arrives.");
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncTrackingStartTask($player->getName(), $this->user));
        }
    }

}
