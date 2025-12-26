<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class Chrome95WebDriver extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumRequest->setWebDriverCluster(true);
//        $this->http->SetProxy("ya.ru:80");
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->saveScreenshots = true;
        $this->http->GetURL('http://www.ipinfo.io');

        return $this->http->FindPreg("#BROWSER DETAILS#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
