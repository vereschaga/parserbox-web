<?php

namespace AwardWallet\Engine\turkish\RewardAvailability;

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
        $this->http->GetURL('https://www.turkishairlines.com/en-int/');

        $res = $this->waitForElement(\WebDriverBy::xpath('
            //h1[contains(text(), "Access Denied")]
            | //button[@id="signin"]
            | //span[contains(text(), "This site can’t be reached")]
            | //button[@id = "signoutBTN"]
            | //div[@data-bind="text: ffpNumber()"]'
        ), 30);

        if (!$res) {
            $this->logger->debug('unexpected check page');

            return false;
        }

        return true;
    }
}
