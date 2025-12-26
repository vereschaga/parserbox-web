<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;
use ChromeDevtoolsProtocol\Context;
use ChromeDevtoolsProtocol\DevtoolsClient;
use ChromeDevtoolsProtocol\Model\Page\NavigateRequest;

class ChromeDevTools extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use TestHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        $this->usePacFile(false);
        $this->ArchiveLogs = true;
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('http://dcc1-2a00-1fa2-49f-cb06-dda5-a118-8661-f04e.eu.ngrok.io/admin/fetch.html');
        $ctx = Context::withTimeout(Context::background(), 20 /* seconds */);
        $devTools = new DevtoolsClient('ws://' . $this->http->driver->getServerAddress() . '/devtools/' . $this->getWebDriver()->getSessionID() . '/page');
        $devTools->page()->enable($ctx);
        $cookies = $devTools->page()->getCookies($ctx);
        $this->logger->info("cookies: " . json_encode($cookies));
        $devTools->page()->addDownloadProgressListener(function ($event) {
            $this->logger->info("download progress: " . json_encode($event));
        });
        $devTools->page()->navigate($ctx, NavigateRequest::builder()->setUrl('http://dcc1-2a00-1fa2-49f-cb06-dda5-a118-8661-f04e.eu.ngrok.io/admin/fetch.html')->build());
        $event = $devTools->page()->awaitDownloadProgress($ctx);
        $this->logger->info("download progress event: " . json_encode($event));

        return true;
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
