<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class Chrome100 extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
//        $this->http->SetProxy("ya.ru:80");
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->logger->info("updated parser");
        $this->http->GetURL('http://www.browser-info.net');

        return $this->http->FindPreg("#BROWSER DETAILS#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
