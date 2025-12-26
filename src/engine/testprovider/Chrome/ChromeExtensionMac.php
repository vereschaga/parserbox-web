<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class ChromeExtensionMac extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useChromeExtension(\SeleniumFinderRequest::CHROME_EXTENSION_DEFAULT);
        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
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
        $this->driver->executeAsyncScript("alert('World')");

        return $this->http->FindPreg("#Health check#ims");
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
