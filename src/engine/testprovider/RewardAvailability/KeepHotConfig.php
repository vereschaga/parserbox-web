<?php

namespace AwardWallet\Engine\testprovider\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    //for unit test
    public $success = false;
    public $active = false;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getInterval(): int
    {
        return 2 * 60;
    }

    public function getLimitLifeTime(): ?int
    {
        return null;
    }

    public function run(): bool
    {
        return $this->success;
    }

    public function getCountToKeep(): int
    {
        return 2;
    }

    public function getAfterDateTime(): ?int
    {
        return null;
    }
}
