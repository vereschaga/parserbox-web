<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\Success;

class Timezone extends Success
{
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useChromium();

        if (!empty($this->AccountFields['Login2'])) {
            $this->seleniumOptions->timezone = $this->AccountFields['Login2'];
        }
        $this->UseSelenium();
    }

    public function Parse()
    {
        $this->http->GetURL("https://awardwallet-public.s3.amazonaws.com/healthcheck.html");
        $this->SetBalance($this->driver->executeScript("return new Date().getTimezoneOffset();"));
    }
}
