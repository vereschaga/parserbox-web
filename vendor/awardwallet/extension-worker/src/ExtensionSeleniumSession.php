<?php

namespace AwardWallet\ExtensionWorker;

class ExtensionSeleniumSession
{

    public \SeleniumDriver $driver;
    public string $extensionSessionId;

    public function __construct(\SeleniumDriver $driver, string $extensionSessionId)
    {
        $this->driver = $driver;
        $this->extensionSessionId = $extensionSessionId;
    }

    public function stop()
    {
        $this->driver->stop();;
    }

}