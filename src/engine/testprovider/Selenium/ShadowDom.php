<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\Success;

class ShadowDom extends Success
{
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromePuppeteer();
    }

    public function Parse()
    {
        $this->http->GetURL("https://awardwallet-public.s3.amazonaws.com/shadow-dom-test.html");
        $result = $this->executeInShadowDom("#shadow-dom-host", '#shadow-dom-element', 'element.style.backgroundColor = "red"; return element.innerText');
        $this->SetProperty("Name", $result);
        $this->SetBalance(100);
    }
}
