<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\Success;

class KeepProfile extends Success
{
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->UseSelenium();
    }

    public function Parse()
    {
        $this->http->GetURL("https://awardwallet-public.s3.amazonaws.com/healthcheck.html");
        $this->SetBalance($this->driver->executeScript("var value = localStorage.getItem('test_counter'); value = Math.round(value) + 1; localStorage.setItem('test_counter', value); return value;"));
    }
}
