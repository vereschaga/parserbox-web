<?php

namespace AwardWallet\ExtensionWorker;

interface WatchdogControlInterface
{

    public function increaseTimeLimit(int $addedSeconds = 60) : void;

}