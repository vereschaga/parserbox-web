<?php

namespace AwardWallet\Engine\hawaiian\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    use \SeleniumCheckerHelper;

    private const XPATH_LOGOUT = '//span[@class = "nav-account-number"] | //h4[contains(.,"Your Member Benefits")]';

    private $closeOldSession = false;

    public function isActive(): bool
    {
        return true;
    }

    public function getCountToKeep(): int
    {
        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 5; // 10 minutes and ask continue
    }

    public function getLimitLifeTime(): ?int
    {
        // close after N minutes
        return null;
    }

    public function getAfterDateTime(): ?int
    {
        // все горячие созданные до указанного времени будут закрыты
        if ($this->closeOldSession) {
            $closeBefore = date('Y-m-d') . ' 11:45'; // UTC

            return strtotime($closeBefore);
        }

        return null;
    }

    public function run(): bool
    {
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), '.hawaiianairlines.com') === false) {
            $this->logger->error('unexpected page');

            return false;
        }
        $startOver = $this->waitForElement(\WebDriverBy::xpath("//div[contains(text(),'Your session has expired')]/following::button[contains(.,'Start Over')]"), 0, true);

        if ($startOver) {
            $startOver->click();
            $this->waitForElement(\WebDriverBy::xpath("//a[@id='triptype1']"), 10);
        } else {
            try {
                $this->http->GetURL('https://www.hawaiianairlines.com/book/flights');
            } catch (\UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());

                try {
                    $error = $this->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $this->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (\NoAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException -> NoAlertOpenException exception: " . $e->getMessage());
                } finally {
                    $this->logger->debug("UnexpectedAlertOpenException -> finally");
                }
            }
        }

        $logout = $this->waitForElement(\WebDriverBy::xpath(self::XPATH_LOGOUT), 0, true);

        if (!$logout) {
            $this->logger->error('not logged in');

            return false;
        }
        $this->http->GetURL('https://www.hawaiianairlines.com/book/flights');

        if (!$this->waitForElement(\WebDriverBy::xpath("//a[@id=\"triptype1\"]"), 0)) {
            $this->logger->error('not load book page');

            return false;
        }
        $this->saveResponse();

        $this->keepSession(true);

        return true;
    }
}
