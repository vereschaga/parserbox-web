<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class Chrome100Mac extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->recordRequests = true;
//        $this->http->SetProxy("ya.ru:80");
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->logger->info("updated parser");
        $this->http->GetURL('https://s3.amazonaws.com/awardwallet-public/healthcheck.html');
        $this->http->driver->browserCommunicator->getRecordedRequests();
        $this->driver->executeAsyncScript("console.log('World'); sendResponse('ok');");

        return $this->http->FindPreg("#Health check#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
