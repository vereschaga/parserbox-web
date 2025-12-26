<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\Success;

class Screenshot extends Success
{
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->logger->info('InitBrowser');
        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::CHROME_100);
        $this->http->saveScreenshots = true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://awardwallet-public.s3.amazonaws.com/healthcheck.html");
//        $this->holdSession();
        $this->logger->info("url: " . $this->http->currentUrl());
//        $this->AskQuestion("Question", "SomeMessage", "TestStep");
        $this->SetBalance(1);
    }

    public function ProcessStep($step)
    {
        $this->logger->info("processing step $step");
        $this->logger->info("url: " . $this->http->currentUrl());
        $this->http->GetURL("https://awardwallet-public.s3.amazonaws.com/healthcheck.html");
        $this->SetBalance(2);
    }

}
