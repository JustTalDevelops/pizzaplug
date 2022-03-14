<?php

namespace tal\pizzaplug\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use tal\pizzaplug\Main;
use tal\pizzaplug\User;

class AsyncTrackingStartTask extends AsyncTask
{

    public function __construct(
        public string $player,
        public User $user,
    ){}

    public function onRun(): void
    {
        $result = [];
        while (count($result) == 0) {
            // Use a custom curl here versus the internet class, so we can get away with not sending a User-Agent.
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://tracker.dominos.com/tracker-presentation-service/v2/orders?phonenumber={$this->user->phoneNumber}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    "DPZ-Language: en",
                    "DPZ-Market: UNITED_STATES",
                ],
            ]);

            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);

            sleep(1); // Wait a second between retries.
        }
        $this->setResult($result);
    }

    public function onCompletion(): void
    {
        $player = Server::getInstance()->getPlayerExact($this->player);
        if ($player !== null) {
            $player->sendTip(TextFormat::GREEN . "Loading tracking information...");

            $result = $this->getResult();
            $url = "https://tracker.dominos.com/tracker-presentation-service" . $result[count($result) - 1]["Actions"]["Track"];
            Main::$instance->getScheduler()->scheduleRepeatingTask(new RepeatingTrackingTask($this->player, $url, $this->user), 20);
        }
    }

}