<?php

namespace AwardWallet\Engine\testprovider\Selenium;

use AwardWallet\Engine\testprovider\TestHelper;

class EarlyStop extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('http://www.browser-info.net');

        return $this->http->FindPreg("#BROWSER DETAILS#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
        $this->stopSeleniumBrowser();
        sleep(5);
    }
}
