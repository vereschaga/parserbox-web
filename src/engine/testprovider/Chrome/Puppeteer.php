<?php

namespace AwardWallet\Engine\testprovider\Chrome;

use AwardWallet\Engine\testprovider\TestHelper;

class Puppeteer extends \TAccountChecker
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
        // v8 loader: https://gist.github.com/stesie/c9143b98355295420470
        $this->http->GetURL('https://loyalty.awardwallet.com/test/fetch.html');
        $executor = $this->getPuppeteerExecutor();
        $json = $executor->execute(
            __DIR__ . '/puppeteer-test.js'
        );
        $this->logger->debug(var_export($json, true), ['pre' => true]);
        $this->logger->info(json_encode($json));

        return true;
    }

    public function Login()
    {
        $this->SetBalance(1);
    }
}
