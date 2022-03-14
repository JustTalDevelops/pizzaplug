<?php

namespace tal\pizzaplug\tasks;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use tal\pizzaplug\User;

class RepeatingTrackingTask extends Task
{

    public bool $ready = true;

    public function __construct(
        public string $player,
        public string $url,
        public User   $user,
    ){}

    public function onRun(): void
    {
        if ($this->ready) {
            $this->ready = false;
            Server::getInstance()->getAsyncPool()->submitTask(new AsyncTrackingTask($this->player, $this->url, function (bool $online): void {
                if (!$online) {
                    $this->getHandler()->cancel();
                    return;
                }
                $this->ready = true;
            }));
        }
    }

}
