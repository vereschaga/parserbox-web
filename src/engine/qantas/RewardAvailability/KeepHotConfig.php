<?php

namespace AwardWallet\Engine\qantas\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    use \SeleniumCheckerHelper;

    private $closeOldSession = false;

    public function isActive(): bool
    {
        return true;
    }

    public function getCountToKeep(): int
    {
        if (time() < strtotime('04:00') || time() > strtotime('20:00')) {
            return 40;
        }

        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 10; // 10 minutes and ask continue
    }

    public function getLimitLifeTime(): ?int
    {
        return null;
    }

    public function getAfterDateTime(): ?int
    {
        // все горячие созданные до указанного времени будут закрыты
        return null;
    }

    public function run(): bool
    {
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), '.qantas.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        $this->http->GetURL("https://www.qantas.com/au/en.html");
        $port = $this->waitForElement(\WebDriverBy::xpath('//div[@data-testid="departure-port"]'), 35);
        $this->saveResponse();

        if (!$port) {
            $this->logger->debug('unexpected check page');

            return false;
        }

        return true;
    }
}
