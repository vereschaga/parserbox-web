<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class Chrome84 extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
//        $this->http->SetProxy("ya.ru:80");
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('https://awardwallet-public.s3.amazonaws.com/healthcheck.html');

        return $this->http->FindPreg("#Health check#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
